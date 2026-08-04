<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    private static bool $resolvingActingUser = false;

    public function apply(Builder $builder, Model $model): void
    {
        // Re-entrancy guard: Laravel's SessionGuard::id() calls $this->user() internally
        // (it is NOT a plain session read), so the very first cold resolution of the
        // authenticated user triggers User::retrieveById(), which builds a User query and
        // re-applies this same scope, which calls auth()->id() again, recursing forever
        // until memory is exhausted. This flag breaks the cycle: the one re-entrant call
        // (which only ever fetches the acting user's own row by primary key) skips
        // filtering and lets the outer call's auth()->id() resolve normally once it
        // returns. It doesn't weaken isolation — the re-entrant query is already
        // constrained to a single id by retrieveById() itself.
        if (self::$resolvingActingUser) {
            return;
        }

        self::$resolvingActingUser = true;

        try {
            $userId = auth()->id();
        } finally {
            self::$resolvingActingUser = false;
        }

        if (! $userId) {
            return;
        }

        $actingUser = User::withoutGlobalScope(self::class)->find($userId);

        if (! $actingUser) {
            return;
        }

        if ($actingUser->widestScopeLevel() === 'yayasan') {
            $activeLembagaId = session('active_lembaga_id');

            if ($activeLembagaId) {
                $builder->where($model->getTable().'.lembaga_id', $activeLembagaId);
            }

            return;
        }

        $builder->where($model->getTable().'.lembaga_id', $actingUser->lembaga_id);
    }
}
