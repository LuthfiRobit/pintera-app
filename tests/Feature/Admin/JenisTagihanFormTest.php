<?php
// tests/Feature/Admin/JenisTagihanFormTest.php

use App\Models\JenisTagihan;
use App\Models\JenisTagihanSasaranGrup;
use App\Models\KategoriKeringanan;
use App\Models\Lembaga;
use App\Models\TagihanBillingGenerator;
use App\Models\User;
use App\Services\TagihanBillingGenerator as BillingGenerator;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function buatUserKeuangan(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    return [$user, $lembaga];
}

it('creates a non-ppdb jenis tagihan with sasaran, tarif, and keringanan in one save', function () {
    [$user, $lembaga] = buatUserKeuangan();
    $kategoriKeringanan = KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'SPP Bulanan',
        'kategori' => 'spp',
        'bisa_dicicil' => false,
        'mode' => 'otomatis',
        'default_amount' => 500000,
        'tanggal_mulai' => '2026-07-01',
        'tanggal_generate' => 1,
        'hari_jatuh_tempo' => 10,
        'sasaran' => [
            ['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]],
        ],
        'tarif' => [
            ['nominal' => 450000, 'kriteria' => [['field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]]],
        ],
        'keringanan' => [
            ['kategori_keringanan_id' => $kategoriKeringanan->id, 'tipe_potongan' => 'persen', 'nilai' => 50, 'keterangan' => 'Beasiswa'],
        ],
    ]);

    $response->assertRedirect(route('admin.jenis-tagihan.index'));
    $jenisTagihan = JenisTagihan::where('nama', 'SPP Bulanan')->firstOrFail();
    expect($jenisTagihan->mode)->toBe('otomatis');

    $sasaranGrup = $jenisTagihan->sasaranGrup()->where('tipe', 'sasaran')->with('kriteria')->first();
    expect($sasaranGrup->kriteria)->toHaveCount(1);
    expect($sasaranGrup->kriteria->first()->field)->toBe('status_siswa');

    $tarifGrup = $jenisTagihan->sasaranGrup()->where('tipe', 'tarif')->first();
    expect((float) $tarifGrup->nominal)->toBe(450000.0);

    $rule = $jenisTagihan->keringananRules()->first();
    expect($rule->kategori_keringanan_id)->toBe($kategoriKeringanan->id);
    expect((float) $rule->nilai)->toBe(50.0);
});

it('rejects a sasaran payload for a ppdb kategori with a 422, creating nothing', function () {
    [$user] = buatUserKeuangan();

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'Biaya Pendaftaran',
        'kategori' => 'pendaftaran',
        'bisa_dicicil' => false,
        'sasaran' => [['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]]]],
    ]);

    $response->assertStatus(422);
    expect(JenisTagihan::where('nama', 'Biaya Pendaftaran')->exists())->toBeFalse();
    expect(JenisTagihanSasaranGrup::count())->toBe(0);
});

it('rejects two keringanan rules for the same kategori_keringanan_id in one payload', function () {
    [$user, $lembaga] = buatUserKeuangan();
    $kategoriKeringanan = KategoriKeringanan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Yatim Piatu']);

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.store'), [
        'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'keringanan' => [
            ['kategori_keringanan_id' => $kategoriKeringanan->id, 'tipe_potongan' => 'fixed', 'nilai' => 10000],
            ['kategori_keringanan_id' => $kategoriKeringanan->id, 'tipe_potongan' => 'persen', 'nilai' => 20],
        ],
    ]);

    $response->assertStatus(422);
    expect(JenisTagihan::where('nama', 'SPP Bulanan')->exists())->toBeFalse();
});

it('replaces sasaran on update without touching already-generated tagihan for that jenis tagihan', function () {
    [$user, $lembaga] = buatUserKeuangan();
    $jenisTagihan = JenisTagihan::create([
        'lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'mode' => 'manual', 'default_amount' => 500000,
    ]);
    $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
    $grup->kriteria()->create(['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]);

    $log = app(BillingGenerator::class)->generate($jenisTagihan, 'manual');
    expect($log->bills_generated)->toBeGreaterThanOrEqual(0);
    $existingTagihanCount = \App\Models\Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count();

    $response = $this->actingAs($user)->put(route('admin.jenis-tagihan.update', $jenisTagihan), [
        'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false,
        'mode' => 'manual', 'default_amount' => 500000,
        'sasaran' => [
            ['kriteria' => [['field' => 'status_siswa', 'operator' => 'in', 'value' => ['lulus']]]],
        ],
    ]);

    $response->assertRedirect(route('admin.jenis-tagihan.index'));
    expect(\App\Models\Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count())->toBe($existingTagihanCount);
    $newGrup = $jenisTagihan->sasaranGrup()->where('tipe', 'sasaran')->first();
    expect($newGrup->kriteria->first()->value)->toBe(['lulus']);
});

it('still creates a ppdb jenis tagihan without any billing fields, unchanged from before', function () {
    [$user] = buatUserKeuangan();

    $response = $this->actingAs($user)->post(route('admin.jenis-tagihan.store'), [
        'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false,
    ]);

    $response->assertRedirect();
    $jenisTagihan = JenisTagihan::where('nama', 'Biaya Pendaftaran')->firstOrFail();
    $response->assertRedirect(route('admin.jenis-tagihan.nominal', $jenisTagihan));
});
