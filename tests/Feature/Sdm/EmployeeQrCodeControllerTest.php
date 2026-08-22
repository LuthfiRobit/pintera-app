<?php
// tests/Feature/Sdm/EmployeeQrCodeControllerTest.php

use App\Domains\Sdm\Models\EmployeeQrCode;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('lets a guru view and generate their own qr code', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.lihat-qr-sendiri', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('kehadiran-sdm.lihat-qr-sendiri');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('sdm.qr-saya'))
        ->assertOk()
        ->assertSee('Anda belum memiliki QR kehadiran');

    $this->actingAs($user)->post(route('sdm.qr-saya.generate'))->assertRedirect();

    expect(EmployeeQrCode::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->where('is_active', true)->exists())->toBeTrue();
});

it('rejects a user without kehadiran-sdm.lihat-qr-sendiri permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('sdm.qr-saya'))->assertForbidden();
});
