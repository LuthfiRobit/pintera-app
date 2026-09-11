<?php

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsPolaJamManager(Lembaga $lembaga): User
{
    $perms = ['pola-jam.view', 'pola-jam.create', 'pola-jam.edit', 'pola-jam.delete', 'jam-pelajaran.create', 'jam-pelajaran.edit', 'jam-pelajaran.delete', 'kelas.edit'];
    foreach ($perms as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo($perms);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access without pola-jam.view permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.pola-jam.index'))->assertForbidden();
});

it('creates a pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $this->actingAs($manager)->post(route('admin.pola-jam.store'), [
        'nama' => 'Kelas Tinggi 4-6',
    ])->assertRedirect(route('admin.pola-jam.index'));

    $polaJam = PolaJam::where('nama', 'Kelas Tinggi 4-6')->first();
    expect($polaJam)->not->toBeNull();
    expect($polaJam->lembaga_id)->toBe($lembaga->id);
});

it('lets the lembaga-scoped manager see the pola jam they just created in the index', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $this->actingAs($manager)->post(route('admin.pola-jam.store'), [
        'nama' => 'Kelas Rendah 1-3',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertSee('Kelas Rendah 1-3');
});

it('creates a pola jam with the active lembaga for a yayasan-scoped manager', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_create_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.create']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->post(route('admin.pola-jam.store'), ['nama' => 'Pola Yayasan'])
        ->assertRedirect(route('admin.pola-jam.index'));

    $polaJam = PolaJam::where('nama', 'Pola Yayasan')->first();
    expect($polaJam)->not->toBeNull();
    expect($polaJam->lembaga_id)->toBe($lembaga->id);
});

it('rejects creating a pola jam for a yayasan-scoped manager with no active lembaga', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_no_active_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.create']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)
        ->post(route('admin.pola-jam.store'), ['nama' => 'Pola Tanpa Lembaga'])
        ->assertSessionHasErrors('lembaga_id');

    expect(PolaJam::where('nama', 'Pola Tanpa Lembaga')->exists())->toBeFalse();
});

it('menolak actor yayasan dengan active_lembaga_id stale saat membuat pola jam', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_stale_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.create']);

    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembagaLain->id])
        ->post(route('admin.pola-jam.store'), ['nama' => 'Pola Uji Stale'])
        ->assertSessionHasErrors('lembaga_id');

    expect(PolaJam::where('nama', 'Pola Uji Stale')->exists())->toBeFalse();
});

it('adds a jam pelajaran slot to an existing pola jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => ['senin'],
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => '0',
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('label', 'Upacara')->exists())->toBeTrue();
});

it('rejects adding a jam pelajaran slot to another lembaga\'s pola jam', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsPolaJamManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $polaB = PolaJam::factory()->create(['lembaga_id' => $lembagaB->id]);

    $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $polaB->id,
        'hari' => ['senin'],
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => '0',
    ])->assertNotFound();

    expect(JamPelajaran::where('pola_jam_id', $polaB->id)->where('label', 'Upacara')->exists())->toBeFalse();
});

it('adds a jam pelajaran slot to multiple hari at once from one submit', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => ['senin', 'rabu'],
        'urutan' => 1,
        'label' => 'Jam ke-1',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => '1',
    ]);

    $response->assertRedirect(route('admin.pola-jam.index'));
    $response->assertSessionHas('status', 'Slot berhasil ditambahkan untuk Senin dan Rabu.');

    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('hari', 'senin')->where('label', 'Jam ke-1')->exists())->toBeTrue();
    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('hari', 'rabu')->where('label', 'Jam ke-1')->exists())->toBeTrue();
    expect(JamPelajaran::where('pola_jam_id', $pola->id)->count())->toBe(2);
});

it('skips a hari that already has a slot at the same urutan and reports it in the status message', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'selasa', 'urutan' => 1]);

    $response = $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => ['senin', 'selasa'],
        'urutan' => 1,
        'label' => 'Jam ke-1',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => '1',
    ]);

    $response->assertRedirect(route('admin.pola-jam.index'));
    $response->assertSessionHas('status', 'Slot berhasil ditambahkan untuk Senin. Selasa dilewati karena urutan ini sudah dipakai.');

    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('hari', 'senin')->where('label', 'Jam ke-1')->exists())->toBeTrue();
    expect(JamPelajaran::where('pola_jam_id', $pola->id)->where('hari', 'selasa')->count())->toBe(1);
});

