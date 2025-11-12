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
}
