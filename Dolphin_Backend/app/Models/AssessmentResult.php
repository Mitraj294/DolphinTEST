<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin \Eloquent
 * @property int $id
 * @method static \Illuminate\Database\Eloquent\Builder|static where(string $column, $value = null)
 * @method static static create(array $attributes = [])
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
        'self_total_words' => 'array',
        'conc_total_words' => 'array',
        'adj_total_words' => 'array',
    ];

    public function organizationAssessment(): BelongsTo
    {
        return $this->belongsTo(OrganizationAssessment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
