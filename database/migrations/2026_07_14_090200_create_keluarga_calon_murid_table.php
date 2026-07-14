<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keluarga_calon_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calon_murid_id')->constrained('calon_murid')->cascadeOnDelete();
            $table->enum('jenis', ['ayah', 'ibu', 'wali']);
            $table->string('nama');
            $table->text('nik')->nullable();
            $table->unsignedSmallInteger('tahun_lahir')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('pekerjaan')->nullable();
            $table->string('penghasilan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keluarga_calon_murid');
    }
};
