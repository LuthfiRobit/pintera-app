<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yayasan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->text('npwp_yayasan')->nullable();
            $table->string('akta_pendirian_nomor')->nullable();
            $table->date('akta_pendirian_tanggal')->nullable();
            $table->string('sk_kemenkumham_nomor')->nullable();
            $table->text('alamat')->nullable();
            $table->string('telepon')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->string('nama_ketua_pembina')->nullable();
            $table->string('nama_ketua_pengurus')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yayasan');
    }
};
