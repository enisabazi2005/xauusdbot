<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    protected $fillable = [
        'external_id',
        'name',
        'url',
        'country',
        'currency',
        'impact',
        'event_at',
        'display_date',
        'display_time',
        'actual',
        'forecast',
        'previous',
        'week_start',
        'weekly_notified_at',
        'today_notified_at',
    ];

    protected $casts = [
        'event_at' => 'datetime',
        'week_start' => 'date',
        'weekly_notified_at' => 'datetime',
        'today_notified_at' => 'datetime',
    ];
}