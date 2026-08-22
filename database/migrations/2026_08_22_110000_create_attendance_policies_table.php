<?php
// database/migrations/2026_08_22_110000_create_attendance_policies_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->string('jenis_ptk')->nullable();
            $table->foreignId('jenis_karyawan_id')->nullable()->constrained('jenis_karyawan_master')->cascadeOnDelete();
            $table->time('jam_masuk');
            $table->time('jam_pulang')->nullable();
            $table->unsignedInteger('toleransi_menit')->default(0);
            $table->json('hari_kerja')->nullable();
            $table->timestamps();

            $table->unique(['yayasan_id', 'lembaga_id', 'jenis_ptk', 'jenis_karyawan_id'], 'attendance_policy_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_policies');
    }
};
