<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\GenericEmail;
use App\Services\UrlBuilder;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ScheduledEmailController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'member_id' => 'sometimes|integer|exists:users,id',
            'recipient_email' => 'sometimes|email',
            'subject' => 'required|string',
            'body' => 'required|string',
            'send_at' => 'sometimes|date',
            'assessment_id' => 'sometimes|nullable|integer',
            'group_id' => 'sometimes|integer',
        ]);

        if (empty($data['member_id']) && empty($data['recipient_email'])) {
            return response()->json(['message' => 'member_id or recipient_email is required'], 422);
        }

        $delay = null;
        if (!empty($data['send_at'])) {
            try {
                $delay = Carbon::parse($data['send_at'])->setTimezone('UTC');
            } catch (\Throwable $e) {
                Log::warning('[ScheduledEmailController] Invalid send_at provided', ['send_at' => $data['send_at']]);
            }
        }

        Log::info('[ScheduledEmailController] scheduling email', ['payload' => $data]);
        $body = isset($data['body']) ? trim((string) $data['body']) : '';
        if ($body === '') {
            $body = sprintf('You have an assessment scheduled%s', !empty($data['assessment_id']) ? '' : '.');
        }

        $actionUrl = UrlBuilder::assessmentsUrl();
        if (!empty($data['assessment_id'])) {
            $actionUrl = UrlBuilder::assessmentsUrl((int) $data['assessment_id']);
        }

        $notification = new GenericEmail($data['subject'], $body, $actionUrl);
        if ($delay) {
            $notification = $notification->delay($delay);
        }

        try {
            if (!empty($data['member_id'])) {
                $user = User::findOrFail($data['member_id']);
                $user->notify($notification);
                if (!empty($data['assessment_id'])) {
                    try {
                        DB::table('organization_assessment_member')
                            ->where('organization_assessment_id', $data['assessment_id'])
                            ->where('user_id', $data['member_id'])
                            ->update(['notified_at' => CarbonImmutable::now('UTC')]);
                    } catch (\Throwable $e) {
                        Log::warning('[ScheduledEmailController] failed to mark notified_at', ['error' => $e->getMessage(), 'assessment_id' => $data['assessment_id'], 'member_id' => $data['member_id']]);
                    }
                }
            } else {
                Notification::route('mail', $data['recipient_email'])->notify($notification);
            }
        } catch (\Throwable $e) {
            Log::error('[ScheduledEmailController] Failed to schedule email', ['error' => $e->getMessage(), 'payload' => $data]);
            return response()->json(['message' => 'Failed to schedule email'], 500);
        }

        return response()->json(['status' => 'queued'], 202);
    }
}
