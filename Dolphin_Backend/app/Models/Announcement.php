<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    protected $fillable = [
        'message',
        'schedule_date',
        'schedule_time',
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'schedule_time' => 'datetime',
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
}
