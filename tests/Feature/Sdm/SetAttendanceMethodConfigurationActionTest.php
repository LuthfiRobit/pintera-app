<?php
// tests/Feature/Sdm/SetAttendanceMethodConfigurationActionTest.php

use App\Domains\Sdm\Actions\SetAttendanceMethodConfigurationAction;
use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('creates a yayasan-level default configuration when lembaga_id is null', function () {
    $yayasan = Yayasan::factory()->create();

    $config = app(SetAttendanceMethodConfigurationAction::class)->execute($yayasan->id, null, AttendanceMethod::Qr, true);

    expect($config->lembaga_id)->toBeNull();
    expect($config->is_enabled)->toBeTrue();
});

it('updates an existing configuration instead of creating a duplicate row', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $action = app(SetAttendanceMethodConfigurationAction::class);

    $action->execute($yayasan->id, $lembaga->id, AttendanceMethod::Qr, true);
    $action->execute($yayasan->id, $lembaga->id, AttendanceMethod::Qr, false);

    expect(\App\Domains\Sdm\Models\AttendanceMethodConfiguration::where('lembaga_id', $lembaga->id)->count())->toBe(1);
    expect(\App\Domains\Sdm\Models\AttendanceMethodConfiguration::where('lembaga_id', $lembaga->id)->first()->is_enabled)->toBeFalse();
});
