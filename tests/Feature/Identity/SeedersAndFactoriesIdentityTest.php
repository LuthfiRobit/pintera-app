<?php

use App\Domains\Identity\Models\Person;
use App\Models\CalonMurid;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Yayasan;
use Database\Seeders\EssentialUserSeeder;
use Database\Seeders\GuruSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;

it('creates and links Person when using GuruFactory', function () {
    $guru = Guru::factory()->create(['nama' => 'Budi Guru', 'nik' => '3201234567890001']);

    expect($guru->person_id)->not->toBeNull();
    expect($guru->person)->toBeInstanceOf(Person::class);
    expect($guru->nama)->toBe('Budi Guru');
    expect($guru->nik)->toBe('3201234567890001');
});

it('creates and links Person when using KaryawanFactory', function () {
    $karyawan = Karyawan::factory()->create(['nama' => 'Siti Karyawan', 'nik' => '3201234567890002']);

    expect($karyawan->person_id)->not->toBeNull();
    expect($karyawan->person)->toBeInstanceOf(Person::class);
    expect($karyawan->nama)->toBe('Siti Karyawan');
    expect($karyawan->nik)->toBe('3201234567890002');
});

it('creates and links Person when using OrangTuaFactory', function () {
    $orangTua = OrangTua::factory()->create(['nama_lengkap' => 'Pak Joko', 'nik' => '3201234567890003']);

    expect($orangTua->person_id)->not->toBeNull();
    expect($orangTua->person)->toBeInstanceOf(Person::class);
    expect($orangTua->nama_lengkap)->toBe('Pak Joko');
    expect($orangTua->nik)->toBe('3201234567890003');
});

it('creates and links Person when using SiswaFactory', function () {
    $siswa = Siswa::factory()->create(['nama_lengkap' => 'Ani Siswa']);

    expect($siswa->person_id)->not->toBeNull();
    expect($siswa->person)->toBeInstanceOf(Person::class);
    expect($siswa->nama_lengkap)->toBe('Ani Siswa');
});

it('creates and links Person when using CalonMuridFactory', function () {
    $calonMurid = CalonMurid::factory()->create(['nama_lengkap' => 'Budi Calon', 'nik' => '3201234567890005']);

    expect($calonMurid->person_id)->not->toBeNull();
    expect($calonMurid->person)->toBeInstanceOf(Person::class);
    expect($calonMurid->nama_lengkap)->toBe('Budi Calon');
    expect($calonMurid->nik)->toBe('3201234567890005');
});

it('seeds persons correctly when running GuruSeeder and EssentialUserSeeder', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'npsn' => '20223333']);

    $this->seed(RolePermissionSeeder::class);
    $this->seed(EssentialUserSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(GuruSeeder::class);

    $gurus = Guru::where('lembaga_id', $lembaga->id)->get();
    expect($gurus->isNotEmpty())->toBeTrue();
    foreach ($gurus as $guru) {
        expect($guru->person_id)->not->toBeNull();
        expect($guru->person)->not->toBeNull();
    }
});
