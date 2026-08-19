<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreKomponenPenilaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('komponen-penilaian.kelola');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'kktp' => ['nullable', 'string'],
            'kktp_minimal' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function toDTO(): KomponenPenilaianData
    {
        return KomponenPenilaianData::fromArray($this->validated());
    }
}
