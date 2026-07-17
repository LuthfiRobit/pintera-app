<?php
// tests/Unit/PpdbConfigurationSeedersTest.php

use App\Models\DokumenSyaratPpdb;
use App\Models\FormulirField;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\SeleksiPpdb;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Database\Seeders\DokumenSyaratPpdbSeeder;
use Database\Seeders\FormulirFieldSeeder;
use Database\Seeders\GelombangPpdbSeeder;
use Database\Seeders\JalurPpdbSeeder;
use Database\Seeders\JenisTesMasterSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\SeleksiPpdbSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\TahunAjaranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new TahunAjaranSeeder())->run();
    (new SemesterSeeder())->run();
    (new JenisTesMasterSeeder())->run();
});

function jalankanKonfigurasiPpdb(): void
{
    (new GelombangPpdbSeeder())->run();
    (new JalurPpdbSeeder())->run();
    (new FormulirFieldSeeder())->run();
    (new DokumenSyaratPpdbSeeder())->run();
    (new SeleksiPpdbSeeder())->run();
}

it('seeds SMP PPDB configuration in BOTH the inactive and active tahun ajaran', function () {
    jalankanKonfigurasiPpdb();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $lama = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', false)->first();
    $baru = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();

    expect(JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $lama->id)->where('nama', 'Reguler')->exists())->toBeTrue();
    expect(JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $baru->id)->where('nama', 'Reguler')->exists())->toBeTrue();
});

it('seeds SMA PPDB configuration only in its active tahun ajaran', function () {
    jalankanKonfigurasiPpdb();

    $sma = Lembaga::where('npsn', '20223355')->first();
    $baru = TahunAjaran::where('lembaga_id', $sma->id)->where('status_aktif', true)->first();

    expect(JalurPpdb::where('lembaga_id', $sma->id)->where('tahun_ajaran_id', $baru->id)->where('nama', 'Reguler')->exists())->toBeTrue();
    expect(GelombangPpdb::where('lembaga_id', $sma->id)->where('tahun_ajaran_id', $baru->id)->exists())->toBeTrue();
});

it('seeds 3 jalur (Reguler, Prestasi, Afirmasi) with their formulir/dokumen/seleksi for the SMP active tahun ajaran', function () {
    jalankanKonfigurasiPpdb();

    $smp = Lembaga::where('npsn', '20223344')->first();
    $baru = TahunAjaran::where('lembaga_id', $smp->id)->where('status_aktif', true)->first();

    $reguler = JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $baru->id)->where('nama', 'Reguler')->first();
    expect($reguler)->not->toBeNull();
    expect(FormulirField::where('jalur_ppdb_id', $reguler->id)->where('label', 'Sekolah Asal')->exists())->toBeTrue();
    expect(DokumenSyaratPpdb::where('jalur_ppdb_id', $reguler->id)->where('nama_dokumen', 'Akta Kelahiran')->exists())->toBeTrue();
    expect(SeleksiPpdb::where('jalur_ppdb_id', $reguler->id)->count())->toBe(2);

    $afirmasi = JalurPpdb::where('lembaga_id', $smp->id)->where('tahun_ajaran_id', $baru->id)->where('nama', 'Afirmasi')->first();
    expect($afirmasi)->not->toBeNull();
    expect(SeleksiPpdb::where('jalur_ppdb_id', $afirmasi->id)->count())->toBe(0);
});

it('is idempotent when run twice', function () {
    jalankanKonfigurasiPpdb();
    $jalurSebelum = JalurPpdb::count();
    jalankanKonfigurasiPpdb();

    expect(JalurPpdb::count())->toBe($jalurSebelum);
});
