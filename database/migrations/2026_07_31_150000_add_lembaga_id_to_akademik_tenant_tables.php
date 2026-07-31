<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables below have always derived their tenant implicitly through a related
     * model (kelas/mata_pelajaran/siswa) instead of carrying their own lembaga_id.
     * That forced every controller touching them to manually re-derive and compare
     * lembaga_id across joins to avoid cross-tenant leaks -- a pattern that has
     * caused repeated IDOR bugs in this module. Adding an explicit lembaga_id lets
     * BelongsToTenant's global scope close that class of bug by default instead of
     * relying on every call site remembering to check it by hand.
     */
    private const TABLE_SOURCE_COLUMN = [
        'sesi_pembelajaran' => 'kelas_id',
        'jadwal_pelajaran' => 'kelas_id',
        'asesmen' => 'kelas_id',
        'komponen_penilaian' => 'mata_pelajaran_id',
        'nilai_siswa' => 'siswa_id',
    ];

    private const SOURCE_TABLE = [
        'kelas_id' => 'kelas',
        'mata_pelajaran_id' => 'mata_pelajaran',
        'siswa_id' => 'siswa',
    ];

    public function up(): void
    {
        foreach (self::TABLE_SOURCE_COLUMN as $table => $sourceColumn) {
            Schema::table($table, function (Blueprint $blueprint) use ($sourceColumn) {
                $blueprint->foreignId('lembaga_id')->nullable()->after($sourceColumn)->constrained('lembaga')->cascadeOnDelete();
            });
        }

        foreach (self::TABLE_SOURCE_COLUMN as $table => $sourceColumn) {
            $sourceTable = self::SOURCE_TABLE[$sourceColumn];

            DB::statement(
                "UPDATE {$table} t JOIN {$sourceTable} s ON s.id = t.{$sourceColumn} SET t.lembaga_id = s.lembaga_id"
            );
        }

        // NOT NULL via raw SQL: this project doesn't install doctrine/dbal, so
        // Blueprint::change() isn't available to flip nullability after backfill.
        foreach (array_keys(self::TABLE_SOURCE_COLUMN) as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY COLUMN lembaga_id BIGINT UNSIGNED NOT NULL");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLE_SOURCE_COLUMN) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('lembaga_id');
            });
        }
    }
};
