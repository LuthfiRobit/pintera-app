<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Services\PermissionAuditService;
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

    public function __construct(private PermissionAuditService $permissionAudit)
    {
    }

    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::withCount(['users', 'permissions'])
            ->with(['permissions' => fn ($q) => $q->orderBy('name')->limit(5)]);

        if ($search = trim((string) $request->string('search'))) {
            $query->where('name', 'like', '%'.$search.'%');
        }
        if ($scope = $request->string('scope')->value()) {
            $query->where('scope_level', $scope);
        }

        $query->orderBy('name', 'asc');
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $roles = $query->paginate($perPage)->withQueryString();

        $totalRoles = Role::count();
        $totalPlatform = Role::where('scope_level', 'platform')->count();
        $totalYayasan = Role::where('scope_level', 'yayasan')->count();
        $totalLembaga = Role::where('scope_level', 'lembaga')->count();
        $totalDiriSendiri = Role::where('scope_level', 'diri_sendiri')->count();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.roles._daftar', [
                'roles' => $roles,
                'perPage' => $perPage,
            ]);
        }

        return view('admin.roles.index', [
            'roles' => $roles,
            'perPage' => $perPage,
            'search' => $search,
            'scope' => $scope,
            'totalRoles' => $totalRoles,
            'totalPlatform' => $totalPlatform,
            'totalYayasan' => $totalYayasan,
            'totalLembaga' => $totalLembaga,
            'totalDiriSendiri' => $totalDiriSendiri,
        ]);
    }

    public function permissionsCatalog(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('roles.create') || $request->user()->can('roles.edit'), 403);

        $audit = $this->permissionAudit->audit();

        return response()->json([
            'modules' => PermissionCatalog::grouped(),
            'audit' => $audit,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', [
            'moduleGroups' => PermissionCatalog::grouped(),
            'isPlatformActor' => $request->user()->widestScopeLevel() === 'platform',
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'scope_level' => ['required', 'in:yayasan,lembaga,diri_sendiri,platform'],
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

    public function edit(Request $request, Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role,
            'moduleGroups' => PermissionCatalog::grouped(),
            'checkedIds' => $role->permissions->pluck('id')->all(),
            'isPlatformActor' => $request->user()->widestScopeLevel() === 'platform',
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $role);

        $rules = [
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];

        if (! $role->is_protected) {
            $rules['name'] = ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id];
            $rules['scope_level'] = ['required', 'in:yayasan,lembaga,diri_sendiri,platform'];
        }

        $data = $request->validate($rules);

        if (! $role->is_protected) {
            $role->name = $data['name'];

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
            'platform' => 4,
            'yayasan' => 3,
            'lembaga' => 2,
            default => 1, // diri_sendiri
        };
    }
}
