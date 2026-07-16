<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->cascadeOnDelete();
            $table->enum('kategori', ['pendaftaran', 'daftar_ulang']);
            $table->decimal('total_tagihan', 12, 2);
            $table->enum('status', ['belum_bayar', 'dicicil', 'lunas'])->default('belum_bayar');
            $table->date('jatuh_tempo')->nullable();
            $table->timestamps();

            $table->unique(['pendaftaran_id', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan');
    }
};
