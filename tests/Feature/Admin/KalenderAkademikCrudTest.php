<?php

use App\Enums\TipeKalenderAkademik;
use App\Domains\Akademik\Models\KalenderAkademik;
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

it('creates a lembaga-scoped calendar entry without needing kelola-nasional', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);

    $response = $this->actingAs($manager)->postJson(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-09-01',
        'nama' => 'Libur Yayasan',
        'tipe' => TipeKalenderAkademik::Libur->value,
        'berlaku_nasional' => '0',
    ]);

    $response->assertCreated();

    $entry = KalenderAkademik::where('nama', 'Libur Yayasan')->firstOrFail();
    expect($entry->lembaga_id)->toBe($lembaga->id);
});

it('rejects a national entry from a manager without kelola-nasional permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);

    $this->actingAs($manager)->postJson(route('admin.kalender-akademik.store'), [
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

    $this->actingAs($manager)->postJson(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-08-17',
        'nama' => 'Hari Kemerdekaan RI',
        'tipe' => TipeKalenderAkademik::Libur->value,
        'berlaku_nasional' => '1',
    ])->assertCreated();

    $entry = KalenderAkademik::where('nama', 'Hari Kemerdekaan RI')->firstOrFail();
    expect($entry->lembaga_id)->toBeNull();
});

it('rejects a store from a yayasan-scoped manager with no active lembaga selected, without silently creating a national-looking row', function () {
    foreach (['kalender-akademik.view', 'kalender-akademik.kelola'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $yayasanRole = Role::firstOrCreate(['name' => 'admin_kalender_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $yayasanRole->givePermissionTo(['kalender-akademik.view', 'kalender-akademik.kelola']);

    $manager = User::factory()->create(['lembaga_id' => null]);
    $manager->assignRole($yayasanRole);

    $this->actingAs($manager)->postJson(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-09-01',
        'nama' => 'Coba Tanpa Lembaga Aktif',
        'tipe' => TipeKalenderAkademik::Libur->value,
        'berlaku_nasional' => '0',
    ])->assertStatus(422)->assertJsonValidationErrors('lembaga_id');

    expect(KalenderAkademik::where('nama', 'Coba Tanpa Lembaga Aktif')->exists())->toBeFalse();
});

it('denies updating an existing national entry from a manager without kelola-nasional permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);
    $entri = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);

    $this->actingAs($manager)->putJson(route('admin.kalender-akademik.update', $entri), [
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

    $this->actingAs($manager)->putJson(route('admin.kalender-akademik.update', $entri), [
        'nama' => 'Hari Kemerdekaan Republik Indonesia',
        'tipe' => TipeKalenderAkademik::Libur->value,
    ])->assertOk();

    expect($entri->fresh()->nama)->toBe('Hari Kemerdekaan Republik Indonesia');
});

it('rejects updating another lembaga\'s entry with a 404 and leaves the row unchanged', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembagaA, bolehNasional: false);
    $entriLembagaB = KalenderAkademik::create(['lembaga_id' => $lembagaB->id, 'tanggal' => '2026-09-01', 'nama' => 'Entri Lembaga B', 'tipe' => 'libur']);

    $this->actingAs($manager)->putJson(route('admin.kalender-akademik.update', $entriLembagaB), [
        'nama' => 'Diubah Paksa Lintas Tenant',
        'tipe' => TipeKalenderAkademik::Kerja->value,
    ])->assertNotFound();

    expect($entriLembagaB->fresh()->nama)->toBe('Entri Lembaga B');
    expect($entriLembagaB->fresh()->tipe)->toBe(TipeKalenderAkademik::Libur);
});

it('allows a lembaga-scoped manager to update their own lembaga\'s entry', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);
    $entri = KalenderAkademik::create(['lembaga_id' => $lembaga->id, 'tanggal' => '2026-09-01', 'nama' => 'Entri Lembaga Sendiri', 'tipe' => 'libur']);

    $this->actingAs($manager)->putJson(route('admin.kalender-akademik.update', $entri), [
        'nama' => 'Entri Lembaga Sendiri Diubah',
        'tipe' => TipeKalenderAkademik::Kerja->value,
    ])->assertOk();

    expect($entri->fresh()->nama)->toBe('Entri Lembaga Sendiri Diubah');
});

it('creates a range entry and stores tanggal_selesai', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);

    $response = $this->actingAs($manager)->postJson(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => '2026-09-01',
        'nama' => 'Libur Maulid',
        'tipe' => 'libur',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('kalender_akademik', ['nama' => 'Libur Maulid', 'tanggal_selesai' => '2026-09-01']);
});

it('defaults tanggal_selesai to tanggal when omitted (single-day entry)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);

    $response = $this->actingAs($manager)->postJson(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-09-10',
        'nama' => 'Entri Satu Hari',
        'tipe' => 'libur',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('kalender_akademik', ['nama' => 'Entri Satu Hari', 'tanggal' => '2026-09-10', 'tanggal_selesai' => '2026-09-10']);
});

