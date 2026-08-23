<?php
// app/Services/PembayaranService.php

namespace App\Services;

use App\Models\Cicilan;
use App\Models\Pembayaran;
use App\Domains\Keuangan\Models\SkemaCicilan;
use App\Models\Tagihan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PembayaranService
{
    /**
     * The only place that ever creates a skema_cicilan + its cicilan rows.
     * Splits evenly with the rounding remainder absorbed by the last termin,
     * so the sum always matches total_tagihan exactly.
     */
    public function buatSkemaCicilan(Tagihan $tagihan, int $jumlahTermin, string $dibuatOleh, ?int $dibuatOlehUserId = null): SkemaCicilan
    {
        if ($tagihan->skemaCicilan()->exists()) {
            throw new RuntimeException('Tagihan ini sudah punya skema cicilan.');
        }

        $totalTagihan = (int) $tagihan->total_tagihan;
        $perTermin = intdiv($totalTagihan, $jumlahTermin);

        return DB::transaction(function () use ($tagihan, $jumlahTermin, $dibuatOleh, $dibuatOlehUserId, $totalTagihan, $perTermin) {
            $skema = SkemaCicilan::create([
                'tagihan_id' => $tagihan->id,
                'jumlah_termin' => $jumlahTermin,
                'dibuat_oleh' => $dibuatOleh,
                'dibuat_oleh_user_id' => $dibuatOlehUserId,
            ]);

            $jatuhTempoTerakhir = null;

            for ($urutan = 1; $urutan <= $jumlahTermin; $urutan++) {
                $nominal = $urutan < $jumlahTermin
                    ? $perTermin
                    : $totalTagihan - ($perTermin * ($jumlahTermin - 1));
                $jatuhTempoTerakhir = now()->addDays(30 * $urutan);

                Cicilan::create([
                    'skema_cicilan_id' => $skema->id,
                    'urutan' => $urutan,
                    'nominal' => $nominal,
                    'jatuh_tempo' => $jatuhTempoTerakhir,
                    'status' => 'belum_bayar',
                ]);
            }

            $tagihan->update(['status' => 'dicicil', 'jatuh_tempo' => $jatuhTempoTerakhir]);

            return $skema->fresh();
        });
    }

    /**
     * Admin-only manual override of per-termin nominal. Rejects (throws,
     * saves nothing) unless the new total matches total_tagihan exactly, and
     * refuses to touch a termin that is already lunas.
     */
    public function simpanNominalManual(SkemaCicilan $skemaCicilan, array $nominalPerUrutan): void
    {
        $cicilanByUrutan = $skemaCicilan->cicilan()->get()->keyBy('urutan');

        if ($cicilanByUrutan->keys()->sort()->values()->all() !== collect(array_keys($nominalPerUrutan))->sort()->values()->all()) {
            throw new InvalidArgumentException('Nominal harus diisi untuk semua termin, tidak boleh sebagian.');
        }

        foreach ($nominalPerUrutan as $urutan => $nominal) {
            if ($cicilanByUrutan[$urutan]->status === 'lunas') {
                throw new InvalidArgumentException("Termin {$urutan} sudah lunas, tidak bisa diubah.");
            }
        }

        $total = array_sum($nominalPerUrutan);
        $totalTagihan = (int) $skemaCicilan->tagihan->total_tagihan;

        if ($total !== $totalTagihan) {
            throw new InvalidArgumentException("Total nominal cicilan (Rp{$total}) harus persis sama dengan total tagihan (Rp{$totalTagihan}).");
        }

        DB::transaction(function () use ($skemaCicilan, $nominalPerUrutan) {
            foreach ($nominalPerUrutan as $urutan => $nominal) {
                $skemaCicilan->cicilan()->where('urutan', $urutan)->update(['nominal' => $nominal]);
            }
        });
    }

    /**
     * The only place a pembayaran row is ever created. Insert-only: never
     * reuses/updates a prior rejected attempt for the same target.
     */
    public function catatPembayaran(?Tagihan $tagihan, ?Cicilan $cicilan, string $sumber, ?string $filePath, ?int $userId): Pembayaran
    {
        if (($tagihan === null) === ($cicilan === null)) {
            throw new InvalidArgumentException('Tepat satu dari tagihan atau cicilan harus diisi.');
        }

        if ($cicilan) {
            $this->pastikanUrutanBoleh($cicilan);
        }

        return DB::transaction(function () use ($tagihan, $cicilan, $sumber, $filePath, $userId) {
            // Lock the target row so concurrent attempts for the same
            // tagihan/cicilan serialize on this row instead of racing past
            // the "already has an active payment" check below.
            if ($tagihan) {
                $tagihan = Tagihan::whereKey($tagihan->id)->lockForUpdate()->first();
            } else {
                $cicilan = Cicilan::whereKey($cicilan->id)->lockForUpdate()->first();
            }

            $adaPembayaranAktif = $tagihan
                ? Pembayaran::where('tagihan_id', $tagihan->id)->whereIn('status', ['menunggu_verifikasi', 'lunas'])->exists()
                : Pembayaran::where('cicilan_id', $cicilan->id)->whereIn('status', ['menunggu_verifikasi', 'lunas'])->exists();

            if ($adaPembayaranAktif) {
                throw new RuntimeException('Sudah ada pembayaran yang menunggu verifikasi atau sudah lunas untuk ini.');
            }

            $statusAwal = $sumber === 'admin' ? 'lunas' : 'menunggu_verifikasi';

            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihan?->id,
                'cicilan_id' => $cicilan?->id,
                'sumber' => $sumber,
                'metode' => 'transfer_manual',
                'file_path' => $filePath,
                'status' => $statusAwal,
                'diverifikasi_oleh_user_id' => $sumber === 'admin' ? $userId : null,
                'diverifikasi_pada' => $sumber === 'admin' ? now() : null,
            ]);

            if ($statusAwal === 'lunas') {
                $this->tandaiLunas($tagihan, $cicilan);
            } elseif ($cicilan) {
                $cicilan->update(['status' => 'menunggu_verifikasi']);
            }

            return $pembayaran;
        });
    }

    /**
     * The only place a pembayaran row's status is ever mutated after
     * creation. On 'lunas', cascades into cicilan/tagihan status via the
     * same shared tandaiLunas() logic catatPembayaran() uses for the
     * sumber=admin fast path — one code path, never duplicated.
     */
    public function verifikasiPembayaran(Pembayaran $pembayaran, string $keputusan, ?string $catatan, int $adminUserId): void
    {
        if ($pembayaran->status !== 'menunggu_verifikasi') {
            throw new RuntimeException('Pembayaran ini sudah diverifikasi sebelumnya.');
        }

        DB::transaction(function () use ($pembayaran, $keputusan, $catatan, $adminUserId) {
            $pembayaran->update([
                'status' => $keputusan,
                'catatan_verifikasi' => $catatan,
                'diverifikasi_oleh_user_id' => $adminUserId,
                'diverifikasi_pada' => now(),
            ]);

            if ($keputusan === 'lunas') {
                $this->tandaiLunas($pembayaran->tagihan, $pembayaran->cicilan);
            } elseif ($pembayaran->cicilan) {
                $pembayaran->cicilan->update(['status' => 'ditolak']);
            }
        });
    }

    private function pastikanUrutanBoleh(Cicilan $cicilan): void
    {
        if ($cicilan->urutan === 1) {
            return;
        }

        $terminSebelumnya = Cicilan::where('skema_cicilan_id', $cicilan->skema_cicilan_id)
            ->where('urutan', $cicilan->urutan - 1)
            ->first();

        if (! $terminSebelumnya || $terminSebelumnya->status !== 'lunas') {
            throw new RuntimeException('Termin sebelumnya belum lunas — bayar berurutan.');
        }
    }

    private function tandaiLunas(?Tagihan $tagihan, ?Cicilan $cicilan): void
    {
        if ($tagihan) {
            $tagihan->update(['status' => 'lunas']);

            return;
        }

        $cicilan->update(['status' => 'lunas']);

        $skema = $cicilan->skemaCicilan;
        $semuaLunas = $skema->cicilan()->where('status', '!=', 'lunas')->doesntExist();

        if ($semuaLunas) {
            $skema->tagihan->update(['status' => 'lunas']);
        }
    }
}
