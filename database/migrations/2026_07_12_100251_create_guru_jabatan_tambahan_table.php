<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('guru_jabatan_tambahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->foreignId('jabatan_tambahan_master_id')->constrained('jabatan_tambahan_master')->cascadeOnDelete();
            $table->date('mulai_periode');
            $table->date('akhir_periode')->nullable();
            $table->string('no_sk')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guru_jabatan_tambahan');
    }
};
