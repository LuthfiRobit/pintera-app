<?php
// app/Http/Controllers/Admin/KasusTerhapusController.php

namespace App\Http\Controllers\Admin;

use App\Domains\Kasus\Models\Kasus;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KasusTerhapusController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('kasus.lihat-log-akses');

        $user = auth()->user();
        $search = request('search');
        $perPage = in_array((int) request('per_page'), [10, 20, 25, 50]) ? (int) request('per_page') : 20;

        // Query Dasar
        $baseQuery = Kasus::onlyTrashed()
            ->withoutGlobalScope(TenantScope::class)
            ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where('lembaga_id', $user->lembaga_id));

        // Statistik
        $totalTerhapus = (clone $baseQuery)->count();
        $dihapusBulanIni = (clone $baseQuery)->whereYear('deleted_at', now()->year)->whereMonth('deleted_at', now()->month)->count();

        // Pencarian
        if (!empty($search)) {
            $baseQuery->where(function ($q) use ($search) {
                $q->whereHas('siswa', function ($siswaQuery) use ($search) {
                    $siswaQuery->withoutGlobalScopes()->where('nama_lengkap', 'like', '%' . $search . '%');
                })->orWhere('kategori_masalah', 'like', '%' . $search . '%');
            });
        }

        $kasusList = $baseQuery->with(['siswa' => fn ($q) => $q->withoutGlobalScopes()])
            ->latest('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.kasus.terhapus', [
            'kasusList' => $kasusList,
            'totalTerhapus' => $totalTerhapus,
            'dihapusBulanIni' => $dihapusBulanIni,
            'search' => $search,
            'perPage' => $perPage
        ]);
    }
}
