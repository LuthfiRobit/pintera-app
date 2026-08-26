<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreFaseDefaultMappingRequest extends FormRequest
{
    public const BENTUK_PENDIDIKAN = ['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bentuk_pendidikan' => ['required', Rule::in(self::BENTUK_PENDIDIKAN)],
            'tingkat' => ['nullable', 'string', 'max:10'],
            'fase_id' => ['required', 'exists:fase,id'],
            'lembaga_id' => ['nullable', 'integer', 'exists:lembaga,id'],
        ];
    }
}
