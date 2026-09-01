<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function buatUserKeuanganFormPageUi(int $lembagaId): User
{
    $user = User::factory()->create(['lembaga_id' => $lembagaId]);
    $user->assignRole('bendahara_lembaga');

    return $user;
}

it('renders the tipe select with all 5 options for non-PPDB categories', function () {
    $lembaga = Lembaga::factory()->create();
    $user = buatUserKeuanganFormPageUi($lembaga->id);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee('name="tipe"', false);
    $response->assertSee('value="harian"', false);
    $response->assertSee('value="mingguan"', false);
    $response->assertSee('value="bulanan"', false);
    $response->assertSee('value="tahunan"', false);
    $response->assertSee('value="sekali"', false);
});

it('renders the inputs for all 5 support columns with proper labels and help text', function () {
    $lembaga = Lembaga::factory()->create();
    $user = buatUserKeuanganFormPageUi($lembaga->id);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee('name="hari_generate"', false);
    $response->assertSee('name="bulan_generate"', false);
    $response->assertSee('name="tanggal_generate"', false);
    $response->assertSee('name="hari_jatuh_tempo"', false);
    $response->assertSee('name="offset_hari_jatuh_tempo"', false);
});

it('pre-fills existing tipe and support column values when editing an existing jenis tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create([
        'mode' => 'otomatis', 'tipe' => 'mingguan', 'hari_generate' => 3, 'offset_hari_jatuh_tempo' => 5,
    ]);
    $user = buatUserKeuanganFormPageUi($jenisTagihan->lembaga_id);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    $response->assertSee('value="3"', false);
    $response->assertSee('value="5"', false);
});
