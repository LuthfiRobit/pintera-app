<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;

final class StoreKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tahun_ajaran_id' => ['required', 'integer'],
            'nama' => ['required', 'string', 'max:255'],
            'tingkat' => ['nullable', 'string', 'max:20'],
            'fase_id' => ['nullable', 'integer', 'exists:fase,id'],
            'wali_kelas_guru_id' => ['nullable', 'integer'],
            'pola_jam_id' => ['nullable', 'integer'],
        ];
    }
}
