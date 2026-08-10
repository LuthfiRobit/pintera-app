<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nominal_tagihan_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->decimal('nominal', 12, 2);
            $table->timestamps();

            $table->unique(['jenis_tagihan_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nominal_tagihan_siswa');
    }
};
