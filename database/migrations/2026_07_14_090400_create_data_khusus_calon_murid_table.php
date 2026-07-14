<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_khusus_calon_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calon_murid_id')->unique()->constrained('calon_murid')->cascadeOnDelete();
            $table->boolean('kepemilikan_kip')->default(false);
            $table->string('nomor_kip')->nullable();
            $table->text('riwayat_beasiswa')->nullable();
            $table->text('kebutuhan_khusus')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_khusus_calon_murid');
    }
};
