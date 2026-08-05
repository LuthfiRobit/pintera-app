<?php

namespace App\Http\Controllers\Admin;

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
            ->with(['causer', 'subject' => fn ($q) => $q->withTrashed()->with('siswa')])
            ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->whereHasMorph(
                'subject',
                [\App\Models\Kasus::class],
                fn ($subQuery) => $subQuery->withoutGlobalScopes()->withTrashed()->where('lembaga_id', $user->lembaga_id)
            ))
            ->latest()
            ->paginate(20);

        return view('admin.kasus.akses-log', ['logs' => $logs]);
    }
}
