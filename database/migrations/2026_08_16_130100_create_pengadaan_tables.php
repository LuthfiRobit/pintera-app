<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_pengadaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->nullable()->constrained('yayasan')->nullOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->nullOnDelete();
            $table->string('nomor_pengajuan')->unique();
            $table->string('judul_pengajuan');
            $table->text('latar_belakang')->nullable();
            $table->string('tingkat_urgensi')->default('biasa'); // biasa, mendesak, kritis
            $table->decimal('total_estimasi', 15, 2)->default(0);
            $table->string('status')->default('draft'); // draft, submitted, in_review, revision_required, approved, rejected, disbursed, completed
            $table->decimal('nominal_pencairan', 15, 2)->nullable();
            $table->date('tanggal_pencairan')->nullable();
            $table->text('catatan_pencairan')->nullable();
            $table->string('bukti_transfer_pencairan_path')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pengajuan_pengadaan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_pengadaan_id')->constrained('pengajuan_pengadaan')->cascadeOnDelete();
            $table->foreignId('kategori_aset_id')->nullable()->constrained('kategori_aset')->nullOnDelete();
            $table->foreignId('target_ruangan_id')->nullable()->constrained('ruangan')->nullOnDelete();
            $table->string('nama_barang');
            $table->string('merk')->nullable();
            $table->text('spesifikasi')->nullable();
            $table->unsignedInteger('qty')->default(1);
            $table->string('satuan')->default('unit');
            $table->decimal('estimasi_harga_satuan', 15, 2)->default(0);
            $table->decimal('total_estimasi', 15, 2)->default(0);
            $table->string('tipe_pencatatan')->default('unit'); // unit, batch
            $table->string('foto_referensi_path')->nullable();
            $table->string('status_item')->default('pending'); // pending, approved, rejected
            $table->text('catatan_reviewer')->nullable();
            $table->timestamps();
        });

        Schema::create('lpj_pengadaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_pengadaan_id')->unique()->constrained('pengajuan_pengadaan')->cascadeOnDelete();
            $table->decimal('total_realisasi', 15, 2)->default(0);
            $table->decimal('selisih_dana', 15, 2)->default(0); // nominal_pencairan - total_realisasi (positive = surplus/sisa, negative = kurang)
            $table->string('bukti_kembali_sisa_dana_path')->nullable();
            $table->string('status_lpj')->default('draft'); // draft, submitted, revision_required, verified
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lpj_pengadaan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lpj_pengadaan_id')->constrained('lpj_pengadaan')->cascadeOnDelete();
            $table->foreignId('pengajuan_item_id')->constrained('pengajuan_pengadaan_item')->cascadeOnDelete();
            $table->decimal('harga_satuan_riil', 15, 2)->default(0);
            $table->decimal('total_riil', 15, 2)->default(0);
            $table->string('foto_nota_path')->nullable();
            $table->string('foto_fisik_barang_path')->nullable();
            $table->string('status_konversi_sarpras')->default('pending'); // pending, converted
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lpj_pengadaan_item');
        Schema::dropIfExists('lpj_pengadaan');
        Schema::dropIfExists('pengajuan_pengadaan_item');
        Schema::dropIfExists('pengajuan_pengadaan');
    }
};
