<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @mixin \Eloquent
 * @property int $id
 * @property mixed $selected_options
 * @property int $assessment_id
 * @method static \Illuminate\Database\Eloquent\Builder|static where(string $column, $value = null)
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

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function attemptTime(): BelongsTo
    {
        return $this->belongsTo(AssessmentTime::class, 'attempt_id');
    }

    public function time(): HasOne
    {
        return $this->hasOne(AssessmentTime::class);
    }
}
