<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\PolaJam\AssignKelasToPolaJamAction;
use App\Domains\Akademik\Actions\PolaJam\CreatePolaJamAction;
use App\Domains\Akademik\Actions\PolaJam\DeletePolaJamAction;
use App\Domains\Akademik\Actions\PolaJam\DuplicatePolaJamAction;
use App\Domains\Akademik\Actions\PolaJam\UpdatePolaJamAction;
use App\Domains\Akademik\DataTransferObjects\AssignKelasData;
use App\Domains\Akademik\DataTransferObjects\PolaJamData;
use App\Domains\Akademik\Models\PolaJam;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\Kelas;
use App\Models\Lembaga;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PolaJamController extends BaseController
{
    use AuthorizesRequests;
    use ResolveLembagaScopeTrait;

    /**
     * Info scope yayasan/lembaga yang sedang aktif, ditampilkan sebagai badge di header
     * halaman (pola sama seperti admin/siswa/index.blade.php) -- HANYA relevan untuk aktor
     * berscope yayasan (punya switcher lembaga).
     *
     * @return array{isYayasan: bool, activeLembaga: ?Lembaga}
     */
    private function scopeHeaderData(Request $request): array
    {
        $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
        $lembagaId = $this->resolveActiveLembagaId($request->user());

        return [
            'isYayasan' => $isYayasan,
            'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
        ];
    }

    public function index(Request $request): View
    {
        $this->authorize('pola-jam.view');

        $data = [
            'polaJamList' => PolaJam::with(['jamPelajaran', 'lembaga', 'kelas.tahunAjaran'])->orderBy('nama')->get(),
            'kelasList' => Kelas::with(['tahunAjaran', 'polaJam'])->orderBy('nama')->get(),
            ...$this->scopeHeaderData($request),
        ];

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('portals.lembaga.akademik.pola-jam._daftar', $data);
        }

        return view('portals.lembaga.akademik.pola-jam.index', $data);
    }

    public function store(Request $request, CreatePolaJamAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('pola-jam.create');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;

        if ($lembagaId === null) {
            $msg = 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $msg,
                    'errors' => ['lembaga_id' => [$msg]],
                ], 422);
            }

            return back()->withErrors(['lembaga_id' => $msg])->withInput();
        }

        $polaJam = $action->execute(new PolaJamData(nama: $data['nama'], lembagaId: $lembagaId));

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Pola jam berhasil dibuat.',
                'data' => $polaJam,
            ], 201);
        }

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dibuat.');
    }

    public function update(Request $request, PolaJam $polaJam, UpdatePolaJamAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('pola-jam.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        $action->execute($polaJam, new PolaJamData(nama: $data['nama'], lembagaId: $polaJam->lembaga_id));

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Pola jam berhasil diperbarui.',
                'data' => $polaJam->fresh(),
            ]);
        }

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil diperbarui.');
    }

    public function destroy(Request $request, PolaJam $polaJam, DeletePolaJamAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('pola-jam.delete');

        try {
            $action->execute($polaJam);
        } catch (ValidationException $e) {
            $msg = $e->validator->errors()->first('pola_jam');
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $msg,
                    'errors' => ['pola_jam' => [$msg]],
                ], 422);
            }

            return back()->withErrors(['pola_jam' => $msg]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Pola jam berhasil dihapus.',
            ]);
        }

        return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dihapus.');
    }

    public function assignKelas(Request $request, PolaJam $polaJam, AssignKelasToPolaJamAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('kelas.edit');

        $data = $request->validate([
            'kelas_ids' => ['nullable', 'array'],
            'kelas_ids.*' => ['integer'],
        ]);

        try {
            $action->execute($polaJam, new AssignKelasData(kelasIds: $data['kelas_ids'] ?? []));
        } catch (ValidationException $e) {
            $msg = $e->validator->errors()->first('kelas_ids');
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $msg,
                    'errors' => ['kelas_ids' => [$msg]],
                ], 422);
            }

            return back()->withErrors(['kelas_ids' => $msg]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tautan kelas untuk pola jam ini berhasil disimpan.',
            ]);
        }

        return redirect()->route('admin.pola-jam.index')->with('status', 'Tautan kelas untuk pola jam ini berhasil disimpan.');
    }

    public function duplicate(Request $request, PolaJam $polaJam, DuplicatePolaJamAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('pola-jam.create');

        [$newPola, $count] = $action->execute($polaJam);

        $status = "Pola jam \"{$polaJam->nama}\" beserta {$count} slot jam berhasil diduplikasi.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $status,
                'data' => $newPola,
            ]);
        }

        return redirect()->route('admin.pola-jam.index')->with('status', $status);
    }
}
