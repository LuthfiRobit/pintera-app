<?php

use App\Domains\Akademik\Actions\Penilaian\CreateKomponenPenilaianAction;
use App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function actingAsKomponenManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'komponen-penilaian.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
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
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'kode' => 'TP 3.1',
        'deskripsi' => 'Siswa mampu menjelaskan siklus air',
        'kktp' => 'Mampu menjelaskan minimal 3 tahapan siklus air secara runtut',
        'bobot' => 100,
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
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelSaya->id, 'semester_id' => $semesterSaya->id, 'kode' => 'TP-SAYA']);

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelLain->id, 'semester_id' => $semesterLain->id, 'kode' => 'TP-LAIN']);

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
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapelSaya->id,
        'semester_id' => $semesterLain->id,
        'deskripsi' => 'Campur lembaga',
        'bobot' => 100,
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
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelCocok->id, 'semester_id' => $semesterCocok->id, 'kode' => 'TP-COCOK']);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelLain->id, 'semester_id' => $semesterCocok->id, 'kode' => 'TP-MAPEL-LAIN']);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelCocok->id, 'semester_id' => $semesterLain->id, 'kode' => 'TP-SEMESTER-LAIN']);

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
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'kode' => 'TP 3.1', 'deskripsi' => 'Siklus air']);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'kode' => 'TP 4.2', 'deskripsi' => 'Fotosintesis']);

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
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);

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

it('shows edit and hapus actions for each komponen in the daftar', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'));

    $response->assertSee(route('admin.komponen-penilaian.edit', $komponen), false);
    $response->assertSee('Hapus');
    $response->assertSee('confirmDialog', false);
    $response->assertSee(route('admin.komponen-penilaian.destroy', $komponen), false);
});

it('updates a komponen penilaian including mata pelajaran and semester when not yet used', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap']);
    $mapelLama = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelBaru = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelLama->id, 'semester_id' => $semesterLama->id, 'kode' => 'TP LAMA']);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapelBaru->id,
        'semester_id' => $semesterBaru->id,
        'kode' => 'TP BARU',
        'deskripsi' => 'Deskripsi baru',
        'kktp' => 'KKTP baru',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->subjek_id)->toBe($mapelBaru->id);
    expect($komponen->semester_id)->toBe($semesterBaru->id);
    expect($komponen->kode)->toBe('TP BARU');
    expect($komponen->deskripsi)->toBe('Deskripsi baru');
});

it('locks mata pelajaran and semester when the komponen is already used in an asesmen', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelLain = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);

    $editResponse = $this->actingAs($manager)->get(route('admin.komponen-penilaian.edit', $komponen));
    $editResponse->assertSee('tidak bisa diubah');

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapelLain->id,
        'deskripsi' => 'Coba ganti mapel',
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    expect($komponen->fresh()->subjek_id)->toBe($mapel->id);
    expect($komponen->fresh()->deskripsi)->toBe('Coba ganti mapel');
});

it('locks mata pelajaran and semester when the komponen is already used in a nilai siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap']);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    NilaiSiswa::factory()->create(['komponen_penilaian_id' => $komponen->id, 'siswa_id' => $siswa->id]);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'semester_id' => $semesterLain->id,
        'deskripsi' => 'Coba ganti semester',
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    expect($komponen->fresh()->semester_id)->toBe($semester->id);
    expect($komponen->fresh()->deskripsi)->toBe('Coba ganti semester');
});

it('rejects updating to a mata pelajaran and semester from different lembaga when not yet used', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsKomponenManager($lembagaA);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelA->id, 'semester_id' => $semesterA->id]);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapelA->id,
        'semester_id' => $semesterB->id,
        'deskripsi' => 'Campur lembaga',
        'bobot' => 100,
    ])->assertNotFound();

    expect($komponen->fresh()->semester_id)->toBe($semesterA->id);
});

it('rejects editing or updating a komponen penilaian belonging to another lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsKomponenManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $mapelB = MataPelajaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $komponenB = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelB->id, 'semester_id' => $semesterB->id]);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.edit', $komponenB))->assertNotFound();
    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponenB), ['deskripsi' => 'Coba'])->assertNotFound();
});