it('rejects the whole batch with an error when every checked hari already has a slot at that urutan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 1]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'selasa', 'urutan' => 1]);

    $response = $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => ['senin', 'selasa'],
        'urutan' => 1,
        'label' => 'Jam ke-1 Duplikat',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => '1',
    ]);

    $response->assertSessionHasErrors('hari');
    expect(JamPelajaran::where('label', 'Jam ke-1 Duplikat')->exists())->toBeFalse();
    expect(JamPelajaran::where('pola_jam_id', $pola->id)->count())->toBe(2);
});

it('rejects submitting the slot form with no hari checked', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => [],
        'urutan' => 1,
        'label' => 'Jam ke-1',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:40',
        'is_pelajaran' => '1',
    ])->assertSessionHasErrors('hari');

    expect(JamPelajaran::where('pola_jam_id', $pola->id)->exists())->toBeFalse();
});

it('renames a pola jam via update', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga); // extend helper's permissions to include pola-jam.edit, or add a dedicated helper
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Lama']);

    $this->actingAs($manager)->put(route('admin.pola-jam.update', $pola), [
        'nama' => 'Baru',
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect($pola->fresh()->nama)->toBe('Baru');
});

it('rejects editing another lembaga\'s pola jam with 404', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);

    $this->actingAs($manager)->put(route('admin.pola-jam.update', $polaLain), [
        'nama' => 'Diubah Paksa',
    ])->assertNotFound();

    expect($polaLain->fresh()->nama)->not->toBe('Diubah Paksa');
});

it('deletes a pola jam that has no kelas assigned and no jam pelajaran with a jadwal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->delete(route('admin.pola-jam.destroy', $pola))
        ->assertRedirect(route('admin.pola-jam.index'));

    expect(PolaJam::find($pola->id))->toBeNull();
});

it('refuses to delete a pola jam that is assigned to a kelas', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);

    $this->actingAs($manager)->delete(route('admin.pola-jam.destroy', $pola))
        ->assertSessionHasErrors();

    expect(PolaJam::find($pola->id))->not->toBeNull();
});

it('refuses to delete a pola jam whose jam pelajaran has a jadwal pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    $this->actingAs($manager)->delete(route('admin.pola-jam.destroy', $pola))
        ->assertSessionHasErrors();

    expect(PolaJam::find($pola->id))->not->toBeNull();
});

it('refuses to delete a pola jam whose jam pelajaran has a jadwal pelajaran, even when no kelas is assigned to it', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    // Deliberately no pola_jam_id here, so guard 1 (kelas()->exists()) is false
    // and only guard 2 (jamPelajaran()->whereHas('jadwalPelajaran')) can reject the destroy.
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    expect($kelas->fresh()->pola_jam_id)->not->toBe($pola->id);
    expect($pola->kelas()->exists())->toBeFalse();

    $this->actingAs($manager)->delete(route('admin.pola-jam.destroy', $pola))
        ->assertSessionHasErrors();

    expect(PolaJam::find($pola->id))->not->toBeNull();
});

