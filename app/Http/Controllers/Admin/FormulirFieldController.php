<?php

namespace App\Http\Controllers\Admin;

use App\Models\FormulirField;
use App\Models\JalurPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;

class FormulirFieldController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('formulir-field.create');

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
                $message = 'Field bertipe pilihan butuh minimal 2 opsi (satu opsi per baris).';

                if ($request->wantsJson()) {
                    return response()->json(['message' => $message, 'errors' => ['options' => [$message]]], 422);
                }

                return back()->withErrors(['options' => $message])->withInput();
            }
        }

        $field = FormulirField::create([
            'jalur_ppdb_id' => $jalur->id,
            'label' => $data['label'],
            'field_type' => $data['field_type'],
            'options' => $options,
            'is_required' => $request->boolean('is_required'),
            'urutan' => $jalur->formulirField()->count(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['data' => $field], 201);
        }

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Field formulir berhasil ditambahkan.');
    }

    public function destroy(Request $request, FormulirField $formulirField): RedirectResponse|JsonResponse
    {
        $this->authorize('formulir-field.delete');

        $jumlahJawaban = $formulirField->jawabanFormulir()->count();
        if ($jumlahJawaban > 0) {
            return $this->errorResponse(
                $request,
                "Tidak bisa dihapus, sudah ada {$jumlahJawaban} jawaban terkait dari calon murid."
            );
        }

        $jalur = $formulirField->jalurPpdb;
        $formulirField->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Field formulir berhasil dihapus.']);
        }

        return redirect()->route('admin.jalur-ppdb.edit', $jalur)->with('status', 'Field formulir berhasil dihapus.');
    }

    private function errorResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['formulir_field' => $message]);
    }
}
