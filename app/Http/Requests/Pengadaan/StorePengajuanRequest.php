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

    public function messages(): array
    {
        return [
            'judul_pengajuan.required' => 'Judul pengajuan wajib diisi.',
            'judul_pengajuan.max' => 'Judul pengajuan maksimal 255 karakter.',
            'tingkat_urgensi.required' => 'Tingkat urgensi wajib dipilih.',
            'items.required' => 'Minimal harus ada 1 item barang yang diajukan.',
            'items.min' => 'Minimal harus ada 1 item barang yang diajukan.',
            'items.*.nama_barang.required' => 'Nama barang wajib diisi.',
            'items.*.kategori_aset_id.required' => 'Kategori aset wajib dipilih.',
            'items.*.kategori_aset_id.exists' => 'Kategori aset yang dipilih tidak valid.',
            'items.*.target_ruangan_id.required' => 'Target ruangan penempatan wajib dipilih.',
            'items.*.target_ruangan_id.exists' => 'Ruangan yang dipilih tidak valid.',
            'items.*.qty.required' => 'Jumlah (Qty) barang wajib diisi.',
            'items.*.qty.min' => 'Jumlah (Qty) barang minimal 1 unit.',
            'items.*.satuan.required' => 'Satuan barang wajib diisi (misal: Unit, Buah, Set).',
            'items.*.estimasi_harga_satuan.required' => 'Estimasi harga satuan wajib diisi.',
            'items.*.estimasi_harga_satuan.min' => 'Estimasi harga satuan tidak boleh negatif.',
            'items.*.tipe_pencatatan.required' => 'Tipe pencatatan aset (Unit / Batch) wajib dipilih.',
            'items.*.tipe_pencatatan.in' => 'Tipe pencatatan harus unit atau batch.',
            'items.*.foto_referensi.image' => 'Berkas foto referensi harus berupa gambar (JPG, PNG, WebP).',
            'items.*.foto_referensi.max' => 'Ukuran foto referensi maksimal 5MB.',
        ];
    }

    public function toDTO(?int $yayasanId = null, ?int $lembagaId = null): PengajuanPengadaanData
    {
        return PengajuanPengadaanData::fromArray($this->validated(), $yayasanId, $lembagaId);
    }
}
