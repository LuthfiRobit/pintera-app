<?php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;

it('shows the guru placeholder dashboard to a user with only the guru role', function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $user = User::factory()->create();
    $user->assignRole('guru');

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Dashboard Guru');
});

it('shows the siswa placeholder dashboard to a user with only the siswa role', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $user = User::factory()->create();
    $user->assignRole('siswa');

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Dashboard Siswa');
});

it('shows the yayasan dashboard with a lembaga switcher to a yayasan-scoped user', function () {
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Pintera Switcher']);

    $user = User::factory()->create(['yayasan_id' => $yayasan->id]);
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('SD Pintera Switcher');
    $response->assertSee('switch_lembaga='.$lembaga->id, false);
});

it('does not show a lembaga belonging to another yayasan on the yayasan dashboard', function () {
    Role::firstOrCreate(['name' => 'yayasan_super_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan', 'is_protected' => true]);
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    Lembaga::factory()->create(['yayasan_id' => $yayasanSaya->id, 'nama' => 'SMP Milik Saya']);
    Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id, 'nama' => 'SMA Yayasan Lain']);

    $user = User::factory()->create(['yayasan_id' => $yayasanSaya->id]);
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)->get('/dashboard');

    // Regression test for the DashboardController cross-yayasan stats leak: Lembaga::all()
    // was previously unfiltered, so a yayasan admin's landing dashboard showed institution
    // names, SPMB/keuangan aggregates, and system-wide counts from every other yayasan too.
    $response->assertOk();
    $response->assertSee('SMP Milik Saya');
    $response->assertDontSee('SMA Yayasan Lain');
});

it('shows the platform dashboard with cross-yayasan aggregates to a platform_super_admin', function () {
    Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);

    $yayasanA = Yayasan::factory()->create(['nama' => 'Yayasan Alpha']);
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $yayasanB = Yayasan::factory()->create(['nama' => 'Yayasan Beta']);
    Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);

    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $guru = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $guru->assignRole('guru');

    $admin = User::factory()->create();
    $admin->assignRole('platform_super_admin');

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertViewIs('admin.dashboard.platform');
    $response->assertSee('Yayasan Alpha');
    $response->assertSee('Yayasan Beta');
    $response->assertViewHas('stats', function ($stats) {
        return $stats['yayasan'] === 2 && $stats['lembaga'] === 2;
    });
});

it('shows the generic staff dashboard without a switcher to a lembaga-scoped user', function () {
    Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertDontSee('switch_lembaga', false);
});

it('shows the yayasan growth trend chart and health columns on the platform dashboard', function () {
    Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);
    $yayasan = Yayasan::factory()->create(['nama' => 'Yayasan Sehat']);
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $admin = User::factory()->create();
    $admin->assignRole('platform_super_admin');

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('trenTenantChart(', false);
    $response->assertViewHas('ringkasanPerYayasan', function ($ringkasan) {
        return $ringkasan->first()['akunNonaktif'] === 0;
    });
});

it('passes teaching schedule and progressKelasWali to the guru dashboard view', function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $user = User::factory()->create();
    $user->assignRole('guru');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Jadwal Mengajar Hari Ini');
    $response->assertViewHas('jadwalHariIni');
});

it('passes children data, unpaid bills, and today schedule to the orang_tua dashboard view', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $user = User::factory()->create();
    $user->assignRole('orang_tua');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Tagihan Belum Lunas');
    $response->assertSee('Jadwal Pelajaran Anak Hari Ini');
    $response->assertViewHas('tagihanBelumLunas');
});

it('passes profile, schedule, and unpaid bills to the siswa dashboard view', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $user = User::factory()->create();
    $user->assignRole('siswa');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Kelas Saya');
    $response->assertSee('Jadwal Pelajaran Hari Ini');
    $response->assertViewHas('tagihanBelumLunas');
});

it('shows a siswa their latest recorded grade on their own dashboard', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $lembaga = Lembaga::factory()->create();
    $kelas = \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = \App\Models\Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $mapel = \App\Domains\Akademik\Models\MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = \App\Models\Semester::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = \App\Domains\Akademik\Models\KomponenPenilaian::factory()->create([
        'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga->id,
    ]);
    $asesmen = \App\Domains\Akademik\Models\Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga->id,
    ]);
    \App\Domains\Akademik\Models\NilaiSiswa::create([
        'siswa_id' => $siswa->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id, 'lembaga_id' => $lembaga->id, 'nilai_angka' => 92,
    ]);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('siswa');
    $siswa->update(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('92');
});

it('shows an orang tua the latest recorded grade for their linked child', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $lembaga = Lembaga::factory()->create();
    $kelas = \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = \App\Models\Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Anak Dashboard Ortu']);
    $mapel = \App\Domains\Akademik\Models\MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = \App\Models\Semester::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = \App\Domains\Akademik\Models\KomponenPenilaian::factory()->create([
        'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga->id,
    ]);
    $asesmen = \App\Domains\Akademik\Models\Asesmen::factory()->create([
        'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga->id,
    ]);
    \App\Domains\Akademik\Models\NilaiSiswa::create([
        'siswa_id' => $siswa->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id, 'lembaga_id' => $lembaga->id, 'nilai_angka' => 85,
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = \App\Models\OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $response = $this->actingAs($orangTuaUser)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Anak Dashboard Ortu');
    $response->assertSee('85');
});

it('shows the 30-day attendance chart on the karyawan dashboard', function () {
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenis = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create();

    $karyawan = app(\App\Services\AkunKaryawanGenerator::class)->buat(
        'Karyawan Chart Test', '3201234567894444', $yayasan->id, $lembaga->id, $jenis->id
    );
    $karyawan->user()->update(['must_change_password' => false, 'email_verified_at' => now()]);

    $response = $this->actingAs($karyawan->user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('presensiBulananChart(', false);
});




