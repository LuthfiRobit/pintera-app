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

final class ApprovePengajuanRaporAction
{
    public function __construct(
        private readonly ProcessApprovalAction $processApprovalAction,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor
    {
        $effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $user->lembaga_id;

        if ($effectiveLembagaId === null || (int) $pengajuanRapor->lembaga_id !== (int) $effectiveLembagaId) {
            throw ValidationException::withMessages([
                'approval' => 'Anda tidak berwenang menyetujui pengajuan rapor lembaga lain.',
            ]);
        }

        $approvalRequest = $pengajuanRapor->approvalRequest;

        if (! $approvalRequest) {
            throw ValidationException::withMessages([
                'approval' => 'Pengajuan rapor ini belum pernah diajukan.',
            ]);
        }

        return DB::transaction(function () use ($pengajuanRapor, $approvalRequest, $user, $action, $catatan) {
            $pengajuanRapor = PengajuanRapor::lockForUpdate()->findOrFail($pengajuanRapor->id);

            $this->processApprovalAction->execute($approvalRequest, $user, $action, $catatan);
            $approvalRequest->refresh();

            if ($approvalRequest->status === ApprovalStatus::Rejected) {
                $pengajuanRapor->status = StatusPengajuanRapor::Ditolak;
                $pengajuanRapor->catatan_revisi = $catatan;
            } elseif ($approvalRequest->status === ApprovalStatus::Approved) {
                $pengajuanRapor->status = StatusPengajuanRapor::Disetujui;
                $pengajuanRapor->disetujui_oleh = $user->id;
                $pengajuanRapor->disetujui_pada = now();
            }

            $pengajuanRapor->save();

            return $pengajuanRapor->fresh();
        });
    }
}
