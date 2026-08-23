<?php

use App\Enums\KelompokMataPelajaran;
use App\Enums\StatusMataPelajaran;
use App\Enums\TipeMataPelajaran;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsMataPelajaranManager(Lembaga $lembaga): User
{
    foreach (['mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['mata-pelajaran.view', 'mata-pelajaran.create', 'mata-pelajaran.edit']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without mata-pelajaran.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.mata-pelajaran.index'))->assertForbidden();
});

it('creates a mata pelajaran with full standardized educational fields', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);

    $this->actingAs($manager)->post(route('admin.mata-pelajaran.store'), [
        'kode' => 'MTK-01',
        'nama' => 'Matematika',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ])->assertRedirect(route('admin.mata-pelajaran.index'));

    $mapel = MataPelajaran::where('kode', 'MTK-01')->first();
    expect($mapel)->not->toBeNull();
    expect($mapel->nama)->toBe('Matematika');
    expect($mapel->kelompok)->toBe(KelompokMataPelajaran::Umum);
});

it('only lists mata pelajaran belonging to the acting manager\'s own lembaga in index view', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembagaA);

    MataPelajaran::create([
        'lembaga_id' => $lembagaA->id,
        'kode' => 'A-01',
        'nama' => 'Mapel Lembaga A',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);
    MataPelajaran::withoutGlobalScopes()->create([
        'lembaga_id' => $lembagaB->id,
        'kode' => 'B-01',
        'nama' => 'Mapel Lembaga B',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));

    $response->assertSee('Mapel Lembaga A');
    $response->assertSee('A-01');
    $response->assertDontSee('Mapel Lembaga B');
});

it('updates a mata pelajaran including status and no_urut', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);
    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'IPA-01',
        'nama' => 'IPA',
        'no_urut' => 2,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $this->actingAs($manager)->put(route('admin.mata-pelajaran.update', $mapel), [
        'kode' => 'IPA-01-REV',
        'nama' => 'Ilmu Pengetahuan Alam',
        'no_urut' => 5,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Pilihan->value,
        'status' => StatusMataPelajaran::Nonaktif->value,
    ])->assertRedirect(route('admin.mata-pelajaran.index'));

    $fresh = $mapel->fresh();
    expect($fresh->kode)->toBe('IPA-01-REV');
    expect($fresh->nama)->toBe('Ilmu Pengetahuan Alam');
    expect($fresh->no_urut)->toBe(5);
    expect($fresh->kelompok)->toBe(KelompokMataPelajaran::Pilihan);
    expect($fresh->status)->toBe(StatusMataPelajaran::Nonaktif);
});

it('calculates executive KPI statistics accurately in index view', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);

    MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'SD-01',
        'nama' => 'Matematika SD',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);
    MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'PAUD-01',
        'nama' => 'Motorik Halus',
        'no_urut' => 2,
        'tipe' => TipeMataPelajaran::AspekPerkembangan->value,
        'kelompok' => null,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index'));
    $response->assertOk();
    $response->assertViewHas('totalMapel', 2);
    $response->assertViewHas('countKurikulum', 1);
    $response->assertViewHas('countAspek', 1);
});

it('returns only table partial view when requested via AJAX XMLHttpRequest', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsMataPelajaranManager($lembaga);

    MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'AJAX-01',
        'nama' => 'Mapel AJAX',
        'no_urut' => 1,
        'tipe' => TipeMataPelajaran::Mapel->value,
        'kelompok' => KelompokMataPelajaran::Umum->value,
        'status' => StatusMataPelajaran::Aktif->value,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.mata-pelajaran.index', ['search' => 'AJAX']), [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk();
    $response->assertViewIs('admin.mata-pelajaran._daftar');
    $response->assertSee('Mapel AJAX');
});

