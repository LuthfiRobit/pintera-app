<?php

namespace App\Http\Requests\Pengadaan;

use App\Domains\Pengadaan\DataTransferObjects\PengajuanPengadaanData;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePengajuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pengadaan.proposal.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'judul_pengajuan' => ['required', 'string', 'max:255'],
            'latar_belakang' => ['nullable', 'string'],
            'tingkat_urgensi' => ['required', new Enum(TingkatUrgensi::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama_barang' => ['required', 'string', 'max:255'],
            'items.*.kategori_aset_id' => ['required', 'exists:kategori_aset,id'],
            'items.*.target_ruangan_id' => ['required', 'exists:ruangan,id'],
            'items.*.merk' => ['nullable', 'string', 'max:255'],
            'items.*.spesifikasi' => ['nullable', 'string'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.satuan' => ['required', 'string', 'max:50'],
            'items.*.estimasi_harga_satuan' => ['required', 'numeric', 'min:0'],
            'items.*.tipe_pencatatan' => ['required', 'in:unit,batch'],
            'items.*.foto_referensi' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function toDTO(?int $yayasanId = null, ?int $lembagaId = null): PengajuanPengadaanData
    {
        return PengajuanPengadaanData::fromArray($this->validated(), $yayasanId, $lembagaId);
    }
}
