<?php
// database/migrations/2026_08_04_170000_create_kasus_sesi_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasus_sesi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kasus_id')->constrained('kasus')->cascadeOnDelete();
            $table->dateTime('dijadwalkan_pada');
            $table->enum('peserta', ['siswa', 'orang_tua', 'keduanya']);
            $table->string('lokasi_mode');
            $table->enum('status', ['terjadwal', 'selesai', 'batal', 'tidak_hadir'])->default('terjadwal');
            $table->text('alasan_batal')->nullable();
            $table->text('catatan_internal')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasus_sesi');
    }
};
