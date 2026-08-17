<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->nullable()->constrained('mata_pelajaran')->nullOnDelete();
            
            $table->string('judul_topik');
            $table->string('alokasi_waktu', 100);
            $table->string('pertemuan_ke', 50)->nullable();
            
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('mime_type', 100);
            
            $table->string('status', 30)->default('draft');
            $table->text('catatan_revisi')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            
            $table->timestamps();

            $table->index(['lembaga_id', 'status']);
            $table->index(['guru_id', 'status']);
            $table->index(['kelas_id', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpp');
    }
};
