<?php

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new RoleSeeder)->run();
});

function buatOrangTuaDenganAnakDanJadwal(Lembaga $lembaga, Kelas $kelas): array
{
    $user = User::factory()->create();
    $orangTua = OrangTua::factory()->create(['user_id' => $user->id]);
    $anak = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $orangTua->siswa()->attach($anak->id, ['hubungan' => 'ibu']);

    return [$user->fresh(), $anak];
}

it('menampilkan jadwal 1 minggu penuh untuk anak terpilih', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create([
        'pola_jam_id' => $pola->id,
        'is_pelajaran' => true,
        'hari' => 'senin',
        'jam_mulai' => '07:30',
        'jam_selesai' => '08:15',
    ]);
    JadwalPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'guru_id' => $guru->id,
        'mata_pelajaran_id' => $mapel->id,
        'jam_pelajaran_id' => $jam->id,
        'semester_id' => $semester->id,
    ]);
    [$user, $anak] = buatOrangTuaDenganAnakDanJadwal($lembaga, $kelas);

    $response = $this->actingAs($user)->get(route('admin.jadwal-anak.index', ['siswa_id' => $anak->id]));

    $response->assertOk();
    $response->assertViewHas('jadwalList', fn ($list) => $list->flatten()->count() === 1);
});

it('menolak kebocoran jadwal anak orang tua lain lewat siswa_id (IDOR)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$userA, $anakA] = buatOrangTuaDenganAnakDanJadwal($lembaga, $kelas);
    [$userB, $anakB] = buatOrangTuaDenganAnakDanJadwal($lembaga, $kelas);

    $response = $this->actingAs($userA)->get(route('admin.jadwal-anak.index', ['siswa_id' => $anakB->id]));

    $response->assertOk();
    $response->assertViewHas('anak', fn ($anak) => $anak->id === $anakA->id);
});

it('menampilkan panel kosong ramah jika akun bukan orang tua atau belum punya anak', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.jadwal-anak.index'));

    $response->assertOk();
    $response->assertViewHas('anakList', fn ($list) => $list->isEmpty());
    $response->assertViewHas('jadwalList', fn ($list) => $list->isEmpty());
});
