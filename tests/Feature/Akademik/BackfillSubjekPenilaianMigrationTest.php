<?php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function getBackfillMigrationInstance()
{
    return require database_path('migrations/2026_08_26_100200_backfill_subjek_penilaian.php');
}

it('backfills subjek_type=elemen_cp when both elemen_cp and mata_pelajaran_id are filled on the same row', function () {
    if (! Schema::hasColumn('komponen_penilaian', 'mata_pelajaran_id')) {
        $this->markTestSkipped('Legacy column mata_pelajaran_id has been dropped in Task 6 migration.');
    }

    $elemenCp = ElemenCp::factory()->create(['kode' => 'jati_diri']);
    $mapel = MataPelajaran::factory()->create();
    $semester = Semester::factory()->create();

    $id = DB::table('komponen_penilaian')->insertGetId([
        'mata_pelajaran_id' => $mapel->id,
        'elemen_cp' => 'jati_diri',
        'semester_id' => $semester->id,
        'lembaga_id' => $mapel->lembaga_id,
        'deskripsi' => 'Tes precedence',
        'bobot' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    getBackfillMigrationInstance()->up();

    $komponen = KomponenPenilaian::withoutGlobalScopes()->find($id);
    expect($komponen->subjek_type)->toBe('elemen_cp');
    expect($komponen->subjek_id)->toBe($elemenCp->id);
});

it('backfills subjek_type=mata_pelajaran when only mata_pelajaran_id is filled', function () {
    if (! Schema::hasColumn('komponen_penilaian', 'mata_pelajaran_id')) {
        $this->markTestSkipped('Legacy column mata_pelajaran_id has been dropped in Task 6 migration.');
    }

    $mapel = MataPelajaran::factory()->create();
    $semester = Semester::factory()->create();

    $id = DB::table('komponen_penilaian')->insertGetId([
        'mata_pelajaran_id' => $mapel->id,
        'elemen_cp' => null,
        'semester_id' => $semester->id,
        'lembaga_id' => $mapel->lembaga_id,
        'deskripsi' => 'Tes tanpa elemen_cp',
        'bobot' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    getBackfillMigrationInstance()->up();

    $komponen = KomponenPenilaian::withoutGlobalScopes()->find($id);
    expect($komponen->subjek_type)->toBe('mata_pelajaran');
    expect($komponen->subjek_id)->toBe($mapel->id);
});

it('throws when a row has an unmapped or missing elemen_cp reference', function () {
    if (! Schema::hasColumn('komponen_penilaian', 'mata_pelajaran_id')) {
        $this->markTestSkipped('Legacy column mata_pelajaran_id has been dropped in Task 6 migration.');
    }

    $mapel = MataPelajaran::factory()->create();
    $semester = Semester::factory()->create();

    DB::table('komponen_penilaian')->insert([
        'mata_pelajaran_id' => $mapel->id,
        'elemen_cp' => 'kode_tidak_dikenal',
        'semester_id' => $semester->id,
        'lembaga_id' => $mapel->lembaga_id,
        'deskripsi' => 'Baris elemen_cp tidak valid',
        'bobot' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => getBackfillMigrationInstance()->up())
        ->toThrow(RuntimeException::class);
});

it('backfills asesmen the same way (no elemen_cp column on this table)', function () {
    if (! Schema::hasColumn('asesmen', 'mata_pelajaran_id')) {
        $this->markTestSkipped('Legacy column mata_pelajaran_id has been dropped in Task 6 migration.');
    }

    $mapel = MataPelajaran::factory()->create();
    $kelas = App\Models\Kelas::factory()->create(['lembaga_id' => $mapel->lembaga_id]);
    $semester = Semester::factory()->create();
    $guru = App\Models\Guru::factory()->create(['lembaga_id' => $mapel->lembaga_id]);

    $id = DB::table('asesmen')->insertGetId([
        'guru_id' => $guru->id,
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $mapel->lembaga_id,
        'jenis' => 'sumatif_lingkup_materi',
        'judul' => 'Tes',
        'tanggal' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    getBackfillMigrationInstance()->up();

    $asesmen = Asesmen::withoutGlobalScopes()->find($id);
    expect($asesmen->subjek_type)->toBe('mata_pelajaran');
    expect($asesmen->subjek_id)->toBe($mapel->id);
});
