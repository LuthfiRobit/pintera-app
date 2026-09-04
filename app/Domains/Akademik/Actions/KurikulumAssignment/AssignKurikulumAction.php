<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KurikulumAssignment;

use App\Domains\Akademik\DataTransferObjects\KurikulumAssignmentData;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\User;

final class AssignKurikulumAction
{
    use ResolveLembagaScopeTrait;

    public function __construct(
        private readonly CreateKurikulumAssignmentAction $createKurikulumAssignmentAction,
    ) {}

    public function executeCreate(User $actor, string $bentukPendidikan, ?string $tingkat, string $kurikulum, ?int $lembagaIdDiminta, int $tahunAjaranId): KurikulumAssignment
    {
        $lembagaId = $this->resolveLembagaId($actor, $lembagaIdDiminta);

        return $this->createKurikulumAssignmentAction->execute(new KurikulumAssignmentData(
            bentukPendidikan: $bentukPendidikan,
            tingkat: $tingkat,
            kurikulum: $kurikulum,
            lembagaId: $lembagaId,
            tahunAjaranId: $tahunAjaranId,
        ));
    }
}
