<?php

use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanLembagaGuruSemester(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create();

    return compact('lembaga', 'semester', 'guru', 'user');
}

it('membuat JadwalPiketMingguan dengan relasi yang benar', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'user' => $user] = siapkanLembagaGuruSemester();

    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => 1,
        'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id,
    ]);

    expect($jadwal->guru->id)->toBe($guru->id)
        ->and($jadwal->lembaga->id)->toBe($lembaga->id)
        ->and($jadwal->semester->id)->toBe($semester->id)
        ->and($jadwal->dibuatOleh->id)->toBe($user->id);
});

it('menolak JadwalPiketMingguan duplikat (guru+hari+semester sama)', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'user' => $user] = siapkanLembagaGuruSemester();
    JadwalPiketMingguan::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => 1, 'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id]);

    expect(fn () => JadwalPiketMingguan::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => 1, 'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id]))
        ->toThrow(QueryException::class);
});

it('membuat PiketHarian dengan relasi yang benar', function () {
    ['lembaga' => $lembaga, 'guru' => $guru] = siapkanLembagaGuruSemester();

    $piket = PiketHarian::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id,
        'tanggal' => now()->toDateString(), 'sumber' => 'override_manual',
    ]);

    expect($piket->guru->id)->toBe($guru->id)
        ->and($piket->lembaga->id)->toBe($lembaga->id);
});

it('menolak PiketHarian duplikat (guru+tanggal+lembaga sama)', function () {
    ['lembaga' => $lembaga, 'guru' => $guru] = siapkanLembagaGuruSemester();
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual']);

    expect(fn () => PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual']))
        ->toThrow(QueryException::class);
});
