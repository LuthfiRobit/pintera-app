<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lembaga_data_periodik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->string('waktu_penyelenggaraan')->nullable();
            $table->string('sumber_listrik')->nullable();
            $table->unsignedInteger('daya_listrik')->nullable();
            $table->string('akses_internet')->nullable();
            $table->boolean('status_bos')->default(false);
            $table->string('sertifikasi_iso')->nullable();
            $table->boolean('ketersediaan_air_bersih')->default(false);
            $table->boolean('kecukupan_air_bersih')->default(false);
            $table->unsignedInteger('jumlah_tempat_cuci_tangan')->default(0);
            $table->unsignedInteger('jumlah_jamban')->default(0);
            $table->string('stratifikasi_uks')->nullable();
            $table->boolean('media_kie_sanitasi')->default(false);
            $table->timestamps();

            $table->unique(['lembaga_id', 'semester_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lembaga_data_periodik');
    }
};
