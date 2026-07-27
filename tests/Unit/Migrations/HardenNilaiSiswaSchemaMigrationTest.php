<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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

it('upgrades a legacy nilai_siswa table missing komponen_penilaian_id', function () {
    // MySQL does not support transactional DDL: every CREATE/ALTER/DROP TABLE statement
    // causes an implicit COMMIT of any active transaction, regardless of what wraps it. That
    // means RefreshDatabase's per-test transaction rollback does NOT undo the Schema::drop()/
    // Schema::create() calls below -- they are permanently committed to the real test
    // database the instant they run (verified independently against this project's own test
    // DB). The try/finally below explicitly restores nilai_siswa to its correct, current
    // shape no matter what happens in the body, so this test can't corrupt the schema for
    // every test that runs after it in the suite.
    try {
        Schema::drop('nilai_siswa');
        Schema::create('nilai_siswa', function ($table) {
            $table->id();
            $table->foreignId('asesmen_id')->constrained('asesmen')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->decimal('skor', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['asesmen_id', 'siswa_id']);
        });

        expect(Schema::hasColumn('nilai_siswa', 'komponen_penilaian_id'))->toBeFalse();

        $migration = include database_path('migrations/2026_07_28_090000_harden_nilai_siswa_schema_migration.php');
        $migration->up();

        expect(Schema::hasColumn('nilai_siswa', 'komponen_penilaian_id'))->toBeTrue();
        expect(Schema::hasColumn('nilai_siswa', 'skor'))->toBeFalse();
        expect(Schema::hasColumn('nilai_siswa', 'nilai_angka'))->toBeTrue();
        expect(Schema::hasColumn('nilai_siswa', 'predikat'))->toBeTrue();
    } finally {
        // Restore nilai_siswa to the exact shape create_asesmen_tables produces, independent
        // of whether the migration-under-test worked correctly or an assertion above threw,
        // so the real schema is guaranteed correct for whatever test runs after this one.
        Schema::dropIfExists('nilai_siswa');
        Schema::create('nilai_siswa', function ($table) {
            $table->id();
            $table->foreignId('asesmen_id')->constrained('asesmen')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('komponen_penilaian_id')->constrained('komponen_penilaian')->cascadeOnDelete();
            $table->unsignedTinyInteger('nilai_angka')->nullable();
            $table->string('predikat')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['asesmen_id', 'siswa_id', 'komponen_penilaian_id'], 'nilai_siswa_unik');
        });
    }
});
