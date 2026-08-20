<?php

use App\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusEvaluasi;
use App\Models\User;

it('creates a kasus_evaluasi row linked to a kasus and its author', function () {
    $user = User::factory()->create();
    $kasus = Kasus::factory()->create();
    $evaluasi = KasusEvaluasi::factory()->create([
        'kasus_id' => $kasus->id,
        'dibuat_oleh_user_id' => $user->id,
        'keputusan' => 'eskalasi',
    ]);

    expect($evaluasi->kasus->id)->toBe($kasus->id);
    expect($evaluasi->dibuatOleh->id)->toBe($user->id);
    expect($kasus->evaluasi)->toHaveCount(1);
});

it('has the new StatusKasus cases with labels', function () {
    expect(StatusKasus::Berjalan->label())->toBe('Berjalan');
    expect(StatusKasus::Eskalasi->label())->toBe('Eskalasi');
    expect(StatusKasus::Selesai->label())->toBe('Selesai');
});
