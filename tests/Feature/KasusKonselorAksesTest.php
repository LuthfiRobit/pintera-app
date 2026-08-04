<?php
// tests/Feature/KasusKonselorAksesTest.php

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Kasus;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function buatGuruBkKonselorAkses(Lembaga $lembaga): array
{
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.view']);
    $user->assignRole('guru');
    $guru = Guru::withoutGlobalScopes()->create([
        'user_id' => $user->id, 'lembaga_id' => $lembaga->id,
        'nik' => fake()->unique()->numerify('################'), 'nama' => 'Konselor BK',
        'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk', 'status_kepegawaian' => 'GTY',
        'status_aktif' => 'aktif',
    ]);

    return [$user, $guru];
}

function buatKaryawanKonselorAkses(Yayasan $yayasan): array
{
    $user = User::factory()->create(['lembaga_id' => null]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'karyawan_pool', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kasus.view']);
    $user->assignRole('karyawan_pool');
    $jenis = \App\Models\JenisKaryawanMaster::factory()->create(['is_konselor' => true]);
    $karyawan = Karyawan::withoutGlobalScopes()->create([
        'user_id' => $user->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Karyawan Konselor',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);

    return [$user, $karyawan];
}

it('lets an assigned guru_bk konselor open kasus.index and kasus.show', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    [$konselorUser, $guruBk] = buatGuruBkKonselorAkses($lembaga);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::Ditugaskan, 'konselor_guru_id' => $guruBk->id,
    ]);

    $this->actingAs($konselorUser)->get(route('kasus.index'))->assertOk()->assertSee($siswa->nama_lengkap);
    $this->actingAs($konselorUser)->get(route('kasus.show', $kasus))->assertOk();
});

it('lets an assigned karyawan_pool konselor open kasus.index and kasus.show', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    [$konselorUser, $karyawan] = buatKaryawanKonselorAkses($yayasan);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::Ditugaskan, 'konselor_karyawan_id' => $karyawan->id,
    ]);

    $this->actingAs($konselorUser)->get(route('kasus.index'))->assertOk()->assertSee($siswa->nama_lengkap);
    $this->actingAs($konselorUser)->get(route('kasus.show', $kasus))->assertOk();
});

it('lets the siswa a kasus is about open kasus.show, but not a different siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $siswaUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $siswaRole = Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswaRole->givePermissionTo(['kasus.view']);
    $siswaUser->assignRole('siswa');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $siswaUser->id]);

    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaLainUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaLainUser->assignRole('siswa');
    $siswaLain->update(['user_id' => $siswaLainUser->id]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);

    $this->actingAs($siswaUser)->get(route('kasus.show', $kasus))->assertOk();
    $this->actingAs($siswaLainUser)->get(route('kasus.show', $kasus))->assertNotFound();
});

it('404s a guru_bk who is not assigned to the kasus', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    [$unrelatedKonselorUser] = buatGuruBkKonselorAkses($lembaga);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);

    $this->actingAs($unrelatedKonselorUser)->get(route('kasus.show', $kasus))->assertNotFound();
});
