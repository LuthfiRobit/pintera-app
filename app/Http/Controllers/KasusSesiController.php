<?php
// app/Http/Controllers/KasusSesiController.php

namespace App\Http\Controllers;

use App\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusSesi;
use App\Models\Scopes\TenantScope;
use App\Notifications\SesiDijadwalkanNotification;
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
        abort_if($kasus->trashed(), 404);
        abort_unless(in_array($kasus->status, [StatusKasus::Ditugaskan, StatusKasus::Berjalan, StatusKasus::Eskalasi], true), 403);

        $data = $request->validate([
            'sesi' => ['required', 'array', 'min:1'],
            'sesi.*.dijadwalkan_pada' => ['required', 'date'],
            'sesi.*.peserta' => ['required', 'in:siswa,orang_tua,keduanya'],
            'sesi.*.lokasi_mode' => ['required', 'string', 'max:255'],
        ]);

        $created = DB::transaction(function () use ($data, $kasus) {
            $rows = collect($data['sesi'])->map(fn ($row) => KasusSesi::create([
                'kasus_id' => $kasus->id,
                'dijadwalkan_pada' => $row['dijadwalkan_pada'],
                'peserta' => $row['peserta'],
                'lokasi_mode' => $row['lokasi_mode'],
            ]));

            if ($kasus->status->value === 'ditugaskan') {
                $kasus->update(['status' => 'berjalan']);
            }

            return $rows;
        });

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();

        foreach ($created as $sesi) {
            if (in_array($sesi->peserta, ['siswa', 'keduanya'], true)) {
                $siswa?->user()->withoutGlobalScope(TenantScope::class)->first()?->notify(new SesiDijadwalkanNotification($sesi));
            }
            if (in_array($sesi->peserta, ['orang_tua', 'keduanya'], true)) {
                $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
                $kontakUtama?->notify(new SesiDijadwalkanNotification($sesi));
            }
        }

        return redirect()->route('kasus.show', $kasus)->with('status', 'Sesi berhasil dijadwalkan.');
    }

    public function updateStatus(Request $request, Kasus $kasus, KasusSesi $kasusSesi): RedirectResponse
    {
        $this->authorize('kasus.view');
        $this->assertKonselorPemegangKasus($kasus);
        abort_if($kasusSesi->kasus_id !== $kasus->id, 404);
        abort_if($kasusSesi->status->value !== 'terjadwal', 403);

        $data = $request->validate([
            'status' => ['required', 'in:selesai,batal,tidak_hadir'],
            'catatan_internal' => ['nullable', 'string'],
            'alasan_batal' => ['required_if:status,batal', 'nullable', 'string'],
        ]);

        $kasusSesi->update([
            'status' => $data['status'],
            'catatan_internal' => $data['catatan_internal'] ?? $kasusSesi->catatan_internal,
            'alasan_batal' => $data['alasan_batal'] ?? null,
        ]);

        return redirect()->route('kasus.show', $kasus)->with('status', 'Status sesi berhasil diperbarui.');
    }

    private function assertKonselorPemegangKasus(Kasus $kasus): void
    {
        $user = auth()->user();
        $karyawanId = $user->karyawan()->withoutGlobalScope(TenantScope::class)->first()?->id;
        $isKonselor = ($kasus->konselor_guru_id !== null && $kasus->konselor_guru_id === $user->guru?->id)
            || ($kasus->konselor_karyawan_id !== null && $kasus->konselor_karyawan_id === $karyawanId);

        abort_unless($isKonselor, 403);
    }
}
