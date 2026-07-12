<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ekstrakurikuler_lembaga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('jenis_ekskul');
            $table->string('nama_ekskul');
            $table->string('no_sk')->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->unsignedInteger('jam_per_minggu')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekstrakurikuler_lembaga');
    }
};
