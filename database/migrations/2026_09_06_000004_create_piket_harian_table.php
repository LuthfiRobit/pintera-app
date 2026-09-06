<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piket_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->date('tanggal');
            $table->enum('sumber', ['dari_jadwal_mingguan', 'override_manual']);
            $table->foreignId('jadwal_piket_mingguan_id')->nullable()->constrained('jadwal_piket_mingguan')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lembaga_id', 'guru_id', 'tanggal'], 'piket_harian_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piket_harian');
    }
};
