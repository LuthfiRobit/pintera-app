<?php

namespace App\Domains\Shared\Context;

use App\Domains\Shared\Context\Contracts\TenantContextInterface;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class TenantContext implements TenantContextInterface
{
    public function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function isYayasanScope(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->widestScopeLevel() === 'yayasan';
    }

    public function activeLembagaId(): ?int
    {
        $user = $this->user();

        if (! $user) {
            return null;
        }

        if ($this->isYayasanScope()) {
            return session('active_lembaga_id');
        }

        return $user->lembaga_id;
    }

    public function activeYayasanId(): ?int
    {
        $user = $this->user();

        if (! $user) {
            return null;
        }

        if ($user->lembaga && $user->lembaga->yayasan_id) {
            return $user->lembaga->yayasan_id;
        }

        $activeLembagaId = $this->activeLembagaId();
        if ($activeLembagaId) {
            $lembaga = \App\Models\Lembaga::find($activeLembagaId);

            return $lembaga?->yayasan_id;
        }

        return null;
    }
}
