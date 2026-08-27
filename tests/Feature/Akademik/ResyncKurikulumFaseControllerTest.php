<?php

use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanResyncControllerUser(): array
{
    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return [$manager, $lembaga, $ta];
}

it('shows the diff table for kelas whose kurikulum drifted from the live assignment', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync', [
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id,
    ]));

    $response->assertOk();
    $response->assertSee($kelas->nama);
});

it('applies resync via POST and redirects with success status', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.resync.apply'), [
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'kelas_ids' => [$kelas->id],
    ])->assertRedirect(route('admin.kurikulum-assignment.resync', ['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id]));

    expect($kelas->fresh()->kurikulum->value)->toBe('merdeka');
});

it('rejects resync for a kelas belonging to a different lembaga (cross-tenant guard)', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();
    $lembagaLain = Lembaga::factory()->create();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id])->id, 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.resync.apply'), [
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'kelas_ids' => [$kelasLain->id],
    ])->assertForbidden();

    expect($kelasLain->fresh()->kurikulum->value)->toBe('k13');
});
