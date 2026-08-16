<?php

namespace App\Http\Requests\Sarpras;

use App\Domains\Sarpras\Enums\JenisRuangan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRuanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sarpras.ruangan.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'gedung_id' => ['required', 'exists:gedung,id'],
            'kode_ruangan' => ['required', 'string', 'max:50'],
            'nama_ruangan' => ['required', 'string', 'max:255'],
            'lantai' => ['nullable', 'integer', 'min:1', 'max:50'],
            'jenis_ruangan' => ['required', Rule::enum(JenisRuangan::class)],
            'kapasitas_siswa' => ['nullable', 'integer', 'min:1', 'max:500'],
            'luas_m2' => ['nullable', 'numeric', 'min:1', 'max:9999.99'],
            'penanggung_jawab_guru_id' => ['nullable', 'exists:guru,id'],
            'is_shared' => ['nullable', 'boolean'],
            'is_aktif' => ['nullable', 'boolean'],
        ];
    }
}
