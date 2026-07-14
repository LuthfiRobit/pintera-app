<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Models\DokumenSyaratPpdb;
use App\Models\JalurPpdb;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class UploadDokumenController extends BaseController
{
    use ResolvesSpmbTenant;

    public function create(string $lembagaSlug, JalurPpdb $jalur): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);
        $syaratList = DokumenSyaratPpdb::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();

        return view('spmb.upload-dokumen', ['lembaga' => $lembaga, 'jalur' => $jalur, 'syaratList' => $syaratList]);
    }

    public function store(Request $request, string $lembagaSlug, JalurPpdb $jalur, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        $syaratList = DokumenSyaratPpdb::where('jalur_ppdb_id', $jalur->id)->get();
        $rules = [];
        foreach ($syaratList as $syarat) {
            $rules["dokumen.{$syarat->id}"] = [
                $syarat->wajib ? 'required' : 'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:2048',
            ];
        }
        $request->validate($rules);

        $disimpan = $wizardSession->get($lembaga, $jalur)['dokumen'] ?? [];

        foreach ($syaratList as $syarat) {
            $file = $request->file("dokumen.{$syarat->id}");
            if (! $file) {
                continue;
            }

            $path = $file->store('pendaftaran-tmp/'.session()->getId(), 'public');

            $disimpan[$syarat->id] = [
                'file_path' => $path,
                'nama_file_asli' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'ukuran_bytes' => $file->getSize(),
            ];
        }

        $wizardSession->put($lembaga, $jalur, ['dokumen' => $disimpan]);

        return redirect()->route('spmb.review', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]);
    }
}
