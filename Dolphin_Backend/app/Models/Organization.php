<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends Model
{
    use HasFactory;
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

    /**
     * @var array<string,string>
     */

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ReferralSource, \App\Models\Organization>
     */
    public function referralSource(): BelongsTo
    {
        return $this->belongsTo(ReferralSource::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\Organization>
     */
    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_person_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, \App\Models\Organization>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\OrganizationAddress, \App\Models\Organization>
     */
    public function address(): HasOne
    {
        return $this->hasOne(OrganizationAddress::class);
    }

    // Legacy relationship (organization_users pivot). Prefer members().
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\User, \App\Models\Organization>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('status')
            ->withTimestamps();
    }

    // Primary membership pivot used across Groups and Assessments
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\User, \App\Models\Organization>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_member')
            ->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\OrganizationAssessment, \App\Models\Organization>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(OrganizationAssessment::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Lead, \App\Models\Organization>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Group, \App\Models\Organization>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Announcement, \App\Models\Organization>
     */
    public function announcements(): BelongsToMany
    {
        return $this->belongsToMany(Announcement::class, 'announcement_organizations')
            ->withTimestamps();
    }

    /**
     * Latest active subscription for the organization owner (user_id).
     *
     * Some parts of the application eager-load `activeSubscription` from
     * Organization, but subscriptions are stored per user. Expose a has-one
     * relation that points to the owning user's latest active subscription so
     * `with('activeSubscription')` works and middleware can query it.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\Subscription, \App\Models\Organization>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'user_id', 'user_id')
            ->where('status', 'active')
            ->latestOfMany('created_at');
    }
}
