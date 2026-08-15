<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    protected $fillable = [
        'requested_at',
        'status',
        'successful',
        'stories_received',
        'response_time_ms',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'successful' => 'boolean',
        ];
    }
}