<?php

namespace App\Http\Controllers\Admin;

use App\Models\FormulirField;
use App\Models\JalurPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class FormulirFieldController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $data = $request->validate([
            'jalur_ppdb_id' => ['required', Rule::exists('jalur_ppdb', 'id')->where(fn ($query) => $query->whereIn('id', JalurPpdb::pluck('id')))],
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'in:text,textarea,number,date,select,file'],
            'is_required' => ['nullable', 'boolean'],
            'options' => ['required_if:field_type,select', 'nullable', 'string'],
        ]);

        $jalur = JalurPpdb::findOrFail($data['jalur_ppdb_id']);

        $options = null;
        if ($data['field_type'] === 'select') {
            $options = array_values(array_filter(array_map('trim', explode("\n", $data['options'] ?? ''))));

            if (count($options) < 2) {
                return back()->withErrors(['options' => 'Field bertipe pilihan butuh minimal 2 opsi (satu opsi per baris).'])->withInput();
            }
        }

        FormulirField::create([
            'jalur_ppdb_id' => $jalur->id,
            'label' => $data['label'],
            'field_type' => $data['field_type'],
            'options' => $options,
            'is_required' => $request->boolean('is_required'),
            'urutan' => $jalur->formulirField()->count(),
        ]);

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Field formulir berhasil ditambahkan.');
    }

    public function destroy(FormulirField $formulirField): RedirectResponse
    {
        $this->authorize('manage-ppdb');

        $jalur = $formulirField->jalurPpdb;
        $formulirField->delete();

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Field formulir berhasil dihapus.');
    }
}
