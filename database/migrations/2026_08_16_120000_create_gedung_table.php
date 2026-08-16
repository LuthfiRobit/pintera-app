<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gedung', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->string('kode_gedung', 50);
            $table->string('nama_gedung', 255);
            $table->unsignedInteger('jumlah_lantai')->default(1);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->index(['lembaga_id', 'is_aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gedung');
    }
};
