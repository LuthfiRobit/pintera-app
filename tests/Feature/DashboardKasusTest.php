<?php
// tests/Feature/DashboardKasusTest.php

use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Guru;
use App\Domains\Kasus\Models\Kasus;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('shows a guru only kasus they submitted, not kasus submitted by another guru', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Permission::firstOrCreate(['name' => 'kasus.ajukan', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.ajukan', 'kasus.view']);
    $guruUser->assignRole('guru');
    $guru = Guru::withoutGlobalScopes()->create([
        'user_id' => $guruUser->id, 'lembaga_id' => $lembaga->id,
        'nik' => fake()->unique()->numerify('################'), 'nama' => 'Guru Satu',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
        'status_aktif' => 'aktif',
    ]);

    $kasusMilikSaya = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'diajukan_oleh_guru_id' => $guru->id,
        'kategori_masalah' => 'Milik Saya', 'deskripsi' => 'x',
    ]);
    $kasusGuruLain = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Milik Guru Lain', 'deskripsi' => 'x',
    ]);

    $response = $this->actingAs($guruUser)->get(route('dashboard'));

    $response->assertOk()->assertSee('Milik Saya')->assertDontSee('Milik Guru Lain');
});

it('shows a guru_bk konselor a "kasus saya tangani" section on their dashboard', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $konselorUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.view']);
    $konselorUser->assignRole('guru');
    $guruBk = Guru::withoutGlobalScopes()->create([
        'user_id' => $konselorUser->id, 'lembaga_id' => $lembaga->id,
        'nik' => fake()->unique()->numerify('################'), 'nama' => 'Konselor BK',
        'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk', 'status_kepegawaian' => 'GTY',
        'status_aktif' => 'aktif',
    ]);
    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'konselor_guru_id' => $guruBk->id,
        'status' => StatusKasus::Berjalan, 'kategori_masalah' => 'Ditangani Saya', 'deskripsi' => 'x',
    ]);

    $response = $this->actingAs($konselorUser)->get(route('dashboard'));

    $response->assertOk()->assertSee('Ditangani Saya');
});

it('shows an orang tua only kasus belonging to their linked children', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $anakSaya = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $anakLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.view']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Dashboard',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200001111',
        'email' => 'ortu.dashboard@example.test',
    ]);
    $anakSaya->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    Kasus::create([
        'siswa_id' => $anakSaya->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Anak Saya', 'deskripsi' => 'x',
    ]);
    Kasus::create([
        'siswa_id' => $anakLain->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Anak Lain', 'deskripsi' => 'x',
    ]);

    $response = $this->actingAs($orangTuaUser)->get(route('dashboard'));

    $response->assertOk()->assertSee('Anak Saya')->assertDontSee('Anak Lain');
});

it('shows a pegawai_yayasan konselor only kasus they are assigned to', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $karyawanUser = User::factory()->create(['lembaga_id' => null]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'pegawai_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kasus.view']);
    $karyawanUser->assignRole('pegawai_yayasan');
    $jenis = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create(['is_konselor' => true]);
    $karyawan = \App\Models\Karyawan::withoutGlobalScopes()->create([
        'user_id' => $karyawanUser->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Karyawan Konselor',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);
    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'konselor_karyawan_id' => $karyawan->id,
        'status' => StatusKasus::Berjalan, 'kategori_masalah' => 'Ditangani Karyawan', 'deskripsi' => 'x',
    ]);

    // F2: an unassigned kasus (konselor_karyawan_id = null) in a DIFFERENT lembaga must
    // NOT leak to this karyawan via the TenantScope-bypassed query defaulting a null
    // $karyawanId to a `WHERE konselor_karyawan_id IS NULL` match.
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id]);
    Kasus::create([
        'siswa_id' => $siswaLain->id, 'lembaga_id' => $lembagaLain->id, 'konselor_karyawan_id' => null,
        'status' => StatusKasus::Ditugaskan, 'kategori_masalah' => 'Kasus Tak Ditugaskan Karyawan', 'deskripsi' => 'x',
    ]);

    $response = $this->actingAs($karyawanUser)->get(route('dashboard'));

    $response->assertOk()->assertSee('Ditangani Karyawan')->assertDontSee('Kasus Tak Ditugaskan Karyawan');
});

