<?php
// tests/Feature/Admin/JenisTagihanProsesTest.php

use App\Models\JenisTagihan;
use App\Models\JenisTagihanSasaranGrup;
use App\Models\JenisTagihanSasaranKriteria;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use App\Services\TagihanBillingGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

function buatUserKeuanganUntukProses(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    return [$user, $lembaga];
}

it('returns a full breakdown of generated, sudah_tertagih, tidak_memenuhi_kriteria, and gagal', function () {
    [$user, $lembaga] = buatUserKeuanganUntukProses();

    $siswaCocokBelumTertagih = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'L']);
    $siswaCocokSudahTertagih = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'L']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'P']); // tidak memenuhi kriteria

    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'spp', 'default_amount' => 200000]);
    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]);


    app(TagihanBillingGenerator::class)->generateForSiswa($siswaCocokSudahTertagih, $jenisTagihan, 'manual');

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.proses', $jenisTagihan));

    $response->assertOk();
    $response->assertJson([
        'bills_generated' => 1,
        'sudah_tertagih' => 1,
        'tidak_memenuhi_kriteria' => 1,
        'gagal' => 0,
    ]);
    expect(Tagihan::where('tagihable_id', $siswaCocokBelumTertagih->id)->exists())->toBeTrue();
});

it('rejects proses for a ppdb-kategori jenis_tagihan with a 422', function () {
    [$user, $lembaga] = buatUserKeuanganUntukProses();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'pendaftaran']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($user)->postJson(route('admin.jenis-tagihan.proses', $jenisTagihan));

    $response->assertStatus(422);
    expect(Tagihan::count())->toBe(0);
});

it('denies proses without jenis-tagihan.edit permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'kategori' => 'spp']);

    $this->actingAs($user)->postJson(route('admin.jenis-tagihan.proses', $jenisTagihan))->assertForbidden();
});
