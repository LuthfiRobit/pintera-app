<?php
// app/Services/DashboardStatsService.php

namespace App\Services;

use App\Domains\Keuangan\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\TahunAjaran;

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

    private function tahunAjaranAktif(int $lembagaId): ?TahunAjaran
    {
        return TahunAjaran::withoutGlobalScopes()
            ->where('lembaga_id', $lembagaId)
            ->where('status_aktif', true)
            ->first();
    }
}
