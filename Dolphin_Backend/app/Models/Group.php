<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'description',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationAssessment::class, 'organization_assessment_group')
            ->withTimestamps();
    }

    public function announcements(): BelongsToMany
    {
        return $this->belongsToMany(Announcement::class, 'announcement_groups')
            ->withPivot('member_ids')
            ->withTimestamps();
    }
}
