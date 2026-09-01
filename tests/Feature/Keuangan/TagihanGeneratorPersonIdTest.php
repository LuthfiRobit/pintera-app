<?php

// tests/Feature/Keuangan/TagihanGeneratorPersonIdTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Models\CalonMurid;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Services\TagihanGenerator;
use Illuminate\Support\Facades\DB;

function siapkanPendaftaranBerbayarUntukPersonId(): array
{
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'jalur_ppdb_id' => $jalur->id]);

    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);

    return [$lembaga, $jalur, $pendaftaran];
}

it('TagihanGenerator fills tagihan.person_id from pendaftaran.calonMurid.person_id', function () {
    [, , $pendaftaran] = siapkanPendaftaranBerbayarUntukPersonId();
    $calonMurid = $pendaftaran->calonMurid;

    $tagihan = app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran');

    expect($tagihan)->not->toBeNull();
    expect($tagihan->person_id)->toBe($calonMurid->person_id);
});

it('throws hard, instead of creating a tagihan with a null person_id, when pendaftaran has no resolvable calonMurid', function () {
    [, , $pendaftaran] = siapkanPendaftaranBerbayarUntukPersonId();

    // Simulate corrupt data: the calon_murid_id still points at a row, but
    // that row no longer exists (referential integrity already broken
    // upstream). Foreign key checks are toggled off only for this delete.
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    CalonMurid::where('id', $pendaftaran->calon_murid_id)->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    $pendaftaran->refresh();

    expect(fn () => app(TagihanGenerator::class)->generate($pendaftaran, 'pendaftaran'))
        ->toThrow(RuntimeException::class);

    $this->assertDatabaseMissing('tagihan', ['pendaftaran_id' => $pendaftaran->id]);
});
