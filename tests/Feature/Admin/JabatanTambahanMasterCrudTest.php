<?php

use App\Domains\Sdm\Models\JabatanTambahanMaster;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function actingAsJabatanTambahanManager(): User
{
    $manager = User::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_jabatan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    foreach (['jabatan-tambahan-master.view', 'jabatan-tambahan-master.create', 'jabatan-tambahan-master.edit', 'jabatan-tambahan-master.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role->givePermissionTo(['jabatan-tambahan-master.view', 'jabatan-tambahan-master.create', 'jabatan-tambahan-master.edit', 'jabatan-tambahan-master.delete']);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to unauthorized users without view permission', function () {
    $guest = User::factory()->create();
    $this->actingAs($guest)->get(route('admin.jabatan-tambahan-master.index'))->assertForbidden();
});

it('allows authorized admin to store a new master position via JSON', function () {
    $manager = actingAsJabatanTambahanManager();

    $response = $this->actingAs($manager)->postJson(route('admin.jabatan-tambahan-master.store'), [
        'nama' => 'Koordinator IT Sekolah',
        'kelompok' => 'fungsional',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['message', 'item' => ['id', 'nama', 'kelompok', 'guru_count']]);

    $item = JabatanTambahanMaster::where('nama', 'Koordinator IT Sekolah')->first();
    expect($item)->not->toBeNull();
    expect($item->yayasan_id)->toBe($manager->yayasan_id);
});

it('rejects duplicate position name via JSON validation', function () {
    $manager = actingAsJabatanTambahanManager();
    JabatanTambahanMaster::factory()->create(['nama' => 'Wali Kelas', 'kelompok' => 'fungsional', 'yayasan_id' => $manager->yayasan_id]);

    $response = $this->actingAs($manager)->postJson(route('admin.jabatan-tambahan-master.store'), [
        'nama' => 'Wali Kelas',
        'kelompok' => 'fungsional',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['nama']);
});

it('allows updating an existing position via JSON', function () {
    $manager = actingAsJabatanTambahanManager();
    $jabatan = JabatanTambahanMaster::factory()->create(['nama' => 'Wakasek Lama', 'kelompok' => 'struktural', 'yayasan_id' => $manager->yayasan_id]);

    $response = $this->actingAs($manager)->putJson(route('admin.jabatan-tambahan-master.update', $jabatan), [
        'nama' => 'Wakasek Baru',
        'kelompok' => 'struktural',
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Data jabatan berhasil diperbarui']);

    expect($jabatan->fresh()->nama)->toBe('Wakasek Baru');
});

it('allows deleting an unassigned master position via JSON', function () {
    $manager = actingAsJabatanTambahanManager();
    $jabatan = JabatanTambahanMaster::factory()->create(['nama' => 'Jabatan Sementara', 'kelompok' => 'fungsional', 'yayasan_id' => $manager->yayasan_id]);

    $response = $this->actingAs($manager)->deleteJson(route('admin.jabatan-tambahan-master.destroy', $jabatan));

    $response->assertStatus(200)
        ->assertJson(['message' => 'Jabatan telah dihapus permanen.']);

    expect(JabatanTambahanMaster::where('id', $jabatan->id)->exists())->toBeFalse();
});

it('prevents deleting a master position that is currently assigned to a guru', function () {
    $manager = actingAsJabatanTambahanManager();
    $jabatan = JabatanTambahanMaster::factory()->create(['nama' => 'Wali Kelas Aktif', 'kelompok' => 'fungsional', 'yayasan_id' => $manager->yayasan_id]);
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru->jabatanTambahan()->attach($jabatan->id, ['no_sk' => 'SK-001', 'mulai_periode' => '2025-07-01']);

    $response = $this->actingAs($manager)->deleteJson(route('admin.jabatan-tambahan-master.destroy', $jabatan));

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Jabatan tidak dapat dihapus karena saat ini masih disandang oleh 1 Guru aktif. Lepaskan tautan jabatan pada guru bersangkutan sebelum menghapusnya.',
        ]);

    expect(JabatanTambahanMaster::where('id', $jabatan->id)->exists())->toBeTrue();
});

it('renders the reactive SPA portal view cleanly with expected Alpine data bindings and tab bar', function () {
    $manager = actingAsJabatanTambahanManager();
    JabatanTambahanMaster::factory()->create(['nama' => 'Wali Kelas', 'kelompok' => 'fungsional', 'yayasan_id' => $manager->yayasan_id]);
    JabatanTambahanMaster::factory()->create(['nama' => 'Wakasek Kurikulum', 'kelompok' => 'struktural', 'yayasan_id' => $manager->yayasan_id]);

    $response = $this->actingAs($manager)->get(route('admin.jabatan-tambahan-master.index'));

    $response->assertStatus(200)
        ->assertSee('Wali Kelas')
        ->assertSee('Wakasek Kurikulum')
        ->assertSee('Master Jabatan Tambahan')
        ->assertSee('activeFilter')
        ->assertSee('scrollbar-none');
});

it('does not leak jabatan tambahan across yayasan boundaries on the index page', function () {
    $managerA = actingAsJabatanTambahanManager();
    $jabatanA = JabatanTambahanMaster::factory()->create(['nama' => 'Milik Yayasan A', 'yayasan_id' => $managerA->yayasan_id]);
    JabatanTambahanMaster::factory()->create(['nama' => 'Milik Yayasan B']);

    $response = $this->actingAs($managerA)->getJson(route('admin.jabatan-tambahan-master.index'));

    $response->assertOk();
    $ids = collect($response->json('items'))->pluck('id');
    expect($ids)->toContain($jabatanA->id);
    expect($ids)->toHaveCount(1);
});

it('allows two different yayasan to use the exact same jabatan tambahan nama', function () {
    $managerA = actingAsJabatanTambahanManager();
    JabatanTambahanMaster::factory()->create(['nama' => 'Wali Kelas']);

    $this->actingAs($managerA)->postJson(route('admin.jabatan-tambahan-master.store'), [
        'nama' => 'Wali Kelas',
        'kelompok' => 'fungsional',
    ])->assertCreated();
});

it('does not block deleting a jabatan tambahan that is only assigned to a guru in a different yayasan', function () {
    $managerA = actingAsJabatanTambahanManager();
    $jabatanA = JabatanTambahanMaster::factory()->create(['yayasan_id' => $managerA->yayasan_id]);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $guruB->jabatanTambahan()->attach($jabatanA->id, ['no_sk' => 'SK-002', 'mulai_periode' => '2025-07-01']);

    $this->actingAs($managerA)->deleteJson(route('admin.jabatan-tambahan-master.destroy', $jabatanA))
        ->assertOk();

    expect(JabatanTambahanMaster::withoutGlobalScopes()->find($jabatanA->id))->toBeNull();
});

it('404s when a manager tries to update or delete a jabatan tambahan owned by a different yayasan', function () {
    $managerA = actingAsJabatanTambahanManager();
    $jabatanB = JabatanTambahanMaster::factory()->create();

    $this->actingAs($managerA)->putJson(route('admin.jabatan-tambahan-master.update', $jabatanB), [
        'nama' => 'Diubah Paksa',
        'kelompok' => 'struktural',
    ])->assertNotFound();

    $this->actingAs($managerA)->deleteJson(route('admin.jabatan-tambahan-master.destroy', $jabatanB))
        ->assertNotFound();

    expect(JabatanTambahanMaster::withoutGlobalScopes()->find($jabatanB->id)->nama)->not->toBe('Diubah Paksa');
});
