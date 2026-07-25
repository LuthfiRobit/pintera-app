<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jam_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pola_jam_id')->constrained('pola_jam')->cascadeOnDelete();
            $table->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']);
            $table->unsignedInteger('urutan');
            $table->string('label');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->boolean('is_pelajaran')->default(true);
            $table->timestamps();

            $table->unique(['pola_jam_id', 'hari', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jam_pelajaran');
    }
};
