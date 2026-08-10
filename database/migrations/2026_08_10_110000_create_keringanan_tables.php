<?php
// database/migrations/2026_08_10_110000_create_keringanan_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_keringanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('nama');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('jenis_tagihan_keringanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->cascadeOnDelete();
            $table->foreignId('kategori_keringanan_id')->constrained('kategori_keringanan')->restrictOnDelete();
            $table->enum('tipe_potongan', ['fixed', 'persen']);
            $table->decimal('nilai', 12, 2);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['jenis_tagihan_id', 'kategori_keringanan_id'], 'jenis_tagihan_keringanan_unique');
        });

        Schema::create('siswa_keringanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('kategori_keringanan_id')->constrained('kategori_keringanan')->restrictOnDelete();
            $table->date('berlaku_dari');
            $table->date('berlaku_sampai')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa_keringanan');
        Schema::dropIfExists('jenis_tagihan_keringanan');
        Schema::dropIfExists('kategori_keringanan');
    }
};
