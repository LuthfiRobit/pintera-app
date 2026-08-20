<?php

use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsJamPelajaranManager(Lembaga $lembaga, array $permissions = ['jam-pelajaran.edit', 'jam-pelajaran.delete']): User
{
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_jam_pelajaran_'.$lembaga->id, 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->syncPermissions($permissions);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('updates a jam pelajaran slot', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJamPelajaranManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'label' => 'Lama']);

    $this->actingAs($manager)->put(route('admin.jam-pelajaran.update', $jam), [
        'label' => 'Baru', 'jam_mulai' => '08:00', 'jam_selesai' => '08:35', 'is_pelajaran' => '1',
    ])->assertRedirect(route('admin.pola-jam.index'));

    expect($jam->fresh()->label)->toBe('Baru');
});

it('rejects an update that would collide with another slot on the same pola/hari/urutan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJamPelajaranManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 1]);
    $jamKedua = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'hari' => 'senin', 'urutan' => 2]);

    $this->actingAs($manager)->put(route('admin.jam-pelajaran.update', $jamKedua), [
        'label' => 'Coba Tabrak', 'jam_mulai' => '08:00', 'jam_selesai' => '08:35', 'is_pelajaran' => '1', 'urutan' => 1, 'hari' => 'senin',
    ])->assertSessionHasErrors();

    expect($jamKedua->fresh()->urutan)->toBe(2);
});

it('rejects editing another lembaga\'s jam pelajaran with 404', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJamPelajaranManager($lembaga);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id]);

    $this->actingAs($manager)->put(route('admin.jam-pelajaran.update', $jamLain), [
        'label' => 'Diubah Paksa', 'jam_mulai' => '08:00', 'jam_selesai' => '08:35', 'is_pelajaran' => '1',
    ])->assertNotFound();
});

it('deletes a jam pelajaran with no jadwal pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJamPelajaranManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id]);

    $this->actingAs($manager)->delete(route('admin.jam-pelajaran.destroy', $jam))
        ->assertRedirect(route('admin.pola-jam.index'));

    expect(JamPelajaran::find($jam->id))->toBeNull();
});

it('refuses to delete a jam pelajaran that has a jadwal pelajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJamPelajaranManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    JadwalPelajaran::factory()->create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id,
    ]);

    $this->actingAs($manager)->delete(route('admin.jam-pelajaran.destroy', $jam))
        ->assertSessionHasErrors();

    expect(JamPelajaran::find($jam->id))->not->toBeNull();
});
