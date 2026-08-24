<?php

namespace App\Domains\Keuangan\Contracts;

interface BriInboundAuthenticatorInterface
{
    /**
     * Terbitkan token sementara kalau client_id/client_secret cocok. Null kalau salah.
     */
    public function issueToken(string $clientId, string $clientSecret): ?string;

    /**
     * Cek apakah token masih berlaku (pernah kita terbitkan & belum kadaluarsa).
     */
    public function validateToken(string $token): bool;
}