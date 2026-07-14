<?php

namespace App\Http\Controllers\Spmb\Concerns;

use App\Http\Controllers\Spmb\PortalController;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;

trait ResolvesSpmbTenant
{
    protected function resolveLembaga(string $lembagaSlug): Lembaga
    {
        return Lembaga::where('slug', $lembagaSlug)->firstOrFail();
    }

    protected function assertJalurBelongsToLembaga(Lembaga $lembaga, JalurPpdb $jalur): void
    {
        abort_unless($jalur->lembaga_id === $lembaga->id, 404);
    }

    protected function resolveGelombangAktifUntukJalur(Lembaga $lembaga, JalurPpdb $jalur): GelombangPpdb
    {
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        $gelombang = PortalController::cariGelombangAktif($lembaga);

        abort_if(! $gelombang || $gelombang->tahun_ajaran_id !== $jalur->tahun_ajaran_id, 404);

        return $gelombang;
    }
}
