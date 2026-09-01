<?php

// tests/Feature/Console/VerifyTagihanPersonIdTest.php
//
// See BackfillTagihanPersonIdTest.php's header comment: the "exits 1 when a
// null exists" case can no longer be constructed under this branch's own
// NOT NULL constraint (migration 2026_09_01_000002) and is not tested here
// for the same reason. Only the guaranteed-clean case remains testable.

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Siswa;

it('exits 0 when no tagihan.person_id is null', function () {
    $siswa = Siswa::factory()->create();
    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'person_id' => $siswa->person_id]);

    $this->artisan('keuangan:verify-tagihan-person-id')->assertSuccessful();
});
