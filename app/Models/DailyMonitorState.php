<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyMonitorState extends Model
{
    protected $fillable = [
        'date',
        'gold_score',
        'usd_sentiment',
        'confidence',
        'fed_expectation',
        'macro_state',
        'daily_big_alert_sent',
        'last_request_at',
        'requests_count',
        'monitoring_started_at',
        'monitoring_stopped_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',

            'gold_score' => 'decimal:2',
            'usd_sentiment' => 'decimal:2',
            'confidence' => 'decimal:2',

            'daily_big_alert_sent' => 'boolean',

            'last_request_at' => 'datetime',

            'monitoring_started_at' => 'datetime',
            'monitoring_stopped_at' => 'datetime',
        ];
    }
}