<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('calon_murid_id')->nullable()->constrained('calon_murid')->nullOnDelete();
            $table->foreignId('pendaftaran_asal_id')->nullable()->constrained('pendaftaran')->nullOnDelete();

            $table->enum('sumber_data', ['spmb', 'import', 'manual']);
            $table->string('nis');
            $table->string('nisn')->nullable();
            $table->string('nama_lengkap');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama')->nullable();
            $table->enum('status', ['aktif', 'lulus', 'pindah', 'keluar'])->default('aktif');

            $table->timestamps();

            $table->unique(['lembaga_id', 'nis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};
