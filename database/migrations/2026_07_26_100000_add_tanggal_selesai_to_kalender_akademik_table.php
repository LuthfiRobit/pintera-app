<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kalender_akademik', function (Blueprint $table) {
            $table->date('tanggal_selesai')->nullable()->after('tanggal');
        });
    }

    public function down(): void
    {
        Schema::table('kalender_akademik', function (Blueprint $table) {
            $table->dropColumn('tanggal_selesai');
        });
    }
};
