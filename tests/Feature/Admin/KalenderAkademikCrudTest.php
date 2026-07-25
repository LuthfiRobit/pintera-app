<?php

use App\Enums\TipeKalenderAkademik;
use App\Models\KalenderAkademik;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsKalenderManager(Lembaga $lembaga, bool $bolehNasional = false): User
{
    $permissions = ['kalender-akademik.view', 'kalender-akademik.kelola'];
    if ($bolehNasional) {
        $permissions[] = 'kalender-akademik.kelola-nasional';
    }
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_kalender_'.($bolehNasional ? 'pusat' : 'lembaga'), 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo($permissions);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without kalender-akademik.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.kalender-akademik.index'))->assertForbidden();
});

it('creates a lembaga-scoped calendar entry without needing kelola-nasional', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);

    $this->actingAs($manager)->post(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-09-01',
        'nama' => 'Libur Yayasan',
        'tipe' => TipeKalenderAkademik::Libur->value,
        'berlaku_nasional' => '0',
    ])->assertRedirect(route('admin.kalender-akademik.index'));

    $entry = KalenderAkademik::where('nama', 'Libur Yayasan')->firstOrFail();
    expect($entry->lembaga_id)->toBe($lembaga->id);
});

it('rejects a national entry from a manager without kelola-nasional permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);

    $this->actingAs($manager)->post(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-09-01',
        'nama' => 'Coba Nasional',
        'tipe' => TipeKalenderAkademik::Libur->value,
        'berlaku_nasional' => '1',
    ])->assertForbidden();

    expect(KalenderAkademik::where('nama', 'Coba Nasional')->exists())->toBeFalse();
});

it('allows a national entry from a manager with kelola-nasional permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: true);

    $this->actingAs($manager)->post(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-08-17',
        'nama' => 'Hari Kemerdekaan RI',
        'tipe' => TipeKalenderAkademik::Libur->value,
        'berlaku_nasional' => '1',
    ])->assertRedirect(route('admin.kalender-akademik.index'));

    $entry = KalenderAkademik::where('nama', 'Hari Kemerdekaan RI')->firstOrFail();
    expect($entry->lembaga_id)->toBeNull();
});

it('rejects a second entry on the same date for the same scope', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);
    KalenderAkademik::create(['lembaga_id' => $lembaga->id, 'tanggal' => '2026-09-01', 'nama' => 'Entri Pertama', 'tipe' => 'libur']);

    $this->actingAs($manager)->post(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-09-01',
        'nama' => 'Entri Kedua',
        'tipe' => TipeKalenderAkademik::Kerja->value,
        'berlaku_nasional' => '0',
    ])->assertSessionHasErrors('tanggal');
});

it('rejects a store from a yayasan-scoped manager with no active lembaga selected, without silently creating a national-looking row', function () {
    foreach (['kalender-akademik.view', 'kalender-akademik.kelola'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $yayasanRole = Role::firstOrCreate(['name' => 'admin_kalender_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $yayasanRole->givePermissionTo(['kalender-akademik.view', 'kalender-akademik.kelola']);

    $manager = User::factory()->create(['lembaga_id' => null]);
    $manager->assignRole($yayasanRole);

    $this->actingAs($manager)->post(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-09-01',
        'nama' => 'Coba Tanpa Lembaga Aktif',
        'tipe' => TipeKalenderAkademik::Libur->value,
        'berlaku_nasional' => '0',
    ])->assertSessionHasErrors('lembaga_id');

    expect(KalenderAkademik::where('nama', 'Coba Tanpa Lembaga Aktif')->exists())->toBeFalse();
});

it('denies updating an existing national entry from a manager without kelola-nasional permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);
    $entri = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);

    $this->actingAs($manager)->put(route('admin.kalender-akademik.update', $entri), [
        'nama' => 'Nama Diubah Tanpa Izin',
        'tipe' => TipeKalenderAkademik::Libur->value,
    ])->assertForbidden();

    expect($entri->fresh()->nama)->toBe('Hari Kemerdekaan RI');
});

it('allows updating an existing national entry from a manager with kelola-nasional permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: true);
    $entri = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);

    $this->actingAs($manager)->put(route('admin.kalender-akademik.update', $entri), [
        'nama' => 'Hari Kemerdekaan Republik Indonesia',
        'tipe' => TipeKalenderAkademik::Libur->value,
    ])->assertRedirect(route('admin.kalender-akademik.index'));

    expect($entri->fresh()->nama)->toBe('Hari Kemerdekaan Republik Indonesia');
});

it('rejects viewing the edit form for another lembaga\'s entry with a 404, not a 403', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembagaA, bolehNasional: false);
    $entriLembagaB = KalenderAkademik::create(['lembaga_id' => $lembagaB->id, 'tanggal' => '2026-09-01', 'nama' => 'Entri Lembaga B', 'tipe' => 'libur']);

    $this->actingAs($manager)->get(route('admin.kalender-akademik.edit', $entriLembagaB))->assertNotFound();
});

it('rejects updating another lembaga\'s entry with a 404 and leaves the row unchanged', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembagaA, bolehNasional: false);
    $entriLembagaB = KalenderAkademik::create(['lembaga_id' => $lembagaB->id, 'tanggal' => '2026-09-01', 'nama' => 'Entri Lembaga B', 'tipe' => 'libur']);

    $this->actingAs($manager)->put(route('admin.kalender-akademik.update', $entriLembagaB), [
        'nama' => 'Diubah Paksa Lintas Tenant',
        'tipe' => TipeKalenderAkademik::Kerja->value,
    ])->assertNotFound();

    expect($entriLembagaB->fresh()->nama)->toBe('Entri Lembaga B');
    expect($entriLembagaB->fresh()->tipe)->toBe(TipeKalenderAkademik::Libur);
});

it('allows a lembaga-scoped manager to view and update their own lembaga\'s entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);
    $entri = KalenderAkademik::create(['lembaga_id' => $lembaga->id, 'tanggal' => '2026-09-01', 'nama' => 'Entri Lembaga Sendiri', 'tipe' => 'libur']);

    $this->actingAs($manager)->get(route('admin.kalender-akademik.edit', $entri))->assertOk();

    $this->actingAs($manager)->put(route('admin.kalender-akademik.update', $entri), [
        'nama' => 'Entri Lembaga Sendiri Diubah',
        'tipe' => TipeKalenderAkademik::Kerja->value,
    ])->assertRedirect(route('admin.kalender-akademik.index'));

    expect($entri->fresh()->nama)->toBe('Entri Lembaga Sendiri Diubah');
});
