<?php

namespace App\Domains\Identity\Events;

use App\Domains\Identity\Models\Person;

// Deliberately NOT ShouldQueue: Keuangan's reparenting listener (and any
// future domain listener) must run synchronously inside
// MergePersonsAction's transaction, so a listener failure rolls back the
// whole merge instead of leaving it half-applied. Do not add ShouldQueue.
class PersonsMerged
{
    public function __construct(
        public readonly Person $losing,
        public readonly Person $winning,
    ) {}
}
