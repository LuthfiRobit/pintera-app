<?php

namespace App\Http\Controllers\Admin;

use App\Models\JabatanTambahanMaster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JabatanTambahanMasterController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('jabatan-tambahan-master.view');

        $jabatanList = JabatanTambahanMaster::withCount(['guru' => fn ($q) => $q->withoutGlobalScopes()])->orderBy('kelompok')->orderBy('nama')->get();

        if ($request->wantsJson()) {
            return response()->json(['items' => $jabatanList]);
        }

        return view('admin.jabatan-tambahan-master.index', [
            'jabatanList' => $jabatanList,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:jabatan_tambahan_master,nama'],
            'kelompok' => ['required', Rule::in(['struktural', 'fungsional'])],
        ]);

        $item = JabatanTambahanMaster::create($data)->loadCount(['guru' => fn ($q) => $q->withoutGlobalScopes()]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jabatan tambahan berhasil dirilis',
                'item' => $item,
            ], 201);
        }

        return back()->with('success', 'Jabatan tambahan berhasil ditambahkan.');
    }

    public function update(Request $request, JabatanTambahanMaster $jabatanTambahanMaster): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jabatan_tambahan_master', 'nama')->ignore($jabatanTambahanMaster->id)],
            'kelompok' => ['required', Rule::in(['struktural', 'fungsional'])],
        ]);

        $jabatanTambahanMaster->update($data);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Data jabatan berhasil diperbarui',
                'item' => $jabatanTambahanMaster->fresh(['guru' => fn ($q) => $q->withoutGlobalScopes()])->loadCount(['guru' => fn ($q) => $q->withoutGlobalScopes()]),
            ], 200);
        }

        return back()->with('success', 'Jabatan tambahan berhasil diperbarui.');
    }

    public function destroy(Request $request, JabatanTambahanMaster $jabatanTambahanMaster): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.delete');

        $guruCount = $jabatanTambahanMaster->guru()->withoutGlobalScopes()->count();
        if ($guruCount > 0) {
            $message = "Jabatan tidak dapat dihapus karena saat ini masih disandang oleh {$guruCount} Guru aktif. Lepaskan tautan jabatan pada guru bersangkutan sebelum menghapusnya.";
            
            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $jabatanTambahanMaster->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jabatan telah dihapus permanen.'], 200);
        }

        return back()->with('success', 'Jabatan telah dihapus permanen.');
    }
}
