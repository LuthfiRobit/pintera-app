<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sertifikasi_guru', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->string('jenis_sertifikasi');
            $table->string('nomor_sertifikat')->nullable();
            $table->string('bidang_studi_sertifikasi')->nullable();
            $table->string('nrg')->nullable();
            $table->unsignedSmallInteger('tahun_sertifikasi')->nullable();
            $table->string('kode_lembaga_sertifikasi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sertifikasi_guru');
    }
};
