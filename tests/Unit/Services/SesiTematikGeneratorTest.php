<?php

use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\SesiTematikGenerator;
use App\Enums\Hari;
use App\Models\Guru;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanKelasTematikDenganWali(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'TK', 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 1, 'jam_mulai' => '08:00:00', 'jam_selesai' => '08:30:00', 'is_pelajaran' => true]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 2, 'jam_mulai' => '08:30:00', 'jam_selesai' => '09:00:00', 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'pola_jam_id' => $pola->id,
        'wali_kelas_guru_id' => $guru->id,
    ]);
    Siswa::factory()->count(3)->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return compact('kelas', 'guru', 'semester');
}

it('creates one sesi tematik for the kelas with jadwal_pelajaran_id and mata_pelajaran_id both null', function () {
    ['kelas' => $kelas, 'guru' => $guru, 'semester' => $semester] = siapkanKelasTematikDenganWali();

    $sesi = (new SesiTematikGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id); // a Wednesday

    expect($sesi)->not->toBeNull();
    expect($sesi->jadwal_pelajaran_id)->toBeNull();
    expect($sesi->mata_pelajaran_id)->toBeNull();
    expect($sesi->guru_id)->toBe($guru->id);
    expect($sesi->kelas_id)->toBe($kelas->id);
    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
});

it('auto-creates a hadir presensi row for every siswa aktif in the kelas', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasTematikDenganWali();

    $sesi = (new SesiTematikGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($sesi->presensi()->count())->toBe(3);
    expect($sesi->presensi()->first()->status->value)->toBe('hadir');
});

it('returns null and creates nothing when the kelas has no wali kelas assigned', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasTematikDenganWali();
    $kelas->update(['wali_kelas_guru_id' => null]);

    $sesi = (new SesiTematikGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($sesi)->toBeNull();
    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(0);
});

it('returns null on a day the kalender resolver marks as libur', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasTematikDenganWali();

    $sesi = (new SesiTematikGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-16'), $semester->id); // a Sunday, weekly off-day

    expect($sesi)->toBeNull();
    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(0);
});

it('is idempotent: calling it twice for the same date does not duplicate the sesi', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasTematikDenganWali();

    $pertama = (new SesiTematikGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);
    $kedua = (new SesiTematikGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
    expect($kedua->id)->toBe($pertama->id);
});
