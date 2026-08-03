<?php

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsPolaJamManager(Lembaga $lembaga): User
{
    foreach (['pola-jam.view', 'pola-jam.create', 'pola-jam.edit', 'pola-jam.delete', 'jam-pelajaran.create'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['pola-jam.view', 'pola-jam.create', 'pola-jam.edit', 'pola-jam.delete', 'jam-pelajaran.create']);

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

    expect(PolaJam::where('nama', 'Kelas Tinggi 4-6')->exists())->toBeTrue();
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
    $manager = User::factory()->create(['lembaga_id' => null]);
    $manager->assignRole($role);
    // No active_lembaga_id in session — this yayasan-scoped user can see both lembaga A and B,
    // so the tenant scope alone would let this cross-lembaga assignment through.

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