it('assigns a pola jam to multiple kelas at once from the pola jam screen', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    foreach (['pola-jam.view', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_pola_jam_assign', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['pola-jam.view', 'kelas.edit']);
    $manager->assignRole($role);

    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasSatu = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $kelasDua = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $this->actingAs($manager)->put(route('admin.pola-jam.assign-kelas', $pola), [
        'kelas_ids' => [$kelasSatu->id, $kelasDua->id],
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect($kelasSatu->fresh()->pola_jam_id)->toBe($pola->id);
    expect($kelasDua->fresh()->pola_jam_id)->toBe($pola->id);
});

it('unlinks a kelas that was previously assigned but is unchecked on the next submit', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    foreach (['pola-jam.view', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_pola_jam_assign_unlink', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['pola-jam.view', 'kelas.edit']);
    $manager->assignRole($role);

    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasTetap = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $kelasDilepas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);

    $this->actingAs($manager)->put(route('admin.pola-jam.assign-kelas', $pola), [
        'kelas_ids' => [$kelasTetap->id],
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect($kelasTetap->fresh()->pola_jam_id)->toBe($pola->id);
    expect($kelasDilepas->fresh()->pola_jam_id)->toBeNull();
});

it('rejects assigning a pola jam to another lembaga\'s kelas with a validation error', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    foreach (['pola-jam.view', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_pola_jam_assign_2', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['pola-jam.view', 'kelas.edit']);
    $manager->assignRole($role);

    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);

    $this->actingAs($manager)->put(route('admin.pola-jam.assign-kelas', $pola), [
        'kelas_ids' => [$kelasLain->id],
    ])->assertSessionHasErrors();

    expect($kelasLain->fresh()->pola_jam_id)->toBeNull();
});

it('rejects assigning a pola jam to a different lembaga\'s kelas for a yayasan-scoped user with no active lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);

    foreach (['pola-jam.view', 'kelas.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_assign_mix_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.view', 'kelas.edit']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    // No active_lembaga_id in session — this yayasan-scoped user can see both lembaga A and B
    // (both belong to $yayasan), so the tenant scope alone would let this cross-lembaga
    // assignment through.

    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id]);

    $this->actingAs($manager)->put(route('admin.pola-jam.assign-kelas', $polaA), [
        'kelas_ids' => [$kelasB->id],
    ])->assertSessionHasErrors();

    expect($kelasB->fresh()->pola_jam_id)->toBeNull();
});

it('displays pola jam index with eager loaded kelas and tahun ajaran without n+1 or 500 errors', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'status_aktif' => true]);
    $manager = actingAsPolaJamManager($lembaga);
    $manager->roles->first()->givePermissionTo(Permission::firstOrCreate(['name' => 'kelas.edit', 'guard_name' => 'web']));

    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Reguler']);
    $kelas = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'pola_jam_id' => $pola->id,
        'nama' => 'VII-A',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('Pola Reguler');
    $response->assertSee('VII-A');
    $response->assertSee('2026/2027');
});

it('duplicates a pola jam along with all its jam pelajaran slots without copying kelas bindings', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Reguler']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'pola_jam_id' => $pola->id]);

    JamPelajaran::create([
        'pola_jam_id' => $pola->id,
        'hari' => 'senin',
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => false,
    ]);
    JamPelajaran::create([
        'pola_jam_id' => $pola->id,
        'hari' => 'senin',
        'urutan' => 2,
        'label' => 'KBM Ke-1',
        'jam_mulai' => '07:35',
        'jam_selesai' => '08:10',
        'is_pelajaran' => true,
    ]);

    $response = $this->actingAs($manager)->post(route('admin.pola-jam.duplicate', $pola));

    $response->assertRedirect(route('admin.pola-jam.index'));
    $response->assertSessionHas('status', 'Pola jam "Pola Reguler" beserta 2 slot jam berhasil diduplikasi.');

    $clonedPola = PolaJam::where('nama', 'Pola Reguler (Salinan)')->where('lembaga_id', $lembaga->id)->first();
    expect($clonedPola)->not->toBeNull();
    expect($clonedPola->jamPelajaran)->toHaveCount(2);
    expect($clonedPola->kelas)->toHaveCount(0);
});

it('rejects duplicating another lembaga\'s pola jam with 404', function () {
    $yayasanA = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $manager = actingAsPolaJamManager($lembagaA);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $polaB = PolaJam::factory()->create(['lembaga_id' => $lembagaB->id, 'nama' => 'Pola Lembaga B']);

    $this->actingAs($manager)->post(route('admin.pola-jam.duplicate', $polaB))->assertNotFound();
    expect(PolaJam::where('nama', 'Pola Lembaga B (Salinan)')->exists())->toBeFalse();
});

it('loads polaJam relation on kelasList in index view for conflict indicator', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $pola1 = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Reguler']);
    $pola2 = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Intensif']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'pola_jam_id' => $pola1->id, 'nama' => 'VIII-B']);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('VIII-B');
    $response->assertSee('Pola Reguler');
});

it('renders timetable matrix headers and slot chips on pola jam index', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Matriks']);
    JamPelajaran::create([
        'pola_jam_id' => $pola->id,
        'hari' => 'rabu',
        'urutan' => 3,
        'label' => 'Kegiatan Literasi',
        'jam_mulai' => '08:45',
        'jam_selesai' => '09:20',
        'is_pelajaran' => false,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('Matriks Mingguan');
    $response->assertSee('Jam Ke- / Waktu');
    $response->assertSee('Kegiatan Literasi');
    $response->assertSee('08:45 - 09:20');
});

it('shows the "Semua Lembaga" badge in aggregate mode on the pola jam index', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_badge_agregat_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.view']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.pola-jam.index'))
        ->assertSee('Semua Lembaga')
        ->assertSee('border-purple-200 bg-purple-50 text-purple-700', false);
});

