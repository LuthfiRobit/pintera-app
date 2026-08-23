<?php
// tests/Feature/ErrorPageDynamicMessageTest.php

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('shows the real abort message instead of the generic 404 copy', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.lihat-qr-sendiri', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kehadiran-sdm.lihat-qr-sendiri']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    // User punya permission via role admin_sdm, TAPI tidak ada baris Guru/Karyawan terkait —
    // ini persis skenario yang dilaporkan user: 404 generik padahal ada pesan spesifik di kode.
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('sdm.qr-saya'));

    $response->assertStatus(404)
        ->assertSee('Data kepegawaian Anda tidak ditemukan.')
        ->assertDontSee('Halaman yang Anda cari mungkin sudah dipindahkan atau tidak tersedia lagi.');
});

it('still shows the generic fallback copy when a 404 has no exception message', function () {
    $response = $this->get('/rute-yang-benar-benar-tidak-ada-di-manapun');

    $response->assertStatus(404)
        ->assertSee('Halaman yang Anda cari mungkin sudah dipindahkan atau tidak tersedia lagi.');
});
