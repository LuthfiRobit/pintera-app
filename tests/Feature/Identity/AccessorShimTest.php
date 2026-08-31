<?php

use App\Domains\Identity\Models\Person;
use App\Models\CalonMurid;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Yayasan;

it('proxies Guru identity reads to the linked Person', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $person = Person::factory()->create([
        'yayasan_id' => $yayasan->id,
        'nama_lengkap' => 'Guru Satu',
        'nik' => '4444444444444444',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '1985-05-15',
        'agama' => 'Islam',
        'no_hp' => '081234567890',
        'email' => 'guru@example.test',
    ]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $person->id]);

    expect($guru->nama)->toBe('Guru Satu');
    expect($guru->nik)->toBe('4444444444444444');
    expect($guru->jenis_kelamin)->toBe('L');
    expect($guru->tempat_lahir)->toBe('Bandung');
    expect($guru->tanggal_lahir?->format('Y-m-d'))->toBe('1985-05-15');
    expect($guru->agama)->toBe('Islam');
    expect($guru->no_hp)->toBe('081234567890');
    expect($guru->email)->toBe('guru@example.test');
    expect($guru->routeNotificationForMail())->toBe('guru@example.test');
});

it('proxies Karyawan identity reads to the linked Person', function () {
    $yayasan = Yayasan::factory()->create();
    $person = Person::factory()->create([
        'yayasan_id' => $yayasan->id,
        'nama_lengkap' => 'Karyawan Satu',
        'nik' => '5555555555555555',
        'no_hp' => '081298765432',
        'email' => 'karyawan@example.test',
    ]);
    $karyawan = Karyawan::factory()->create(['yayasan_id' => $yayasan->id, 'person_id' => $person->id]);

    expect($karyawan->nama)->toBe('Karyawan Satu');
    expect($karyawan->nik)->toBe('5555555555555555');
    expect($karyawan->no_hp)->toBe('081298765432');
    expect($karyawan->email)->toBe('karyawan@example.test');
});

it('proxies OrangTua and Siswa nama_lengkap reads to the linked Person', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $personOrtu = Person::factory()->create([
        'yayasan_id' => $yayasan->id,
        'nama_lengkap' => 'Ortu Satu',
        'nik' => '6666666666666666',
        'no_hp' => '081211112222',
        'email' => 'ortu@example.test',
        'alamat_jalan' => 'Jl. Kenanga No. 10',
    ]);
    $personSiswa = Person::factory()->create([
        'yayasan_id' => $yayasan->id,
        'nama_lengkap' => 'Siswa Satu',
        'jenis_kelamin' => 'P',
        'tempat_lahir' => 'Surabaya',
        'tanggal_lahir' => '2010-10-20',
        'agama' => 'Islam',
    ]);

    $ortu = OrangTua::factory()->create(['person_id' => $personOrtu->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $personSiswa->id]);

    expect($ortu->nama_lengkap)->toBe('Ortu Satu');
    expect($ortu->nik)->toBe('6666666666666666');
    expect($ortu->no_hp)->toBe('081211112222');
    expect($ortu->email)->toBe('ortu@example.test');
    expect($ortu->alamat)->toBe('Jl. Kenanga No. 10');
    expect($ortu->routeNotificationForMail())->toBe('ortu@example.test');
    expect($ortu->routeNotificationForWhatsapp())->toBe('081211112222');

    expect($siswa->nama_lengkap)->toBe('Siswa Satu');
    expect($siswa->jenis_kelamin)->toBe('P');
    expect($siswa->tempat_lahir)->toBe('Surabaya');
    expect($siswa->tanggal_lahir?->format('Y-m-d'))->toBe('2010-10-20');
    expect($siswa->agama)->toBe('Islam');
});

it('proxies CalonMurid with its differently-named contact fields', function () {
    $yayasan = Yayasan::factory()->create();
    $person = Person::factory()->create([
        'yayasan_id' => $yayasan->id,
        'nama_lengkap' => 'Calon Satu',
        'nik' => '7777777777777777',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Medan',
        'tanggal_lahir' => '2012-12-12',
        'agama' => 'Islam',
        'no_hp' => '081234567890',
        'email' => 'calon@example.test',
    ]);
    $calon = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id, 'person_id' => $person->id]);

    expect($calon->nama_lengkap)->toBe('Calon Satu');
    expect($calon->nik)->toBe('7777777777777777');
    expect($calon->jenis_kelamin)->toBe('L');
    expect($calon->tempat_lahir)->toBe('Medan');
    expect($calon->tanggal_lahir?->format('Y-m-d'))->toBe('2012-12-12');
    expect($calon->agama)->toBe('Islam');
    expect($calon->no_telepon)->toBe('081234567890');
    expect($calon->email_kontak)->toBe('calon@example.test');
});
