<?php

namespace App\Domains\Akademik\Actions\Presensi;

use App\Domains\Akademik\DataTransferObjects\JurnalPresensiData;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\PresensiNotificationService;
use Illuminate\Support\Facades\DB;

final class RecordJurnalDanPresensiAction
{
    public function __construct(
        private readonly PresensiNotificationService $presensiNotificationService,
    ) {}

    public function execute(SesiPembelajaran $sesi, JurnalPresensiData $data, ?int $diisiOlehGuruId = null): SesiPembelajaran
    {
        $perluDicek = [];

        $sesiTerbaru = DB::transaction(function () use ($sesi, $data, $diisiOlehGuruId, &$perluDicek) {
            $sesi->update(array_filter([
                'materi' => $data->materi,
                'diisi_oleh_guru_id' => $diisiOlehGuruId,
            ], fn ($value, $key) => $key !== 'diisi_oleh_guru_id' || $value !== null, ARRAY_FILTER_USE_BOTH));

            $statusLamaPerSiswa = $sesi->presensi()->get()->keyBy('siswa_id')
                ->map(fn ($p) => $p->status->value);

            foreach ($data->presensi as $siswaId => $status) {
                $sesi->presensi()->where('siswa_id', $siswaId)->update([
                    'status' => $status,
                    'keterangan' => $data->keterangan[$siswaId] ?? null,
                ]);

                $perluDicek[] = ['siswa_id' => $siswaId, 'status_lama' => $statusLamaPerSiswa->get($siswaId)];
            }

            return $sesi->fresh();
        });

        // WAJIB di luar transaksi -- pengiriman notifikasi (network I/O ke WhatsApp
        // Gateway) tidak boleh menahan transaksi DB terbuka.
        foreach ($perluDicek as $item) {
            $presensiTerbaru = $sesiTerbaru->presensi()->where('siswa_id', $item['siswa_id'])->first();
            if ($presensiTerbaru !== null) {
                $this->presensiNotificationService->kirimJikaPerluAtasPerubahan($presensiTerbaru, $item['status_lama']);
            }
        }

        return $sesiTerbaru;
    }
}
