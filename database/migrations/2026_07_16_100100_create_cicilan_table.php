<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cicilan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skema_cicilan_id')->constrained('skema_cicilan')->cascadeOnDelete();
            $table->unsignedTinyInteger('urutan');
            $table->decimal('nominal', 12, 2);
            $table->date('jatuh_tempo');
            $table->enum('status', ['belum_bayar', 'menunggu_verifikasi', 'ditolak', 'lunas'])->default('belum_bayar');
            $table->timestamps();

            $table->unique(['skema_cicilan_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cicilan');
    }
};
