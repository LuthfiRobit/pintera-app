<?php

use App\Models\Asesmen;
use App\Models\NilaiSiswa;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can record a score and relate to asesmen and siswa', function () {
    $nilai = NilaiSiswa::factory()->create([
        'skor' => 88.5,
        'catatan' => 'Sangat memahami materi, pertahankan.',
    ]);

    expect($nilai->skor)->toEqual(88.5);
    expect($nilai->asesmen)->toBeInstanceOf(Asesmen::class);
    expect($nilai->siswa)->toBeInstanceOf(Siswa::class);
    expect($nilai->catatan)->toBe('Sangat memahami materi, pertahankan.');
});