it('shows the active lembaga name badge when a yayasan-scoped actor has switched into a lembaga', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_badge_narrow_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Cempaka Raya']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->get(route('admin.pola-jam.index'))
        ->assertSee('SD Cempaka Raya')
        ->assertSee('border-brand-200 bg-brand-50 text-brand-700', false);
});

it('does not show the scope badge for a lembaga-scoped actor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $this->actingAs($manager)->get(route('admin.pola-jam.index'))
        ->assertDontSee('Semua Lembaga');
});

it('shows the lembaga name pill per card in aggregate mode', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_pill_agregat_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Cendekia']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Agregat Test']);

    $this->actingAs($manager)->get(route('admin.pola-jam.index'))
        ->assertSee('SMP Cendekia');
});

it('hides the per-card lembaga pill (keeping only the single header badge mention) once a lembaga is switched into', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_pill_narrow_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Cendekia Utama']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Narrow Test']);

    $response = $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->get(route('admin.pola-jam.index'));

    $response->assertDontSee('<span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">SMP Cendekia Utama</span>', false);
    $response->assertSee('border-brand-200 bg-brand-50 text-brand-700', false);
});

it('disables the "+ Tambah Pola Jam" button when a yayasan-scoped actor has not switched into a lembaga', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'pola-jam.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_disabled_btn_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.view', 'pola-jam.create']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.pola-jam.index'))
        ->assertSee('Pilih lembaga aktif lewat pengalih lembaga terlebih dahulu', false);
});

it('enables the "+ Tambah Pola Jam" button for a lembaga-scoped actor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $this->actingAs($manager)->get(route('admin.pola-jam.index'))
        ->assertSee('openCreatePola()', false)
        ->assertDontSee('Pilih lembaga aktif lewat pengalih lembaga terlebih dahulu', false);
});

it('uses confirmDialog() for the Duplikat button instead of submitting instantly with no confirmation', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Duplikat Test']);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 1]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 2]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertSee('confirmDialog(', false);
    $response->assertSee('Tautan kelas TIDAK ikut disalin', false);
});

it('tidak ada nama icon rusak (class/playlist_add/grid_view/add_circle/content_copy) yang bocor sebagai teks literal di halaman pola-jam', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertDontSee('name="class"', false);
    $response->assertDontSee('name="playlist_add"', false);
    $response->assertDontSee('name="grid_view"', false);
    $response->assertDontSee('name="add_circle"', false);
    $response->assertDontSee('name="content_copy"', false);
});

it('tautan kelas menampilkan ringkasan jumlah kelas aktif vs arsip, bukan menumpuk semua pill mentah', function () {
    Permission::firstOrCreate(['name' => 'kelas.edit', 'guard_name' => 'web']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $manager->givePermissionTo('kelas.edit');
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $taArsip = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);

    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taAktif->id, 'pola_jam_id' => $pola->id, 'nama' => 'Kelas 1A Aktif']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taArsip->id, 'pola_jam_id' => $pola->id, 'nama' => 'Kelas 1A Arsip']);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('1 kelas aktif');
    $response->assertSee('1 arsip');
});

it('form input slot menampilkan tombol shortcut hari dan preset label datalist', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('Senin–Kamis');
    $response->assertSee('Semua Hari');
    $response->assertSee('preset-label-'.$pola->id, false);
    $response->assertSee('Istirahat');
    $response->assertDontSee('sm:col-span-1"', false);
});

it('daftar harian menampilkan tab navigasi per hari dan format waktu tanpa detik', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '07:35']);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('hariAktif', false);
    $response->assertSee('07:00');
    $response->assertDontSee('07:00:00');
});

it('label kolom kiri matriks mingguan tidak mengklaim waktu spesifik satu hari untuk semua kolom', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '07:35']);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'jumat', 'urutan' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '07:30']);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('lihat per hari');
});

it('modal assign kelas menampilkan input pencarian dan tombol pilih semua per grup', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('Cari nama kelas...');
    $response->assertSee('Pilih Semua di Grup Ini');
    $response->assertSee('pencarianKelas', false);
});

it('modal tambah/edit pola jam menampilkan badge lembaga aktif untuk aktor yayasan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Pintera Cabang Utama']);
    Permission::firstOrCreate(['name' => 'pola-jam.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_admin_pola_jam', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo('pola-jam.view');
    $manager = User::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null]);
    $manager->assignRole($role);

    $response = $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('Untuk lembaga');
    $response->assertSee('SD Pintera Cabang Utama');
});

