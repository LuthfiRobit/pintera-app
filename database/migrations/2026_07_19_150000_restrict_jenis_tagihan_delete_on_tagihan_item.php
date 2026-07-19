<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagihan_item', function (Blueprint $table) {
            $table->dropForeign(['jenis_tagihan_id']);
            $table->foreign('jenis_tagihan_id')->references('id')->on('jenis_tagihan')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tagihan_item', function (Blueprint $table) {
            $table->dropForeign(['jenis_tagihan_id']);
            $table->foreign('jenis_tagihan_id')->references('id')->on('jenis_tagihan')->cascadeOnDelete();
        });
    }
};
