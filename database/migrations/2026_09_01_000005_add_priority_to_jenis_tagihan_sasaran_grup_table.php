<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_tagihan_sasaran_grup', function (Blueprint $table) {
            $table->unsignedInteger('priority')->nullable()->after('nominal');
        });

        DB::statement('
            UPDATE jenis_tagihan_sasaran_grup g
            JOIN (SELECT id, ROW_NUMBER() OVER (PARTITION BY jenis_tagihan_id ORDER BY id) AS rn FROM jenis_tagihan_sasaran_grup WHERE tipe = "tarif") ranked
            ON g.id = ranked.id
            SET g.priority = ranked.rn
        ');
    }

    public function down(): void
    {
        Schema::table('jenis_tagihan_sasaran_grup', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
