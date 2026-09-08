# Spec: Relasi `tahunAjaran` Ke-Scope Diam-Diam — Menu Kurikulum Assignment (Susulan)

> **Branch**: `akademik-v2` (setara `rbac-v2`)
> **Tanggal**: 8 September 2026
> **Latar belakang**: Susulan langsung dari `.agents/specs/2026-09-08-kurikulum-assignment-scope-wording-audit.md` (6 item sebelumnya SUDAH selesai & terverifikasi). User menemukan lewat screenshot nyata: kolom "Tahun Ajaran" tampil "-" untuk baris yang BUKAN lembaga sedang ditinjau, padahal datanya valid. Investigasi menemukan akar masalahnya BUKAN di `KurikulumAssignment` (model itu sendiri TIDAK pakai `TenantScope`), tapi di relasi `tahunAjaran()` → model `TahunAjaran` YANG pakai `TenantScope`, dan `TenantScope` otomatis ikut menempel ke SETIAP query terhadap `TahunAjaran` — TERMASUK query relasi eager-load — kecuali dibebaskan eksplisit lewat `withoutGlobalScope(TenantScope::class)`.
>
> **Batas scope spec ini**: HANYA memperbaiki 3 titik query di `KurikulumAssignmentController` yang menyentuh relasi/tabel `TahunAjaran` tanpa bypass. **`TenantScope` itu sendiri TIDAK disentuh** — root-cause di sana (perilaku `TenantScope` untuk aktor platform-scope pada model manapun secara umum) adalah temuan TERPISAH yang jauh lebih besar (mempengaruhi 47 model di seluruh aplikasi), sengaja BUKAN bagian spec ini, perlu spec/plan/kickoff sendiri dengan regresi jauh lebih luas kalau/ketika user memutuskan untuk menggarapnya.

## Ringkasan Temuan (3 titik, 1 akar masalah)

| # | Lokasi | Severity | Ringkasan |
|---|---|---|---|
| C.1 | `index()` | 🔴 Tinggi | `->with(['lembaga', 'tahunAjaran'])` — relasi `tahunAjaran` ke-scope ke lembaga yang SEDANG DITINJAU aktor (session `active_lembaga_id`), bukan ke lembaga PEMILIK baris assignment. Baris milik lembaga lain tampil "Tahun Ajaran: -" walau datanya valid. **Sudah dikonfirmasi lewat screenshot nyata user + query database langsung.** |
| C.2 | `edit()` | 🔴 Tinggi | `->loadMissing('tahunAjaran', 'lembaga')` — bug identik C.1, tapi di halaman edit. Makin sering kena SEKARANG karena hasil perbaikan sebelumnya (`canManageAssignment()`) justru dengan BENAR mengizinkan yayasan-scope mengedit assignment lembaga MANAPUN di yayasannya — bukan cuma yang sedang ditinjau. **Dikonfirmasi empiris lewat simulasi tinker.** |
| C.3 | `tahunAjaranListForScope()` (dipakai `create()`) | 🟡 Sedang | Cabang `platform` (`TahunAjaran::orderByDesc('tanggal_mulai')->get()`) TIDAK bypass `TenantScope` — dropdown "Tahun Ajaran" di halaman Tambah Assignment akan **kosong total** untuk aktor platform-scope. Dikonfirmasi lewat pembacaan kode + prinsip yang sama seperti C.1/C.2 (belum sempat direproduksi via browser sungguhan karena tidak ada akun platform-scope hidup di seed data saat ini — TETAP masuk scope karena logic-nya identik & sudah terbukti salah di C.1/C.2). |

## Keputusan yang Diambil

