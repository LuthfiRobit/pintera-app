<?php

namespace App\Domains\Workflow\Models;

use App\Domains\Workflow\Enums\ApproverType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $table = 'workflow_steps';

    protected $fillable = [
        'workflow_definition_id',
        'step_number',
        'step_name',
        'approver_type',
        'approver_value',
        'scope_level',
        'is_final_step',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'approver_type' => ApproverType::class,
            'is_final_step' => 'boolean',
        ];
    }

    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function nextStep(): ?self
    {
        return self::where('workflow_definition_id', $this->workflow_definition_id)
            ->where('step_number', '>', $this->step_number)
            ->orderBy('step_number')
            ->first();
    }
}
