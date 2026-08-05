<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class KasusAksesLogController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('kasus.lihat-log-akses');

        $user = auth()->user();

        $logs = Activity::query()
            ->where('log_name', 'akses_klinis')
            ->with(['subject' => fn ($q) => $q->withoutGlobalScopes()->withTrashed()->with(['siswa' => fn ($sq) => $sq->withoutGlobalScopes()])])
            ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->whereHasMorph(
                'subject',
                [\App\Models\Kasus::class],
                fn ($subQuery) => $subQuery->withoutGlobalScopes()->withTrashed()->where('lembaga_id', $user->lembaga_id)
            ))
            ->latest()
            ->paginate(20);

        // The `causer` morphTo relation resolves through App\Models\User, which uses
        // BelongsToTenant. Eager-loading it via ->with('causer') would silently apply
        // TenantScope per morph type, resolving to null for any causer whose lembaga_id
        // differs from the viewing admin (orang_tua and yayasan_super_admin both have a
        // null lembaga_id; a konselor from another lembaga also differs). Resolve causers
        // separately, scope-free, and key them by id for the view to look up.
        $causers = User::withoutGlobalScopes()
            ->whereIn('id', $logs->getCollection()->pluck('causer_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return view('admin.kasus.akses-log', ['logs' => $logs, 'causers' => $causers]);
    }
}
