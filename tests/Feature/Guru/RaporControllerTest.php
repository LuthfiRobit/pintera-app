<?php

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\EkstrakurikulerLembaga;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Spatie\Permission\Models\Permission;

function siapkanWaliKelasUntukRapor(string $bentukPendidikan = 'SD'): array
{
    (new RoleSeeder)->run();
    (new WorkflowDefinitionSeeder)->run();
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

it('saves catatan wali kelas via update and redirects back to the index', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester, 'lembaga' => $lembaga] = siapkanWaliKelasUntukRapor();
    EkstrakurikulerLembaga::create(['lembaga_id' => $lembaga->id, 'jenis_ekskul' => 'wajib', 'nama_ekskul' => 'Pramuka']);

    $response = $this->actingAs($guruUser)->put(route('guru.rapor.catatan.update', $siswa), [
        'semester_id' => $semester->id,
        'catatan_sikap' => 'Sopan dan santun.',
        'ekstrakurikuler' => [['nama' => 'Pramuka', 'peran' => 'Anggota']],
    ]);

    $response->assertRedirect(route('guru.rapor.catatan.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));
    $this->assertDatabaseHas('catatan_wali_kelas', [
        'siswa_id' => $siswa->id,
        'semester_id' => $semester->id,
        'catatan_sikap' => 'Sopan dan santun.',
    ]);
});

it('redirects to the next siswa edit page when next_siswa_id is submitted', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $siswaKedua = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Budi Santoso']);

    $response = $this->actingAs($guruUser)->put(route('guru.rapor.catatan.update', $siswa), [
        'semester_id' => $semester->id,
        'next_siswa_id' => $siswaKedua->id,
    ]);

    $response->assertRedirect(route('guru.rapor.catatan.edit', ['siswa' => $siswaKedua->id, 'semester_id' => $semester->id]));
});

it('rejects updating catatan for a siswa the guru is not wali kelas of', function () {
    ['guruUser' => $guruUser, 'lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLain->id]);

    $this->actingAs($guruUser)
        ->put(route('guru.rapor.catatan.update', $siswaLain), ['semester_id' => $semester->id])
        ->assertForbidden();
});

it('generates a narasi draft via AJAX for a siswa with nilai', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'membaca lancar', 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 88]);

    $response = $this->actingAs($guruUser)->post(route('guru.rapor.catatan.generate-narasi', ['siswa' => $siswa->id, 'semester_id' => $semester->id]));
    $response->assertOk();
    $response->assertJson(['narasi' => 'Menunjukkan penguasaan sangat baik dalam membaca lancar.']);
});

it('submits the pengajuan rapor when every siswa has a CatatanWaliKelas', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    CatatanWaliKelas::factory()->create(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]);

    $response = $this->actingAs($guruUser)->post(route('guru.rapor.pengajuan.submit'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
    ]);

    $response->assertRedirect(route('guru.rapor.catatan.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));
    $this->assertDatabaseHas('pengajuan_rapor', [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'status' => StatusPengajuanRapor::Diajukan->value,
    ]);
});

it('redirects back with errors when a siswa is missing a CatatanWaliKelas on submit', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'semester' => $semester] = siapkanWaliKelasUntukRapor();

    $response = $this->actingAs($guruUser)->post(route('guru.rapor.pengajuan.submit'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
    ]);

    $response->assertSessionHasErrors('catatan_wali_kelas');
});

it('rejects submitting a pengajuan for a kelas the guru is not wali kelas of', function () {
    ['guruUser' => $guruUser, 'lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $this->actingAs($guruUser)
        ->post(route('guru.rapor.pengajuan.submit'), ['kelas_id' => $kelasLain->id, 'semester_id' => $semester->id])
        ->assertForbidden();
});

it('streams a pdf for a siswa the guru is wali kelas of', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.cetak', ['siswa' => $siswa->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('rejects printing a pdf for a siswa the guru is not wali kelas of', function () {
    ['guruUser' => $guruUser, 'lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLain->id]);

    $this->actingAs($guruUser)
        ->get(route('guru.rapor.cetak', ['siswa' => $siswaLain->id, 'semester_id' => $semester->id]))
        ->assertForbidden();
});

it('rejects opening the edit form when semester_id belongs to a different tahun ajaran than the siswa kelas', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'lembaga' => $lembaga] = siapkanWaliKelasUntukRapor();
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);

    $this->actingAs($guruUser)
        ->get(route('guru.rapor.catatan.edit', ['siswa' => $siswa->id, 'semester_id' => $semesterLain->id]))
        ->assertNotFound();
});

it('rejects generating narasi when semester_id belongs to a different tahun ajaran than the siswa kelas', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'lembaga' => $lembaga] = siapkanWaliKelasUntukRapor();
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);

    $this->actingAs($guruUser)
        ->post(route('guru.rapor.catatan.generate-narasi', ['siswa' => $siswa->id, 'semester_id' => $semesterLain->id]))
        ->assertNotFound();
});

it('rejects submitting a pengajuan when semester_id belongs to a different tahun ajaran than the kelas', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'lembaga' => $lembaga] = siapkanWaliKelasUntukRapor();
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);

    $this->actingAs($guruUser)
        ->post(route('guru.rapor.pengajuan.submit'), ['kelas_id' => $kelas->id, 'semester_id' => $semesterLain->id])
        ->assertNotFound();
});

it('rejects printing a pdf when semester_id belongs to a different tahun ajaran than the siswa kelas', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'lembaga' => $lembaga] = siapkanWaliKelasUntukRapor();
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);

    $this->actingAs($guruUser)
        ->get(route('guru.rapor.cetak', ['siswa' => $siswa->id, 'semester_id' => $semesterLain->id]))
        ->assertNotFound();
});
