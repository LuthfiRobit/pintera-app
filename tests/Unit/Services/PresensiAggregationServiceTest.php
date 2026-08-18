<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\PresensiAggregationService;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanKelasUntukRekap(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id,
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2026-12-31',
    ]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    return compact('kelas', 'semester');
}

it('menghitung total hadir, izin, sakit, alpa, dan terlambat per siswa dalam rentang semester', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasUntukRekap();
    $siswa = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);

    $sesi1 = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => '2026-08-10']);
    $sesi2 = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => '2026-08-11']);
    $sesi3 = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => '2026-08-12']);

    Presensi::create(['sesi_pembelajaran_id' => $sesi1->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);
    Presensi::create(['sesi_pembelajaran_id' => $sesi2->id, 'siswa_id' => $siswa->id, 'status' => 'izin']);
    Presensi::create(['sesi_pembelajaran_id' => $sesi3->id, 'siswa_id' => $siswa->id, 'status' => 'alpa']);

    $rekap = (new PresensiAggregationService())->agregasiPerKelas($kelas->id, $semester);

    $baris = $rekap->firstWhere('siswa_id', $siswa->id);
    expect($baris)->not->toBeNull()
        ->and($baris['nama'])->toBe($siswa->nama)
        ->and($baris['hadir'])->toBe(1)
        ->and($baris['izin'])->toBe(1)
        ->and($baris['alpa'])->toBe(1)
        ->and($baris['sakit'])->toBe(0)
        ->and($baris['terlambat'])->toBe(0);
});

it('mengecualikan presensi dari sesi di luar rentang tanggal semester', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasUntukRekap();
    $siswa = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);

    $sesiDiLuarSemester = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => '2027-01-15']);
    Presensi::create(['sesi_pembelajaran_id' => $sesiDiLuarSemester->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);

    $rekap = (new PresensiAggregationService())->agregasiPerKelas($kelas->id, $semester);

    $baris = $rekap->firstWhere('siswa_id', $siswa->id);
    expect($baris['hadir'])->toBe(0);
});

it('menyertakan siswa aktif tanpa presensi sama sekali dengan semua total nol', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasUntukRekap();
    $siswa = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);

    $rekap = (new PresensiAggregationService())->agregasiPerKelas($kelas->id, $semester);

    $baris = $rekap->firstWhere('siswa_id', $siswa->id);
    expect($baris)->not->toBeNull()
        ->and($baris['hadir'])->toBe(0)
        ->and($baris['izin'])->toBe(0)
        ->and($baris['sakit'])->toBe(0)
        ->and($baris['alpa'])->toBe(0)
        ->and($baris['terlambat'])->toBe(0);
});

it('mengembalikan collection kosong untuk kelas tanpa siswa aktif', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasUntukRekap();

    $rekap = (new PresensiAggregationService())->agregasiPerKelas($kelas->id, $semester);

    expect($rekap)->toHaveCount(0);
});
