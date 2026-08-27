<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\RppData;
use App\Domains\Akademik\Models\Rpp;
use App\Models\Kelas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $kelasId = $this->input('kelas_id');
            $rpp = $this->route('rpp');
            if (! $kelasId || ! $rpp) {
                return;
            }

            $kelas = Kelas::find($kelasId);
            if ($kelas && $kelas->tahun_ajaran_id !== $rpp->semester->tahun_ajaran_id) {
                $validator->errors()->add('kelas_id', 'Kelas yang dipilih bukan berasal dari tahun ajaran yang sama dengan semester dokumen RPP ini.');
            }
        });
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

    public function toDTO(Rpp $rpp, Kelas $kelas): RppData
    {
        $validated = $this->validated();

        return new RppData(
            yayasanId: $rpp->yayasan_id,
            lembagaId: $rpp->lembaga_id,
            guruId: $rpp->guru_id,
            tahunAjaranId: $rpp->tahun_ajaran_id,
            semesterId: $rpp->semester_id,
            kelasId: $kelas->id,
            judulTopik: (string) $validated['judul_topik'],
            alokasiWaktu: (string) $validated['alokasi_waktu'],
            mataPelajaranId: ! empty($validated['mata_pelajaran_id']) ? (int) $validated['mata_pelajaran_id'] : null,
            pertemuanKe: $validated['pertemuan_ke'] ?? null,
            file: $this->file('file'),
        );
    }
}
