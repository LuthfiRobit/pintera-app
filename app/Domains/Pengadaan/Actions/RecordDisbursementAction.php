<?php

namespace App\Domains\Pengadaan\Actions;

use App\Domains\Pengadaan\DataTransferObjects\DisbursementData;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordDisbursementAction
{
    public function execute(PengajuanPengadaan $proposal, DisbursementData $data): void
    {
        if ($proposal->status !== StatusPengajuan::Approved) {
            throw ValidationException::withMessages([
                'disbursement' => 'Pencairan dana hanya dapat dicatat untuk proposal yang telah disetujui (Approved).',
            ]);
        }

        DB::transaction(function () use ($proposal, $data) {
            $proposal->nominal_pencairan = $data->nominalCair;
            $proposal->tanggal_pencairan = $data->tanggalCair;
            $proposal->catatan_pencairan = $data->catatanPencairan;
            if ($data->buktiTransferPath) {
                $proposal->bukti_transfer_pencairan_path = $data->buktiTransferPath;
            }
            $proposal->status = StatusPengajuan::Disbursed;
            $proposal->save();
        });
    }
}
