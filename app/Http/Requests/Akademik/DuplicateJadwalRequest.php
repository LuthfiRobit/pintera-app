<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;

final class DuplicateJadwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('jadwal-pelajaran.kelola');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'source_kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'source_semester_id' => ['required', 'integer', 'exists:semester,id'],
            'target_kelas_id' => ['required', 'integer', 'exists:kelas,id', 'different:source_kelas_id'],
            'target_semester_id' => ['required', 'integer', 'exists:semester,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_kelas_id.different' => 'Kelas tujuan tidak boleh sama dengan kelas asal.',
        ];
    }
}
