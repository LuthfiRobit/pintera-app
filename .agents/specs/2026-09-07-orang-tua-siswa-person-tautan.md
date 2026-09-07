# Spec: Perbaikan Tautan Orang Tua-Siswa & Konsistensi Identitas Person

> **Branch**: `rbac-v2`
> **Tanggal**: 7 September 2026
> **Latar belakang**: User melaporkan bug fungsional di halaman Data Induk Orang Tua ("filter 0 tapi saat dicek detail ada yang tertaut anaknya"). Investigasi mendalam (baca kode + verifikasi empiris via tinker terhadap DB nyata `pintera_sdm_app`, dibungkus transaksi rollback) menemukan bug itu BUKAN soal scope yayasan/lembaga (topik spec sebelumnya `2026-09-07-scope-yayasan-lembaga-menu-fix.md`), melainkan 4 bug fungsional berbeda yang saling terkait lewat relasi Siswa-OrangTua-Person. Item #3 di bawah (pencarian NIK orang tua) adalah yang PALING PARAH — jalur pendaftaran keluarga baru (siswa + tautkan orang tua) yang jadi alur utama aplikasi ternyata gagal total untuk SEMUA level scope.

## Keputusan Bisnis: Urutan Pendaftaran Siswa-Orang Tua

**Siswa didaftarkan lebih dulu, orang tua ditautkan kemudian (dari Profil Siswa) — ini alur UTAMA/resmi, bukan salah satu dari dua alur setara.**

Alasan (dikonfirmasi lewat diskusi dengan user):
- Realita operasional sekolah: pendaftaran siswa adalah peristiwa utama; orang tua "muncul" sebagai konsekuensi pendaftaran siswa, bukan sebaliknya.
- Kode yang sudah ada mendukung arah ini secara eksplisit — komentar di `resources/views/admin/orang-tua/tabs/siswa.blade.php` baris 7 & 19: *"Penautan dilakukan dari Profil Siswa"*.
- Kasus kakak-adik (siswa kedua dst.) adalah momen paling penting: sistem WAJIB menemukan profil orang tua yang sudah ada (lewat pencarian NIK) supaya tidak membuat duplikat `Person`/`User`. Bug #3 di bawah membuat momen ini gagal total.

**Implikasi desain**: halaman Data Induk → Orang Tua (`OrangTuaController`, berdiri sendiri) tetap ada sebagai alat KELOLA profil orang tua yang sudah terdaftar (edit data, lihat anak tertaut, nonaktifkan akun) dan sebagai jalur sekunder untuk pra-input data orang tua sebelum siswa terdaftar. Bukan dihapus, hanya diposisikan ulang secara peran — tidak ada perubahan kode untuk poin ini sendiri, hanya konteks untuk mengevaluasi prioritas fix di bawah.

## Konteks Arsitektur (WAJIB dipahami sebelum baca detail fix)

- **`Person`** (`app/Domains/Identity/Models/Person.php`) adalah tabel identitas induk bersama — `Siswa`, `Guru`, `Karyawan`, `OrangTua` masing-masing `hasOne`/`belongsTo` ke 1 baris `Person` lewat `person_id`. NIK disimpan `encrypted` di `Person.nik`, dengan `nik_hash` (SHA-256, auto-computed di `saving()`) untuk pencarian.
- **`Person::YayasanScope`** (`app/Models/Scopes/YayasanScope.php`) — global scope KHUSUS `Person`, berbeda dari `TenantScope`. Scope berdasarkan `yayasan_id` SAJA (bukan `lembaga_id`) — desain yang benar karena identitas orang per-yayasan, bukan per-lembaga.
- **`CreatePersonAction`** (`app/Domains/Identity/Actions/CreatePersonAction.php`) — pola RUJUKAN yang SUDAH BENAR untuk deteksi duplikat NIK: `Person::withoutGlobalScope(YayasanScope::class)->where('yayasan_id', $yayasanId)->where('nik_hash', $nikHash)->first()`, sengaja bypass scope karena aktor yang login bisa beda yayasan dari target yang dicek. Kalau ketemu → `PersonAlreadyExistsException`.
- **`OrangTuaController::store()`** (`app/Http/Controllers/Admin/OrangTuaController.php:77`) sudah mengikuti pola benar yang setara: `User::withoutGlobalScopes()->where('username', $data['nik'])->first()`.
- **`SiswaOrangTuaController`** (jalur UTAMA, lihat Keputusan Bisnis di atas) TIDAK mengikuti pola ini — inilah akar Bug #3.
- **`OrangTua::siswa()`** relasi (`app/Models/OrangTua.php`) didefinisikan dengan `->withoutGlobalScopes()` BAKED IN di level relasi — artinya `withCount('siswa')` dan `->load('siswa')` SELALU menghitung/memuat SEMUA anak lintas lembaga DAN lintas yayasan, tanpa terkecuali. Ini benar untuk `edit()` (selalu fresh load), tapi jadi sumber Bug #2 di `index()` (kartu ringkasan yang di-scope ke 1 lembaga tapi angkanya lolos scope).
- **`MergePersonsAction`** (`app/Domains/Identity/Actions/MergePersonsAction.php`) sudah ada & teruji (dari proyek `identity-v1-person-master-entity`), tapi **tidak ada UI admin yang memicunya** — murni dipanggil terprogram (event `PersonsMerged` didengar `ReparentTagihanOnPersonsMerged`). Di luar scope spec ini — TIDAK membangun UI merge; fix di bawah cukup MENCEGAH duplikat terjadi di titik penciptaan, bukan membersihkan duplikat setelah terjadi.

