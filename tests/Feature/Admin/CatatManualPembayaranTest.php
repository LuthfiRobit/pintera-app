<?php

// tests/Feature/Admin/CatatManualPembayaranTest.php

use App\Domains\Keuangan\Models\Cicilan;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Models\TagihanItem;
use App\Domains\Keuangan\Services\PembayaranService;
use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function siapkanCicilanTermin1(): array
{
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 900000]);
    $tagihan = Tagihan::factory()->create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 900000, 'status' => 'belum_bayar']);
    TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'jumlah' => 900000]);

    $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihan, 3, 'admin', null);
    $cicilan = Cicilan::where('skema_cicilan_id', $skema->id)->where('urutan', 1)->first();

    return [$lembaga, $cicilan];
}

it('denies catat manual without the pembayaran.catat-manual permission', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::factory()->create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->post(route('admin.tagihan.catat-manual', $tagihan))->assertForbidden();
});

it('lets bendahara_lembaga record a lump-sum tagihan payment directly as lunas', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::factory()->create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->post(route('admin.tagihan.catat-manual', $tagihan));

    $response->assertRedirect();
    expect($tagihan->fresh()->status)->toBe('lunas');
    expect(Pembayaran::where('tagihan_id', $tagihan->id)->first()->sumber)->toBe('admin');
});

it('404s catat manual tagihan for a tagihan belonging to a different lembaga', function () {
    [$lembaga, , , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $tagihan = Tagihan::factory()->create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 500000, 'status' => 'belum_bayar']);

    $lembagaLain = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $user->assignRole('bendahara_lembaga');

    $this->actingAs($user)->post(route('admin.tagihan.catat-manual', $tagihan))->assertNotFound();
});

it('denies catat manual cicilan without the pembayaran.catat-manual permission', function () {
    [$lembaga, $cicilan] = siapkanCicilanTermin1();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->post(route('admin.cicilan.catat-manual', $cicilan))->assertForbidden();
});

it('lets bendahara_lembaga record a cicilan termin payment directly as lunas', function () {
    [$lembaga, $cicilan] = siapkanCicilanTermin1();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->post(route('admin.cicilan.catat-manual', $cicilan));

    $response->assertRedirect();
    expect($cicilan->fresh()->status)->toBe('lunas');
    $pembayaran = Pembayaran::where('cicilan_id', $cicilan->id)->first();
    expect($pembayaran)->not->toBeNull();
    expect($pembayaran->sumber)->toBe('admin');
});

it('404s catat manual cicilan for a cicilan belonging to a different lembaga', function () {
    [, $cicilan] = siapkanCicilanTermin1();

    $lembagaLain = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $user->assignRole('bendahara_lembaga');

    $this->actingAs($user)->post(route('admin.cicilan.catat-manual', $cicilan))->assertNotFound();
});
