<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_piket_mingguan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->unsignedTinyInteger('hari');
            $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->foreignId('dibuat_oleh_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lembaga_id', 'guru_id', 'hari', 'semester_id'], 'jadwal_piket_mingguan_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_piket_mingguan');
    }
};
