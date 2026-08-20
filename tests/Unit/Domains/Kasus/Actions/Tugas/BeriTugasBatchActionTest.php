<?php

use App\Domains\Kasus\Actions\Tugas\BeriTugasBatchAction;
use App\Domains\Kasus\DataTransferObjects\BeriTugasBatchData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Services\TugasBatchGenerator;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates one kasus_tugas row per day for a harian batch and moves kasus to berjalan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $kasus = Kasus::factory()->create(['lembaga_id' => $lembaga->id, 'siswa_id' => $siswa->id, 'status' => StatusKasus::Ditugaskan]);

    $created = (new BeriTugasBatchAction(new TugasBatchGenerator))->execute($kasus, new BeriTugasBatchData(
        judul: 'Jurnal Harian',
        instruksi: 'Tulis 1 paragraf refleksi tiap hari',
        frekuensi: 'harian',
        tanggalMulai: now()->toDateString(),
        tanggalSelesai: now()->addDays(2)->toDateString(),
        tanggalPengumpulanBulananRaw: null,
    ));

    expect($created)->toHaveCount(3)
        ->and($kasus->fresh()->status)->toBe(StatusKasus::Berjalan)
        ->and(KasusTugas::where('kasus_id', $kasus->id)->where('batch_id', $created->first()->batch_id)->count())->toBe(3);
});
