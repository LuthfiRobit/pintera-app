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
            $table->string('kode_lembaga', 20)->nullable()->unique()->after('slug');
        });

        // Backfill deterministik dari slug + id (id selalu unik, jadi hasilnya
        // pasti unik juga tanpa perlu logic dedup collision-retry).
        DB::statement(
            "UPDATE lembaga SET kode_lembaga = CONCAT(UPPER(SUBSTRING(REPLACE(slug, '-', ''), 1, 6)), '-', id) WHERE kode_lembaga IS NULL"
        );

        // NOT NULL via raw SQL: proyek ini tidak instal doctrine/dbal, jadi
        // Blueprint::change() tidak berfungsi untuk ubah nullability.
        DB::statement('ALTER TABLE lembaga MODIFY COLUMN kode_lembaga VARCHAR(20) NOT NULL');
    }

    public function down(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->dropUnique(['kode_lembaga']);
            $table->dropColumn('kode_lembaga');
        });
    }
};
