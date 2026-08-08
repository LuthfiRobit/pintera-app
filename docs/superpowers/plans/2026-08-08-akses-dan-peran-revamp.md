# Akses dan Peran Revamp Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyeragamkan UI/UX dan arsitektur modul Role Builder dan Manajemen Akun Staff dengan standar Gold Standard (AJAX Partial HTML dengan KPI Cards).

**Architecture:** Modul Role dan User akan dikonversi menggunakan arsitektur `dataTableFilter`. Role Builder akan menghapus rute data JSON-nya dan beralih ke Server-Side Rendering (Blade Partial), sedangkan User Management akan ditingkatkan dari SSR statis menjadi AJAX Partial HTML. Keduanya menggunakan skema warna Tailwind abu-abu (`gray-900`) dan biru (`brand-500`) dengan metrik KPI.

**Tech Stack:** Laravel, Blade, Tailwind CSS, Alpine.js (dataTableFilter).

## Global Constraints

- Warna dan utilitas kelas harus selaras dengan Gold Standard (hindari `text-ink`, `bg-paper`, `text-brass`).
- Gunakan Alpine `x-data="dataTableFilter"` pada elemen container pembungkus.
- Implementasi _Dropdown_ "Tampilkan: 10 / hal" harus ada dan berfungsi via `@change="muatUlangDaftar()"`.
- Setiap perubahan divalidasi dan di-commit per task.

---

### Task 1: Rombak Controller Akses dan Peran

**Files:**
- Modify: `app/Http/Controllers/Admin/RoleController.php`
- Modify: `routes/admin.php`
- Modify: `app/Http/Controllers/Admin/UserController.php`

**Interfaces:**
- Produces: Data tabel paginasi dan variabel `search`, `scope` (Role) / `search` (User) ke _view_. AJAX mendeteksi `$request->wantsJson()` lalu me-return partial `_daftar.blade.php`.

- [ ] **Step 1: Hapus endpoint JSON di RoleController**
Ubah `RoleController.php`: hapus `public function data(...)` seluruhnya. Pindahkan logika pengambilan data ke dalam `index()`.
```php
    public function index(Request $request): \Illuminate\View\View|\Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::withCount(['users', 'permissions']);

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
        $totalYayasan = Role::where('scope_level', 'yayasan')->count();
        $totalLembaga = Role::where('scope_level', 'lembaga')->count();

        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('admin.roles._daftar', ['roles' => $roles, 'perPage' => $perPage])->render(),
            ]);
        }

        return view('admin.roles.index', [
            'roles' => $roles,
            'perPage' => $perPage,
            'search' => $search,
            'scope' => $scope,
            'totalRoles' => $totalRoles,
            'totalYayasan' => $totalYayasan,
            'totalLembaga' => $totalLembaga,
        ]);
    }
```

- [ ] **Step 2: Hapus route JSON Role**
Di `routes/admin.php`, hapus baris `Route::get('roles/data', [RoleController::class, 'data'])->name('roles.data');`.

- [ ] **Step 3: Tambah logika AJAX ke UserController**
Ubah `UserController.php@index`:
```php
    public function index(Request $request): \Illuminate\View\View|\Illuminate\Http\JsonResponse
    {
        $this->authorize('users.view');
        
        $search = $request->input('search');
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = User::with('roles', 'lembaga')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('name');

        $users = $query->paginate($perPage)->withQueryString();
        
        $totalUsers = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))->count();
        $totalAktif = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))->where('is_active', true)->count();
        $totalNonaktif = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))->where('is_active', false)->count();

        if ($request->wantsJson()) {
            return response()->json([
                'html' => view('admin.users._daftar', ['users' => $users, 'perPage' => $perPage])->render(),
            ]);
        }

        return view('admin.users.index', [
            'users' => $users,
            'perPage' => $perPage,
            'search' => $search,
            'totalUsers' => $totalUsers,
            'totalAktif' => $totalAktif,
            'totalNonaktif' => $totalNonaktif,
        ]);
    }
```

- [ ] **Step 4: Commit perubahan arsitektur backend**
```bash
git add app/Http/Controllers/Admin/RoleController.php routes/admin.php app/Http/Controllers/Admin/UserController.php
git commit -m "feat(admin): siapkan endpoint AJAX HTML partial untuk roles dan users"
```

---

### Task 2: Buat View Partial untuk Daftar Akun Staff (User)

**Files:**
- Create: `resources/views/admin/users/_daftar.blade.php`

**Interfaces:**
- Consumes: `$users`, `$perPage` (dari `UserController@index`).

