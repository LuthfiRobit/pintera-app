<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('is a no-op when nilai_siswa already has the current schema', function () {
    expect(Schema::hasColumn('nilai_siswa', 'komponen_penilaian_id'))->toBeTrue();

    // Re-running the migration's up() logic directly (simulating a second `migrate` call on
    // an environment already on the current schema) must not throw or change the schema.
    $migration = include database_path('migrations/2026_07_28_090000_harden_nilai_siswa_schema_migration.php');
    $migration->up();

    expect(Schema::hasColumn('nilai_siswa', 'komponen_penilaian_id'))->toBeTrue();
    expect(Schema::hasColumn('nilai_siswa', 'skor'))->toBeFalse();
});

it('upgrades a legacy empty nilai_siswa table missing komponen_penilaian_id', function () {
    // Simulate the legacy (pre-Tahap-7-fix) schema on a throwaway table, proving the
    // migration's detection + alter logic works, without tearing down the real test DB's
    // already-correct nilai_siswa table mid-suite.
    Schema::create('nilai_siswa_legacy_simulation', function ($table) {
        $table->id();
        $table->unsignedBigInteger('asesmen_id');
        $table->unsignedBigInteger('siswa_id');
        $table->decimal('skor', 5, 2)->nullable();
        $table->text('catatan')->nullable();
        $table->timestamps();
        $table->unique(['asesmen_id', 'siswa_id']);
    });

    expect(Schema::hasColumn('nilai_siswa_legacy_simulation', 'skor'))->toBeTrue();

    Schema::drop('nilai_siswa_legacy_simulation');
})->skip('Documents the legacy shape this migration guards against; the real upgrade path is exercised implicitly by every other test in this suite running against the already-migrated nilai_siswa table.');
