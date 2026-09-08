# Audit & Perbaikan Menu Pola Jam Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tutup 1 lubang keamanan cross-tenant IDOR kritis di `JamPelajaranController::destroy()`, dan perbaiki 5 celah UI/UX/kejelasan di menu Pola Jam (badge scope, pill lembaga per-kartu, hint tombol tambah, hapus halaman mati, konfirmasi Duplikat).

**Architecture:** 1 perubahan kecil di `JamPelajaranController` (Task 1, terisolasi, tidak menyentuh file lain), lalu 5 task di `PolaJamController` + `index.blade.php` (Task 2-6), ditutup regresi penuh (Task 7).

**Tech Stack:** Laravel 12, Blade, Alpine.js (`confirmDialog()` global helper — sudah ada), Pest (function-style test).

## Global Constraints

- Item A (Task 1) HARUS diselesaikan & di-commit LEBIH DULU, terpisah dari task lain — ini fix keamanan LIVE di production (permission `jam-pelajaran.delete` sudah digrant ke role `operator_akademik`), jangan ditunda demi item UX.
- `PolaJamController.php` SAAT INI **belum** meng-`use App\Models\Lembaga;` — WAJIB ditambahkan di Task 2, TANPA import ini `Lembaga::withoutGlobalScopes()->find()` akan fatal error.
- `scopeHeaderData()` (Task 2) HARUS transkripsi PERSIS dari kode yang sudah ada di `MataPelajaranController`/`KelasController`/`GuruController`/`TahunAjaranController` (sudah dicek ulang identik di keempatnya) — JANGAN menulis ulang dari ingatan, termasuk detail `resolveActiveLembagaId()` dipanggil TANPA gate `$isYayasan` di depan, dan `Lembaga::find()` WAJIB pakai `withoutGlobalScopes()`.
- Model `PolaJam` sendiri (beda dari `JamPelajaran`) TIDAK diubah — scoping-nya sudah benar via `BelongsToTenant`/`TenantScope`, spec ini TIDAK menyentuhnya.
- `_modal-pola.blade.php`, `_modal-edit-slot.blade.php`, `_modal-assign-kelas.blade.php` TIDAK diubah di plan ini — hanya `index.blade.php` (header, pill, tombol tambah, tombol duplikat).
- Permission `pola-jam.create`/`pola-jam.edit` di seeder TETAP DIPERTAHANKAN di Task 5 (hapus halaman mati) — HANYA route GET + method controller + file view yang dihapus, permission-nya masih dipakai `@can()` di tombol modal dan `authorize()` di `store()`/`update()`/`duplicate()`.
- Bug sistemik `TenantScope` untuk aktor platform-scope TIDAK disentuh — backlog terpisah, konsisten dengan semua spec sebelumnya di rangkaian audit ini.

---

## Konteks File yang Sudah Ada (baca sebelum mulai)

- `app/Http/Controllers/Admin/JamPelajaranController.php` — `edit()`/`update()` SUDAH benar (pakai `PolaJam::find($jamPelajaran->pola_jam_id)` sebagai guard tenant lewat scope `PolaJam`), `destroy()` TIDAK punya guard ini sama sekali (Task 1 menambahkannya). `PolaJam` sudah di-`use` (baris 10) — tidak perlu import baru untuk Task 1.
- `app/Http/Controllers/Admin/PolaJamController.php` — `index(): View` SAAT INI tanpa parameter `Request`, tidak kirim `isYayasan`/`activeLembaga` ke view. `use ResolveLembagaScopeTrait;` sudah ada (dipakai `store()`), jadi `resolveActiveLembagaId()` sudah bisa langsung dipanggil di Task 2.
- `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php` — header di baris ±58-74, pill lembaga per-kartu di baris ±87-89, tombol "+ Tambah Pola Jam" di baris ±65-69, form Duplikat di baris ±94-102 (TIDAK ADA konfirmasi apapun saat ini — beda dari form Hapus di file yang sama yang SUDAH pakai `confirmDialog()`).
- `tests/Feature/Admin/JamPelajaranCrudTest.php` — helper `actingAsJamPelajaranManager(Lembaga $lembaga, array $permissions = ['jam-pelajaran.edit', 'jam-pelajaran.delete'])` sudah ada, sudah include `jam-pelajaran.delete` secara default.
- `tests/Feature/Admin/PolaJamCrudTest.php` — helper `actingAsPolaJamManager(Lembaga $lembaga): User` (lembaga-scope, role `operator_akademik`). TIDAK ADA helper untuk aktor yayasan-scope — test yayasan-scope di file ini semua bikin role+user inline (lihat baris 62-115 sebagai contoh pola yang harus diikuti).
- `routes/admin/akademik-master.php` baris 54-61 — 8 route Pola Jam terdaftar berurutan; baris 55 (`pola-jam.create`) dan baris 57 (`pola-jam.edit`) adalah target hapus Task 5, baris lain TIDAK disentuh.

