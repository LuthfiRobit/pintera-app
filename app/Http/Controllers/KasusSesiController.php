<?php
// app/Http/Controllers/KasusSesiController.php

namespace App\Http\Controllers;

use App\Models\Kasus;
use App\Models\KasusSesi;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class KasusSesiController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Kasus $kasus): RedirectResponse
    {
        $this->authorize('kasus.view');
        $this->assertKonselorPemegangKasus($kasus);

        $data = $request->validate([
            'sesi' => ['required', 'array', 'min:1'],
            'sesi.*.dijadwalkan_pada' => ['required', 'date'],
            'sesi.*.peserta' => ['required', 'in:siswa,orang_tua,keduanya'],
            'sesi.*.lokasi_mode' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $kasus) {
            foreach ($data['sesi'] as $row) {
                KasusSesi::create([
                    'kasus_id' => $kasus->id,
                    'dijadwalkan_pada' => $row['dijadwalkan_pada'],
                    'peserta' => $row['peserta'],
                    'lokasi_mode' => $row['lokasi_mode'],
                ]);
            }
        });

        return redirect()->route('kasus.show', $kasus)->with('status', 'Sesi berhasil dijadwalkan.');
    }

    private function assertKonselorPemegangKasus(Kasus $kasus): void
    {
        $user = auth()->user();
        $isKonselor = ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $user->karyawan?->id);

        abort_unless($isKonselor, 403);
    }
}
