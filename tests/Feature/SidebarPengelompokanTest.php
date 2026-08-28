<?php

use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission;

function siapkanGuruUntukSidebar(): User
{
    foreach (['presensi.isi', 'komponen-penilaian.kelola-sendiri', 'asesmen.kelola', 'rapor.input-wali', 'rpp.view', 'rpp.kelola', 'kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.lihat-sendiri', 'kasus.view'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi', 'komponen-penilaian.kelola-sendiri', 'asesmen.kelola', 'rapor.input-wali', 'rpp.view', 'rpp.kelola', 'kehadiran-sdm.lihat-qr-sendiri', 'kehadiran-sdm.izin.lihat-sendiri', 'kasus.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru');
    Guru::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id]);

    return $user;
}

it('shows RPP, QR Kehadiran, Izin/Cuti, and Kasus Pendampingan under Ruang Guru for a guru account', function () {
    $guru = siapkanGuruUntukSidebar();

    $response = $this->actingAs($guru)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSeeInOrder(['Ruang Guru', 'Perangkat Ajar (RPP)']);
    $response->assertSee('QR Kehadiran Saya');
    $response->assertSee('Izin/Cuti Saya');
});

it('shows Ruang Siswa group with 3 dalam-pengembangan links for a siswa account', function () {
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.view']);

    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('siswa');
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Ruang Siswa');
    $response->assertSee('Nilai &amp; Rapor', false);
    $response->assertSee('Jadwal Pelajaran');
    $response->assertSee('Presensi Saya');
});

it('shows Ruang Orang Tua group with dalam-pengembangan links plus keuangan self-service items, moved out of the Keuangan group', function () {
    foreach (['kasus.view', 'keuangan.akses'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['kasus.view', 'keuangan.akses']);

    $orangTuaUser = User::factory()->create();
    $orangTuaUser->assignRole('orang_tua');
    OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);

    $response = $this->actingAs($orangTuaUser)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Ruang Orang Tua');
    $response->assertSee('Nilai Anak');
    $response->assertSee('Jadwal Anak');
    $response->assertSee('Riwayat Izin/Sakit Anak');
    $response->assertSee('Dompet &amp; Tagihan Saya', false);
});

it('does not duplicate RPP into Akademik for a guru account, but still shows it there for kepala_sekolah', function () {
    $guru = siapkanGuruUntukSidebar();

    $responseGuru = $this->actingAs($guru)->get(route('dashboard'));
    $responseGuru->assertOk();
    expect(substr_count($responseGuru->getContent(), 'Perangkat Ajar (RPP)'))->toBe(1);

    foreach (['spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi', 'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk', 'tagihan.view', 'komponen-penilaian.kelola', 'rapor.view', 'rapor.approve', 'kenaikan-kelas.kelola', 'rpp.view', 'rpp.verify', 'kehadiran-sdm.izin.approve'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $kepsekRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $kepsekRole->givePermissionTo(['rpp.view', 'rpp.verify']);
    $kepsek = User::factory()->create(['lembaga_id' => Lembaga::factory()->create()->id]);
    $kepsek->assignRole('kepala_sekolah');

    $responseKepsek = $this->actingAs($kepsek)->get(route('dashboard'));
    $responseKepsek->assertOk();
    $responseKepsek->assertSee('Perangkat Ajar (RPP)');
});

it('shows Kasus Pendampingan under Kehadiran Saya (not Pendampingan) for a pool konselor karyawan without kasus.view', function () {
    (new RoleSeeder)->run();
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $user->assignRole('pegawai_yayasan');
    $jenis = JenisKaryawanMaster::factory()->create(['is_konselor' => true]);
    $karyawan = Karyawan::withoutGlobalScopes()->create([
        'user_id' => $user->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Karyawan Pool',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);
    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::Ditugaskan, 'konselor_karyawan_id' => $karyawan->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSeeInOrder(['Kehadiran Saya', 'Kasus Pendampingan']);
});

it('does not show QR Kehadiran or Izin/Cuti twice for a guru account', function () {
    $guru = siapkanGuruUntukSidebar();

    $response = $this->actingAs($guru)->get(route('dashboard'));

    $response->assertOk();
    expect(substr_count($response->getContent(), 'QR Kehadiran Saya'))->toBe(1);
    expect(substr_count($response->getContent(), 'Izin/Cuti Saya'))->toBe(1);
});

it('keeps guru personal items in Ruang Guru and shows administrative items normally when the same user also holds wakasek_kurikulum', function () {
    $guru = siapkanGuruUntukSidebar();
    foreach (['komponen-penilaian.kelola', 'rapor.view', 'rapor.verify', 'kenaikan-kelas.kelola', 'jadwal-pelajaran.kelola'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $wakasekRole = Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $wakasekRole->givePermissionTo(['komponen-penilaian.kelola', 'rapor.view', 'rapor.verify', 'kenaikan-kelas.kelola', 'jadwal-pelajaran.kelola']);
    $guru->assignRole('wakasek_kurikulum');

    $response = $this->actingAs($guru)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Ruang Guru');
    expect(substr_count($response->getContent(), 'Perangkat Ajar (RPP)'))->toBe(1);
    $response->assertSee('Kenaikan Kelas');
});

it('does not treat guru_bk as guru identity for sidebar grouping purposes', function () {
    (new RoleSeeder)->run();
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru_bk');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Ruang Guru');
});
