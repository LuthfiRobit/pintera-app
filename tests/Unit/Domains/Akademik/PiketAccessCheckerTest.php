<?php

use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\PiketAccessChecker;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanSesiUntukAccessCheckerTest(int $lembagaId): SesiPembelajaran
{
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaId]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaId, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $guruPemilik = Guru::factory()->create(['lembaga_id' => $lembagaId]);

    return SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembagaId, 'kelas_id' => $kelas->id, 'guru_id' => $guruPemilik->id, 'tanggal' => now()->toDateString(),
    ]);
}

it('guru pemilik sesi selalu bisa akses', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $sesi = siapkanSesiUntukAccessCheckerTest($lembaga->id);
    $guruPemilik = Guru::find($sesi->guru_id);

    expect((new PiketAccessChecker)->bisaAkses($sesi, $guruPemilik))->toBeTrue();
});

it('guru piket lembaga SAMA, tanggal SAMA bisa akses sesi guru lain', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $sesi = siapkanSesiUntukAccessCheckerTest($lembaga->id);
    $guruPiket = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guruPiket->id, 'tanggal' => $sesi->tanggal->toDateString(), 'sumber' => 'override_manual']);

    expect((new PiketAccessChecker)->bisaAkses($sesi, $guruPiket))->toBeTrue();
});

it('guru piket lembaga LAIN TIDAK bisa akses walau tanggal sama', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $sesi = siapkanSesiUntukAccessCheckerTest($lembagaA->id);
    $guruPiketLembagaB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    // Guru ini piket di lembaga B, BUKAN di lembaga A tempat $sesi berada.
    PiketHarian::create(['lembaga_id' => $lembagaB->id, 'guru_id' => $guruPiketLembagaB->id, 'tanggal' => $sesi->tanggal->toDateString(), 'sumber' => 'override_manual']);

    expect((new PiketAccessChecker)->bisaAkses($sesi, $guruPiketLembagaB))->toBeFalse();
});

it('guru bukan pemilik & bukan piket TIDAK bisa akses', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $sesi = siapkanSesiUntukAccessCheckerTest($lembaga->id);
    $guruLain = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    expect((new PiketAccessChecker)->bisaAkses($sesi, $guruLain))->toBeFalse();
});
