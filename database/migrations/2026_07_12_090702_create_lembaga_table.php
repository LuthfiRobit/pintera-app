<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lembaga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();

            $table->string('npsn')->unique();
            $table->string('nss')->nullable();
            $table->string('nama');
            $table->string('slug')->unique();
            $table->enum('bentuk_pendidikan', ['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB']);
            $table->enum('status_sekolah', ['negeri', 'swasta']);
            $table->string('status_kepemilikan')->nullable();
            $table->enum('naungan', ['kemendikdasmen', 'kemenag']);
            $table->string('sk_pendirian_nomor')->nullable();
            $table->date('sk_pendirian_tanggal')->nullable();
            $table->string('sk_izin_operasional_nomor')->nullable();
            $table->date('sk_izin_operasional_tanggal')->nullable();
            $table->enum('akreditasi', ['A', 'B', 'C', 'belum'])->default('belum');
            $table->string('sk_akreditasi_nomor')->nullable();
            $table->date('tanggal_sk_akreditasi')->nullable();
            $table->string('nama_kepala_sekolah')->nullable();
            $table->string('nama_bendahara_bosp')->nullable();

            $table->text('alamat_jalan')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('nama_dusun')->nullable();
            $table->string('desa_kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kabupaten_kota')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kode_pos')->nullable();
            $table->decimal('lintang', 10, 7)->nullable();
            $table->decimal('bujur', 10, 7)->nullable();

            $table->string('telepon')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            $table->string('nama_bank')->nullable();
            $table->string('cabang_kcp_unit')->nullable();
            $table->string('rekening_atas_nama')->nullable();
            $table->text('nomor_rekening')->nullable();

            $table->boolean('mbs')->default(false);
            $table->string('nama_wajib_pajak')->nullable();
            $table->text('npwp')->nullable();
            $table->boolean('memungut_iuran')->default(false);
            $table->decimal('nominal_iuran', 15, 2)->nullable();
            $table->enum('periode_iuran', ['bulanan', 'tahunan'])->nullable();
            $table->boolean('status_aktif')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lembaga');
    }
};
