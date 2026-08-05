<?php
// tests/Feature/Admin/JenisKaryawanMasterCrudTest.php

use App\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function actingAsJenisKaryawanManager(): User
{
    $manager = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    foreach (['jenis-karyawan-master.view', 'jenis-karyawan-master.create', 'jenis-karyawan-master.edit', 'jenis-karyawan-master.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role->givePermissionTo(['jenis-karyawan-master.view', 'jenis-karyawan-master.create', 'jenis-karyawan-master.edit', 'jenis-karyawan-master.delete']);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without jenis-karyawan-master.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.jenis-karyawan-master.index'))->assertForbidden();
});

it('renders the index page for a manager', function () {
    $manager = actingAsJenisKaryawanManager();

    $this->actingAs($manager)->get(route('admin.jenis-karyawan-master.index'))->assertOk();
});

it('renders a well-formed root container, not JS leaking into the page as text', function () {
    // Guards against unescaped `"` inside the x-data attribute (e.g. a JS
    // template literal with `\"`) prematurely closing the attribute, which
    // makes the browser swallow the class attribute and dump trailing JS as
    // a stray text node instead of parsing it as part of the tag.
    $manager = actingAsJenisKaryawanManager();

    $html = $this->actingAs($manager)->get(route('admin.jenis-karyawan-master.index'))->getContent();

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' mx-auto ') and contains(concat(' ', normalize-space(@class), ' '), ' max-w-6xl ')]");

    expect($nodes->length)->toBeGreaterThan(0);
});

it('creates a jenis karyawan via JSON', function () {
    $manager = actingAsJenisKaryawanManager();

    $this->actingAs($manager)->postJson(route('admin.jenis-karyawan-master.store'), ['nama' => 'Konselor BK'])
        ->assertCreated()
        ->assertJsonPath('item.nama', 'Konselor BK');

    expect(JenisKaryawanMaster::where('nama', 'Konselor BK')->exists())->toBeTrue();
});

it('rejects a duplicate nama', function () {
    $manager = actingAsJenisKaryawanManager();
    JenisKaryawanMaster::factory()->create(['nama' => 'Psikolog']);

    $this->actingAs($manager)->postJson(route('admin.jenis-karyawan-master.store'), ['nama' => 'Psikolog'])
        ->assertStatus(422);
});

it('updates a jenis karyawan', function () {
    $manager = actingAsJenisKaryawanManager();
    $jenis = JenisKaryawanMaster::factory()->create(['nama' => 'Lama']);

    $this->actingAs($manager)->putJson(route('admin.jenis-karyawan-master.update', $jenis), ['nama' => 'Baru'])
        ->assertOk();

    expect($jenis->fresh()->nama)->toBe('Baru');
});

it('blocks deleting a jenis karyawan that is still in use by a karyawan', function () {
    $manager = actingAsJenisKaryawanManager();
    $jenis = JenisKaryawanMaster::factory()->create();
    Karyawan::factory()->create(['jenis_karyawan_id' => $jenis->id]);

    $this->actingAs($manager)->deleteJson(route('admin.jenis-karyawan-master.destroy', $jenis))
        ->assertStatus(422);

    expect(JenisKaryawanMaster::find($jenis->id))->not->toBeNull();
});

it('deletes a jenis karyawan that is not in use', function () {
    $manager = actingAsJenisKaryawanManager();
    $jenis = JenisKaryawanMaster::factory()->create();

    $this->actingAs($manager)->deleteJson(route('admin.jenis-karyawan-master.destroy', $jenis))
        ->assertOk();

    expect(JenisKaryawanMaster::find($jenis->id))->toBeNull();
});
