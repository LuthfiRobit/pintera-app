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

    public function index(Request $request): View
    {
        $this->authorize('users.view');

        $users = User::with('roles', 'lembaga')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users]);
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
        ]);

        $user->assignRole($data['role']);

        return redirect()->route('admin.users.index')->with('status', 'Akun staff berhasil dibuat.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('users.edit');

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

        $user->update(['name' => $data['name'], 'email' => $data['email']]);
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('status', 'Akun staff berhasil diperbarui.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('users.toggle-active');

        $user->update(['is_active' => ! $user->is_active]);

        return redirect()->route('admin.users.index')->with('status', 'Status akun berhasil diperbarui.');
    }

    private function scopeRank(string $level): int
    {
        return match ($level) {
            'yayasan' => 3,
            'lembaga' => 2,
            default => 1, // diri_sendiri
        };
    }
}
