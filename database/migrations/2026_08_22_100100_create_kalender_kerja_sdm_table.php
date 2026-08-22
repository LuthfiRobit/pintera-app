<?php
// database/migrations/2026_08_22_100100_create_kalender_kerja_sdm_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kalender_kerja_sdm', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->date('tanggal');
            $table->date('tanggal_selesai')->nullable();
            $table->string('nama');
            $table->string('tipe');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['lembaga_id', 'tanggal']);
            $table->index(['yayasan_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kalender_kerja_sdm');
    }
};
