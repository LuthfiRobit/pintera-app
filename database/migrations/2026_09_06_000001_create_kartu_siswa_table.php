<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kartu_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->enum('tipe', ['qr'])->default('qr');
            $table->string('kode')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['siswa_id', 'tipe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kartu_siswa');
    }
};
