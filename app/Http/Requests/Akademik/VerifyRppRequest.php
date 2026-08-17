<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\Enums\StatusRpp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class VerifyRppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rpp.verify');
    }

    /**
     * @return array<string, array<int, string|Enum>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(StatusRpp::class)],
            'catatan_revisi' => ['required_if:status,perlu_revisi', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Keputusan verifikasi wajib ditentukan (Disetujui atau Perlu Revisi).',
            'catatan_revisi.required_if' => 'Catatan revisi wajib diisi jika meminta perbaikan kepada guru.',
        ];
    }
}
