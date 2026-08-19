<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\AsesmenData;
use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Guru;
use Illuminate\Support\Facades\DB;

final class CreateAsesmenAction
{
    public function execute(Guru $guru, AsesmenData $data): Asesmen
    {
        $komponenIds = ! empty($data->komponenId)
            ? KomponenPenilaian::whereIn('id', $data->komponenId)->where('mata_pelajaran_id', $data->mataPelajaranId)->pluck('id')
            : collect();

        return DB::transaction(function () use ($guru, $data, $komponenIds) {
            $asesmen = Asesmen::create([
                'guru_id' => $guru->id,
                'kelas_id' => $data->kelasId,
                'mata_pelajaran_id' => $data->mataPelajaranId,
                'semester_id' => $data->semesterId,
                'jenis' => JenisAsesmen::from($data->jenis),
                'judul' => $data->judul,
                'tanggal' => $data->tanggal,
            ]);

            if ($komponenIds->isNotEmpty()) {
                $asesmen->komponenPenilaian()->attach($komponenIds);
            }

            $siswaList = $asesmen->kelas->siswa()->get();
            foreach ($siswaList as $siswa) {
                foreach ($komponenIds as $komponenId) {
                    NilaiSiswa::firstOrCreate([
                        'asesmen_id' => $asesmen->id,
                        'siswa_id' => $siswa->id,
                        'komponen_penilaian_id' => $komponenId,
                    ]);
                }
            }

            return $asesmen;
        });
    }
}
