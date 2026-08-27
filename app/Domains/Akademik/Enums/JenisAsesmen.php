<?php

namespace App\Domains\Akademik\Enums;

enum JenisAsesmen: string
{
    case DiagnostikKognitif = 'diagnostik_kognitif';
    case DiagnostikNonKognitif = 'diagnostik_non_kognitif';
    case Formatif = 'formatif';
    case SumatifLingkupMateri = 'sumatif_lingkup_materi';
    case SumatifAkhirSemester = 'sumatif_akhir_semester';
    case SumatifAkhirJenjang = 'sumatif_akhir_jenjang';

    public function label(): string
    {
        return match ($this) {
            self::DiagnostikKognitif => 'Diagnostik Kognitif',
            self::DiagnostikNonKognitif => 'Diagnostik Non-Kognitif',
            self::Formatif => 'Formatif',
            self::SumatifLingkupMateri => 'Sumatif Lingkup Materi',
            self::SumatifAkhirSemester => 'Sumatif Akhir Semester',
            self::SumatifAkhirJenjang => 'Sumatif Akhir Jenjang',
        };
    }

    /**
     * Jenis asesmen yang secara semantik merupakan SUMBER PERHITUNGAN RAPOR.
     * Dipakai sbg filter di SEMUA tempat yang mengagregasi/menampilkan nilai
     * sbg representasi rapor: RaporCalculationService::hitungRekapKelas(),
     * CapaianKompetensiGenerator::generateNarasi(),
     * DashboardStatsService::statistikProgressRaporKelas(), dan widget
     * "Nilai Terbaru" siswa/orang tua di Admin\DashboardController.
     * Diagnostik dan Formatif SENGAJA tidak termasuk -- keduanya asesmen
     * untuk proses pembelajaran (pemetaan kesiapan belajar, penyesuaian
     * metode ajar), bukan komponen nilai rapor.
     *
     * Kalau menambah case baru ke enum ini di masa depan, WAJIB secara sadar
     * memutuskan apakah case itu masuk daftar ini atau tidak -- jangan
     * dibiarkan default masuk/keluar tanpa keputusan eksplisit. Kalau
     * menambah CONSUMER BARU yang membaca nilai sbg representasi rapor,
     * WAJIB pakai method ini sbg filter -- lihat riwayat 3 consumer yang
     * sempat lolos audit awal Priority #6 (27 Agustus 2026).
     *
     * @return array<int, self>
     */
    public static function masukRapor(): array
    {
        return [
            self::SumatifLingkupMateri,
            self::SumatifAkhirSemester,
            self::SumatifAkhirJenjang,
        ];
    }
}
