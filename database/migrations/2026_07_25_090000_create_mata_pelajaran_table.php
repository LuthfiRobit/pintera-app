<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('kode', 20);
            $table->string('nama', 255);
            $table->unsignedSmallInteger('no_urut')->default(1);
            $table->enum('tipe', ['mapel', 'aspek_perkembangan']);
            $table->enum('kelompok', [
                'umum',
                'agama_kemenag',
                'pilihan',
                'kejuruan',
                'mulok',
                'projek_p5_ppra'
            ])->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();

            $table->unique(['lembaga_id', 'kode']);
            $table->index(['lembaga_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mata_pelajaran');
    }
};
