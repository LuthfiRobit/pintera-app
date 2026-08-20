<?php
// app/Http/Controllers/KasusEvaluasiController.php

namespace App\Http\Controllers;

use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusEvaluasi;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Notifications\KasusDikembalikanNotification;
use App\Notifications\KasusEskalasiNotification;
use App\Notifications\KasusSelesaiNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class KasusEvaluasiController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Kasus $kasus): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasus->trashed(), 404);
        $user = auth()->user();
        $originalStatus = $kasus->status->value;

        if ($originalStatus === 'berjalan') {
            $isKonselor = ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
                || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id);
            abort_unless($isKonselor, 403);

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

        DB::transaction(function () use ($kasus, $data, $newStatus, $user) {
            KasusEvaluasi::create([
                'kasus_id' => $kasus->id,
                'tanggal' => now(),
                'catatan' => $data['catatan'],
                'keputusan' => $data['keputusan'],
                'dibuat_oleh_user_id' => $user->id,
            ]);

            if ($newStatus !== $kasus->status->value) {
                $kasus->update(['status' => $newStatus]);
            }
        });

        $this->notifyEvaluasi($kasus, $data['keputusan'], $originalStatus);

        return redirect()->route('kasus.show', $kasus)->with('status', 'Evaluasi berhasil disimpan.');
    }

    private function notifyEvaluasi(Kasus $kasus, string $keputusan, string $originalStatus): void
    {
        if ($keputusan === 'eskalasi') {
            $admins = User::withoutGlobalScope(TenantScope::class)
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin_akademik'))
                ->where('lembaga_id', $kasus->lembaga_id)
                ->get();
            foreach ($admins as $admin) {
                $admin->notify(new KasusEskalasiNotification($kasus));
            }

            return;
        }

        if ($keputusan === 'lanjut' && $originalStatus === 'eskalasi') {
            $konselorUser = $kasus->konselor_guru_id !== null
                ? $kasus->konselorGuru()->withoutGlobalScope(TenantScope::class)->first()?->user()->withoutGlobalScope(TenantScope::class)->first()
                : $kasus->konselorKaryawan()->withoutGlobalScope(TenantScope::class)->first()?->user()->withoutGlobalScope(TenantScope::class)->first();
            $konselorUser?->notify(new KasusDikembalikanNotification($kasus));

            return;
        }

        if ($keputusan === 'selesai') {
            $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
            $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
            $kontakUtama?->notify(new KasusSelesaiNotification($kasus));

            $guruPengaju = $kasus->diajukanOlehGuru()->withoutGlobalScope(TenantScope::class)->first();
            $guruPengaju?->user()->withoutGlobalScope(TenantScope::class)->first()?->notify(new KasusSelesaiNotification($kasus));
        }
    }
}