1. **Fix di level QUERY RELASI, bukan di `TenantScope`** — pola `->with(['relasi' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])` SUDAH menjadi konvensi established di codebase ini (dipakai `KasusAksesLogController`, `KasusTerhapusController`, sebagian besar `DashboardController`) — spec ini MEREPLIKASI pola itu, bukan menciptakan pendekatan baru.
2. **`lembaga` relation TIDAK perlu bypass** — dikonfirmasi model `Lembaga` TIDAK memakai `BelongsToTenant`/`TenantScope` sama sekali (dia adalah unit tenant itu sendiri, bukan sesuatu yang di-scope oleh tenant lain) — cuma `tahunAjaran` yang jadi masalah.
3. **`KurikulumAssignment` model sendiri TIDAK dan TIDAK PERLU pakai `TenantScope`** — dikonfirmasi ulang (lihat spec sebelumnya) — scoping-nya SUDAH benar ditangani manual di `index()`'s `$query->where(...)` eksplisit. Temuan C.1-C.3 SEMUANYA soal relasi ke `TahunAjaran`, bukan soal `KurikulumAssignment` itu sendiri.
4. **Root-cause di `TenantScope` (kenapa relasi eager-load ikut ke-scope sama sekali) TIDAK diperbaiki di sini** — dikonfirmasi lewat investigasi terpisah bahwa ini memengaruhi 47 model di seluruh aplikasi, bukan spesifik Kurikulum Assignment. Memperbaikinya di titik root akan mengubah perilaku fondasi isolasi multi-tenant SELURUH aplikasi — high blast-radius, perlu keputusan & proses terpisah yang lebih hati-hati (spec/plan/kickoff sendiri, regresi PENUH bukan cuma filter modul).

---

## C.1 — `index()`: Bypass `TenantScope` pada Eager-Load `tahunAjaran`

**File**: `app/Http/Controllers/Admin/KurikulumAssignmentController.php` (method `index()`)

Kode saat ini:
```php
$query = KurikulumAssignment::with(['lembaga', 'tahunAjaran']);
```

Fix:
```php
$query = KurikulumAssignment::with(['lembaga', 'tahunAjaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);
```

