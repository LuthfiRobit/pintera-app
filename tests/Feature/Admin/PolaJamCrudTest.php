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
        'hari' => 'senin',
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
        'hari' => 'senin',
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => '0',
    ])->assertNotFound();

    expect(JamPelajaran::where('pola_jam_id', $polaB->id)->where('label', 'Upacara')->exists())->toBeFalse();
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

it('assigns a pola jam to a kelas from the pola jam screen', function () {
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
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $this->actingAs($manager)->put(route('admin.pola-jam.assign-kelas', ['polaJam' => $pola, 'kelas' => $kelas]))
        ->assertRedirect(route('admin.pola-jam.index'));

    expect($kelas->fresh()->pola_jam_id)->toBe($pola->id);
});

it('rejects assigning a pola jam to another lembaga\'s kelas with 404', function () {
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

    $this->actingAs($manager)->put(route('admin.pola-jam.assign-kelas', ['polaJam' => $pola, 'kelas' => $kelasLain]))
        ->assertNotFound();

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

    $this->actingAs($manager)->put(route('admin.pola-jam.assign-kelas', ['polaJam' => $polaA, 'kelas' => $kelasB]))
        ->assertSessionHasErrors();

    expect($kelasB->fresh()->pola_jam_id)->toBeNull();
});
