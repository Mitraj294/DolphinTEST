<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'plan_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'status',
        'trial_ends_at',
        'ends_at',
        'started_at',
        'current_period_end',
        'cancel_at_period_end',
        'is_paused',
        'default_payment_method_id',
        'payment_method_type',
        'payment_method_brand',
        'payment_method_last4',
        'payment_method_label',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
        'started_at' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'is_paused' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /** Determine if subscription is currently active. */
    public function isActive(): bool
    {
        $active = $this->status === 'active';
        if (!$active) {
            return false;
        }

        // If paused or canceled at period end and we're past the end date, treat as inactive.
        $periodEnded = $this->cancel_at_period_end && $this->current_period_end && $this->current_period_end->isPast();
        if ($periodEnded) {
            return false;
        }

        $hasEnded = $this->ends_at && $this->ends_at->isPast();
        if ($hasEnded) {
            return false;
        }

        return true;
    }

    /** Determine if subscription is in trial period. */
    public function isInTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /** Remaining trial days (integer) or null if not in trial. */
    public function remainingTrialDays(): ?int
    {
        if (!$this->isInTrial()) {
            return null;
        }
        return now()->diffInDays($this->trial_ends_at, false);
    }

    /** Remaining active period days (integer) or null if not active. */
    public function remainingPeriodDays(): ?int
    {
        $end = $this->current_period_end ?? $this->ends_at;
        if (!$end) {
            return null;
        }
        if ($end->isPast()) {
            return 0;
        }
        return now()->diffInDays($end, false);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where(function ($q) {
            $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
        });
    }
}
