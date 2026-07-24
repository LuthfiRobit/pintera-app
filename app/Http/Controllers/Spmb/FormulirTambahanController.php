<?php

namespace App\Http\Controllers\Spmb;

use App\Http\Controllers\Spmb\Concerns\ResolvesWizardContext;
use App\Models\FormulirField;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FormulirTambahanController extends BaseController
{
    use ResolvesWizardContext;

    /**
     * Matches AMBANG_OPSI_SEARCHABLE in resources/js/formulir-tambahan-form.js — the
     * same >7-10-options threshold that decides whether a select becomes Tom Select.
     * A select past this threshold also needs the full row width to itself, same as
     * textarea/file, rather than being squeezed into a half-width paired column.
     */
    private const AMBANG_OPSI_SEARCHABLE = 10;

    public function create(): View
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();
        $fieldList = FormulirField::where('jalur_ppdb_id', $jalur->id)->orderBy('urutan')->get();
        $nominal = $this->resolveNominalPendaftaran($lembaga, $jalur);

        return view('spmb.formulir-tambahan', [
            'lembaga' => $lembaga, 'jalur' => $jalur, 'nominal' => $nominal,
            'fieldRows' => $this->bangunBarisField($fieldList),
        ]);
    }

    public function store(Request $request, PendaftaranWizardSession $wizardSession): RedirectResponse
    {
        [$lembaga, $jalur] = $this->resolveWizardContext();

        $fieldList = FormulirField::where('jalur_ppdb_id', $jalur->id)->get();
        $rules = [];
        foreach ($fieldList as $field) {
            $rules["jawaban.{$field->id}"] = $this->bangunAturanValidasi($field);
        }
        $request->validate($rules);

        $jawabanLama = $wizardSession->get($lembaga, $jalur)['jawaban_formulir'] ?? [];
        $jawaban = [];

        foreach ($fieldList as $field) {
            if ($field->field_type === 'file') {
                $file = $request->file("jawaban.{$field->id}");

                if ($file) {
                    $path = $file->store('pendaftaran-tmp/'.session()->getId(), 'public');
                    $jawaban[$field->id] = [
                        'file_path' => $path,
                        'nama_file_asli' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'ukuran_bytes' => $file->getSize(),
                    ];
                } elseif (isset($jawabanLama[$field->id])) {
                    // Native file inputs never pre-fill the browser's picker, so a
                    // resubmission (e.g. after a different field failed validation)
                    // arrives with no file at all — carry the earlier upload forward
                    // instead of discarding it, same as UploadDokumenController does.
                    $jawaban[$field->id] = $jawabanLama[$field->id];
                }

                continue;
            }

            $nilai = $request->input("jawaban.{$field->id}");
            if ($nilai !== null && $nilai !== '') {
                $jawaban[$field->id] = $nilai;
            }
        }

        $wizardSession->put($lembaga, $jalur, ['jawaban_formulir' => $jawaban]);

        return redirect()->route('portal.wizard.dokumen');
    }

    /**
     * Groups fields into rows for the view: consecutive "compact" fields (a single
     * line of input — text/number/date/select-that-stays-native) are paired 2-up,
     * while "wide" fields (textarea, file, select-that-becomes-Tom-Select) always
     * get a full-width row of their own and break any pending pair.
     *
     * @param  iterable<FormulirField>  $fieldList
     * @return array<int, array<int, FormulirField>>
     */
    private function bangunBarisField(iterable $fieldList): array
    {
        $baris = [];
        $bufferKompak = null;

        foreach ($fieldList as $field) {
            if ($this->isFieldKompak($field)) {
                if ($bufferKompak) {
                    $baris[] = [$bufferKompak, $field];
                    $bufferKompak = null;
                } else {
                    $bufferKompak = $field;
                }

                continue;
            }

            if ($bufferKompak) {
                $baris[] = [$bufferKompak];
                $bufferKompak = null;
            }

            $baris[] = [$field];
        }

        if ($bufferKompak) {
            $baris[] = [$bufferKompak];
        }

        return $baris;
    }

    private function isFieldKompak(FormulirField $field): bool
    {
        if (in_array($field->field_type, ['textarea', 'file'], true)) {
            return false;
        }

        if ($field->field_type === 'select' && count($field->options ?? []) > self::AMBANG_OPSI_SEARCHABLE) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, mixed>
     */
    private function bangunAturanValidasi(FormulirField $field): array
    {
        $wajib = $field->is_required ? 'required' : 'nullable';

        return match ($field->field_type) {
            'number' => [$wajib, 'numeric'],
            'date' => [$wajib, 'date'],
            'select' => [$wajib, Rule::in($field->options ?? [])],
            'textarea' => [$wajib, 'string', 'max:2000'],
            'file' => [$wajib, 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            default => [$wajib, 'string', 'max:255'],
        };
    }
}
