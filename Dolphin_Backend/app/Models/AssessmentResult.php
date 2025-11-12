<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AssessmentResult
 *
 * Stores computed assessment scoring metrics per attempt & type:
 * - type: 'original' (self) or 'adjust'
 * - category ratios: self_*, conc_*, adj_* (A,B,C,D plus avg)
 * - decision approach: dec_approach (0..1 heuristic)
 * - total counts and raw selected word arrays for transparency/debugging
 */
class AssessmentResult extends Model
{
    protected $fillable = [
        'organization_assessment_id',
        'user_id',
        'attempt_id',
        'type',
        'self_a', 'conc_a', 'task_a', 'adj_a',
        'self_b', 'conc_b', 'task_b', 'adj_b',
        'self_c', 'conc_c', 'task_c', 'adj_c',
        'self_d', 'conc_d', 'task_d', 'adj_d',
        'self_avg', 'conc_avg', 'adj_avg', 'task_avg',
        'dec_approach',
        'self_total_count', 'conc_total_count', 'adj_total_count',
        'self_total_words', 'conc_total_words', 'adj_total_words',
    ];

    protected $casts = [
        // Arrays of normalized words (stored as JSON)
        'self_total_words' => 'array',
        'conc_total_words' => 'array',
        'adj_total_words' => 'array',
    ];

    /** Optional relationship when linked to an organization-level assessment */
    public function organizationAssessment(): BelongsTo
    {
        return $this->belongsTo(OrganizationAssessment::class);
    }

    /** Owning user of this result */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
