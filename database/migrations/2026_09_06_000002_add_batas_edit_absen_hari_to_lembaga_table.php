<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->unsignedSmallInteger('batas_edit_absen_hari')->default(3)->after('hari_libur_mingguan');
        });
    }

    public function down(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->dropColumn('batas_edit_absen_hari');
        });
    }
};
