<?php

use App\Domains\Kasus\Actions\Sesi\JadwalkanSesiAction;
use App\Domains\Kasus\DataTransferObjects\JadwalkanSesiData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusSesi;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates sesi rows and moves kasus from ditugaskan to berjalan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::Ditugaskan]);

    $created = (new JadwalkanSesiAction)->execute($kasus, new JadwalkanSesiData(sesi: [
        ['dijadwalkan_pada' => now()->addDay()->toDateTimeString(), 'peserta' => 'siswa', 'lokasi_mode' => 'daring'],
        ['dijadwalkan_pada' => now()->addDays(2)->toDateTimeString(), 'peserta' => 'orang_tua', 'lokasi_mode' => 'tatap_muka'],
    ]));

    expect($created)->toHaveCount(2)
        ->and($kasus->fresh()->status)->toBe(StatusKasus::Berjalan)
        ->and(KasusSesi::where('kasus_id', $kasus->id)->count())->toBe(2);
});

it('does not change kasus status if it is already berjalan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::Berjalan]);

    (new JadwalkanSesiAction)->execute($kasus, new JadwalkanSesiData(sesi: [
        ['dijadwalkan_pada' => now()->addDay()->toDateTimeString(), 'peserta' => 'siswa', 'lokasi_mode' => 'daring'],
    ]));

    expect($kasus->fresh()->status)->toBe(StatusKasus::Berjalan);
});
