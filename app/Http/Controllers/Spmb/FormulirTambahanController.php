<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext;
use App\Models\FormulirField;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class FormulirTambahanController extends BaseController
{
    use ResolvesWizardContext;

    public function create(): View
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();
        $fieldList = FormulirField::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();
        $nominal = $this->resolveNominalPendaftaran($lembaga, $jalur);

        return view('spmb.formulir-tambahan', ['lembaga' => $lembaga, 'jalur' => $jalur, 'fieldList' => $fieldList, 'nominal' => $nominal]);
    }

    public function store(Request $request, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();

        $fieldList = FormulirField::where('jalur_ppdb_id', $jalur->id)->get();
        $rules = [];
        foreach ($fieldList as $field) {
            $rules["jawaban.{$field->id}"] = $field->is_required ? ['required'] : ['nullable'];
        }
        $data = $request->validate($rules);

        $wizardSession->put($lembaga, $jalur, ['jawaban_formulir' => $data['jawaban'] ?? []]);

        return redirect()->route('portal.wizard.dokumen');
    }
}
