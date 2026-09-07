<?php

// tests/Feature/Admin/JenisKaryawanMasterCrudTest.php

use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsJenisKaryawanManager(): User
{
    $manager = User::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
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

    $dom = new DOMDocument;
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
    JenisKaryawanMaster::factory()->create(['nama' => 'Psikolog', 'yayasan_id' => $manager->yayasan_id]);

    $this->actingAs($manager)->postJson(route('admin.jenis-karyawan-master.store'), ['nama' => 'Psikolog'])
        ->assertStatus(422);
});

it('updates a jenis karyawan', function () {
    $manager = actingAsJenisKaryawanManager();
    $jenis = JenisKaryawanMaster::factory()->create(['nama' => 'Lama', 'yayasan_id' => $manager->yayasan_id]);

    $this->actingAs($manager)->putJson(route('admin.jenis-karyawan-master.update', $jenis), ['nama' => 'Baru'])
        ->assertOk();

    expect($jenis->fresh()->nama)->toBe('Baru');
});

it('blocks deleting a jenis karyawan that is still in use by a karyawan', function () {
    $manager = actingAsJenisKaryawanManager();
    $jenis = JenisKaryawanMaster::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    Karyawan::factory()->create(['jenis_karyawan_id' => $jenis->id, 'lembaga_id' => $lembaga->id, 'yayasan_id' => $manager->yayasan_id]);

    $this->actingAs($manager)->deleteJson(route('admin.jenis-karyawan-master.destroy', $jenis))
        ->assertStatus(422);

    expect(JenisKaryawanMaster::find($jenis->id))->not->toBeNull();
});

it('deletes a jenis karyawan that is not in use', function () {
    $manager = actingAsJenisKaryawanManager();
    $jenis = JenisKaryawanMaster::factory()->create(['yayasan_id' => $manager->yayasan_id]);

    $this->actingAs($manager)->deleteJson(route('admin.jenis-karyawan-master.destroy', $jenis))
        ->assertOk();

    expect(JenisKaryawanMaster::find($jenis->id))->toBeNull();
});

it('does not leak jenis karyawan across yayasan boundaries on the index page', function () {
    $managerA = actingAsJenisKaryawanManager();
    $jenisA = JenisKaryawanMaster::factory()->create(['nama' => 'Milik Yayasan A', 'yayasan_id' => $managerA->yayasan_id]);
    JenisKaryawanMaster::factory()->create(['nama' => 'Milik Yayasan B']);

    $response = $this->actingAs($managerA)->getJson(route('admin.jenis-karyawan-master.index'));

    $response->assertOk();
    $ids = collect($response->json('items'))->pluck('id');
    expect($ids)->toContain($jenisA->id);
    expect($ids)->toHaveCount(1);
});

it('assigns yayasan_id from the acting manager automatically, ignoring any yayasan_id in the request payload', function () {
    $manager = actingAsJenisKaryawanManager();
    $lainYayasan = Yayasan::factory()->create();

    $this->actingAs($manager)->postJson(route('admin.jenis-karyawan-master.store'), [
        'nama' => 'Satpam Baru',
        'yayasan_id' => $lainYayasan->id,
    ])->assertCreated();

    $item = JenisKaryawanMaster::where('nama', 'Satpam Baru')->first();
    expect($item->yayasan_id)->toBe($manager->yayasan_id);
});

it('allows two different yayasan to use the exact same jenis karyawan nama', function () {
    $managerA = actingAsJenisKaryawanManager();
    JenisKaryawanMaster::factory()->create(['nama' => 'Satpam']);

    $this->actingAs($managerA)->postJson(route('admin.jenis-karyawan-master.store'), ['nama' => 'Satpam'])
        ->assertCreated();
});

it('does not block deleting a jenis karyawan that is only in use by a karyawan in a different yayasan', function () {
    $managerA = actingAsJenisKaryawanManager();
    $jenisA = JenisKaryawanMaster::factory()->create(['nama' => 'Satpam', 'yayasan_id' => $managerA->yayasan_id]);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $jenisB = JenisKaryawanMaster::factory()->create(['nama' => 'Satpam', 'yayasan_id' => $yayasanB->id]);
    Karyawan::factory()->create(['jenis_karyawan_id' => $jenisB->id, 'lembaga_id' => $lembagaB->id, 'yayasan_id' => $yayasanB->id]);

    $this->actingAs($managerA)->deleteJson(route('admin.jenis-karyawan-master.destroy', $jenisA))
        ->assertOk();

    expect(JenisKaryawanMaster::withoutGlobalScopes()->find($jenisA->id))->toBeNull();
    expect(JenisKaryawanMaster::withoutGlobalScopes()->find($jenisB->id))->not->toBeNull();
});
