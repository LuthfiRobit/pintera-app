<?php
// tests/Feature/Admin/KasusTriaseTest.php

use App\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Kasus;
use App\Models\KasusConsent;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Notifications\KonselorDipilihNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

function actingAsKasusTriaseManager(Lembaga $lembaga): User
{
    foreach (['kasus.view', 'kasus.triase'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view', 'kasus.triase']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('assigns a guru_bk konselor from the resolver, moves status to menunggu_consent, and creates two consent rows', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKasusTriaseManager($lembaga);

    $guruBk = Guru::withoutGlobalScopes()->create([
        'user_id' => User::factory()->create(['lembaga_id' => $lembaga->id])->id,
        'lembaga_id' => $lembaga->id, 'nik' => fake()->unique()->numerify('################'),
        'nama' => 'Konselor BK', 'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk',
        'status_kepegawaian' => 'GTY', 'status_aktif' => 'aktif',
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaUser->assignRole('orang_tua');
    $kontakUtama = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Kontak Utama',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200003333',
        'email' => 'kontak.utama.triase@example.test',
    ]);
    $siswa->orangTua()->attach($kontakUtama->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);

    Notification::fake();

    $this->actingAs($manager)->post(route('admin.kasus.assign-konselor', $kasus), [
        'tingkat_urgensi' => 'sedang',
        'konselor_tipe' => 'guru',
        'konselor_id' => $guruBk->id,
    ])->assertRedirect(route('admin.kasus.index'));

    $kasus->refresh();
    expect($kasus->status)->toBe(StatusKasus::MenungguConsent);
    expect($kasus->tingkat_urgensi)->toBe('sedang');
    expect($kasus->konselor_guru_id)->toBe($guruBk->id);
    expect($kasus->konselor_karyawan_id)->toBeNull();
    expect(KasusConsent::where('kasus_id', $kasus->id)->count())->toBe(2);
    expect(KasusConsent::where('kasus_id', $kasus->id)->where('jenis', 'sesi_pendampingan')->where('status', 'menunggu')->exists())->toBeTrue();
    expect(KasusConsent::where('kasus_id', $kasus->id)->where('jenis', 'pengumpulan_media')->where('status', 'menunggu')->exists())->toBeTrue();

    Notification::assertSentTo($kontakUtama, KonselorDipilihNotification::class);
});

it('rejects triase from an admin without kasus.triase permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
    ]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noPermissionUser)->post(route('admin.kasus.assign-konselor', $kasus), [
        'tingkat_urgensi' => 'rendah', 'konselor_tipe' => 'guru', 'konselor_id' => 1,
    ])->assertForbidden();
});
