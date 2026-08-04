<?php

use App\Models\Guru;
use App\Models\OrangTua;

it('routes mail notifications for Guru to the guru table email column', function () {
    $guru = Guru::factory()->create(['email' => 'guru.test@example.test']);

    expect($guru->routeNotificationForMail())->toBe('guru.test@example.test');
});

it('routes mail notifications for OrangTua to the orang_tua table email column, not the user email', function () {
    $orangTua = OrangTua::factory()->create(['email' => 'ortu.test@example.test']);

    expect($orangTua->user->email)->toBeNull();
    expect($orangTua->routeNotificationForMail())->toBe('ortu.test@example.test');
});
