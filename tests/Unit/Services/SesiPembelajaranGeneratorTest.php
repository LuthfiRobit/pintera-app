<?php

use App\Enums\Hari;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\PolaJam;
use App\Models\Semester;
use App\Models\SesiPembelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use App\Services\SesiPembelajaranGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanKelasDenganJadwal(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'is_pelajaran' => true]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwal = JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);
    Siswa::factory()->count(3)->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return compact('kelas', 'jadwal', 'semester');
}

it('creates a sesi pembelajaran for each jadwal matching the date\'s day of week', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id); // a Wednesday

    expect($hasil)->toHaveCount(1);
    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
});

it('auto-creates a hadir presensi row for every siswa in the kelas', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($hasil->first()->presensi()->count())->toBe(3);
    expect($hasil->first()->presensi()->first()->status->value)->toBe('hadir');
});

it('does not generate a sesi on a day the kalender resolver marks as libur', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-16'), $semester->id); // a Sunday, weekly off-day

    expect($hasil)->toHaveCount(0);
    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(0);
});

it('is idempotent: calling it twice for the same date does not duplicate the sesi', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();

    (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);
    (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
});

it('merges 2 consecutive jam pelajaran with the same mapel and guru into one sesi spanning the whole block', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 1, 'is_pelajaran' => true, 'jam_mulai' => '07:35', 'jam_selesai' => '08:10']);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 2, 'is_pelajaran' => true, 'jam_mulai' => '08:10', 'jam_selesai' => '09:00']);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'mata_pelajaran_id' => $mapel->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamDua->id, 'mata_pelajaran_id' => $mapel->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    Siswa::factory()->count(2)->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($hasil)->toHaveCount(1);
    expect($hasil->first()->jam_mulai)->toBe('07:35:00');
    expect($hasil->first()->jam_selesai)->toBe('09:00:00');
    expect($hasil->first()->presensi()->count())->toBe(2);
});

it('does not merge consecutive slots when the mata pelajaran differs', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 1, 'is_pelajaran' => true]);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 2, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelSatu = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelDua = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'mata_pelajaran_id' => $mapelSatu->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamDua->id, 'mata_pelajaran_id' => $mapelDua->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($hasil)->toHaveCount(2);
});

it('does not merge slots with the same mapel and guru if they are not adjacent (a different slot sits between them)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 1, 'is_pelajaran' => true]);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 2, 'is_pelajaran' => true]);
    $jamTiga = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 3, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'mata_pelajaran_id' => $mapelA->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamDua->id, 'mata_pelajaran_id' => $mapelLain->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamTiga->id, 'mata_pelajaran_id' => $mapelA->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    // 3 separate sesi: mapelA alone, mapelLain alone, mapelA alone again — urutan 1 and 3
    // share mata_pelajaran_id/guru_id but are not consecutive, so they must NOT merge.
    expect($hasil)->toHaveCount(3);
});

it('is idempotent for a merged block: calling it twice does not duplicate the sesi or its presensi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jamSatu = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 1, 'is_pelajaran' => true, 'jam_mulai' => '07:35', 'jam_selesai' => '08:10']);
    $jamDua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => Hari::Rabu->value, 'urutan' => 2, 'is_pelajaran' => true, 'jam_mulai' => '08:10', 'jam_selesai' => '09:00']);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamSatu->id, 'mata_pelajaran_id' => $mapel->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamDua->id, 'mata_pelajaran_id' => $mapel->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id]);
    Siswa::factory()->count(2)->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $pertama = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);
    $kedua = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect(SesiPembelajaran::where('kelas_id', $kelas->id)->count())->toBe(1);
    expect($kedua->first()->id)->toBe($pertama->first()->id);
    expect($kedua->first()->presensi()->count())->toBe(2);
});

it('does not overwrite a manually-edited presensi status when the generator runs a second time', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);
    $sesi = $hasil->first();
    $presensiPertama = $sesi->presensi()->first();
    $presensiPertama->update(['status' => 'izin', 'keterangan' => 'Sakit demam']);

    (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    $presensiPertama->refresh();
    expect($presensiPertama->status->value)->toBe('izin');
    expect($presensiPertama->keterangan)->toBe('Sakit demam');
    expect($sesi->presensi()->count())->toBe(3);
});

it('excludes non-aktif siswa from auto-generated presensi', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();
    $siswaLulus = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'lulus']);

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($hasil->first()->presensi()->count())->toBe(3); // only the 3 aktif siswa from siapkanKelasDenganJadwal()
    expect($hasil->first()->presensi()->where('siswa_id', $siswaLulus->id)->exists())->toBeFalse();
});
