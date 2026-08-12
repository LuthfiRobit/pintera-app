<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_key');
            $table->enum('channel', ['wa', 'email', 'database']);
            $table->json('payload')->nullable();
            $table->enum('status', ['sent', 'failed', 'skipped']);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
