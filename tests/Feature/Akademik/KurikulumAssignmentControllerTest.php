<?php

use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsKurikulumAssignmentManager(Lembaga $lembaga): User
{
    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without kurikulum-assignment.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.kurikulum-assignment.index'))->assertForbidden();
});

it('creates a kurikulum assignment', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->where('tingkat', '1')->exists())->toBeTrue();
});

it('rejects an invalid tingkat for the given bentuk_pendidikan', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '13',
        'kurikulum' => 'merdeka',
    ])->assertSessionHasErrors('tingkat');

    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->exists())->toBeFalse();
});

it('rejects a duplicate assignment for the same scope via the controller duplicate-check', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertSessionHasErrors('bentuk_pendidikan');

    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->where('tingkat', '1')->count())->toBe(1);
});

it('updates a kurikulum assignment without changing its lembaga or tahun_ajaran scope', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->put(route('admin.kurikulum-assignment.update', $assignment), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect($assignment->fresh()->kurikulum->value)->toBe('merdeka');
    expect($assignment->fresh()->lembaga_id)->toBe($lembaga->id);
    expect($assignment->fresh()->tahun_ajaran_id)->toBe($ta->id);
});

it('deletes a kurikulum assignment', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->delete(route('admin.kurikulum-assignment.destroy', $assignment))->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect(KurikulumAssignment::find($assignment->id))->toBeNull();
});

it('forbids a lembaga-scoped user from managing another lembaga\'s assignment', function () {
    $lembagaSaya = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $lembagaLain = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $taLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKurikulumAssignmentManager($lembagaSaya);
    $assignmentLain = KurikulumAssignment::create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $taLain->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.edit', $assignmentLain))->assertForbidden();
});

function actingAsPlatformKurikulumManager(): User
{
    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $yayasan = Yayasan::factory()->create();
    $role = Role::firstOrCreate(['name' => 'yayasan_admin_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    return $manager;
}

it('rejects a store where lembaga_id is forced to the admin lembaga but tahun_ajaran belongs to another lembaga', function () {
    $lembagaSaya = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $lembagaLain = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $taLembagaLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = actingAsKurikulumAssignmentManager($lembagaSaya);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $taLembagaLain->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertSessionHasErrors('tahun_ajaran_id');

    expect(KurikulumAssignment::where('tahun_ajaran_id', $taLembagaLain->id)->exists())->toBeFalse();
});

it('allows platform/yayasan to store with an explicit lembaga_id when tahun_ajaran matches that lembaga', function () {
    $manager = actingAsPlatformKurikulumManager();
    $lembagaTarget = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $taLembagaTarget = TahunAjaran::factory()->create(['lembaga_id' => $lembagaTarget->id]);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'lembaga_id' => $lembagaTarget->id,
        'tahun_ajaran_id' => $taLembagaTarget->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect(KurikulumAssignment::where('tahun_ajaran_id', $taLembagaTarget->id)->where('lembaga_id', $lembagaTarget->id)->exists())->toBeTrue();
});

it('rejects platform/yayasan store when explicit lembaga_id does not match the chosen tahun_ajaran ownership', function () {
    $manager = actingAsPlatformKurikulumManager();
    $lembagaTarget = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $taLembagaLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'lembaga_id' => $lembagaTarget->id,
        'tahun_ajaran_id' => $taLembagaLain->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertSessionHasErrors('tahun_ajaran_id');

    expect(KurikulumAssignment::where('tahun_ajaran_id', $taLembagaLain->id)->where('lembaga_id', $lembagaTarget->id)->exists())->toBeFalse();
});

it('does not reject a platform/yayasan store for ownership when lembaga_id is left null (default nasional)', function () {
    $manager = actingAsPlatformKurikulumManager();
    $lembagaManaPun = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $taLembagaManaPun = TahunAjaran::factory()->create(['lembaga_id' => $lembagaManaPun->id]);

    $response = $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $taLembagaManaPun->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ]);

    $response->assertSessionDoesntHaveErrors(['tahun_ajaran_id']);
});
