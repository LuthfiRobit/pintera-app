<?php
// database/migrations/2026_08_23_100000_create_kuota_cuti_config_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kuota_cuti_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->string('jenis_ptk')->nullable();
            $table->foreignId('jenis_karyawan_id')->nullable()->constrained('jenis_karyawan_master')->cascadeOnDelete();
            $table->unsignedInteger('jatah_hari_per_tahun');
            $table->timestamps();

            $table->unique(['yayasan_id', 'lembaga_id', 'jenis_ptk', 'jenis_karyawan_id'], 'kuota_cuti_config_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kuota_cuti_config');
    }
};
