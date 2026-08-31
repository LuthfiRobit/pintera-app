<?php

use App\Domains\Identity\Models\Person;
use App\Domains\Identity\Services\PersonDuplicateFinder;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

it('finds a candidate with matching nama_lengkap and tanggal_lahir', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $this->actingAs($admin);

    Person::factory()->create(['yayasan_id' => $yayasan->id, 'nama_lengkap' => 'Rahmat Hidayat', 'tanggal_lahir' => '2000-01-01']);

    $candidates = app(PersonDuplicateFinder::class)->find('Rahmat Hidayat', '2000-01-01');

    expect($candidates)->toHaveCount(1);
});

it('returns no candidates when nothing matches', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $this->actingAs($admin);

    $candidates = app(PersonDuplicateFinder::class)->find('Nama Tidak Ada', null);

    expect($candidates)->toBeEmpty();
});
