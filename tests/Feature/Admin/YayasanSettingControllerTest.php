<?php

use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsYayasanSettingSuperAdmin(): array
{
    Permission::firstOrCreate(['name' => 'yayasan.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo('yayasan.kelola');

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('yayasan_super_admin');

    return [$user];
}

it('shows the yayasan settings form with existing data', function () {
    [$user] = actingAsYayasanSettingSuperAdmin();
    $yayasan = Yayasan::factory()->create(['nama' => 'Yayasan Permata Kraksaan']);

    $response = $this->actingAs($user)->get(route('admin.yayasan.edit'));

    $response->assertOk();
    $response->assertSee('Yayasan Permata Kraksaan');
});

it('updates all yayasan fields', function () {
    [$user] = actingAsYayasanSettingSuperAdmin();
    Yayasan::factory()->create();

    $response = $this->actingAs($user)->put(route('admin.yayasan.update'), [
        'nama' => 'Yayasan Baru',
        'npwp_yayasan' => '12.345.678.9-012.000',
        'akta_pendirian_nomor' => 'AKT-001',
        'akta_pendirian_tanggal' => '2020-01-15',
        'sk_kemenkumham_nomor' => 'SK-002',
        'alamat' => 'Jl. Contoh No. 1',
        'telepon' => '0331123456',
        'email' => 'yayasan@example.test',
        'website' => 'https://example.test',
        'nama_ketua_pembina' => 'Budi Santoso',
        'nama_ketua_pengurus' => 'Siti Aminah',
    ]);

    $response->assertRedirect(route('admin.yayasan.edit'));
    $this->assertDatabaseHas('yayasan', ['nama' => 'Yayasan Baru', 'nama_ketua_pembina' => 'Budi Santoso']);
});

it('uploads a new logo and deletes the old one', function () {
    Storage::fake('public');
    [$user] = actingAsYayasanSettingSuperAdmin();
    $oldPath = 'yayasan-logo/old.png';
    Storage::disk('public')->put($oldPath, 'dummy-old-content');
    $yayasan = Yayasan::factory()->create(['logo' => $oldPath]);

    $response = $this->actingAs($user)->put(route('admin.yayasan.update'), [
        'nama' => $yayasan->nama,
        'logo' => UploadedFile::fake()->image('logo-baru.png'),
    ]);

    $response->assertRedirect(route('admin.yayasan.edit'));
    $yayasan->refresh();
    Storage::disk('public')->assertExists($yayasan->logo);
    Storage::disk('public')->assertMissing($oldPath);
    expect($yayasan->logo)->not->toBe($oldPath);
});

it('rejects a logo file that is too large', function () {
    Storage::fake('public');
    [$user] = actingAsYayasanSettingSuperAdmin();
    Yayasan::factory()->create();

    $response = $this->actingAs($user)->put(route('admin.yayasan.update'), [
        'nama' => 'Yayasan X',
        'logo' => UploadedFile::fake()->create('logo.png', 2000, 'image/png'),
    ]);

    $response->assertSessionHasErrors('logo');
});

it('denies access to a user without yayasan.kelola permission', function () {
    $user = User::factory()->create(['lembaga_id' => null]);

    $response = $this->actingAs($user)->get(route('admin.yayasan.edit'));

    $response->assertForbidden();
});
