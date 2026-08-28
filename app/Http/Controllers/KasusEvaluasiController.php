<?php

namespace App\Http\Controllers;

use App\Domains\Kasus\Actions\Evaluasi\CatatEvaluasiAction;
use App\Domains\Kasus\DataTransferObjects\CatatEvaluasiData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Policies\KasusPolicy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class KasusEvaluasiController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Kasus $kasus, CatatEvaluasiAction $action, KasusPolicy $policy): RedirectResponse
    {
        abort_if($kasus->trashed(), 404);
        $user = auth()->user();
        $originalStatus = $kasus->status->value;

        if ($originalStatus === 'berjalan') {
            abort_unless($policy->isKonselor($user, $kasus), 403);

            $data = $request->validate([
                'catatan' => ['required', 'string'],
                'keputusan' => ['required', 'in:lanjut,eskalasi,selesai'],
            ]);
        } elseif ($originalStatus === 'eskalasi') {
            $this->authorize('kasus.triase');
            abort_if($user->widestScopeLevel() !== 'yayasan' && $kasus->lembaga_id !== $user->lembaga_id, 404);

            $data = $request->validate([
                'catatan' => ['required', 'string'],
                'keputusan' => ['required', 'in:lanjut,selesai'],
            ]);
        } else {
            abort(404);
        }

        $newStatus = match (true) {
            $data['keputusan'] === 'eskalasi' => 'eskalasi',
            $data['keputusan'] === 'selesai' => 'selesai',
            $data['keputusan'] === 'lanjut' && $originalStatus === 'eskalasi' => 'berjalan',
            default => $originalStatus,
        };

        $action->execute(
            $kasus,
            new CatatEvaluasiData(catatan: $data['catatan'], keputusan: $data['keputusan'], dibuatOlehUserId: $user->id),
            newStatus: $newStatus,
            originalStatus: $originalStatus,
        );

        return redirect()->route('kasus.show', $kasus)->with('status', 'Evaluasi berhasil disimpan.');
    }
}
