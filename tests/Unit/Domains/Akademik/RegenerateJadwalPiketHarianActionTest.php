<?php

use App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction;
use App\Domains\Akademik\Actions\Piket\RegenerateJadwalPiketHarianAction;
use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\KalenderAkademikResolver;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanPiketHarianRegenerateTest(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => []]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'lembaga_id' => $lembaga->id,
        'tanggal_mulai' => now()->subDays(10)->toDateString(), 'tanggal_selesai' => now()->addDays(30)->toDateString(),
    ]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create();
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    return compact('lembaga', 'semester', 'guru', 'user', 'kelas');
}

it('baris dari_jadwal_mingguan tanggal depan tanpa akuntabilitas -- dihapus & digenerate ulang', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'user' => $user] = siapkanPiketHarianRegenerateTest();
    $jadwalLama = JadwalPiketMingguan::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => now()->addDay()->dayOfWeek, 'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id]);
    $tanggalLama = now()->addDay()->toDateString();
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => $tanggalLama, 'sumber' => 'dari_jadwal_mingguan', 'jadwal_piket_mingguan_id' => $jadwalLama->id]);

    $guruBaru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwalLama->update(['guru_id' => $guruBaru->id]);

    (new RegenerateJadwalPiketHarianAction(new GenerateJadwalPiketHarianAction(new KalenderAkademikResolver)))
        ->execute($lembaga->id, $semester->id);

    expect(PiketHarian::where('tanggal', $tanggalLama)->where('guru_id', $guru->id)->exists())->toBeFalse();
    expect(PiketHarian::where('tanggal', $tanggalLama)->where('guru_id', $guruBaru->id)->exists())->toBeTrue();
});

it('baris override_manual TIDAK disentuh regenerate', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'user' => $user] = siapkanPiketHarianRegenerateTest();
    $tanggalDepan = now()->addDay()->toDateString();
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => $tanggalDepan, 'sumber' => 'override_manual']);

    (new RegenerateJadwalPiketHarianAction(new GenerateJadwalPiketHarianAction(new KalenderAkademikResolver)))
        ->execute($lembaga->id, $semester->id);

    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->where('tanggal', $tanggalDepan)->where('sumber', 'override_manual')->exists())->toBeTrue();
});

it('baris tanggal LAMPAU (tanggal < hari ini) TIDAK disentuh regenerate', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'user' => $user] = siapkanPiketHarianRegenerateTest();
    $tanggalLampau = now()->subDay()->toDateString();
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => $tanggalLampau, 'sumber' => 'dari_jadwal_mingguan']);

    (new RegenerateJadwalPiketHarianAction(new GenerateJadwalPiketHarianAction(new KalenderAkademikResolver)))
        ->execute($lembaga->id, $semester->id);

    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->where('tanggal', $tanggalLampau)->exists())->toBeTrue();
});

it('baris yang SUDAH DIPAKAI (ada SesiPembelajaran.diisi_oleh_guru_id cocok) TIDAK disentuh regenerate walau tanggal depan', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'user' => $user, 'kelas' => $kelas] = siapkanPiketHarianRegenerateTest();
    $tanggalHariIni = now()->toDateString();
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => $tanggalHariIni, 'sumber' => 'dari_jadwal_mingguan']);

    $guruAsli = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruAsli->id,
        'diisi_oleh_guru_id' => $guru->id, 'tanggal' => $tanggalHariIni,
    ]);

    (new RegenerateJadwalPiketHarianAction(new GenerateJadwalPiketHarianAction(new KalenderAkademikResolver)))
        ->execute($lembaga->id, $semester->id);

    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->where('tanggal', $tanggalHariIni)->exists())->toBeTrue();
});
