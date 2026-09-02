<?php

// tests/Feature/Admin/SkemaCicilanTest.php

use App\Domains\Keuangan\Models\Cicilan;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Domains\Keuangan\Models\SkemaCicilan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Models\TagihanItem;
use App\Domains\Keuangan\Services\PembayaranService;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function siapkanTagihanDaftarUlangBisaDicicil(): array
{
    [$lembaga, $jalur, , $pendaftaran] = buatPendaftaranUntukAdmin(status: 'diterima');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Uang Pangkal', 'kategori' => 'daftar_ulang', 'bisa_dicicil' => true, 'maks_cicilan' => 3]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 900000]);
    $tagihan = Tagihan::factory()->create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 900000, 'status' => 'belum_bayar']);
    TagihanItem::create(['tagihan_id' => $tagihan->id, 'jenis_tagihan_id' => $jenisTagihan->id, 'jumlah' => 900000]);

    return [$lembaga, $pendaftaran, $tagihan];
}

it('denies membuat skema cicilan without the cicilan.kelola permission', function () {
    [$lembaga, , $tagihan] = siapkanTagihanDaftarUlangBisaDicicil();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihan), ['jumlah_termin' => 3])
        ->assertForbidden();
});

it('lets bendahara_lembaga create a skema cicilan for a tagihan', function () {
    [$lembaga, , $tagihan] = siapkanTagihanDaftarUlangBisaDicicil();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihan), ['jumlah_termin' => 3]);

    $response->assertRedirect();
    expect(SkemaCicilan::where('tagihan_id', $tagihan->id)->first()->dibuat_oleh)->toBe('admin');
    expect(Cicilan::where('skema_cicilan_id', SkemaCicilan::where('tagihan_id', $tagihan->id)->value('id'))->count())->toBe(3);
});

it('404s creating a skema cicilan for a tagihan belonging to a different lembaga', function () {
    [, , $tagihanLembagaLain] = siapkanTagihanDaftarUlangBisaDicicil();
    $lembagaSaya = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $user->assignRole('bendahara_lembaga');

    $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihanLembagaLain), ['jumlah_termin' => 3])
        ->assertNotFound();
});

it('404s (not crashes) creating a skema cicilan for a non-PPDB (siswa) tagihan, which has no pendaftaran relation', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihanSiswa = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'status' => 'belum_bayar',
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihanSiswa), ['jumlah_termin' => 3])
        ->assertNotFound();
});

it('lets bendahara_lembaga edit nominal manually and rejects a mismatched total', function () {
    [$lembaga, , $tagihan] = siapkanTagihanDaftarUlangBisaDicicil();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');
    $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihan), ['jumlah_termin' => 3]);
    $skema = SkemaCicilan::where('tagihan_id', $tagihan->id)->first();

    $responseSalah = $this->actingAs($user)->post(route('admin.skema-cicilan.nominal.store', $skema), [
        'nominal' => [1 => 100000, 2 => 300000, 3 => 300000],
    ]);
    $responseSalah->assertSessionHasErrors();

    $responseBenar = $this->actingAs($user)->post(route('admin.skema-cicilan.nominal.store', $skema), [
        'nominal' => [1 => 500000, 2 => 200000, 3 => 200000],
    ]);
    $responseBenar->assertRedirect();
    expect((int) Cicilan::where('skema_cicilan_id', $skema->id)->where('urutan', 1)->value('nominal'))->toBe(500000);
});

it('404s editing nominal manually for a skema cicilan belonging to a different lembaga', function () {
    [$lembaga, , $tagihan] = siapkanTagihanDaftarUlangBisaDicicil();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');
    $this->actingAs($user)->post(route('admin.tagihan.skema-cicilan.store', $tagihan), ['jumlah_termin' => 3]);
    $skema = SkemaCicilan::where('tagihan_id', $tagihan->id)->first();

    $lembagaLain = Lembaga::factory()->create();
    $userLain = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $userLain->assignRole('bendahara_lembaga');

    $this->actingAs($userLain)->post(route('admin.skema-cicilan.nominal.store', $skema), [
        'nominal' => [1 => 500000, 2 => 200000, 3 => 200000],
    ])->assertNotFound();
});

it('404s (not crashes) editing nominal manually for a skema cicilan on a non-PPDB (siswa) tagihan', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $tagihanSiswa = Tagihan::factory()->create([
        'tagihable_type' => Siswa::class, 'tagihable_id' => $siswa->id, 'jenis_tagihan_id' => $jenisTagihan->id,
        'status' => 'belum_bayar', 'total_tagihan' => 900000,
    ]);
    // Skema ini hanya bisa dibuat lewat pemanggilan service langsung (bypass eligibility
    // service & endpoint admin.tagihan.skema-cicilan.store, yang memang sudah menolak
    // tagihan non-PPDB) -- mensimulasikan data yang lolos lewat jalur lain di luar UI normal.
    $skema = app(PembayaranService::class)->buatSkemaCicilan($tagihanSiswa, 3, 'admin', null);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $this->actingAs($user)->post(route('admin.skema-cicilan.nominal.store', $skema), [
        'nominal' => [1 => 300000, 2 => 300000, 3 => 300000],
    ])->assertNotFound();
});
