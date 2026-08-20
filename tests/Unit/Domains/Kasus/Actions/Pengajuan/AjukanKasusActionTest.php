<?php

use App\Domains\Kasus\Actions\Pengajuan\AjukanKasusAction;
use App\Domains\Kasus\DataTransferObjects\AjukanKasusData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a kasus submitted by guru with diajukan_oleh_guru_id set and status diajukan', function () {
    Notification::fake();

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $kasus = (new AjukanKasusAction)->execute(
        $siswa,
        new AjukanKasusData(siswaId: $siswa->id, kategoriMasalah: 'Akademik', deskripsi: 'Nilai turun drastis', lampiranPath: null),
        isGuru: true,
        guruId: $guru->id,
    );

    expect($kasus->status)->toBe(StatusKasus::Diajukan)
        ->and($kasus->diajukan_oleh_guru_id)->toBe($guru->id)
        ->and($kasus->diajukan_oleh_orang_tua_id)->toBeNull()
        ->and(Kasus::where('siswa_id', $siswa->id)->count())->toBe(1);
});
