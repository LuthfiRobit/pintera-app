<?php

namespace App\Domains\Workflow\Actions;

use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Domains\Workflow\Models\ApprovalLog;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Services\ApproverResolverService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessApprovalAction
{
    public function __construct(
        protected ApproverResolverService $resolverService,
    ) {
    }

    public function execute(ApprovalRequest $request, User $user, ApprovalAction $action, ?string $notes = null): bool
    {
        $currentStep = $request->currentStep;

        if (! $currentStep) {
            throw ValidationException::withMessages([
                'approval' => 'Permintaan persetujuan ini sudah selesai atau tidak memiliki langkah aktif.',
            ]);
        }

        if (! $this->resolverService->canUserApprove($currentStep, $user, $request)) {
            throw ValidationException::withMessages([
                'approval' => 'Anda tidak memiliki hak akses untuk memproses langkah persetujuan ini.',
            ]);
        }

        return DB::transaction(function () use ($request, $currentStep, $user, $action, $notes) {
            ApprovalLog::create([
                'approval_request_id' => $request->id,
                'workflow_step_id' => $currentStep->id,
                'user_id' => $user->id,
                'action' => $action,
                'notes' => $notes,
            ]);

            $request->last_notes = $notes;

            if ($action === ApprovalAction::Reject) {
                $request->status = ApprovalStatus::Rejected;
                $request->save();
                return true;
            }

            if ($action === ApprovalAction::RequestRevision) {
                $request->status = ApprovalStatus::RevisionRequired;
                $request->save();
                return true;
            }

            if ($action === ApprovalAction::Approve) {
                if ($currentStep->is_final_step) {
                    $request->status = ApprovalStatus::Approved;
                    $request->save();
                    return true;
                }

                $nextStep = $currentStep->nextStep();
                if ($nextStep) {
                    $request->current_step_id = $nextStep->id;
                    $request->status = ApprovalStatus::InReview;
                } else {
                    $request->status = ApprovalStatus::Approved;
                }

                $request->save();
                return true;
            }

            return false;
        });
    }
}
