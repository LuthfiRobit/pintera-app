# Spec: Kunci "Bentuk Pendidikan" ke Lembaga — Menu Kurikulum Assignment (Susulan)

> **Branch**: `akademik-v2` (setara `rbac-v2`)
> **Tanggal**: 8 September 2026
> **Latar belakang**: Susulan ke-2 dari audit Kurikulum Assignment. User menemukan lewat screenshot form Tambah Assignment: field "Bentuk Pendidikan" adalah dropdown BEBAS (bisa pilih apa saja), padahal `lembaga.bentuk_pendidikan` SUDAH punya 1 nilai TETAP per lembaga (dikonfirmasi query: SDIT PINTERA="SD", TK Pintera Ceria="TK"). Investigasi menemukan ini BUKAN cuma redundan secara kosmetik — **`CreateKelasAction` (satu-satunya konsumen nyata data ini lewat `KurikulumAssignmentResolver`) SELALU memakai `$lembaga->bentuk_pendidikan` yang tetap**, TIDAK PERNAH menerima pilihan bebas. Assignment yang `bentuk_pendidikan`-nya TIDAK COCOK dengan `bentuk_pendidikan` milik `lembaga_id`-nya sendiri **tidak akan PERNAH terpakai** — mati total. **Dikonfirmasi ADA di database sungguhan saat ini**: assignment milik SDIT PINTERA (bentuk_pendidikan="SD") dengan `bentuk_pendidikan` assignment = "TK".

## Keputusan Produk (dikonfirmasi user)

**Untuk aktor yayasan-scope dan lembaga-scope**: field "Bentuk Pendidikan" DIKUNCI OTOMATIS ke `bentuk_pendidikan` milik lembaga yang bersangkutan (lembaga aktif saat create, lembaga pemilik assignment saat edit) — dropdown bebas DIHAPUS, diganti tampilan read-only, pola identik "Berlaku Untuk" yang sudah read-only untuk scope ini.

**Untuk aktor PLATFORM-scope**: TIDAK diubah — TETAP dropdown bebas, di CREATE maupun EDIT. Alasan: platform bisa membuat assignment GLOBAL (`lembaga_id = null`, tidak terikat 1 lembaga manapun, bebas pilih memang benar) ATAU untuk 1 lembaga spesifik manapun lintas yayasan (kalau platform sengaja ingin override kombinasi tertentu, itu wewenang mereka, di luar cakupan keputusan produk ini).

## Ringkasan Temuan & Fix (1 root cause, 3 titik kode)

| # | Lokasi | Fix |
|---|---|---|
| D.1 | `_form.blade.php` — field "Bentuk Pendidikan" | Untuk non-platform: dropdown → teks read-only + hidden input berisi `bentuk_pendidikan` lembaga terkait |
| D.2 | `store()` | Nilai `bentuk_pendidikan` yang DISIMPAN untuk non-platform DIHITUNG ULANG server-side dari `Lembaga::find($lembagaId)->bentuk_pendidikan` — TIDAK mempercayai nilai dari request (defense-in-depth, mencegah manipulasi lewat devtools) |
| D.3 | `update()` | Sama seperti D.2, pola identik, sumbernya `$kurikulumAssignment->lembaga_id` (immutable) |

**`StoreKurikulumAssignmentRequest`/`UpdateKurikulumAssignmentRequest` TIDAK DIUBAH SAMA SEKALI** — keduanya tetap mewajibkan `bentuk_pendidikan` terkirim (`required`) dan tetap menjalankan cross-check `tingkat` vs `bentuk_pendidikan` yang sudah ada (`withValidator`) — dipenuhi oleh hidden input (D.1), BUKAN oleh perubahan rule validasi.

---

## D.1 — View: Kunci Field untuk Non-Platform

**File**: `resources/views/admin/kurikulum-assignment/_form.blade.php`

Kode saat ini (baris ±60-68, TIDAK berubah berdasarkan mode create/edit maupun scope aktor):
```blade
<div class="sm:col-span-6">
    <x-input-label value="Bentuk Pendidikan" />
    <select name="bentuk_pendidikan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        @foreach ($bentukPendidikanList as $bp)
            <option value="{{ $bp->value }}" @selected($val('bentuk_pendidikan') === $bp->value)>{{ $bp->value }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('bentuk_pendidikan')" class="mt-1.5" />
</div>
```

