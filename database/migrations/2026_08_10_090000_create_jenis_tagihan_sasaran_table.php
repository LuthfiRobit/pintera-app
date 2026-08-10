<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_tagihan_sasaran_grup', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->cascadeOnDelete();
            $table->enum('tipe', ['sasaran', 'tarif']);
            $table->decimal('nominal', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('jenis_tagihan_sasaran_kriteria', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jenis_tagihan_sasaran_grup_id');
            $table->foreign('jenis_tagihan_sasaran_grup_id', 'fk_kriteria_grup')
                ->references('id')
                ->on('jenis_tagihan_sasaran_grup')
                ->cascadeOnDelete();
            $table->enum('field', ['lembaga', 'tahun_ajaran', 'tingkat', 'kelas', 'jenis_kelamin', 'status_siswa']);
            $table->enum('operator', ['in', 'not_in']);
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_tagihan_sasaran_kriteria');
        Schema::dropIfExists('jenis_tagihan_sasaran_grup');
    }
};
