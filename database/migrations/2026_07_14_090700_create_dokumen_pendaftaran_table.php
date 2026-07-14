<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->cascadeOnDelete();
            $table->foreignId('dokumen_syarat_ppdb_id')->constrained('dokumen_syarat_ppdb');
            $table->string('file_path');
            $table->string('nama_file_asli');
            $table->string('mime_type');
            $table->unsignedInteger('ukuran_bytes');
            $table->enum('status_verifikasi', ['belum_diverifikasi', 'diterima', 'ditolak'])
                ->default('belum_diverifikasi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_pendaftaran');
    }
};
