<?php

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new RoleSeeder)->run();
});

function buatSiswaDenganAkunDanJadwal(Lembaga $lembaga, Kelas $kelas): array
{
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('siswa');
    $siswa = Siswa::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return [$user->fresh(), $siswa];
}

it('menampilkan jadwal 1 minggu penuh untuk semester yang dipilih', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamSenin = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true, 'hari' => 'senin']);
    $jamSelasa = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true, 'hari' => 'selasa']);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'mata_pelajaran_id' => $mapel->id, 'jam_pelajaran_id' => $jamSenin->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'mata_pelajaran_id' => $mapel->id, 'jam_pelajaran_id' => $jamSelasa->id, 'semester_id' => $semester->id]);
    [$user, $siswa] = buatSiswaDenganAkunDanJadwal($lembaga, $kelas);

    $response = $this->actingAs($user)->get(route('admin.jadwal-pelajaran-saya.index', ['semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('jadwalList', fn ($list) => $list->flatten()->count() === 2 && $list->keys()->sort()->values()->all() === ['selasa', 'senin']);
});

it('regresi identitas: siswa A hanya melihat jadwal kelasnya sendiri, bukan kelas siswa B', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $polaB = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaA->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaB->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true, 'hari' => 'senin']);
    $jamB = JamPelajaran::factory()->create(['pola_jam_id' => $polaB->id, 'is_pelajaran' => true, 'hari' => 'senin']);
    JadwalPelajaran::create(['kelas_id' => $kelasA->id, 'guru_id' => $guru->id, 'mata_pelajaran_id' => $mapel->id, 'jam_pelajaran_id' => $jamA->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelasB->id, 'guru_id' => $guru->id, 'mata_pelajaran_id' => $mapel->id, 'jam_pelajaran_id' => $jamB->id, 'semester_id' => $semester->id]);
    [$userA, $siswaA] = buatSiswaDenganAkunDanJadwal($lembaga, $kelasA);
    [$userB, $siswaB] = buatSiswaDenganAkunDanJadwal($lembaga, $kelasB);

    $response = $this->actingAs($userA)->get(route('admin.jadwal-pelajaran-saya.index', ['semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('jadwalList', fn ($list) => $list->flatten()->count() === 1 && $list->flatten()->first()->kelas_id === $kelasA->id);
});
