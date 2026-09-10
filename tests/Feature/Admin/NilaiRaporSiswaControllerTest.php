<?php

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\PengajuanRapor;
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

function buatSiswaDenganAkun(Lembaga $lembaga, Kelas $kelas): array
{
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('siswa');
    $siswa = Siswa::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return [$user->fresh(), $siswa];
}

it('menampilkan nilai untuk semester yang dipilih', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    [$user, $siswa] = buatSiswaDenganAkun($lembaga, $kelas);

    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'jenis' => JenisAsesmen::SumatifLingkupMateri]);
    NilaiSiswa::factory()->create(['siswa_id' => $siswa->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 92]);

    $response = $this->actingAs($user)->get(route('admin.nilai-rapor-saya.index', ['semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('nilaiList', fn ($list) => $list->contains(fn ($n) => $n->nilai_angka === 92));
});

it('regresi identitas: siswa A hanya melihat nilainya sendiri, bukan campur dengan siswa B', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    [$userA, $siswaA] = buatSiswaDenganAkun($lembaga, $kelas);
    [$userB, $siswaB] = buatSiswaDenganAkun($lembaga, $kelas);

    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'jenis' => JenisAsesmen::SumatifLingkupMateri]);
    NilaiSiswa::factory()->create(['siswa_id' => $siswaA->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 70]);
    NilaiSiswa::factory()->create(['siswa_id' => $siswaB->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 95]);

    $response = $this->actingAs($userA)->get(route('admin.nilai-rapor-saya.index', ['semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('nilaiList', fn ($list) => $list->count() === 1 && $list->first()->nilai_angka === 70);
});

it('mengizinkan unduh rapor kalau PengajuanRapor sudah Disetujui', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $siswa] = buatSiswaDenganAkun($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Disetujui]);

    $response = $this->actingAs($user)->get(route('admin.nilai-rapor-saya.unduh-rapor', ['semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('shows Perlu Revisi status, not Belum Diajukan, when the PengajuanRapor was rejected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $siswa] = buatSiswaDenganAkun($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Ditolak]);

    $response = $this->actingAs($user)->get(route('admin.nilai-rapor-saya.index', ['semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertSee('Perlu Revisi');
    $response->assertDontSee('Belum Diajukan');
});

it('menolak unduh rapor kalau PengajuanRapor belum Disetujui', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $siswa] = buatSiswaDenganAkun($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Diajukan]);

    $response = $this->actingAs($user)->get(route('admin.nilai-rapor-saya.unduh-rapor', ['semester_id' => $semester->id]));

    $response->assertStatus(404);
});
