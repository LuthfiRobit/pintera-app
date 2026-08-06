<?php
// app/Http/Controllers/KasusTugasController.php

namespace App\Http\Controllers;

use App\Enums\StatusKasus;
use App\Http\Requests\StoreKasusTugasBatchRequest;
use App\Models\Kasus;
use App\Models\KasusTugas;
use App\Models\Scopes\TenantScope;
use App\Notifications\TugasBatchDibuatNotification;
use App\Notifications\TugasSelesaiNotification;
use App\Services\TugasBatchGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KasusTugasController extends BaseController
{
    use AuthorizesRequests;

    public function store(StoreKasusTugasBatchRequest $request, Kasus $kasus, TugasBatchGenerator $generator): RedirectResponse
    {
        $this->authorize('kasus.view');
        $this->assertKonselorPemegangKasus($kasus);
        abort_if($kasus->trashed(), 404);
        abort_unless(in_array($kasus->status, [StatusKasus::Ditugaskan, StatusKasus::Berjalan, StatusKasus::Eskalasi], true), 403);

        $data = $request->validated();

        $tanggalPengumpulanBulanan = null;
        $akhirBulan = false;
        if (($data['tanggal_pengumpulan_bulanan'] ?? null) === 'akhir_bulan') {
            $akhirBulan = true;
        } elseif (! empty($data['tanggal_pengumpulan_bulanan'])) {
            $tanggalPengumpulanBulanan = (int) $data['tanggal_pengumpulan_bulanan'];
        }

        $barisTanggal = $generator->generate(
            $data['frekuensi'],
            Carbon::parse($data['tanggal_mulai']),
            Carbon::parse($data['tanggal_selesai']),
            $tanggalPengumpulanBulanan,
            $akhirBulan,
        );

        $created = DB::transaction(function () use ($data, $kasus, $barisTanggal) {
            $batchId = (string) Str::uuid();
            $batchTotal = $barisTanggal->count();

            $rows = $barisTanggal->values()->map(fn ($baris, $index) => KasusTugas::create([
                'kasus_id' => $kasus->id,
                'judul' => $data['judul'],
                'instruksi' => $data['instruksi'],
                'frekuensi' => $data['frekuensi'],
                'batch_id' => $batchId,
                'batch_urutan' => $index + 1,
                'batch_total' => $batchTotal,
                'mulai_pada' => $baris['mulai_pada'],
                'batas_selesai_pada' => $baris['batas_selesai_pada'],
            ]));

            if ($kasus->status->value === 'ditugaskan') {
                $kasus->update(['status' => 'berjalan']);
            }

            return $rows;
        });

        $siswa = $kasus->siswa()->withoutGlobalScope(TenantScope::class)->first();
        $kontakUtama = $siswa?->orangTua()->wherePivot('is_kontak_utama', true)->first();
        $siswaUser = $siswa?->user()->withoutGlobalScope(TenantScope::class)->first();

        // Satu notifikasi ringkasan per penerima untuk SELURUH batch, bukan satu notifikasi
        // per baris — sebuah batch harian/bulanan yang panjang bisa menghasilkan puluhan
        // baris kasus_tugas dalam satu submit, dan mengirim notifikasi terpisah untuk
        // masing-masing akan membanjiri siswa/orang tua (keputusan desain 2026-08-06).
        $siswaUser?->notify(new TugasBatchDibuatNotification($created));
        $kontakUtama?->notify(new TugasBatchDibuatNotification($created));

        return redirect()->route('kasus.show', $kasus)->with('status', "Tugas berhasil diberikan ({$created->count()} baris dibuat).");
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
