<?php

namespace App\Http\Requests\Sarpras;

use App\Domains\Sarpras\DataTransferObjects\MutasiAsetData;
use Illuminate\Foundation\Http\FormRequest;

class StoreMutasiAsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.mutasi.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'aset_barang_id' => ['required', 'exists:aset_barang,id'],
            'ruangan_tujuan_id' => ['required', 'exists:ruangan,id'],
            'qty_pindah' => ['required', 'integer', 'min:1'],
            'tanggal_mutasi' => ['required', 'date'],
            'alasan_mutasi' => ['required', 'string', 'max:1000'],
        ];
    }

    public function toDTO(int $userId): MutasiAsetData
    {
        return MutasiAsetData::fromArray($this->validated(), $userId);
    }
}
