<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\UpdateKomponenPenilaianData;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\MataPelajaran;
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
            $rules['subjek_type'] = ['required', Rule::in(['mata_pelajaran', 'elemen_cp'])];
            $rules['subjek_id'] = ['required', 'integer', function ($attribute, $value, $fail) {
                $exists = match ($this->input('subjek_type')) {
                    'mata_pelajaran' => MataPelajaran::withoutGlobalScopes()->where('id', $value)->exists(),
                    'elemen_cp' => ElemenCp::where('id', $value)->exists(),
                    default => false,
                };
                if (! $exists) {
                    $fail('Subjek penilaian yang dipilih tidak valid.');
                }
            }];
            $rules['semester_id'] = ['required', 'integer'];
        }

        return $rules;
    }

    public function toDTO(): UpdateKomponenPenilaianData
    {
        return UpdateKomponenPenilaianData::fromArray($this->validated());
    }
}
