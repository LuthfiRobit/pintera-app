<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Sdm\Models\JenisKaryawanMaster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
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

        return view('admin.jenis-karyawan-master.index', [
            'jenisList' => $jenisList,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('jenis-karyawan-master.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:jenis_karyawan_master,nama'],
        ]);

        $item = JenisKaryawanMaster::create($data)->loadCount('karyawan');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jenis karyawan berhasil ditambahkan.',
                'item' => $item,
            ], 201);
        }

        return back()->with('success', 'Jenis karyawan berhasil ditambahkan.');
    }

    public function update(Request $request, JenisKaryawanMaster $jenisKaryawanMaster): JsonResponse|RedirectResponse
    {
        $this->authorize('jenis-karyawan-master.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_karyawan_master', 'nama')->ignore($jenisKaryawanMaster->id)],
        ]);

        $jenisKaryawanMaster->update($data);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Jenis karyawan berhasil diperbarui.',
                'item' => $jenisKaryawanMaster->fresh()->loadCount('karyawan'),
            ], 200);
        }

        return back()->with('success', 'Jenis karyawan berhasil diperbarui.');
    }

    public function destroy(Request $request, JenisKaryawanMaster $jenisKaryawanMaster): JsonResponse|RedirectResponse
    {
        $this->authorize('jenis-karyawan-master.delete');

        $karyawanCount = $jenisKaryawanMaster->karyawan()->count();
        if ($karyawanCount > 0) {
            $message = "Jenis karyawan tidak dapat dihapus karena masih dipakai oleh {$karyawanCount} karyawan.";

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $jenisKaryawanMaster->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jenis karyawan telah dihapus.'], 200);
        }

        return back()->with('success', 'Jenis karyawan telah dihapus.');
    }
}
