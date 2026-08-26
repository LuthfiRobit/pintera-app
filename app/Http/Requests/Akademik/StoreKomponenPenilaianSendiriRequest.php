<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\MataPelajaran;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreKomponenPenilaianSendiriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('komponen-penilaian.kelola-sendiri');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subjek_type' => ['required', Rule::in(['mata_pelajaran', 'elemen_cp'])],
            'subjek_id' => ['required', 'integer', function ($attribute, $value, $fail) {
                $exists = match ($this->input('subjek_type')) {
                    'mata_pelajaran' => MataPelajaran::withoutGlobalScopes()->where('id', $value)->exists(),
                    'elemen_cp' => ElemenCp::where('id', $value)->exists(),
                    default => false,
                };
                if (! $exists) {
                    $fail('Subjek penilaian yang dipilih tidak valid.');
                }
            }],
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
