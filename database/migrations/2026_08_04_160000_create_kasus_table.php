<?php
// database/migrations/2026_08_04_160000_create_kasus_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('diajukan_oleh_guru_id')->nullable()->constrained('guru')->nullOnDelete();
            $table->foreignId('diajukan_oleh_orang_tua_id')->nullable()->constrained('orang_tua')->nullOnDelete();
            $table->string('kategori_masalah');
            $table->text('deskripsi');
            $table->string('lampiran')->nullable();
            $table->enum('tingkat_urgensi', ['rendah', 'sedang', 'tinggi'])->nullable();
            $table->enum('status', ['diajukan', 'menunggu_consent', 'ditugaskan'])->default('diajukan');
            $table->foreignId('konselor_guru_id')->nullable()->constrained('guru')->nullOnDelete();
            $table->foreignId('konselor_karyawan_id')->nullable()->constrained('karyawan')->nullOnDelete();
            $table->timestamp('dikonfirmasi_pihak_lain_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasus');
    }
};
