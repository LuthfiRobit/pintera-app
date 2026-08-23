<?php

use App\Models\Guru;
use App\Domains\Sdm\Models\JabatanTambahanMaster;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([
        'jabatan-tambahan-master.view',
        'jabatan-tambahan-master.create',
        'jabatan-tambahan-master.edit',
        'jabatan-tambahan-master.delete',
    ]);
});

it('denies access to unauthorized users without view permission', function () {
    $guest = User::factory()->create();
    $this->actingAs($guest)->get(route('admin.jabatan-tambahan-master.index'))->assertForbidden();
});

it('allows authorized admin to store a new master position via JSON', function () {
    $response = $this->actingAs($this->admin)->postJson(route('admin.jabatan-tambahan-master.store'), [
        'nama' => 'Koordinator IT Sekolah',
        'kelompok' => 'fungsional',
    ]);

    $response->assertStatus(201)
             ->assertJsonStructure(['message', 'item' => ['id', 'nama', 'kelompok', 'guru_count']]);

    expect(JabatanTambahanMaster::where('nama', 'Koordinator IT Sekolah')->exists())->toBeTrue();
});

it('rejects duplicate position name via JSON validation', function () {
    JabatanTambahanMaster::create(['nama' => 'Wali Kelas', 'kelompok' => 'fungsional']);

    $response = $this->actingAs($this->admin)->postJson(route('admin.jabatan-tambahan-master.store'), [
        'nama' => 'Wali Kelas',
        'kelompok' => 'fungsional',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['nama']);
});

it('allows updating an existing position via JSON', function () {
    $jabatan = JabatanTambahanMaster::create(['nama' => 'Wakasek Lama', 'kelompok' => 'struktural']);

    $response = $this->actingAs($this->admin)->putJson(route('admin.jabatan-tambahan-master.update', $jabatan), [
        'nama' => 'Wakasek Baru',
        'kelompok' => 'struktural',
    ]);

    $response->assertStatus(200)
             ->assertJson(['message' => 'Data jabatan berhasil diperbarui']);

    expect($jabatan->fresh()->nama)->toBe('Wakasek Baru');
});

it('allows deleting an unassigned master position via JSON', function () {
    $jabatan = JabatanTambahanMaster::create(['nama' => 'Jabatan Sementara', 'kelompok' => 'fungsional']);

    $response = $this->actingAs($this->admin)->deleteJson(route('admin.jabatan-tambahan-master.destroy', $jabatan));

    $response->assertStatus(200)
             ->assertJson(['message' => 'Jabatan telah dihapus permanen.']);

    expect(JabatanTambahanMaster::where('id', $jabatan->id)->exists())->toBeFalse();
});

it('prevents deleting a master position that is currently assigned to a guru', function () {
    $jabatan = JabatanTambahanMaster::create(['nama' => 'Wali Kelas Aktif', 'kelompok' => 'fungsional']);
    $guru = Guru::factory()->create();
    $guru->jabatanTambahan()->attach($jabatan->id, ['no_sk' => 'SK-001', 'mulai_periode' => '2025-07-01']);

    $response = $this->actingAs($this->admin)->deleteJson(route('admin.jabatan-tambahan-master.destroy', $jabatan));

    $response->assertStatus(422)
             ->assertJson([
                 'message' => 'Jabatan tidak dapat dihapus karena saat ini masih disandang oleh 1 Guru aktif. Lepaskan tautan jabatan pada guru bersangkutan sebelum menghapusnya.'
             ]);

    expect(JabatanTambahanMaster::where('id', $jabatan->id)->exists())->toBeTrue();
});

it('renders the reactive SPA portal view cleanly with expected Alpine data bindings and tab bar', function () {
    JabatanTambahanMaster::create(['nama' => 'Wali Kelas', 'kelompok' => 'fungsional']);
    JabatanTambahanMaster::create(['nama' => 'Wakasek Kurikulum', 'kelompok' => 'struktural']);

    $response = $this->actingAs($this->admin)->get(route('admin.jabatan-tambahan-master.index'));

    $response->assertStatus(200)
             ->assertSee('Wali Kelas')
             ->assertSee('Wakasek Kurikulum')
             ->assertSee('Master Jabatan Tambahan')
             ->assertSee('activeFilter')
             ->assertSee('scrollbar-none');
});
