<?php

use App\Domains\Akademik\Actions\PolaJam\CreateJamPelajaranAction;
use App\Domains\Akademik\DataTransferObjects\JamPelajaranData;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates one slot per requested hari', function () {
    $polaJam = PolaJam::factory()->create();

    $result = (new CreateJamPelajaranAction)->execute(new JamPelajaranData(
        polaJamId: $polaJam->id,
        hari: ['senin', 'selasa'],
        urutan: 1,
        label: 'Jam ke-1',
        jamMulai: '07:00',
        jamSelesai: '07:45',
        isPelajaran: true,
    ));

    expect($result['berhasil'])->toBe(['senin', 'selasa'])
        ->and($result['dilewati'])->toBe([])
        ->and(JamPelajaran::where('pola_jam_id', $polaJam->id)->count())->toBe(2);
});

it('skips a hari whose urutan slot is already taken and reports it', function () {
    $polaJam = PolaJam::factory()->create();
    JamPelajaran::factory()->create(['pola_jam_id' => $polaJam->id, 'hari' => 'senin', 'urutan' => 1]);

    $result = (new CreateJamPelajaranAction)->execute(new JamPelajaranData(
        polaJamId: $polaJam->id,
        hari: ['senin', 'selasa'],
        urutan: 1,
        label: 'Jam ke-1',
        jamMulai: '07:00',
        jamSelesai: '07:45',
        isPelajaran: true,
    ));

    expect($result['berhasil'])->toBe(['selasa'])
        ->and($result['dilewati'])->toBe(['senin']);
});
