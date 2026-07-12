<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class RoleController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.roles.index', ['roles' => Role::withCount('users')->get()]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', ['permissions' => Permission::all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'scope_level' => ['required', 'in:yayasan,lembaga,diri_sendiri'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'scope_level' => $data['scope_level'],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('status', 'Role berhasil dibuat.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role,
            'permissions' => Permission::all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $rules = [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];

        if (! $role->is_protected) {
            $rules['scope_level'] = ['required', 'in:yayasan,lembaga,diri_sendiri'];
        }

        $data = $request->validate($rules);

        $role->name = $data['name'];

        if (! $role->is_protected) {
            $role->scope_level = $data['scope_level'];
        }

        $role->save();
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('status', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Role masih dipakai user aktif, pindahkan dulu sebelum menghapus.']);
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', 'Role berhasil dihapus.');
    }
}
