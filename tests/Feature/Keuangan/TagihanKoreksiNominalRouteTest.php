<?php

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('lets an admin with tagihan.edit correct a flagged tagihan nominal via the route', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'total_tagihan' => 500000, 'net_amount' => 500000, 'paid_amount' => 100000,
        'status' => 'sebagian', 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.tagihan.koreksi-nominal', $tagihan), [
        'total_tagihan' => 500000, 'discount_amount' => 100000,
    ]);

    $response->assertRedirect();
    $fresh = $tagihan->fresh();
    expect((float) $fresh->net_amount)->toBe(400000.0);
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
});

it('denies access without tagihan.edit permission', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'x',
    ]);

    $this->actingAs($admin)->post(route('admin.tagihan.koreksi-nominal', $tagihan), [
        'total_tagihan' => 400000, 'discount_amount' => 0,
    ])->assertForbidden();
});

it('rejects discount_amount greater than total_tagihan at validation level', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'x',
    ]);

    $this->actingAs($admin)->post(route('admin.tagihan.koreksi-nominal', $tagihan), [
        'total_tagihan' => 100000, 'discount_amount' => 200000,
    ])->assertSessionHasErrors('discount_amount');
});

it('404s correcting a tagihan belonging to a different lembaga', function () {
    $lembagaLain = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembagaLain->id]);

    $lembagaAsli = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembagaAsli->id]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'total_tagihan' => 500000, 'net_amount' => 500000, 'paid_amount' => 100000,
        'status' => 'sebagian', 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    $this->actingAs($admin)->post(route('admin.tagihan.koreksi-nominal', $tagihan), [
        'total_tagihan' => 400000, 'discount_amount' => 0,
    ])->assertNotFound();
});
