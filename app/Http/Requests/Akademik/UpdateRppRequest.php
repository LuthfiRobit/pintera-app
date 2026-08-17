<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateRppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rpp.kelola');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['nullable', 'integer', 'exists:mata_pelajaran,id'],
            'judul_topik' => ['required', 'string', 'max:255'],
            'alokasi_waktu' => ['required', 'string', 'max:100'],
            'pertemuan_ke' => ['nullable', 'string', 'max:50'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kelas_id.required' => 'Kelas wajib dipilih.',
            'judul_topik.required' => 'Judul topik / lingkup materi wajib diisi.',
            'alokasi_waktu.required' => 'Alokasi waktu pertemuan wajib diisi.',
            'file.mimes' => 'Format berkas harus berupa PDF, DOC, atau DOCX.',
            'file.max' => 'Ukuran berkas maksimal adalah 10 Megabytes (MB).',
        ];
    }
}
