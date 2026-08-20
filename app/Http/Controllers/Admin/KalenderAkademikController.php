<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Models\KalenderAkademik;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class KalenderAkademikController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string'],
            'berlaku_nasional' => ['nullable', 'boolean'],
        ]);

        $nasional = $request->boolean('berlaku_nasional');

        if ($nasional) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        if (! $nasional && $request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {
            return $this->errorResponse($request, 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah entri kalender.', 'lembaga_id');
        }

        $lembagaId = $nasional ? null : ($request->user()->lembaga_id ?? session('active_lembaga_id'));
        $tanggalSelesai = $data['tanggal_selesai'] ?? $data['tanggal'];

        if ($this->tumpangTindih($lembagaId, $data['tanggal'], $tanggalSelesai)) {
            return $this->errorResponse($request, 'Rentang tanggal ini tumpang tindih dengan entri lain pada cakupan yang sama.', 'tanggal');
        }

        $entri = KalenderAkademik::create([
            'lembaga_id' => $lembagaId,
            'tanggal' => $data['tanggal'],
            'tanggal_selesai' => $tanggalSelesai,
            'nama' => $data['nama'],
            'tipe' => $data['tipe'],
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['data' => $entri->fresh()], 201);
        }

        return redirect()->route('admin.pengaturan.akademik.index')->with('status', 'Entri kalender berhasil disimpan.');
    }

    public function update(Request $request, KalenderAkademik $kalenderAkademik): RedirectResponse|JsonResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        if ($kalenderAkademik->lembaga_id !== null && $kalenderAkademik->lembaga_id !== $lembagaId) {
            abort(404);
        }

        if ($kalenderAkademik->lembaga_id === null) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:libur,kerja'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $kalenderAkademik->update($data);

        if ($request->wantsJson()) {
            return response()->json(['data' => $kalenderAkademik->fresh()]);
        }

        return redirect()->route('admin.pengaturan.akademik.index')->with('status', 'Entri kalender berhasil diperbarui.');
    }

    public function destroy(Request $request, KalenderAkademik $kalenderAkademik): RedirectResponse|JsonResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        if ($kalenderAkademik->lembaga_id !== null && $kalenderAkademik->lembaga_id !== $lembagaId) {
            abort(404);
        }

        if ($kalenderAkademik->lembaga_id === null) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        $kalenderAkademik->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Entri kalender berhasil dihapus.']);
        }

        return redirect()->route('admin.pengaturan.akademik.index')->with('status', 'Entri kalender berhasil dihapus.');
    }

    /**
     * Detects whether [$mulai, $selesai] overlaps an existing entry in the
     * same scope (same lembaga_id, or both national when $lembagaId is
     * null). Mirrors KalenderAkademikResolver::cocokRentang's handling of a
     * null tanggal_selesai: such a row is a single-day entry whose
     * *effective* end date is its own `tanggal`, not an open-ended/unbounded
     * range. Treating "tanggal_selesai IS NULL" as unconditionally
     * overlapping (i.e. ORing it in without also checking the existing
     * row's `tanggal` against $mulai) produces false positives for any new
     * range that starts after such a single-day entry.
     */
    private function tumpangTindih(?int $lembagaId, string $mulai, string $selesai, ?int $kecualiId = null): bool
    {
        return KalenderAkademik::where(fn ($q) => $lembagaId === null ? $q->whereNull('lembaga_id') : $q->where('lembaga_id', $lembagaId))
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->where('tanggal', '<=', $selesai)
            ->where(fn ($q) => $q->where('tanggal_selesai', '>=', $mulai)
                ->orWhere(fn ($q2) => $q2->whereNull('tanggal_selesai')->where('tanggal', '>=', $mulai))
            )
            ->exists();
    }

    private function errorResponse(Request $request, string $message, string $field): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
        }

        return back()->withErrors([$field => $message])->withInput();
    }
}
