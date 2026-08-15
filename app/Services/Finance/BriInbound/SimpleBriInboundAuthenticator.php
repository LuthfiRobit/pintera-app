<?php

namespace App\Services\Finance\BriInbound;

use App\Contracts\BriInboundAuthenticatorInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SimpleBriInboundAuthenticator implements BriInboundAuthenticatorInterface
{
    public function issueToken(string $clientId, string $clientSecret): ?string
    {
        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $expectedClientId = (string) config('services.bri.inbound.client_id');
        $expectedClientSecret = (string) config('services.bri.inbound.client_secret');

        if (!hash_equals($expectedClientId, $clientId) || !hash_equals($expectedClientSecret, $clientSecret)) {
            return null;
        }

        $token = Str::random(40);
        Cache::put('bri_inbound_token:' . $token, true, 900);

        return $token;
    }

    public function validateToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        return Cache::has('bri_inbound_token:' . $token);
    }
}
