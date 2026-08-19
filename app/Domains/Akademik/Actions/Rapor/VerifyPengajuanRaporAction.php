<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rapor;

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VerifyPengajuanRaporAction
{
    public function __construct(
        private readonly ProcessApprovalAction $processApprovalAction,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor
    {
        if ((int) $pengajuanRapor->lembaga_id !== (int) $user->lembaga_id) {
            throw ValidationException::withMessages([
                'approval' => 'Anda tidak berwenang memverifikasi pengajuan rapor lembaga lain.',
            ]);
        }

        $approvalRequest = $pengajuanRapor->approvalRequest;

        if (! $approvalRequest) {
            throw ValidationException::withMessages([
                'approval' => 'Pengajuan rapor ini belum pernah diajukan.',
            ]);
        }

        return DB::transaction(function () use ($pengajuanRapor, $approvalRequest, $user, $action, $catatan) {
            $this->processApprovalAction->execute($approvalRequest, $user, $action, $catatan);
            $approvalRequest->refresh();

            if ($approvalRequest->status === ApprovalStatus::Rejected) {
                $pengajuanRapor->status = StatusPengajuanRapor::Ditolak;
                $pengajuanRapor->catatan_revisi = $catatan;
            } elseif ($approvalRequest->status === ApprovalStatus::InReview) {
                $pengajuanRapor->status = StatusPengajuanRapor::Diverifikasi;
                $pengajuanRapor->diverifikasi_oleh = $user->id;
                $pengajuanRapor->diverifikasi_pada = now();
            }

            $pengajuanRapor->save();

            return $pengajuanRapor->fresh();
        });
    }
}
