<?php

// tests/Feature/Console/BackfillTagihanPersonIdTest.php
//
// tagihan.person_id is NOT NULL as of migration 2026_09_01_000002 (this same
// branch), so a row with person_id => null can no longer be constructed via
// any insert path against this schema -- the command's own null-handling
// branches (skip-and-log an unresolvable row) are therefore untestable here,
// forever, since Pest's RefreshDatabase always migrates to the latest
// schema. The command itself is still kept (see its class comment) for a
// fresh environment that hasn't applied that migration yet. What remains
// testable in this schema state is the command's no-op/idempotent behavior.

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Siswa;

it('is a no-op and exits successfully when every tagihan already has a person_id', function () {
    $siswa = Siswa::factory()->create();
    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id]);

    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();
});

it('is idempotent -- running twice does not error', function () {
    $siswa = Siswa::factory()->create();
    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id]);

    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();
    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();
});
