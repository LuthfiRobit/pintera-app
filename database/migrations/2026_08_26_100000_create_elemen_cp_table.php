<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elemen_cp', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            $table->unsignedTinyInteger('no_urut');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elemen_cp');
    }
};
