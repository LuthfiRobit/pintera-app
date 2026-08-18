<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('has dropped the legacy iuran columns now that jenis_tagihan covers billing', function () {
    expect(\Illuminate\Support\Facades\Schema::hasColumn('lembaga', 'memungut_iuran'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('lembaga', 'nominal_iuran'))->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasColumn('lembaga', 'periode_iuran'))->toBeFalse();
});

it('denies access to a user without lembaga.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.lembaga.index'))->assertForbidden();
});

it('lets a yayasan-scoped user create a new lembaga', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();

    $this->actingAs($manager)->post(route('admin.lembaga.store'), [
        'yayasan_id' => $yayasan->id,
        'npsn' => '20301234',
        'kode_lembaga' => 'SMAPT3',
        'nama' => 'SMA Pintera Tiga',
        'bentuk_pendidikan' => 'SMA',
        'status_sekolah' => 'swasta',
        'naungan' => 'kemendikdasmen',
    ])->assertRedirect(route('admin.lembaga.index'));

    expect(Lembaga::where('npsn', '20301234')->exists())->toBeTrue();
});

it('forbids a lembaga-scoped user from editing a lembaga that is not their own', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);

    $yayasan = Yayasan::factory()->create();
    $ownLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $otherLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = User::factory()->create(['lembaga_id' => $ownLembaga->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.lembaga.edit', $otherLembaga))->assertForbidden();
});

it('filters the index by name or npsn when cari is given', function () {
    Permission::firstOrCreate(['name' => 'lembaga.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Pintera Satu', 'npsn' => '20111111']);
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Pintera Dua', 'npsn' => '20222222']);

    $byName = $this->actingAs($manager)->get(route('admin.lembaga.index', ['cari' => 'Pintera Satu']));
    expect($byName->viewData('lembaga')->pluck('nama')->all())->toBe(['SD Pintera Satu']);

    $byNpsn = $this->actingAs($manager)->get(route('admin.lembaga.index', ['cari' => '20222222']));
    expect($byNpsn->viewData('lembaga')->pluck('nama')->all())->toBe(['SMP Pintera Dua']);
});

it('filters the index by bentuk pendidikan', function () {
    Permission::firstOrCreate(['name' => 'lembaga.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Pintera', 'bentuk_pendidikan' => 'SD']);
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Pintera', 'bentuk_pendidikan' => 'SMP']);

    $response = $this->actingAs($manager)->get(route('admin.lembaga.index', ['bentuk' => 'SD']));

    expect($response->viewData('lembaga')->pluck('nama')->all())->toBe(['SD Pintera']);
});

it('filters the index by status sekolah', function () {
    Permission::firstOrCreate(['name' => 'lembaga.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'Lembaga Negeri', 'status_sekolah' => 'negeri']);
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'Lembaga Swasta', 'status_sekolah' => 'swasta']);

    $response = $this->actingAs($manager)->get(route('admin.lembaga.index', ['status' => 'negeri']));

    expect($response->viewData('lembaga')->pluck('nama')->all())->toBe(['Lembaga Negeri']);
});

