<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sk_ppdb', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->string('nomor_sk');
            $table->date('tanggal_terbit');
            $table->foreignId('diterbitkan_oleh_user_id')->constrained('users');
            $table->string('file_path');
            $table->timestamps();

            $table->unique(['lembaga_id', 'nomor_sk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_ppdb');
    }
};