## Ruang Lingkup

4 bug independen (dikerjakan 1 plan, saling terkait tema Siswa-OrangTua-Person tapi masing-masing testable terpisah).

---

## Bug #1 — Index Orang Tua: Snapshot Client-Side Basi, Tidak Pernah Refresh

**File**: `resources/views/admin/orang-tua/index.blade.php`

Seluruh halaman adalah Alpine.js SPA (`x-data="orangTuaIndexSPA()"`, baris 243-341) yang meng-embed SATU snapshot data (`@json($spaItems)`, baris 223-241) saat load pertama. Filter "Ada Anak"/"Belum Ada Anak" (baris 96-116, `filteredItems` baris 268-284) murni menghitung dari array statis ini — **tidak ada mekanisme re-fetch apapun**. Karena penautan anak SELALU terjadi dari halaman Siswa (bukan halaman ini — lihat Keputusan Bisnis), skenario berikut sangat mungkin: admin buka index Orang Tua → tab baru buka Siswa → tautkan anak ke orang tua X → kembali ke tab index Orang Tua (tanpa reload) → filter "Belum Ada Anak" masih menampilkan orang tua X (data page-load lama), padahal detail sudah tertaut.

**Fix**: ubah filter "Ada Anak"/"Belum Ada Anak" DAN pencarian nama/NIK dari client-side murni menjadi query param server-side (pola yang sudah dipakai `SiswaController::index()` — `->when($request->status, ...)`), TIDAK perlu SPA reaktif penuh untuk filter ini. Struktur:

1. `OrangTuaController::index()` (`app/Http/Controllers/Admin/OrangTuaController.php:24-62`) terima query param baru `?anak=ada|belum` selain `search` yang sudah ada, terapkan SETELAH query scope yayasan/lembaga (Bug A.3 spec sebelumnya) sudah jalan:
   ```php
   ->when($request->query('anak') === 'ada', fn ($q) => $q->having('siswa_count', '>', 0))
   ->when($request->query('anak') === 'belum', fn ($q) => $q->having('siswa_count', 0))
   ```
   (`withCount('siswa')` sudah ada di query yang sama — baris 36 — sehingga `siswa_count` tersedia untuk `having()`. Perlu pindah filter `anak` SETELAH `->withCount('siswa')` di chain method, dan gunakan `having` bukan `where` karena `siswa_count` adalah hasil agregat.)
2. Filter button (baris 96-116) diubah dari Alpine `@click="activeFilter = 'ada'"` menjadi link biasa `<a href="{{ route('admin.orang-tua.index', ['anak' => 'ada'] + request()->except('anak')) }}">` (pola link filter server-side, cek `resources/views/admin/siswa/index.blade.php` untuk referensi pola filter serupa yang SUDAH ADA di codebase supaya konsisten).
3. Hapus logika `activeFilter`/`filteredItems` dari `orangTuaIndexSPA()` Alpine component (baris 268-284) — SISAKAN Alpine hanya untuk hal yang genuinely client-side (kalau ada, mis. modal konfirmasi nonaktifkan). Kalau setelah dicek TIDAK ADA lagi kebutuhan Alpine reaktif di halaman ini setelah filter dipindah server-side, boleh hapus `x-data` seluruhnya dan render `@foreach` biasa dari `$orangTuaList` — TAPI cek dulu penggunaan Alpine lain di file yang sama sebelum memutuskan (mis. dropdown aksi per-baris) sebelum menghapus komponen secara keseluruhan.

