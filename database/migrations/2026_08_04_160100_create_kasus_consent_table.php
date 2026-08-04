<?php
// database/migrations/2026_08_04_160100_create_kasus_consent_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasus_consent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kasus_id')->constrained('kasus')->cascadeOnDelete();
            $table->enum('jenis', ['sesi_pendampingan', 'pengumpulan_media']);
            $table->enum('status', ['menunggu', 'disetujui'])->default('menunggu');
            $table->timestamp('disetujui_at')->nullable();
            $table->timestamps();
            $table->unique(['kasus_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasus_consent');
    }
};
