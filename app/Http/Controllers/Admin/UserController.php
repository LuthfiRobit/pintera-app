<?php

namespace App\Http\Controllers\Admin;

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $this->authorize('users.view');

        $search = $request->input('search');
        $roleFilter = $request->input('role');
        $scopeGroup = $request->input('scope_group');
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $groups = $this->scopeGroups();

        $query = User::with('roles', 'lembaga', 'yayasan')
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")))
            ->when($roleFilter, fn ($q) => $q->whereHas('roles', fn ($q2) => $q2->where('name', $roleFilter)))
            ->when($scopeGroup && isset($groups[$scopeGroup]), fn ($q) => $q->whereHas('roles', fn ($q2) => $q2->whereIn('name', $groups[$scopeGroup])))
            ->orderBy('name');

        $users = $query->paginate($perPage)->withQueryString();

        $totalUsers = User::count();
        $totalAktif = User::where('is_active', true)->count();
        $totalNonaktif = User::where('is_active', false)->count();

        $scopeCounts = ['semua' => $totalUsers];
        foreach ($groups as $groupName => $roleNames) {
            $scopeCounts[$groupName] = User::whereHas('roles', fn ($q) => $q->whereIn('name', $roleNames))->count();
        }

        $isPlatformViewer = $request->user()->widestScopeLevel() === 'platform';

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.users._daftar', [
                'users' => $users,
                'perPage' => $perPage,
                'isPlatformViewer' => $isPlatformViewer,
            ]);
        }

        $availableRoles = Role::orderBy('name')->get();

        $rolesByGroup = ['semua' => $availableRoles->pluck('name')->values()];
        foreach ($groups as $groupName => $roleNames) {
            $rolesByGroup[$groupName] = $availableRoles->whereIn('name', $roleNames)->pluck('name')->values();
        }

        return view('admin.users.index', [
            'users' => $users,
            'perPage' => $perPage,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'scopeGroup' => $scopeGroup,
            'availableRoles' => $availableRoles,
            'rolesByGroup' => $rolesByGroup,
            'scopeCounts' => $scopeCounts,
            'isPlatformViewer' => $isPlatformViewer,
            'totalUsers' => $totalUsers,
            'totalAktif' => $totalAktif,
            'totalNonaktif' => $totalNonaktif,
        ]);
    }

    /**
     * Peta kategori chip filter ke daftar nama role, sesuai precedence RBAC v2
     * (spec .agents/specs/2026-08-25-rbac-pengguna-scope-filter.md §4).
     */
    private function scopeGroups(): array
    {
        return [
            'platform' => ['platform_super_admin'],
            'yayasan' => ['yayasan_super_admin', 'bendahara_yayasan', 'pegawai_yayasan'],
            'lembaga' => ['kepala_sekolah', 'wakasek_kurikulum', 'wakasek_kesiswaan', 'operator_akademik', 'admin_sdm', 'bendahara_lembaga', 'admin_sarpras', 'admin_administrasi', 'pegawai_lembaga'],
            'staf' => ['guru', 'wali_kelas', 'guru_bk'],
            'orang_tua' => ['orang_tua'],
            'siswa' => ['siswa'],
        ];
    }

    public function create(Request $request): View
    {
        $this->authorize('users.create');

        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        $roles = Role::all()->filter(fn ($role) => $this->scopeRank($role->scope_level) <= $actingRank)->values();

        return view('admin.users.create', [
            'roles' => $roles,
            'lembaga' => $request->user()->widestScopeLevel() === 'yayasan'
                ? Lembaga::withoutGlobalScopes()->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('users.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'lembaga_id' => ['nullable', 'exists:lembaga,id'],
            'role' => ['required', 'exists:roles,name'],
        ]);

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? ($data['lembaga_id'] ?? null)
            : $request->user()->lembaga_id;

        $selectedRole = Role::where('name', $data['role'])->firstOrFail();

        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        if ($this->scopeRank($selectedRole->scope_level) > $actingRank) {
            return back()->withErrors(['role' => 'Anda tidak dapat memberikan role dengan scope lebih luas dari scope Anda sendiri.'])->withInput();
        }

        if ($selectedRole->scope_level !== 'yayasan' && $lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Lembaga wajib diisi untuk role ini.'])->withInput();
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'lembaga_id' => $lembagaId,
            'yayasan_id' => $selectedRole->scope_level === 'yayasan' ? $request->user()->yayasan_id : null,
        ]);

        $user->assignRole($data['role']);

        return redirect()->route('admin.users.index')->with('status', 'Akun staff berhasil dibuat.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('users.edit');
        abort_if($user->hasRole('siswa'), 404);

        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        $roles = Role::all()->filter(fn ($role) => $this->scopeRank($role->scope_level) <= $actingRank)->values();

        return view('admin.users.edit', [
            'targetUser' => $user,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('users.edit');
        abort_if($user->hasRole('siswa'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'role' => ['required', 'exists:roles,name'],
        ]);

        $selectedRole = Role::where('name', $data['role'])->firstOrFail();
        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        if ($this->scopeRank($selectedRole->scope_level) > $actingRank) {
            return back()->withErrors(['role' => 'Anda tidak dapat memberikan role dengan scope lebih luas dari scope Anda sendiri.'])->withInput();
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'yayasan_id' => $selectedRole->scope_level === 'yayasan' ? $request->user()->yayasan_id : null,
        ]);
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('status', 'Akun staff berhasil diperbarui.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('users.toggle-active');
        abort_if($user->hasRole('siswa'), 404);

        $user->update(['is_active' => ! $user->is_active]);

        return redirect()->route('admin.users.index')->with('status', 'Status akun berhasil diperbarui.');
    }

    private function scopeRank(string $level): int
    {
        return match ($level) {
            'platform' => 4,
            'yayasan' => 3,
            'lembaga' => 2,
            default => 1, // diri_sendiri
        };
    }
}
