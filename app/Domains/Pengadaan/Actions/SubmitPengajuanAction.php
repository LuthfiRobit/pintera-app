<?php

namespace App\Domains\Pengadaan\Actions;

use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Models\ApprovalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitPengajuanAction
{
    public function __construct(
        protected InitializeApprovalRequestAction $initWorkflowAction,
    ) {
    }

    public function execute(PengajuanPengadaan $proposal): ApprovalRequest
    {
        if ($proposal->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => 'Proposal tidak dapat diajukan karena belum memiliki rincian barang.',
            ]);
        }

        return DB::transaction(function () use ($proposal) {
            $proposal->status = StatusPengajuan::Submitted;
            $proposal->save();

            $approvalRequest = $this->initWorkflowAction->execute(
                workflowCode: 'PENGADAAN_SARPRAS',
                approvable: $proposal,
                requester: $proposal->pengaju ?? $proposal->lembaga,
            );

            return $approvalRequest;
        });
    }
}
