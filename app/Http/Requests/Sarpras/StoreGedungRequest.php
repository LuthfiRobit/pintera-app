<?php

namespace App\Http\Requests\Sarpras;

use Illuminate\Foundation\Http\FormRequest;

class StoreGedungRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.gedung.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'kode_gedung' => ['required', 'string', 'max:50'],
            'nama_gedung' => ['required', 'string', 'max:255'],
            'jumlah_lantai' => ['nullable', 'integer', 'min:1', 'max:50'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'is_aktif' => ['nullable', 'boolean'],
        ];
    }
}
