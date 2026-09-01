<?php

use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('logs net_amount, discount_amount, discount_type, perlu_ditinjau_ulang, and alasan_perlu_ditinjau changes', function () {
    $siswa = Siswa::factory()->create();
    $tagihan = Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'net_amount' => 300000, 'perlu_ditinjau_ulang' => false]);

    $tagihan->update(['net_amount' => 250000, 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh']);

    $activity = $tagihan->activities()->latest()->first();
    expect($activity->changes['attributes'])->toHaveKeys(['net_amount', 'perlu_ditinjau_ulang', 'alasan_perlu_ditinjau']);
});

it('shows a badge count of tagihan perlu_ditinjau_ulang scoped to the acting lembaga', function () {
    $lembagaSatu = Lembaga::factory()->create();
    $lembagaDua = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembagaSatu->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembagaSatu->id]);

    $siswaSatuA = Siswa::factory()->create(['lembaga_id' => $lembagaSatu->id]);
    $siswaSatuB = Siswa::factory()->create(['lembaga_id' => $lembagaSatu->id]);
    $siswaDua = Siswa::factory()->create(['lembaga_id' => $lembagaDua->id]);

    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswaSatuA->id, 'perlu_ditinjau_ulang' => true]);
    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswaSatuB->id, 'perlu_ditinjau_ulang' => true]);
    Tagihan::factory()->create(['tagihable_type' => Siswa::class, 'tagihable_id' => $siswaDua->id, 'perlu_ditinjau_ulang' => true]); // lembaga lain, tidak boleh terhitung

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertSee('2'); // badge count utk lembagaSatu saja
});
