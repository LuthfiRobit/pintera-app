<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_seleksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran')->cascadeOnDelete();
            $table->foreignId('seleksi_ppdb_id')->constrained('seleksi_ppdb')->restrictOnDelete();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dinilai_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dinilai_pada')->nullable();
            $table->timestamps();

            $table->unique(['pendaftaran_id', 'seleksi_ppdb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_seleksi');
    }
};
