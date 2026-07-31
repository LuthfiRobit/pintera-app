<?php
// tests/Feature/Console/BackfillAkunLamaTest.php

use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Hash;

it('flags every guru account still needing a forced password change', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id, 'must_change_password' => false]);
    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);

    $this->artisan('akun:backfill-lama')->assertExitCode(0);

    expect($guruUser->fresh()->must_change_password)->toBeTrue();
});

it('creates a login account for every siswa that does not have one yet', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMPPRM']);
    $siswa = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id, 'nis' => '2020001', 'status' => StatusSiswa::Aktif->value,
    ]); // no user_id

    $this->artisan('akun:backfill-lama')->assertExitCode(0);

    $freshSiswa = $siswa->fresh();
    expect($freshSiswa->user_id)->not->toBeNull();
    expect($freshSiswa->user->username)->toBe('SMPPRM-2020001');
    expect(Hash::check('2020001', $freshSiswa->user->password))->toBeTrue();
});

it('does not touch a siswa that already has a linked account', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $existingUser = User::factory()->create(['lembaga_id' => $lembaga->id, 'username' => 'ALREADY-SET']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $existingUser->id]);

    $this->artisan('akun:backfill-lama')->assertExitCode(0);

    expect($siswa->fresh()->user_id)->toBe($existingUser->id);
    expect($existingUser->fresh()->username)->toBe('ALREADY-SET');
});

it('does not create an account for a non-active siswa', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMPPRM']);
    $alumnus = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id, 'nis' => '2020003', 'status' => StatusSiswa::Lulus->value,
    ]);

    $this->artisan('akun:backfill-lama')->assertExitCode(0);

    expect($alumnus->fresh()->user_id)->toBeNull();
});

it('continues to the next siswa when one account creation fails, instead of aborting the whole run', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMPPRM']);

    // This siswa's NIS collides with an existing user's username, so account creation
    // for it fails on a unique constraint — the command must catch that per-row and
    // still process the siswa after it.
    User::factory()->create(['lembaga_id' => $lembaga->id, 'username' => 'SMPPRM-2020004']);
    $siswaGagal = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id, 'nis' => '2020004', 'status' => StatusSiswa::Aktif->value,
    ]);
    $siswaBerhasil = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id, 'nis' => '2020005', 'status' => StatusSiswa::Aktif->value,
    ]);

    $this->artisan('akun:backfill-lama')->assertExitCode(0);

    expect($siswaGagal->fresh()->user_id)->toBeNull();
    expect($siswaBerhasil->fresh()->user_id)->not->toBeNull();
});

it('skips flagging guru when --siswa-only is passed, so guru who already changed their password are left alone', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMPPRM']);
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id, 'must_change_password' => false]);
    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);
    $siswa = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id, 'nis' => '2020006', 'status' => StatusSiswa::Aktif->value,
    ]);

    $this->artisan('akun:backfill-lama --siswa-only')->assertExitCode(0);

    expect($guruUser->fresh()->must_change_password)->toBeFalse();
    expect($siswa->fresh()->user_id)->not->toBeNull();
});

it('skips creating siswa accounts when --guru-only is passed', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMPPRM']);
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id, 'must_change_password' => false]);
    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);
    $siswa = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id, 'nis' => '2020007', 'status' => StatusSiswa::Aktif->value,
    ]);

    $this->artisan('akun:backfill-lama --guru-only')->assertExitCode(0);

    expect($guruUser->fresh()->must_change_password)->toBeTrue();
    expect($siswa->fresh()->user_id)->toBeNull();
});

it('changes nothing in --dry-run mode', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMPPRM']);
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id, 'must_change_password' => false]);
    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $guruUser->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nis' => '2020002']);

    $this->artisan('akun:backfill-lama --dry-run')->assertExitCode(0);

    expect($guruUser->fresh()->must_change_password)->toBeFalse();
    expect($siswa->fresh()->user_id)->toBeNull();
});
