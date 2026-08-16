<?php

namespace App\Domains\Shared\Context\Contracts;

interface TenantContextInterface
{
    public function activeLembagaId(): ?int;

    public function activeYayasanId(): ?int;

    public function isYayasanScope(): bool;
}