it('shows a pegawai_yayasan-roled user with no Karyawan profile row no unassigned kasus', function () {
    // F2: $user->karyawan()->...->first()?->id resolving to null makes
    // where('konselor_karyawan_id', null) compile to IS NULL on a TenantScope-bypassed
    // query, leaking every unassigned kasus across every lembaga/yayasan to a profile-less
    // pegawai_yayasan user. The sibling test above always creates a real Karyawan row, so it
    // never exercises this null path — this test does.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'pegawai_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kasus.view']);
    $karyawanTanpaProfil = User::factory()->create(['lembaga_id' => null]);
    $karyawanTanpaProfil->assignRole('pegawai_yayasan');

    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'konselor_karyawan_id' => null,
        'kategori_masalah' => 'Kasus Karyawan Belum Ditugaskan', 'deskripsi' => 'x',
    ]);

    $response = $this->actingAs($karyawanTanpaProfil)->get(route('dashboard'));

    $response->assertOk()->assertDontSee('Kasus Karyawan Belum Ditugaskan');
});

it('shows a guru-roled user with no Guru profile row neither an orang-tua-submitted kasus nor an unassigned kasus', function () {
    // F3: $user->guru?->id resolving to null makes where('diajukan_oleh_guru_id', null)
    // and where('konselor_guru_id', null) compile to IS NULL, leaking every
    // orang-tua-submitted / unassigned kasus in the lembaga to a profile-less guru user.
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.view']);
    $guruTanpaProfil = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruTanpaProfil->assignRole('guru');

    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'diajukan_oleh_guru_id' => null,
        'kategori_masalah' => 'Diajukan Orang Tua', 'deskripsi' => 'x',
    ]);
    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'konselor_guru_id' => null,
        'kategori_masalah' => 'Kasus Belum Ditugaskan', 'deskripsi' => 'x',
    ]);

    $response = $this->actingAs($guruTanpaProfil)->get(route('dashboard'));

    $response->assertOk()
        ->assertDontSee('Diajukan Orang Tua')
        ->assertDontSee('Kasus Belum Ditugaskan');
});

it('counts the guru dashboard stat tiles from only that guru\'s own visible kasus, not a global count', function () {
    // Spec: "Ringkasan angka per status di tiap dashboard sesuai dengan daftar kasus yang
    // terlihat di peran tsb (bukan angka global lintas peran)."
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Permission::firstOrCreate(['name' => 'kasus.ajukan', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.ajukan', 'kasus.view']);
    $guruUser->assignRole('guru');
    $guru = Guru::withoutGlobalScopes()->create([
        'user_id' => $guruUser->id, 'lembaga_id' => $lembaga->id,
        'nik' => fake()->unique()->numerify('################'), 'nama' => 'Guru Stats',
        'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas', 'status_kepegawaian' => 'GTY',
        'status_aktif' => 'aktif',
    ]);

    // One kasus this guru submitted, berjalan status.
    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'diajukan_oleh_guru_id' => $guru->id,
        'status' => StatusKasus::Berjalan, 'kategori_masalah' => 'x', 'deskripsi' => 'x',
    ]);
    // Two kasus submitted by someone else, must not count toward this guru's stats.
    Kasus::create(['siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'status' => StatusKasus::Berjalan, 'kategori_masalah' => 'x', 'deskripsi' => 'x']);
    Kasus::create(['siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id, 'status' => StatusKasus::Berjalan, 'kategori_masalah' => 'x', 'deskripsi' => 'x']);

    $response = $this->actingAs($guruUser)->get(route('dashboard'));

    $response->assertOk();
    $response->assertViewHas('kasusDiajukanStats', fn ($stats) => $stats['berjalan'] === 1);
});