**Acceptance criteria**:
1. Tautkan anak ke orang tua X dari halaman Siswa, lalu buka (reload biasa, BUKAN navigasi SPA) halaman index Orang Tua dengan filter "Belum Ada Anak" → orang tua X TIDAK muncul.
2. Filter "Ada Anak"/"Belum Ada Anak"/"Semua" tetap bekerja seperti sebelumnya secara visual (tombol, highlight aktif).
3. Filter search nama/NIK yang sudah ada (baris `search`, `OrangTuaController::index()` baris ~40) tidak berubah perilakunya.
4. Test baru: buat 2 `OrangTua` (1 py anak, 1 tidak), request `?anak=ada` → assert hanya yang py anak ikut; request `?anak=belum` → assert sebaliknya.

---

## Bug #2 — Kartu/Angka "Anak Tertaut": Over-Count Lintas Lembaga & Lintas Yayasan

**File**: `app/Http/Controllers/Admin/OrangTuaController.php` (`index()`, `withCount('siswa')` baris 36; `edit()`, `->load('siswa')` sekitar baris 112-116)

Diverifikasi empiris (tinker, transaksi rollback): admin lembaga-scope yang punya siswa A (anak dari OrangTua X) di lembaganya, sementara OrangTua X JUGA punya anak B di lembaga LAIN (yayasan sama), melihat `siswa_count = 2` — padahal seharusnya cuma berwenang melihat/menghitung 1 (anak yang ada di lembaganya sendiri). Akar penyebab: `OrangTua::siswa()` (`app/Models/OrangTua.php`) mendefinisikan relasi dengan `->withoutGlobalScopes()` BAKED IN di level relasi (bukan dipanggil situasional) — SETIAP pemakaian relasi ini (baik `withCount()` maupun `->load()`) otomatis bypass `TenantScope`, tanpa jalan untuk mempersempit balik ke lembaga aktor.

**Ini BUKAN bug di `edit()`** — halaman edit SENGAJA menampilkan SEMUA anak lintas lembaga (tab "Anak Tertaut" adalah pandangan lengkap profil orang tua, bukan pandangan ter-scope). Perilaku ini konsisten dengan `Person::YayasanScope` yang berbasis yayasan (bukan lembaga) — orang tua adalah entitas level yayasan. **Yang salah HANYA angka ringkasan di `index()`** (dan turunannya kalau ada, mis. total count di kartu statistik atas halaman kalau ada) yang seharusnya merefleksikan "berapa anak yang BERWENANG dilihat aktor ini", bukan "berapa anak sebenarnya di seluruh yayasan/sistem".

**Fix**: `OrangTuaController::index()` (`app/Http/Controllers/Admin/OrangTuaController.php:24-62`, kondisi SETELAH Bug A.3 spec sebelumnya diterapkan — `$lembagaIdsYayasan` baris 30 dan `$activeLembagaId` baris 31 SUDAH ADA, JANGAN dihitung ulang) — ganti `withCount('siswa')` baris 39 menjadi `withCount(['siswa' => ...])` dengan closure scope yang sama persis dengan yang dipakai `orWhereHas('siswa', ...)` di baris 42/45-50 untuk konsistensi:

