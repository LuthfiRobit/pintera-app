<?php

use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Policies\KasusPolicy;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('isKonselor returns true for the assigned konselor guru, false for another guru', function () {
    $lembaga = Lembaga::factory()->create();
    $guruKonselor = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruLain = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKonselor = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruKonselor->user_id = $userKonselor->id;
    $guruKonselor->save();
    $guruKonselor->person->update(['user_id' => $userKonselor->id]);

    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'konselor_guru_id' => $guruKonselor->id]);

    $userB = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruLain->user_id = $userB->id;
    $guruLain->save();
    $guruLain->person->update(['user_id' => $userB->id]);

    $policy = new KasusPolicy;

    expect($policy->isKonselor($userKonselor->fresh(), $kasus))->toBeTrue();
    expect($policy->isKonselor($userB->fresh(), $kasus))->toBeFalse();
});

it('view grants access to the guru submitter even when not the konselor', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruPengaju = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruPengaju->user_id = $user->id;
    $guruPengaju->save();
    $guruPengaju->person->update(['user_id' => $user->id]);

    $kasus = Kasus::factory()->create([
        'lembaga_id' => $lembaga->id,
        'siswa_id' => $siswa->id,
        'diajukan_oleh_guru_id' => $guruPengaju->id,
    ]);

    expect((new KasusPolicy)->view($user->fresh(), $kasus, $siswa))->toBeTrue();
});

it('view grants access to orang tua kontak utama but not a non-kontak-utama orang tua', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kontakUtama = OrangTua::factory()->create();
    $bukanKontakUtama = OrangTua::factory()->create();
    $siswa->orangTua()->attach($kontakUtama->id, ['is_kontak_utama' => true]);
    $siswa->orangTua()->attach($bukanKontakUtama->id, ['is_kontak_utama' => false]);

    $userKontakUtama = User::factory()->create();
    $kontakUtama->user_id = $userKontakUtama->id;
    $kontakUtama->save();
    $kontakUtama->person->update(['user_id' => $userKontakUtama->id]);

    $userBukanKontakUtama = User::factory()->create();
    $bukanKontakUtama->user_id = $userBukanKontakUtama->id;
    $bukanKontakUtama->save();
    $bukanKontakUtama->person->update(['user_id' => $userBukanKontakUtama->id]);

    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id]);

    $policy = new KasusPolicy;

    expect($policy->view($userKontakUtama->fresh(), $kasus, $siswa))->toBeTrue();
    expect($policy->view($userBukanKontakUtama->fresh(), $kasus, $siswa))->toBeFalse();
});

it('view grants access to a triase admin within the same lembaga but not a different lembaga', function () {
    Role::firstOrCreate(['name' => 'admin_lembaga', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    Permission::firstOrCreate(['name' => 'kasus.triase', 'guard_name' => 'web']);

    $lembagaA = Lembaga::factory()->create();
    $lembagaB = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembagaA->id, 'siswa_id' => $siswa->id]);

    $adminA = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $adminA->givePermissionTo('kasus.triase');
    $adminB = User::factory()->create(['lembaga_id' => $lembagaB->id]);
    $adminB->givePermissionTo('kasus.triase');

    $policy = new KasusPolicy;

    expect($policy->view($adminA->fresh(), $kasus, $siswa))->toBeTrue();
    expect($policy->view($adminB->fresh(), $kasus, $siswa))->toBeFalse();
});

it('view grants access to the terkait siswa themselves', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa->user_id = $user->id;
    $siswa->save();
    $siswa->person->update(['user_id' => $user->id]);

    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id]);

    expect((new KasusPolicy)->view($user->fresh(), $kasus, $siswa))->toBeTrue();
});

it('view denies access to a user with no relation to the kasus at all', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id]);
    $userAsing = User::factory()->create(['lembaga_id' => $lembaga->id]);

    expect((new KasusPolicy)->view($userAsing, $kasus, $siswa))->toBeFalse();
});

it('kelolaSesiTugas mirrors isKonselor exactly', function () {
    $lembaga = Lembaga::factory()->create();
    $karyawanKonselor = Karyawan::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $karyawanKonselor->user_id = $user->id;
    $karyawanKonselor->save();
    $karyawanKonselor->person->update(['user_id' => $user->id]);

    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'konselor_karyawan_id' => $karyawanKonselor->id]);

    expect((new KasusPolicy)->kelolaSesiTugas($user->fresh(), $kasus))->toBeTrue();
});

it('resolves KasusPolicy via auto-discovery or explicit registration', function () {
    $kasus = Kasus::factory()->create();
    expect(Gate::getPolicyFor($kasus))->toBeInstanceOf(KasusPolicy::class);
});

it('viewAny returns true when user has kasus.view capability, regardless of konselor history', function () {
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_bk', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kasus.view']);

    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru_bk');

    expect((new KasusPolicy)->viewAny($user))->toBeTrue();
});

it('viewAny returns true when karyawan is konselor on at least one kasus, without kasus.view capability', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $jenis = JenisKaryawanMaster::factory()->create(['is_konselor' => true]);
    $karyawan = Karyawan::factory()->create([
        'user_id' => $user->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Karyawan Pool',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);
    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::Ditugaskan, 'konselor_karyawan_id' => $karyawan->id,
    ]);

    expect((new KasusPolicy)->viewAny($user))->toBeTrue();
});

it('viewAny returns false for karyawan with no kasus.view capability and never assigned as konselor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenis = JenisKaryawanMaster::factory()->create(['is_konselor' => false]);
    Karyawan::factory()->create([
        'user_id' => $user->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Satpam',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);

    expect((new KasusPolicy)->viewAny($user))->toBeFalse();
});

it('viewAny returns true for karyawan whose only konselor assignment is on a Selesai kasus (no status filter)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => null]);
    $jenis = JenisKaryawanMaster::factory()->create(['is_konselor' => true]);
    $karyawan = Karyawan::factory()->create([
        'user_id' => $user->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenis->id, 'nama' => 'Karyawan Pool Selesai',
        'nik' => fake()->unique()->numerify('################'), 'status_aktif' => 'aktif',
    ]);
    Kasus::create([
        'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
        'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
        'status' => StatusKasus::Selesai, 'konselor_karyawan_id' => $karyawan->id,
    ]);

    expect((new KasusPolicy)->viewAny($user))->toBeTrue();
});
