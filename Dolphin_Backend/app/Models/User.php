<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasRoles;

/**
 * @mixin \Eloquent
 * @property int $id
 * @property string $email
 * @method static static findOrFail($id)
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use HasApiTokens;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'organization_id',
        'email_verified_at',
        'status',
        'password',
        'last_login_at',
        'force_password_change',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'force_password_change' => 'boolean',
        'trial_ends_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Role relationship is defined on the model directly for clarity. The
    // HasRoles trait provides helper methods (hasRole/hasAnyRole) and will
    // rely on this relationship when evaluating permissions.
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_users');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Role, \App\Models\User>
     */
    

    // Add role helper trait so middleware can call hasRole/hasAnyRole
    // without errors.

    // Legacy relationship (organization_users pivot) retained for backward compatibility.
    // Prefer organizationMemberships() which uses organization_member.
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_users')
            ->withPivot('status')
            ->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Organization, \App\Models\User>
     */
    

    // Primary organization membership relation (replacement for organizations()).
    public function organizationMemberships(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_member')
            ->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Organization, \App\Models\User>
     */
    

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Group, \App\Models\User>
     */
    

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Country, \App\Models\User>
     */
    

    // Optional geographic relations (used by some eager loads). These will
    // safely resolve to null when the corresponding foreign keys are absent.
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\State, \App\Models\User>
     */
    

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\City, \App\Models\User>
     */
    

    public function organizationAssessments(): HasMany
    {
        return $this->hasMany(OrganizationAssessment::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\OrganizationAssessment, \App\Models\User>
     */
    

    public function assessmentResponses(): HasMany
    {
        return $this->hasMany(AssessmentResponse::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\AssessmentResponse, \App\Models\User>
     */
    

    public function assessmentResults(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\AssessmentResult, \App\Models\User>
     */
    

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Subscription, \App\Models\User>
     */
    

    public function leadNotes(): HasMany
    {
        return $this->hasMany(LeadNote::class, 'created_by');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\LeadNote, \App\Models\User>
     */
    

    public function managedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'sales_person_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Organization, \App\Models\User>
     */
    

    public function announcementsAsAdmin(): BelongsToMany
    {
        return $this->belongsToMany(Announcement::class, 'announcement_dolphin_admins', 'admin_id', 'announcement_id')
            ->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Announcement, \App\Models\User>
     */
    
}
