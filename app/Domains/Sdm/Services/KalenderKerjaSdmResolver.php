<?php

namespace App\Domains\Sdm\Services;

use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use Carbon\CarbonInterface;

class KalenderKerjaSdmResolver
{
    /**
     * @return array{libur: bool, alasan: string}
     */
    public function resolve(Lembaga $lembaga, CarbonInterface $tanggal): array
    {
        $entriLembaga = KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $lembaga->id)
            ->where(fn ($q) => $this->cocokRentang($q, $tanggal))
            ->first();

        if ($entriLembaga) {
            return [
                'libur' => $entriLembaga->tipe === TipeKalenderKerjaSdm::Libur,
                'alasan' => $entriLembaga->nama,
            ];
        }

        $entriNasional = KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
            ->whereNull('lembaga_id')
            ->where('yayasan_id', $lembaga->yayasan_id)
            ->where(fn ($q) => $this->cocokRentang($q, $tanggal))
            ->first();

        if ($entriNasional) {
            return [
                'libur' => $entriNasional->tipe === TipeKalenderKerjaSdm::Libur,
                'alasan' => $entriNasional->nama,
            ];
        }

        if (in_array($tanggal->dayOfWeek, $lembaga->hari_libur_mingguan_sdm ?? [], true)) {
            return ['libur' => true, 'alasan' => 'Libur mingguan SDM'];
        }

        return ['libur' => false, 'alasan' => 'Hari kerja efektif'];
    }

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
