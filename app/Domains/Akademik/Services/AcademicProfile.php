<?php

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Enums\ModePembelajaran;
use InvalidArgumentException;

/**
 * Immutable value object yang menyediakan platform defaults untuk pre-fill
 * UX (mis. mode pembelajaran, kunci template rapor). BUKAN sumber kebenaran
 * konfigurasi tenant dan TIDAK BOLEH dipakai untuk menimpa nilai yang sudah
 * dipilih/disimpan admin -- lihat spec Sprint 4 §Keputusan Desain poin 1.
 */
final class AcademicProfile
{
    private function __construct(
        public readonly ModePembelajaran $learningMode,
        public readonly string $reportTemplate,
    ) {}

    public static function fromBentukPendidikan(string $bentukPendidikan): self
    {
        return new self(
            learningMode: ModePembelajaran::fromBentukPendidikan($bentukPendidikan),
            reportTemplate: match (true) {
                in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true) => 'paud',
                $bentukPendidikan === 'SMK' => 'smk',
                in_array($bentukPendidikan, ['SMP', 'SMA'], true) => 'smp-sma',
                $bentukPendidikan === 'SD' => 'sd',
                // SLB memakai template SD sbg KEPUTUSAN FINAL yang disengaja (diformalkan
                // Prioritas #3 Roadmap Kurikulum Dinamis, 27 Agustus 2026) -- bukan fallback
                // diam-diam. Tidak ada pelanggan SLB nyata dgn kebutuhan struktur rapor
                // berbeda saat ini; keputusan ini revisable kalau itu berubah.
                $bentukPendidikan === 'SLB' => 'sd',
                default => throw new InvalidArgumentException("Unsupported bentuk_pendidikan: {$bentukPendidikan}"),
            },
        );
    }
}
