<?php

namespace App\Http\Controllers\Lembaga\Sdm;

use App\Domains\Sdm\Actions\JenisKaryawan\CreateJenisKaryawanAction;
use App\Domains\Sdm\Actions\JenisKaryawan\DeleteJenisKaryawanAction;
use App\Domains\Sdm\Actions\JenisKaryawan\UpdateJenisKaryawanAction;
use App\Domains\Sdm\DataTransferObjects\JenisKaryawanMasterData;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JenisKaryawanMasterController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('jenis-karyawan-master.view');

        $jenisList = JenisKaryawanMaster::withCount('karyawan')->orderBy('nama')->get();

        if ($request->wantsJson()) {
            return response()->json(['items' => $jenisList]);
        }

        return view('portals.lembaga.sdm.jenis-karyawan-master.index', [
            'jenisList' => $jenisList,
        ]);
    }

    public function store(Request $request, CreateJenisKaryawanAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('jenis-karyawan-master.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:jenis_karyawan_master,nama'],
        ]);

        $item = $action->execute(JenisKaryawanMasterData::fromArray($data));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jenis karyawan berhasil ditambahkan.',
                'item' => $item,
            ], 201);
        }

        return back()->with('success', 'Jenis karyawan berhasil ditambahkan.');
    }

    public function update(Request $request, JenisKaryawanMaster $jenisKaryawanMaster, UpdateJenisKaryawanAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('jenis-karyawan-master.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_karyawan_master', 'nama')->ignore($jenisKaryawanMaster->id)],
        ]);

        $item = $action->execute($jenisKaryawanMaster, JenisKaryawanMasterData::fromArray($data));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jenis karyawan berhasil diperbarui.',
                'item' => $item,
            ], 200);
        }

        return back()->with('success', 'Jenis karyawan berhasil diperbarui.');
    }

    public function destroy(Request $request, JenisKaryawanMaster $jenisKaryawanMaster, DeleteJenisKaryawanAction $action): JsonResponse|RedirectResponse
    {
        $this->authorize('jenis-karyawan-master.delete');

        try {
            $action->execute($jenisKaryawanMaster);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['jenis_karyawan'][0];

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jenis karyawan telah dihapus.'], 200);
        }

        return back()->with('success', 'Jenis karyawan telah dihapus.');
    }
}
