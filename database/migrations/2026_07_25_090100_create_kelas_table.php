<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->string('nama');
            $table->string('tingkat')->nullable();
            $table->unsignedBigInteger('pola_jam_id')->nullable()->index();
            $table->foreignId('wali_kelas_guru_id')->nullable()->constrained('guru')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'nama']);
            $table->index(['lembaga_id', 'tahun_ajaran_id'], 'idx_kelas_lembaga_ta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
