<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\RppData;
use App\Models\Kelas;
use App\Models\Semester;
use Illuminate\Foundation\Http\FormRequest;

final class StoreRppRequest extends FormRequest
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
            'semester_id' => ['required', 'integer', 'exists:semester,id'],
            'mata_pelajaran_id' => ['nullable', 'integer', 'exists:mata_pelajaran,id'],
            'judul_topik' => ['required', 'string', 'max:255'],
            'alokasi_waktu' => ['required', 'string', 'max:100'],
            'pertemuan_ke' => ['nullable', 'string', 'max:50'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kelas_id.required' => 'Kelas wajib dipilih.',
            'semester_id.required' => 'Semester wajib dipilih.',
            'judul_topik.required' => 'Judul topik / lingkup materi wajib diisi.',
            'alokasi_waktu.required' => 'Alokasi waktu pertemuan wajib diisi.',
            'file.required' => 'Berkas RPP / Modul Ajar wajib diunggah.',
            'file.mimes' => 'Format berkas harus berupa PDF, DOC, atau DOCX.',
            'file.max' => 'Ukuran berkas maksimal adalah 10 Megabytes (MB).',
        ];
    }

    public function toDTO(int $guruId, Kelas $kelas, Semester $semester): RppData
    {
        $validated = $this->validated();

        return new RppData(
            yayasanId: $kelas->yayasan_id ?: $kelas->lembaga->yayasan_id,
            lembagaId: $kelas->lembaga_id,
            guruId: $guruId,
            tahunAjaranId: $semester->tahun_ajaran_id,
            semesterId: $semester->id,
            kelasId: $kelas->id,
            judulTopik: (string) $validated['judul_topik'],
            alokasiWaktu: (string) $validated['alokasi_waktu'],
            mataPelajaranId: ! empty($validated['mata_pelajaran_id']) ? (int) $validated['mata_pelajaran_id'] : null,
            pertemuanKe: $validated['pertemuan_ke'] ?? null,
            file: $this->file('file'),
        );
    }
}
