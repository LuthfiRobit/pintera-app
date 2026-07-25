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
