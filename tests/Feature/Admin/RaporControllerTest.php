<?php

use App\Enums\JenisAsesmen;
use App\Models\Asesmen;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsRaporViewer(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'rapor.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['rapor.view']);

    $viewer = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $viewer->assignRole($role);

    return $viewer;
}

it('denies access without rapor.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.rapor.index'))->assertForbidden();
});

it('displays the rapor recap page for selected class and semester', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $asesmen = Asesmen::factory()->create([
        'guru_id' => $guru->id,
        'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::SumatifLingkupMateri,
    ]);

    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);

    NilaiSiswa::create([
        'asesmen_id' => $asesmen->id,
        'siswa_id' => $siswa->id,
        'komponen_penilaian_id' => $komponen->id,
        'nilai_angka' => 88,
    ]);

    $viewer = actingAsRaporViewer($lembaga);

    $this->actingAs($viewer)
        ->get(route('admin.rapor.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]))
        ->assertOk()
        ->assertSee('88');
});

it('defaults to the active tahun ajaran, first kelas, and latest semester when none is selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taAktif->id, 'nama' => '7A']);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taAktif->id, 'nama' => '7B']);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taAktif->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taAktif->id]);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index'));

    $response->assertViewHas('tahunAjaranId', $taAktif->id);
    $response->assertViewHas('selectedKelas', fn ($kelas) => $kelas->id === $kelasA->id);
    $response->assertViewHas('selectedSemester', fn ($semester) => $semester->id === $semesterBaru->id);
});

it('only offers kelas and semester options belonging to the selected tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taBaru->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taLama->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id]);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', ['tahun_ajaran_id' => $taBaru->id]));

    $response->assertViewHas('kelasList', fn ($list) => $list->contains('id', $kelasBaru->id) && ! $list->contains('id', $kelasLama->id));
    $response->assertViewHas('semesterList', fn ($list) => $list->contains('id', $semesterBaru->id) && ! $list->contains('id', $semesterLama->id));
});

it('ignores a kelas_id or semester_id that does not belong to the selected tahun ajaran and falls back to defaults', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taBaru->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id]);

    $viewer = actingAsRaporViewer($lembaga);

    // kelas_id belongs to $taLama but tahun_ajaran_id in the request is $taBaru — mismatch must be ignored.
    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', [
        'tahun_ajaran_id' => $taBaru->id,
        'kelas_id' => $kelasLama->id,
    ]));

    $response->assertViewHas('selectedKelas', fn ($kelas) => $kelas->id === $kelasBaru->id);
    $response->assertViewHas('selectedSemester', fn ($semester) => $semester->id === $semesterBaru->id);
});

it('returns only the fragment for an ajax request, not the full page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index'), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertDontSee('raporFilter(', false);
});

it('returns kelas and semester options scoped to the given tahun ajaran via the opsi endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '9C']);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap']);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->getJson(route('admin.rapor.opsi', ['tahun_ajaran_id' => $tahunAjaran->id]));

    $response->assertOk();
    $response->assertJsonFragment(['id' => $kelas->id, 'nama' => '9C']);
    $response->assertJsonFragment(['id' => $semester->id, 'nama' => 'Genap']);
});

it('rejects a tahun_ajaran_id belonging to another lembaga on the opsi endpoint', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $viewer = actingAsRaporViewer($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($viewer)->getJson(route('admin.rapor.opsi', ['tahun_ajaran_id' => $tahunAjaranB->id]))
        ->assertNotFound();
});

it('uses the configured ambang tuntas threshold in the legend', function () {
    config(['akademik.ambang_tuntas' => 80]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', ['tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertSee('Tuntas (&ge; 80)', false);
    $response->assertSee('Perlu Bimbingan (&lt; 80)', false);
});
