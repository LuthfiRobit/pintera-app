<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_pembelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_pelajaran_id')->nullable()->constrained('jadwal_pelajaran')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->nullOnDelete();
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->text('materi')->nullable();
            $table->enum('status', ['terlaksana', 'diganti', 'kosong'])->default('terlaksana');
            $table->timestamps();

            $table->unique(['jadwal_pelajaran_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_pembelajaran');
    }
};
