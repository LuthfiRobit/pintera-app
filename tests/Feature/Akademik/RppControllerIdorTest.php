<?php

use App\Domains\Akademik\Actions\Rpp\VerifyRppAction;
use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\Rpp;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function siapkanRppIdorFixture(): array
{
    Storage::fake('public');
    Permission::firstOrCreate(['name' => 'rpp.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rpp.kelola', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rpp.verify', 'guard_name' => 'web']);

    $roleGuru = Role::firstOrCreate(['name' => 'guru_idor_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $roleGuru->givePermissionTo(['rpp.view', 'rpp.kelola']);

    $roleKurikulum = Role::firstOrCreate(['name' => 'wakasek_idor_test', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $roleKurikulum->givePermissionTo(['rpp.view', 'rpp.kelola', 'rpp.verify']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $userGuruA = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userGuruA->assignRole($roleGuru);
    $guruA = Guru::factory()->create(['user_id' => $userGuruA->id, 'lembaga_id' => $lembaga->id]);

    $userGuruB = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userGuruB->assignRole($roleGuru);
    $guruB = Guru::factory()->create(['user_id' => $userGuruB->id, 'lembaga_id' => $lembaga->id]);

    $userKurikulum = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKurikulum->assignRole($roleKurikulum);

    $rppMilikA = Rpp::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'guru_id' => $guruA->id,
        'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id, 'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id, 'judul_topik' => 'RPP Milik Guru A',
        'alokasi_waktu' => '2 JP', 'file_path' => 'rpp/milik-a.pdf', 'file_name' => 'milik-a.pdf',
        'file_size_bytes' => 1024, 'mime_type' => 'application/pdf', 'status' => StatusRpp::Draft,
    ]);
    Storage::disk('public')->put('rpp/milik-a.pdf', 'dummy-content');

    return [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA];
}

it('rejects Guru B updating an RPP owned by Guru A', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userGuruB)->put(route('admin.rpp.update', $rppMilikA), [
        'kelas_id' => $rppMilikA->kelas_id,
        'judul_topik' => 'Diubah Paksa Oleh Guru B',
        'alokasi_waktu' => '2 JP',
    ])->assertForbidden();

    expect($rppMilikA->fresh()->judul_topik)->toBe('RPP Milik Guru A');
});

it('rejects Guru B submitting an RPP owned by Guru A', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userGuruB)->post(route('admin.rpp.submit', $rppMilikA))->assertForbidden();

    expect($rppMilikA->fresh()->status)->toBe(StatusRpp::Draft);
});

it('rejects Guru B destroying an RPP owned by Guru A', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userGuruB)->delete(route('admin.rpp.destroy', $rppMilikA))->assertForbidden();

    expect(Rpp::find($rppMilikA->id))->not->toBeNull();
});

it('allows Guru A to update, submit their own RPP as before', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userGuruA)->put(route('admin.rpp.update', $rppMilikA), [
        'kelas_id' => $rppMilikA->kelas_id,
        'judul_topik' => 'Diubah Oleh Pemilik Sah',
        'alokasi_waktu' => '2 JP',
    ])->assertRedirect();

    expect($rppMilikA->fresh()->judul_topik)->toBe('Diubah Oleh Pemilik Sah');

    $this->actingAs($userGuruA)->post(route('admin.rpp.submit', $rppMilikA))->assertRedirect();
    expect($rppMilikA->fresh()->status)->toBe(StatusRpp::Diajukan);
});

it('rejects Guru B downloading an RPP owned by Guru A without rpp.verify', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userGuruB)->get(route('admin.rpp.download', $rppMilikA))->assertForbidden();
});

it('allows a user with rpp.verify to download any RPP in the same lembaga', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userKurikulum)->get(route('admin.rpp.download', $rppMilikA))->assertOk();
});

it('allows Guru A to download their own RPP as before', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userGuruA)->get(route('admin.rpp.download', $rppMilikA))->assertOk();
});

it('rejects store when the guru does not teach the selected kelas+mapel+semester combination', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();
    $kelas = Kelas::find($rppMilikA->kelas_id);
    $mapel = MataPelajaran::find($rppMilikA->mata_pelajaran_id);
    $semester = Semester::find($rppMilikA->semester_id);
    $file = UploadedFile::fake()->create('rpp-baru.pdf', 100, 'application/pdf');

    // Guru B tidak punya JadwalPelajaran untuk kombinasi kelas+mapel+semester ini.
    $this->actingAs($userGuruB)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'mata_pelajaran_id' => $mapel->id,
        'judul_topik' => 'RPP Tidak Sah',
        'alokasi_waktu' => '2 JP',
        'file' => $file,
    ])->assertForbidden();

    expect(Rpp::where('judul_topik', 'RPP Tidak Sah')->exists())->toBeFalse();
});

