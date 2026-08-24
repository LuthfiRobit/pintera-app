<?php

declare(strict_types=1);

namespace App\Domains\Keuangan\Actions\Webhook;

use App\Domains\Keuangan\Contracts\BriInboundAuthenticatorInterface;

class IssueBriAccessTokenAction
{
    public function __construct(private readonly BriInboundAuthenticatorInterface $authenticator)
    {
    }

    public function execute(string $clientId, string $clientSecret): ?string
    {
        return $this->authenticator->issueToken($clientId, $clientSecret);
    }
}