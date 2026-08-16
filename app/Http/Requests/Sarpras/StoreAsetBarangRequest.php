<?php

namespace App\Http\Requests\Sarpras;

use App\Domains\Sarpras\Enums\KondisiAset;
use App\Domains\Sarpras\Enums\SumberPerolehanAset;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAsetBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.aset.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'kategori_aset_id' => ['required', 'exists:kategori_aset,id'],
            'ruangan_id' => ['required', 'exists:ruangan,id'],
            'kode_inventaris' => ['required', 'string', 'max:100'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'merk' => ['nullable', 'string', 'max:255'],
            'spesifikasi' => ['nullable', 'string', 'max:1000'],
            'tipe_pencatatan' => ['required', Rule::enum(TipePencatatanAset::class)],
            'qty' => ['required', 'integer', 'min:1'],
            'satuan' => ['required', 'string', 'max:50'],
            'kondisi' => ['required', Rule::enum(KondisiAset::class)],
            'sumber_perolehan' => ['required', Rule::enum(SumberPerolehanAset::class)],
            'tanggal_perolehan' => ['nullable', 'date'],
            'harga_perolehan' => ['nullable', 'numeric', 'min:0'],
            'foto' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
