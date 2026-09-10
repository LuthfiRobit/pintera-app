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

function buatOrangTuaDenganAnak(Lembaga $lembaga, Kelas $kelas): array
{
    $user = User::factory()->create();
    $orangTua = OrangTua::factory()->create(['user_id' => $user->id]);
    $anak = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $orangTua->siswa()->attach($anak->id, ['hubungan' => 'ayah']);

    return [$user->fresh(), $anak];
}

it('menampilkan nilai anak untuk semester yang dipilih', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    [$user, $anak] = buatOrangTuaDenganAnak($lembaga, $kelas);

    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'jenis' => JenisAsesmen::SumatifLingkupMateri]);
    NilaiSiswa::factory()->create(['siswa_id' => $anak->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 88]);

    $response = $this->actingAs($user)->get(route('admin.nilai-anak.index', ['siswa_id' => $anak->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('nilaiList', fn ($list) => $list->contains(fn ($n) => $n->nilai_angka === 88));
});

it('menampilkan daftar semester untuk dropdown tanpa perlu semester_id eksplisit di query string', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $anak] = buatOrangTuaDenganAnak($lembaga, $kelas);

    // Sengaja TIDAK mengirim semester_id -- ini yang membuktikan bug lama:
    // Semester::where(...) tanpa withoutGlobalScope(TenantScope::class) selalu
    // mengembalikan collection kosong untuk actor orang tua (lembaga_id null).
    $response = $this->actingAs($user)->get(route('admin.nilai-anak.index', ['siswa_id' => $anak->id]));

    $response->assertOk();
    $response->assertViewHas('semesterList', fn ($list) => $list->contains('id', $semester->id));
    $response->assertViewHas('semesterId', $semester->id);
});

it('menolak kebocoran nilai anak orang tua lain lewat siswa_id di query string (IDOR)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$userA, $anakA] = buatOrangTuaDenganAnak($lembaga, $kelas);
    [$userB, $anakB] = buatOrangTuaDenganAnak($lembaga, $kelas);

    $response = $this->actingAs($userA)->get(route('admin.nilai-anak.index', ['siswa_id' => $anakB->id]));

    $response->assertOk();
    $response->assertViewHas('anak', fn ($anak) => $anak->id === $anakA->id);
});

it('menampilkan tombol unduh rapor kalau PengajuanRapor sudah Disetujui', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $anak] = buatOrangTuaDenganAnak($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Disetujui]);

    $response = $this->actingAs($user)->get(route('admin.nilai-anak.index', ['siswa_id' => $anak->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('pengajuanRapor', fn ($p) => $p !== null);
});

it('shows Perlu Revisi status, not Belum Diajukan, when the PengajuanRapor for the anak was rejected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $anak] = buatOrangTuaDenganAnak($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Ditolak]);

    $response = $this->actingAs($user)->get(route('admin.nilai-anak.index', ['siswa_id' => $anak->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertSee('Perlu Revisi');
    $response->assertDontSee('Belum Diajukan');
});

it('menolak unduh rapor untuk anak orang tua lain (403 tegas, bukan fallback)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$userA, $anakA] = buatOrangTuaDenganAnak($lembaga, $kelas);
    [$userB, $anakB] = buatOrangTuaDenganAnak($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Disetujui]);

    $response = $this->actingAs($userA)->get(route('admin.nilai-anak.unduh-rapor', ['siswa' => $anakB->id, 'semester_id' => $semester->id]));

    $response->assertStatus(403);
});

it('menolak unduh rapor kalau PengajuanRapor belum Disetujui', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $anak] = buatOrangTuaDenganAnak($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Diajukan]);

    $response = $this->actingAs($user)->get(route('admin.nilai-anak.unduh-rapor', ['siswa' => $anak->id, 'semester_id' => $semester->id]));

    $response->assertStatus(404);
});

it('berhasil mengunduh rapor PDF kalau PengajuanRapor sudah Disetujui untuk anak sendiri', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $anak] = buatOrangTuaDenganAnak($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Disetujui]);

    $response = $this->actingAs($user)->get(route('admin.nilai-anak.unduh-rapor', ['siswa' => $anak->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});
