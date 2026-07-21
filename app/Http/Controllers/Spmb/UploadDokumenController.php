<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext;
use App\Models\DokumenSyaratPpdb;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class UploadDokumenController extends BaseController
{
    use ResolvesWizardContext;

    public function create(): View
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();
        $syaratList = DokumenSyaratPpdb::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();
        $nominal = $this->resolveNominalPendaftaran($lembaga, $jalur);

        return view('spmb.upload-dokumen', ['lembaga' => $lembaga, 'jalur' => $jalur, 'syaratList' => $syaratList, 'nominal' => $nominal]);
    }

    public function store(Request $request, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();

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

        return redirect()->route('portal.wizard.review');
    }
}
