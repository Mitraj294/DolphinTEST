<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    protected $table = 'assessment';

    protected $fillable = [
        'title',
        'description',
        'form_definition',
    ];

    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentResponse::class);
    }
}
