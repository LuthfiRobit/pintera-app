<?php

use App\Models\OrangTua;
use App\Models\Role;
use App\Models\User;
use Illuminate\Testing\TestResponse;

// Halaman dashboard punya 2 markup <nav>: sidebar utama (yang jadi target task ini) dan
// bottom-nav mobile terpisah (resources/views/layouts/bottom-nav.blade.php, di luar cakupan
// task ini) yang kebetulan memuat label placeholder yang sama. Isolasi ke <nav> sidebar (nav
// pertama di halaman) supaya assertion tidak salah tangkap markup bottom-nav.
function sidebarNavHtml(TestResponse $response): string
{
    preg_match('/<nav.*?<\/nav>/s', $response->getContent(), $matches);

    return $matches[0] ?? '';
}

it('tidak menampilkan menu sidebar stub untuk siswa', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $user = User::factory()->create();
    $user->assignRole('siswa');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $sidebarHtml = sidebarNavHtml($response);
    expect($sidebarHtml)->not->toContain('Nilai &amp; Rapor');
    expect($sidebarHtml)->not->toContain('Jadwal Pelajaran');
    expect($sidebarHtml)->not->toContain('Presensi Saya');
});

it('tidak menampilkan menu sidebar stub untuk orang tua', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $orangTuaUser = User::factory()->create();
    $orangTuaUser->assignRole('orang_tua');
    OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);

    $response = $this->actingAs($orangTuaUser)->get(route('dashboard'));

    $response->assertOk();
    $sidebarHtml = sidebarNavHtml($response);
    expect($sidebarHtml)->not->toContain('Nilai Anak');
    expect($sidebarHtml)->not->toContain('Jadwal Anak');
    expect($sidebarHtml)->not->toContain('Riwayat Izin/Sakit Anak');
});