```php
$orangTuaList = OrangTua::with(['user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class), 'person'])
    ->withCount(['siswa' => function ($q) use ($user, $lembagaIdsYayasan, $activeLembagaId) {
        $q->withoutGlobalScope(TenantScope::class);
        if ($user->widestScopeLevel() !== 'yayasan') {
            $q->where('siswa.lembaga_id', $user->lembaga_id);
        } elseif ($activeLembagaId) {
            $q->where('siswa.lembaga_id', $activeLembagaId);
        } else {
            $q->whereIn('siswa.lembaga_id', $lembagaIdsYayasan);
        }
    }])
    ->when($user->widestScopeLevel() !== 'yayasan', fn ($q) => $q->where(fn ($q2) => $q2
        ->whereDoesntHave('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class))
        ->orWhereHas('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class)->where('siswa.lembaga_id', $user->lembaga_id))))
    ->when($user->widestScopeLevel() === 'yayasan', fn ($q) => $q->where(function ($q2) use ($lembagaIdsYayasan, $activeLembagaId) {
        $q2->whereDoesntHave('siswa', fn ($q3) => $q3->withoutGlobalScope(TenantScope::class))
            ->orWhereHas('siswa', function ($q3) use ($lembagaIdsYayasan, $activeLembagaId) {
                $q3->withoutGlobalScope(TenantScope::class);
                $activeLembagaId
                    ? $q3->where('siswa.lembaga_id', $activeLembagaId)
                    : $q3->whereIn('siswa.lembaga_id', $lembagaIdsYayasan);
            });
    }))
    ->when($search, fn ($q) => $q->search($search))
    ->orderByNama()
    ->get();
```
Baris 40-54 (dua `->when()` visibilitas, `->when($search, ...)`, `->orderByNama()->get()`) TIDAK berubah — hanya baris 39 (`withCount`) yang diganti.

**Acceptance criteria**:
1. Admin lembaga-scope A, orang tua X punya anak di lembaga A (1) DAN lembaga B (1, yayasan sama) → `siswa_count` di index menampilkan **1**, bukan 2.
2. Yayasan mode "Semua Lembaga" → `siswa_count` menampilkan agregat SEMUA lembaga milik yayasan sendiri (2 dalam contoh di atas), TIDAK termasuk anak di yayasan lain.
3. Halaman `edit()` (tab "Anak Tertaut") TIDAK berubah — tetap menampilkan SEMUA anak lintas lembaga (perilaku yang benar, sesuai penjelasan di atas).
4. Test baru: skenario tepat seperti tinker verifikasi (2 lembaga 1 yayasan, orang tua sama, 1 anak masing-masing) — assert index `siswa_count` = 1 untuk admin lembaga A, = 2 untuk yayasan mode "Semua Lembaga".

---

## Bug #3 — Pencarian NIK Orang Tua dari Halaman Siswa: Gagal Total untuk SEMUA Level Scope

**File**: `app/Http/Controllers/Admin/SiswaOrangTuaController.php` (`cari()` baris 21-44, `store()` baris 46-118)

**Prioritas tertinggi di spec ini** — ini jalur UTAMA aplikasi (lihat Keputusan Bisnis), dan diverifikasi GAGAL TOTAL untuk SEMUA level scope, termasuk skenario paling longgar (yayasan mode "Semua Lembaga"), lewat 2 tinker terpisah dengan hasil sama-sama "TIDAK KETEMU".

**Akar penyebab, 2 bug yang saling memperparah:**

1. `cari()` baris 28 dan `store()` baris 66: `User::where('username', $data['nik'])->first()` — TIDAK memakai `withoutGlobalScopes()`, padahal `User` pakai `BelongsToTenant` dan `TenantScope` otomatis membatasi query ke lembaga/yayasan aktor. Bandingkan dengan `OrangTuaController::store()` baris 77 yang BENAR: `User::withoutGlobalScopes()->where('username', $data['nik'])->first()`.
2. `AkunOrangTuaGenerator::buat()` (`app/Services/AkunOrangTuaGenerator.php`, `User::create([...])` sekitar baris 40-46) TIDAK PERNAH mengisi `yayasan_id` pada `User` yang dibuat — parameter `$yayasanId` dihitung dengan benar (dipakai untuk `Person` lewat `CreatePersonAction`) tapi tidak diteruskan ke `User::create()`. Akibatnya `User.lembaga_id = null` DAN `User.yayasan_id = null` untuk SETIAP orang tua yang dibuat lewat generator ini — dikonfirmasi pada data nyata di `pintera_sdm_app`.

Kombinasi ini membuat pencarian gagal bahkan di skenario TERLONGGAR: `TenantScope`'s "pool pattern" (agregasi via `orWhere('yayasan_id', ...)` untuk model yang `$fillable`-nya punya `yayasan_id`, yang `User` penuhi) tidak bisa menyelamatkan karena baris target itu SENDIRI `yayasan_id`-nya `NULL` — tidak ada nilai untuk dicocokkan.

