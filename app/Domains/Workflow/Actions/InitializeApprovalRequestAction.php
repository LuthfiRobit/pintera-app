<?php

namespace App\Domains\Workflow\Actions;

use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Models\WorkflowDefinition;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class InitializeApprovalRequestAction
{
    public function execute(string $workflowCode, Model $approvable, ?Model $requester = null): ApprovalRequest
    {
        $definition = WorkflowDefinition::where('code', $workflowCode)
            ->where('is_active', true)
            ->first();

        if (! $definition) {
            throw new InvalidArgumentException("Workflow definition with code [{$workflowCode}] not found or inactive.");
        }

        $firstStep = $definition->firstStep();
        if (! $firstStep) {
            throw new InvalidArgumentException("Workflow [{$workflowCode}] has no steps configured.");
        }

        return ApprovalRequest::create([
            'workflow_definition_id' => $definition->id,
            'approvable_type' => $approvable->getMorphClass(),
            'approvable_id' => $approvable->getKey(),
            'requester_type' => $requester ? $requester->getMorphClass() : null,
            'requester_id' => $requester ? $requester->getKey() : null,
            'current_step_id' => $firstStep->id,
            'status' => ApprovalStatus::Pending,
        ]);
    }
}
