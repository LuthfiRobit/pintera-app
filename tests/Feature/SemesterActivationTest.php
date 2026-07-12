<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;

it('copies lembaga_id from the parent tahun ajaran on create', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');
    $this->actingAs($user);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => true,
    ]);

    $semester = Semester::create([
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Ganjil',
        'urutan' => 1,
        'kode_dapodik' => '20261',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-01-15',
    ]);

    expect($semester->lembaga_id)->toBe($lembaga->id);
});

it('copies lembaga_id from the parent tahun ajaran even when the acting user is not lembaga-scoped', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);

    // The acting user is yayasan-scoped, so BelongsToTenant's own auto-fill (which only
    // fires for lembaga-scoped users) does NOT set lembaga_id — only Semester's own
    // booted() hook, which looks up the parent TahunAjaran, can supply the value here.
    $actingUser = User::factory()->create();
    $actingUser->assignRole('yayasan_super_admin');
    $this->actingAs($actingUser);

    // TahunAjaran must be created by a lembaga-scoped user to get lembaga_id auto-filled correctly,
    // so create it first as the acting lembaga user, then switch to the yayasan-scoped actor.
    $lembagaUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $lembagaUser->assignRole('admin_administrasi');
    $this->actingAs($lembagaUser);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => true,
    ]);

    $this->actingAs($actingUser);

    $semester = Semester::create([
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Ganjil',
        'urutan' => 1,
        'kode_dapodik' => '20261',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-01-15',
    ]);

    expect($semester->lembaga_id)->toBe($lembaga->id);
});

it('refuses to activate a semester whose tahun ajaran is not active', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');
    $this->actingAs($user);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => false,
    ]);

    $semester = Semester::create([
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Ganjil',
        'urutan' => 1,
        'kode_dapodik' => '20261',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-01-15',
    ]);

    expect(fn () => $semester->activate())->toThrow(RuntimeException::class);
});

it('deactivates sibling semesters for the same lembaga on activation', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');
    $this->actingAs($user);

    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id,
        'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => true,
    ]);

    $ganjil = Semester::create([
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Ganjil',
        'urutan' => 1,
        'kode_dapodik' => '20261',
        'tanggal_mulai' => '2026-07-01',
        'tanggal_selesai' => '2027-01-15',
        'status_aktif' => true,
    ]);

    $genap = Semester::create([
        'tahun_ajaran_id' => $tahunAjaran->id,
        'nama' => 'Genap',
        'urutan' => 2,
        'kode_dapodik' => '20262',
        'tanggal_mulai' => '2027-01-16',
        'tanggal_selesai' => '2027-06-30',
        'status_aktif' => false,
    ]);

    $genap->activate();

    expect($ganjil->fresh()->status_aktif)->toBeFalse();
    expect($genap->fresh()->status_aktif)->toBeTrue();
});
