# Audit & Perbaikan Menu Pola Jam

**Tanggal**: 2026-09-08
**Branch**: `akademik-v2`
**Status**: Draft — menunggu review user sebelum plan+kickoff

## Ringkasan

Audit menu Pola Jam & Jam Pelajaran menemukan 1 bug keamanan kritis (cross-tenant IDOR, TERBUKTI EMPIRIS) dan 5 celah UI/UX/kejelasan. Semua digabung dalam 1 spec atas persetujuan user ("jika tidak ada resiko mending digabung saja") — fix IDOR menyentuh file berbeda (`JamPelajaranController`) dari 5 item UX (`PolaJamController` + view), sehingga aman digabung tanpa risiko saling tabrak.

**Backend scoping Pola Jam sendiri (model `PolaJam`) sudah BENAR** — pakai `BelongsToTenant`, otomatis menyempit/agregat lewat `TenantScope` yang sudah teruji solid (`tests/Feature/TenantScopeTest.php`), bukan hasil re-implementasi manual seperti kasus lama Kurikulum Assignment. Celah yang ditemukan murni di `JamPelajaran` (child model tanpa `BelongsToTenant`) dan di lapisan UI/UX.

---

## Item A — 🔴 KRITIS: IDOR lintas-lembaga di `JamPelajaranController::destroy()`

### Masalah

Aktor lembaga-scope dengan permission `jam-pelajaran.delete` bisa menghapus slot Jam Pelajaran milik **lembaga lain**, bahkan **yayasan lain**, cukup dengan mengetahui/menebak ID-nya.

**Root cause**: model `JamPelajaran` TIDAK memakai `BelongsToTenant` (tidak ada kolom `lembaga_id`, scoping-nya numpang lewat relasi `pola_jam_id` ke `PolaJam` induknya yang memang tenant-scoped). `edit()` dan `update()` di `JamPelajaranController` SUDAH benar — keduanya melakukan pengecekan manual `PolaJam::find($jamPelajaran->pola_jam_id)` yang memanfaatkan `TenantScope` milik `PolaJam` (kalau pola jam induknya di luar scope aktor, `find()` mengembalikan `null` → `abort(404)`). **`destroy()` TIDAK PUNYA pengecekan yang sama sekali.**

**Dibuktikan empiris** (test HTTP sementara, sudah dihapus setelah verifikasi): actor lembaga A `DELETE /jam-pelajaran/{id milik lembaga B}` → HTTP 302 (redirect sukses normal) → slot **benar-benar terhapus** dari database.

**Bukan bug dorman** — permission `jam-pelajaran.delete` sudah digrant ke role `operator_akademik` (`database/seeders/RoleSeeder.php` baris ±33, `scope_level: 'lembaga'`, role staf akademik biasa di tiap lembaga) — live & exploitable di production sekarang.

### Kode saat ini (`app/Http/Controllers/Admin/JamPelajaranController.php`)

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

### Perbaikan

Tambahkan pengecekan yang SAMA PERSIS seperti yang sudah dipakai `edit()`/`update()` di controller yang sama (baris 67-76, 78-84) — `PolaJam` sudah tenant-scoped lewat `BelongsToTenant`, jadi `PolaJam::find()` otomatis mengembalikan `null` untuk pola jam di luar scope aktor:

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

`PolaJam` sudah di-`use` di file yang sama (baris 10) — tidak perlu import tambahan.

### Test yang wajib ditambahkan

Mirror persis test existing "rejects editing another lembaga's jam pelajaran with 404" (`tests/Feature/Admin/JamPelajaranCrudTest.php` baris 59-70), untuk method `destroy()`:

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

---

## Item B — Badge scope di header index Pola Jam

### Masalah

