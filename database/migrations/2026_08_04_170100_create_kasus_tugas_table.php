<?php
// database/migrations/2026_08_04_170100_create_kasus_tugas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasus_tugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kasus_id')->constrained('kasus')->cascadeOnDelete();
            $table->string('judul');
            $table->text('instruksi');
            $table->enum('frekuensi', ['sekali', 'harian', 'mingguan', 'bulanan']);
            $table->date('mulai_pada');
            $table->date('batas_selesai_pada');
            $table->enum('status', ['ditugaskan', 'dikerjakan', 'revisi', 'selesai', 'terlewat'])->default('ditugaskan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasus_tugas');
    }
};
