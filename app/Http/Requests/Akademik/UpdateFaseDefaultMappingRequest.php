<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFaseDefaultMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bentuk_pendidikan' => ['required', Rule::in(StoreFaseDefaultMappingRequest::BENTUK_PENDIDIKAN)],
            'tingkat' => ['nullable', 'string', 'max:10'],
            'fase_id' => ['required', 'exists:fase,id'],
        ];
    }
}
