<?php

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Enums\ApproverType;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Models\WorkflowStep;
use App\Models\Lembaga;
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
                $effectiveLembagaId = $this->resolveEffectiveLembagaId($user);

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

    /**
     * Resolusi lembaga aktif aktor yang tervalidasi -- BUKAN raw session read.
     * Untuk aktor lembaga-scope, lembaga sudah tetap (User::lembaga_id). Untuk
     * aktor yayasan-scope, session('active_lembaga_id') divalidasi dulu
     * terhadap kepemilikan yayasan sebelum dipercaya -- session stale/lintas
     * yayasan menghasilkan null, BUKAN nilai yang salah dipakai.
     *
     * Sengaja tidak reuse App\Domains\Akademik\Support\ResolveLembagaScopeTrait
     * -- Workflow adalah engine generik dipakai Akademik/Pengadaan/SDM dan
     * tidak boleh bergantung pada namespace domain manapun.
     */
    private function resolveEffectiveLembagaId(User $user): ?int
    {
        if ($user->widestScopeLevel() !== 'yayasan') {
            return $user->lembaga_id;
        }

        $lembagaId = session('active_lembaga_id');
        if ($lembagaId === null) {
            return null;
        }

        $milikYayasan = Lembaga::where('id', $lembagaId)->where('yayasan_id', $user->yayasan_id)->exists();

        return $milikYayasan ? $lembagaId : null;
    }
}
