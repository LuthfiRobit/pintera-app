<?php

use App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction;
use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\KalenderAkademik;
use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Services\KalenderAkademikResolver;
use App\Enums\TipeKalenderAkademik;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generate PiketHarian untuk semua tanggal cocok hari dalam rentang semester, mulai dari hari ini', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => []]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mulai = now()->startOfDay();
    $selesai = now()->addWeeks(3)->startOfDay();
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'lembaga_id' => $lembaga->id,
        'tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $selesai->toDateString(),
    ]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create();
    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => $mulai->dayOfWeek,
        'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id,
    ]);

    (new GenerateJadwalPiketHarianAction(new KalenderAkademikResolver))->execute($jadwal);

    $tanggalHasil = PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->orderBy('tanggal')->pluck('tanggal')->map->toDateString()->all();
    $tanggalHarapan = collect(range(0, 3))
        ->map(fn ($i) => $mulai->copy()->addWeeks($i)->toDateString())
        ->filter(fn ($tgl) => $tgl <= $selesai->toDateString())
        ->values()->all();
    expect($tanggalHasil)->toBe($tanggalHarapan);
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->get()->every(fn ($p) => $p->sumber === 'dari_jadwal_mingguan' && $p->jadwal_piket_mingguan_id === $jadwal->id))->toBeTrue();
});

it('skip tanggal yang jatuh di hari libur akademik', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => []]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mulai = now()->startOfDay();
    $selesai = now()->addWeeks(2)->startOfDay();
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'lembaga_id' => $lembaga->id,
        'tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $selesai->toDateString(),
    ]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create();
    $tanggalLiburKandidat = $mulai->copy()->addWeek();
    KalenderAkademik::create([
        'lembaga_id' => $lembaga->id, 'tanggal' => $tanggalLiburKandidat->toDateString(), 'tanggal_selesai' => $tanggalLiburKandidat->toDateString(),
        'nama' => 'Libur Uji Coba', 'tipe' => TipeKalenderAkademik::Libur,
    ]);
    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => $mulai->dayOfWeek,
        'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id,
    ]);

    (new GenerateJadwalPiketHarianAction(new KalenderAkademikResolver))->execute($jadwal);

    $tanggalHasil = PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->pluck('tanggal')->map->toDateString()->all();
    expect($tanggalHasil)->not->toContain($tanggalLiburKandidat->toDateString());
    expect($tanggalHasil)->toContain($mulai->toDateString());
});

it('idempotent -- dipanggil 2x tidak membuat baris duplikat', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => []]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mulai = now()->startOfDay();
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'lembaga_id' => $lembaga->id,
        'tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $mulai->copy()->addWeeks(2)->toDateString(),
    ]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create();
    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => $mulai->dayOfWeek,
        'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id,
    ]);

    $action = new GenerateJadwalPiketHarianAction(new KalenderAkademikResolver);
    $action->execute($jadwal);
    $jumlahPertama = PiketHarian::count();
    $action->execute($jadwal);
    $jumlahKedua = PiketHarian::count();

    expect($jumlahKedua)->toBe($jumlahPertama);
    expect($jumlahPertama)->toBeGreaterThan(0);
});
