<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Actions\Sesi\JadwalkanSesiAction;
use App\Domains\Kasus\Actions\Sesi\UpdateStatusSesiAction;
use App\Domains\Kasus\DataTransferObjects\JadwalkanSesiData;
use App\Domains\Kasus\DataTransferObjects\UpdateStatusSesiData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusSesi;
use App\Domains\Kasus\Enums\StatusKasus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class KasusSesiController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Kasus $kasus, JadwalkanSesiAction $action): RedirectResponse
    {
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasus->trashed(), 404);
        abort_unless(in_array($kasus->status, [StatusKasus::Ditugaskan, StatusKasus::Berjalan, StatusKasus::Eskalasi], true), 403);

        $data = $request->validate([
            'sesi' => ['required', 'array', 'min:1'],
            'sesi.*.dijadwalkan_pada' => ['required', 'date'],
            'sesi.*.peserta' => ['required', 'in:siswa,orang_tua,keduanya'],
            'sesi.*.lokasi_mode' => ['required', 'string', 'max:255'],
        ]);

        $action->execute($kasus, new JadwalkanSesiData(sesi: $data['sesi']));

        return redirect()->route('kasus.show', $kasus)->with('status', 'Sesi berhasil dijadwalkan.');
    }

    public function updateStatus(Request $request, Kasus $kasus, KasusSesi $kasusSesi, UpdateStatusSesiAction $action): RedirectResponse
    {
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasusSesi->kasus_id !== $kasus->id, 404);
        abort_if($kasusSesi->status->value !== 'terjadwal', 403);

        $data = $request->validate([
            'status' => ['required', 'in:selesai,batal,tidak_hadir'],
            'catatan_internal' => ['nullable', 'string'],
            'alasan_batal' => ['required_if:status,batal', 'nullable', 'string'],
        ]);

        $action->execute($kasusSesi, new UpdateStatusSesiData(
            status: $data['status'],
            catatanInternal: $data['catatan_internal'] ?? null,
            alasanBatal: $data['alasan_batal'] ?? null,
        ));

        return redirect()->route('kasus.show', $kasus)->with('status', 'Status sesi berhasil diperbarui.');
    }
}
