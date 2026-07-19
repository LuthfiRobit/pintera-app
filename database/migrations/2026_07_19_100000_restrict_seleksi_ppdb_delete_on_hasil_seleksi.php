<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_seleksi', function (Blueprint $table) {
            $table->dropForeign(['seleksi_ppdb_id']);
            $table->foreign('seleksi_ppdb_id')->references('id')->on('seleksi_ppdb')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hasil_seleksi', function (Blueprint $table) {
            $table->dropForeign(['seleksi_ppdb_id']);
            $table->foreign('seleksi_ppdb_id')->references('id')->on('seleksi_ppdb')->cascadeOnDelete();
        });
    }
};
