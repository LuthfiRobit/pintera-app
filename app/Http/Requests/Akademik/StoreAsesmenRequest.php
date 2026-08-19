<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\AsesmenData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreAsesmenRequest extends FormRequest
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
            'kelas_id' => ['required', 'integer'],
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'jenis' => ['required', 'in:sumatif_lingkup_materi,sumatif_akhir_semester,sumatif_akhir_jenjang'],
            'judul' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'komponen_id' => ['required', 'array', 'min:1'],
            'komponen_id.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'komponen_id.required' => 'Pilih minimal satu Tujuan Pembelajaran.',
            'komponen_id.min' => 'Pilih minimal satu Tujuan Pembelajaran.',
        ];
    }

    public function toDTO(): AsesmenData
    {
        return AsesmenData::fromArray($this->validated());
    }
}
