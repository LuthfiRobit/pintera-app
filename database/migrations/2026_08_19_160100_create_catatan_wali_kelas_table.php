<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan_wali_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->text('catatan_sikap')->nullable();
            $table->text('catatan_perkembangan')->nullable();
            $table->decimal('tinggi_badan_cm', 5, 1)->nullable();
            $table->decimal('berat_badan_kg', 5, 1)->nullable();
            $table->decimal('lingkar_kepala_cm', 5, 1)->nullable();
            $table->json('ekstrakurikuler')->nullable();
            $table->json('prestasi')->nullable();
            $table->json('pkl_info')->nullable();
            $table->string('keterangan_kenaikan', 50)->nullable();
            $table->timestamps();

            $table->unique(['siswa_id', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_wali_kelas');
    }
};
