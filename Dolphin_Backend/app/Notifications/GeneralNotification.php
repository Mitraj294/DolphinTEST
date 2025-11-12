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

    
    public function via($notifiable)
    {
        $channels = [];

        
        if ($notifiable instanceof \App\Models\User) {
            $channels[] = 'database';
            if ($this->hasValidEmail($notifiable->email ?? null)) {
                $channels[] = 'mail';
            }
            return array_unique($channels);
        }

        
        try {
            
            if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
                $route = $notifiable->routeNotificationFor('mail');
                if ($this->routeHasValidEmail($route)) {
                    $channels[] = 'mail';
                }
                return array_unique($channels);
            }
        } catch (\Exception $e) {
            
            Log::warning('[Notification] Failed to inspect AnonymousNotifiable route', ['error' => $e->getMessage()]);
        }

        
        if (is_object($notifiable) && property_exists($notifiable, 'email') && $this->hasValidEmail($notifiable->email)) {
            $channels[] = 'mail';
        }

        return array_unique($channels);
    }

    
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

    
    public function toMail($notifiable)
    {
        
        $displayName = $this->resolveDisplayName($notifiable);
        $subject = $this->announcement->subject ?? 'New Announcement';

        
        $toEmail = $notifiable->email ?? null;
        if (!$toEmail && $notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            try {
                $toEmail = $notifiable->routeNotificationFor('mail');
            } catch (\Exception $e) {
                // Log and continue — we couldn't resolve an email from the anonymous notifiable
                Log::warning('[Notification] Failed to resolve email for AnonymousNotifiable', ['error' => $e->getMessage()]);
            }
        }

        
        return (new AnnouncementMailable(
            $this->announcement,
            $displayName,
            $subject
        ))->to($toEmail);
    }

    
    private function hasValidEmail($email): bool
    {
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    
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
