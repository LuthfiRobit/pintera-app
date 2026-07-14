<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_periodik_calon_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calon_murid_id')->unique()->constrained('calon_murid')->cascadeOnDelete();
            $table->unsignedSmallInteger('tinggi_badan_cm')->nullable();
            $table->unsignedSmallInteger('berat_badan_kg')->nullable();
            $table->unsignedSmallInteger('jarak_tempuh_km')->nullable();
            $table->unsignedSmallInteger('waktu_tempuh_menit')->nullable();
            $table->unsignedTinyInteger('jumlah_saudara_kandung')->nullable();
            $table->string('alat_transportasi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_periodik_calon_murid');
    }
};
