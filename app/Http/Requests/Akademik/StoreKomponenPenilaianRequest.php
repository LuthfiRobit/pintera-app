<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData;
use App\Domains\Akademik\Enums\ElemenCapaianPembelajaran;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreKomponenPenilaianRequest extends FormRequest
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
        return [
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'kktp' => ['nullable', 'string'],
            'kktp_minimal' => ['nullable', 'integer', 'min:0', 'max:100'],
            'elemen_cp' => ['nullable', Rule::enum(ElemenCapaianPembelajaran::class)],
        ];
    }

    public function toDTO(): KomponenPenilaianData
    {
        return KomponenPenilaianData::fromArray($this->validated());
    }
}
