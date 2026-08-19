<?php

use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanWaliKelasUntukRapor(string $bentukPendidikan = 'SD'): array
{
    Permission::firstOrCreate(['name' => 'rapor.input-wali', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rapor.ajukan', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_wali_rapor', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['rapor.input-wali', 'rapor.ajukan']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => $bentukPendidikan]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);

    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);

    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'wali_kelas_guru_id' => $guru->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Ahmad Fauzi']);

    return compact('guruUser', 'guru', 'kelas', 'siswa', 'lembaga', 'yayasan', 'tahunAjaran', 'semester');
}

it('denies access without rapor.input-wali permission', function () {
    $this->actingAs(User::factory()->create())->get(route('guru.rapor.catatan.index'))->assertForbidden();
});

it('lists siswa in the kelas the guru is wali kelas of, with a completeness badge', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester, 'tahunAjaran' => $tahunAjaran] = siapkanWaliKelasUntukRapor();

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.index', [
        'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id, 'kelas_id' => $kelas->id,
    ]));

    $response->assertOk();
    $response->assertSee('Ahmad Fauzi');
    $response->assertViewHas('siswaList', function ($list) use ($siswa) {
        return $list->firstWhere('id', $siswa->id)?->catatan_lengkap === false;
    });
});

it('marks a siswa complete once a CatatanWaliKelas row exists for that semester', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    CatatanWaliKelas::factory()->create(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]);

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertViewHas('siswaList', function ($list) use ($siswa) {
        return $list->firstWhere('id', $siswa->id)?->catatan_lengkap === true;
    });
});

it('does not list a kelas the guru is not wali kelas of', function () {
    ['guruUser' => $guruUser, 'lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran] = siapkanWaliKelasUntukRapor();
    $kelasBukanWali = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.index', ['kelas_id' => $kelasBukanWali->id]));

    $response->assertViewHas('kelas', fn ($kelas) => $kelas === null);
});

it('shows antropometri fields on the edit form for a TK kelas but not for an SMP kelas', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor('TK');
    $responseTk = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.edit', ['siswa' => $siswa->id, 'semester_id' => $semester->id]));
    $responseTk->assertOk();
    $responseTk->assertViewHas('tampilkanAntropometri', true);
    $responseTk->assertViewHas('tampilkanPklInfo', false);

    ['guruUser' => $guruUserSmp, 'siswa' => $siswaSmp, 'semester' => $semesterSmp] = siapkanWaliKelasUntukRapor('SMP');
    $responseSmp = $this->actingAs($guruUserSmp)->get(route('guru.rapor.catatan.edit', ['siswa' => $siswaSmp->id, 'semester_id' => $semesterSmp->id]));
    $responseSmp->assertOk();
    $responseSmp->assertViewHas('tampilkanAntropometri', false);
    $responseSmp->assertViewHas('tampilkanPklInfo', false);
});

it('shows pkl_info fields on the edit form for an SMK kelas', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor('SMK');

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.edit', ['siswa' => $siswa->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('tampilkanPklInfo', true);
    $response->assertViewHas('tampilkanAntropometri', false);
});

it('denies opening a siswa edit form the guru is not wali kelas of', function () {
    ['guruUser' => $guruUser, 'lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLain->id]);

    $this->actingAs($guruUser)
        ->get(route('guru.rapor.catatan.edit', ['siswa' => $siswaLain->id, 'semester_id' => $semester->id]))
        ->assertForbidden();
});

it('requires a semester_id query param to open the edit form', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa] = siapkanWaliKelasUntukRapor();

    $this->actingAs($guruUser)
        ->get(route('guru.rapor.catatan.edit', ['siswa' => $siswa->id]))
        ->assertNotFound();
});
