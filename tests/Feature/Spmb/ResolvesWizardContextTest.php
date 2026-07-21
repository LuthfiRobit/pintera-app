<?php
// tests/Feature/Spmb/ResolvesWizardContextTest.php

use App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function subjekResolvesWizardContext(): object
{
    return new class
    {
        use ResolvesWizardContext;

        public function jalankan(): array
        {
            return $this->resolveWizardContext();
        }

        public function nominal($lembaga, $jalur)
        {
            return $this->resolveNominalPendaftaran($lembaga, $jalur);
        }
    };
}

it('resolves lembaga and jalur from the spmb_pilihan session', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalur->id]);

    [$resolvedLembaga, $resolvedJalur] = subjekResolvesWizardContext()->jalankan();

    expect($resolvedLembaga->id)->toBe($lembaga->id);
    expect($resolvedJalur->id)->toBe($jalur->id);
});

it('redirects to the dashboard when the spmb_pilihan session is empty', function () {
    try {
        subjekResolvesWizardContext()->jalankan();
        $this->fail('Expected HttpResponseException to be thrown.');
    } catch (HttpResponseException $e) {
        expect($e->getResponse()->headers->get('Location'))->toBe(route('portal.dashboard'));
    }
});

it('redirects to the dashboard when the session ids do not resolve to real records', function () {
    session(['spmb_pilihan.lembaga_id' => 999999, 'spmb_pilihan.jalur_id' => 999999]);

    try {
        subjekResolvesWizardContext()->jalankan();
        $this->fail('Expected HttpResponseException to be thrown.');
    } catch (HttpResponseException $e) {
        expect($e->getResponse()->headers->get('Location'))->toBe(route('portal.dashboard'));
    }
});

it('404s when the jalur in session does not belong to the lembaga in session', function () {
    [$lembaga] = buatLembagaDenganGelombangBuka();
    [, , $jalurLain] = buatLembagaDenganGelombangBuka();
    session(['spmb_pilihan.lembaga_id' => $lembaga->id, 'spmb_pilihan.jalur_id' => $jalurLain->id]);

    subjekResolvesWizardContext()->jalankan();
})->throws(NotFoundHttpException::class);

it('resolves the nominal pendaftaran for the jalur when a jenis tagihan pendaftaran exists', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $jenis = App\Models\JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran']);
    App\Models\NominalTagihanJalur::create(['jenis_tagihan_id' => $jenis->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);

    $nominal = subjekResolvesWizardContext()->nominal($lembaga, $jalur);

    expect((float) $nominal->nominal)->toBe(150000.0);
});

it('returns null nominal when there is no jenis tagihan pendaftaran for the lembaga', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();

    $nominal = subjekResolvesWizardContext()->nominal($lembaga, $jalur);

    expect($nominal)->toBeNull();
});
