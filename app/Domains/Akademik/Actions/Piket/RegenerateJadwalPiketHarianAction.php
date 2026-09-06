<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Piket;

use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use Illuminate\Support\Facades\DB;

final class RegenerateJadwalPiketHarianAction
{
    public function __construct(
        private readonly GenerateJadwalPiketHarianAction $generateAction,
    ) {}

    public function execute(int $lembagaId, int $semesterId): void
    {
        DB::transaction(function () use ($lembagaId, $semesterId) {
            // Langkah 1: ambil kandidat
            $kandidat = PiketHarian::where('lembaga_id', $lembagaId)
                ->where('tanggal', '>=', now()->toDateString())
                ->where('sumber', 'dari_jadwal_mingguan')
                ->get();

            // Langkah 2: filter buang yang sudah dipakai
            $bolehDihapus = $kandidat->reject(function (PiketHarian $baris) {
                return SesiPembelajaran::where('diisi_oleh_guru_id', $baris->guru_id)
                    ->where('tanggal', $baris->tanggal->toDateString())
                    ->where('lembaga_id', $baris->lembaga_id)
                    ->exists();
            });

            // Langkah 3: delete + generate ulang
            PiketHarian::whereIn('id', $bolehDihapus->pluck('id'))->delete();

            $jadwalList = JadwalPiketMingguan::where('lembaga_id', $lembagaId)
                ->where('semester_id', $semesterId)
                ->get();

            foreach ($jadwalList as $jadwal) {
                $this->generateAction->execute($jadwal);
            }
        });
    }
}
