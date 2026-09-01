<?php

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\DataTransferObjects\JenisTagihanData;
use App\Domains\Keuangan\Enums\TipeTagihan;
use App\Domains\Keuangan\Events\BillTypeActivated;
use App\Domains\Keuangan\Models\JenisTagihan;
use Illuminate\Support\Facades\DB;

class UpdateJenisTagihanAction
{
    public function __construct(
        private readonly SyncJenisTagihanBillingConfigAction $syncBillingConfig,
    ) {}

    public function execute(JenisTagihan $jenisTagihan, JenisTagihanData $data): JenisTagihan
    {
        return DB::transaction(function () use ($jenisTagihan, $data) {
            $wasActive = (bool) $jenisTagihan->is_active;

            $syncResult = $this->syncBillingConfig->execute($jenisTagihan, $data->billing);

            $tipeEnum = isset($data->attributes['tipe'])
                ? ($data->attributes['tipe'] instanceof TipeTagihan ? $data->attributes['tipe'] : TipeTagihan::tryFrom($data->attributes['tipe']))
                : null;

            $nullified = $tipeEnum ? $this->nullifyFieldsNotOwnedBy($tipeEnum) : [];

            $attributes = array_merge(
                $nullified,
                $data->attributes,
                [
                    'nama' => $data->nama,
                    'kategori' => $data->kategori,
                    'bisa_dicicil' => $data->bisaDicicil,
                    'maks_cicilan' => $data->maksCicilan,
                ]
            );

            $jenisTagihan->update($attributes);

            $fresh = $jenisTagihan->fresh();
            $fresh->syncBillingConfigResult = $syncResult;

            // Menggantikan JenisTagihan::booted() lama (model tidak boleh punya business
            // logic) — satu-satunya call site nyata adalah alur ini (update() generik via
            // controller), store()/create() tidak pernah memicu transisi is_active.
            if (! $wasActive && (bool) $fresh->is_active) {
                event(new BillTypeActivated($fresh));
            }

            return $fresh;
        });
    }

    private function nullifyFieldsNotOwnedBy(TipeTagihan $tipe): array
    {
        $ownedByTipe = match ($tipe) {
            TipeTagihan::Harian => ['offset_hari_jatuh_tempo'],
            TipeTagihan::Mingguan => ['hari_generate', 'offset_hari_jatuh_tempo'],
            TipeTagihan::Bulanan => ['tanggal_generate', 'hari_jatuh_tempo'],
            TipeTagihan::Tahunan => ['bulan_generate', 'tanggal_generate', 'hari_jatuh_tempo'],
            TipeTagihan::Sekali => [],
        };

        $semuaFieldPendukung = ['hari_generate', 'bulan_generate', 'tanggal_generate', 'hari_jatuh_tempo', 'offset_hari_jatuh_tempo'];

        return array_fill_keys(array_diff($semuaFieldPendukung, $ownedByTipe), null);
    }
}
