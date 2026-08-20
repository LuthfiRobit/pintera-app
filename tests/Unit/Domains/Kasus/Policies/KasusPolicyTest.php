<?php

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Policies\KasusPolicy;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('isKonselor returns true for the assigned konselor guru, false for another guru', function () {
    $lembaga = Lembaga::factory()->create();
    $guruKonselor = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruLain = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKonselor = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruKonselor->user_id = $userKonselor->id;
    $guruKonselor->save();

    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'konselor_guru_id' => $guruKonselor->id]);

    $userB = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruLain->user_id = $userB->id;
    $guruLain->save();

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

    $userBukanKontakUtama = User::factory()->create();
    $bukanKontakUtama->user_id = $userBukanKontakUtama->id;
    $bukanKontakUtama->save();

    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id]);

    $policy = new KasusPolicy;

    expect($policy->view($userKontakUtama->fresh(), $kasus, $siswa))->toBeTrue();
    expect($policy->view($userBukanKontakUtama->fresh(), $kasus, $siswa))->toBeFalse();
});

it('view grants access to a triase admin within the same lembaga but not a different lembaga', function () {
    Role::firstOrCreate(['name' => 'admin_lembaga', 'guard_name' => 'web', 'scope_level' => 'lembaga']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'kasus.triase', 'guard_name' => 'web']);

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

    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'konselor_karyawan_id' => $karyawanKonselor->id]);

    expect((new KasusPolicy)->kelolaSesiTugas($user->fresh(), $kasus))->toBeTrue();
});

it('resolves KasusPolicy via auto-discovery or explicit registration', function () {
    $kasus = Kasus::factory()->create();
    expect(Gate::getPolicyFor($kasus))->toBeInstanceOf(KasusPolicy::class);
});
