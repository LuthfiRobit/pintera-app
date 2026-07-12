<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $userId = auth()->id();

        if (! $userId) {
            return;
        }

        // Deliberately re-queries User with this scope removed, instead of calling
        // auth()->user(). User itself uses BelongsToTenant (see step 7 below), and
        // auth()->user() triggers a DB lookup the first time it's called in a request
        // (session-based re-authentication) — if that lookup re-entered this same
        // apply() method via auth()->user(), it would recurse forever. auth()->id()
        // is safe here because Laravel's SessionGuard reads the id straight out of
        // the session without querying the database.
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