`PolaJamController::index()` tidak mengirim `isYayasan`/`activeLembaga` ke view — satu-satunya menu di rangkaian audit ini yang belum ikut pola badge scope yang sudah konsisten di Tahun Ajaran/Kelas/Mata Pelajaran/Kurikulum Assignment. User tidak punya cara cepat melihat sedang di mode "Semua Lembaga" atau sudah narrow ke 1 lembaga.

### Perbaikan

**PENTING**: `PolaJamController.php` SAAT INI belum meng-`use App\Models\Lembaga;`. Tambahkan import ini di bagian atas file — tanpanya, `Lembaga::withoutGlobalScopes()->find()` di `scopeHeaderData()` di bawah akan fatal error (`Class "App\Http\Controllers\Admin\Lembaga" not found`, karena PHP mencari di namespace controller, bukan `App\Models`).

Tambah helper `scopeHeaderData()` di `PolaJamController` — pola PERSIS sama seperti yang sudah dipakai `MataPelajaranController`/`KelasController`/`GuruController`/`TahunAjaranController` (dicek ulang langsung dari kode ketiganya, BUKAN `KurikulumAssignmentController` yang menghitungnya inline tanpa helper terpisah):

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

Catatan: `resolveActiveLembagaId()` dipanggil TANPA GATE `$isYayasan` di depan (beda dari draf pertama saya) — untuk aktor lembaga-scope, method ini cuma balikin `lembaga_id` milik sendiri (harmless), lalu di-diskarding oleh kondisi `$isYayasan &&` di baris return. `withoutGlobalScopes()` pada `Lembaga::find()` juga sengaja diikutkan meski `Lembaga` model TIDAK punya global scope apapun saat ini (dicek `app/Models/Lembaga.php`) — murni ikut konvensi yang sudah dipakai seragam di 4 controller lain, bukan kebutuhan fungsional saat ini.

`index()` memanggilnya dan menyebarkan hasilnya ke view (perlu `Request $request` di signature, saat ini `index(): View` tanpa parameter):

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

Badge di header `index.blade.php` (baris ±58-74), pola identik dengan badge Kurikulum Assignment:

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

---

## Item C — Pill nama lembaga per-kartu hanya saat mode agregat

### Masalah

Pill nama lembaga (`index.blade.php` baris 87-89) SELALU muncul di setiap kartu pola jam, termasuk saat sudah narrow ke 1 lembaga — redundan begitu Item B (badge header) ada, dan tidak konsisten dengan konvensi "sembunyikan label per-item di luar mode agregat" yang dipakai Kelas/Kurikulum Assignment.

### Kode saat ini

```blade
@if($pola->lembaga)
    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ $pola->lembaga->nama }}</span>
@endif
```

### Perbaikan

```blade
@if (($isYayasan ?? false) && ! ($activeLembaga ?? null) && $pola->lembaga)
    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ $pola->lembaga->nama }}</span>
@endif
```

---

## Item D — Hint tombol "+ Tambah Pola Jam" saat mode agregat (opsional, prioritas rendah)

### Konteks

Guard di `store()` (sudah ada & benar — redirect dengan `$errors` kalau yayasan-scope belum pilih lembaga aktif) plus tampilan `$errors->any()` di `index.blade.php` (sudah ada & benar) SUDAH CUKUP menutupi kasus ini secara fungsional. Item ini murni polish supaya user tidak perlu coba-submit dulu baru tahu — TIDAK mengubah alur bisnis apapun.

### Perbaikan

Tombol "+ Tambah Pola Jam" (`index.blade.php` baris 66-68) diberi kondisi disabled + tooltip saat `$isYayasan` true dan `$activeLembaga` null:

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

---

## Item E — Hapus halaman mati (`create()`/`edit()`)

### Masalah

Alur nyata pembuatan/edit Pola Jam 100% lewat modal Alpine inline (`_modal-pola.blade.php`, dipicu `openCreatePola()`/`openEditPola()` dari `index.blade.php`, POST langsung ke `store`/`update`). Method `PolaJamController::create()`/`edit()`, route GET `pola-jam.create`/`pola-jam.edit`, dan view `create.blade.php`/`edit.blade.php` tidak pernah diakses lewat link manapun di aplikasi, dan tidak ada test yang meng-hit GET-nya. Pola identik dengan temuan halaman mati Tahun Ajaran yang sudah diaudit & dihapus sebelumnya.

