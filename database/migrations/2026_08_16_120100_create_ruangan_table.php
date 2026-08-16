<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('gedung_id')->constrained('gedung')->cascadeOnDelete();
            $table->string('kode_ruangan', 50);
            $table->string('nama_ruangan', 255);
            $table->integer('lantai')->default(1);
            $table->string('jenis_ruangan', 50)->default('kelas_teori');
            $table->unsignedInteger('kapasitas_siswa')->nullable();
            $table->decimal('luas_m2', 8, 2)->nullable();
            $table->foreignId('penanggung_jawab_guru_id')->nullable()->constrained('guru')->nullOnDelete();
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->index(['lembaga_id', 'is_aktif']);
            $table->index(['gedung_id', 'lantai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruangan');
    }
};
