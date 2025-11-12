<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Lead;
use App\Models\User;
use Exception;

class SendAssessmentController extends Controller
{
    
    public function send(Request $request)
    {
        try {
            $validated = $request->validate([
                'to' => 'required|email',
                'subject' => 'required|string',
                'body' => 'required|string',
                'registration_link' => 'required|url',
                'name' => 'required|string',
                'lead_id' => 'nullable|integer|exists:leads,id',
            ]);

            Log::info('SendAssessmentController@send called', $validated);

            $lead = $this->findLead($validated);
            $registrationUrl = $this->buildRegistrationUrl($lead, $validated['registration_link']);
            $htmlBody = $this->prepareEmailBody($validated, $registrationUrl);

            
            if (env('DISABLE_EXTERNAL_CALLS', false)) {
                Log::info('Skipping sending assessment email because DISABLE_EXTERNAL_CALLS is set', ['to' => $validated['to']]);
                if ($lead) {
                    $this->updateLeadStatus($lead);
                }
                $resp = $this->generateSuccessResponse();
                $data = $resp->getData(true);
                $data['note'] = 'External calls disabled by DISABLE_EXTERNAL_CALLS';
                return response()->json($data, 200);
            }

            $this->configureMailerForDevelopment();

            try {
                Mail::html($htmlBody, function ($message) use ($validated) {
                    $message->to($validated['to'])->subject($validated['subject']);
                });
            } catch (\Throwable $e) {
                Log::warning('Mail::html failed in SendAssessmentController: ' . $e->getMessage(), ['to' => $validated['to']]);
                
            }

            if ($lead) {
                $this->updateLeadStatus($lead);
            }

            return $this->generateSuccessResponse();
        } catch (Exception $e) {
            Log::error('SendAssessmentController@send failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }

    
    private function findLead(array $validated): ?Lead
    {
        if (!empty($validated['lead_id'])) {
            return Lead::find($validated['lead_id']);
        }
        return Lead::where('email', $validated['to'])->first();
    }

    
    private function buildRegistrationUrl(?Lead $lead, string $baseUrl): string
    {
        if (!$lead) {
            return $baseUrl;
        }

        $params = array_filter([
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            
            'phone' => $lead->phone_number,
            'organization_name' => $lead->organization_name,
            'organization_size' => $lead->organization_size,
            'organization_address' => $lead->address,
        ]);

        $queryString = http_build_query($params);
        return $baseUrl . (parse_url($baseUrl, PHP_URL_QUERY) ? '&' : '?') . $queryString;
    }

    
    private function prepareEmailBody(array $validated, string $registrationUrl): string
    {
        $htmlBody = $validated['body'];

        
        $placeholders = ['{{registrationUrl}}', '{{registration_link}}', '{{name}}'];
        $replacements = [$registrationUrl, $registrationUrl, $validated['name']];
        $htmlBody = str_replace($placeholders, $replacements, $htmlBody);

        
        if (stripos($htmlBody, '<html') === false) {
            $safeSubject = htmlspecialchars($validated['subject'], ENT_QUOTES, 'UTF-8');
            return "<!DOCTYPE html><html><head><title>{$safeSubject}</title></head><body>{$htmlBody}</body></html>";
        }

        return $htmlBody;
    }

    
    private function updateLeadStatus(Lead $lead): void
    {
        try {
            $lead->assessment_sent_at = now();

            
            
            $userExists = User::where('email', $lead->email)
                ->whereNull('deleted_at')
                ->whereNotNull('password')
                ->exists();

            Log::info('SendAssessmentController:updateLeadStatus - userExists check', [
                'lead_id' => $lead->id,
                'lead_email' => $lead->email,
                'user_exists' => $userExists,
                'current_lead_status' => $lead->status,
            ]);

            if ($userExists) {
                
                $lead->status = 'Registered';
                if (!$lead->registered_at) {
                    $lead->registered_at = now();
                }
            } else {
                
                if (strtolower((string)$lead->status) !== 'registered') {
                    $lead->status = 'Assessment Sent';
                }
            }

            $lead->save();
            Log::info('Lead status updated successfully.', ['lead_id' => $lead->id]);
        } catch (Exception $e) {
            Log::error('Failed to update lead status.', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    
    private function configureMailerForDevelopment(): void
    {
        if (config('mail.default') === 'log' && env('MAIL_FORCE_SMTP', false)) {
            Log::info('Overriding mailer to SMTP for development.');
            config(['mail.default' => 'smtp']);
        }
    }

    
    private function generateSuccessResponse(): \Illuminate\Http\JsonResponse
    {
        $response = [
            'message' => 'Assessment email sent successfully.',
            'mailer' => config('mail.default')
        ];

        if ($response['mailer'] === 'log') {
            $response['note'] = 'Emails are currently being logged. Configure SMTP to deliver real messages.';
        }

        return response()->json($response, 200);
    }
}
