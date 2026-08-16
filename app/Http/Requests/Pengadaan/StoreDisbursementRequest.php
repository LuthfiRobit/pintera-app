<?php

namespace App\Http\Requests\Pengadaan;

use App\Domains\Pengadaan\DataTransferObjects\DisbursementData;
use Illuminate\Foundation\Http\FormRequest;

class StoreDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pengadaan.disbursement.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'nominal_pencairan' => ['required', 'numeric', 'min:1'],
            'tanggal_pencairan' => ['required', 'date'],
            'catatan_pencairan' => ['nullable', 'string', 'max:1000'],
            'bukti_transfer' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function toDTO(?string $buktiTransferPath = null): DisbursementData
    {
        return DisbursementData::fromArray($this->validated(), $buktiTransferPath);
    }
}