it('deletes a komponen penilaian that is not used anywhere', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);

    $this->actingAs($manager)->delete(route('admin.komponen-penilaian.destroy', $komponen))
        ->assertRedirect(route('admin.komponen-penilaian.index'))
        ->assertSessionHas('status');

    expect(KomponenPenilaian::find($komponen->id))->toBeNull();
});

it('blocks deleting a komponen penilaian already used in an asesmen', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);

    $this->actingAs($manager)->delete(route('admin.komponen-penilaian.destroy', $komponen))
        ->assertSessionHasErrors('komponen_penilaian');

    expect(KomponenPenilaian::find($komponen->id))->not->toBeNull();
});

it('blocks deleting a komponen penilaian already used in a nilai siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    NilaiSiswa::factory()->create(['komponen_penilaian_id' => $komponen->id, 'siswa_id' => $siswa->id]);

    $this->actingAs($manager)->delete(route('admin.komponen-penilaian.destroy', $komponen))
        ->assertSessionHasErrors('komponen_penilaian');

    expect(KomponenPenilaian::find($komponen->id))->not->toBeNull();
});

it('rejects deleting a komponen penilaian belonging to another lembaga', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsKomponenManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $mapelB = MataPelajaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $komponenB = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelB->id, 'semester_id' => $semesterB->id]);

    $this->actingAs($manager)->delete(route('admin.komponen-penilaian.destroy', $komponenB))->assertNotFound();

    expect(KomponenPenilaian::withoutGlobalScopes()->find($komponenB->id))->not->toBeNull();
});

it('denies access to edit, update, and destroy without komponen-penilaian.kelola permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $outsider = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($outsider)->get(route('admin.komponen-penilaian.edit', $komponen))->assertForbidden();
    $this->actingAs($outsider)->put(route('admin.komponen-penilaian.update', $komponen), ['deskripsi' => 'x'])->assertForbidden();
    $this->actingAs($outsider)->delete(route('admin.komponen-penilaian.destroy', $komponen))->assertForbidden();
});

it('shows komponen from every tahun ajaran when tahun_ajaran_id is explicitly empty', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $taLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    $semesterAktif = Semester::factory()->create(['tahun_ajaran_id' => $taAktif->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $taLain->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semesterAktif->id, 'kode' => 'TP-AKTIF']);
    KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semesterLain->id, 'kode' => 'TP-LAIN-TAHUN']);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.index', ['tahun_ajaran_id' => '']));

    $response->assertOk();
    $response->assertSee('TP-AKTIF');
    $response->assertSee('TP-LAIN-TAHUN');
});

it('shows tahun ajaran alongside each semester option on the edit form to avoid ambiguity', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026']);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027']);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id, 'nama' => 'Ganjil']);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id, 'nama' => 'Ganjil']);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semesterA->id]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.edit', $komponen));

    $response->assertOk();
    $response->assertSee('Ganjil — 2025/2026');
});

it('defaults to the active tahun ajaran on the create page when none is selected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.create'));

    $response->assertViewHas('tahunAjaranId', $taAktif->id);
});

it('only offers semester options belonging to the selected tahun ajaran on the create page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $taBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $taLama->id]);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $taBaru->id]);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.create', ['tahun_ajaran_id' => $taBaru->id]));

    $response->assertViewHas('semesterList', fn ($list) => $list->contains('id', $semesterBaru->id) && ! $list->contains('id', $semesterLama->id));
});

it('shows the tahun ajaran select wired with Tom Select on the create page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.create'));

    $response->assertSee('komponenPenilaianCreateForm(', false);
    $response->assertSee('name="tahun_ajaran_id"', false);
});

it('preserves the selected tahun ajaran and semester after a validation failure on store', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->from(route('admin.komponen-penilaian.create'))->post(route('admin.komponen-penilaian.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'semester_id' => $semester->id,
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
    ])->assertSessionHasErrors('deskripsi');

    $followUp = $this->actingAs($manager)->get(route('admin.komponen-penilaian.create'));

    $followUp->assertSee('value="'.$tahunAjaran->id.'" selected', false);
    $followUp->assertSee('value="'.$semester->id.'" selected', false);
});

