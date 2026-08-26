<?php
// app/Services/DashboardStatsService.php

namespace App\Services;

use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Models\KuotaCutiConfig;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Karyawan;
use App\Models\Kelas;
use App\Models\Pendaftaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

class DashboardStatsService
{
    public function statistikSpmb(int $lembagaId): array
    {
        $tahunAjaran = $this->tahunAjaranAktif($lembagaId);

        if (! $tahunAjaran) {
            return ['total' => 0, 'menunggu_verifikasi' => 0, 'diterima' => 0, 'ditolak' => 0];
        }

        $counts = Pendaftaran::where('lembaga_id', $lembagaId)
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'menunggu_verifikasi' => (int) ($counts['menunggu_verifikasi'] ?? 0),
            'diterima' => (int) ($counts['diterima'] ?? 0),
            'ditolak' => (int) ($counts['ditolak'] ?? 0),
        ];
    }

    public function trenPendaftaranHarian(int $lembagaId): array
    {
        $mulai = now()->subDays(29)->startOfDay();

        $counts = Pendaftaran::where('lembaga_id', $lembagaId)
            ->where('submitted_at', '>=', $mulai)
            ->selectRaw('DATE(submitted_at) as tanggal, count(*) as total')
            ->groupBy('tanggal')
            ->pluck('total', 'tanggal');

        $labels = [];
        $data = [];

        for ($i = 29; $i >= 0; $i--) {
            $hari = now()->subDays($i);
            $labels[] = $hari->translatedFormat('d M');
            $data[] = (int) ($counts[$hari->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    public function statistikKeuangan(int $lembagaId): array
    {
        $tahunAjaran = $this->tahunAjaranAktif($lembagaId);

        if (! $tahunAjaran) {
            return [
                'rpTerkumpul' => 0, 'rpBelumLunas' => 0, 'pembayaranMenungguVerifikasi' => 0,
                'donut' => ['belum_bayar' => 0, 'dicicil' => 0, 'lunas' => 0],
            ];
        }

        $tagihanQuery = fn () => Tagihan::whereHas(
            'pendaftaran',
            fn ($q) => $q->where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $tahunAjaran->id)
        );

        $rpTerkumpul = (int) $tagihanQuery()->where('status', 'lunas')->sum('total_tagihan');
        $rpBelumLunas = (int) $tagihanQuery()->whereIn('status', ['belum_bayar', 'dicicil'])->sum('total_tagihan');

        $donutCounts = $tagihanQuery()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pembayaranMenungguVerifikasi = Pembayaran::where('status', 'menunggu_verifikasi')
            ->where(function ($q) use ($lembagaId, $tahunAjaran) {
                $q->whereHas('tagihan.pendaftaran', fn ($p) => $p->where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $tahunAjaran->id))
                    ->orWhereHas('cicilan.skemaCicilan.tagihan.pendaftaran', fn ($p) => $p->where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $tahunAjaran->id));
            })
            ->count();

        return [
            'rpTerkumpul' => $rpTerkumpul,
            'rpBelumLunas' => $rpBelumLunas,
            'pembayaranMenungguVerifikasi' => $pembayaranMenungguVerifikasi,
            'donut' => [
                'belum_bayar' => (int) ($donutCounts['belum_bayar'] ?? 0),
                'dicicil' => (int) ($donutCounts['dicicil'] ?? 0),
                'lunas' => (int) ($donutCounts['lunas'] ?? 0),
            ],
        ];
    }

    public function statistikPresensiSdm(array $lembagaIds): array
    {
        $counts = AttendanceRecord::whereIn('lembaga_id', $lembagaIds)
            ->whereDate('tanggal', now()->toDateString())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'hadir' => (int) ($counts['hadir'] ?? 0),
            'izin' => (int) ($counts['izin'] ?? 0),
            'sakit' => (int) ($counts['sakit'] ?? 0),
            'alpa' => (int) ($counts['alpa'] ?? 0),
            'cuti' => (int) ($counts['cuti'] ?? 0),
        ];
    }

    public function statistikProgressRaporKelas(Kelas $kelas): array
    {
        $semester = Semester::where('lembaga_id', $kelas->lembaga_id)->where('status_aktif', true)->first();

        if (! $semester) {
            return ['persen' => 0.0, 'terisi' => 0, 'total' => 0];
        }

        $totalSiswa = Siswa::where('kelas_id', $kelas->id)->count();
        $totalKomponen = KomponenPenilaian::where('semester_id', $semester->id)
            ->where('assessment_type', 'numeric')
            ->whereHasMorph('subjek', [MataPelajaran::class], fn ($q) => $q->where('lembaga_id', $kelas->lembaga_id))
            ->count();

        $totalTerisi = NilaiSiswa::whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelas->id))
            ->whereHas('komponenPenilaian', fn ($q) => $q->where('semester_id', $semester->id))
            ->whereNotNull('nilai_angka')
            ->count();

        $totalSlot = $totalSiswa * $totalKomponen;

        return [
            'persen' => $totalSlot > 0 ? round($totalTerisi / $totalSlot * 100, 1) : 0.0,
            'terisi' => $totalTerisi,
            'total' => $totalSlot,
        ];
    }

    public function statistikSisaKuotaCuti(Karyawan $karyawan): ?array
    {
        $config = KuotaCutiConfig::where('jenis_karyawan_id', $karyawan->jenis_karyawan_id)
            ->where(fn ($q) => $q->where('lembaga_id', $karyawan->lembaga_id)->orWhere('yayasan_id', $karyawan->yayasan_id))
            ->first();

        if (! $config) {
            return null;
        }

        $terpakai = $karyawan->pengajuanIzinCuti()
            ->whereHas('approvalRequest', fn ($q) => $q->where('status', ApprovalStatus::Approved))
            ->whereYear('tanggal_mulai', now()->year)
            ->get()
            ->sum(fn ($p) => $p->tanggal_mulai->diffInDays($p->tanggal_selesai) + 1);

        return [
            'jatah' => $config->jatah_hari_per_tahun,
            'terpakai' => $terpakai,
            'sisa' => max(0, $config->jatah_hari_per_tahun - $terpakai),
        ];
    }

    public function trenPertumbuhanYayasan(): array
    {
        $mulai = now()->subMonths(5)->startOfMonth();

        $counts = Yayasan::where('created_at', '>=', $mulai)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as bulan, count(*) as total")
            ->groupBy('bulan')
            ->pluck('total', 'bulan');

        $labels = [];
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $bulan = now()->subMonths($i);
            $labels[] = $bulan->translatedFormat('M Y');
            $data[] = (int) ($counts[$bulan->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function tahunAjaranAktif(int $lembagaId): ?TahunAjaran
    {
        return TahunAjaran::withoutGlobalScopes()
            ->where('lembaga_id', $lembagaId)
            ->where('status_aktif', true)
            ->first();
    }
}
