<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'size',
        'referral_source_id',
        'referral_other_text',
        'contract_start',
        'contract_end',
        'sales_person_id',
        'last_contacted',
        'certified_staff',
        'user_id',
    ];

    protected $casts = [
        'contract_start' => 'date',
        'contract_end' => 'date',
        'last_contacted' => 'datetime',
    ];

    public function referralSource(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class);
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_person_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): HasOne
    {
        return $this->hasOne(OrganizationAddress::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_member')
            ->withTimestamps();
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(OrganizationAssessment::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function announcements(): BelongsToMany
    {
        return $this->belongsToMany(Announcement::class, 'announcement_organizations')
            ->withTimestamps();
    }
}
