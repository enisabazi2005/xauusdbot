<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_monitor_states', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('gold_score', 5, 2);
            $table->decimal('usd_sentiment', 5, 2);
            $table->decimal('confidence', 5, 2);
            $table->string('fed_expectation', 30);
            $table->string('macro_state', 30);
            $table->boolean('daily_big_alert_sent')->default(false);
            $table->dateTime('last_request_at')->nullable();
            $table->unsignedInteger('requests_count')->default(0);
            $table->dateTime('monitoring_started_at')->nullable();
            $table->dateTime('monitoring_stopped_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_monitor_states');
    }
};
