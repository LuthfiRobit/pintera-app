<?php

// tests/Feature/Console/VerifyTagihanPersonIdTest.php

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Siswa;

it('exits 0 when no tagihan.person_id is null', function () {
    $siswa = Siswa::factory()->create();
    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'person_id' => $siswa->person_id]);

    $this->artisan('keuangan:verify-tagihan-person-id')->assertSuccessful();
});

it('exits 1 and lists offending ids when any tagihan.person_id is null', function () {
    $tagihan = Tagihan::factory()->create(['person_id' => null]);

    $this->artisan('keuangan:verify-tagihan-person-id')->assertFailed();
});