`TenantScope` SUDAH di-import di file ini (dipakai `store()`'s `$tahunAjaranValid` check) — tidak perlu import baru.

---

## C.2 — `edit()`: Bypass `TenantScope` pada `loadMissing('tahunAjaran')`

**File**: `app/Http/Controllers/Admin/KurikulumAssignmentController.php` (method `edit()`)

Kode saat ini:
```php
return view('admin.kurikulum-assignment.edit', [
    'assignment' => $kurikulumAssignment->loadMissing('tahunAjaran', 'lembaga'),
    'kurikulumList' => KurikulumFramework::cases(),
    'bentukPendidikanList' => BentukPendidikan::cases(),
    'isPlatform' => $isPlatform,
]);
```

Fix:
```php
return view('admin.kurikulum-assignment.edit', [
    'assignment' => $kurikulumAssignment->loadMissing([
        'tahunAjaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
        'lembaga',
    ]),
    'kurikulumList' => KurikulumFramework::cases(),
    'bentukPendidikanList' => BentukPendidikan::cases(),
    'isPlatform' => $isPlatform,
]);
```

---

## C.3 — `tahunAjaranListForScope()`: Bypass `TenantScope` untuk Cabang Platform

**File**: `app/Http/Controllers/Admin/KurikulumAssignmentController.php` (private method `tahunAjaranListForScope()`)

Kode saat ini:
```php
private function tahunAjaranListForScope(Request $request)
{
    $scope = $request->user()->widestScopeLevel();

    if ($scope === 'platform') {
        return TahunAjaran::orderByDesc('tanggal_mulai')->get();
    }

    if ($scope === 'yayasan') {
        $activeLembagaId = session('active_lembaga_id');
        if ($activeLembagaId) {
            return TahunAjaran::where('lembaga_id', $activeLembagaId)->orderByDesc('tanggal_mulai')->get();
        }

        $lembagaIds = Lembaga::where('yayasan_id', $request->user()->yayasan_id)->pluck('id');

        return TahunAjaran::whereIn('lembaga_id', $lembagaIds)->orderByDesc('tanggal_mulai')->get();
    }

    return TahunAjaran::where('lembaga_id', $request->user()->lembaga_id)->orderByDesc('tanggal_mulai')->get();
}
```

Fix (HANYA cabang `platform` yang berubah):
```php
private function tahunAjaranListForScope(Request $request)
{
    $scope = $request->user()->widestScopeLevel();

    if ($scope === 'platform') {
        return TahunAjaran::withoutGlobalScope(TenantScope::class)->orderByDesc('tanggal_mulai')->get();
    }

    if ($scope === 'yayasan') {
        $activeLembagaId = session('active_lembaga_id');
        if ($activeLembagaId) {
            return TahunAjaran::where('lembaga_id', $activeLembagaId)->orderByDesc('tanggal_mulai')->get();
        }

        $lembagaIds = Lembaga::where('yayasan_id', $request->user()->yayasan_id)->pluck('id');

        return TahunAjaran::whereIn('lembaga_id', $lembagaIds)->orderByDesc('tanggal_mulai')->get();
    }

    return TahunAjaran::where('lembaga_id', $request->user()->lembaga_id)->orderByDesc('tanggal_mulai')->get();
}
```

**Kenapa cabang `yayasan`/lembaga-scope TIDAK perlu diubah**: keduanya SUDAH benar secara kebetulan — cabang `yayasan` dengan `activeLembagaId` terisi melakukan `where('lembaga_id', $activeLembagaId)` eksplisit, yang SAMA PERSIS dengan filter yang akan ditambahkan `TenantScope` (redundant, tapi tidak salah). Cabang tanpa `activeLembagaId` (`whereIn('lembaga_id', $lembagaIds)`, mode "Semua Lembaga") demikian juga — `TenantScope`'s cabang yayasan menghitung SET LEMBAGA YANG SAMA PERSIS secara independen. Cabang paling akhir (lembaga-scope, `where('lembaga_id', $request->user()->lembaga_id)`) juga cocok dengan apa yang `TenantScope` akan tambahkan untuk aktor itu. **Cuma cabang `platform` yang berbeda** — `TenantScope` menambahkan `WHERE lembaga_id IS NULL` (karena `$actingUser->lembaga_id` platform selalu `null`), yang TIDAK COCOK dengan niat kode aslinya (`TahunAjaran::orderByDesc(...)->get()` — jelas dimaksudkan TANPA filter sama sekali untuk platform).

---

## Di Luar Scope / Backlog Terpisah

1. **Root-cause di `TenantScope::apply()`** — baris fallback `$builder->where($model->getTable().'.lembaga_id', $actingUser->lembaga_id);` memperlakukan aktor platform-scope SEOLAH lembaga-scope dengan `lembaga_id = null` (menghasilkan `WHERE lembaga_id IS NULL`), padahal seharusnya TIDAK ADA FILTER SAMA SEKALI untuk platform pada model apa pun (bukan cuma model `User` seperti kondisi skip yang ada sekarang). **DIKONFIRMASI EMPIRIS lewat tinker** mempengaruhi MINIMAL `TahunAjaran` dan `Kelas` (2 model yang sempat dites), dan berpotensi ke 45 model `BelongsToTenant` lainnya. **TIDAK DIPERBAIKI DI SINI** — layak spec/plan/kickoff TERSENDIRI dengan regresi test PENUH (bukan cuma filter modul terkait), karena ini fondasi keamanan isolasi tenant SELURUH aplikasi.
2. **Audit menyeluruh 47 model `BelongsToTenant` × seluruh controller** untuk pola eager-load serupa yang belum di-bypass — SEBAGIAN sudah disampling (`KasusAksesLogController`, `KasusTerhapusController`, `DashboardController` — MAYORITAS sudah benar, beberapa baris di `DashboardController` baris 55/126/138 BELUM diverifikasi mendalam apakah berisiko) — TIDAK dituntaskan di sini, backlog terpisah kalau user ingin audit sistemik penuh.
3. **`platform_super_admin` role saat ini TIDAK punya permission apa pun** di seeder asli (dikonfirmasi) — jadi C.3 murni pencegahan dini (defense-in-depth) untuk skenario yang belum bisa direproduksi lewat akun sungguhan hari ini, TAPI SUDAH bisa direproduksi lewat pola role platform-scope ad-hoc yang dipakai test `KurikulumAssignmentControllerTest.php` sendiri.

---

## Ringkasan Test yang Wajib Ditambahkan

| Item | Test |
|---|---|
| C.1 | Feature test — yayasan-scope switch ke Lembaga A, assignment milik Lembaga B (yayasan sama) py Tahun Ajaran bernama unik, assert `assertSee` nama Tahun Ajaran itu di response index (BUKAN "-") |
| C.2 | Feature test — skenario SAMA seperti C.1 tapi di halaman edit assignment milik Lembaga B saat sedang meninjau Lembaga A |
| C.3 | Feature test — `actingAsPlatformScopeKurikulumManager()` (helper sudah ada), buat 1 Tahun Ajaran, assert `assertSee` nama Tahun Ajaran itu di halaman create |

Regresi wajib: seluruh `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (SEMUA test existing, termasuk 6+ test dari spec sebelumnya — tidak satu pun boleh berubah perilakunya, fix ini murni MENAMBAH data yang sebelumnya hilang, bukan mengubah struktur/otorisasi apa pun).
