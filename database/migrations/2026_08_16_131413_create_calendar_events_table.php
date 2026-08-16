<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();

            $table->string('external_id')->nullable()->unique();

            $table->string('name');
            $table->string('country', 50)->nullable();
            
            $table->string('currency', 10)->nullable();
            $table->text('url')->nullable();

            $table->enum('impact', [
                'high',
                'medium',
                'low',
                'holiday',
                'unknown',
            ])->default('unknown');

            $table->dateTime('event_at')->nullable();

            $table->string('display_date')->nullable();

            $table->string('display_time')->nullable();

            $table->string('actual')->nullable();

            $table->string('forecast')->nullable();

            $table->string('previous')->nullable();

            $table->date('week_start')->nullable();

            $table->dateTime('weekly_notified_at')->nullable();

            $table->dateTime('today_notified_at')->nullable();

            $table->timestamps();

            $table->index([
                'event_at',
                'currency',
                'impact',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};