<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateNilaiSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('asesmen.kelola');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nilai' => ['required', 'array'],
            'nilai.*.*.nilai_angka' => ['nullable', 'integer', 'min:0', 'max:100'],
            'nilai.*.*.catatan' => ['nullable', 'string'],
        ];
    }

    public function toDTO(): NilaiSiswaBatchData
    {
        return NilaiSiswaBatchData::fromArray($this->validated());
    }
}
