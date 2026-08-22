<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AjukanIzinCutiAction
{
    public function __construct(private readonly InitializeApprovalRequestAction $initWorkflowAction) {}

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
