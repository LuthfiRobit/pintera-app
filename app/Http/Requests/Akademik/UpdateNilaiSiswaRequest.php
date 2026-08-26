<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use App\Domains\Akademik\Enums\PredikatPaud;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateNilaiSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('asesmen.kelola');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = ['nilai' => ['required', 'array']];

        /** @var \App\Domains\Akademik\Models\Asesmen $asesmen */
        $asesmen = $this->route('asesmen');
        $tipePerKomponen = $asesmen->komponenPenilaian()->pluck('assessment_type', 'komponen_penilaian.id');

        foreach ($this->input('nilai', []) as $siswaId => $perKomponen) {
            foreach (array_keys($perKomponen) as $komponenId) {
                $tipe = $tipePerKomponen->get((int) $komponenId)?->value;
                $prefix = "nilai.{$siswaId}.{$komponenId}";

                $rules["{$prefix}.nilai_angka"] = $tipe === 'numeric'
                    ? ['nullable', 'integer', 'min:0', 'max:100']
                    : ['prohibited'];
                $rules["{$prefix}.predikat"] = $tipe === 'predicate'
                    ? ['required', Rule::in(array_column(PredikatPaud::cases(), 'value'))]
                    : ['prohibited'];
                $rules["{$prefix}.catatan"] = $tipe === 'narrative'
                    ? ['required', 'string']
                    : ['nullable', 'string'];
            }
        }

        return $rules;
    }

    public function toDTO(): NilaiSiswaBatchData
    {
        return NilaiSiswaBatchData::fromArray($this->validated());
    }
}
