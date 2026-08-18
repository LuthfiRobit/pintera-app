<?php

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Presensi;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PresensiAggregationService
{
    /**
     * @return Collection<int, array{siswa_id:int,nama:string,hadir:int,izin:int,sakit:int,alpa:int,terlambat:int}>
     */
    public function agregasiPerKelas(int $kelasId, Semester $semester): Collection
    {
        $siswaList = Siswa::where('kelas_id', $kelasId)->where('status', 'aktif')->orderBy('nama_lengkap')->get();

        $counts = DB::table('presensi')
            ->select('presensi.siswa_id', 'presensi.status', DB::raw('count(*) as total'))
            ->join('sesi_pembelajaran', 'sesi_pembelajaran.id', '=', 'presensi.sesi_pembelajaran_id')
            ->where('sesi_pembelajaran.kelas_id', $kelasId)
            ->whereBetween('sesi_pembelajaran.tanggal', [$semester->tanggal_mulai, $semester->tanggal_selesai])
            ->groupBy('presensi.siswa_id', 'presensi.status')
            ->get()
            ->groupBy('siswa_id');

        return $siswaList->map(function (Siswa $siswa) use ($counts) {
            $byStatus = $counts->get($siswa->id, collect())->pluck('total', 'status');

            return [
                'siswa_id' => $siswa->id,
                'nama' => $siswa->nama_lengkap,
                'hadir' => (int) ($byStatus['hadir'] ?? 0),
                'izin' => (int) ($byStatus['izin'] ?? 0),
                'sakit' => (int) ($byStatus['sakit'] ?? 0),
                'alpa' => (int) ($byStatus['alpa'] ?? 0),
                'terlambat' => (int) ($byStatus['terlambat'] ?? 0),
            ];
        });
    }
}
