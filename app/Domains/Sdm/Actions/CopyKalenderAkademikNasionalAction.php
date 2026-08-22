<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Akademik\Models\KalenderAkademik;
use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;
use App\Domains\Sdm\Models\KalenderKerjaSdm;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Collection;

final class CopyKalenderAkademikNasionalAction
{
    /**
     * @param  array<int, int>  $kalenderAkademikIds
     */
    public function execute(int $yayasanId, array $kalenderAkademikIds): Collection
    {
        $entriAkademikList = KalenderAkademik::nasional()->whereIn('id', $kalenderAkademikIds)->get();

        return $entriAkademikList->map(function (KalenderAkademik $entriAkademik) use ($yayasanId) {
            $sudahAda = KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
                ->whereNull('lembaga_id')
                ->where('yayasan_id', $yayasanId)
                ->where('tanggal', $entriAkademik->tanggal->toDateString())
                ->where('nama', $entriAkademik->nama)
                ->exists();

            if ($sudahAda) {
                return null;
            }

            return KalenderKerjaSdm::create([
                'yayasan_id' => $yayasanId,
                'lembaga_id' => null,
                'tanggal' => $entriAkademik->tanggal,
                'tanggal_selesai' => $entriAkademik->tanggal_selesai,
                'nama' => $entriAkademik->nama,
                'tipe' => TipeKalenderKerjaSdm::from($entriAkademik->tipe->value),
                'keterangan' => $entriAkademik->keterangan,
            ]);
        })->filter();
    }
}
