<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasus_evaluasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kasus_id')->constrained('kasus')->cascadeOnDelete();
            $table->dateTime('tanggal');
            $table->text('catatan');
            $table->enum('keputusan', ['lanjut', 'eskalasi', 'selesai']);
            $table->foreignId('dibuat_oleh_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasus_evaluasi');
    }
};
