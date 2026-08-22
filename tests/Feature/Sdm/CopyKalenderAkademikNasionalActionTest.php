<?php
// tests/Feature/Sdm/CopyKalenderAkademikNasionalActionTest.php

use App\Domains\Akademik\Models\KalenderAkademik;
use App\Domains\Sdm\Actions\CopyKalenderAkademikNasionalAction;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Models\Yayasan;

it('copies a national academic calendar entry into an independent SDM calendar entry', function () {
    $yayasan = Yayasan::factory()->create();
    $entriAkademik = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);

    $hasil = app(CopyKalenderAkademikNasionalAction::class)->execute($yayasan->id, [$entriAkademik->id]);

    expect($hasil)->toHaveCount(1);
    expect(KalenderKerjaSdm::where('yayasan_id', $yayasan->id)->whereNull('lembaga_id')->where('nama', 'Hari Kemerdekaan RI')->exists())->toBeTrue();
});

it('does not create a duplicate when the same entry is copied twice', function () {
    $yayasan = Yayasan::factory()->create();
    $entriAkademik = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);
    $action = app(CopyKalenderAkademikNasionalAction::class);

    $action->execute($yayasan->id, [$entriAkademik->id]);
    $hasilKedua = $action->execute($yayasan->id, [$entriAkademik->id]);

    expect($hasilKedua)->toHaveCount(0);
    expect(KalenderKerjaSdm::where('yayasan_id', $yayasan->id)->where('nama', 'Hari Kemerdekaan RI')->count())->toBe(1);
});

it('keeps the copied SDM entry independent from the original academic entry after copying', function () {
    $yayasan = Yayasan::factory()->create();
    $entriAkademik = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);
    app(CopyKalenderAkademikNasionalAction::class)->execute($yayasan->id, [$entriAkademik->id]);

    $entriAkademik->update(['nama' => 'Nama Diubah di Akademik']);

    $entriSdm = KalenderKerjaSdm::where('yayasan_id', $yayasan->id)->where('nama', 'Hari Kemerdekaan RI')->first();
    expect($entriSdm)->not->toBeNull();
});