it('paginates the index at the requested per_page size', function () {
    Permission::firstOrCreate(['name' => 'lembaga.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    Lembaga::factory()->count(12)->create(['yayasan_id' => $yayasan->id]);

    $response = $this->actingAs($manager)->get(route('admin.lembaga.index', ['per_page' => 10]));

    $response->assertOk();
    expect($response->viewData('lembaga'))->toHaveCount(10);
    expect($response->viewData('lembaga')->total())->toBe(12);
});

it('lets a lembaga-scoped user edit their own lembaga', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);

    $yayasan = Yayasan::factory()->create();
    $ownLembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $manager = User::factory()->create(['lembaga_id' => $ownLembaga->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->put(route('admin.lembaga.update', $ownLembaga), [
        'yayasan_id' => $yayasan->id,
        'npsn' => $ownLembaga->npsn,
        'kode_lembaga' => $ownLembaga->kode_lembaga,
        'nama' => 'Nama Baru',
        'bentuk_pendidikan' => $ownLembaga->bentuk_pendidikan,
        'status_sekolah' => $ownLembaga->status_sekolah,
        'naungan' => $ownLembaga->naungan,
    ])->assertRedirect(route('admin.lembaga.index'));

    expect($ownLembaga->fresh()->nama)->toBe('Nama Baru');
});

it('defaults status_aktif to true on create when the checkbox key is absent from the request', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();

    $this->actingAs($manager)->post(route('admin.lembaga.store'), [
        'yayasan_id' => $yayasan->id,
        'npsn' => '20301235',
        'kode_lembaga' => 'SMAPT4',
        'nama' => 'SMA Pintera Empat',
        'bentuk_pendidikan' => 'SMA',
        'status_sekolah' => 'swasta',
        'naungan' => 'kemendikdasmen',
        // status_aktif deliberately omitted, like an unchecked box or a raw API call
    ]);

    expect(Lembaga::where('npsn', '20301235')->first()->status_aktif)->toBeTrue();
});

it('turns status_aktif and mbs off when their checkboxes are left unchecked on update', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create([
        'yayasan_id' => $yayasan->id,
        'status_aktif' => true,
        'mbs' => true,
    ]);

    $this->actingAs($manager)->put(route('admin.lembaga.update', $lembaga), [
        'yayasan_id' => $yayasan->id,
        'npsn' => $lembaga->npsn,
        'kode_lembaga' => $lembaga->kode_lembaga,
        'nama' => $lembaga->nama,
        'bentuk_pendidikan' => $lembaga->bentuk_pendidikan,
        'status_sekolah' => $lembaga->status_sekolah,
        'naungan' => $lembaga->naungan,
        // both checkboxes omitted, simulating the user unchecking them
    ])->assertRedirect(route('admin.lembaga.index'));

    $fresh = $lembaga->fresh();
    expect($fresh->status_aktif)->toBeFalse();
    expect($fresh->mbs)->toBeFalse();
});

it('rejects a duplicate npsn on create but allows a lembaga to keep its own npsn on update', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    $existing = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'npsn' => '20301236']);

    $this->actingAs($manager)->post(route('admin.lembaga.store'), [
        'yayasan_id' => $yayasan->id,
        'npsn' => '20301236',
        'kode_lembaga' => 'SMAPT5',
        'nama' => 'SMA Pintera Lima',
        'bentuk_pendidikan' => 'SMA',
        'status_sekolah' => 'swasta',
        'naungan' => 'kemendikdasmen',
    ])->assertSessionHasErrors('npsn');

    $this->actingAs($manager)->put(route('admin.lembaga.update', $existing), [
        'yayasan_id' => $yayasan->id,
        'npsn' => '20301236',
        'kode_lembaga' => $existing->kode_lembaga,
        'nama' => 'Nama Diperbarui',
        'bentuk_pendidikan' => $existing->bentuk_pendidikan,
        'status_sekolah' => $existing->status_sekolah,
        'naungan' => $existing->naungan,
    ])->assertSessionDoesntHaveErrors('npsn');
});

it('persists the extended profile fields (alamat, kontak, bank) on create', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();

    $this->actingAs($manager)->post(route('admin.lembaga.store'), [
        'yayasan_id' => $yayasan->id,
        'npsn' => '20301237',
        'kode_lembaga' => 'SMAPT6',
        'nama' => 'SMA Pintera Enam',
        'bentuk_pendidikan' => 'SMA',
        'status_sekolah' => 'swasta',
        'naungan' => 'kemendikdasmen',
        'kecamatan' => 'Lowokwaru',
        'kabupaten_kota' => 'Malang',
        'provinsi' => 'Jawa Timur',
        'telepon' => '0341-123456',
        'nama_bank' => 'Bank Jatim',
    ])->assertRedirect(route('admin.lembaga.index'));

    $created = Lembaga::where('npsn', '20301237')->first();
    expect($created->kecamatan)->toBe('Lowokwaru');
    expect($created->kabupaten_kota)->toBe('Malang');
    expect($created->provinsi)->toBe('Jawa Timur');
    expect($created->nama_bank)->toBe('Bank Jatim');
});

