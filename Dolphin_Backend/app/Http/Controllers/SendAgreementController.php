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


// SendAgreementController
// Controller for sending Stripe agreement/payment links via email,
// managing guest token validation for quick access, and preparing
// email bodies with dynamic URLs and user information.
// Structure:
//   - send()           : Send agreement/payment email
//   - validateGuest()  : Validate guest token or token string
//   - prepareEmailBody(): Replace placeholders in email HTML

class SendAgreementController extends Controller
{

    // Send an agreement email containing a Stripe payment link.
    // Handles user creation, guest token generation, and email composition.
    // @param Request $request
    // @return \Illuminate\Http\JsonResponse

    public function send(Request $request)
    {
        try {

            // 1. Request Validation

            $validated = $request->validate([
                'to'      => 'required|email',
                'subject' => 'required|string',
                'body'    => 'required|string',
                'price_id' => 'nullable|string',
                'name'    => 'required|string',
                'lead_id' => 'nullable|integer|exists:leads,id',
            ]);
            Log::info('SendAgreementController@send called', $validated);


            // 2-5: Find lead and ensure a user exists; build plans URL and guest link
            $lead = $this->resolveLead($validated);
            $user = $this->createOrFindUser($validated, $lead);
            $plansUrl = $this->buildPlansUrl($user, $validated);

            // 6. Prepare and send email
            $validated['checkout_url'] = $plansUrl;
            $htmlBody = $this->prepareEmailBody($validated, $plansUrl);

            $this->logEmailPreview($validated, $htmlBody);

            Mail::html($htmlBody, function ($message) use ($validated) {
                $message->to($validated['to'])->subject($validated['subject']);
            });

            $responsePayload = [
                'message' => 'Agreement email sent',
                'mailer'  => config('mail.default'),
            ];

            return response()->json($responsePayload, 200);
        } catch (Exception $e) {
            Log::error('SendAgreementController@send failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }


    // Validate a guest token supplied by the frontend and return basic user data.
    // Supports both guest code and full token string.
    // @param Request $request
    // @return \Illuminate\Http\JsonResponse

    public function validateGuest(Request $request)
    {
        $guestCode = $request->query('guest_code');
        if ($guestCode) {
            // NOTE: Guest code functionality removed (guest_links table deleted)
            return response()->json([
                'valid' => false,
                'message' => 'Guest code functionality has been removed. Please login normally.'
            ], 200);
        } else {
            $token = $request->query('token');
            [$responseData, $status] = $this->validateTokenFlow($token);
            return response()->json($responseData, $status);
        }
    }

    private function resolveLead(array $validated): ?Lead
    {
        if (! empty($validated['lead_id'])) {
            return Lead::find($validated['lead_id']);
        }

        return Lead::where('email', $validated['to'])->first();
    }

    private function createOrFindUser(array $validated, ?Lead $lead)
    {
        $user = User::where('email', $validated['to'])->first();
        if ($user) {
            return $user;
        }

        $passwordPlain = Str::random(12);
        $nameParts = $this->splitName($validated['name']);
        $userData = [
            'email'      => $validated['to'],
            'first_name' => $lead->first_name ?? $nameParts['first_name'],
            'last_name'  => $lead->last_name ?? $nameParts['last_name'],
            'password'   => Hash::make($passwordPlain),
        ];

        if ($lead) {
            $userData['phone'] = $lead->phone;
            $userData['country_id'] = $lead->country_id ?? null;
        }

        $user = User::create($userData);

        try {
            $role = Role::where('name', 'organizationadmin')->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }
        } catch (Exception $e) {
            Log::warning('Failed to attach role: ' . $e->getMessage());
        }

        return $user;
    }

    private function buildPlansUrl($user, array $validated): string
    {
        $frontend = env('FRONTEND_URL', 'http://127.0.0.1:8080');
        $plansUrl = $frontend . '/subscriptions/plans';
        $qs = [];
        if (! empty($user->email)) {
            $qs['email'] = $user->email;
        }
        if (! empty($validated['lead_id'])) {
            $qs['lead_id'] = $validated['lead_id'];
        }
        if (! empty($validated['price_id'])) {
            $qs['price_id'] = $validated['price_id'];
        }

        // NOTE: Guest link functionality removed (guest_links table deleted)
        // Users must authenticate normally to access plans

        // add subscription details if available
        try {
            $currentSub = $user->subscriptions()->latest('created_at')->first();
            if ($currentSub) {
                $qs['plan_amount'] = $currentSub->amount;
                $qs['plan_name'] = $currentSub->plan_name;
                // Prefer ends_at property on the subscription model
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

        if (! empty($qs)) {
            $plansUrl .= '?' . http_build_query($qs);
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

    private function validateTokenFlow($token): array
    {
        $responseData = ['valid' => false];
        $status = 200;

        if (! $token) {
            return [['error' => 'Missing token or guest_code'], 400];
        }

        $foundUser = null;

        try {
            $accessTokenModel = $this->resolveAccessTokenModelFromToken($token);
            if ($accessTokenModel) {
                $now = Carbon::now();
                $expiresAt = isset($accessTokenModel->expires_at) ? Carbon::parse($accessTokenModel->expires_at) : null;
                if (empty($accessTokenModel->revoked) && (! $expiresAt || $expiresAt->gt($now))) {
                    $foundUser = \App\Models\User::find($accessTokenModel->user_id);
                }
            }
        } catch (\Exception $e) {
            Log::warning('validateGuest token check failed: ' . $e->getMessage());
        }

        if (! $foundUser) {
            try {
                $user = User::where('remember_token', $token)->first();
                if ($user) {
                    $foundUser = $user;
                }
            } catch (\Exception $e) {
                Log::warning('validateGuest remember_token fallback failed: ' . $e->getMessage());
            }
        }

        if ($foundUser) {
            $responseData = [
                'valid' => true,
                'user' => [
                    'id'        => $foundUser->id,
                    'email'     => $foundUser->email,
                    'first_name' => $foundUser->first_name,
                    'last_name' => $foundUser->last_name,
                ],
            ];
        }

        return [$responseData, $status];
    }

    private function resolveAccessTokenModelFromToken(string $token)
    {
        $accessTokenModel = null;

        if (strpos($token, '|') !== false) {
            [$idPart] = explode('|', $token, 2);
            $accessTokenModel = DB::table('oauth_access_tokens')->where('id', $idPart)->first();
        }

        if (! $accessTokenModel && substr_count($token, '.') === 2) {
            try {
                $parts = explode('.', $token);
                $payloadB64 = $parts[1] ?? null;
                $payloadJson = $payloadB64 ? base64_decode(strtr($payloadB64, '-_', '+/')) : null;
                $payload = json_decode($payloadJson, true);
                if (is_array($payload) && ! empty($payload['jti'])) {
                    $accessTokenModel = DB::table('oauth_access_tokens')->where('id', $payload['jti'])->first();
                }
            } catch (\Exception $e) {
                // ignore malformed JWTs here
            }
        }

        if (! $accessTokenModel) {
            $accessTokenModel = DB::table('oauth_access_tokens')->where('id', $token)->first();
        }

        return $accessTokenModel;
    }


    // Prepare the final HTML content for the send-agreement email.
    // Replaces placeholders like {{checkout_url}} and {{name}}.
    // @param array $validated
    // @param string $checkoutUrl
    // @return string

    private function prepareEmailBody(array $validated, string $checkoutUrl): string
    {
        $htmlBody = $validated['body'] ?? '';
        // Replace common placeholders
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

        // Replace plans page URL in hrefs and plain text
        $pattern = '/https?:\/\/[\w:\.\-@]+\/subscriptions\/plans(?:\?[^"\'\s<>]*)?/i';
        $htmlBody = preg_replace($pattern, $checkoutUrl, $htmlBody);
        $htmlBody = str_replace('/subscriptions/plans', $checkoutUrl, $htmlBody);

        // Replace anchor hrefs with final URL
        $checkoutUrlHtmlAttr = htmlspecialchars($checkoutUrl, ENT_QUOTES, 'UTF-8');
        $hrefPattern = "/href=(['\"])(?:https?:\\/\\/[^\"']+)?\\/subscriptions\\/plans(?:\\?[^\"']*)?\\1/i";
        $htmlBody = preg_replace($hrefPattern, 'href=$1' . $checkoutUrlHtmlAttr . '$1', $htmlBody);

        // Ensure anchor hrefs point to the final URL using DOM parsing
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


    // Helper to split a full name into first and last name.
    // @param string $name
    // @return array ['first_name' => ..., 'last_name' => ...]

    private function splitName($name)
    {
        $parts = explode(' ', trim($name));
        $first = $parts[0] ?? '';
        $last = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
        return ['first_name' => $first, 'last_name' => $last];
    }
}
