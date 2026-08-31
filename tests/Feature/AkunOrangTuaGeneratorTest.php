<?php

use App\Domains\Identity\Models\Person;
use App\Models\Role;
use App\Models\Yayasan;
use App\Services\AkunOrangTuaGenerator;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
});

it('creates a Person and links orang_tua.person_id when generating an orang tua account', function () {
    $yayasan = Yayasan::factory()->create();

    $orangTua = app(AkunOrangTuaGenerator::class)->buat(
        namaLengkap: 'Orang Tua Baru',
        nik: '7777777777777777',
        noHp: '081200000002',
        email: 'ortu.baru@example.test',
        alamat: 'Jl. Contoh No. 1',
        pekerjaan: 'Wiraswasta',
        yayasanId: $yayasan->id,
    );

    expect($orangTua->person_id)->not->toBeNull();
    expect($orangTua->nama_lengkap)->toBe('Orang Tua Baru');
    expect($orangTua->nik)->toBe('7777777777777777');
    expect($orangTua->no_hp)->toBe('081200000002');
    expect($orangTua->email)->toBe('ortu.baru@example.test');
    expect($orangTua->alamat)->toBe('Jl. Contoh No. 1');
    expect($orangTua->pekerjaan)->toBe('Wiraswasta');

    $person = Person::withoutGlobalScopes()->find($orangTua->person_id);
    expect($person->yayasan_id)->toBe($yayasan->id);
    expect($person->nama_lengkap)->toBe('Orang Tua Baru');
    expect($person->user_id)->toBe($orangTua->user_id);
});
