<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Lead;
use App\Models\User;
use App\Models\Role;
use App\Models\Organization;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Exception;
use App\Services\UrlBuilder;
class SendAgreementController extends Controller
{

    public function send(Request $request)
    {
        try {
            $validated = $request->validate([
                'to'      => 'required|email',
                'subject' => 'required|string',
                'body'    => 'required|string',
                'price_id' => 'nullable|string',
                'name'    => 'required|string',
                'lead_id' => 'nullable|integer|exists:leads,id',
            ]);
            Log::info('SendAgreementController@send called', $validated);

            $lead = $this->resolveLead($validated);
            $user = $this->createOrFindUser($validated);
            $plansUrl = $this->buildPlansUrl($user, $validated);
            $validated['checkout_url'] = $plansUrl;
            $htmlBody = $this->prepareEmailBody($validated, $plansUrl);
            $this->logEmailPreview($validated, $htmlBody);
            if (env('DISABLE_EXTERNAL_CALLS', false)) {
                Log::info('Skipping sending agreement email because DISABLE_EXTERNAL_CALLS is set', ['to' => $validated['to']]);
            } else {
                try {
                    Mail::html($htmlBody, function ($message) use ($validated) {
                        $message->to($validated['to'])->subject($validated['subject']);
                    });
                } catch (\Throwable $e) {
                    Log::warning('Mail::html failed in SendAgreementController: ' . $e->getMessage(), ['to' => $validated['to']]);
                }
            }

            $responsePayload = [
                'message' => 'Agreement email sent',
                'mailer'  => config('mail.default'),
            ];

            try {
                if ($lead) {
                    if ($user && ! empty($user->email)) {
                        $lead->status = 'Registered';
                        if (empty($lead->registered_at)) {
                            $lead->registered_at = now();
                        }
                    } else {
                        if (strtolower((string)$lead->status) !== 'registered') {
                            $lead->status = 'Agreement Sent';
                        }
                    }
                    $lead->save();
                    Log::info('Lead status updated after sending agreement', ['lead_id' => $lead->id, 'status' => $lead->status]);
                }
            } catch (Exception $e) {
                Log::warning('Failed to update lead status after sending agreement: ' . $e->getMessage());
            }

            return response()->json($responsePayload, 200);
        } catch (Exception $e) {
            Log::error('SendAgreementController@send failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    public function validateGuest(Request $request)
    {
        return response()->json([
            'valid' => false,
            'message' => 'Guest validation has been removed. Please login normally.'
        ], 410);
    }

    private function resolveLead(array $validated): ?Lead
    {
        if (! empty($validated['lead_id'])) {
            return Lead::find($validated['lead_id']);
        }

        return Lead::where('email', $validated['to'])->first();
    }

    private function createOrFindUser(array $validated)
    {
        try {
            return User::where('email', $validated['to'])->first();
        } catch (Exception $e) {
            Log::warning('createOrFindUser lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    private function buildPlansUrl(?User $user, array $validated): string
    {
    $frontend = UrlBuilder::base();
    $plansUrl = $frontend . '/register';
        $qs = [];
        if (! empty($user) && ! empty($user->email)) {
            $qs['email'] = $user->email;
        }
        if (! empty($validated['lead_id'])) {
            $qs['lead_id'] = $validated['lead_id'];
        }
        if (! empty($validated['price_id'])) {
            $qs['price_id'] = $validated['price_id'];
        }

        if (! empty($user)) {
            try {
                $currentSub = $user->subscriptions()->latest('created_at')->first();
                if ($currentSub) {
                    $qs['plan_amount'] = $currentSub->amount;
                    $qs['plan_name'] = $currentSub->plan_name;
                    if (! empty($currentSub->ends_at)) {
                        $qs['subscription_end'] = $currentSub->ends_at instanceof \Carbon\Carbon ? $currentSub->ends_at->toDateTimeString() : (string)$currentSub->ends_at;
                    } elseif (! empty($currentSub->subscription_end)) {
                        $qs['subscription_end'] = $currentSub->subscription_end instanceof \Carbon\Carbon ? $currentSub->subscription_end->toDateTimeString() : (string)$currentSub->subscription_end;
                    }
                    $qs['subscription_status'] = $currentSub->status;
                }
            } catch (Exception $e) {
                Log::warning('Failed to append subscription details to plans URL: ' . $e->getMessage());
            }
        }

        if (! empty($qs)) {
            $plansUrl .= '?' . http_build_query($qs);
        }

        if (strpos($plansUrl, 'redirect=') === false) {
            $sep = strpos($plansUrl, '?') === false ? '?' : '&';
            $plansUrl .= $sep . 'redirect=' . urlencode('/subscriptions/plans');
        }

        return $plansUrl;
    }

    private function logEmailPreview(array $validated, string $htmlBody): void
    {
        try {
            $hrefs = [];
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body>' . $htmlBody . '</body></html>';
            $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            foreach ($dom->getElementsByTagName('a') as $a) {
                $href = $a->getAttribute('href');
                if ($href) {
                    $hrefs[] = $href;
                }
            }
            libxml_clear_errors();
            Log::info('SendAgreementController: final email HTML preview', [
                'to'          => $validated['to'] ?? null,
                'checkout_url' => $validated['checkout_url'] ?? null,
                'hrefs'       => array_values(array_unique(array_filter($hrefs))),
                'snippet'     => substr(strip_tags($htmlBody), 0, 500),
            ]);
        } catch (Exception $e) {
            Log::warning('SendAgreementController: failed to parse final HTML for logging: ' . $e->getMessage());
        }
    }

    private function prepareEmailBody(array $validated, string $checkoutUrl): string
    {
        $htmlBody = $validated['body'] ?? '';
        $placeholders = [
            '{{checkout_url}}',
            '{{checkoutUrl}}',
            '{{checkouturl}}',
            '{{name}}',
            '{{plans_url}}'
        ];
        $replacements = [
            $checkoutUrl,
            $checkoutUrl,
            $checkoutUrl,
            $validated['name'] ?? '',
            $checkoutUrl
        ];
        $htmlBody = str_replace($placeholders, $replacements, $htmlBody);

        $pattern = '/https?:\/\/[\w:\.\-@]+\/subscriptions\/plans(?:\?[^"\'\s<>]*)?/i';
        $htmlBody = preg_replace($pattern, $checkoutUrl, $htmlBody);
        $htmlBody = str_replace('/subscriptions/plans', $checkoutUrl, $htmlBody);
        $checkoutUrlHtmlAttr = htmlspecialchars($checkoutUrl, ENT_QUOTES, 'UTF-8');
        $hrefPattern = "/href=(['\"])(?:https?:\\/\\/[^\"']+)?\\/subscriptions\\/plans(?:\\?[^\"']*)?\\1/i";
        $htmlBody = preg_replace($hrefPattern, 'href=$1' . $checkoutUrlHtmlAttr . '$1', $htmlBody);
        try {
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body>' . $htmlBody . '</body></html>';
            $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            foreach ($dom->getElementsByTagName('a') as $a) {
                $href = $a->getAttribute('href');
                if ($href && preg_match('/\/subscriptions\/plans/i', $href)) {
                    $a->setAttribute('href', $checkoutUrl);
                }
            }
            libxml_clear_errors();
            $htmlBody = '';
            foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $child) {
                $htmlBody .= $dom->saveHTML($child);
            }
        } catch (\Exception $e) {
            Log::warning('prepareEmailBody DOM parsing failed: ' . $e->getMessage());
        }

        return $htmlBody;
    }
}
