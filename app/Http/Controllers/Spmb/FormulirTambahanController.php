<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesSpmbTenant;
use App\Models\FormulirField;
use App\Models\JalurPpdb;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class FormulirTambahanController extends BaseController
{
    use ResolvesSpmbTenant;

    public function create(string $lembagaSlug, JalurPpdb $jalur): View
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);
        $fieldList = FormulirField::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();

        return view('spmb.formulir-tambahan', ['lembaga' => $lembaga, 'jalur' => $jalur, 'fieldList' => $fieldList]);
    }

    public function store(Request $request, string $lembagaSlug, JalurPpdb $jalur, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        $lembaga = $this->resolveLembaga($lembagaSlug);
        $this->assertJalurBelongsToLembaga($lembaga, $jalur);

        $fieldList = FormulirField::where('jalur_ppdb_id', $jalur->id)->get();
        $rules = [];
        foreach ($fieldList as $field) {
            $rules["jawaban.{$field->id}"] = $field->is_required ? ['required'] : ['nullable'];
        }
        $data = $request->validate($rules);

        $wizardSession->put($lembaga, $jalur, ['jawaban_formulir' => $data['jawaban'] ?? []]);

        return redirect()->route('spmb.dokumen', ['lembagaSlug' => $lembaga->slug, 'jalur' => $jalur->id]);
    }
}
