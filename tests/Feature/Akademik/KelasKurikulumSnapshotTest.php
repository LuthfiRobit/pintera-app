<?php

use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanKelasKurikulumUser(string $bentukPendidikan = 'SD'): array
{
    foreach (['kelas.view', 'kelas.create', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kelas.view', 'kelas.create', 'kelas.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => $bentukPendidikan]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return [$manager, $lembaga, $tahunAjaran];
}

it('rejects creating a kelas with 422-style redirect when no kurikulum assignment matches', function () {
    [$manager, $lembaga, $ta] = siapkanKelasKurikulumUser('SD');
    // TIDAK ada KurikulumAssignment yang dibuat sama sekali.

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas Tanpa Kurikulum',
        'tingkat' => '1',
    ])->assertSessionHasErrors('tingkat');

    expect(Kelas::where('nama', 'Kelas Tanpa Kurikulum')->exists())->toBeFalse();
});

it('snapshots the resolved kurikulum onto Kelas when created', function () {
    [$manager, $lembaga, $ta] = siapkanKelasKurikulumUser('SD');
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'merdeka']);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas 1A',
        'tingkat' => '1',
    ])->assertRedirect(route('admin.kelas.index'));

    $kelas = Kelas::where('nama', 'Kelas 1A')->firstOrFail();
    expect($kelas->kurikulum->value)->toBe('merdeka');
});

it('does not change Kelas.kurikulum when the underlying assignment is edited afterwards', function () {
    [$manager, $lembaga, $ta] = siapkanKelasKurikulumUser('SD');
    $assignment = KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas Lama',
        'tingkat' => '1',
    ]);
    $kelasLama = Kelas::where('nama', 'Kelas Lama')->firstOrFail();

    $assignment->update(['kurikulum' => 'merdeka']);

    expect($kelasLama->fresh()->kurikulum->value)->toBe('k13');
});

it('does not let UpdateKelasAction change kurikulum via the edit form', function () {
    [$manager, $lembaga, $ta] = siapkanKelasKurikulumUser('SD');
    KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);

    $this->actingAs($manager)->post(route('admin.kelas.store'), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas Update',
        'tingkat' => '1',
    ]);
    $kelas = Kelas::where('nama', 'Kelas Update')->firstOrFail();
    expect($kelas->kurikulum->value)->toBe('k13');

    $this->actingAs($manager)->put(route('admin.kelas.update', $kelas), [
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas Update (edited)',
        'tingkat' => '2',
    ])->assertRedirect(route('admin.kelas.index'));

    expect($kelas->fresh()->kurikulum->value)->toBe('k13');
    expect($kelas->fresh()->nama)->toBe('Kelas Update (edited)');
});

it('reads legacy kelas with kurikulum=null without error', function () {
    [$manager, $lembaga, $ta] = siapkanKelasKurikulumUser('SD');
    $legacy = Kelas::create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'nama' => 'Kelas Legacy',
        'tingkat' => '1',
    ]);

    expect($legacy->fresh()->kurikulum)->toBeNull();

    $this->actingAs($manager)->get(route('admin.kelas.index'))->assertOk();
});
