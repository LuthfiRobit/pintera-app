<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\KategoriKeringanan;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders the keringanan assignment widget wired to the existing SiswaKeringananController endpoints', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'spp']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    KategoriKeringanan::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $response = $this->actingAs($admin)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    // URL template pakai placeholder __ID__ (diganti di JS per siswa), bukan id nyata --
    // route() dipanggil dengan siswa dummy hanya untuk resolusi path, lihat form.blade.php.
    $response->assertSee(route('admin.siswa.keringanan.store', ['siswa' => '__ID__']), false);
    $response->assertSee(route('admin.siswa-keringanan.destroy', ['siswaKeringanan' => '__ID__']), false);
});

it('renders the widget on the create page pointed at the new preview-siswa-keringanan endpoint', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // Card Keringanan (termasuk widget ini) dibungkus Alpine x-if="!kategoriPpdb" yang
    // dievaluasi client-side, jadi markup-nya tetap ada di HTML source terlepas dari kategori
    // awal -- assert di sini hanya membuktikan widget benar-benar dirender & di-wire ke
    // endpoint preview yang baru, bukan perilaku show/hide PPDB (itu di luar jangkauan Pest).
    $response->assertSee('Kelola Assignment Siswa', false);
    $response->assertSee(route('admin.jenis-tagihan.preview-siswa-keringanan'), false);
});
