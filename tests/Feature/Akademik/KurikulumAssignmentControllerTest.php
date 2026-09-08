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

function actingAsYayasanKurikulumManager(): User
{
    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $yayasan = Yayasan::factory()->create();
    $role = Role::firstOrCreate(['name' => 'yayasan_admin_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null]);
    $manager->assignRole($role);

    return $manager;
}

function actingAsPlatformScopeKurikulumManager(): User
{
    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'platform_admin_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'platform']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete']);

    $manager = User::factory()->create(['yayasan_id' => null, 'lembaga_id' => null]);
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

it('memakai session active_lembaga_id untuk lembaga_id assignment yayasan, mengabaikan lembaga_id yang dikirim di body request', function () {
    $manager = actingAsYayasanKurikulumManager();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $taLembagaAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'lembaga_id' => $lembagaLain->id, // dicoba dipaksakan, HARUS diabaikan
        'tahun_ajaran_id' => $taLembagaAktif->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    $assignment = KurikulumAssignment::where('tahun_ajaran_id', $taLembagaAktif->id)->first();
    expect($assignment->lembaga_id)->toBe($lembagaAktif->id);
});

it('menolak yayasan membuat assignment global (lembaga_id null) -- hanya platform yang boleh', function () {
    $manager = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'lembaga_id' => '',
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ]);

    // lembaga_id yayasan SELALU dari session, tidak pernah null walau field 'lembaga_id' dikirim kosong.
    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()?->lembaga_id)->toBe($lembaga->id);
});

it('yayasan tanpa active_lembaga_id di sesi ditolak dengan pesan jelas saat membuat assignment', function () {
    $manager = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    session()->forget('active_lembaga_id');

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect()->assertSessionHasErrors('lembaga_id');

    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->exists())->toBeFalse();
});

it('platform BISA membuat assignment global (lembaga_id null)', function () {
    $manager = actingAsPlatformScopeKurikulumManager();
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()?->lembaga_id)->toBeNull();
});

it('platform BISA membuat assignment untuk lembaga manapun lintas yayasan', function () {
    $manager = actingAsPlatformScopeKurikulumManager();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaYayasanLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembagaYayasanLain->id]);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'lembaga_id' => $lembagaYayasanLain->id,
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()?->lembaga_id)->toBe($lembagaYayasanLain->id);
});

it('menolak yayasan A mengedit/update/hapus assignment milik lembaga di yayasan B (akses baris existing, BUKAN soal nilai ditulis)', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id, 'bentuk_pendidikan' => 'SD']);
    $taB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $assignmentB = KurikulumAssignment::create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $taB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.edit', $assignmentB))->assertForbidden();
    $this->actingAs($managerA)->put(route('admin.kurikulum-assignment.update', $assignmentB), [
        'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'merdeka',
    ])->assertForbidden();
    $this->actingAs($managerA)->delete(route('admin.kurikulum-assignment.destroy', $assignmentB))->assertForbidden();

    expect($assignmentB->fresh()->kurikulum->value)->toBe('k13');
});

it('menolak yayasan mengedit/hapus assignment GLOBAL (lembaga_id null) -- hanya platform yang boleh', function () {
    $manager = actingAsYayasanKurikulumManager();
    $ta = TahunAjaran::factory()->create();
    $assignmentGlobal = KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->delete(route('admin.kurikulum-assignment.destroy', $assignmentGlobal))->assertForbidden();

    expect(KurikulumAssignment::find($assignmentGlobal->id))->not->toBeNull();
});

it('platform TETAP lihat SEMUA assignment lintas yayasan di index (regresi negatif)', function () {
    $manager = actingAsPlatformScopeKurikulumManager();
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $taA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $taB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $assignmentA = KurikulumAssignment::create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $taA->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    $assignmentB = KurikulumAssignment::create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $taB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'));

    $response->assertViewHas('assignmentList', function ($list) use ($assignmentA, $assignmentB) {
        return $list->contains('id', $assignmentA->id) && $list->contains('id', $assignmentB->id);
    });
});

it('yayasan cuma lihat assignment global + milik yayasannya sendiri di index, TIDAK lihat milik yayasan lain', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $yayasanB = Yayasan::factory()->create();
    $lembagaMilikSendiri = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $taSendiri = TahunAjaran::factory()->create(['lembaga_id' => $lembagaMilikSendiri->id]);
    $taB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $assignmentSendiri = KurikulumAssignment::create(['lembaga_id' => $lembagaMilikSendiri->id, 'tahun_ajaran_id' => $taSendiri->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    $assignmentB = KurikulumAssignment::create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $taB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    $assignmentGlobal = KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $taSendiri->id, 'bentuk_pendidikan' => 'SMP', 'tingkat' => '7', 'kurikulum' => 'k13']);

    $response = $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.index'));

    $response->assertViewHas('assignmentList', function ($list) use ($assignmentSendiri, $assignmentB, $assignmentGlobal) {
        return $list->contains('id', $assignmentSendiri->id)
            && $list->contains('id', $assignmentGlobal->id)
            && ! $list->contains('id', $assignmentB->id);
    });
});

