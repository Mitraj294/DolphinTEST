<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * AssessmentResponse
 *
 * Raw user selections for a single assessment question set.
 * Grouped logically by (user_id, attempt_id) for an "attempt" sequence.
 */
class AssessmentResponse extends Model
{
    protected $fillable = [
        'user_id',
        'attempt_id',
        'assessment_id',
        'selected_options',
        'organization_assessment_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Related assessment (question set definition) */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** Link back to the organization assessment assignment when available */
    public function organizationAssessment(): BelongsTo
    {
        return $this->belongsTo(OrganizationAssessment::class, 'organization_assessment_id');
    }

    /** Timing aggregate (if stored) for an attempt id */
    public function attemptTime(): BelongsTo
    {
        return $this->belongsTo(AssessmentTime::class, 'attempt_id');
    }

    /** Per-response timing record (start/end/time_spent) */
    public function time(): HasOne
    {
        return $this->hasOne(AssessmentTime::class);
    }

    /**
     * Check whether a given user has previously submitted responses for an assessment.
     *
     * @param int|string $userId
     * @param int|string $assessmentId
     * @return bool
     */
    public static function hasUserSubmitted($userId, $assessmentId): bool
    {
        return self::where('user_id', $userId)->where('assessment_id', $assessmentId)->exists();
    }
}
