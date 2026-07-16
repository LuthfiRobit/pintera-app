<?php
// tests/Feature/Admin/JenisTagihanTest.php

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function buatLembagaDenganJalurUntukTagihan(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);

    return [$lembaga, $tahunAjaran, $jalur];
}

it('denies jenis tagihan management without permission', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)->get(route('admin.jenis-tagihan.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [])->assertForbidden();
});

it('lets admin_keuangan create a jenis tagihan scoped to their own lembaga', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ]);

    $response->assertRedirect();
    $jenisTagihan = JenisTagihan::where('nama', 'Biaya Pendaftaran')->first();
    expect($jenisTagihan)->not->toBeNull();
    expect($jenisTagihan->lembaga_id)->toBe($lembaga->id);
});

it('lets admin_keuangan set nominal per jalur, rejecting a duplicate pair at the db level', function () {
    [$lembaga, , $jalur] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.nominal.store', $jenisTagihan), [
        'nominal' => [$jalur->id => 150000],
    ]);

    $response->assertRedirect();
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->where('jalur_ppdb_id', $jalur->id)->first()->nominal)->toEqual(150000);

    // Re-saving with a different value updates in place (updateOrCreate), never duplicates the pair.
    $this->actingAs($user)->post(route('admin.jenis-tagihan.nominal.store', $jenisTagihan), ['nominal' => [$jalur->id => 200000]]);
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->where('jalur_ppdb_id', $jalur->id)->count())->toBe(1);
    expect(NominalTagihanJalur::where('jenis_tagihan_id', $jenisTagihan->id)->where('jalur_ppdb_id', $jalur->id)->first()->nominal)->toEqual(200000);
});

it('only lists jenis tagihan belonging to the acting lembaga-scoped user own lembaga', function () {
    [$lembagaA] = buatLembagaDenganJalurUntukTagihan();
    [$lembagaB] = buatLembagaDenganJalurUntukTagihan();
    JenisTagihan::create(['lembaga_id' => $lembagaA->id, 'nama' => 'Punya A', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    JenisTagihan::create(['lembaga_id' => $lembagaB->id, 'nama' => 'Punya B', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    $user = User::factory()->create(['lembaga_id' => $lembagaA->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.index'));

    $response->assertOk()->assertSee('Punya A')->assertDontSee('Punya B');
});

it('denies kepala_sekolah from creating a jenis tagihan (view-only role for this module)', function () {
    [$lembaga] = buatLembagaDenganJalurUntukTagihan();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ])->assertForbidden();
});
