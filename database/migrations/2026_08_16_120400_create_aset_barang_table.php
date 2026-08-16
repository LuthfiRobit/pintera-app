<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aset_barang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('kategori_aset_id')->constrained('kategori_aset')->restrictOnDelete();
            $table->foreignId('ruangan_id')->constrained('ruangan')->restrictOnDelete();
            $table->string('kode_inventaris', 100);
            $table->string('nama_barang', 255);
            $table->string('merk', 255)->nullable();
            $table->text('spesifikasi')->nullable();
            $table->string('tipe_pencatatan', 20)->default('unit'); // unit, batch
            $table->unsignedInteger('qty')->default(1);
            $table->string('satuan', 50)->default('unit');
            $table->string('kondisi', 30)->default('baik'); // baik, rusak_ringan, rusak_berat, hilang
            $table->string('sumber_perolehan', 50)->default('beli_lembaga');
            $table->date('tanggal_perolehan')->nullable();
            $table->decimal('harga_perolehan', 15, 2)->nullable();
            $table->string('foto_barang_path', 255)->nullable();
            $table->timestamps();

            $table->index(['lembaga_id', 'kode_inventaris']);
            $table->index(['ruangan_id', 'kategori_aset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aset_barang');
    }
};
