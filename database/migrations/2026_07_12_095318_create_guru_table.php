<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guru', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();

            $table->text('nik');
            $table->string('nik_hash', 64)->unique();
            $table->string('nuptk')->nullable()->unique();
            $table->string('nip')->nullable();
            $table->string('nama');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama')->nullable();
            $table->string('kewarganegaraan')->default('WNI');

            $table->text('alamat_jalan')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('desa_kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kabupaten_kota')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kode_pos')->nullable();

            $table->string('no_hp')->nullable();
            $table->string('email')->nullable();

            $table->enum('jenis_ptk', ['guru_kelas', 'guru_mapel', 'kepala_sekolah', 'tenaga_administrasi']);
            $table->enum('status_kepegawaian', ['PNS', 'PPPK', 'GTY', 'PTY', 'Honorer']);
            $table->string('golongan_pangkat')->nullable();
            $table->date('tmt_tugas')->nullable();
            $table->date('tmt_pns')->nullable();
            $table->enum('status_aktif', ['aktif', 'non_aktif', 'mutasi', 'pensiun'])->default('aktif');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guru');
    }
};
