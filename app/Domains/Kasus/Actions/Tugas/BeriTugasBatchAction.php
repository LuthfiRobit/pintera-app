<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Tugas;

use App\Domains\Kasus\DataTransferObjects\BeriTugasBatchData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Services\TugasBatchGenerator;
use App\Models\Scopes\TenantScope;
use App\Notifications\TugasBatchDibuatNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BeriTugasBatchAction
{
    public function __construct(
        private readonly TugasBatchGenerator $generator
    ) {}

    public function execute(Kasus $kasus, BeriTugasBatchData $data): Collection
    {
        [$tanggalPengumpulanBulanan, $akhirBulan] = $this->generator->parseTanggalPengumpulanBulanan($data->tanggalPengumpulanBulananRaw);

        $tanggalMulai = Carbon::parse($data->tanggalMulai);
        $tanggalSelesai = Carbon::parse($data->tanggalSelesai);

        // Frekuensi yang benar-benar dipakai bisa berbeda dari yang dipilih konselor (fallback
        // bulanan->mingguan atau mingguan->harian jika rentangnya terlalu pendek). Baris yang
        // dibuat harus mencatat frekuensi INI, sama seperti yang sudah ditampilkan di pratinjau,
        // bukan nilai form mentah — lihat KasusTugasBatchPreviewController::preview().
        $frekuensiAkhir = $this->generator->tentukanFrekuensiAkhir($data->frekuensi, $tanggalMulai, $tanggalSelesai);

        $barisTanggal = $this->generator->generate(
            $data->frekuensi,
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
                'judul' => $data->judul,
                'instruksi' => $data->instruksi,
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

        return $created;
    }
}
