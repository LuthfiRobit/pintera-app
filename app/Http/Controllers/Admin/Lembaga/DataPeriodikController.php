<?php

namespace App\Http\Controllers\Admin\Lembaga;

use App\Http\Controllers\Controller;
use App\Models\Lembaga;
use App\Models\LembagaDataPeriodik;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DataPeriodikController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Lembaga $lembaga): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        $validated = $request->validate([
            'semester_id' => 'required|exists:semester,id',
            'waktu_penyelenggaraan' => 'nullable|string|max:100',
            'sumber_listrik' => 'nullable|string|max:100',
            'daya_listrik' => 'nullable|integer',
            'akses_internet' => 'nullable|string|max:100',
            'status_bos' => 'nullable|boolean',
            'sertifikasi_iso' => 'nullable|string|max:100',
            'ketersediaan_air_bersih' => 'nullable|boolean',
            'kecukupan_air_bersih' => 'nullable|boolean',
            'jumlah_tempat_cuci_tangan' => 'nullable|integer',
            'jumlah_jamban' => 'nullable|integer',
            'stratifikasi_uks' => 'nullable|string|max:100',
            'media_kie_sanitasi' => 'nullable|boolean',
        ]);

        $validated['status_bos'] = $request->boolean('status_bos');
        $validated['ketersediaan_air_bersih'] = $request->boolean('ketersediaan_air_bersih');
        $validated['kecukupan_air_bersih'] = $request->boolean('kecukupan_air_bersih');
        $validated['media_kie_sanitasi'] = $request->boolean('media_kie_sanitasi');

        $lembaga->dataPeriodik()->updateOrCreate(
            ['semester_id' => $validated['semester_id']],
            $validated
        );

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#data-periodik')
            ->with('status', 'Data periodik berhasil disimpan.');
    }

    public function update(Request $request, Lembaga $lembaga, LembagaDataPeriodik $dataPeriodik): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($dataPeriodik->lembaga_id === $lembaga->id, 404);

        $validated = $request->validate([
            'semester_id' => 'required|exists:semester,id',
            'waktu_penyelenggaraan' => 'nullable|string|max:100',
            'sumber_listrik' => 'nullable|string|max:100',
            'daya_listrik' => 'nullable|integer',
            'akses_internet' => 'nullable|string|max:100',
            'status_bos' => 'nullable|boolean',
            'sertifikasi_iso' => 'nullable|string|max:100',
            'ketersediaan_air_bersih' => 'nullable|boolean',
            'kecukupan_air_bersih' => 'nullable|boolean',
            'jumlah_tempat_cuci_tangan' => 'nullable|integer',
            'jumlah_jamban' => 'nullable|integer',
            'stratifikasi_uks' => 'nullable|string|max:100',
            'media_kie_sanitasi' => 'nullable|boolean',
        ]);

        $validated['status_bos'] = $request->boolean('status_bos');
        $validated['ketersediaan_air_bersih'] = $request->boolean('ketersediaan_air_bersih');
        $validated['kecukupan_air_bersih'] = $request->boolean('kecukupan_air_bersih');
        $validated['media_kie_sanitasi'] = $request->boolean('media_kie_sanitasi');

        $dataPeriodik->update($validated);

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#data-periodik')
            ->with('status', 'Data periodik berhasil diperbarui.');
    }

    public function destroy(Request $request, Lembaga $lembaga, LembagaDataPeriodik $dataPeriodik): RedirectResponse
    {
        $this->authorize('lembaga.edit');
        abort_unless($dataPeriodik->lembaga_id === $lembaga->id, 404);

        $dataPeriodik->delete();

        return redirect()->to(route('admin.lembaga.edit', $lembaga) . '#data-periodik')
            ->with('status', 'Data periodik berhasil dihapus.');
    }
}
