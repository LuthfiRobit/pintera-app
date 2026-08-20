<?php

namespace App\Services;

use App\Enums\TipeKalenderAkademik;
use App\Domains\Akademik\Models\KalenderAkademik;
use App\Models\Lembaga;
use Carbon\CarbonInterface;

class KalenderAkademikResolver
{
    /**
     * @return array{libur: bool, alasan: string}
     */
    public function resolve(Lembaga $lembaga, CarbonInterface $tanggal): array
    {
        $entriLembaga = KalenderAkademik::untukLembaga($lembaga->id)
            ->where(fn ($q) => $this->cocokRentang($q, $tanggal))
            ->first();

        if ($entriLembaga) {
            return [
                'libur' => $entriLembaga->tipe === TipeKalenderAkademik::Libur,
                'alasan' => $entriLembaga->nama,
            ];
        }

        $entriNasional = KalenderAkademik::nasional()
            ->where(fn ($q) => $this->cocokRentang($q, $tanggal))
            ->first();

        if ($entriNasional) {
            return [
                'libur' => $entriNasional->tipe === TipeKalenderAkademik::Libur,
                'alasan' => $entriNasional->nama,
            ];
        }

        if (in_array($tanggal->dayOfWeek, $lembaga->hari_libur_mingguan ?? [], true)) {
            return ['libur' => true, 'alasan' => 'Libur mingguan'];
        }

        return ['libur' => false, 'alasan' => 'Hari efektif belajar'];
    }

    /**
     * Matches a $tanggal that falls within an entry's [tanggal, tanggal_selesai]
     * range, inclusive. When tanggal_selesai is null the entry is a single day,
     * so the effective end date falls back to tanggal itself.
     */
    private function cocokRentang($query, CarbonInterface $tanggal)
    {
        $tgl = $tanggal->toDateString();

        return $query
            ->whereDate('tanggal', '<=', $tgl)
            ->where(fn ($q) => $q->whereDate('tanggal_selesai', '>=', $tgl)
                ->orWhere(fn ($q2) => $q2->whereNull('tanggal_selesai')->whereDate('tanggal', '>=', $tgl))
            );
    }
}