it('renders the edit page without crashing for a platform-scoped actor viewing a global assignment', function () {
    $manager = actingAsPlatformScopeKurikulumManager();
    $ta = TahunAjaran::factory()->create();
    $assignmentGlobal = KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.edit', $assignmentGlobal))->assertOk();
});

it('renders the edit page without crashing for a platform-scoped actor viewing a lembaga-specific assignment', function () {
    $manager = actingAsPlatformScopeKurikulumManager();
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.edit', $assignment))->assertOk();
});

it('computes canManage correctly per assignment for a yayasan-scoped actor (false for global, true for own-yayasan lembaga)', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $lembagaMilikSendiri = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $taSendiri = TahunAjaran::factory()->create(['lembaga_id' => $lembagaMilikSendiri->id]);
    $taGlobal = TahunAjaran::factory()->create();
    $assignmentSendiri = KurikulumAssignment::create(['lembaga_id' => $lembagaMilikSendiri->id, 'tahun_ajaran_id' => $taSendiri->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    $assignmentGlobal = KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $taGlobal->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $response = $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.index'))->assertOk();

    $response->assertViewHas('assignmentList', function ($list) use ($assignmentSendiri, $assignmentGlobal) {
        $sendiri = $list->firstWhere('id', $assignmentSendiri->id);
        $global = $list->firstWhere('id', $assignmentGlobal->id);

        return $sendiri->canManage === true && $global->canManage === false;
    });
});

it('does not render an Edit link for a global assignment row to a yayasan-scoped actor', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $ta = TahunAjaran::factory()->create();
    $assignmentGlobal = KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $response = $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.index'))->assertOk();

    $response->assertDontSee(route('admin.kurikulum-assignment.edit', $assignmentGlobal), false);
});

it('redirects back to index when a yayasan-scoped actor opens create without an active lembaga', function () {
    $manager = actingAsYayasanKurikulumManager();

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.create'))
        ->assertRedirect(route('admin.kurikulum-assignment.index'))
        ->assertSessionHasErrors('lembaga_id');
});

it('shows the active lembaga name on the create page for a yayasan-scoped actor', function () {
    $manager = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'nama' => 'SMA Bintang Persada']);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.create'))->assertSee('SMA Bintang Persada');
});

it('shows a static note explaining the index never narrows by active lembaga for a yayasan-scoped actor', function () {
    $manager = actingAsYayasanKurikulumManager();

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'))
        ->assertSee('tidak menyempit walau Anda mengganti lembaga aktif', false);
});

it('does not show the aggregate note for a lembaga-scoped actor', function () {
    $lembaga = Lembaga::factory()->create();
    $manager = actingAsKurikulumAssignmentManager($lembaga);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'))
        ->assertDontSee('tidak menyempit walau Anda mengganti lembaga aktif', false);
});

it('shows the correct tahun ajaran name in the index for an assignment belonging to a different lembaga than the one currently active', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'TK']);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $taLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id, 'nama' => '2030/2031']);
    KurikulumAssignment::create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $taLain->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.index'))->assertSee('2030/2031');
});

it('shows the correct tahun ajaran name on the edit page for an assignment belonging to a different lembaga than the one currently active', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'TK']);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $taLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id, 'nama' => '2031/2032']);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $taLain->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.edit', $assignment))->assertSee('2031/2032');
});

it('shows tahun ajaran options in the create dropdown for a platform-scoped actor', function () {
    $manager = actingAsPlatformScopeKurikulumManager();
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2032/2033']);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.create'))->assertSee('2032/2033');
});

it('shows a locked (read-only) bentuk pendidikan on the create page for a non-platform actor, not a free dropdown', function () {
    $manager = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.create'))->assertOk();
    $response->assertSee('SD');
    $response->assertDontSee('<select name="bentuk_pendidikan"', false);
});

it('shows a locked (read-only) bentuk pendidikan on the edit page for a non-platform actor, not a free dropdown', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'TK']);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'TK', 'tingkat' => null, 'kurikulum' => 'k13']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.edit', $assignment))->assertOk();
    $response->assertDontSee('<select name="bentuk_pendidikan"', false);
});

it('ignores a tampered bentuk_pendidikan value on store() for a non-platform actor, forcing the lembaga\'s own value', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($managerA)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'TK',
        'tingkat' => null,
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    $assignment = KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first();
    expect($assignment->bentuk_pendidikan)->toBe('SD');
});

it('ignores a tampered bentuk_pendidikan value on update() for a non-platform actor, forcing the lembaga\'s own value', function () {
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $assignment = KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13']);

    $this->actingAs($manager)->put(route('admin.kurikulum-assignment.update', $assignment), [
        'bentuk_pendidikan' => 'TK',
        'tingkat' => null,
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect($assignment->fresh()->bentuk_pendidikan)->toBe('SD');
});

it('renders the restyled Aksi dropdown with explicit action labels for a manageable row', function () {
    $lembaga = Lembaga::factory()->create();
    $manager = actingAsKurikulumAssignmentManager($lembaga);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    KurikulumAssignment::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'))->assertOk()
        ->assertSee('Edit Assignment')
        ->assertSee('Hapus Assignment');
});





