<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->nullable()->constrained('tagihan')->cascadeOnDelete();
            $table->foreignId('cicilan_id')->nullable()->constrained('cicilan')->cascadeOnDelete();
            $table->enum('sumber', ['calon_siswa', 'admin']);
            $table->enum('metode', ['transfer_manual', 'va_bri'])->default('transfer_manual');
            $table->string('file_path')->nullable();
            $table->enum('status', ['menunggu_verifikasi', 'lunas', 'ditolak']);
            $table->text('catatan_verifikasi')->nullable();
            $table->foreignId('diverifikasi_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->index(['status', 'metode'], 'idx_pembayaran_status_metode');
            $table->index(['tagihan_id', 'status'], 'idx_pembayaran_tagihan_status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
