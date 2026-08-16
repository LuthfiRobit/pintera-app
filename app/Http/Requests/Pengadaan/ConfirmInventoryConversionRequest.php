<?php

namespace App\Http\Requests\Pengadaan;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmInventoryConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pengadaan.lpj.submit') ?? false;
    }

    public function rules(): array
    {
        return [
            'serial_numbers' => ['nullable', 'array'],
        ];
    }
}
