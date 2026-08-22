<?php
// database/migrations/2026_08_22_090200_create_attendance_events_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->morphs('pegawai');
            $table->foreignId('attendance_point_id')->nullable()->constrained('attendance_points')->nullOnDelete();
            $table->string('method');
            $table->enum('arah', ['masuk', 'pulang']);
            $table->string('status');
            $table->dateTime('waktu');
            $table->foreignId('dicatat_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pegawai_type', 'pegawai_id', 'waktu']);
            $table->index(['lembaga_id', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};
