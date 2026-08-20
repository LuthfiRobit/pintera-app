<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Kalender\CreateKalenderAkademikAction;
use App\Domains\Akademik\Actions\Kalender\DeleteKalenderAkademikAction;
use App\Domains\Akademik\Actions\Kalender\UpdateKalenderAkademikAction;
use App\Domains\Akademik\DataTransferObjects\KalenderAkademikData;
use App\Domains\Akademik\Models\KalenderAkademik;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;

class KalenderAkademikController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, CreateKalenderAkademikAction $action): RedirectResponse|JsonResponse
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

        try {
            $entri = $action->execute(
                new KalenderAkademikData(
                    tanggal: $data['tanggal'],
                    tanggalSelesai: $data['tanggal_selesai'] ?? null,
                    nama: $data['nama'],
                    tipe: $data['tipe'],
                    keterangan: $data['keterangan'] ?? null,
                    berlakuNasional: $nasional,
                ),
                $lembagaId
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($request, $e->validator->errors()->first('tanggal'), 'tanggal');
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $entri], 201);
        }

        return redirect()->route('admin.pengaturan.akademik.index')->with('status', 'Entri kalender berhasil disimpan.');
    }

    public function update(Request $request, KalenderAkademik $kalenderAkademik, UpdateKalenderAkademikAction $action): RedirectResponse|JsonResponse
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

        $kalenderAkademik = $action->execute(
            $kalenderAkademik,
            new KalenderAkademikData(
                tanggal: $kalenderAkademik->tanggal->toDateString(),
                tanggalSelesai: $kalenderAkademik->tanggal_selesai?->toDateString(),
                nama: $data['nama'],
                tipe: $data['tipe'],
                keterangan: $data['keterangan'] ?? null,
                berlakuNasional: $kalenderAkademik->lembaga_id === null,
            )
        );

        if ($request->wantsJson()) {
            return response()->json(['data' => $kalenderAkademik]);
        }

        return redirect()->route('admin.pengaturan.akademik.index')->with('status', 'Entri kalender berhasil diperbarui.');
    }

    public function destroy(Request $request, KalenderAkademik $kalenderAkademik, DeleteKalenderAkademikAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('kalender-akademik.kelola');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        if ($kalenderAkademik->lembaga_id !== null && $kalenderAkademik->lembaga_id !== $lembagaId) {
            abort(404);
        }

        if ($kalenderAkademik->lembaga_id === null) {
            $this->authorize('kalender-akademik.kelola-nasional');
        }

        $action->execute($kalenderAkademik);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Entri kalender berhasil dihapus.']);
        }

        return redirect()->route('admin.pengaturan.akademik.index')->with('status', 'Entri kalender berhasil dihapus.');
    }

    private function errorResponse(Request $request, string $message, string $field): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
        }

        return back()->withErrors([$field => $message])->withInput();
    }
}
