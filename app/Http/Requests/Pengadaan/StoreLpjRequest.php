<?php

namespace App\Http\Requests\Pengadaan;

use App\Domains\Pengadaan\DataTransferObjects\LpjPengadaanData;
use Illuminate\Foundation\Http\FormRequest;

class StoreLpjRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pengadaan.lpj.submit') ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.pengajuan_item_id' => ['required', 'exists:pengajuan_pengadaan_item,id'],
            'items.*.harga_satuan_riil' => ['required', 'numeric', 'min:0'],
            'items.*.total_riil' => ['required', 'numeric', 'min:0'],
            'items.*.foto_nota' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'items.*.foto_fisik' => ['nullable', 'image', 'max:5120'],
            'bukti_kembali_sisa' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Rincian realisasi belanja LPJ wajib diisi.',
            'items.*.harga_satuan_riil.required' => 'Harga satuan riil aktual wajib diisi.',
            'items.*.harga_satuan_riil.min' => 'Harga satuan riil tidak boleh negatif.',
            'items.*.total_riil.required' => 'Total belanja riil wajib diisi.',
            'items.*.foto_nota.mimes' => 'Berkas foto nota fisik harus berformat JPG, PNG, atau PDF.',
            'items.*.foto_nota.max' => 'Ukuran berkas foto nota maksimal 5MB.',
            'items.*.foto_fisik.image' => 'Foto fisik barang harus berupa file gambar (JPG, PNG).',
            'items.*.foto_fisik.max' => 'Ukuran foto fisik barang maksimal 5MB.',
            'bukti_kembali_sisa.mimes' => 'Bukti pengembalian sisa dana kas harus berformat JPG, PNG, atau PDF.',
            'bukti_kembali_sisa.max' => 'Ukuran bukti pengembalian sisa kas maksimal 5MB.',
        ];
    }

    public function toDTO(?string $buktiKembaliPath = null, array $processedItems = []): LpjPengadaanData
    {
        return new LpjPengadaanData(
            items: ! empty($processedItems) ? $processedItems : $this->validated()['items'],
            buktiKembaliSisaDanaPath: $buktiKembaliPath,
        );
    }
}
