<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function createAdminTahunAjaranFeatureUser(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $permissions = ['tahun-ajaran.view', 'tahun-ajaran.create', 'tahun-ajaran.activate', 'semester.create', 'semester.activate'];
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return [$user, $lembaga];
}

it('updates existing tahun ajaran attributes via SPA edit modal action', function () {
    [$user, $lembaga] = createAdminTahunAjaranFeatureUser();
    $ta = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01',
        'tanggal_selesai' => '2026-06-30',
        'status_aktif' => true,
    ]);

    $response = $this->actingAs($user)->put(route('admin.tahun-ajaran.update', $ta), [
        'nama' => '2025/2026 Revisi',
        'tanggal_mulai' => '2025-07-15',
        'tanggal_selesai' => '2026-06-25',
    ]);

    $response->assertRedirect(route('admin.tahun-ajaran.index'))->assertSessionHas('status');
    $this->assertDatabaseHas('tahun_ajaran', [
        'id' => $ta->id,
        'nama' => '2025/2026 Revisi',
    ]);
});

it('creates and updates ganjil and genap semesters via batch upsert endpoint in one request', function () {
    [$user, $lembaga] = createAdminTahunAjaranFeatureUser();
    $ta = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => true,
    ]);

    $response = $this->actingAs($user)->post(route('admin.semester.store'), [
        'tahun_ajaran_id' => $ta->id,
        'ganjil_kode_dapodik' => '20261',
        'ganjil_tanggal_mulai' => '2026-07-01',
        'ganjil_tanggal_selesai' => '2026-12-31',
        'genap_kode_dapodik' => '20262',
        'genap_tanggal_mulai' => '2027-01-01',
        'genap_tanggal_selesai' => '2027-06-30',
    ]);

    $response->assertRedirect(route('admin.tahun-ajaran.index'))->assertSessionHas('status', 'Konfigurasi semester Ganjil & Genap berhasil disimpan.');
    expect(Semester::where('tahun_ajaran_id', $ta->id)->count())->toBe(2);
    $this->assertDatabaseHas('semester', ['tahun_ajaran_id' => $ta->id, 'nama' => 'Ganjil', 'urutan' => 1, 'kode_dapodik' => '20261']);
    $this->assertDatabaseHas('semester', ['tahun_ajaran_id' => $ta->id, 'nama' => 'Genap', 'urutan' => 2, 'kode_dapodik' => '20262']);

    // Second hit should update in place without duplication
    $this->actingAs($user)->post(route('admin.semester.store'), [
        'tahun_ajaran_id' => $ta->id,
        'ganjil_kode_dapodik' => '20263',
        'ganjil_tanggal_mulai' => '2026-07-05',
        'ganjil_tanggal_selesai' => '2026-12-20',
        'genap_kode_dapodik' => '20264',
        'genap_tanggal_mulai' => '2027-01-05',
        'genap_tanggal_selesai' => '2027-06-25',
    ]);

    expect(Semester::where('tahun_ajaran_id', $ta->id)->count())->toBe(2);
    $this->assertDatabaseHas('semester', ['tahun_ajaran_id' => $ta->id, 'nama' => 'Ganjil', 'kode_dapodik' => '20263']);
});

it('menolak actor yayasan dengan active_lembaga_id stale saat membuat tahun ajaran', function () {
    Permission::firstOrCreate(['name' => 'tahun-ajaran.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_ta_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['tahun-ajaran.create']);

    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->post(route('admin.tahun-ajaran.store'), [
        'nama' => '2099/2100',
        'tanggal_mulai' => '2099-07-01',
        'tanggal_selesai' => '2100-06-30',
    ]);

    $response->assertSessionHasErrors('lembaga_id');
    expect(TahunAjaran::where('nama', '2099/2100')->exists())->toBeFalse();
});