it('requires a unique kode_lembaga on create but allows a lembaga to keep its own on update', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    $existing = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMPPRM']);

    // Missing kode_lembaga entirely.
    $this->actingAs($manager)->post(route('admin.lembaga.store'), [
        'yayasan_id' => $yayasan->id,
        'npsn' => '20301238',
        'nama' => 'SMA Pintera Tujuh',
        'bentuk_pendidikan' => 'SMA',
        'status_sekolah' => 'swasta',
        'naungan' => 'kemendikdasmen',
    ])->assertSessionHasErrors('kode_lembaga');

    // Duplicate kode_lembaga.
    $this->actingAs($manager)->post(route('admin.lembaga.store'), [
        'yayasan_id' => $yayasan->id,
        'npsn' => '20301239',
        'kode_lembaga' => 'SMPPRM',
        'nama' => 'SMA Pintera Delapan',
        'bentuk_pendidikan' => 'SMA',
        'status_sekolah' => 'swasta',
        'naungan' => 'kemendikdasmen',
    ])->assertSessionHasErrors('kode_lembaga');

    // Updating a lembaga with its own existing kode_lembaga is allowed.
    $this->actingAs($manager)->put(route('admin.lembaga.update', $existing), [
        'yayasan_id' => $yayasan->id,
        'npsn' => $existing->npsn,
        'kode_lembaga' => 'SMPPRM',
        'nama' => 'Nama Diperbarui',
        'bentuk_pendidikan' => $existing->bentuk_pendidikan,
        'status_sekolah' => $existing->status_sekolah,
        'naungan' => $existing->naungan,
    ])->assertSessionDoesntHaveErrors('kode_lembaga');
});

it('cascades a kode_lembaga rename to every linked siswa username', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMPLAMA']);
    $siswaUser = User::factory()->create(['lembaga_id' => $lembaga->id, 'username' => 'SMPLAMA-2026001']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nis' => '2026001', 'user_id' => $siswaUser->id]);

    $this->actingAs($manager)->put(route('admin.lembaga.update', $lembaga), [
        'yayasan_id' => $yayasan->id,
        'npsn' => $lembaga->npsn,
        'kode_lembaga' => 'SMPBARU',
        'nama' => $lembaga->nama,
        'bentuk_pendidikan' => $lembaga->bentuk_pendidikan,
        'status_sekolah' => $lembaga->status_sekolah,
        'naungan' => $lembaga->naungan,
    ])->assertRedirect(route('admin.lembaga.index'));

    expect($siswaUser->fresh()->username)->toBe('SMPBARU-2026001');
});

it('cascades a kode_lembaga rename even when the acting yayasan admin has a different lembaga active in session', function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $role->givePermissionTo(['lembaga.view', 'lembaga.create', 'lembaga.edit']);
    $manager = User::factory()->create();
    $manager->assignRole($role);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMPLAMA2']);
    $lembagaLainAktifDiSesi = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswaUser = User::factory()->create(['lembaga_id' => $lembaga->id, 'username' => 'SMPLAMA2-2026001']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nis' => '2026001', 'user_id' => $siswaUser->id]);

    // The acting admin's active_lembaga_id session points at a THIRD lembaga, not the
    // one being renamed. Without bypassing TenantScope this makes the cascade query
    // silently return zero rows (a false negative), leaving the username stale.
    $this->withSession(['active_lembaga_id' => $lembagaLainAktifDiSesi->id]);

    $this->actingAs($manager)->put(route('admin.lembaga.update', $lembaga), [
        'yayasan_id' => $yayasan->id,
        'npsn' => $lembaga->npsn,
        'kode_lembaga' => 'SMPBARU2',
        'nama' => $lembaga->nama,
        'bentuk_pendidikan' => $lembaga->bentuk_pendidikan,
        'status_sekolah' => $lembaga->status_sekolah,
        'naungan' => $lembaga->naungan,
    ])->assertRedirect(route('admin.lembaga.index'));

    expect($siswaUser->fresh()->username)->toBe('SMPBARU2-2026001');
});
