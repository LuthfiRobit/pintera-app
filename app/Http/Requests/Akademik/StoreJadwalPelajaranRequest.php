<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;

final class StoreJadwalPelajaranRequest extends FormRequest
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
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'jam_pelajaran_id' => ['required', 'array', 'min:1'],
            'jam_pelajaran_id.*' => ['integer'],
            'mata_pelajaran_id' => ['nullable', 'integer'],
            'guru_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'ruangan_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kelas_id.required' => 'Kelas wajib dipilih.',
            'jam_pelajaran_id.required' => 'Pilih minimal satu slot jam pelajaran.',
            'guru_id.required' => 'Guru pengampu wajib dipilih.',
            'semester_id.required' => 'Semester wajib dipilih.',
        ];
    }
}
