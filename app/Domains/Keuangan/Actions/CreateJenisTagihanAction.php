<?php

namespace App\Domains\Keuangan\Actions;

use App\Domains\Keuangan\DataTransferObjects\JenisTagihanData;
use App\Domains\Keuangan\Models\JenisTagihan;
use Illuminate\Support\Facades\DB;

class CreateJenisTagihanAction
{
    public function __construct(
        private readonly SyncJenisTagihanBillingConfigAction $syncBillingConfig,
    ) {}

    public function execute(int $lembagaId, JenisTagihanData $data): JenisTagihan
    {
        return DB::transaction(function () use ($lembagaId, $data) {
            $attributes = array_merge($data->attributes, [
                'lembaga_id'   => $lembagaId,
                'nama'         => $data->nama,
                'kategori'     => $data->kategori,
                'bisa_dicicil' => $data->bisaDicicil,
                'maks_cicilan' => $data->maksCicilan,
            ]);

            $jenisTagihan = JenisTagihan::create($attributes);

            if ($data->billing !== null) {
                $this->syncBillingConfig->execute($jenisTagihan, $data->billing);
            }

            return $jenisTagihan->fresh();
        });
    }
}
