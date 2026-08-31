<?php

use App\Domains\Identity\Models\Person;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

it('computes nik_hash on save', function () {
    $person = Person::factory()->create(['nik' => '1234567890123456']);

    expect($person->nik_hash)->toBe(hash('sha256', '1234567890123456'));
});

it('scopes persons to the acting yayasan_id like other tenant models', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);

    Person::factory()->create(['yayasan_id' => $yayasanA->id]);
    Person::factory()->create(['yayasan_id' => $yayasanB->id]);

    $admin = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $this->actingAs($admin);

    expect(Person::count())->toBe(1);
});
