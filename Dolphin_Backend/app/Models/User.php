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

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'force_password_change' => 'boolean',
            'trial_ends_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // Role relationship is defined on the model directly for clarity. The
    // HasRoles trait provides helper methods (hasRole/hasAnyRole) and will
    // rely on this relationship when evaluating permissions.
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_users');
    }

    // Add role helper trait so middleware can call hasRole/hasAnyRole
    // without errors.
    use HasRoles;

    // Legacy relationship (organization_users pivot) retained for backward compatibility.
    // Prefer organizationMemberships() which uses organization_member.
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_users')
            ->withPivot('status')
            ->withTimestamps();
    }

    // Primary organization membership relation (replacement for organizations()).
    public function organizationMemberships(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_member')
            ->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    // Optional geographic relations (used by some eager loads). These will
    // safely resolve to null when the corresponding foreign keys are absent.
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function organizationAssessments(): HasMany
    {
        return $this->hasMany(OrganizationAssessment::class);
    }

    public function assessmentResponses(): HasMany
    {
        return $this->hasMany(AssessmentResponse::class);
    }

    public function assessmentResults(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function leadNotes(): HasMany
    {
        return $this->hasMany(LeadNote::class, 'created_by');
    }

    public function managedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'sales_person_id');
    }

    public function announcementsAsAdmin(): BelongsToMany
    {
        return $this->belongsToMany(Announcement::class, 'announcement_dolphin_admins', 'admin_id', 'announcement_id')
            ->withTimestamps();
    }
}
