<?php

use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\Rpp;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Storage;
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
