<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class YayasanScope implements Scope
{
    private static bool $resolvingActingUser = false;

    public function apply(Builder $builder, Model $model): void
    {
        // Defensive no-op copied from TenantScope's re-entrancy guard for
        // consistency. Unlike TenantScope (attached to User itself, where
        // resolving auth()->id() can recurse back into a User query and
        // re-trigger that same scope), YayasanScope is only ever attached to
        // Person, so resolving auth()->id() below never triggers a Person
        // query and there is no actual recursion path to guard against here.
        // Kept anyway because it's cheap and keeps the two scopes symmetric.
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

        $actingUser = User::withoutGlobalScope(TenantScope::class)->find($userId);

        if (! $actingUser) {
            return;
        }

        if ($actingUser->widestScopeLevel() === 'platform') {
            return;
        }

        $yayasanId = $actingUser->yayasan_id
            // guru/karyawan/staff-type accounts: yayasan_id or lembaga_id set
            // directly on the users row at account creation.
            ?? $actingUser->lembaga?->yayasan_id
            // siswa-role personal accounts: no yayasan_id/lembaga_id on users
            // at all, only resolvable via their own Siswa profile. User::siswa()
            // is a hasOneThrough(..., Person::class, ...) that already bypasses
            // Siswa's own TenantScope; Siswa carries no YayasanScope (only
            // Person does), so this cannot recurse back into this scope.
            ?? $actingUser->siswa?->lembaga?->yayasan_id
            // orang_tua-role personal accounts: same gap, resolvable only via
            // whichever Siswa they're linked to. User::orangTua() likewise
            // bypasses TenantScope, and OrangTua::siswa() already declares
            // ->withoutGlobalScopes() on its own pivot query, so both hops are
            // scope-safe. A parent with multiple children takes the first one
            // deterministically -- this app's design keeps a Person's
            // yayasan_id fixed, so any linked child's lembaga should sit under
            // the same yayasan.
            ?? $actingUser->orangTua?->siswa()->first()?->lembaga?->yayasan_id;

        if (! $yayasanId) {
            // Fail closed: an actor with no resolvable yayasan boundary sees nothing,
            // matching TenantScope's own fail-closed philosophy elsewhere.
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable().'.yayasan_id', $yayasanId);
    }
}
