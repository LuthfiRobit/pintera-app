<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_aset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->string('kode_kategori', 50);
            $table->string('nama_kategori', 255);
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            $table->index(['lembaga_id', 'kode_kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_aset');
    }
};
