<?php

namespace App\Domains\Keuangan\Actions\JenisTagihan;

use App\Domains\Keuangan\DataTransferObjects\JenisTagihanData;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Events\BillTypeActivated;
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

            $this->syncBillingConfig->execute($jenisTagihan, $data->billing);

            $attributes = array_merge($data->attributes, [
                'nama'         => $data->nama,
                'kategori'     => $data->kategori,
                'bisa_dicicil' => $data->bisaDicicil,
                'maks_cicilan' => $data->maksCicilan,
            ]);

            $jenisTagihan->update($attributes);

            $fresh = $jenisTagihan->fresh();

            // Menggantikan JenisTagihan::booted() lama (model tidak boleh punya business
            // logic) — satu-satunya call site nyata adalah alur ini (update() generik via
            // controller), store()/create() tidak pernah memicu transisi is_active.
            if (! $wasActive && (bool) $fresh->is_active) {
                event(new BillTypeActivated($fresh));
            }

            return $fresh;
        });
    }
}
