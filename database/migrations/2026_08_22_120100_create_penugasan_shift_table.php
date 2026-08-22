<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penugasan_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->morphs('pegawai');
            $table->foreignId('jenis_shift_id')->constrained('jenis_shift')->cascadeOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->json('hari_kerja')->nullable();
            $table->timestamps();

            $table->index(['pegawai_type', 'pegawai_id', 'tanggal_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_shift');
    }
};
