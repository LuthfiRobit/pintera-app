<?php
// tests/Feature/Admin/KasusSoftDeleteRestoreTest.php

use App\Enums\StatusKasus;
use App\Models\Kasus;
use App\Models\KasusConsent;
use App\Models\KasusEvaluasi;
use App\Models\KasusSesi;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function actingAsKasusHapusManager(Lembaga $lembaga): User
{
    foreach (['kasus.view', 'kasus.hapus', 'kasus.pulihkan'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view', 'kasus.hapus', 'kasus.pulihkan']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

function buatKasusSelesaiDenganFamily(Lembaga $lembaga): Kasus
{
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create([
        'siswa_id' => $siswa->id,
        'lembaga_id' => $lembaga->id,
        'status' => StatusKasus::Selesai,
    ]);
    $sesi = KasusSesi::factory()->create(['kasus_id' => $kasus->id]);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id]);
    KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id]);
    KasusEvaluasi::factory()->create(['kasus_id' => $kasus->id]);
    KasusConsent::factory()->create(['kasus_id' => $kasus->id]);

    return $kasus;
}

it('soft-deletes a Selesai kasus and its whole family in one action', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKasusHapusManager($lembaga);
    $kasus = buatKasusSelesaiDenganFamily($lembaga);
    $sesi = $kasus->sesi()->first();
    $tugas = $kasus->tugas()->first();
    $submission = $tugas->submissions()->first();
    $evaluasi = $kasus->evaluasi()->first();
    $consent = $kasus->consents()->first();

    $this->actingAs($manager)
        ->delete(route('admin.kasus.destroy', $kasus))
        ->assertRedirect();

    expect(Kasus::find($kasus->id))->toBeNull();
    expect(Kasus::withTrashed()->find($kasus->id)->deleted_at)->not->toBeNull();
    expect(KasusSesi::find($sesi->id))->toBeNull();
    expect(KasusTugas::find($tugas->id))->toBeNull();
    expect(KasusTugasSubmission::find($submission->id))->toBeNull();
    expect(KasusEvaluasi::find($evaluasi->id))->toBeNull();
    expect(KasusConsent::find($consent->id))->toBeNull();
});

it('refuses to soft-delete a kasus that is not Selesai', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKasusHapusManager($lembaga);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create([
        'siswa_id' => $siswa->id,
        'lembaga_id' => $lembaga->id,
        'status' => StatusKasus::Berjalan,
    ]);

    $this->actingAs($manager)
        ->delete(route('admin.kasus.destroy', $kasus))
        ->assertStatus(422);

    expect(Kasus::find($kasus->id))->not->toBeNull();
});

it('403s an admin_akademik trying to delete a Selesai kasus from a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSendiri = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKasusHapusManager($lembagaSendiri);
    $kasus = buatKasusSelesaiDenganFamily($lembagaLain);

    $this->actingAs($manager)
        ->delete(route('admin.kasus.destroy', $kasus))
        ->assertNotFound();

    // Assertion runs while still acting as $manager, whose own lembaga differs from the
    // kasus's lembaga, so TenantScope (applied by default on Kasus) would filter this out
    // even if it were never deleted. Bypass it here to check the actual deletion state.
    expect(Kasus::withoutGlobalScope(TenantScope::class)->find($kasus->id))->not->toBeNull();
});

it('restores a soft-deleted kasus and its whole family in one action', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKasusHapusManager($lembaga);
    $kasus = buatKasusSelesaiDenganFamily($lembaga);
    $sesi = $kasus->sesi()->first();
    $tugas = $kasus->tugas()->first();
    $submission = $tugas->submissions()->first();
    $evaluasi = $kasus->evaluasi()->first();
    $consent = $kasus->consents()->first();

    $this->actingAs($manager)->delete(route('admin.kasus.destroy', $kasus))->assertRedirect();

    $this->actingAs($manager)
        ->post(route('admin.kasus.restore', $kasus))
        ->assertRedirect();

    expect(Kasus::find($kasus->id))->not->toBeNull();
    expect(KasusSesi::find($sesi->id))->not->toBeNull();
    expect(KasusTugas::find($tugas->id))->not->toBeNull();
    expect(KasusTugasSubmission::find($submission->id))->not->toBeNull();
    expect(KasusEvaluasi::find($evaluasi->id))->not->toBeNull();
    expect(KasusConsent::find($consent->id))->not->toBeNull();
});

it('403s an admin_akademik trying to restore a kasus from a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSendiri = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $ownerManager = actingAsKasusHapusManager($lembagaLain);
    $kasus = buatKasusSelesaiDenganFamily($lembagaLain);
    $this->actingAs($ownerManager)->delete(route('admin.kasus.destroy', $kasus))->assertRedirect();

    $strangerManager = actingAsKasusHapusManager($lembagaSendiri);
    $this->actingAs($strangerManager)
        ->post(route('admin.kasus.restore', $kasus))
        ->assertNotFound();

    // Same TenantScope caveat as above: $strangerManager's lembaga differs from the
    // kasus's lembaga, so the scoped query would return null regardless of restore state.
    expect(Kasus::withoutGlobalScope(TenantScope::class)->withTrashed()->find($kasus->id)->deleted_at)->not->toBeNull();
});

it('404s a trashed kasus when a regular authorized viewer tries to open its detail page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKasusHapusManager($lembaga);
    $kasus = buatKasusSelesaiDenganFamily($lembaga);
    $this->actingAs($manager)->delete(route('admin.kasus.destroy', $kasus))->assertRedirect();

    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    Role::where('name', 'admin_akademik')->first()->givePermissionTo('kasus.view');

    $this->actingAs($manager)
        ->get(route('kasus.show', $kasus))
        ->assertNotFound();
});
