<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Input extends Model
{
    protected $table = 'input';
    protected $primaryKey = 'email';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'email',
        'self_words',
        'concept_words',
    ];

    protected $casts = [
        'self_words' => 'array',
        'concept_words' => 'array',
    ];
}