it('modal edit slot memakai x-select untuk field Hari dan Jenis Sesi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    // <x-select> merender base classes tertentu (lihat resources/views/components/select.blade.php)
    // yang tidak dipakai native <select> lama -- disabled:bg-gray-50 disabled:text-gray-500 disabled:cursor-not-allowed
    // adalah base class KHAS komponen ini.
    $response->assertSee('disabled:bg-gray-50 disabled:text-gray-500 disabled:cursor-not-allowed', false);
});

it('menampilkan KPI Total Pola Jam dan Kelas Tertaut di atas halaman', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('Total Pola Jam');
    $response->assertSee('Kelas Tertaut');
});

it('tombol aksi pada pola jam menggunakan komponen x-tooltip standar pintera', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('bg-[#1E293B]', false);
    $response->assertSee('Salin / Duplikasi Pola Jam');
});

it('kartu kpi total pola jam dan kelas tertaut memuat badge icon svg standar pintera', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertOk();
    $response->assertSee('bg-brand-50 text-brand-600', false);
    $response->assertSee('bg-blue-50 text-blue-600', false);
    $response->assertSee('Pola Jadwal');
});

it('controller pola jam dan jam pelajaran mengembalikan json response pada request ajax', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    // Test JSON store Pola Jam
    $resStore = $this->actingAs($manager)->postJson(route('admin.pola-jam.store'), [
        'nama' => 'Pola Jam Khusus AJAX',
    ]);
    $resStore->assertCreated();
    $resStore->assertJson(['status' => 'success']);

    $pola = PolaJam::where('nama', 'Pola Jam Khusus AJAX')->firstOrFail();

    // Test JSON update Pola Jam
    $resUpdate = $this->actingAs($manager)->putJson(route('admin.pola-jam.update', $pola), [
        'nama' => 'Pola Jam Khusus AJAX Edited',
    ]);
    $resUpdate->assertOk();
    $resUpdate->assertJson(['status' => 'success']);

    // Test JSON duplicate Pola Jam
    $resDuplicate = $this->actingAs($manager)->postJson(route('admin.pola-jam.duplicate', $pola));
    $resDuplicate->assertOk();
    $resDuplicate->assertJson(['status' => 'success']);

    // Test JSON store Jam Pelajaran
    $resSlot = $this->actingAs($manager)->postJson(route('admin.jam-pelajaran.store'), [
        'pola_jam_id' => $pola->id,
        'hari' => ['senin'],
        'urutan' => 1,
        'label' => 'Jam ke-1',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:45',
        'is_pelajaran' => 1,
    ]);
    $resSlot->assertOk();
    $resSlot->assertJson(['status' => 'success']);

    $slot = JamPelajaran::where('pola_jam_id', $pola->id)->firstOrFail();

    // Test JSON update Jam Pelajaran
    $resSlotUpdate = $this->actingAs($manager)->putJson(route('admin.jam-pelajaran.update', $slot), [
        'label' => 'Jam ke-1 Revisi',
        'jam_mulai' => '07:05',
        'jam_selesai' => '07:50',
        'is_pelajaran' => 1,
    ]);
    $resSlotUpdate->assertOk();
    $resSlotUpdate->assertJson(['status' => 'success']);

    // Test JSON assign kelas
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $resAssign = $this->actingAs($manager)->putJson(route('admin.pola-jam.assign-kelas', $pola), [
        'kelas_ids' => [$kelas->id],
    ]);
    $resAssign->assertOk();
    $resAssign->assertJson(['status' => 'success']);

    // Test JSON delete Jam Pelajaran
    $resSlotDelete = $this->actingAs($manager)->deleteJson(route('admin.jam-pelajaran.destroy', $slot));
    $resSlotDelete->assertOk();
    $resSlotDelete->assertJson(['status' => 'success']);

    // Unassign kelas so pola can be deleted
    $pola->kelas()->update(['pola_jam_id' => null]);

    // Test JSON delete Pola Jam
    $resDelete = $this->actingAs($manager)->deleteJson(route('admin.pola-jam.destroy', $pola));
    $resDelete->assertOk();
    $resDelete->assertJson(['status' => 'success']);
});

it('permintaan ajax get index mengembalikan partial view _daftar', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Uji Partial']);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'), [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk();
    $response->assertSee('Pola Uji Partial');
    $response->assertDontSee('Kelola jadwal waktu belajar harian dan tautkan dengan kelas yang relevan.');
});
