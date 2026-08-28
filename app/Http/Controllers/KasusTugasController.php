<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Actions\Tugas\BeriTugasBatchAction;
use App\Domains\Kasus\Actions\Tugas\TandaiTugasSelesaiAction;
use App\Domains\Kasus\DataTransferObjects\BeriTugasBatchData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Http\Requests\StoreKasusTugasBatchRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;

class KasusTugasController extends BaseController
{
    use AuthorizesRequests;

    public function store(StoreKasusTugasBatchRequest $request, Kasus $kasus, BeriTugasBatchAction $action): RedirectResponse
    {
        $this->authorize('kelolaSesiTugas', $kasus);
        abort_if($kasus->trashed(), 404);
        abort_unless(in_array($kasus->status, [StatusKasus::Ditugaskan, StatusKasus::Berjalan, StatusKasus::Eskalasi], true), 403);

        $data = $request->validated();

        $created = $action->execute($kasus, new BeriTugasBatchData(
            judul: $data['judul'],
            instruksi: $data['instruksi'],
            frekuensi: $data['frekuensi'],
            tanggalMulai: $data['tanggal_mulai'],
            tanggalSelesai: $data['tanggal_selesai'],
            tanggalPengumpulanBulananRaw: $data['tanggal_pengumpulan_bulanan'] ?? null,
        ));

        return redirect()->route('kasus.show', $kasus)->with('status', "Tugas berhasil diberikan ({$created->count()} baris dibuat).");
    }

    public function markSelesai(Kasus $kasus, KasusTugas $kasusTugas, TandaiTugasSelesaiAction $action): RedirectResponse
    {
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        $this->authorize('kelolaSesiTugas', $kasus);

        $action->execute($kasus, $kasusTugas);

        return redirect()->route('kasus.show', $kasus)->with('status', 'Tugas ditandai selesai.');
    }
}
