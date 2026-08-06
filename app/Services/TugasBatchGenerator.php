<?php
// app/Services/TugasBatchGenerator.php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class TugasBatchGenerator
{
    public function tentukanFrekuensiAkhir(string $frekuensiDipilih, Carbon $tanggalMulai, Carbon $tanggalSelesai): string
    {
        $selisihHari = $tanggalMulai->diffInDays($tanggalSelesai);

        $frekuensiAkhir = $frekuensiDipilih;

        if ($frekuensiAkhir === 'bulanan' && $selisihHari <= 30) {
            $frekuensiAkhir = 'mingguan';
        }

        if ($frekuensiAkhir === 'mingguan' && $selisihHari <= 7) {
            $frekuensiAkhir = 'harian';
        }

        return $frekuensiAkhir;
    }

    /**
     * @return Collection<int, array{mulai_pada: Carbon, batas_selesai_pada: Carbon}>
     */
    public function generate(
        string $frekuensiDipilih,
        Carbon $tanggalMulai,
        Carbon $tanggalSelesai,
        ?int $tanggalPengumpulanBulanan = null,
        bool $akhirBulan = false,
    ): Collection {
        $frekuensiAkhir = $this->tentukanFrekuensiAkhir($frekuensiDipilih, $tanggalMulai, $tanggalSelesai);

        return match ($frekuensiAkhir) {
            'sekali' => collect([
                ['mulai_pada' => $tanggalMulai->copy(), 'batas_selesai_pada' => $tanggalSelesai->copy()],
            ]),
            'harian' => $this->generateHarian($tanggalMulai, $tanggalSelesai),
            'mingguan' => $this->generateMingguan($tanggalMulai, $tanggalSelesai),
            'bulanan' => $this->generateBulanan($tanggalMulai, $tanggalSelesai, $tanggalPengumpulanBulanan, $akhirBulan),
        };
    }

    private function generateHarian(Carbon $tanggalMulai, Carbon $tanggalSelesai): Collection
    {
        $baris = collect();
        $kursor = $tanggalMulai->copy();

        while ($kursor->lte($tanggalSelesai)) {
            $baris->push(['mulai_pada' => $kursor->copy(), 'batas_selesai_pada' => $kursor->copy()]);
            $kursor->addDay();
        }

        return $baris;
    }

    private function generateMingguan(Carbon $tanggalMulai, Carbon $tanggalSelesai): Collection
    {
        $baris = collect();
        $kursor = $tanggalMulai->copy();

        while ($kursor->lte($tanggalSelesai)) {
            $akhirBlok = $kursor->copy()->addDays(6);
            if ($akhirBlok->gt($tanggalSelesai)) {
                $akhirBlok = $tanggalSelesai->copy();
            }

            $baris->push(['mulai_pada' => $kursor->copy(), 'batas_selesai_pada' => $akhirBlok->copy()]);
            $kursor = $akhirBlok->copy()->addDay();
        }

        return $baris;
    }

    private function generateBulanan(Carbon $tanggalMulai, Carbon $tanggalSelesai, ?int $tanggalPengumpulanBulanan, bool $akhirBulan): Collection
    {
        $baris = collect();
        $mulaiSegmen = $tanggalMulai->copy();

        while (true) {
            $jatuhTempo = $this->hitungJatuhTempoBulanan($mulaiSegmen, $tanggalPengumpulanBulanan, $akhirBulan);
            $batasSelesai = $jatuhTempo->gt($tanggalSelesai) ? $tanggalSelesai->copy() : $jatuhTempo->copy();

            $baris->push(['mulai_pada' => $mulaiSegmen->copy(), 'batas_selesai_pada' => $batasSelesai->copy()]);

            if ($batasSelesai->gte($tanggalSelesai)) {
                break;
            }

            $mulaiSegmen = $batasSelesai->copy()->addDay();
        }

        return $baris;
    }

    private function hitungJatuhTempoBulanan(Carbon $dariTanggal, ?int $tanggalPengumpulanBulanan, bool $akhirBulan): Carbon
    {
        $kandidat = $akhirBulan
            ? $dariTanggal->copy()->endOfMonth()
            : $dariTanggal->copy()->day(min($tanggalPengumpulanBulanan, $dariTanggal->daysInMonth));

        if ($kandidat->lt($dariTanggal)) {
            $bulanBerikutnya = $dariTanggal->copy()->addMonthNoOverflow();
            $kandidat = $akhirBulan
                ? $bulanBerikutnya->copy()->endOfMonth()
                : $bulanBerikutnya->copy()->day(min($tanggalPengumpulanBulanan, $bulanBerikutnya->daysInMonth));
        }

        return $kandidat;
    }
}
