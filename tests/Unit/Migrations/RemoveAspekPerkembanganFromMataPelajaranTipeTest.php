<?php
// tests/Unit/Migrations/RemoveAspekPerkembanganFromMataPelajaranTipeTest.php

use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('rejects inserting aspek_perkembangan into mata_pelajaran.tipe after the migration', function () {
    expect(fn () => DB::table('mata_pelajaran')->insert([
        'lembaga_id' => Lembaga::factory()->create()->id,
        'kode' => 'TEST-01',
        'nama' => 'Uji Enum',
        'no_urut' => 1,
        'tipe' => 'aspek_perkembangan',
        'status' => 'aktif',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('still allows mapel as a valid tipe value', function () {
    $lembaga = Lembaga::factory()->create();

    $mapel = MataPelajaran::create([
        'lembaga_id' => $lembaga->id,
        'kode' => 'TEST-02',
        'nama' => 'Uji Enum Valid',
        'no_urut' => 1,
        'tipe' => 'mapel',
        'status' => 'aktif',
    ]);

    expect($mapel->fresh()->tipe)->toBe(\App\Enums\TipeMataPelajaran::Mapel);
});
