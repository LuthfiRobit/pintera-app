<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\AsesmenData;
use App\Domains\Akademik\Enums\BentukPendidikan;
use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\MataPelajaran;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAsesmenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('asesmen.kelola');
    }

    /**
     * subjek_type WAJIB derivasi dari bentuk_pendidikan lembaga aktor, bukan input
     * pengguna -- PAUD (KB/TPA/SPS/TK) selalu elemen_cp, jenjang lain selalu
     * mata_pelajaran. Nilai apa pun yang dikirim klien di field ini diabaikan.
     */
    protected function prepareForValidation(): void
    {
        $bentuk = BentukPendidikan::tryFrom($this->user()->lembaga?->bentuk_pendidikan ?? '');

        $this->merge([
            'subjek_type' => $bentuk?->isPaud() ? 'elemen_cp' : 'mata_pelajaran',
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'kelas_id' => ['required', 'integer'],
            'subjek_type' => ['required', Rule::in(['mata_pelajaran', 'elemen_cp'])],
            'subjek_id' => ['required', 'integer', function ($attribute, $value, $fail) {
                $exists = match ($this->input('subjek_type')) {
                    'mata_pelajaran' => MataPelajaran::withoutGlobalScopes()->where('id', $value)->exists(),
                    'elemen_cp' => ElemenCp::where('id', $value)->exists(),
                    default => false,
                };
                if (! $exists) {
                    $fail('Subjek penilaian yang dipilih tidak valid.');
                }
            }],
            'semester_id' => ['required', 'integer'],
            'jenis' => ['required', Rule::enum(JenisAsesmen::class)],
            'judul' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'komponen_id' => ['required', 'array', 'min:1'],
            'komponen_id.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'komponen_id.required' => 'Pilih minimal satu Tujuan Pembelajaran.',
            'komponen_id.min' => 'Pilih minimal satu Tujuan Pembelajaran.',
        ];
    }

    public function toDTO(): AsesmenData
    {
        return AsesmenData::fromArray($this->validated());
    }
}
