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
    // Menu stub placeholder /dalam-pengembangan sudah digantikan oleh rute mandiri resmi (2026-09-04)
    expect($sidebarHtml)->not->toContain('dalam-pengembangan?fitur=nilai-rapor');
    expect($sidebarHtml)->not->toContain('dalam-pengembangan?fitur=jadwal-pelajaran');
    expect($sidebarHtml)->not->toContain('dalam-pengembangan?fitur=presensi-saya');
    expect($sidebarHtml)->toContain(route('admin.nilai-rapor-saya.index'));
    expect($sidebarHtml)->toContain(route('admin.jadwal-pelajaran-saya.index'));
    expect($sidebarHtml)->toContain(route('admin.presensi-saya.index'));
});

it('tidak menampilkan menu sidebar stub untuk orang tua', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $orangTuaUser = User::factory()->create();
    $orangTuaUser->assignRole('orang_tua');
    OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);

    $response = $this->actingAs($orangTuaUser)->get(route('dashboard'));

    $response->assertOk();
    $sidebarHtml = sidebarNavHtml($response);
    // Menu stub placeholder /dalam-pengembangan sudah digantikan oleh rute mandiri resmi (2026-09-04)
    expect($sidebarHtml)->not->toContain('dalam-pengembangan?fitur=nilai-anak');
    expect($sidebarHtml)->not->toContain('dalam-pengembangan?fitur=jadwal-anak');
    expect($sidebarHtml)->not->toContain('dalam-pengembangan?fitur=riwayat-izin-sakit-anak');
    expect($sidebarHtml)->toContain(route('admin.nilai-anak.index'));
    expect($sidebarHtml)->toContain(route('admin.jadwal-anak.index'));
    expect($sidebarHtml)->toContain(route('admin.riwayat-izin-sakit-anak.index'));
});
