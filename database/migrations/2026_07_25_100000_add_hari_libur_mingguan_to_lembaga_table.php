<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->json('hari_libur_mingguan')->default(DB::raw('(JSON_ARRAY(0))'));
        });
    }

    public function down(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->dropColumn('hari_libur_mingguan');
        });
    }
};
