<?php

namespace App\Models\Scopes;

use App\Models\Lembaga;
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

        if ($actingUser->widestScopeLevel() === 'platform' && $model instanceof User) {
            return;
        }

        if ($actingUser->widestScopeLevel() === 'yayasan') {
            $activeLembagaId = session('active_lembaga_id');

            if ($activeLembagaId) {
                $builder->where($model->getTable().'.lembaga_id', $activeLembagaId);
            } else {
                // No specific lembaga picked (e.g. the "Semua Lembaga" switcher option): scope
                // down to every lembaga under the actor's OWN yayasan, not left unfiltered.
                // Previously this branch applied no where() at all when $activeLembagaId was
                // empty, leaking rows across every yayasan in the whole system - fixed here.
                // whereIn() with an empty/null-yayasan subquery naturally yields zero rows
                // (fail-closed), matching the same philosophy already used below for a
                // lembaga-scoped actor whose own lembaga_id is null.
                $lembagaMilikYayasan = Lembaga::where('yayasan_id', $actingUser->yayasan_id)->select('id');

                if ($actingUser->yayasan_id && in_array('yayasan_id', $model->getFillable(), true)) {
                    // Some tenant-scoped models (Karyawan/Rpp/AsetBarang/Gedung/KategoriAset/
                    // Ruangan/User) also carry their own yayasan_id column for "pool" records
                    // that belong directly to a yayasan rather than any single lembaga
                    // (lembaga_id null). Those rows are legitimately visible to the actor too.
                    // Guarded by $actingUser->yayasan_id being truthy first: orWhere(column,
                    // null) would otherwise compile to "OR yayasan_id IS NULL", which for an
                    // actor with no yayasan_id of their own would match every other orphaned
                    // pool row system-wide - reopening the exact fail-open hole this fixes.
                    $builder->where(function ($query) use ($model, $lembagaMilikYayasan, $actingUser) {
                        $query->whereIn($model->getTable().'.lembaga_id', $lembagaMilikYayasan)
                            ->orWhere($model->getTable().'.yayasan_id', $actingUser->yayasan_id);
                    });
                } else {
                    $builder->whereIn($model->getTable().'.lembaga_id', $lembagaMilikYayasan);
                }
            }

            return;
        }

        $builder->where($model->getTable().'.lembaga_id', $actingUser->lembaga_id);
    }
}
