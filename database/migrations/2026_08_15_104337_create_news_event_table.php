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
        Schema::create('news_event', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable();
            $table->text('url');
            $table->char('url_hash', 64)->unique();
            $table->text('headline');
            $table->string('source', 100)->default('Forex Factory');
            $table->enum('impact', ['high', 'medium', 'low', 'unknown'])
                ->default('unknown');
            $table->text('preview')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('fetched_at');
            $table->boolean('is_relevant')->default(false);
            $table->decimal('gold_score', 5, 2)->nullable();
            $table->decimal('usd_sentiment', 5, 2)->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('fed_expectation', 30)->nullable();
            $table->text('analysis_reason')->nullable();
            $table->dateTime('telegram_sent_at')->nullable();
            $table->bigInteger('telegram_message_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_event');
    }
};
