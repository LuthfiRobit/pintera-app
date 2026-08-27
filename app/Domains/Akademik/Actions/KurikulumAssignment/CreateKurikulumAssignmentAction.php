<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KurikulumAssignment;

use App\Domains\Akademik\DataTransferObjects\KurikulumAssignmentData;
use App\Domains\Akademik\Models\KurikulumAssignment;

final class CreateKurikulumAssignmentAction
{
    public function execute(KurikulumAssignmentData $data): KurikulumAssignment
    {
        return KurikulumAssignment::create([
            'lembaga_id' => $data->lembagaId,
            'tahun_ajaran_id' => $data->tahunAjaranId,
            'bentuk_pendidikan' => $data->bentukPendidikan,
            'tingkat' => $data->tingkat,
            'kurikulum' => $data->kurikulum,
        ]);
    }
}
