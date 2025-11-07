<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $primaryKey = 'email';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'email',
        'self_total_words',
        'conc_total_words',
        'adj_total_words',
        'self_a', 'self_b', 'self_c', 'self_d', 'self_avg',
        'conc_a', 'conc_b', 'conc_c', 'conc_d', 'conc_avg',
        'dec_approach',
        'original_test_timestamp',
        'latest_test_timestamp',
        'tests_taken_count',
    ];

    protected $casts = [
        'original_test_timestamp' => 'datetime',
        'latest_test_timestamp' => 'datetime',
    ];
}
