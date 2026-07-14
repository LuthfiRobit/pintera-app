<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calon_murid_id')->constrained('calon_murid');
            $table->foreignId('lembaga_id')->constrained('lembaga');
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran');
            $table->foreignId('jalur_ppdb_id')->constrained('jalur_ppdb');
            $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb');
            $table->string('kode_pendaftaran')->unique();
            $table->string('email_pendaftaran');
            $table->enum('status', ['menunggu_verifikasi', 'diterima', 'ditolak', 'daftar_ulang', 'aktif'])
                ->default('menunggu_verifikasi');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['calon_murid_id', 'gelombang_ppdb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendaftaran');
    }
};
