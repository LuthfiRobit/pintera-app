<?php

namespace App\Domains\Keuangan\Actions;

use App\Domains\Keuangan\DataTransferObjects\JenisTagihanData;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Events\BillTypeActivated;
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

            $jenisTagihan->update([
                'nama'         => $data->nama,
                'kategori'     => $data->kategori,
                'bisa_dicicil' => $data->bisaDicicil,
                'maks_cicilan' => $data->maksCicilan,
            ]);

            $this->syncBillingConfig->execute($jenisTagihan, $data->rawBillingConfig);

            $fresh = $jenisTagihan->fresh();

            // PENTING: Event BillTypeActivated yang sebelumnya di-dispatch oleh model booted()
            // kini di-dispatch di sini saat status bertransisi false -> true.
            if (! $wasActive && (bool) $fresh->is_active) {
                event(new BillTypeActivated($fresh));
            }

            return $fresh;
        });
    }
}
