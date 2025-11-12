<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AssessmentTime
 *
 * Optional timing metadata captured for responses/attempts
 * (start_time, end_time, time_spent in seconds).
 */
class AssessmentTime extends Model
{
    protected $fillable = [
        'assessment_response_id',
        'start_time',
        'end_time',
        'time_spent',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /** Parent assessment response */
    public function assessmentResponse(): BelongsTo
    {
        return $this->belongsTo(AssessmentResponse::class);
    }
}