**Dampak turunan yang sudah teramati**: karena pencarian selalu gagal, `store()` (tanpa `orang_tua_id`) selalu masuk cabang "buat baru", lalu backstop `Rule::unique('users', 'username')` (baris 87, query MENTAH ke tabel tanpa scope, jadi BENAR mendeteksi bentrok) menghentikan submit dengan pesan generik "NIK sudah terdaftar" — admin BUNTU, tidak pernah diarahkan ke jalur tautkan yang benar.

**Fix — 2 bagian, WAJIB keduanya:**

**3a. `SiswaOrangTuaController::cari()` dan `store()`** — tambahkan `withoutGlobalScopes()`, ikuti pola PERSIS `OrangTuaController::store()` baris 77:
```php
// cari(), baris 28:
$user = User::withoutGlobalScopes()->where('username', $data['nik'])->first();

// store(), baris 66:
$existingUser = User::withoutGlobalScopes()->where('username', $data['nik'])->first();
```

**3b. `AkunOrangTuaGenerator::buat()`** — teruskan `$yayasanId` yang sudah dihitung ke `User::create()`:
```php
$yayasanId ??= auth()->user()?->yayasan_id ?? auth()->user()?->lembaga?->yayasan_id ?? Yayasan::first()?->id ?? Yayasan::factory()->create()->id;

$person = app(CreatePersonAction::class)->execute(
    identityData: [...],
    lembagaId: null,
    actingYayasanId: $yayasanId,
);

$user = User::create([
    'name' => $namaLengkap,
    'email' => null,
    'username' => $nik,
    'password' => Hash::make($nik),
    'lembaga_id' => null,
    'yayasan_id' => $yayasanId,   // <-- baris baru
    'email_verified_at' => null,
    'is_active' => true,
    'must_change_password' => true,
]);
```

**Catatan cakupan bypass 3a**: `withoutGlobalScopes()` di `cari()`/`store()` sengaja TANPA batas yayasan sama sekali (persis pola `OrangTuaController::store()` yang dijadikan rujukan) — ini AMAN karena tujuannya cuma "apakah NIK ini sudah terdaftar ke akun manapun di sistem", bukan menampilkan data. Kalau ketemu tapi beda yayasan dari siswa yang sedang diedit, hasil `cari()` tetap ditampilkan sebagai "found" (perilaku tidak berubah dari desain saat ini — spec ini tidak menambah validasi cross-yayasan baru untuk kasus ini, karena `store()` sudah py guard terpisah lewat cek `$siswa->lembaga?->yayasan_id` saat generate — cek ulang saat implementasi apakah perlu tambahan guard eksplisit untuk mencegah tautkan siswa yayasan A ke orang tua yang NIK-nya kebetulan cocok dengan user di yayasan B; kalau `PersonAlreadyExistsException`/uniqueness constraint sudah mencegah 2 `Person` beda yayasan pakai NIK sama, ini tidak mungkin terjadi secara alami — verifikasi asumsi ini saat implementasi, bukan tebakan).

