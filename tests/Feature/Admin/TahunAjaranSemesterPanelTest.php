<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function actingAsTahunAjaranManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'manage-tahun-ajaran', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('manage-tahun-ajaran');

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('creates a tahun ajaran auto-scoped to the acting lembaga-scoped user', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);

    $this->actingAs($manager)->post(route('admin.tahun-ajaran.store'), [
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
    ])->assertRedirect(route('admin.tahun-ajaran.index'));

    $tahunAjaran = TahunAjaran::where('nama', '2026/2027')->first();
    expect($tahunAjaran->lembaga_id)->toBe($lembaga->id);
});

it('activates a tahun ajaran via the panel, deactivating the previous one', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);
    $this->actingAs($manager);

    $lama = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2025/2026',
        'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30', 'status_aktif' => true,
    ]);
    $baru = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30',
    ]);

    $this->patch(route('admin.tahun-ajaran.activate', $baru))->assertRedirect(route('admin.tahun-ajaran.index'));

    expect($lama->fresh()->status_aktif)->toBeFalse();
    expect($baru->fresh()->status_aktif)->toBeTrue();
});

it('creates a semester under a tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);
    $this->actingAs($manager);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);

    $this->post(route('admin.semester.store'), [
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Ganjil',
        'urutan' => 1,
        'kode_dapodik' => '20261',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-01-15',
    ])->assertRedirect(route('admin.tahun-ajaran.index'));

    expect(Semester::where('tahun_ajaran_id', $tahunAjaran->id)->where('nama', 'Ganjil')->exists())->toBeTrue();
});

it('shows a friendly error instead of a 500 when activating a semester whose tahun ajaran is inactive', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsTahunAjaranManager($lembaga);
    $this->actingAs($manager);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => false,
    ]);
    $semester = Semester::create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil', 'urutan' => 1,
        'kode_dapodik' => '20261', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-01-15',
    ]);

    $this->patch(route('admin.semester.activate', $semester))
        ->assertRedirect()
        ->assertSessionHasErrors('semester');

    expect($semester->fresh()->status_aktif)->toBeFalse();
});
