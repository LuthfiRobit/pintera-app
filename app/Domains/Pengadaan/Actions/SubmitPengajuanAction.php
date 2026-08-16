<?php

namespace App\Domains\Pengadaan\Actions;

use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Domains\Workflow\Models\ApprovalLog;
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

            // Reset non-approved item review statuses to Pending
            foreach ($proposal->items as $item) {
                if ($item->status_item !== StatusItemPengajuan::Approved) {
                    $item->status_item = StatusItemPengajuan::Pending;
                    $item->save();
                }
            }

            $existingApprovalRequest = $proposal->approvalRequest;

            if ($existingApprovalRequest) {
                $firstStep = $existingApprovalRequest->workflowDefinition?->firstStep();
                $existingApprovalRequest->current_step_id = $firstStep?->id;
                $existingApprovalRequest->status = ApprovalStatus::Pending;
                $existingApprovalRequest->last_notes = null;
                $existingApprovalRequest->save();

                return $existingApprovalRequest;
            }

            $approvalRequest = $this->initWorkflowAction->execute(
                workflowCode: 'PENGADAAN_SARPRAS',
                approvable: $proposal,
                requester: $proposal->pengaju ?? $proposal->lembaga,
            );

            return $approvalRequest;
        });
    }
}
