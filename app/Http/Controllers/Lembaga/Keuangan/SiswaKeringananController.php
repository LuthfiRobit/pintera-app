<?php

// app/Http/Controllers/Lembaga/Keuangan/SiswaKeringananController.php

namespace App\Http\Controllers\Lembaga\Keuangan;

use App\Domains\Keuangan\Models\SiswaKeringanan;
use App\Http\Controllers\Controller;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiswaKeringananController extends Controller
{
    use AuthorizesRequests;

    public function index(Siswa $siswa): View
    {
        $this->authorize('siswa-keringanan.kelola');

        $keringanan = $siswa->siswaKeringanan()->with('kategoriKeringanan')->latest('berlaku_dari')->get();

        return view('admin.siswa.tabs.keringanan', compact('siswa', 'keringanan'));
    }

    public function store(Request $request, Siswa $siswa): RedirectResponse
    {
        $this->authorize('siswa-keringanan.kelola');

        $validated = $request->validate([
            'kategori_keringanan_id' => [
                'required',
                Rule::exists('kategori_keringanan', 'id')->where('lembaga_id', $siswa->lembaga_id),
            ],
            'berlaku_dari' => ['required', 'date'],
            'berlaku_sampai' => ['nullable', 'date', 'after_or_equal:berlaku_dari'],
        ]);

        $siswa->siswaKeringanan()->create($validated);

        return back()->with('success', 'Keringanan berhasil ditambahkan.');
    }

    public function destroy(Request $request, SiswaKeringanan $siswaKeringanan): RedirectResponse
    {
        $this->authorize('siswa-keringanan.kelola');

        // SiswaKeringanan has no BelongsToTenant scope of its own -- {siswaKeringanan}
        // route-model binding is NOT tenant-filtered, unlike {siswa} in store()/index().
        // Without this check, an admin from one lembaga could delete another lembaga's
        // keringanan row by guessing/incrementing the id. Bypass Siswa's own TenantScope
        // here -- otherwise a cross-tenant siswa resolves to null (invisible to the acting
        // user's scope) and crashes on ->lembaga_id instead of cleanly 404ing.
        $siswaLembagaId = Siswa::withoutGlobalScope(TenantScope::class)->find($siswaKeringanan->siswa_id)?->lembaga_id;
        abort_unless($siswaLembagaId === $this->lembagaId($request), 404);

        $siswaKeringanan->delete();

        return back()->with('success', 'Keringanan berhasil dicabut.');
    }

    private function lembagaId(Request $request): ?int
    {
        return $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;
    }
}
