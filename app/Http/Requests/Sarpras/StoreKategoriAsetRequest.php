<?php

namespace App\Http\Requests\Sarpras;

use Illuminate\Foundation\Http\FormRequest;

class StoreKategoriAsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.kategori.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'kode_kategori' => ['required', 'string', 'max:50'],
            'nama_kategori' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