it('rejects storing a new assessment component when total bobot exceeds 100 percent in Admin portal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    KomponenPenilaian::create([
        'lembaga_id' => $lembaga->id,
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'kode' => 'K-1',
        'deskripsi' => 'Existing',
        'bobot' => 80,
    ]);

    $response = $this->actingAs($manager)->postJson(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'kode' => 'K-2',
        'deskripsi' => 'Overload',
        'bobot' => 30,
    ]);

    $response->assertStatus(422)->assertJson(['status' => 'error']);
    expect(KomponenPenilaian::where('kode', 'K-2')->exists())->toBeFalse();
});

function actingAsYayasanKomponenManager(Yayasan $yayasan): User
{
    Permission::firstOrCreate(['name' => 'komponen-penilaian.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_admin_komponen', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['komponen-penilaian.kelola']);

    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    return $manager;
}

it('recomputes lembaga_id to follow the new semester for elemen_cp when a yayasan actor moves it across lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $elemenCp = ElemenCp::factory()->create();

    $createAction = app(CreateKomponenPenilaianAction::class);
    $komponen = $createAction->execute(new KomponenPenilaianData(
        subjekType: 'elemen_cp',
        subjekId: $elemenCp->id,
        semesterId: $semesterA->id,
        kode: 'ECP-1',
        deskripsi: 'Deskripsi awal',
        bobot: 100,
        kktp: null,
        kktpMinimal: null,
        assessmentType: null,
    ));
    expect($komponen->lembaga_id)->toBe($lembagaA->id);

    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => $elemenCp->id,
        'semester_id' => $semesterB->id,
        'kode' => 'ECP-1',
        'deskripsi' => 'Deskripsi awal',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->semester_id)->toBe($semesterB->id);
    expect($komponen->lembaga_id)->toBe($lembagaB->id);
});

it('recomputes lembaga_id to follow the new semester for mata_pelajaran when a yayasan actor moves it across lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $mapelB = MataPelajaran::factory()->create(['lembaga_id' => $lembagaB->id]);

    $createAction = app(CreateKomponenPenilaianAction::class);
    $komponen = $createAction->execute(new KomponenPenilaianData(
        subjekType: 'mata_pelajaran',
        subjekId: $mapelA->id,
        semesterId: $semesterA->id,
        kode: 'MP-1',
        deskripsi: 'Deskripsi awal',
        bobot: 100,
        kktp: null,
        kktpMinimal: null,
        assessmentType: null,
    ));
    expect($komponen->lembaga_id)->toBe($lembagaA->id);

    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapelB->id,
        'semester_id' => $semesterB->id,
        'kode' => 'MP-1',
        'deskripsi' => 'Deskripsi awal',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->subjek_id)->toBe($mapelB->id);
    expect($komponen->semester_id)->toBe($semesterB->id);
    expect($komponen->lembaga_id)->toBe($lembagaB->id);
});

it('does not touch lembaga_id when updating a komponen without changing semester_id', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $createAction = app(CreateKomponenPenilaianAction::class);
    $komponen = $createAction->execute(new KomponenPenilaianData(
        subjekType: 'mata_pelajaran',
        subjekId: $mapel->id,
        semesterId: $semester->id,
        kode: 'MP-STABIL',
        deskripsi: 'Deskripsi awal',
        bobot: 50,
        kktp: null,
        kktpMinimal: null,
        assessmentType: null,
    ));
    $lembagaIdSebelum = $komponen->lembaga_id;

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'Deskripsi diubah tanpa ganti semester',
        'bobot' => 50,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->deskripsi)->toBe('Deskripsi diubah tanpa ganti semester');
    expect($komponen->lembaga_id)->toBe($lembagaIdSebelum);
});

it('tetap konsisten menolak bobot melebihi 100% setelah dibungkus lock (regresi, bukan tes concurrency asli)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    app(CreateKomponenPenilaianAction::class)->execute(new KomponenPenilaianData(
        subjekType: 'mata_pelajaran', subjekId: $mapel->id, semesterId: $semester->id,
        kode: 'A', deskripsi: 'Komponen A', bobot: 60, kktp: null, kktpMinimal: null, assessmentType: null,
    ));

    expect(fn () => app(CreateKomponenPenilaianAction::class)->execute(new KomponenPenilaianData(
        subjekType: 'mata_pelajaran', subjekId: $mapel->id, semesterId: $semester->id,
        kode: 'B', deskripsi: 'Komponen B', bobot: 50, kktp: null, kktpMinimal: null, assessmentType: null,
    )))->toThrow(ValidationException::class);
});
