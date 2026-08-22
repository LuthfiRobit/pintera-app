<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Domains\Workflow\Models\ApprovalLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BatalkanPengajuanIzinCutiAction
{
    public function execute(PengajuanIzinCuti $pengajuan, User $user): void
    {
        $approvalRequest = $pengajuan->approvalRequest;

        if (! $approvalRequest) {
            throw ValidationException::withMessages(['approval' => 'Pengajuan ini tidak memiliki alur persetujuan aktif.']);
        }

        if (! in_array($approvalRequest->status, [ApprovalStatus::Pending, ApprovalStatus::InReview], true)) {
            throw ValidationException::withMessages([
                'approval' => 'Pengajuan yang sudah '.$approvalRequest->status->label().' tidak dapat dibatalkan.',
            ]);
        }

        $pegawai = $pengajuan->pegawai;

        if ((int) $pegawai->user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['approval' => 'Anda hanya dapat membatalkan pengajuan Anda sendiri.']);
        }

        DB::transaction(function () use ($approvalRequest, $user) {
            ApprovalLog::create([
                'approval_request_id' => $approvalRequest->id,
                'workflow_step_id' => $approvalRequest->current_step_id,
                'user_id' => $user->id,
                'action' => ApprovalAction::Cancel,
                'notes' => 'Dibatalkan oleh pengaju.',
            ]);

            $approvalRequest->status = ApprovalStatus::Cancelled;
            $approvalRequest->save();
        });
    }
}