**Peringatan test existing — false negative tersamar**: `tests/Feature/Admin/SiswaOrangTuaLinkingTest.php` baris 24-40 (`'finds an existing orang tua by nik via the cari endpoint'`) SUDAH ADA dan SAAT INI LULUS meski bug belum diperbaiki — TAPI itu bukan bukti fitur ini benar. Fixture-nya (baris 32) sengaja/tidak sengaja membuat `User::factory()->create(['lembaga_id' => $lembagaSama->id])` dengan `lembaga_id` yang SAMA PERSIS dengan lembaga milik manager yang login — jadi `TenantScope` default (`where('lembaga_id', actor->lembaga_id)`) kebetulan cocok TANPA butuh `withoutGlobalScopes()` sama sekali. Test ini TIDAK merepresentasikan kondisi produksi nyata, di mana `User` orang tua dibuat lewat `AkunOrangTuaGenerator` dengan `lembaga_id = null` (Bug #3b) — kondisi yang justru SELALU gagal di `TenantScope` manapun. **WAJIB** saat implementasi: (a) ubah fixture test ini agar realistis — `User` dibuat dengan `lembaga_id: null` (meniru kondisi nyata SEBELUM fix 3b, atau dengan `yayasan_id` terisi meniru kondisi SETELAH fix 3b) DAN/ATAU login sebagai manager di lembaga BEDA (yayasan sama) dari lembaga tempat `User` dibuat, supaya test ini benar-benar gagal tanpa fix `withoutGlobalScopes()` (3a) sebelum diperbaiki; (b) tambah test baru terpisah untuk skenario yayasan mode "Semua Lembaga" (belum ada sama sekali di file ini).

**Acceptance criteria**:
1. Admin lembaga-scope A: siswa baru didaftarkan, orang tuanya SUDAH terdaftar (siswa lain di lembaga sama) → `cari()` dengan NIK yang benar mengembalikan `found: true` dengan data orang tua yang benar.
2. Yayasan mode "Semua Lembaga": orang tua terdaftar lewat siswa di lembaga X (yayasan sama) → admin di lembaga Y (yayasan sama) mencari NIK yang sama → `found: true` (orang tua level yayasan, bukan lembaga — sesuai `Person::YayasanScope`).
3. Orang tua baru dibuat lewat `AkunOrangTuaGenerator::buat()` → `User.yayasan_id` terisi sesuai yayasan target (BUKAN null).
4. Skenario yang tadinya buntu (submit `store()` tanpa `orang_tua_id`, NIK sudah ada) sekarang mengembalikan error yang tepat SEBELUM submit (`cari()` sudah `found: true` di frontend) — bukan gagal diam-diam di backend.
5. Test regresi: `store()` dengan NIK benar-benar baru (belum ada di sistem sama sekali) tetap berhasil membuat orang tua baru seperti sebelumnya.
6. Test baru persis skenario tinker verifikasi #4 dan #5 dari investigasi (lembaga-scope DAN yayasan "Semua Lembaga" mode) — assert `cari()` mengembalikan `found: true`, bukan `false`.

---

## Di Luar Scope / Backlog Terpisah (JANGAN dikerjakan di plan ini)

1. **UI admin untuk `MergePersonsAction`** — mekanisme merge Person sudah ada & teruji dari proyek `identity-v1-person-master-entity`, tapi tidak ada UI pemicu. Spec ini murni MENCEGAH duplikat baru terjadi (via fix Bug #3), bukan membangun alat membersihkan duplikat historis yang mungkin sudah terlanjur ada di data produksi. Kalau audit data produksi nanti menemukan duplikat `Person`/`User` orang tua akibat bug ini (sebelum fix), itu backlog terpisah (data cleanup, bukan perubahan kode).
2. **Reposisi halaman `OrangTuaController` index sebagai "jalur sekunder"** — tidak ada perubahan kode untuk ini, murni keputusan konteks (lihat bagian Keputusan Bisnis) yang menjelaskan prioritas relatif Bug #1/#2 (index, jalur sekunder) vs Bug #3 (siswa, jalur utama).
3. **Method lain di `OrangTuaController`/`SiswaController`** yang tidak disebut eksplisit di atas (mis. `destroy()`, validasi NIS di `SiswaController::validateSiswa()`) — sudah diverifikasi TIDAK bermasalah selama investigasi, tidak disentuh.

---

## Ringkasan Test yang Wajib Ditambahkan

| Bug | File test (buat baru atau tambah ke existing — cek dulu saat plan ditulis) |
|---|---|
| #1 | Feature test `OrangTuaController@index` — assert filter `anak=ada`/`anak=belum` server-side benar setelah data berubah via jalur Siswa |
| #2 | Feature test `OrangTuaController@index` — assert `siswa_count` ter-scope ke lembaga/yayasan aktor, `edit()` tetap lintas lembaga |
| #3 | Tambah ke `tests/Feature/Admin/SiswaOrangTuaLinkingTest.php` (file SUDAH ADA, jangan buat baru) — assert `cari()` ketemu di lembaga-scope DAN yayasan "Semua Lembaga" mode; test `AkunOrangTuaGenerator` — assert `User.yayasan_id` terisi |

Regresi wajib: jalankan test existing untuk `OrangTuaControllerTest`, `SiswaControllerTest`, `tests/Feature/Admin/SiswaOrangTuaLinkingTest.php`, `AkunOrangTuaGenerator`-related tests — pastikan 0 regresi.
