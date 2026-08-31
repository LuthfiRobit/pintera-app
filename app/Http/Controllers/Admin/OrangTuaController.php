<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Identity\Actions\UpdatePersonAction;
use App\Models\OrangTua;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\AkunOrangTuaGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrangTuaController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('orang-tua.view');

        $user = auth()->user();
        $search = $request->query('search');

        // OrangTua accounts always have lembaga_id = null by design, so eager-loading `user`
        // must bypass TenantScope or a lembaga-scoped viewer's own scope silently filters it
        // to null (Eloquent turns `where('lembaga_id', null)` into `whereNull`, which happens
        // to only pass when the viewer ALSO has a null lembaga_id — masking this for any
        // fixture/manager that never set one).
        $orangTuaList = OrangTua::with(['user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->withCount('siswa')
            ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where(fn ($q2) => $q2
                ->whereDoesntHave('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class))
                ->orWhereHas('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class)->where('lembaga_id', $user->lembaga_id))))
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('nama_lengkap', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")))
            ->orderBy('nama_lengkap')
            ->get();

        return view('admin.orang-tua.index', [
            'orangTuaList' => $orangTuaList,
            'search' => $search,
            'totalOrangTua' => $orangTuaList->count(),
            'totalAktif' => $orangTuaList->filter(fn ($o) => $o->user && $o->user->is_active)->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('orang-tua.create');

        return view('admin.orang-tua.create');
    }

    public function store(Request $request, AkunOrangTuaGenerator $generator): RedirectResponse
    {
        $this->authorize('orang-tua.create');

        $data = $this->validateProfil($request);

        $existingUser = User::withoutGlobalScopes()->where('username', $data['nik'])->first();
        if ($existingUser) {
            $existingOrangTua = OrangTua::withoutGlobalScopes()->where('user_id', $existingUser->id)->first();

            if ($existingOrangTua === null) {
                return back()
                    ->withErrors(['nik' => 'NIK ini sudah terdaftar ke akun lain yang bukan profil Orang Tua.'])
                    ->withInput();
            }

            return redirect()
                ->route('admin.orang-tua.edit', $existingOrangTua)
                ->with('status', 'NIK sudah terdaftar — berikut profil Orang Tua yang sudah ada.');
        }

        $yayasanId = $this->resolveYayasanIdForCreate($request);

        $generator->buat(
            $data['nama_lengkap'],
            $data['nik'],
            $data['no_hp'],
            $data['email'] ?? null,
            $data['alamat'] ?? null,
            $data['pekerjaan'] ?? null,
            $yayasanId,
        );

        return redirect()->route('admin.orang-tua.index')->with('status', 'Data Orang Tua & akun berhasil dibuat.');
    }

    public function edit(OrangTua $orangTua): View
    {
        $this->authorize('orang-tua.edit');
        $this->authorizeLembaga($orangTua);

        $orangTua->load([
            'user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'person',
            'siswa' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
        ]);

        return view('admin.orang-tua.edit', ['orangTua' => $orangTua]);
    }

    public function update(Request $request, OrangTua $orangTua): RedirectResponse
    {
        $this->authorize('orang-tua.edit');
        $this->authorizeLembaga($orangTua);

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'no_hp' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'pekerjaan' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $orangTua) {
            if ($orangTua->person) {
                app(UpdatePersonAction::class)->execute($orangTua->person, [
                    'nama_lengkap' => $data['nama_lengkap'],
                    'no_hp' => $data['no_hp'],
                    'email' => $data['email'] ?? $orangTua->person->email,
                    'alamat_jalan' => $data['alamat'] ?? $orangTua->person->alamat_jalan,
                ]);

                $user = $orangTua->person->user ?? ($orangTua->person->user_id ? User::withoutGlobalScopes()->find($orangTua->person->user_id) : null);
                if ($user !== null) {
                    $user->update(['name' => $data['nama_lengkap']]);
                }
            } elseif ($orangTua->user_id !== null) {
                User::withoutGlobalScopes()->where('id', $orangTua->user_id)->update(['name' => $data['nama_lengkap']]);
            }

            $orangTua->update(collect($data)->only(['pekerjaan'])->toArray());
        });

        return redirect()->route('admin.orang-tua.index')->with('status', 'Data Orang Tua berhasil diperbarui.');
    }

    public function updateStatus(Request $request, OrangTua $orangTua): RedirectResponse
    {
        $this->authorize('orang-tua.edit');
        $this->authorizeLembaga($orangTua);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $orangTua->user()->withoutGlobalScope(TenantScope::class)->update(['is_active' => $data['is_active']]);

        return redirect()->route('admin.orang-tua.index')->with('status', 'Status akun Orang Tua berhasil diperbarui.');
    }

    private function resolveYayasanIdForCreate(Request $request): int
    {
        $user = $request->user();

        if ($user->widestScopeLevel() === 'yayasan') {
            return $user->yayasan_id;
        }

        return $user->lembaga->yayasan_id;
    }

    // A lembaga-scoped admin (operator_akademik) may act on any orang tua profile that either has
    // no linked siswa yet (so the just-created-but-not-yet-tautkan flow isn't blocked) or has
    // at least one siswa in their own lembaga. yayasan_super_admin is unrestricted. OrangTua
    // has no lembaga_id of its own by design (a parent can have children in multiple lembaga),
    // so this checks the siswa pivot instead of a direct column.
    private function authorizeLembaga(OrangTua $orangTua): void
    {
        $user = auth()->user();

        if ($user->widestScopeLevel() === 'yayasan') {
            return;
        }

        $hasSiswa = $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->exists();
        $hasSiswaDiLembaga = $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->where('lembaga_id', $user->lembaga_id)->exists();

        abort_if($hasSiswa && ! $hasSiswaDiLembaga, 404);
    }

    private function validateProfil(Request $request): array
    {
        return $request->validate([
            'nik' => ['required', 'digits:16'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'no_hp' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'pekerjaan' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
