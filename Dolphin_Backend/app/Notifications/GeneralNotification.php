<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Mail\AnnouncementMailable;
use Illuminate\Support\Facades\Log;

class GeneralNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $announcement;

    public function __construct($announcement)
    {
        $this->announcement = $announcement;
    }

    public function getAnnouncement()
    {
        return $this->announcement;
    }

    /**
     * Notification channels
     */
    public function via($notifiable)
    {
        $channels = [];

        // If recipient is a User model, include database channel and mail if they have email
        if ($notifiable instanceof \App\Models\User) {
            $channels[] = 'database';
            if ($this->hasValidEmail($notifiable->email ?? null)) {
                $channels[] = 'mail';
            }
            return array_unique($channels);
        }

        // For anonymous notifiables or generic objects, check whether a valid email can be resolved
        try {
            // AnonymousNotifiable::routeNotificationFor('mail') may return string or array
            if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
                $route = $notifiable->routeNotificationFor('mail');
                if ($this->routeHasValidEmail($route)) {
                    $channels[] = 'mail';
                }
                return array_unique($channels);
            }
        } catch (\Exception $e) {
            // ignore and fall through to object checks
            Log::warning('[Notification] Failed to inspect AnonymousNotifiable route', ['error' => $e->getMessage()]);
        }

        // Generic object with public email property
        if (is_object($notifiable) && property_exists($notifiable, 'email') && $this->hasValidEmail($notifiable->email)) {
            $channels[] = 'mail';
        }

        return array_unique($channels);
    }

    /**
     * Store notification in database
     */
    public function toDatabase($notifiable)
    {
        return [
            'title'   => 'New Announcement',
            'message' => $this->announcement->body,
            'announcement_id' => $this->announcement->id,
            'scheduled_at' => $this->announcement->scheduled_at,
            'sent_at' => $this->announcement->sent_at,
        ];
    }

    /**
     * Use the custom Announcement Mailable so we can render a full blade template
     * and include personalization when possible.
     */
    public function toMail($notifiable)
    {
        // *** FIX: Use your own private function here ***
        $displayName = $this->resolveDisplayName($notifiable);
        $subject = $this->announcement->subject ?? 'New Announcement';

        // Get the recipient's email address
        $toEmail = $notifiable->email ?? null;
        if (!$toEmail && $notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            try {
                $toEmail = $notifiable->routeNotificationFor('mail');
            } catch (\Exception $e) {
                // ignore
            }
        }

        // Return your Mailable class instead of the default MailMessage
        return (new AnnouncementMailable(
            $this->announcement,
            $displayName,
            $subject
        ))->to($toEmail);
    }

    /**
     * Return true if the provided value is a valid email address
     */
    private function hasValidEmail($email): bool
    {
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Normalize and check routes returned by AnonymousNotifiable for a valid email
     */
    private function routeHasValidEmail($route): bool
    {
        if (is_string($route)) {
            return $this->hasValidEmail($route);
        }

        if (is_array($route)) {
            foreach ($route as $r) {
                if ($this->hasValidEmail($r)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolve a human-friendly display name for the notifiable
     */
    private function resolveDisplayName($notifiable): string
    {
        $name = '';

        if (is_string($notifiable)) {
            $name = $notifiable;
        } elseif ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            try {
                $route = $notifiable->routeNotificationFor('mail');
                $name = $this->anonymousRouteDisplayName($route);
            } catch (\Exception $e) {
                Log::warning('[Notification] Failed to read AnonymousNotifiable mail route', ['error' => $e->getMessage()]);
                $name = '';
            }
        } elseif (is_object($notifiable)) {
            $name = $this->objectDisplayName($notifiable);
        }

        return (string) $name;
    }

    /**
     * Get display name from an anonymous notifiable route value
     */
    private function anonymousRouteDisplayName($route): string
    {
        if (is_string($route)) {
            return $route;
        }

        if (is_array($route)) {
            return implode(', ', array_values($route));
        }

        return '';
    }

    /**
     * Get display name from an object notifiable
     */
    private function objectDisplayName($notifiable): string
    {
        if (property_exists($notifiable, 'name') && $notifiable->name) {
            return $notifiable->name;
        }

        $first = $notifiable->first_name ?? '';
        $last = $notifiable->last_name ?? '';
        if ($first || $last) {
            return trim($first . ' ' . $last);
        }

        return $notifiable->email ?? '';
    }
}