- [ ] **Step 1: Buat file `_daftar.blade.php` untuk Akun Staff**
Buat struktur header form pagination dan tabel yang identik dengan Gold Standard.
```html
<div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-2.5">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Akun Staff</p>
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ $users->total() }} Data</span>
    </div>

    <div class="flex items-center gap-2">
        <label for="per_page" class="text-xs font-medium text-gray-500">Tampilkan:</label>
        <select id="per_page" x-model="perPage" @change="muatUlangDaftar()" class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
            <option value="10">10 / hal</option>
            <option value="20">20 / hal</option>
            <option value="25">25 / hal</option>
            <option value="50">50 / hal</option>
        </select>
    </div>
</div>

<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                <th class="sticky left-0 z-10 bg-white px-5 py-3 w-32">Aksi</th>
                <th class="px-5 py-3">Nama</th>
                <th class="px-5 py-3">Email</th>
                <th class="px-5 py-3">Role</th>
                <th class="px-5 py-3">Lembaga</th>
                <th class="px-5 py-3">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($users as $user)
                <tr class="transition hover:bg-gray-50/50">
                    <td class="sticky left-0 z-10 bg-white px-5 py-3 align-top">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('admin.users.edit', $user) }}" class="font-medium text-gray-700 hover:text-brand-600">Edit</a>
                            <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="font-medium text-brand-600 hover:text-brand-800">
                                    {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                        </div>
                    </td>
                    <td class="px-5 py-3 align-top font-medium text-gray-900">{{ $user->name }}</td>
                    <td class="px-5 py-3 align-top text-gray-500">{{ $user->email }}</td>
                    <td class="px-5 py-3 align-top text-gray-500">{{ $user->roles->pluck('name')->implode(', ') }}</td>
                    <td class="px-5 py-3 align-top text-gray-500">{{ $user->lembaga?->nama ?? '—' }}</td>
                    <td class="px-5 py-3 align-top">
                        @if ($user->is_active)
                            <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-semibold text-green-700 ring-1 ring-inset ring-green-600/20">Aktif</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-1 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-500/10">Nonaktif</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-8 text-center text-gray-500">Tidak ada akun yang ditemukan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($users->hasPages())
    <div class="border-t border-gray-100 px-5 py-4">
        {{ $users->links('pagination.tailwind-ajax') }}
    </div>
@endif
```

- [ ] **Step 2: Commit perubahan view partial Users**
```bash
git add resources/views/admin/users/_daftar.blade.php
git commit -m "feat(ui): tambahkan partial view tabel akun staff standar"
```

---

### Task 3: Rombak Index View Akun Staff (User)

**Files:**
- Modify: `resources/views/admin/users/index.blade.php`

**Interfaces:**
- Membungkus tabel dengan `x-data="dataTableFilter(...)"`.
- Menambahkan 3 KPI Cards.
- Menambahkan Filter Form (Pencarian).

- [ ] **Step 1: Replace isi `resources/views/admin/users/index.blade.php`**
Implementasikan desain standar Master Data.
```html
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="dataTableFilter({
        filters: {
            search: @js($search),
        },
        perPage: @js($perPage),
        indexUrlBase: @js(route('admin.users.index')),
    })">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Manajemen Akun Staff</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola akun akses sistem untuk pengguna staf, guru, dan pengurus.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Akses & Peran</b>
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="group" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Akun</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalUsers }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <x-icon name="check_circle" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-green-600">Akun Aktif</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalAktif }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-500">
                        <x-icon name="block" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Nonaktif</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalNonaktif }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter_alt" class="h-[15px] w-[15px] text-gray-400" />
                    Filter & Aksi Data
                </p>
                @can('users.create')
                <x-tooltip text="Tambah akun staff baru">
                    <x-link-button href="{{ route('admin.users.create') }}" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-xs sm:text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98] sm:w-auto">
                        <span class="text-base leading-none">+</span> Tambah Akun
                    </x-link-button>
                </x-tooltip>
                @endcan
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Akun</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()" type="text" placeholder="Cari nama atau email..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>
            </div>
        </div>

        <div class="relative rounded-2xl border border-gray-200 bg-white shadow-card">
            <div x-show="loading" class="absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-white/60 backdrop-blur-sm" style="display: none;">
                <div class="flex items-center gap-3 rounded-full bg-white px-4 py-2 shadow-elevated ring-1 ring-gray-900/5">
                    <svg class="h-4 w-4 animate-spin text-brand-500" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span class="text-xs font-medium text-gray-700">Memuat data...</span>
                </div>
            </div>

            <div id="tabel-container">
                @include('admin.users._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 2: Commit perubahan view utama Users**
```bash
git add resources/views/admin/users/index.blade.php
git commit -m "feat(ui): rombak halaman index akun staff sesuai standar baru"
```

---

### Task 4: Buat View Partial untuk Role Builder (Roles)

**Files:**
- Create: `resources/views/admin/roles/_daftar.blade.php`

**Interfaces:**
- Consumes: `$roles`, `$perPage` (dari `RoleController@index`).

- [ ] **Step 1: Buat file `_daftar.blade.php` untuk Role Builder**
```html
<div class="flex flex-col gap-2.5 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-2.5">
        <p class="font-display text-sm font-bold text-gray-900">Daftar Role</p>
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ $roles->total() }} Data</span>
    </div>

    <div class="flex items-center gap-2">
        <label for="per_page" class="text-xs font-medium text-gray-500">Tampilkan:</label>
        <select id="per_page" x-model="perPage" @change="muatUlangDaftar()" class="rounded-lg border-gray-200 py-1 pl-2.5 pr-8 text-xs text-gray-700 shadow-sm transition focus:border-brand-500 focus:ring-brand-500">
            <option value="10">10 / hal</option>
            <option value="20">20 / hal</option>
            <option value="25">25 / hal</option>
            <option value="50">50 / hal</option>
        </select>
    </div>
