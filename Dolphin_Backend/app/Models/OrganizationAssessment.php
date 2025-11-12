<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationAssessment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'organization_id',
        'name',
        'date',
        'time',
        'timezone',
        'send_at',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'string',
        'send_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'organization_assessment_group')
            ->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_assessment_member')
            ->withPivot('status', 'notified_at')
            ->withTimestamps();
    }

    public function results(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }
}
