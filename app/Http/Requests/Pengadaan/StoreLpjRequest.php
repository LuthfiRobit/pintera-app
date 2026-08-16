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

    public function toDTO(?string $buktiKembaliPath = null, array $processedItems = []): LpjPengadaanData
    {
        return new LpjPengadaanData(
            items: ! empty($processedItems) ? $processedItems : $this->validated()['items'],
            buktiKembaliSisaDanaPath: $buktiKembaliPath,
        );
    }
}
