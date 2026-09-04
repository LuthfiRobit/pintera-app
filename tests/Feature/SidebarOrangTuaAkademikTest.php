<?php

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new RoleSeeder)->run();
});

it('menampilkan menu Nilai & Rapor Anak, Jadwal Anak, dan Riwayat Izin/Sakit Anak untuk orang tua', function () {
    $user = User::factory()->create();
    $user->assignRole('orang_tua');
    OrangTua::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Nilai &amp; Rapor Anak', false);
    $response->assertSee('Jadwal Anak');
    $response->assertSee('Riwayat Izin/Sakit Anak');
});

it('tidak menampilkan menu Ruang Orang Tua untuk actor bukan orang tua', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru');
    Guru::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Nilai &amp; Rapor Anak', false);
    $response->assertDontSee('Jadwal Anak');
    $response->assertDontSee('Riwayat Izin/Sakit Anak');
});
