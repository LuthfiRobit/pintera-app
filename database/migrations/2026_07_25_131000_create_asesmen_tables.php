<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asesmen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->string('jenis'); // value of JenisAsesmen enum
            $table->string('judul');
            $table->date('tanggal');
            $table->timestamps();
        });

        Schema::create('asesmen_komponen_penilaian', function (Blueprint $table) {
            $table->foreignId('asesmen_id')->constrained('asesmen')->cascadeOnDelete();
            $table->foreignId('komponen_penilaian_id')->constrained('komponen_penilaian')->cascadeOnDelete();
            $table->primary(['asesmen_id', 'komponen_penilaian_id'], 'asesmen_komponen_primary');
        });

        Schema::create('nilai_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asesmen_id')->constrained('asesmen')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('komponen_penilaian_id')->constrained('komponen_penilaian')->cascadeOnDelete();
            $table->unsignedTinyInteger('nilai_angka')->nullable(); // 0 - 100
            $table->string('predikat')->nullable(); // narrative-style grading (e.g. PAUD aspek perkembangan)
            $table->text('catatan')->nullable(); // deskripsi kualitatif
            $table->timestamps();

            $table->unique(['asesmen_id', 'siswa_id', 'komponen_penilaian_id'], 'nilai_siswa_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_siswa');
        Schema::dropIfExists('asesmen_komponen_penilaian');
        Schema::dropIfExists('asesmen');
    }
};
