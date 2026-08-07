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
        $search = request('search');
        $perPage = request('per_page', 20);

        // Query dasar
        $baseQuery = Activity::query()
            ->where('log_name', 'akses_klinis')
            ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->whereHasMorph(
                'subject',
                [\App\Models\Kasus::class],
                fn ($subQuery) => $subQuery->withoutGlobalScopes()->withTrashed()->where('lembaga_id', $user->lembaga_id)
            ));

        // Statistik
        $totalAkses = (clone $baseQuery)->count();
        $aksesHariIni = (clone $baseQuery)->whereDate('created_at', today())->count();

        // Pencarian (Siswa atau Pengakses)
        // Karena subject dan causer adalah polymorphic, kita apply filter secara manual.
        if (!empty($search)) {
            $baseQuery->where(function ($q) use ($search) {
                // Pencarian berdasarkan nama siswa di Kasus
                $q->whereHasMorph('subject', [\App\Models\Kasus::class], function ($subQuery) use ($search) {
                    $subQuery->withoutGlobalScopes()->withTrashed()->whereHas('siswa', function ($siswaQuery) use ($search) {
                        $siswaQuery->withoutGlobalScopes()->where('nama_lengkap', 'like', '%' . $search . '%');
                    });
                })
                // Pencarian berdasarkan nama causer (User)
                ->orWhereIn('causer_id', User::withoutGlobalScopes()->where('name', 'like', '%' . $search . '%')->pluck('id'));
            });
        }

        $logs = $baseQuery->with(['subject' => fn ($q) => $q->withoutGlobalScopes()->withTrashed()->with(['siswa' => fn ($sq) => $sq->withoutGlobalScopes()])])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        // Eager-loading causers tanpa TenantScope (tetap seperti semula)
        $causers = User::withoutGlobalScopes()
            ->whereIn('id', $logs->getCollection()->pluck('causer_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return view('admin.kasus.akses-log', [
            'logs' => $logs, 
            'causers' => $causers,
            'totalAkses' => $totalAkses,
            'aksesHariIni' => $aksesHariIni,
            'search' => $search,
            'perPage' => $perPage
        ]);
    }
}