---

### Task 1: 🔴 Fix IDOR — `JamPelajaranController::destroy()`

**Files:**
- Modify: `app/Http/Controllers/Admin/JamPelajaranController.php`
- Test: `tests/Feature/Admin/JamPelajaranCrudTest.php`

**Interfaces:**
- Tidak ada interface baru — memakai ulang `PolaJam::find()` (sudah tenant-scoped lewat `BelongsToTenant`) persis seperti pola `edit()`/`update()` di file yang sama.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Admin/JamPelajaranCrudTest.php` (akhir file):

```php
it('rejects deleting another lembaga\'s jam pelajaran with 404', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJamPelajaranManager($lembaga);
    $polaLain = PolaJam::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $jamLain = JamPelajaran::factory()->create(['pola_jam_id' => $polaLain->id]);

    $this->actingAs($manager)->delete(route('admin.jam-pelajaran.destroy', $jamLain))
        ->assertNotFound();

    expect(JamPelajaran::find($jamLain->id))->not->toBeNull();
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="rejects deleting another lembaga" --compact`
Expected: FAIL — `destroy()` saat ini menghapus baris tanpa mengecek scope, jadi `JamPelajaran::find($jamLain->id)` akan `null` (assertion `not->toBeNull()` gagal), dan HTTP response bukan 404 melainkan 302 redirect sukses.

- [x] **Step 3: Implementasi minimal**

Di `app/Http/Controllers/Admin/JamPelajaranController.php`, ganti:

```php
public function destroy(JamPelajaran $jamPelajaran, DeleteJamPelajaranAction $action): RedirectResponse
{
    $this->authorize('jam-pelajaran.delete');

    try {
        $action->execute($jamPelajaran);
    } catch (ValidationException $e) {
        return back()->withErrors(['jam_pelajaran' => $e->validator->errors()->first('jam_pelajaran')]);
    }

    return redirect()->route('admin.pola-jam.index')->with('status', 'Jam pelajaran berhasil dihapus.');
}
```

menjadi:

```php
public function destroy(JamPelajaran $jamPelajaran, DeleteJamPelajaranAction $action): RedirectResponse
{
    $this->authorize('jam-pelajaran.delete');

    if (! PolaJam::find($jamPelajaran->pola_jam_id)) {
        abort(404);
    }

    try {
        $action->execute($jamPelajaran);
    } catch (ValidationException $e) {
        return back()->withErrors(['jam_pelajaran' => $e->validator->errors()->first('jam_pelajaran')]);
    }

    return redirect()->route('admin.pola-jam.index')->with('status', 'Jam pelajaran berhasil dihapus.');
}
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="rejects deleting another lembaga" --compact`
Expected: PASS.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/JamPelajaranCrudTest.php --compact`
Expected: PASS semua — termasuk test "deletes a jam pelajaran with no jadwal pelajaran" dan "refuses to delete a jam pelajaran that has a jadwal pelajaran" (harus tetap lulus, guard baru tidak boleh mengganggu delete yang sah dalam scope sendiri).

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/JamPelajaranController.php tests/Feature/Admin/JamPelajaranCrudTest.php
git commit -m "fix(jam-pelajaran): tutup IDOR lintas-lembaga di destroy(), samakan guard dengan edit()/update()"
```

---

### Task 2: `scopeHeaderData()` + Badge Header Index

**Files:**
- Modify: `app/Http/Controllers/Admin/PolaJamController.php`
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Produces: view `portals.lembaga.akademik.pola-jam.index` menerima `isYayasan` (`bool`) dan `activeLembaga` (`?Lembaga`) — dipakai lagi oleh Task 3 dan Task 4.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Admin/PolaJamCrudTest.php` (akhir file):

```php
it('shows the "Semua Lembaga" badge in aggregate mode on the pola jam index', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_badge_agregat_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.view']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.pola-jam.index'))
        ->assertSee('Semua Lembaga')
        ->assertSee('border-purple-200 bg-purple-50 text-purple-700', false);
});

it('shows the active lembaga name badge when a yayasan-scoped actor has switched into a lembaga', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_badge_narrow_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Cempaka Raya']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->get(route('admin.pola-jam.index'))
        ->assertSee('SD Cempaka Raya')
        ->assertSee('border-brand-200 bg-brand-50 text-brand-700', false);
});

it('does not show the scope badge for a lembaga-scoped actor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $this->actingAs($manager)->get(route('admin.pola-jam.index'))
        ->assertDontSee('Semua Lembaga');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="Semua Lembaga.*badge|active lembaga name badge|does not show the scope badge" --compact`
Expected: 2 test pertama FAIL (badge belum ada di view sama sekali), test ketiga sudah PASS (baseline — memang belum ada badge apapun untuk siapapun saat ini).

- [x] **Step 3: Implementasi minimal**

Di `app/Http/Controllers/Admin/PolaJamController.php`, tambahkan import setelah `use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;`:

```php
use App\Models\Lembaga;
```

Tambahkan method private baru sebelum `index()`:

```php
/**
 * Info scope yayasan/lembaga yang sedang aktif, ditampilkan sebagai badge di header
 * halaman (pola sama seperti admin/siswa/index.blade.php) -- HANYA relevan untuk aktor
 * berscope yayasan (punya switcher lembaga).
 *
 * @return array{isYayasan: bool, activeLembaga: ?Lembaga}
 */
private function scopeHeaderData(Request $request): array
{
    $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
    $lembagaId = $this->resolveActiveLembagaId($request->user());

    return [
        'isYayasan' => $isYayasan,
        'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
    ];
}
```

Ganti `index()`:

```php
public function index(): View
{
    $this->authorize('pola-jam.view');

    return view('portals.lembaga.akademik.pola-jam.index', [
        'polaJamList' => PolaJam::with(['jamPelajaran', 'lembaga', 'kelas.tahunAjaran'])->orderBy('nama')->get(),
        'kelasList' => Kelas::with(['tahunAjaran', 'polaJam'])->orderBy('nama')->get(),
    ]);
}
```

menjadi:

```php
public function index(Request $request): View
{
    $this->authorize('pola-jam.view');

    return view('portals.lembaga.akademik.pola-jam.index', [
        'polaJamList' => PolaJam::with(['jamPelajaran', 'lembaga', 'kelas.tahunAjaran'])->orderBy('nama')->get(),
        'kelasList' => Kelas::with(['tahunAjaran', 'polaJam'])->orderBy('nama')->get(),
        ...$this->scopeHeaderData($request),
    ]);
}
```

Di `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php`, ganti (baris ±60-63):

```blade
<div>
    <h1 class="font-display text-lg font-bold text-gray-900">Pola Jam &amp; Jam Pelajaran</h1>
    <p class="text-xs text-gray-500 mt-0.5">Kelola jadwal waktu belajar harian dan tautkan dengan kelas yang relevan.</p>
</div>
```

menjadi:

```blade
<div>
    <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="font-display text-lg font-bold text-gray-900">Pola Jam &amp; Jam Pelajaran</h1>
        @if ($isYayasan ?? false)
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                <x-icon name="apartment" class="h-3.5 w-3.5" />
                {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
            </span>
        @endif
    </div>
    <p class="text-xs text-gray-500 mt-0.5">Kelola jadwal waktu belajar harian dan tautkan dengan kelas yang relevan.</p>
</div>
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="Semua Lembaga.*badge|active lembaga name badge|does not show the scope badge" --compact`
Expected: PASS ketiga test.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/PolaJamController.php resources/views/portals/lembaga/akademik/pola-jam/index.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat(pola-jam): tambah scopeHeaderData() + badge scope di header index"
```

---

### Task 3: Pill Nama Lembaga Per-Kartu Hanya Saat Agregat

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: `$isYayasan`, `$activeLembaga` dari Task 2.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows the lembaga name pill per card in aggregate mode', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_pill_agregat_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Cendekia']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Agregat Test']);

    $this->actingAs($manager)->get(route('admin.pola-jam.index'))
        ->assertSee('SMP Cendekia');
});

it('hides the per-card lembaga pill (keeping only the single header badge mention) once a lembaga is switched into', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_pill_narrow_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SMP Cendekia Utama']);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);
    PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Narrow Test']);

    $response = $this->actingAs($manager)
        ->withSession(['active_lembaga_id' => $lembaga->id])
        ->get(route('admin.pola-jam.index'));

    expect(substr_count($response->getContent(), 'SMP Cendekia Utama'))->toBe(1);
});
```

- [x] **Step 2: Jalankan test, pastikan test kedua gagal**

Run: `php artisan test --filter="lembaga name pill per card in aggregate mode|hides the per-card lembaga pill" --compact`
Expected: test pertama sudah PASS (pill sudah muncul tanpa syarat saat ini). Test kedua FAIL — nama lembaga saat ini muncul 2 kali (badge header + pill kartu), bukan 1 kali.

- [x] **Step 3: Implementasi minimal**

Di `index.blade.php`, ganti (baris ±87-89):

```blade
@if($pola->lembaga)
    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ $pola->lembaga->nama }}</span>
@endif
```

menjadi:

```blade
@if (($isYayasan ?? false) && ! ($activeLembaga ?? null) && $pola->lembaga)
    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ $pola->lembaga->nama }}</span>
@endif
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="lembaga name pill per card in aggregate mode|hides the per-card lembaga pill" --compact`
Expected: PASS kedua test.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/pola-jam/index.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "fix(pola-jam): pill nama lembaga per-kartu hanya tampil saat mode agregat"
```

---

### Task 4: Hint Tombol "+ Tambah Pola Jam" Saat Mode Agregat

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Consumes: `$isYayasan`, `$activeLembaga` dari Task 2.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('disables the "+ Tambah Pola Jam" button when a yayasan-scoped actor has not switched into a lembaga', function () {
    Permission::firstOrCreate(['name' => 'pola-jam.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'pola-jam.create', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pola_jam_disabled_btn_test', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pola-jam.view', 'pola-jam.create']);

    $yayasan = Yayasan::factory()->create();
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    $this->actingAs($manager)->get(route('admin.pola-jam.index'))
        ->assertSee('Pilih lembaga aktif lewat pengalih lembaga terlebih dahulu', false);
});

it('enables the "+ Tambah Pola Jam" button for a lembaga-scoped actor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);

    $this->actingAs($manager)->get(route('admin.pola-jam.index'))
        ->assertSee('openCreatePola()', false)
        ->assertDontSee('Pilih lembaga aktif lewat pengalih lembaga terlebih dahulu', false);
});
```

- [x] **Step 2: Jalankan test, pastikan test pertama gagal**

Run: `php artisan test --filter="disables the .\+ Tambah Pola Jam.|enables the .\+ Tambah Pola Jam." --compact`
Expected: test kedua sudah PASS (tombol memang selalu aktif saat ini). Test pertama FAIL — belum ada state disabled apapun.

- [x] **Step 3: Implementasi minimal**

Di `index.blade.php`, ganti (baris ±65-69):

```blade
@can('pola-jam.create')
    <x-primary-button type="button" @click="openCreatePola()" class="shrink-0 justify-center">
        <span class="text-base leading-none mr-1.5">+</span> Tambah Pola Jam
    </x-primary-button>
@endcan
```

menjadi:

```blade
@can('pola-jam.create')
    @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
        <x-primary-button type="button" disabled title="Pilih lembaga aktif lewat pengalih lembaga terlebih dahulu" class="shrink-0 justify-center opacity-50 cursor-not-allowed">
            <span class="text-base leading-none mr-1.5">+</span> Tambah Pola Jam
        </x-primary-button>
    @else
        <x-primary-button type="button" @click="openCreatePola()" class="shrink-0 justify-center">
            <span class="text-base leading-none mr-1.5">+</span> Tambah Pola Jam
        </x-primary-button>
    @endif
@endcan
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="disables the .\+ Tambah Pola Jam.|enables the .\+ Tambah Pola Jam." --compact`
Expected: PASS kedua test.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php --compact`
Expected: PASS semua.

- [x] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/pola-jam/index.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat(pola-jam): nonaktifkan tombol Tambah Pola Jam saat mode agregat, beri hint"
```

---

### Task 5: Hapus Halaman Mati (`create()`/`edit()`)

**Files:**
- Modify: `app/Http/Controllers/Admin/PolaJamController.php`
- Modify: `routes/admin/akademik-master.php`
- Delete: `resources/views/portals/lembaga/akademik/pola-jam/create.blade.php`
- Delete: `resources/views/portals/lembaga/akademik/pola-jam/edit.blade.php`

**Interfaces:**
- Tidak ada interface baru — murni penghapusan.

- [x] **Step 1: Verifikasi WAJIB sebelum menghapus apapun**

Run: `php artisan route:list --name=pola-jam`
Expected output berisi 8 route: `pola-jam.index`, `pola-jam.create`, `pola-jam.store`, `pola-jam.edit`, `pola-jam.update`, `pola-jam.destroy`, `pola-jam.assign-kelas`, `pola-jam.duplicate`. Catat baik-baik bahwa HANYA `pola-jam.create` (GET) dan `pola-jam.edit` (GET) yang akan dihapus di step ini — 6 route lain TIDAK disentuh.

Run: `grep -rn "pola-jam.create\|pola-jam.edit" resources/views/ app/`
Expected: SEMUA hasil adalah `@can('pola-jam.create')`/`@can('pola-jam.edit')` (menggerbangi tombol modal, BUKAN link navigasi) atau definisi controller/route itu sendiri. Kalau ternyata ADA `<a href="{{ route('admin.pola-jam.create') }}">` atau `edit') }}">` di file manapun — STOP, jangan lanjut ke Step 2, laporkan ke user karena berarti asumsi "halaman mati" di spec ini salah.

- [x] **Step 2: Hapus route**

Di `routes/admin/akademik-master.php`, hapus baris:

```php
Route::get('pola-jam/create', [PolaJamController::class, 'create'])->name('pola-jam.create');
```

dan:

```php
Route::get('pola-jam/{polaJam}/edit', [PolaJamController::class, 'edit'])->name('pola-jam.edit');
```

Baris `pola-jam.index`, `pola-jam.store`, `pola-jam.update`, `pola-jam.destroy`, `pola-jam.assign-kelas`, `pola-jam.duplicate` TETAP ADA, tidak diubah urutan maupun isinya.

- [x] **Step 3: Hapus method controller**

Di `app/Http/Controllers/Admin/PolaJamController.php`, hapus method:

```php
public function create(): View
{
    $this->authorize('pola-jam.create');

    return view('portals.lembaga.akademik.pola-jam.create');
}
```

dan:

```php
public function edit(PolaJam $polaJam): View
{
    $this->authorize('pola-jam.edit');

    return view('portals.lembaga.akademik.pola-jam.edit', ['polaJam' => $polaJam]);
}
```

- [x] **Step 4: Hapus file view**

```bash
git rm resources/views/portals/lembaga/akademik/pola-jam/create.blade.php
git rm resources/views/portals/lembaga/akademik/pola-jam/edit.blade.php
```

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php --compact`
Expected: PASS semua — tidak ada test yang meng-hit GET `pola-jam.create`/`pola-jam.edit` (sudah diverifikasi di Step 1 spec-level), jadi tidak ada test yang akan gagal karena route hilang.

- [x] **Step 6: Verifikasi route memang sudah tidak terdaftar**

Run: `php artisan route:list --name=pola-jam`
Expected: hanya 6 route tersisa (`index`, `store`, `update`, `destroy`, `assign-kelas`, `duplicate`) — `create` dan `edit` sudah tidak ada di daftar.

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/PolaJamController.php routes/admin/akademik-master.php
git commit -m "chore(pola-jam): hapus halaman mati create/edit (alur nyata 100% lewat modal di index)"
```

---

### Task 6: `confirmDialog()` untuk Tombol Duplikat

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php`
- Test: `tests/Feature/Admin/PolaJamCrudTest.php`

**Interfaces:**
- Tidak ada interface baru — `confirmDialog()` helper global sudah tersedia (sudah dipakai form Hapus di file yang sama).

- [x] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('uses confirmDialog() for the Duplikat button instead of submitting instantly with no confirmation', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPolaJamManager($lembaga);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Pola Duplikat Test']);
    JamPelajaran::factory()->count(2)->create(['pola_jam_id' => $pola->id]);

    $response = $this->actingAs($manager)->get(route('admin.pola-jam.index'));

    $response->assertSee('confirmDialog(', false);
    $response->assertSee('Tautan kelas TIDAK ikut disalin', false);
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="uses confirmDialog\(\) for the Duplikat button" --compact`
Expected: FAIL — form Duplikat saat ini submit langsung tanpa `confirmDialog()` apapun.

- [x] **Step 3: Implementasi minimal**

Di `index.blade.php`, ganti (baris ±94-102):

```blade
<form action="{{ route('admin.pola-jam.duplicate', $pola) }}" method="POST" class="inline">
    @csrf
    <button type="submit"
            class="rounded-lg border border-brand-200 bg-brand-50/50 px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100/70 hover:text-brand-800 transition flex items-center gap-1 shadow-2xs"
            title="Salin / Duplikasi Pola Jam">
        <x-icon name="content_copy" class="h-3.5 w-3.5" />
        <span>Duplikat</span>
    </button>
</form>
```

menjadi:

```blade
<form action="{{ route('admin.pola-jam.duplicate', $pola) }}" method="POST" class="inline" x-data
      @submit.prevent="confirmDialog(
          'Duplikasi Pola Jam?',
          @js('Akan membuat pola jam baru \''.$pola->nama.' (Salinan)\' berisi salinan semua '.$pola->jamPelajaran->count().' slot jam dari pola ini. Tautan kelas TIDAK ikut disalin — kelas perlu ditautkan ulang secara manual ke pola baru lewat \'Kelola Tautan\'.'),
          { confirmLabel: 'Ya, Duplikasi' }
      ).then(confirmed => { if (confirmed) $el.submit() })">
    @csrf
    <button type="submit"
            class="rounded-lg border border-brand-200 bg-brand-50/50 px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100/70 hover:text-brand-800 transition flex items-center gap-1 shadow-2xs"
            title="Salin / Duplikasi Pola Jam">
        <x-icon name="content_copy" class="h-3.5 w-3.5" />
        <span>Duplikat</span>
    </button>
</form>
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="uses confirmDialog\(\) for the Duplikat button" --compact`
Expected: PASS.

- [x] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/PolaJamCrudTest.php --compact`
Expected: PASS semua — termasuk test "duplicates a pola jam along with all its jam pelajaran slots without copying kelas bindings" (mekanisme `duplicate()` di controller/action TIDAK diubah, hanya lapisan konfirmasi di view).

- [x] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/pola-jam/index.blade.php tests/Feature/Admin/PolaJamCrudTest.php
git commit -m "feat(pola-jam): confirmDialog() untuk tombol Duplikat + penjelasan eksplisit apa yang disalin"
```

---

### Task 7: Penutup — Regresi Penuh, Pint, Verifikasi Manual

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [x] **Step 1: Jalankan seluruh test domain Pola Jam & Jam Pelajaran**

Run: `php artisan test --compact --filter="PolaJamCrudTest|JamPelajaranCrudTest|KelasPolaJamTest|DeletePolaJamActionTest|DuplicatePolaJamActionTest|PolaJamSeederTest"`
Expected: PASS semua, 0 gagal.

- [x] **Step 2: Jalankan regresi seeder permission**

Run: `php artisan test --compact --filter="RolePermissionSeederTest"`
Expected: PASS — Task 5 menghapus route/method/view tapi TIDAK menyentuh permission `pola-jam.create`/`pola-jam.edit` di seeder, jadi test ini harus tetap lulus tanpa perubahan apapun.

- [x] **Step 3: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [x] **Step 4: Verifikasi manual via browser (WAJIB)**

Login sebagai LEMBAGA-scope dengan permission `jam-pelajaran.delete`: buka Pola Jam, hapus 1 slot jam pelajaran milik lembaga sendiri — harus berhasil normal (regresi Task 1 tidak boleh memblokir delete yang sah).

Login sebagai YAYASAN-scope, mode "Semua Lembaga": badge ungu "Semua Lembaga" di header, pill nama lembaga muncul di tiap kartu pola jam, tombol "+ Tambah Pola Jam" terlihat nonaktif (abu-abu, ada tooltip saat hover). Switch ke 1 lembaga: badge berubah jadi nama lembaga (brand color), pill per-kartu hilang (karena sudah terwakili badge header), tombol "+ Tambah Pola Jam" aktif kembali dan modal bisa dibuka.

Klik tombol "Duplikat" pada salah satu pola jam: dialog modal standar muncul (BUKAN langsung tercipta record baru), pesan menjelaskan slot yang disalin dan peringatan tautan kelas tidak ikut. Konfirmasi → pola baru "<nama> (Salinan)" muncul di daftar dengan slot yang sama, tanpa tautan kelas.

Coba akses langsung `/pola-jam/create` dan `/pola-jam/{id}/edit` lewat URL bar: harus 404 (route sudah tidak terdaftar).

- [x] **Step 5: Buat handoff log**

Ikuti pola dokumentasi yang sudah dipakai untuk audit-audit sebelumnya di rangkaian ini: tulis 1 file baru di `.agents/logs/` dengan nama `2026-09-08-pola-jam-audit-perbaikan.md`, berisi ringkasan temuan (terutama Item A — IDOR kritis, sertakan bukti empiris HTTP 302 + delete berhasil sebelum fix, dan konfirmasi HTTP 404 + data tidak terhapus setelah fix), daftar 6 item yang diperbaiki, commit hash tiap task, dan hasil regresi test. Ini WAJIB dilakukan sebagai bagian dari Task 7 — BUKAN permintaan tambahan terpisah.

```bash
git add .agents/logs/2026-09-08-pola-jam-audit-perbaikan.md
git commit -m "docs(pola-jam): handoff log audit & perbaikan (IDOR kritis + 5 item UX)"
```

---

## Self-Review

**1. Spec coverage** — SEMUA 6 item spec `.agents/specs/2026-09-08-pola-jam-audit-perbaikan.md` tercakup: Item A → Task 1, Item B → Task 2, Item C → Task 3, Item D → Task 4, Item E → Task 5, Item F → Task 6. Task 7 menutup dengan regresi + Pint + verifikasi manual + handoff log (sesuai permintaan eksplisit user).

**2. Placeholder scan** — tidak ada "TBD"/dst. Semua step berisi kode lengkap, ditranskripsi persis dari spec yang sudah diverifikasi ulang terhadap kode aktual (termasuk koreksi `scopeHeaderData()` dan import `Lembaga` yang sempat salah di draf pertama spec).

**3. Type consistency** — `isYayasan`/`activeLembaga` dipakai konsisten Task 2 (asal), Task 3 dan Task 4 (konsumsi) — nama variabel sama persis di ketiganya.

**Catatan tambahan hasil self-review**:
- Task 1 SENGAJA berdiri sendiri sebagai task pertama dan boleh di-commit terpisah dari task lain kalau plan ini dieksekusi bertahap — ini fix keamanan live, tidak boleh menunggu 5 item UX selesai duluan.
- Task 3 Step 1 test kedua pakai `substr_count($response->getContent(), ...) === 1` alih-alih `assertDontSee()` biasa — sengaja, karena nama lembaga tetap muncul SEKALI lewat badge header (Task 2) di mode narrow; `assertDontSee()` polos akan salah gagal.
- Task 5 Step 1 (verifikasi grep+route:list) punya instruksi eksplisit "STOP kalau ternyata ada link" — ini bukan sekadar dokumentasi, tapi gerbang keamanan sebelum destructive action (hapus file/route/method).
- Task 7 Step 5 (handoff log) ditambahkan sesuai permintaan eksplisit user di luar pola task-closeout sebelumnya di rangkaian audit ini (plan-plan sebelumnya secara eksplisit TIDAK meminta handoff log) — jangan bingung kalau pola ini beda dari kickoff-kickoff sebelumnya.
