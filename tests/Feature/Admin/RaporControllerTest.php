<?php

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
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
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
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
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'jenis' => JenisAsesmen::SumatifLingkupMateri,
    ]);

    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
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
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taAktif->id, 'nama' => 'Ganjil']);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taAktif->id, 'nama' => 'Genap']);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index'));

    $response->assertViewHas('tahunAjaranId', $taAktif->id);
    $response->assertViewHas('selectedKelas', fn ($kelas) => $kelas->id === $kelasA->id);
    $response->assertViewHas('selectedSemester', fn ($semester) => $semester->id === $semesterBaru->id);
});

it('only offers kelas and semester options belonging to the selected tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2020/2021']);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2021/2022']);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taBaru->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taLama->id, 'nama' => 'Ganjil']);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id, 'nama' => 'Genap']);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', ['tahun_ajaran_id' => $taBaru->id]));

    $response->assertViewHas('kelasList', fn ($list) => $list->contains('id', $kelasBaru->id) && ! $list->contains('id', $kelasLama->id));
    $response->assertViewHas('semesterList', fn ($list) => $list->contains('id', $semesterBaru->id) && ! $list->contains('id', $semesterLama->id));
});

it('ignores a kelas_id or semester_id that does not belong to the selected tahun ajaran and falls back to defaults', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2020/2021']);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2021/2022']);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taBaru->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id, 'nama' => 'Genap']);

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

it('streams a pdf for the selected kelas and semester via the cetak endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Budi Santoso']);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.cetak', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('rejects a kelas_id belonging to another lembaga on the cetak endpoint', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $viewer = actingAsRaporViewer($lembagaA);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id]);

    $this->actingAs($viewer)->get(route('admin.rapor.cetak', ['kelas_id' => $kelasB->id, 'semester_id' => $semesterA->id]))
        ->assertNotFound();
});

it('rejects a semester_id belonging to another lembaga on the cetak endpoint', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $viewer = actingAsRaporViewer($lembagaA);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id]);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);

    $this->actingAs($viewer)->get(route('admin.rapor.cetak', ['kelas_id' => $kelasA->id, 'semester_id' => $semesterB->id]))
        ->assertNotFound();
});

it('rejects a kelas_id and semester_id from different tahun ajaran on the cetak endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $viewer = actingAsRaporViewer($lembaga);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2020/2021']);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2021/2022']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranA->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);

    $this->actingAs($viewer)->get(route('admin.rapor.cetak', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]))
        ->assertNotFound();
});

it('shows the cetak rekap nilai link pointing at the cetak route when there are students', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', ['tahun_ajaran_id' => $tahunAjaran->id, 'kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertSee(e(route('admin.rapor.cetak', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id])), false);
});

it('does not mix a kelas and semester from different lembaga within the same yayasan on the index recap', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id]);

    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);

    Permission::firstOrCreate(['name' => 'rapor.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_yayasan_rapor', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['rapor.view']);
    $manager = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)->get(route('admin.rapor.index', [
        'kelas_id' => $kelasA->id,
        'semester_id' => $semesterB->id,
    ]));

    $response->assertOk();
    $response->assertViewHas('rekapNilai', fn ($rekap) => $rekap === []);
});

it('calculates rapor grade using weighted component averages instead of unweighted simple averages', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    // Komponen A (bobot 80%) & Komponen B (bobot 20%)
    $kompA = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'bobot' => 80]);
    $kompB = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'bobot' => 20]);

    $asesmenA = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmenB = Asesmen::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);

    $asesmenA->komponenPenilaian()->attach($kompA->id);
    $asesmenB->komponenPenilaian()->attach($kompB->id);

    // Nilai A: 100 (Bobot 80 => 80), Nilai B: 50 (Bobot 20 => 10). Total Weighted = 90. Unweighted avg would be 75.
    NilaiSiswa::create(['asesmen_id' => $asesmenA->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $kompA->id, 'nilai_angka' => 100]);
    NilaiSiswa::create(['asesmen_id' => $asesmenB->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $kompB->id, 'nilai_angka' => 50]);

    $viewer = actingAsRaporViewer($lembaga);

    $response = $this->actingAs($viewer)->get(route('admin.rapor.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertSee('90');
});


