<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komponen_penilaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->string('kode')->nullable();
            $table->text('deskripsi');
            $table->unsignedTinyInteger('bobot')->default(10);
            $table->text('kktp')->nullable();
            $table->timestamps();

            $table->index(['lembaga_id', 'mata_pelajaran_id', 'semester_id'], 'idx_komp_lmbg_mapel_smt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komponen_penilaian');
    }
};