it('rejects a tanggal_selesai before tanggal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);

    $this->actingAs($manager)->postJson(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-09-01',
        'tanggal_selesai' => '2026-08-23',
        'nama' => 'Rentang Terbalik',
        'tipe' => 'libur',
    ])->assertStatus(422)->assertJsonValidationErrors('tanggal_selesai');

    expect(KalenderAkademik::where('nama', 'Rentang Terbalik')->exists())->toBeFalse();
});

it('rejects a new range that overlaps an existing entry in the same scope', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);
    KalenderAkademik::create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => '2026-09-01',
        'nama' => 'Entri Pertama',
        'tipe' => 'libur',
    ]);

    $this->actingAs($manager)->postJson(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-08-30',
        'tanggal_selesai' => '2026-09-05',
        'nama' => 'Entri Kedua',
        'tipe' => 'libur',
    ])->assertStatus(422)->assertJsonValidationErrors('tanggal');

    expect(KalenderAkademik::where('nama', 'Entri Kedua')->exists())->toBeFalse();
});

it('allows an overlapping range in a DIFFERENT scope (own-lembaga vs national)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);
    KalenderAkademik::create([
        'lembaga_id' => null,
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => '2026-09-01',
        'nama' => 'Entri Nasional',
        'tipe' => 'libur',
    ]);

    $this->actingAs($manager)->postJson(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => '2026-09-01',
        'nama' => 'Entri Lembaga Sama Tanggal',
        'tipe' => 'libur',
        'berlaku_nasional' => '0',
    ])->assertCreated();

    expect(KalenderAkademik::where('nama', 'Entri Lembaga Sama Tanggal')->exists())->toBeTrue();
});

it('detects overlap between two single-day entries on the exact same date', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);
    KalenderAkademik::create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => null,
        'nama' => 'Entri Satu Hari Sudah Ada',
        'tipe' => 'libur',
    ]);

    $this->actingAs($manager)->postJson(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-08-23',
        'nama' => 'Entri Satu Hari Baru',
        'tipe' => 'libur',
    ])->assertStatus(422)->assertJsonValidationErrors('tanggal');

    expect(KalenderAkademik::where('nama', 'Entri Satu Hari Baru')->exists())->toBeFalse();
});

it('does not falsely detect overlap between two clearly non-overlapping single-day entries', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);
    KalenderAkademik::create([
        'lembaga_id' => $lembaga->id,
        'tanggal' => '2026-08-23',
        'tanggal_selesai' => null,
        'nama' => 'Entri Satu Hari Sudah Ada',
        'tipe' => 'libur',
    ]);

    // A day strictly after the existing single-day entry. With a naive
    // "tanggal_selesai IS NULL => treat as always overlapping" clause this
    // would be falsely flagged, since the null tanggal_selesai must fall
    // back to the entry's own `tanggal` as its effective end date, not be
    // treated as an open-ended/unbounded range.
    $this->actingAs($manager)->postJson(route('admin.kalender-akademik.store'), [
        'tanggal' => '2026-08-24',
        'nama' => 'Entri Satu Hari Berbeda',
        'tipe' => 'libur',
    ])->assertCreated();

    expect(KalenderAkademik::where('nama', 'Entri Satu Hari Berbeda')->exists())->toBeTrue();
});

it('deletes an entry the acting lembaga-scoped user owns', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);
    $entri = KalenderAkademik::create(['lembaga_id' => $lembaga->id, 'tanggal' => '2026-09-01', 'nama' => 'Entri Dihapus', 'tipe' => 'libur']);

    $this->actingAs($manager)->deleteJson(route('admin.kalender-akademik.destroy', $entri))->assertOk();

    expect(KalenderAkademik::find($entri->id))->toBeNull();
});

it('rejects deleting another lembaga\'s non-national entry with 404', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembagaA, bolehNasional: false);
    $entriLembagaB = KalenderAkademik::create(['lembaga_id' => $lembagaB->id, 'tanggal' => '2026-09-01', 'nama' => 'Entri Lembaga B', 'tipe' => 'libur']);

    $this->actingAs($manager)->deleteJson(route('admin.kalender-akademik.destroy', $entriLembagaB))->assertNotFound();

    expect(KalenderAkademik::find($entriLembagaB->id))->not->toBeNull();
});

it('rejects deleting a national entry without kalender-akademik.kelola-nasional', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKalenderManager($lembaga, bolehNasional: false);
    $entriNasional = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);

    $this->actingAs($manager)->deleteJson(route('admin.kalender-akademik.destroy', $entriNasional))->assertForbidden();

    expect(KalenderAkademik::find($entriNasional->id))->not->toBeNull();
});
