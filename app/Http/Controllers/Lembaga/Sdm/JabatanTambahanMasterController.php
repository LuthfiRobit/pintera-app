<?php

namespace App\Http\Controllers\Lembaga\Sdm;

use App\Domains\Sdm\Actions\JabatanTambahan\CreateJabatanTambahanAction;
use App\Domains\Sdm\Actions\JabatanTambahan\DeleteJabatanTambahanAction;
use App\Domains\Sdm\Actions\JabatanTambahan\UpdateJabatanTambahanAction;
use App\Domains\Sdm\DataTransferObjects\JabatanTambahanMasterData;
use App\Domains\Sdm\Models\JabatanTambahanMaster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

        return view('portals.lembaga.sdm.jabatan-tambahan-master.index', [
            'jabatanList' => $jabatanList,
        ]);
    }

    public function store(Request $request, CreateJabatanTambahanAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:jabatan_tambahan_master,nama'],
            'kelompok' => ['required', Rule::in(['struktural', 'fungsional'])],
        ]);

        $item = $action->execute(JabatanTambahanMasterData::fromArray($data));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jabatan tambahan berhasil dirilis',
                'item' => $item,
            ], 201);
        }

        return back()->with('success', 'Jabatan tambahan berhasil ditambahkan.');
    }

    public function update(Request $request, JabatanTambahanMaster $jabatanTambahanMaster, UpdateJabatanTambahanAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jabatan_tambahan_master', 'nama')->ignore($jabatanTambahanMaster->id)],
            'kelompok' => ['required', Rule::in(['struktural', 'fungsional'])],
        ]);

        $item = $action->execute($jabatanTambahanMaster, JabatanTambahanMasterData::fromArray($data));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Data jabatan berhasil diperbarui',
                'item' => $item,
            ], 200);
        }

        return back()->with('success', 'Jabatan tambahan berhasil diperbarui.');
    }

    public function destroy(Request $request, JabatanTambahanMaster $jabatanTambahanMaster, DeleteJabatanTambahanAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('jabatan-tambahan-master.delete');

        try {
            $action->execute($jabatanTambahanMaster);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['jabatan'][0];

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jabatan telah dihapus permanen.'], 200);
        }

        return back()->with('success', 'Jabatan telah dihapus permanen.');
    }
}
