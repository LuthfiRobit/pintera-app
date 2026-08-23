<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Sdm\Services\KuotaCutiResolver;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AjukanIzinCutiAction
{
    public function __construct(
        private readonly InitializeApprovalRequestAction $initWorkflowAction,
        private readonly KuotaCutiResolver $kuotaResolver,
    ) {}

    public function execute(
        Model $pegawai,
        KategoriPengajuanIzin $kategori,
        string $tanggalMulai,
        string $tanggalSelesai,
        string $alasan,
    ): PengajuanIzinCuti {
        if ($tanggalMulai > $tanggalSelesai) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => 'Tanggal mulai tidak boleh setelah tanggal selesai.',
            ]);
        }

        $tahunMulai = (int) substr($tanggalMulai, 0, 4);
        $tahunSelesai = (int) substr($tanggalSelesai, 0, 4);

        if ($kategori === KategoriPengajuanIzin::Cuti && $tahunMulai !== $tahunSelesai) {
            throw ValidationException::withMessages([
                'tanggal_selesai' => 'Pengajuan Cuti tidak boleh melewati pergantian tahun kalender. Silakan ajukan terpisah untuk setiap tahun.',
            ]);
        }

        if ($kategori === KategoriPengajuanIzin::Cuti && $this->kuotaResolver->jatahTahunan($pegawai) > 0) {
            $lockKey = 'kuota-cuti:'.get_class($pegawai).':'.$pegawai->id.':'.$tahunMulai;

            return Cache::lock($lockKey, 10)->block(5, function () use ($pegawai, $kategori, $tanggalMulai, $tanggalSelesai, $alasan, $tahunMulai) {
                $hariDiajukan = Carbon::parse($tanggalMulai)->diffInDays(Carbon::parse($tanggalSelesai)) + 1;
                $sisa = $this->kuotaResolver->sisaKuota($pegawai, $tahunMulai);

                if ($hariDiajukan > $sisa) {
                    throw ValidationException::withMessages([
                        'tanggal_mulai' => "Sisa kuota Cuti Anda tahun ini tinggal {$sisa} hari, tidak cukup untuk {$hariDiajukan} hari yang diajukan.",
                    ]);
                }

                return $this->buatPengajuan($pegawai, $kategori, $tanggalMulai, $tanggalSelesai, $alasan);
            });
        }

        return $this->buatPengajuan($pegawai, $kategori, $tanggalMulai, $tanggalSelesai, $alasan);
    }

    private function buatPengajuan(
        Model $pegawai,
        KategoriPengajuanIzin $kategori,
        string $tanggalMulai,
        string $tanggalSelesai,
        string $alasan,
    ): PengajuanIzinCuti {
        return DB::transaction(function () use ($pegawai, $kategori, $tanggalMulai, $tanggalSelesai, $alasan) {
            $pengajuan = $pegawai->pengajuanIzinCuti()->create([
                'lembaga_id' => $pegawai->lembaga_id,
                'kategori' => $kategori,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'alasan' => $alasan,
            ]);

            $this->initWorkflowAction->execute(
                workflowCode: 'IZIN_CUTI_SDM',
                approvable: $pengajuan,
                requester: $pegawai,
            );

            return $pengajuan;
        });
    }
}
