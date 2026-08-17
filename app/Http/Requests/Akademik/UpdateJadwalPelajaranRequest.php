<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateJadwalPelajaranRequest extends FormRequest
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
            'jam_pelajaran_id' => ['required', 'integer'],
            'mata_pelajaran_id' => ['nullable', 'integer'],
            'guru_id' => ['required', 'integer'],
            'kelas_id' => ['nullable', 'integer'],
            'semester_id' => ['nullable', 'integer'],
            'ruangan_id' => ['nullable', 'integer'],
        ];
    }
}
