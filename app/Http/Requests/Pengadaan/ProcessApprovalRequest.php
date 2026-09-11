<?php

namespace App\Http\Requests\Pengadaan;

use App\Domains\Workflow\Enums\ApprovalAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ProcessApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['pengadaan.approval.internal', 'pengadaan.approval.yayasan']) ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', new Enum(ApprovalAction::class)],
            'notes' => ['nullable', 'string', 'max:1000', 'required_if:action,REJECT,REQUEST_REVISION'],
            'item_decisions' => ['nullable', 'array'],
            'item_decisions.*.status' => ['required_with:item_decisions', 'in:approved,rejected'],
            'item_decisions.*.catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Keputusan tindakan persetujuan (Setujui, Tolak, atau Revisi) wajib dipilih.',
            'notes.max' => 'Catatan keputusan maksimal 1000 karakter.',
            'notes.required_if' => 'Catatan wajib diisi saat menolak atau meminta revisi, supaya sekolah tahu apa yang perlu diperbaiki.',
            'item_decisions.*.status.in' => 'Keputusan item harus approved atau rejected.',
        ];
    }
}
