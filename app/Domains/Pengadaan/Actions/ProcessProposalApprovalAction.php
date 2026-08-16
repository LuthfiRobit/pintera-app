<?php

namespace App\Domains\Pengadaan\Actions;

use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessProposalApprovalAction
{
    public function __construct(
        protected ProcessApprovalAction $workflowAction,
    ) {
    }

    public function execute(
        PengajuanPengadaan $proposal,
        User $user,
        ApprovalAction $action,
        array $itemDecisions = [],
        ?string $notes = null
    ): void {
        $approvalRequest = $proposal->approvalRequest;

        if (! $approvalRequest) {
            throw ValidationException::withMessages([
                'approval' => 'Proposal ini tidak memiliki alur persetujuan aktif.',
            ]);
        }

        DB::transaction(function () use ($proposal, $approvalRequest, $user, $action, $itemDecisions, $notes) {
            // Process partial item decisions if provided
            if (! empty($itemDecisions)) {
                $totalApproved = 0;
                foreach ($proposal->items as $item) {
                    if (isset($itemDecisions[$item->id])) {
                        $dec = $itemDecisions[$item->id];
                        $statusStr = is_string($dec['status']) ? $dec['status'] : $dec['status']->value;
                        $item->status_item = StatusItemPengajuan::from($statusStr);
                        $item->catatan_reviewer = $dec['catatan'] ?? null;
                        $item->save();

                        if ($item->status_item === StatusItemPengajuan::Approved) {
                            $totalApproved += $item->total_estimasi;
                        }
                    } else {
                        if ($item->status_item === StatusItemPengajuan::Approved) {
                            $totalApproved += $item->total_estimasi;
                        }
                    }
                }

                if ($action === ApprovalAction::Approve) {
                    $proposal->total_estimasi = $totalApproved;
                    $proposal->save();
                }
            }

            $this->workflowAction->execute($approvalRequest, $user, $action, $notes);
            $approvalRequest->refresh();

            // Sync proposal status with workflow status
            if ($approvalRequest->status === ApprovalStatus::Approved) {
                $proposal->status = StatusPengajuan::Approved;
            } elseif ($approvalRequest->status === ApprovalStatus::Rejected) {
                $proposal->status = StatusPengajuan::Rejected;
            } elseif ($approvalRequest->status === ApprovalStatus::RevisionRequired) {
                $proposal->status = StatusPengajuan::RevisionRequired;
            } elseif ($approvalRequest->status === ApprovalStatus::InReview) {
                $proposal->status = StatusPengajuan::InReview;
            }

            $proposal->save();
        });
    }
}
