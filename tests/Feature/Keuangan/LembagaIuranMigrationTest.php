<?php
// tests/Feature/Keuangan/LembagaIuranMigrationTest.php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The memungut_iuran/nominal_iuran/periode_iuran columns this migration
 * reads from are dropped from `lembaga` by Task 10's migration, which runs
 * later in this same plan. By the time this test executes, migrate:fresh
 * has already applied every migration file in the repo — including that
 * drop. Re-adding the columns here for the duration of this one test lets
 * us exercise the real migration logic against a realistic "before" row
 * without depending on migration file ordering at test-run time. MySQL DDL
 * isn't transactional, so the try/finally guarantees the columns are gone
 * again before any other test runs, pass or fail.
 *
 * Guarded with hasColumn() checks: at the point Task 9 lands, Task 10 has
 * not run yet in this repository, so these columns are still present on
 * the base `lembaga` table (added by create_lembaga_table). Once Task 10
 * merges and drops them permanently, this same helper re-adds them as
 * originally intended. Either way, only columns this helper itself added
 * are dropped again in the finally block — pre-existing columns are left
 * untouched.
 */
// MySQL DDL (Schema::table add/drop columns below) implicitly commits the
// currently-open transaction, so RefreshDatabase's per-test rollback never
// happens for rows created before or after that DDL runs in the same test.
// Every caller of this helper must therefore delete its own Lembaga/
// JenisTagihan rows explicitly in a finally block, or they leak permanently.
function withLegacyIuranColumns(callable $callback): void
{
    $columns = ['memungut_iuran', 'nominal_iuran', 'periode_iuran'];
    $addedColumns = array_filter($columns, fn (string $column) => ! Schema::hasColumn('lembaga', $column));

    if ($addedColumns !== []) {
        Schema::table('lembaga', function (Blueprint $table) use ($addedColumns) {
            if (in_array('memungut_iuran', $addedColumns, true)) {
                $table->boolean('memungut_iuran')->default(false);
            }
            if (in_array('nominal_iuran', $addedColumns, true)) {
                $table->decimal('nominal_iuran', 15, 2)->nullable();
            }
            if (in_array('periode_iuran', $addedColumns, true)) {
                $table->enum('periode_iuran', ['bulanan', 'tahunan'])->nullable();
            }
        });
    }

    try {
        $callback();
    } finally {
        if ($addedColumns !== []) {
            Schema::table('lembaga', function (Blueprint $table) use ($addedColumns) {
                $table->dropColumn($addedColumns);
            });
        }
    }
}

it('creates a spp jenis_tagihan for every lembaga that had memungut_iuran enabled', function () {
    withLegacyIuranColumns(function () {
        $lembaga = Lembaga::factory()->create();

        try {
            DB::table('lembaga')->where('id', $lembaga->id)->update([
                'memungut_iuran' => true,
                'nominal_iuran' => 275000,
                'periode_iuran' => 'bulanan',
            ]);

            (require database_path('migrations/2026_08_10_170000_migrate_lembaga_iuran_to_jenis_tagihan.php'))->up();

            $jenisTagihan = JenisTagihan::where('lembaga_id', $lembaga->id)->where('kategori', 'spp')->first();

            expect($jenisTagihan)->not->toBeNull();
            expect((float) $jenisTagihan->default_amount)->toBe(275000.0);
            expect($jenisTagihan->mode)->toBe('otomatis');
            expect($jenisTagihan->nama)->toBe('SPP Bulanan');
            expect($jenisTagihan->tanggal_generate)->toBe(1);
            expect($jenisTagihan->hari_jatuh_tempo)->toBe(10);
        } finally {
            JenisTagihan::where('lembaga_id', $lembaga->id)->delete();
            Lembaga::where('id', $lembaga->id)->delete();
        }
    });
});

it('does not duplicate the jenis_tagihan when the migration runs twice', function () {
    withLegacyIuranColumns(function () {
        $lembaga = Lembaga::factory()->create();

        try {
            DB::table('lembaga')->where('id', $lembaga->id)->update([
                'memungut_iuran' => true,
                'nominal_iuran' => 100000,
                'periode_iuran' => 'bulanan',
            ]);

            $migration = require database_path('migrations/2026_08_10_170000_migrate_lembaga_iuran_to_jenis_tagihan.php');
            $migration->up();
            $migration->up();

            expect(JenisTagihan::where('lembaga_id', $lembaga->id)->where('kategori', 'spp')->count())->toBe(1);
        } finally {
            JenisTagihan::where('lembaga_id', $lembaga->id)->delete();
            Lembaga::where('id', $lembaga->id)->delete();
        }
    });
});

it('skips a lembaga where memungut_iuran is false', function () {
    withLegacyIuranColumns(function () {
        $lembaga = Lembaga::factory()->create();

        try {
            DB::table('lembaga')->where('id', $lembaga->id)->update(['memungut_iuran' => false]);

            (require database_path('migrations/2026_08_10_170000_migrate_lembaga_iuran_to_jenis_tagihan.php'))->up();

            expect(JenisTagihan::where('lembaga_id', $lembaga->id)->where('kategori', 'spp')->exists())->toBeFalse();
        } finally {
            JenisTagihan::where('lembaga_id', $lembaga->id)->delete();
            Lembaga::where('id', $lembaga->id)->delete();
        }
    });
});