it('allows store when the guru actually teaches the selected combination (via JadwalPelajaran)', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();
    $kelas = Kelas::find($rppMilikA->kelas_id);
    $mapel = MataPelajaran::find($rppMilikA->mata_pelajaran_id);
    $semester = Semester::find($rppMilikA->semester_id);
    $guruB = $userGuruB->guru;
    $jamPelajaran = JamPelajaran::factory()->create();
    JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'guru_id' => $guruB->id, 'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id, 'jam_pelajaran_id' => $jamPelajaran->id,
    ]);
    $file = UploadedFile::fake()->create('rpp-sah.pdf', 100, 'application/pdf');

    $this->actingAs($userGuruB)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'mata_pelajaran_id' => $mapel->id,
        'judul_topik' => 'RPP Sah Guru B',
        'alokasi_waktu' => '2 JP',
        'file' => $file,
    ])->assertRedirect();

    expect(Rpp::where('judul_topik', 'RPP Sah Guru B')->exists())->toBeTrue();
});

it('rejects store of a tematik RPP (no mata_pelajaran_id) when the guru is not the wali kelas', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();
    $kelas = Kelas::find($rppMilikA->kelas_id);
    $semester = Semester::find($rppMilikA->semester_id);
    // $kelas belum punya wali_kelas_guru_id (default null dari factory) -- guru mana pun BUKAN wali kelasnya.
    $file = UploadedFile::fake()->create('rpp-tematik.pdf', 100, 'application/pdf');

    $this->actingAs($userGuruB)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'mata_pelajaran_id' => null,
        'judul_topik' => 'RPP Tematik Tidak Sah',
        'alokasi_waktu' => '1 Pekan',
        'file' => $file,
    ])->assertForbidden();

    expect(Rpp::where('judul_topik', 'RPP Tematik Tidak Sah')->exists())->toBeFalse();
});

it('allows store of a tematik RPP when the guru IS the wali kelas', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();
    $kelas = Kelas::find($rppMilikA->kelas_id);
    $semester = Semester::find($rppMilikA->semester_id);
    $guruB = $userGuruB->guru;
    $kelas->update(['wali_kelas_guru_id' => $guruB->id]);
    $file = UploadedFile::fake()->create('rpp-tematik-sah.pdf', 100, 'application/pdf');

    $this->actingAs($userGuruB)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'mata_pelajaran_id' => null,
        'judul_topik' => 'RPP Tematik Sah Wali Kelas',
        'alokasi_waktu' => '1 Pekan',
        'file' => $file,
    ])->assertRedirect();

    expect(Rpp::where('judul_topik', 'RPP Tematik Sah Wali Kelas')->exists())->toBeTrue();
});

it('rejects verify from a verifier belonging to a different lembaga than the RPP', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $userKurikulumLembagaLain = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $userKurikulumLembagaLain->assignRole('wakasek_idor_test');

    $rppMilikA->update(['status' => StatusRpp::Diajukan]);

    // Lapis 1: HTTP route-model-binding terisolasi otomatis via TenantScope (404)
    $this->actingAs($userKurikulumLembagaLain)->post(route('admin.rpp.verify', $rppMilikA), [
        'status' => StatusRpp::Disetujui->value,
    ])->assertNotFound();

    // Lapis 2: Action defense-in-depth cross-check melempar ValidationException
    expect(fn () => (new VerifyRppAction)->execute(
        rpp: $rppMilikA,
        targetStatus: StatusRpp::Disetujui,
        verifierUserId: (int) $userKurikulumLembagaLain->id,
        verifierLembagaId: (int) $userKurikulumLembagaLain->lembaga_id
    ))->toThrow(ValidationException::class);

    expect($rppMilikA->fresh()->status)->toBe(StatusRpp::Diajukan);
});

it('allows verify from a verifier in the same lembaga as before', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();
    $rppMilikA->update(['status' => StatusRpp::Diajukan]);

    $this->actingAs($userKurikulum)->post(route('admin.rpp.verify', $rppMilikA), [
        'status' => StatusRpp::Disetujui->value,
    ])->assertRedirect();

    expect($rppMilikA->fresh()->status)->toBe(StatusRpp::Disetujui);
});
