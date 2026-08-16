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

    public function messages(): array
    {
        return [
            'nominal_pencairan.required' => 'Nominal pencairan dana kas wajib diisi.',
            'nominal_pencairan.min' => 'Nominal pencairan minimal Rp 1.',
            'tanggal_pencairan.required' => 'Tanggal pencairan wajib diisi.',
            'tanggal_pencairan.date' => 'Format tanggal pencairan tidak valid.',
            'bukti_transfer.mimes' => 'Bukti transfer/struk kas harus berformat JPG, PNG, atau PDF.',
            'bukti_transfer.max' => 'Ukuran bukti pencairan maksimal 5MB.',
        ];
    }

    public function toDTO(?string $buktiTransferPath = null): DisbursementData
    {
        return DisbursementData::fromArray($this->validated(), $buktiTransferPath);
    }
}
