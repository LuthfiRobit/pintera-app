<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Models\EkstrakurikulerLembaga;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCatatanWaliKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rapor.input-wali');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'integer', 'exists:semester,id'],
            'catatan_sikap' => ['nullable', 'string', 'max:2000'],
            'catatan_perkembangan' => ['nullable', 'string', 'max:2000'],
            'tinggi_badan_cm' => ['nullable', 'numeric', 'min:0'],
            'berat_badan_kg' => ['nullable', 'numeric', 'min:0'],
            'lingkar_kepala_cm' => ['nullable', 'numeric', 'min:0'],
            'ekstrakurikuler' => ['nullable', 'array'],
            'ekstrakurikuler.*.nama' => [
                'required_with:ekstrakurikuler',
                Rule::in($this->ekskulOptionsUntukSiswa()),
            ],
            'ekstrakurikuler.*.peran' => ['nullable', 'string', 'max:255'],
            'prestasi' => ['nullable', 'array'],
            'prestasi.*.nama' => ['required_with:prestasi', 'string', 'max:255'],
            'prestasi.*.tingkat' => ['nullable', 'string', 'max:255'],
            'prestasi.*.tahun' => ['nullable', 'string', 'max:4'],
            'pkl_info' => ['nullable', 'array'],
            'pkl_info.*.perusahaan' => ['required_with:pkl_info', 'string', 'max:255'],
            'pkl_info.*.posisi' => ['nullable', 'string', 'max:255'],
            'pkl_info.*.durasi' => ['nullable', 'string', 'max:255'],
            'keterangan_kenaikan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDTO(int $siswaId): CatatanWaliKelasData
    {
        return CatatanWaliKelasData::fromArray([...$this->validated(), 'siswa_id' => $siswaId]);
    }

    /**
     * @return array<int, string>
     */
    private function ekskulOptionsUntukSiswa(): array
    {
        $siswa = $this->route('siswa');

        if ($siswa === null) {
            return [];
        }

        return EkstrakurikulerLembaga::where('lembaga_id', $siswa->lembaga_id)->pluck('nama_ekskul')->all();
    }
}
