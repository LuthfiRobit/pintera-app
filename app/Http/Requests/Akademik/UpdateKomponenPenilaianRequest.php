<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\UpdateKomponenPenilaianData;
use App\Domains\Akademik\Enums\AssessmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateKomponenPenilaianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('komponen-penilaian.kelola');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $komponen = $this->route('komponenPenilaian');
        $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

        $rules = [
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'kktp' => ['nullable', 'string'],
            'kktp_minimal' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];

        if (! $dipakai) {
            $rules['assessment_type'] = ['nullable', Rule::enum(AssessmentType::class)];
        }

        return $rules;
    }

    public function toDTO(): UpdateKomponenPenilaianData
    {
        return UpdateKomponenPenilaianData::fromArray($this->validated());
    }
}