Fix:
```blade
@if ($isPlatform ?? false)
    <div class="sm:col-span-6">
        <x-input-label value="Bentuk Pendidikan" />
        <select name="bentuk_pendidikan" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            @foreach ($bentukPendidikanList as $bp)
                <option value="{{ $bp->value }}" @selected($val('bentuk_pendidikan') === $bp->value)>{{ $bp->value }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('bentuk_pendidikan')" class="mt-1.5" />
    </div>
@else
    @php
        $lembagaBentukPendidikan = $assignment ? $assignment->lembaga?->bentuk_pendidikan : ($activeLembaga->bentuk_pendidikan ?? null);
    @endphp
    <div class="sm:col-span-6">
        <x-input-label value="Bentuk Pendidikan" />
        <p class="mt-1.5 rounded-lg border border-gray-100 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-600">{{ $lembagaBentukPendidikan }} <span class="text-gray-400">(mengikuti bentuk pendidikan lembaga, tidak bisa diubah)</span></p>
        <input type="hidden" name="bentuk_pendidikan" value="{{ $lembagaBentukPendidikan }}">
    </div>
@endif
```

**Kenapa TETAP ada `<input type="hidden">`, bukan cuma teks polos**: `StoreKurikulumAssignmentRequest`/`UpdateKurikulumAssignmentRequest` KEDUANYA mewajibkan `bentuk_pendidikan` (`required`) DAN memakainya untuk cross-check `tingkat` (`withValidator`). Kalau field ini dihapus total dari form, validasi `required` akan GAGAL untuk semua submit non-platform. Hidden input memenuhi syarat form TANPA membuka celah edit manual di UI — nilai FINAL yang benar-benar tersimpan TETAP dihitung ulang di server (D.2/D.3), TIDAK mempercayai isi hidden input ini kalau sempat dimanipulasi lewat devtools.

**Variabel yang dipakai SUDAH TERSEDIA, TIDAK perlu perubahan controller untuk view data**:
- Mode create, non-platform: `$activeLembaga` (SUDAH dikirim `create()`, dijamin non-null lewat guard yang sudah ada).
- Mode edit, non-platform: `$assignment->lembaga` (SUDAH ter-load — baik lewat `loadMissing()` yang sudah ada SEKARANG, maupun setelah fix C.2 di spec susulan sebelumnya; relasi `lembaga()` ke model `Lembaga` TIDAK terpengaruh isu `TenantScope` sama sekali karena `Lembaga` TIDAK memakai `BelongsToTenant`). Dijamin non-null untuk non-platform karena `authorizeExistingAssignmentScope()` sudah menolak (403) non-platform yang mencoba edit assignment global (`lembaga_id = null`) SEBELUM sampai ke titik ini.

---

## D.2 — `store()`: Hitung Ulang `bentuk_pendidikan` Server-Side untuk Non-Platform

**File**: `app/Http/Controllers/Admin/KurikulumAssignmentController.php` (method `store()`)

Kode saat ini:
```php
$validated = $request->validated();
$tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;
$lembagaIdDiminta = $request->user()->widestScopeLevel() === 'platform' ? ($validated['lembaga_id'] ?? null) : null;

if ($request->user()->widestScopeLevel() === 'yayasan' && $this->resolveActiveLembagaId($request->user()) === null) {
    return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah assignment kurikulum.'])->withInput();
}

$lembagaId = $this->resolveLembagaId($request->user(), $lembagaIdDiminta);

if ($lembagaId !== null) {
    $tahunAjaranValid = TahunAjaran::withoutGlobalScope(TenantScope::class)
        ->whereKey($validated['tahun_ajaran_id'])
        ->where('lembaga_id', $lembagaId)
        ->exists();

    if (! $tahunAjaranValid) {
        return back()->withErrors(['tahun_ajaran_id' => 'Tahun ajaran yang dipilih bukan milik lembaga ini.'])->withInput();
    }
}

if (KurikulumAssignment::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
    return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
}

$action->executeCreate($request->user(), $validated['bentuk_pendidikan'], $tingkat, $validated['kurikulum'], $lembagaIdDiminta, (int) $validated['tahun_ajaran_id']);
```

