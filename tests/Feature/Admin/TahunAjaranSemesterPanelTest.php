<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsTahunAjaranManager(Lembaga $lembaga): User
{
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo($permissions);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('creates a tahun ajaran auto-scoped to the acting lembaga-scoped user', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);

    $this->actingAs($manager)->post(route('admin.tahun-ajaran.store'), [
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
    ])->assertRedirect(route('admin.tahun-ajaran.index'));

    $tahunAjaran = TahunAjaran::where('nama', '2026/2027')->first();
    expect($tahunAjaran->lembaga_id)->toBe($lembaga->id);
});

it('activates a tahun ajaran via the panel, deactivating the previous one', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);
    $this->actingAs($manager);

    $lama = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => true,
    ]);
    $baru = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30',
    ]);

    $this->patch(route('admin.tahun-ajaran.activate', $baru))->assertRedirect(route('admin.tahun-ajaran.index'));

    expect($lama->fresh()->status_aktif)->toBeFalse();
    expect($baru->fresh()->status_aktif)->toBeTrue();
});

it('creates a semester under a tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);
    $this->actingAs($manager);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);

    $this->post(route('admin.semester.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'ganjil_kode_dapodik' => '20261',
        'ganjil_tanggal_mulai' => '2026-07-01',
        'ganjil_tanggal_selesai' => '2026-12-31',
        'genap_kode_dapodik' => '20262',
        'genap_tanggal_mulai' => '2027-01-01',
        'genap_tanggal_selesai' => '2027-06-30',
    ])->assertRedirect(route('admin.tahun-ajaran.index'));

    expect(Semester::where('tahun_ajaran_id', $tahunAjaran->id)->where('nama', 'Ganjil')->exists())->toBeTrue();
    expect(Semester::where('tahun_ajaran_id', $tahunAjaran->id)->where('nama', 'Genap')->exists())->toBeTrue();
});

it('rejects creating a semester under a tahun_ajaran belonging to a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembagaSaya);
    $this->actingAs($manager);

    $tahunAjaranLain = TahunAjaran::create([
        'lembaga_id' => $lembagaLain->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30',
    ]);

    $this->post(route('admin.semester.store'), [
        'tahun_ajaran_id' => $tahunAjaranLain->id,
        'ganjil_kode_dapodik' => '20261',
        'ganjil_tanggal_mulai' => '2026-07-01',
        'ganjil_tanggal_selesai' => '2026-12-31',
        'genap_kode_dapodik' => '20262',
        'genap_tanggal_mulai' => '2027-01-01',
        'genap_tanggal_selesai' => '2027-06-30',
    ])->assertNotFound();

    expect(Semester::where('tahun_ajaran_id', $tahunAjaranLain->id)->exists())->toBeFalse();
});

it('shows a friendly error instead of a 500 when activating a semester whose tahun ajaran is inactive', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);
    $this->actingAs($manager);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => false,
    ]);
    $semester = Semester::create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil', 'urutan' => 1,
        'kode_dapodik' => '20261', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-01-15',
    ]);

    $this->patch(route('admin.semester.activate', $semester))
        ->assertRedirect()
        ->assertSessionHasErrors('semester');

    expect($semester->fresh()->status_aktif)->toBeFalse();
});

it('lets a yayasan-scoped user create a tahun ajaran for the lembaga they have switched into', function () {
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo($permissions);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    $this->actingAs($manager);

    session(['active_lembaga_id' => $lembaga->id]);

    $this->post(route('admin.tahun-ajaran.store'), [
        'nama' => '2027/2028',
        'tanggal_mulai' => '2027-07-01',
        'tanggal_selesai' => '2028-06-30',
    ])->assertRedirect(route('admin.tahun-ajaran.index'));

    $tahunAjaran = TahunAjaran::where('nama', '2027/2028')->first();
    expect($tahunAjaran)->not->toBeNull();
    expect($tahunAjaran->lembaga_id)->toBe($lembaga->id);
});

it('shows a friendly error instead of a 500 when a yayasan-scoped user creates a tahun ajaran without switching to a lembaga', function () {
    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo($permissions);

    $manager = User::factory()->create();
    $manager->assignRole($role);

    $this->actingAs($manager)->post(route('admin.tahun-ajaran.store'), [
        'nama' => '2027/2028',
        'tanggal_mulai' => '2027-07-01',
        'tanggal_selesai' => '2028-06-30',
    ])->assertSessionHasErrors('lembaga_id');

    expect(TahunAjaran::withoutGlobalScopes()->where('nama', '2027/2028')->exists())->toBeFalse();
});
