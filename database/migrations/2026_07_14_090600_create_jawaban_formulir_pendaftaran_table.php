<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jawaban_formulir_pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->cascadeOnDelete();
            $table->foreignId('formulir_field_id')->constrained('formulir_field');
            $table->text('nilai')->nullable();
            $table->string('nama_file_asli')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('ukuran_bytes')->nullable();
            $table->index(['pendaftaran_id', 'formulir_field_id'], 'idx_jawaban_form_pendaftaran_field');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jawaban_formulir_pendaftaran');
    }
};
