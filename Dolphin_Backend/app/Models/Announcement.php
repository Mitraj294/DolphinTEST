<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class Announcement extends Model
{
    protected $fillable = [
        'message',
        'schedule_date',
        'schedule_time',
        'sender_id',
        'sent_at',
    ];

    
    protected $casts = [
        'schedule_date' => 'date',
        
        
        'schedule_time' => 'string',
        'sent_at' => 'datetime',
    ];

    
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'announcement_groups')
            ->withPivot('member_ids')
            ->withTimestamps();
    }

    
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'announcement_organizations')
            ->withTimestamps();
    }

    
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_dolphin_admins', 'announcement_id', 'admin_id')
            ->withTimestamps();
    }

    
    public function getBodyAttribute(): ?string
    {
        return $this->attributes['message'] ?? null;
    }

    
    public function setBodyAttribute(?string $body): void
    {
        $this->attributes['message'] = $body;
    }

    
    public function getScheduledAtAttribute(): ?\Carbon\Carbon
    {
        $date = $this->attributes['schedule_date'] ?? null;
        $time = $this->attributes['schedule_time'] ?? null;
        if ($date && $time) {
            try {
                return Carbon::parse($date . ' ' . $time);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    
    public function setScheduledAtAttribute($scheduledAt): void
    {
        if (!$scheduledAt) {
            return;
        }
        try {
            $dt = Carbon::parse($scheduledAt);
            $this->attributes['schedule_date'] = $dt->toDateString();
            
            $this->attributes['schedule_time'] = $dt->format('H:i:s');
        } catch (\Exception $e) {
            
        }
    }
}
