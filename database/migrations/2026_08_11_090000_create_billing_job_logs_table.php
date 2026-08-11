<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_job_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->cascadeOnDelete();
            $table->enum('trigger_type', ['cron', 'manual', 'event']);
            $table->string('trigger_event')->nullable();
            $table->string('period', 7)->nullable();
            $table->unsignedInteger('bills_generated');
            $table->enum('status', ['success', 'partial', 'failed']);
            $table->json('error_log')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_job_logs');
    }
};
