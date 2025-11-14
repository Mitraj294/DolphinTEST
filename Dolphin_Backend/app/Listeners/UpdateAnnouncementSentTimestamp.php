<?php

namespace App\Listeners;

use App\Notifications\GeneralNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateAnnouncementSentTimestamp
{
    public function __construct()
    {
        // No construction-time initialization required for this listener.
        // The listener operates statelessly in response to events.
    }


    public function handle(NotificationSent $event): void
    {
        if ($event->notification instanceof GeneralNotification) {
            try {


                $announcement = $event->notification->getAnnouncement();
                $announcementId = $announcement->id ?? null;

                Log::info('[UpdateAnnouncementSentTimestamp] NotificationSent event received', [
                    'announcement_id' => $announcementId,
                    'channel' => $event->channel ?? null,
                    'notifiable' => is_object($event->notifiable) ? get_class($event->notifiable) : (string) $event->notifiable,
                ]);

                if ($announcementId) {
                    $updated = DB::table('announcements')
                        ->where('id', $announcementId)
                        ->whereNull('sent_at')
                        ->update(['sent_at' => now()]);

                    if ($updated) {
                        Log::info('[UpdateAnnouncementSentTimestamp] announcement.sent_at updated', ['announcement_id' => $announcementId]);
                    } else {
                        Log::debug('[UpdateAnnouncementSentTimestamp] announcement.sent_at not updated (already set or missing)', ['announcement_id' => $announcementId]);
                    }
                } else {
                    Log::warning('[UpdateAnnouncementSentTimestamp] no announcement id on notification', []);
                }
            } catch (\Exception $e) {

                Log::error('[UpdateAnnouncementSentTimestamp] failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
