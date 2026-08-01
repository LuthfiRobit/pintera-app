<?php

use App\Models\DokumenPendaftaran;
use App\Models\HasilSeleksi;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SkPpdb;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds a spread of pendaftaran states across K-9 institutions for manual M3 testing', function () {
    $this->seed(DatabaseSeeder::class);

    foreach (Lembaga::all() as $lembaga) {
        $pendaftaranLembaga = Pendaftaran::where('lembaga_id', $lembaga->id)->get();
        expect($pendaftaranLembaga->count())->toBeGreaterThanOrEqual(3);

        expect($pendaftaranLembaga->where('status', 'menunggu_verifikasi')->count())->toBeGreaterThanOrEqual(1);
        expect($pendaftaranLembaga->where('status', 'diterima')->count())->toBeGreaterThanOrEqual(1);
        expect($pendaftaranLembaga->where('status', 'ditolak')->count())->toBeGreaterThanOrEqual(1);

        $dengan_dokumen_campuran = $pendaftaranLembaga->first(function (Pendaftaran $p) {
            $statuses = DokumenPendaftaran::where('pendaftaran_id', $p->id)->pluck('status_verifikasi');

            return $statuses->contains('diterima') && $statuses->contains('ditolak');
        });
        expect($dengan_dokumen_campuran)->not->toBeNull();

        expect(HasilSeleksi::whereIn('pendaftaran_id', $pendaftaranLembaga->pluck('id'))->exists())->toBeTrue();
    }

    expect(SkPpdb::count())->toBeGreaterThanOrEqual(1);
    $pendaftaranDenganSk = Pendaftaran::whereNotNull('sk_ppdb_id')->first();
    expect($pendaftaranDenganSk)->not->toBeNull();
    expect($pendaftaranDenganSk->status)->toBeIn(['diterima', 'ditolak']);
});

it('is idempotent when the full DatabaseSeeder is run twice', function () {
    $this->seed(DatabaseSeeder::class);
    $countFirstRun = Pendaftaran::count();

    $this->seed(DatabaseSeeder::class);

    expect(Pendaftaran::count())->toBe($countFirstRun);
});
