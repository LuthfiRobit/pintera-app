<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\FaseMapping;

use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\User;

final class SetFaseDefaultMappingAction
{
    use ResolveLembagaScopeTrait;

    public function __construct(
        private readonly CreateFaseDefaultMappingAction $createFaseDefaultMappingAction,
    ) {}

    public function executeCreate(User $actor, string $bentukPendidikan, ?string $tingkat, int $faseId, ?int $lembagaIdDiminta): FaseDefaultMapping
    {
        $lembagaId = $this->resolveLembagaId($actor, $lembagaIdDiminta);

        return $this->createFaseDefaultMappingAction->execute(new FaseDefaultMappingData(
            bentukPendidikan: $bentukPendidikan,
            tingkat: $tingkat,
            faseId: $faseId,
            lembagaId: $lembagaId,
        ));
    }
}
