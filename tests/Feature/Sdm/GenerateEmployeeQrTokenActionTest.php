<?php
// tests/Feature/Sdm/GenerateEmployeeQrTokenActionTest.php

use App\Domains\Sdm\Actions\GenerateEmployeeQrTokenAction;
use App\Domains\Sdm\Models\EmployeeQrCode;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('generates a random unique active token for a pegawai with no prior token', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $qr = app(GenerateEmployeeQrTokenAction::class)->execute($guru);

    expect($qr->token)->toHaveLength(48);
    expect($qr->is_active)->toBeTrue();
    expect($qr->token)->not->toContain((string) $guru->nik);
});

it('deactivates the previous token when regenerating for the same pegawai', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $action = app(GenerateEmployeeQrTokenAction::class);

    $first = $action->execute($guru);
    $second = $action->execute($guru);

    expect($first->fresh()->is_active)->toBeFalse();
    expect($second->is_active)->toBeTrue();
    expect(EmployeeQrCode::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->where('is_active', true)->count())->toBe(1);
});
