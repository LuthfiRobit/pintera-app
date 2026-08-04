<?php
// app/Http/Controllers/KasusTugasController.php

namespace App\Http\Controllers;

use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\Scopes\TenantScope;
use App\Notifications\TugasDitugaskanNotification;
use App\Notifications\TugasSelesaiNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class KasusTugasController extends BaseController
{
    use AuthorizesRequests;

    public function store(Request $request, Kasus $kasus): RedirectResponse
    {
        $this->authorize('kasus.view');
        $this->assertKonselorPemegangKasus($kasus);

        $data = $request->validate([
            'tugas' => ['required', 'array', 'min:1'],
            'tugas.*.judul' => ['required', 'string', 'max:255'],
            'tugas.*.instruksi' => ['required', 'string'],
            'tugas.*.frekuensi' => ['required', 'in:sekali,harian,mingguan,bulanan'],
            'tugas.*.mulai_pada' => ['required', 'date'],
            'tugas.*.batas_selesai_pada' => ['required', 'date', 'after_or_equal:tugas.*.mulai_pada'],
        ]);

        $created = DB::transaction(function () use ($data, $kasus) {
            return collect($data['tugas'])->map(fn ($row) => KasusTugas::create([
                'kasus_id' => $kasus->id,
                'judul' => $row['judul'],
                'instruksi' => $row['instruksi'],
                'frekuensi' => $row['frekuensi'],
                'mulai_pada' => $row['mulai_pada'],
                'batas_selesai_pada' => $row['batas_selesai_pada'],
            ]));
        });

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();

        foreach ($created as $tugas) {
            $siswa?->user()->withoutGlobalScope(TenantScope::class)->first()?->notify(new TugasDitugaskanNotification($tugas));
            $kontakUtama?->notify(new TugasDitugaskanNotification($tugas));
        }

        return redirect()->route('kasus.show', $kasus)->with('status', 'Tugas berhasil diberikan.');
    }

    public function markSelesai(Kasus $kasus, KasusTugas $kasusTugas): RedirectResponse
    {
        $this->authorize('kasus.view');
        abort_if($kasusTugas->kasus_id !== $kasus->id, 404);
        $this->assertKonselorPemegangKasus($kasus);

        $kasusTugas->update(['status' => 'selesai']);

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        $kontakUtama?->notify(new TugasSelesaiNotification($kasusTugas));

        return redirect()->route('kasus.show', $kasus)->with('status', 'Tugas ditandai selesai.');
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
