<?php
// database/migrations/2026_08_22_100000_add_hari_libur_mingguan_sdm_to_lembaga_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->json('hari_libur_mingguan_sdm')->default(DB::raw('(JSON_ARRAY(0))'))->after('hari_libur_mingguan');
        });
    }

    public function down(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->dropColumn('hari_libur_mingguan_sdm');
        });
    }
};
