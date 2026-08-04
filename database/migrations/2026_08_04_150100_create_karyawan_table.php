<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('jenis_karyawan_id')->constrained('jenis_karyawan_master')->restrictOnDelete();

            $table->text('nik');
            $table->string('nik_hash', 64)->unique();
            $table->string('nama');
            $table->string('no_hp')->nullable();
            $table->string('email')->nullable();
            $table->enum('status_aktif', ['aktif', 'non_aktif'])->default('aktif');
            $table->integer('kapasitas_kasus_aktif')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan');
    }
};
