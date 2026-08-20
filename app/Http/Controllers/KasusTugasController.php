<?php
// app/Http/Controllers/KasusTugasController.php

namespace App\Http\Controllers;

use App\Domains\Kasus\Enums\StatusKasus;
use App\Http\Controllers\Concerns\AssertsKonselorPemegangKasus;
use App\Http\Requests\StoreKasusTugasBatchRequest;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
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
    use AssertsKonselorPemegangKasus;

    public function store(StoreKasusTugasBatchRequest $request, Kasus $kasus, TugasBatchGenerator $generator): RedirectResponse
    {
        $this->authorize('kasus.view');
        $this->assertKonselorPemegangKasus($kasus);
        abort_if($kasus->trashed(), 404);
        abort_unless(in_array($kasus->status, [StatusKasus::Ditugaskan, StatusKasus::Berjalan, StatusKasus::Eskalasi], true), 403);

        $data = $request->validated();

        [$tanggalPengumpulanBulanan, $akhirBulan] = $generator->parseTanggalPengumpulanBulanan($data['tanggal_pengumpulan_bulanan'] ?? null);

        $tanggalMulai = Carbon::parse($data['tanggal_mulai']);
        $tanggalSelesai = Carbon::parse($data['tanggal_selesai']);

        // Frekuensi yang benar-benar dipakai bisa berbeda dari yang dipilih konselor (fallback
        // bulanan->mingguan atau mingguan->harian jika rentangnya terlalu pendek). Baris yang
        // dibuat harus mencatat frekuensi INI, sama seperti yang sudah ditampilkan di pratinjau,
        // bukan nilai form mentah — lihat KasusTugasBatchPreviewController::preview().
        $frekuensiAkhir = $generator->tentukanFrekuensiAkhir($data['frekuensi'], $tanggalMulai, $tanggalSelesai);

        $barisTanggal = $generator->generate(
            $data['frekuensi'],
            $tanggalMulai,
            $tanggalSelesai,
            $tanggalPengumpulanBulanan,
            $akhirBulan,
        );

        $created = DB::transaction(function () use ($data, $kasus, $barisTanggal, $frekuensiAkhir) {
            $batchId = (string) Str::uuid();
            $batchTotal = $barisTanggal->count();

            $rows = $barisTanggal->values()->map(fn ($baris, $index) => KasusTugas::create([
                'kasus_id' => $kasus->id,
                'judul' => $data['judul'],
                'instruksi' => $data['instruksi'],
                'frekuensi' => $frekuensiAkhir,
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
}
