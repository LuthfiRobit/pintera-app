<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Piket;

use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;

final class RegenerateJadwalPiketHarianAction
{
    public function __construct(
        private readonly GenerateJadwalPiketHarianAction $generateAction,
    ) {}

    public function execute(int $lembagaId, int $semesterId): void
    {
        DB::transaction(function () use ($lembagaId, $semesterId) {
            $semester = Semester::withoutGlobalScopes()->findOrFail($semesterId);

            // Langkah 1: ambil kandidat -- dibatasi ke RENTANG TANGGAL semester ini saja
            // (bukan lewat jadwal_piket_mingguan_id: FK itu di-null-kan otomatis ON DELETE
            // saat JadwalPiketMingguan dihapus SEBELUM baris ini dipanggil dari destroy(),
            // jadi scoping via relasi FK tidak bisa diandalkan di titik ini). Rentang
            // tanggal antar semester tidak pernah tumpang tindih, jadi ini tetap aman
            // mengisolasi baris otomatis milik semester lain yang jadwalnya kebetulan
            // sudah disiapkan lebih awal (mis. saat admin mengganti semester jadwal
            // mingguan lewat halaman edit).
            $kandidat = PiketHarian::where('lembaga_id', $lembagaId)
                ->where('tanggal', '>=', now()->toDateString())
                ->where('tanggal', '<=', $semester->tanggal_selesai)
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
