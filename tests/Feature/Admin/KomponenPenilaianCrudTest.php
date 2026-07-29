<?php

use App\Models\KomponenPenilaian;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsKomponenManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'komponen-penilaian.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['komponen-penilaian.kelola']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without komponen-penilaian.kelola permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.komponen-penilaian.index'))->assertForbidden();
});

it('creates a komponen penilaian', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKomponenManager($lembaga);

    $this->actingAs($manager)->post(route('admin.komponen-penilaian.store'), [
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'kode' => 'TP 3.1',
        'deskripsi' => 'Siswa mampu menjelaskan siklus air',
        'kktp' => 'Mampu menjelaskan minimal 3 tahapan siklus air secara runtut',
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    expect(KomponenPenilaian::where('kode', 'TP 3.1')->exists())->toBeTrue();
});

it('does not list another lembaga\'s komponen penilaian', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $tahunAjaranSaya = TahunAjaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $semesterSaya = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranSaya->id]);
    $mapelSaya = MataPelajaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelSaya->id, 'semester_id' => $semesterSaya->id, 'kode' => 'TP-SAYA']);

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelLain->id, 'semester_id' => $semesterLain->id, 'kode' => 'TP-LAIN']);

    $manager = actingAsKomponenManager($lembagaSaya);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'));

    $response->assertOk();
    $response->assertSee('TP-SAYA');
    $response->assertDontSee('TP-LAIN');
});

it('rejects creating a komponen penilaian mixing a mata pelajaran and semester from different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $mapelSaya = MataPelajaran::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);

    $manager = actingAsKomponenManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.komponen-penilaian.store'), [
        'mata_pelajaran_id' => $mapelSaya->id,
        'semester_id' => $semesterLain->id,
        'deskripsi' => 'Campur lembaga',
    ])->assertNotFound();

    expect(KomponenPenilaian::where('deskripsi', 'Campur lembaga')->exists())->toBeFalse();
});

it('only offers semester options belonging to the selected tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026']);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027']);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taLama->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index', ['tahun_ajaran_id' => $taBaru->id]));

    $response->assertViewHas('semesterList', fn ($list) => $list->contains('id', $semesterBaru->id) && ! $list->contains('id', $semesterLama->id));
});

it('defaults to the active tahun ajaran when none is selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'));

    $response->assertViewHas('tahunAjaranId', $taAktif->id);
});

it('filters the komponen list by tahun ajaran, semester, and mata pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterCocok = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap']);
    $mapelCocok = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelCocok->id, 'semester_id' => $semesterCocok->id, 'kode' => 'TP-COCOK']);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelLain->id, 'semester_id' => $semesterCocok->id, 'kode' => 'TP-MAPEL-LAIN']);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapelCocok->id, 'semester_id' => $semesterLain->id, 'kode' => 'TP-SEMESTER-LAIN']);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index', [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'semester_id' => $semesterCocok->id,
        'mata_pelajaran_id' => $mapelCocok->id,
    ]), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertSee('TP-COCOK');
    $response->assertDontSee('TP-MAPEL-LAIN');
    $response->assertDontSee('TP-SEMESTER-LAIN');
});

it('filters the komponen list by search text on kode or deskripsi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'kode' => 'TP 3.1', 'deskripsi' => 'Siklus air']);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'kode' => 'TP 4.2', 'deskripsi' => 'Fotosintesis']);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index', ['search' => 'siklus']));

    $response->assertOk();
    $response->assertSee('Siklus air');
    $response->assertDontSee('Fotosintesis');
});

it('shows semester and tahun ajaran together on each row to avoid ambiguity', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027']);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'));

    $response->assertSee('Ganjil — 2026/2027');
});

it('returns only the fragment for an ajax request, not the full page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'), ['X-Requested-With' => 'XMLHttpRequest']);

    $response->assertOk();
    $response->assertSee('Daftar Komponen &amp; Tujuan Pembelajaran', false);
    $response->assertDontSee('komponenPenilaianFilter(', false);
});

it('returns semester options scoped to the given tahun ajaran via the opsi endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);

    $response = $this->actingAs($manager)->getJson(route('admin.komponen-penilaian.opsi', ['tahun_ajaran_id' => $tahunAjaran->id]));

    $response->assertOk();
    $response->assertJsonFragment(['id' => $semester->id, 'nama' => 'Ganjil']);
});

it('rejects a tahun_ajaran_id belonging to another lembaga on the opsi endpoint', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsKomponenManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($manager)->getJson(route('admin.komponen-penilaian.opsi', ['tahun_ajaran_id' => $tahunAjaranB->id]))
        ->assertNotFound();
});

it('wires the filter card with komponenPenilaianFilter and the correct initial values', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id, 'mata_pelajaran_id' => $mapel->id,
    ]));

    $response->assertSee('komponenPenilaianFilter(', false);
    $response->assertSee((string) $tahunAjaran->id, false);
    $response->assertSee((string) $semester->id, false);
    $response->assertSee((string) $mapel->id, false);
});
