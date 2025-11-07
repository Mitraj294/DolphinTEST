<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'event_id',
        'type',
        'payload',
        'processed',
        'error',
    ];

    protected $casts = [
        'processed' => 'boolean',
        'payload' => 'array',
    ];
}
