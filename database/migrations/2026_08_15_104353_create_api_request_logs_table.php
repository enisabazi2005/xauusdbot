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
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
             $table->dateTime('requested_at');
             $table->smallInteger('status')->nullable();
             $table->boolean('successful')->default(false);
             $table->unsignedInteger('stories_received')->default(0);
             $table->unsignedInteger('response_time_ms')->nullable();
             $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
