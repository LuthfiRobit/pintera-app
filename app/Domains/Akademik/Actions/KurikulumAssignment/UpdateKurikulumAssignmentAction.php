<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KurikulumAssignment;

use App\Domains\Akademik\DataTransferObjects\KurikulumAssignmentData;
use App\Domains\Akademik\Models\KurikulumAssignment;

final class UpdateKurikulumAssignmentAction
{
    public function execute(KurikulumAssignment $assignment, KurikulumAssignmentData $data): KurikulumAssignment
    {
        $assignment->update([
            'bentuk_pendidikan' => $data->bentukPendidikan,
            'tingkat' => $data->tingkat,
            'kurikulum' => $data->kurikulum,
        ]);

        return $assignment;
    }
}
