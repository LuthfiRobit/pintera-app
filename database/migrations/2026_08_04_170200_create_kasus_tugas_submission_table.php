<?php
// database/migrations/2026_08_04_170200_create_kasus_tugas_submission_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasus_tugas_submission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tugas_id')->constrained('kasus_tugas')->cascadeOnDelete();
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('orang_tua_id')->nullable()->constrained('orang_tua')->cascadeOnDelete();
            $table->text('teks')->nullable();
            $table->string('lampiran')->nullable();
            $table->enum('status_review', ['menunggu_review', 'diterima', 'revisi_diminta'])->default('menunggu_review');
            $table->text('catatan_revisi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasus_tugas_submission');
    }
};