### Verifikasi WAJIB sebelum hapus

1. `php artisan route:list --name=pola-jam` — pastikan hanya route `create`/`edit` yang mau dihapus, route lain (`index`, `store`, `update`, `destroy`, `assign-kelas`, `duplicate`) TIDAK disentuh.
2. `grep -rn "pola-jam.create\|pola-jam.edit" resources/views/ app/` — pastikan TIDAK ADA `<a href>` atau referensi lain ke route ini SELAIN `@can('pola-jam.create')`/`@can('pola-jam.edit')` (yang menggerbangi tombol modal, BUKAN link ke halaman ini) dan definisi controller/route itu sendiri.

### Yang dihapus

- Route `Route::get('pola-jam/create', ...)` dan `Route::get('pola-jam/{polaJam}/edit', ...)` di `routes/admin/akademik-master.php`.
- Method `PolaJamController::create()` dan `PolaJamController::edit()`.
- File `resources/views/portals/lembaga/akademik/pola-jam/create.blade.php` dan `edit.blade.php`.

### Yang TETAP DIPERTAHANKAN

Permission `pola-jam.create`/`pola-jam.edit` di seeder — masih dipakai `@can()` di tombol modal (`index.blade.php` baris 65, 93, 104) dan `$this->authorize()` di `store()`/`update()`/`duplicate()`.

---

## Item F — `confirmDialog()` untuk tombol Duplikat + penjelasan eksplisit

### Masalah

Tombol Duplikat (`index.blade.php` baris 94-102) TIDAK PUNYA konfirmasi APAPUN — klik langsung POST dan langsung tercipta record baru, tanpa jeda bagi user untuk memahami apa yang akan terjadi. Ini kemungkinan besar akar "kebingungan user" yang dilaporkan: tidak jelas bahwa (a) hanya slot jam yang disalin, (b) tautan kelas TIDAK ikut disalin dan perlu ditautkan ulang manual.

### Kode saat ini

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

### Perbaikan

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

---

## Di Luar Scope

- Bug sistemik `TenantScope` untuk aktor platform-scope (0 baris untuk SEMUA model `BelongsToTenant`, termasuk `PolaJam`) — backlog terpisah, konsisten dengan keputusan di semua spec sebelumnya di rangkaian audit ini.
- Tidak menambah fitur baru (filter, pencarian, pagination) di index Pola Jam.
- Tidak mengubah mekanisme modal (`_modal-pola.blade.php`, `_modal-edit-slot.blade.php`, `_modal-assign-kelas.blade.php`) — hanya `index.blade.php` (header, pill, tombol tambah, tombol duplikat) dan controller yang disentuh.
- Tidak audit ulang `JadwalPelajaran`/modul lain yang terhubung — di luar cakupan menu Pola Jam.

## Tabel Panduan Test

| Item | Test yang dibutuhkan |
|---|---|
| A | "rejects deleting another lembaga's jam pelajaran with 404" (regresi kritis) |
| B | Badge muncul dengan teks & warna benar, baik mode agregat maupun narrow |
| C | Pill lembaga per-kartu TIDAK muncul saat narrow, MUNCUL saat agregat |
| D | Tombol "+ Tambah Pola Jam" disabled saat agregat, aktif saat narrow/lembaga-scope |
| E | Route `pola-jam.create`/`pola-jam.edit` sudah tidak terdaftar; regresi test existing (create/update/destroy/assign/duplicate lewat modal-style request) tetap lulus |
| F | Response mengandung `confirmDialog(` dan TIDAK ada submit langsung tanpa konfirmasi pada form duplikat |