Fix (sisipkan penghitungan ulang SETELAH `$lembagaId` diketahui, lalu ganti SEMUA pemakaian `$validated['bentuk_pendidikan']` jadi `$bentukPendidikan`):
```php
$validated = $request->validated();
$tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;
$lembagaIdDiminta = $request->user()->widestScopeLevel() === 'platform' ? ($validated['lembaga_id'] ?? null) : null;

if ($request->user()->widestScopeLevel() === 'yayasan' && $this->resolveActiveLembagaId($request->user()) === null) {
    return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah assignment kurikulum.'])->withInput();
}

$lembagaId = $this->resolveLembagaId($request->user(), $lembagaIdDiminta);

// Non-platform: bentuk_pendidikan SELALU mengikuti bentuk_pendidikan milik lembaga tujuan --
// nilai dari hidden input form TIDAK dipercaya begitu saja, dihitung ulang di server supaya
// tidak bisa dimanipulasi lewat devtools untuk membuat kombinasi yang mustahil terpakai
// CreateKelasAction (yang selalu memakai $lembaga->bentuk_pendidikan, bukan pilihan bebas).
$bentukPendidikan = $validated['bentuk_pendidikan'];
if ($request->user()->widestScopeLevel() !== 'platform') {
    $bentukPendidikan = Lembaga::find($lembagaId)?->bentuk_pendidikan ?? $bentukPendidikan;
}

if ($lembagaId !== null) {
    $tahunAjaranValid = TahunAjaran::withoutGlobalScope(TenantScope::class)
        ->whereKey($validated['tahun_ajaran_id'])
        ->where('lembaga_id', $lembagaId)
        ->exists();

    if (! $tahunAjaranValid) {
        return back()->withErrors(['tahun_ajaran_id' => 'Tahun ajaran yang dipilih bukan milik lembaga ini.'])->withInput();
    }
}

if (KurikulumAssignment::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])->where('bentuk_pendidikan', $bentukPendidikan)->where('tingkat', $tingkat)->exists()) {
    return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
}

$action->executeCreate($request->user(), $bentukPendidikan, $tingkat, $validated['kurikulum'], $lembagaIdDiminta, (int) $validated['tahun_ajaran_id']);
```

`Lembaga` SUDAH di-import di file ini (dipakai `authorizeExistingAssignmentScope()`/`tahunAjaranListForScope()`) — tidak perlu import baru.

---

## D.3 — `update()`: Hitung Ulang `bentuk_pendidikan` Server-Side untuk Non-Platform

**File**: `app/Http/Controllers/Admin/KurikulumAssignmentController.php` (method `update()`)

Kode saat ini:
```php
$validated = $request->validated();
$tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;

if (KurikulumAssignment::where('id', '!=', $kurikulumAssignment->id)->where('lembaga_id', $kurikulumAssignment->lembaga_id)->where('tahun_ajaran_id', $kurikulumAssignment->tahun_ajaran_id)->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
    return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
}

$action->execute($kurikulumAssignment, new KurikulumAssignmentData(
    bentukPendidikan: $validated['bentuk_pendidikan'],
    tingkat: $tingkat,
    kurikulum: $validated['kurikulum'],
    lembagaId: $kurikulumAssignment->lembaga_id,
    tahunAjaranId: $kurikulumAssignment->tahun_ajaran_id,
));
```

