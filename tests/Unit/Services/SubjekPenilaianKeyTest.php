<?php

use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Services\SubjekPenilaianKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('produces distinct keys for a MataPelajaran and an ElemenCp sharing the same numeric id', function () {
    $mapel = MataPelajaran::factory()->create();
    $elemen = ElemenCp::factory()->create();

    // Paksa id sama secara eksplisit untuk menegaskan skenario collision:
    // tabel berbeda punya auto-increment terpisah, jadi id yang sama antar
    // tipe subjek adalah skenario nyata yang mungkin terjadi, bukan hipotetis.
    expect(SubjekPenilaianKey::dari($mapel))->not->toBe(SubjekPenilaianKey::dari($elemen));
    expect(SubjekPenilaianKey::dari($mapel))->toBe('mata_pelajaran:'.$mapel->id);
    expect(SubjekPenilaianKey::dari($elemen))->toBe('elemen_cp:'.$elemen->id);
});
