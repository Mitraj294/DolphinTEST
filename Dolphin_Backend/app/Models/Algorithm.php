<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Algorithm extends Model
{
    protected $fillable = [
        'name',
        'version',
        'is_global',
        'description',
        'self_table',
        'conc_table',
        'adjust_table',
    ];

    protected $casts = [
        'is_global' => 'boolean',
        'self_table' => 'array',
        'conc_table' => 'array',
        'adjust_table' => 'array',
    ];
}
