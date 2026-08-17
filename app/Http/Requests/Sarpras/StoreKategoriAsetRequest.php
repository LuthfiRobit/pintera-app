<?php

namespace App\Http\Requests\Sarpras;

use App\Domains\Sarpras\DataTransferObjects\KategoriAsetData;
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

    public function toDTO(int $yayasanId, ?int $lembagaId): KategoriAsetData
    {
        return KategoriAsetData::fromArray($this->validated(), $yayasanId, $lembagaId);
    }
}
