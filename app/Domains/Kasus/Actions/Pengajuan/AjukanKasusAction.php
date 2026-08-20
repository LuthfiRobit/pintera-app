<?php

declare(strict_types=1);

namespace App\Domains\Kasus\Actions\Pengajuan;

use App\Domains\Kasus\DataTransferObjects\AjukanKasusData;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Enums\StatusKasus;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Notifications\KasusDiajukanNotification;
use Illuminate\Support\Facades\DB;

final class AjukanKasusAction
{
    public function execute(Siswa $siswa, AjukanKasusData $data, bool $isGuru, int $guruId = null, int $orangTuaId = null): Kasus
    {
        $kasus = DB::transaction(function () use ($data, $siswa, $isGuru, $guruId, $orangTuaId) {
            return Kasus::create([
                'siswa_id' => $siswa->id,
                'lembaga_id' => $siswa->lembaga_id,
                'diajukan_oleh_guru_id' => $isGuru ? $guruId : null,
                'diajukan_oleh_orang_tua_id' => $isGuru ? null : $orangTuaId,
                'kategori_masalah' => $data->kategoriMasalah,
                'deskripsi' => $data->deskripsi,
                'lampiran' => $data->lampiranPath,
                'status' => StatusKasus::Diajukan,
            ]);
        });

        // The Kasus->siswa relation would re-apply Siswa's TenantScope when lazy-loaded,
        // which (for an orang_tua submitter with no lembaga_id) filters the real siswa row
        // out entirely. Cache the already-authorized, scope-bypassed $siswa on the relation
        // so notifyPihakLain() (and the redirect target) see the correct record.
        $kasus->setRelation('siswa', $siswa);

        $this->notifyPihakLain($kasus, $isGuru);

        return $kasus;
    }

    private function notifyPihakLain(Kasus $kasus, bool $isGuru): void
    {
        if ($isGuru) {
            $kontakUtama = $kasus->siswa->orangTua()->wherePivot('is_kontak_utama', true)->first();
            $kontakUtama?->notify(new KasusDiajukanNotification($kasus));

            return;
        }

        $kelas = $kasus->siswa->kelas()->withoutGlobalScope(TenantScope::class)->first();
        $waliKelas = $kelas?->waliKelas()->withoutGlobalScope(TenantScope::class)->first();
        $waliKelas?->notify(new KasusDiajukanNotification($kasus));
    }
}
