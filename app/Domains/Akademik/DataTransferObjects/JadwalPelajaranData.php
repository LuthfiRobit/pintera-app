<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

use Illuminate\Http\Request;

final readonly class JadwalPelajaranData
{
    public function __construct(
        public int $lembagaId,
        public int $kelasId,
        public int $guruId,
        public int $jamPelajaranId,
        public int $semesterId,
        public ?int $mataPelajaranId = null,
        public ?int $ruanganId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            lembagaId: (int) $data['lembaga_id'],
            kelasId: (int) $data['kelas_id'],
            guruId: (int) $data['guru_id'],
            jamPelajaranId: (int) $data['jam_pelajaran_id'],
            semesterId: (int) $data['semester_id'],
            mataPelajaranId: ! empty($data['mata_pelajaran_id']) ? (int) $data['mata_pelajaran_id'] : null,
            ruanganId: ! empty($data['ruangan_id']) ? (int) $data['ruangan_id'] : null,
        );
    }

    public static function fromRequest(Request $request, int $lembagaId): self
    {
        return new self(
            lembagaId: $lembagaId,
            kelasId: (int) $request->input('kelas_id'),
            guruId: (int) $request->input('guru_id'),
            jamPelajaranId: (int) $request->input('jam_pelajaran_id'),
            semesterId: (int) $request->input('semester_id'),
            mataPelajaranId: $request->filled('mata_pelajaran_id') ? (int) $request->input('mata_pelajaran_id') : null,
            ruanganId: $request->filled('ruangan_id') ? (int) $request->input('ruangan_id') : null,
        );
    }

    public function toArray(): array
    {
        return [
            'lembaga_id' => $this->lembagaId,
            'kelas_id' => $this->kelasId,
            'mata_pelajaran_id' => $this->mataPelajaranId,
            'guru_id' => $this->guruId,
            'jam_pelajaran_id' => $this->jamPelajaranId,
            'semester_id' => $this->semesterId,
            'ruangan_id' => $this->ruanganId,
        ];
    }
}
