<?php

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

it('resolves $user->guru through the person hasOneThrough chain', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $user->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => $person->id]);

    expect($user->fresh()->guru?->id)->toBe($guru->id);
});

it('resolves $user->karyawan, orangTua, and siswa the same way, and returns null when absent', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $person = Person::factory()->create(['yayasan_id' => $yayasan->id, 'user_id' => $user->id]);
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id, 'person_id' => $person->id]);

    expect($user->fresh()->karyawan?->id)->toBe($karyawan->id);
    expect($user->fresh()->orangTua)->toBeNull();
    expect($user->fresh()->siswa)->toBeNull();
});
