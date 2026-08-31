<?php

use App\Domains\Identity\Models\Person;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Yayasan;
use App\Services\AkunKaryawanGenerator;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'pegawai_lembaga', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    Role::firstOrCreate(['name' => 'pegawai_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
});

it('creates a Person and links karyawan.person_id when generating a karyawan account', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();

    $karyawan = app(AkunKaryawanGenerator::class)->buat(
        nama: 'Karyawan Baru',
        nik: '6666666666666666',
        yayasanId: $yayasan->id,
        lembagaId: $lembaga->id,
        jenisKaryawanId: $jenisKaryawan->id,
        noHp: '081200000001',
        email: 'karyawan.baru@example.test',
    );

    expect($karyawan->person_id)->not->toBeNull();
    expect($karyawan->nama)->toBe('Karyawan Baru');
    expect($karyawan->nik)->toBe('6666666666666666');

    $person = Person::withoutGlobalScopes()->find($karyawan->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
    expect($person->nama_lengkap)->toBe('Karyawan Baru');
    expect($person->user_id)->toBe($karyawan->user_id);
});

it('creates a pool karyawan with correct yayasan scoping', function () {
    $yayasan = Yayasan::factory()->create();
    $jenisKaryawan = JenisKaryawanMaster::factory()->create();

    $karyawan = app(AkunKaryawanGenerator::class)->buat(
        nama: 'Karyawan Pool',
        nik: '6666666666666667',
        yayasanId: $yayasan->id,
        lembagaId: null,
        jenisKaryawanId: $jenisKaryawan->id,
    );

    expect($karyawan->person_id)->not->toBeNull();
    $person = Person::withoutGlobalScopes()->find($karyawan->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
    expect($person->user_id)->toBe($karyawan->user_id);
});
