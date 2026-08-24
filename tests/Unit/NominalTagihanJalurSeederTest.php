<?php
// tests/Unit/NominalTagihanJalurSeederTest.php

use App\Models\JalurPpdb;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTagihanSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\NominalTagihanJalurSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new JenisTagihanSeeder())->run();
});

it('sets real nominals for Reguler and exactly 0 for Afirmasi, and skips Prestasi for the SD institution', function () {
    (new NominalTagihanJalurSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $aktif = TahunAjaran::where('lembaga_id', $lembaga->id)->where('status_aktif', true)->first();
        $pendaftaran = JenisTagihan::where('lembaga_id', $lembaga->id)->where('nama', 'Biaya Pendaftaran')->first();

        $reguler = JalurPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'Reguler')->first();
        $nominalReguler = NominalTagihanJalur::where('jenis_tagihan_id', $pendaftaran->id)->where('jalur_ppdb_id', $reguler->id)->first();
        expect($nominalReguler)->not->toBeNull();
        expect((int) $nominalReguler->nominal)->toBeGreaterThan(0);

        $afirmasi = JalurPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'Afirmasi')->first();
        $nominalAfirmasi = NominalTagihanJalur::where('jenis_tagihan_id', $pendaftaran->id)->where('jalur_ppdb_id', $afirmasi->id)->first();
        expect((int) $nominalAfirmasi->nominal)->toBe(0);

        $prestasi = JalurPpdb::where('lembaga_id', $lembaga->id)->where('tahun_ajaran_id', $aktif->id)->where('nama', 'Prestasi')->first();
        expect(NominalTagihanJalur::where('jenis_tagihan_id', $pendaftaran->id)->where('jalur_ppdb_id', $prestasi->id)->exists())->toBeFalse();
    }
});

it('does not set nominal against the inactive tahun ajaran jalur for SD', function () {
    (new NominalTagihanJalurSeeder())->run();

    $sdit = Lembaga::where('npsn', '20223333')->first();
    $lama = TahunAjaran::where('lembaga_id', $sdit->id)->where('status_aktif', false)->first();
    $jalurLama = JalurPpdb::where('lembaga_id', $sdit->id)->where('tahun_ajaran_id', $lama->id)->pluck('id');

    expect(NominalTagihanJalur::whereIn('jalur_ppdb_id', $jalurLama)->exists())->toBeFalse();
});

it('is idempotent when run twice', function () {
    (new NominalTagihanJalurSeeder())->run();
    $sebelum = NominalTagihanJalur::count();
    (new NominalTagihanJalurSeeder())->run();

    expect(NominalTagihanJalur::count())->toBe($sebelum);
});
