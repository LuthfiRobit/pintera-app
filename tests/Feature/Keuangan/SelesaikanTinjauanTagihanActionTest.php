<?php

use App\Domains\Keuangan\Actions\Tagihan\SelesaikanTinjauanTagihanAction;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('clears the flag and reason without touching any nominal column', function () {
    $tagihan = Tagihan::factory()->create([
        'net_amount' => 300000, 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh alasan',
    ]);

    app(SelesaikanTinjauanTagihanAction::class)->execute($tagihan);

    $fresh = $tagihan->fresh();
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
    expect($fresh->alasan_perlu_ditinjau)->toBeNull();
    expect((float) $fresh->net_amount)->toBe(300000.0);
});

it('exposes the action via a route guarded by permission', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create(['perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'x']);

    $response = $this->actingAs($admin)->post(route('admin.tagihan.selesai-ditinjau', $tagihan));

    $response->assertRedirect();
    expect($tagihan->fresh()->perlu_ditinjau_ulang)->toBeFalse();
});
