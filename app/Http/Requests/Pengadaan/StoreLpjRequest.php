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
            'items.*.foto_nota' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'items.*.foto_fisik' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'bukti_kembali_sisa' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $proposal = $this->route('proposal');
            if (! $proposal) {
                return;
            }

            $nominalPencairan = (float) $proposal->nominal_pencairan;
            $items = $this->input('items', []);
            $totalRiil = 0;

            foreach ($items as $item) {
                $totalRiil += (float) ($item['total_riil'] ?? 0);
            }

            $sisaKas = $nominalPencairan - $totalRiil;
            if ($sisaKas > 0 && ! $this->hasFile('bukti_kembali_sisa')) {
                $validator->errors()->add(
                    'bukti_kembali_sisa',
                    'Terdapat sisa dana kas sebesar Rp ' . number_format($sisaKas, 0, ',', '.') . '. Bukti transfer/setoran pengembalian sisa kas ke Yayasan wajib dilampirkan.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Rincian realisasi belanja LPJ wajib diisi.',
            'items.*.harga_satuan_riil.required' => 'Harga satuan riil aktual wajib diisi.',
            'items.*.harga_satuan_riil.min' => 'Harga satuan riil tidak boleh negatif.',
            'items.*.total_riil.required' => 'Total belanja riil wajib diisi.',
            'items.*.foto_nota.required' => 'Scan nota/faktur pembelian untuk setiap item barang wajib diunggah.',
            'items.*.foto_nota.mimes' => 'Berkas scan nota fisik harus berformat JPG, JPEG, PNG, atau PDF.',
            'items.*.foto_nota.max' => 'Ukuran berkas scan nota maksimal 5MB.',
            'items.*.foto_fisik.required' => 'Foto fisik barang saat tiba di sekolah wajib diunggah untuk setiap item.',
            'items.*.foto_fisik.image' => 'Foto fisik barang harus berupa file gambar (JPG, JPEG, PNG).',
            'items.*.foto_fisik.mimes' => 'Foto fisik barang harus berformat JPG, JPEG, atau PNG.',
            'items.*.foto_fisik.max' => 'Ukuran foto fisik barang maksimal 5MB.',
            'bukti_kembali_sisa.mimes' => 'Bukti pengembalian sisa dana kas harus berformat JPG, JPEG, PNG, atau PDF.',
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
