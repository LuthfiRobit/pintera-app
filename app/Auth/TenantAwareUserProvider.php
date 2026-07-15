<?php

namespace App\Auth;

use App\Models\Scopes\TenantScope;
use Illuminate\Auth\EloquentUserProvider;

class TenantAwareUserProvider extends EloquentUserProvider
{
    /**
     * Identity resolution (login lookup, session re-hydration, remember-me) must never be
     * filtered by the acting user's own tenant scope — a yayasan-scoped user's lembaga_id is
     * always null, so once they select an active lembaga in the switcher, TenantScope's
     * where('lembaga_id', $activeLembagaId) would never match their own row again, silently
     * logging them out on every subsequent request and rejecting correct login credentials.
     */
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope(TenantScope::class);
    }
}
