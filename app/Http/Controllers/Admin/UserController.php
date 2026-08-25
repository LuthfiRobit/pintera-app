<?php

namespace App\Http\Controllers\Admin;

use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

        $actor = $request->user();
        $isPlatformViewer = $actor->widestScopeLevel() === 'platform';
        $groups = $this->scopeGroups();

        $query = $this->visibleUsersQuery($actor, $isPlatformViewer)
            ->with('roles', 'lembaga.yayasan', 'yayasan')
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")))
            ->when($roleFilter, fn ($q) => $q->whereHas('roles', fn ($q2) => $q2->where('name', $roleFilter)))
            ->when($scopeGroup && array_key_exists($scopeGroup, $groups), fn ($q) => $this->applyScopeGroup($q, $scopeGroup))
            ->orderBy('name');

        $users = $query->paginate($perPage)->withQueryString();

        $totalUsers = $this->visibleUsersQuery($actor, $isPlatformViewer)->count();
        $totalAktif = $this->visibleUsersQuery($actor, $isPlatformViewer)->where('is_active', true)->count();
        $totalNonaktif = $this->visibleUsersQuery($actor, $isPlatformViewer)->where('is_active', false)->count();

        $scopeCounts = ['semua' => $totalUsers];
        foreach (array_keys($groups) as $groupName) {
            $scopeCounts[$groupName] = $this->applyScopeGroup($this->visibleUsersQuery($actor, $isPlatformViewer), $groupName)->count();
        }

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
     * Peta kategori chip filter ke daftar nama role -- dipakai untuk mengisi opsi
     * select Role per grup (Filter Role dinamis). BUKAN dipakai langsung sebagai
     * filter keanggotaan chip karena pegawai_lembaga/pegawai_yayasan sengaja
     * co-exist dengan role fungsional (invariant scope-carrier RBAC v2) --
     * keanggotaan chip yang presisi ada di applyScopeGroup().
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

    /**
     * Filter keanggotaan chip scope, precedence-aware (spec §4): pegawai_lembaga
     * dan pegawai_yayasan adalah role scope-carrier yang SENGAJA co-exist dengan
     * role fungsional (mis. guru selalu juga punya pegawai_lembaga). Kalau grup
     * "lembaga"/"yayasan" memakai whereIn polos atas nama role dalam daftar
     * scopeGroups(), guru akan ikut tercocokkan lewat pegawai_lembaga-nya dan
     * muncul ganda di chip Lembaga DAN Staf. Method ini mengecualikan user yang
     * punya role dari grup berprioritas lebih rendah (staf, untuk lembaga; tidak
     * ada pengecualian tambahan untuk yayasan karena platform_super_admin tidak
     * pernah diberi pegawai_yayasan oleh invariant manapun saat ini).
     */
    private function applyScopeGroup(Builder $query, string $scopeGroup): Builder
    {
        return match ($scopeGroup) {
            'platform' => $query->whereHas('roles', fn ($q) => $q->where('name', 'platform_super_admin')),
            'yayasan' => $query->where(fn ($q) => $q
                ->whereHas('roles', fn ($q2) => $q2->whereIn('name', ['yayasan_super_admin', 'bendahara_yayasan']))
                ->orWhere(fn ($q2) => $q2
                    ->whereHas('roles', fn ($q3) => $q3->where('name', 'pegawai_yayasan'))
                    ->whereDoesntHave('roles', fn ($q3) => $q3->where('name', 'platform_super_admin')))),
            'lembaga' => $query->where(fn ($q) => $q
                ->whereHas('roles', fn ($q2) => $q2->whereIn('name', ['kepala_sekolah', 'wakasek_kurikulum', 'wakasek_kesiswaan', 'operator_akademik', 'admin_sdm', 'bendahara_lembaga', 'admin_sarpras', 'admin_administrasi']))
                ->orWhere(fn ($q2) => $q2
                    ->whereHas('roles', fn ($q3) => $q3->where('name', 'pegawai_lembaga'))
                    ->whereDoesntHave('roles', fn ($q3) => $q3->whereIn('name', ['guru', 'wali_kelas', 'guru_bk'])))),
            'staf' => $query->whereHas('roles', fn ($q) => $q->whereIn('name', ['guru', 'wali_kelas', 'guru_bk'])),
            'orang_tua' => $query->whereHas('roles', fn ($q) => $q->where('name', 'orang_tua')),
            'siswa' => $query->whereHas('roles', fn ($q) => $q->where('name', 'siswa')),
            default => $query,
        };
    }

    /**
     * Query User:: yang visible bagi $actor, meniru logic TenantScope TAPI dengan
     * satu tambahan: akun orang_tua SENGAJA punya lembaga_id=null DAN yayasan_id=null
     * (lihat OrangTuaController::index(), pola yang sama persis sudah established
     * di sana) -- visibilitasnya baru bisa diresolve lewat relasi
     * orangTua->siswa->lembaga_id, bukan kolom lembaga_id/yayasan_id milik User itu
     * sendiri. TenantScope generik tidak bisa mengekspresikan itu, jadi di-bypass
     * total di sini dan diganti kondisi manual yang menambahkan orang_tua sebagai
     * OR tambahan pada tiap cabang.
     */
    private function visibleUsersQuery(User $actor, bool $isPlatformViewer): Builder
    {
        $query = User::withoutGlobalScope(TenantScope::class);

        if ($isPlatformViewer) {
            return $query;
        }

        if ($actor->widestScopeLevel() === 'yayasan') {
            $activeLembagaId = session('active_lembaga_id');

            if ($activeLembagaId) {
                return $query->where(fn ($q) => $q
                    ->where('users.lembaga_id', $activeLembagaId)
                    ->orWhereHas('orangTua.siswa', fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)->where('lembaga_id', $activeLembagaId)));
            }

            $lembagaMilikYayasan = Lembaga::where('yayasan_id', $actor->yayasan_id)->pluck('id');

            return $query->where(fn ($q) => $q
                ->whereIn('users.lembaga_id', $lembagaMilikYayasan)
                ->orWhere('users.yayasan_id', $actor->yayasan_id)
                ->orWhereHas('orangTua.siswa', fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)->whereIn('lembaga_id', $lembagaMilikYayasan)));
        }

        return $query->where(fn ($q) => $q
            ->where('users.lembaga_id', $actor->lembaga_id)
            ->orWhereHas('orangTua.siswa', fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)->where('lembaga_id', $actor->lembaga_id)));
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

    /**
     * Daftar role yang boleh ditampilkan/dipilih di form Pengguna (create/edit).
     * Mengecualikan siswa/orang_tua (punya modul pembuatan akun tersendiri) dan
     * pegawai_lembaga/pegawai_yayasan (scope-carrier, auto-assign, tidak pernah
     * dipilih manual) -- lihat baselineCarrierRole(). Filter rank tetap berlaku
     * per-role individual karena role dalam satu grup checkbox (lihat
     * formRoleGroups()) bisa punya scope_level berbeda (mis. guru = diri_sendiri).
     */
    private function assignableRoles(int $actingRank): \Illuminate\Support\Collection
    {
        $excluded = ['siswa', 'orang_tua', 'pegawai_lembaga', 'pegawai_yayasan'];

        return Role::all()
            ->reject(fn ($role) => in_array($role->name, $excluded, true))
            ->filter(fn ($role) => $this->scopeRank($role->scope_level) <= $actingRank)
            ->values();
    }

    /**
     * Taksonomi grup checkbox role di form Pengguna -- sama persis dengan
     * taksonomi chip di halaman list (spec 2026-08-25-rbac-pengguna-scope-filter
     * §4), MINUS carrier role dan MINUS siswa/orang_tua (tidak pernah muncul di
     * form ini).
     */
    private function formRoleGroups(): array
    {
        return [
            'Platform' => ['platform_super_admin'],
            'Yayasan' => ['yayasan_super_admin', 'bendahara_yayasan'],
            'Lembaga' => ['kepala_sekolah', 'wakasek_kurikulum', 'wakasek_kesiswaan', 'operator_akademik', 'admin_sdm', 'bendahara_lembaga', 'admin_sarpras', 'admin_administrasi'],
            'Staf' => ['guru', 'wali_kelas', 'guru_bk'],
        ];
    }

    /**
     * Kelompokkan $roles (hasil assignableRoles()) ke label grup formRoleGroups(),
     * membuang grup yang kosong (mis. grup Platform disembunyikan sama sekali
     * untuk actor lembaga-scope karena role platform_super_admin sudah difilter
     * habis duluan oleh scopeRank di assignableRoles()).
     */
    private function groupRolesForForm(\Illuminate\Support\Collection $roles): \Illuminate\Support\Collection
    {
        return collect($this->formRoleGroups())
            ->map(fn ($names) => $roles->whereIn('name', $names)->values())
            ->filter(fn ($group) => $group->isNotEmpty());
    }

    /**
     * Role scope-carrier yang WAJIB otomatis ditambahkan berdampingan dengan role
     * fungsional yang dipilih -- meniru invariant AkunKaryawanGenerator (spec RBAC
     * v2 §5.5, §7). Berbasis scope_level (bukan hardcode nama role) supaya
     * otomatis berlaku untuk role fungsional baru di masa depan. pegawai_yayasan
     * TIDAK PERNAH dikembalikan di sini -- form ini untuk staf ber-lembaga_id,
     * bukan alur pool karyawan yayasan.
     */
    private function baselineCarrierRole(\Illuminate\Support\Collection $selectedRoles, ?int $lembagaId): ?string
    {
        if ($lembagaId === null) {
            return null;
        }

        $needsCarrier = $selectedRoles->contains(fn ($role) => in_array($role->scope_level, ['lembaga', 'diri_sendiri'], true));

        return $needsCarrier ? 'pegawai_lembaga' : null;
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
