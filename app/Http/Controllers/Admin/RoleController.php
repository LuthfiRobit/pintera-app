<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Services\PermissionCatalog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
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

        return view('admin.roles.index');
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::withCount(['users', 'permissions']);

        if ($search = trim((string) $request->string('search'))) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if ($scope = $request->string('scope')->value()) {
            $query->where('scope_level', $scope);
        }

        $sortable = ['name', 'scope_level', 'users_count', 'permissions_count'];
        $sort = in_array($request->string('sort')->value(), $sortable, true) ? $request->string('sort')->value() : 'name';
        $direction = $request->string('direction')->value() === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'scope_level' => $role->scope_level,
                'is_protected' => $role->is_protected,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
            ])->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function permissionsCatalog(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('roles.create') || $request->user()->can('roles.edit'), 403);

        return response()->json(['modules' => PermissionCatalog::grouped()]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', ['moduleGroups' => PermissionCatalog::grouped()]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'scope_level' => ['required', 'in:yayasan,lembaga,diri_sendiri'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
        if ($this->scopeRank($data['scope_level']) > $actingRank) {
            return $this->errorResponse(
                $request,
                'scope_level',
                'Anda tidak dapat membuat role dengan scope lebih luas dari scope Anda sendiri.'
            );
        }

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'scope_level' => $data['scope_level'],
        ]);

        $role->syncPermissions(Permission::whereIn('id', $data['permissions'] ?? [])->get());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Role berhasil dibuat.'], 201);
        }

        return redirect()->route('admin.roles.index')->with('status', 'Role berhasil dibuat.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role,
            'moduleGroups' => PermissionCatalog::grouped(),
            'checkedIds' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse|JsonResponse
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
            $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
            if ($this->scopeRank($data['scope_level']) > $actingRank) {
                return $this->errorResponse(
                    $request,
                    'scope_level',
                    'Anda tidak dapat mengubah role ke scope lebih luas dari scope Anda sendiri.'
                );
            }
            $role->scope_level = $data['scope_level'];
        }

        $role->save();
        $role->syncPermissions(Permission::whereIn('id', $data['permissions'] ?? [])->get());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Role berhasil diperbarui.']);
        }

        return redirect()->route('admin.roles.index')->with('status', 'Role berhasil diperbarui.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $role);

        if ($role->users()->exists()) {
            return $this->errorResponse(
                $request,
                'role',
                'Role masih dipakai user aktif, pindahkan dulu sebelum menghapus.'
            );
        }

        $role->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Role berhasil dihapus.']);
        }

        return redirect()->route('admin.roles.index')->with('status', 'Role berhasil dihapus.');
    }

    private function errorResponse(Request $request, string $field, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
        }

        return back()->withErrors([$field => $message])->withInput();
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
