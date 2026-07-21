<?php

namespace App\Http\Controllers\Spmb\Concerns;

use App\Models\JalurPpdb;
use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\NominalTagihanJalur;
use Illuminate\Http\Exceptions\HttpResponseException;

trait ResolvesWizardContext
{
    use ResolvesSpmbTenant;

    /**
     * @return array{0: Lembaga, 1: JalurPpdb}
     */
    protected function resolveWizardContext(): array
    {
        $lembagaId = session('spmb_pilihan.lembaga_id');
        $jalurId = session('spmb_pilihan.jalur_id');

        $lembaga = $lembagaId ? Lembaga::find($lembagaId) : null;
        $jalur = $jalurId ? JalurPpdb::find($jalurId) : null;

        if (! $lembaga || ! $jalur) {
            throw new HttpResponseException(redirect()->route('portal.dashboard'));
        }

        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        return [$lembaga, $jalur];
    }

    protected function resolveNominalPendaftaran(Lembaga $lembaga, JalurPpdb $jalur): ?NominalTagihanJalur
    {
        $jenisPendaftaran = JenisTagihan::where('lembaga_id', $lembaga->id)->where('kategori', 'pendaftaran')->first();

        if (! $jenisPendaftaran) {
            return null;
        }

        return NominalTagihanJalur::where('jenis_tagihan_id', $jenisPendaftaran->id)
            ->where('jalur_ppdb_id', $jalur->id)
            ->first();
    }
}
