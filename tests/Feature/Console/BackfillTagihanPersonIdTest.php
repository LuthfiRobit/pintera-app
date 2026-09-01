<?php

// tests/Feature/Console/BackfillTagihanPersonIdTest.php

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use App\Models\Siswa;

it('backfills person_id for Pendaftaran-tagihable rows via calonMurid', function () {
    $calonMurid = CalonMurid::factory()->create();
    $pendaftaran = Pendaftaran::factory()->create(['calon_murid_id' => $calonMurid->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Pendaftaran::class,
        'tagihable_id' => $pendaftaran->id,
        'person_id' => null,
    ]);

    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();

    expect($tagihan->fresh()->person_id)->toBe($calonMurid->person_id);
});

it('backfills person_id for Siswa-tagihable rows directly', function () {
    $siswa = Siswa::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class,
        'tagihable_id' => $siswa->id,
        'person_id' => null,
    ]);

    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();

    expect($tagihan->fresh()->person_id)->toBe($siswa->person_id);
});

it('skips and reports, but does not throw, when tagihable cannot be resolved', function () {
    $tagihan = Tagihan::factory()->create([
        'tagihable_type' => Pendaftaran::class,
        'tagihable_id' => 999999, // does not exist
        'person_id' => null,
    ]);

    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();

    expect($tagihan->fresh()->person_id)->toBeNull();
});

it('is idempotent -- running twice does not error and does not reprocess already-backfilled rows', function () {
    $siswa = Siswa::factory()->create();
    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'person_id' => null]);

    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();
    $this->artisan('keuangan:backfill-tagihan-person-id')->assertSuccessful();
});