</div>

<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                <th class="sticky left-0 z-10 bg-white px-5 py-3 w-32">Aksi</th>
                <th class="px-5 py-3">Nama Role</th>
                <th class="px-5 py-3">Scope Level</th>
                <th class="px-5 py-3">Users</th>
                <th class="px-5 py-3">Permissions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($roles as $role)
                <tr class="transition hover:bg-gray-50/50">
                    <td class="sticky left-0 z-10 bg-white px-5 py-3 align-top">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('admin.roles.edit', $role) }}" class="font-medium text-gray-700 hover:text-brand-600">Edit</a>
                            @if (!$role->is_protected)
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Hapus role ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:text-red-800">
                                        Hapus
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                    <td class="px-5 py-3 align-top">
                        <p class="font-medium text-gray-900">{{ $role->name }}</p>
                        @if ($role->is_protected)
                            <span class="mt-1 inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-700 ring-1 ring-brand-600/20">Protected</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 align-top">
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">{{ ucfirst($role->scope_level) }}</span>
                    </td>
                    <td class="px-5 py-3 align-top font-mono text-gray-600">{{ $role->users_count }}</td>
                    <td class="px-5 py-3 align-top font-mono text-gray-600">{{ $role->permissions_count }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-5 py-8 text-center text-gray-500">Tidak ada role yang ditemukan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($roles->hasPages())
    <div class="border-t border-gray-100 px-5 py-4">
        {{ $roles->links('pagination.tailwind-ajax') }}
    </div>
@endif
```

- [ ] **Step 2: Commit perubahan view partial Roles**
```bash
git add resources/views/admin/roles/_daftar.blade.php
git commit -m "feat(ui): tambahkan partial view tabel role builder standar"
```

---

### Task 5: Rombak Index View Role Builder (Roles)

**Files:**
- Modify: `resources/views/admin/roles/index.blade.php`

**Interfaces:**
- Menghapus `<div x-data="rolesTable">` sepenuhnya.
- Membungkus tabel dengan `x-data="dataTableFilter(...)"`.

- [ ] **Step 1: Replace isi `resources/views/admin/roles/index.blade.php`**
```html
<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4" x-data="dataTableFilter({
        filters: {
            search: @js($search),
            scope: @js($scope)
        },
        perPage: @js($perPage),
        indexUrlBase: @js(route('admin.roles.index')),
    })">
        @if (session('status'))
            <div class="rounded-lg bg-success-50 p-4 text-sm text-success-700">{{ session('status') }}</div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Role Builder</h1>
                <p class="text-xs text-gray-500 mt-0.5">Kelola tingkat kewenangan dan akses izin per modul secara spesifik.</p>
            </div>
            <p class="text-sm text-gray-500">
                Beranda <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Akses & Peran</b>
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <x-icon name="shield" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-gray-500">Total Roles</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalRoles }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="domain" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Scope Yayasan</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalYayasan }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <x-icon name="school" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-[11px] font-semibold uppercase tracking-wider text-green-600">Scope Lembaga</p>
                        <p class="font-display text-lg font-bold text-gray-900 leading-tight">{{ $totalLembaga }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <x-icon name="filter_alt" class="h-[15px] w-[15px] text-gray-400" />
                    Filter & Aksi Data
                </p>
                <x-tooltip text="Tambah tingkat role kewenangan baru">
                    <x-link-button href="{{ route('admin.roles.create') }}" class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-xs sm:text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 active:scale-[0.98] sm:w-auto">
                        <span class="text-base leading-none">+</span> Tambah Role
                    </x-link-button>
                </x-tooltip>
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Cari Role</label>
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-icon name="search" class="h-[13px] w-[13px] shrink-0 text-gray-400" />
                        <input x-model="filters.search" @input.debounce.500ms="muatUlangDaftar()" type="text" placeholder="Cari nama role..." class="w-full border-0 bg-transparent p-0 text-xs sm:text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0">
                    </div>
                </div>
                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-500">Scope Level</label>
                    <select x-model="filters.scope" @change="muatUlangDaftar()" class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Semua Scope</option>
                        <option value="yayasan">Yayasan</option>
                        <option value="lembaga">Lembaga</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="relative rounded-2xl border border-gray-200 bg-white shadow-card">
            <div x-show="loading" class="absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-white/60 backdrop-blur-sm" style="display: none;">
                <div class="flex items-center gap-3 rounded-full bg-white px-4 py-2 shadow-elevated ring-1 ring-gray-900/5">
                    <svg class="h-4 w-4 animate-spin text-brand-500" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <span class="text-xs font-medium text-gray-700">Memuat data...</span>
                </div>
            </div>

            <div id="tabel-container">
                @include('admin.roles._daftar')
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 2: Commit perubahan view utama Roles**
```bash
git add resources/views/admin/roles/index.blade.php
git commit -m "feat(ui): rombak halaman index role builder sesuai standar baru"
```
