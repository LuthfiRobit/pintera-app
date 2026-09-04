<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\JenisTesMaster;
use App\Models\SeleksiPpdb;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JenisTesMasterController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;

    public function index(): View
    {
        $this->authorize('jenis-tes.view');

        return view('admin.jenis-tes.index', [
            'jenisTesList' => JenisTesMaster::withCount('seleksi')->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tes.create');

        $isYayasanScope = $request->user()->widestScopeLevel() === 'yayasan';
        if ($isYayasanScope) {
            $lembagaId = $this->resolveActiveLembagaId($request->user());
            if ($lembagaId === null) {
                $message = 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tes.';

                if ($request->wantsJson()) {
                    return response()->json(['message' => $message, 'errors' => ['lembaga_id' => [$message]]], 422);
                }

                return back()->withErrors(['lembaga_id' => $message])->withInput();
            }
        } else {
            $lembagaId = $request->user()->lembaga_id;
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tes_master', 'nama')->where(fn ($query) => $query->where('lembaga_id', $lembagaId))],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($isYayasanScope) {
            $data['lembaga_id'] = $lembagaId;
        }

        $jenisTes = JenisTesMaster::create($data);

        if ($request->wantsJson()) {
            return response()->json(['data' => $jenisTes->fresh()], 201);
        }

        return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil ditambahkan.');
    }

    public function update(Request $request, JenisTesMaster $jenisTes): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tes.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_tes_master', 'nama')
                ->where(fn ($query) => $query->where('lembaga_id', $jenisTes->lembaga_id))
                ->ignore($jenisTes->id)],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ]);

        $jenisTes->update($data);

        if ($request->wantsJson()) {
            return response()->json(['data' => $jenisTes->fresh()->loadCount('seleksi')]);
        }

        return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil diperbarui.');
    }

    public function destroy(Request $request, JenisTesMaster $jenisTes): RedirectResponse|JsonResponse
    {
        $this->authorize('jenis-tes.delete');

        $jumlahSeleksi = SeleksiPpdb::where('jenis_tes_master_id', $jenisTes->id)->count();
        if ($jumlahSeleksi > 0) {
            $message = "Tidak bisa dihapus, jenis tes ini masih dipakai di {$jumlahSeleksi} jadwal seleksi.";

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('admin.jenis-tes.index')->withErrors(['jenis_tes' => $message]);
        }

        $jenisTes->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Jenis tes berhasil dihapus.']);
        }

        return redirect()->route('admin.jenis-tes.index')->with('status', 'Jenis tes berhasil dihapus.');
    }
}
