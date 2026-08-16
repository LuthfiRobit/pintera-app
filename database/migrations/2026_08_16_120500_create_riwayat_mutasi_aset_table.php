<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_mutasi_aset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aset_barang_id')->constrained('aset_barang')->cascadeOnDelete();
            $table->foreignId('ruangan_asal_id')->constrained('ruangan')->restrictOnDelete();
            $table->foreignId('ruangan_tujuan_id')->constrained('ruangan')->restrictOnDelete();
            $table->unsignedInteger('qty_pindah')->default(1);
            $table->date('tanggal_mutasi');
            $table->text('alasan_mutasi');
            $table->foreignId('dilakukan_oleh_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['aset_barang_id', 'tanggal_mutasi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_mutasi_aset');
    }
};
