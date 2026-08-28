<?php

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Enums\ApproverType;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Models\WorkflowStep;
use App\Models\User;

class ApproverResolverService
{
    public function canUserApprove(WorkflowStep $step, User $user, ApprovalRequest $request): bool
    {
        if ($user->hasRole('yayasan_super_admin')) {
            return true;
        }

        return match ($step->approver_type) {
            ApproverType::Role => $this->checkRoleApprover($step, $user, $request),
            ApproverType::SpecificUser => (int) $step->approver_value === (int) $user->id,
            ApproverType::DirectRelation => $this->checkDirectRelationApprover($step, $user, $request),
        };
    }

    protected function checkRoleApprover(WorkflowStep $step, User $user, ApprovalRequest $request): bool
    {
        if (! $user->hasRole($step->approver_value)) {
            return false;
        }

        if ($step->scope_level === 'lembaga') {
            $targetLembagaId = $request->approvable?->lembaga_id ?? $request->requester?->lembaga_id;

            if ($targetLembagaId !== null) {
                $effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
                    ? session('active_lembaga_id')
                    : $user->lembaga_id;

                if ($effectiveLembagaId === null || (int) $targetLembagaId !== (int) $effectiveLembagaId) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function checkDirectRelationApprover(WorkflowStep $step, User $user, ApprovalRequest $request): bool
    {
        $relationType = $step->approver_value; // e.g. wali_kelas, atasan_langsung

        if ($relationType === 'wali_kelas') {
            $siswa = $request->requester;
            if ($siswa && method_exists($siswa, 'kelasAktif')) {
                $kelas = $siswa->kelasAktif();

                return $kelas && $kelas->wali_kelas_guru_id === $user->guru?->id;
            }
        }

        return false;
    }
}
