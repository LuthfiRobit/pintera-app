<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders the create form with live preview indicators for sasaran, tarif, and keringanan', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($admin)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee('preview-sasaran', false);
    $response->assertSee('preview-tarif-keringanan', false);
});

it('renders the edit form with reorder controls and in-form keringanan assignment modal/section', function () {
    $lembaga = Lembaga::factory()->create();
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole('bendahara_lembaga');
    session(['active_lembaga_id' => $lembaga->id]);

    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'spp']);

    $response = $this->actingAs($admin)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    $response->assertSee('preview-sasaran', false);
    $response->assertSee('preview-tarif-keringanan', false);
    $response->assertSee('tarif-grup/reorder', false);
    // Widget assignment siswa-ke-keringanan langsung di form (bukan cuma modal buat kategori
    // baru) -- lihat JenisTagihanFormKeringananWidgetTest.php untuk cakupan penuhnya.
    $response->assertSee('preview-siswa-keringanan', false);
    $response->assertSee('Kelola Assignment Siswa', false);
});