Fix:
```php
$validated = $request->validated();
$tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;

// Sama seperti store() -- non-platform TIDAK BISA mengubah bentuk_pendidikan menjauh dari
// bentuk_pendidikan lembaga pemilik assignment ini (lembaga_id sendiri immutable, dijamin
// authorizeExistingAssignmentScope() di atas method ini).
$bentukPendidikan = $validated['bentuk_pendidikan'];
if ($request->user()->widestScopeLevel() !== 'platform') {
    $bentukPendidikan = Lembaga::find($kurikulumAssignment->lembaga_id)?->bentuk_pendidikan ?? $bentukPendidikan;
}

if (KurikulumAssignment::where('id', '!=', $kurikulumAssignment->id)->where('lembaga_id', $kurikulumAssignment->lembaga_id)->where('tahun_ajaran_id', $kurikulumAssignment->tahun_ajaran_id)->where('bentuk_pendidikan', $bentukPendidikan)->where('tingkat', $tingkat)->exists()) {
    return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
}

$action->execute($kurikulumAssignment, new KurikulumAssignmentData(
    bentukPendidikan: $bentukPendidikan,
    tingkat: $tingkat,
    kurikulum: $validated['kurikulum'],
    lembagaId: $kurikulumAssignment->lembaga_id,
    tahunAjaranId: $kurikulumAssignment->tahun_ajaran_id,
));
```

**Catatan kasus global**: untuk assignment global (`lembaga_id = null`), `authorizeExistingAssignmentScope()` SUDAH menjamin HANYA platform yang bisa sampai ke method ini (403 untuk non-platform sebelum baris manapun di atas dieksekusi) — jadi cabang `if ($request->user()->widestScopeLevel() !== 'platform')` TIDAK PERNAH true untuk assignment global, `Lembaga::find(null)` TIDAK PERNAH benar-benar dipanggil dalam praktiknya. Tetap ditulis dengan null-safe (`?->`) sebagai defensive coding standar, bukan karena skenario itu benar-benar mungkin terjadi.

---

## Di Luar Scope / Backlog Terpisah

1. **Data existing yang SUDAH mismatch** (assignment SDIT PINTERA dengan `bentuk_pendidikan = 'TK'` yang ditemukan saat audit) — spec ini TIDAK melakukan migrasi/pembersihan data lama, HANYA mencegah kombinasi baru yang salah terbentuk lagi. Data lama yang sudah telanjur mismatch akan tetap ada (mati/tidak terpakai) sampai dihapus manual oleh Platform Admin lewat halaman ini sendiri (dropdown bebas TETAP tersedia untuk platform, jadi mereka bisa Edit baris itu kalau mau membetulkan/menghapusnya).
2. **Validasi platform juga dikunci** (opsi #2 yang TIDAK dipilih user) — platform TETAP bebas pilih `bentuk_pendidikan` apa pun untuk lembaga spesifik manapun, termasuk yang berpotensi menghasilkan assignment "mati" seperti temuan awal. Ini keputusan produk eksplisit, bukan celah yang terlewat.
3. **Rombak UI create() jadi dinamis (JS re-lock saat platform ganti pilihan lembaga di dropdown "Berlaku Untuk")** — TIDAK termasuk. Platform TETAP melihat 2 dropdown independen ("Berlaku Untuk" dan "Bentuk Pendidikan") tanpa saling mempengaruhi secara live di browser — konsisten dengan keputusan #2 di atas (platform memang sengaja diberi kebebasan penuh).

---

## Ringkasan Test yang Wajib Ditambahkan

| Item | Test |
|---|---|
| D.1 | Feature test — yayasan-scope, GET halaman create, assert `assertSee` teks bentuk_pendidikan lembaga aktif, assert `assertDontSee` markup `<select name="bentuk_pendidikan"` (string literal, pastikan bukan dropdown). Feature test kedua — yayasan-scope edit assignment miliknya, assert pola sama dengan bentuk_pendidikan milik lembaga assignment tsb. |
| D.2 | Feature test — yayasan-scope switch ke lembaga ber-`bentuk_pendidikan='SD'`, POST `store()` dengan `bentuk_pendidikan` DIPAKSA `'TK'` di payload (simulasi manipulasi devtools), assert assignment yang TERSIMPAN tetap `bentuk_pendidikan = 'SD'` (BUKAN 'TK' yang dikirim). |
| D.3 | Feature test — pola sama seperti D.2 tapi untuk `update()` pada assignment existing. |
| Regresi | Feature test existing "platform BISA membuat assignment global" dan "platform BISA membuat assignment untuk lembaga manapun lintas yayasan" (SUDAH ADA) — assert TETAP hijau tanpa perubahan (platform TIDAK terpengaruh sama sekali oleh spec ini). |

Regresi wajib: seluruh `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (SEMUA test existing dari spec-spec sebelumnya).
