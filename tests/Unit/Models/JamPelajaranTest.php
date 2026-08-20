<?php

use App\Enums\Hari;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('belongs to a pola jam and casts hari to the enum', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    $jam = JamPelajaran::create([
        'pola_jam_id' => $pola->id,
        'hari' => Hari::Senin->value,
        'urutan' => 1,
        'label' => 'Upacara',
        'jam_mulai' => '07:00',
        'jam_selesai' => '07:35',
        'is_pelajaran' => false,
    ]);

    expect($jam->fresh()->hari)->toBe(Hari::Senin);
    expect($jam->fresh()->polaJam->id)->toBe($pola->id);
    expect($jam->fresh()->is_pelajaran)->toBeFalse();
});

it('supports a different set of slots on Monday vs other days within the same pola', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);

    JamPelajaran::create(['pola_jam_id' => $pola->id, 'hari' => Hari::Senin->value, 'urutan' => 1, 'label' => 'Upacara', 'jam_mulai' => '07:00', 'jam_selesai' => '07:35', 'is_pelajaran' => false]);
    JamPelajaran::create(['pola_jam_id' => $pola->id, 'hari' => Hari::Senin->value, 'urutan' => 2, 'label' => 'Jam ke-1', 'jam_mulai' => '07:35', 'jam_selesai' => '08:10', 'is_pelajaran' => true]);
    JamPelajaran::create(['pola_jam_id' => $pola->id, 'hari' => Hari::Selasa->value, 'urutan' => 1, 'label' => 'Jam ke-1', 'jam_mulai' => '07:00', 'jam_selesai' => '07:35', 'is_pelajaran' => true]);

    expect($pola->fresh()->jamPelajaran()->where('hari', Hari::Senin->value)->count())->toBe(2);
    expect($pola->fresh()->jamPelajaran()->where('hari', Hari::Selasa->value)->count())->toBe(1);
});
