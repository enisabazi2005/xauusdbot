<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsEvent extends Model
{
    protected $fillable = [
        'external_id',
        'url',
        'url_hash',
        'headline',
        'source',
        'impact',
        'preview',
        'published_at',
        'fetched_at',
        'is_relevant',
        'gold_score',
        'usd_sentiment',
        'confidence',
        'fed_expectation',
        'analysis_reason',
        'telegram_sent_at',
        'telegram_message_id',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'fetched_at' => 'datetime',
            'telegram_sent_at' => 'datetime',

            'is_relevant' => 'boolean',

            'gold_score' => 'decimal:2',
            'usd_sentiment' => 'decimal:2',
            'confidence' => 'decimal:2',
        ];
    }
}