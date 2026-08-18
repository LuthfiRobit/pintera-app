<?php

namespace App\Domains\Akademik\Actions\Presensi;

use App\Domains\Akademik\DataTransferObjects\JurnalPresensiData;
use App\Domains\Akademik\Models\SesiPembelajaran;
use Illuminate\Support\Facades\DB;

final class RecordJurnalDanPresensiAction
{
    public function execute(SesiPembelajaran $sesi, JurnalPresensiData $data): SesiPembelajaran
    {
        return DB::transaction(function () use ($sesi, $data) {
            $sesi->update(['materi' => $data->materi]);

            foreach ($data->presensi as $siswaId => $status) {
                $sesi->presensi()->where('siswa_id', $siswaId)->update(['status' => $status]);
            }

            return $sesi->fresh();
        });
    }
}
