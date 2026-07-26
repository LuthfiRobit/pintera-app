<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_pembelajaran_id')->constrained('sesi_pembelajaran')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa', 'terlambat'])->default('hadir');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['sesi_pembelajaran_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi');
    }
};
