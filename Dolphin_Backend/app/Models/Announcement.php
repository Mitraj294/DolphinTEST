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
        // schedule_time is stored as a SQL TIME column; cast to string and
        // treat scheduled_at via the accessor which combines date + time.
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

    /**
     * Backwards-compatible accessor so code referencing ->body works while the
     * underlying column is `message`.
     */
    public function getBodyAttribute()
    {
        return $this->attributes['message'] ?? null;
    }

    public function setBodyAttribute($value)
    {
        $this->attributes['message'] = $value;
    }

    /**
     * Provide a convenient scheduled_at virtual attribute that maps to
     * schedule_date + schedule_time when available.
     */
    public function getScheduledAtAttribute()
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

    public function setScheduledAtAttribute($value)
    {
        if (!$value) {
            return;
        }
        try {
            $dt = Carbon::parse($value);
            $this->attributes['schedule_date'] = $dt->toDateString();
            // store only the time portion in the schedule_time column
            $this->attributes['schedule_time'] = $dt->format('H:i:s');
        } catch (\Exception $e) {
            // ignore invalid values
        }
    }
}
