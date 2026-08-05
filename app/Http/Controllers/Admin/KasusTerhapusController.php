<?php
// app/Http/Controllers/Admin/KasusTerhapusController.php

namespace App\Http\Controllers\Admin;

use App\Models\Kasus;
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

        $kasusList = Kasus::onlyTrashed()
            ->withoutGlobalScope(TenantScope::class)
            ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where('lembaga_id', $user->lembaga_id))
            ->with(['siswa' => fn ($q) => $q->withoutGlobalScopes()])
            ->latest('deleted_at')
            ->paginate(20);

        return view('admin.kasus.terhapus', ['kasusList' => $kasusList]);
    }
}
