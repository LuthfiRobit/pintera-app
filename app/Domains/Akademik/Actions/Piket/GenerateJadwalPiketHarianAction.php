<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Piket;

use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Services\KalenderAkademikResolver;
use Carbon\CarbonPeriod;

final class GenerateJadwalPiketHarianAction
{
    public function __construct(
        private readonly KalenderAkademikResolver $kalenderResolver,
    ) {}

    public function execute(JadwalPiketMingguan $jadwal): void
    {
        $jadwal->loadMissing('semester', 'lembaga');
        $semester = $jadwal->semester;
        $lembaga = $jadwal->lembaga;

        if ($semester->tanggal_mulai === null || $semester->tanggal_selesai === null) {
            return;
        }

        // Mulai dari HARI INI (bukan semester->tanggal_mulai) -- kalau JadwalPiketMingguan
        // dibuat di tengah semester, jangan generate PiketHarian utk tanggal LAMPAU. Wewenang
        // piket sengaja hanya berlaku hari ini/ke depan (spec §2 poin 3); men-generate baris
        // utk tanggal lampau akan diam-diam memberi akses piket ke sesi lama lewat
        // PiketAccessChecker (yang sengaja cuma cek kecocokan tabel, bukan cek tanggal=hari ini).
        $tanggalMulai = $semester->tanggal_mulai->isPast() ? now('Asia/Jakarta')->startOfDay() : $semester->tanggal_mulai;
        $periode = CarbonPeriod::create($tanggalMulai, $semester->tanggal_selesai);

        foreach ($periode as $tanggal) {
            if ((int) $tanggal->dayOfWeek !== (int) $jadwal->hari) {
                continue;
            }

            $resolusi = $this->kalenderResolver->resolve($lembaga, $tanggal);
            if ($resolusi['libur']) {
                continue;
            }

            PiketHarian::firstOrCreate(
                [
                    'lembaga_id' => $jadwal->lembaga_id,
                    'guru_id' => $jadwal->guru_id,
                    'tanggal' => $tanggal->toDateString(),
                ],
                [
                    'sumber' => 'dari_jadwal_mingguan',
                    'jadwal_piket_mingguan_id' => $jadwal->id,
                ]
            );
        }
    }
}
