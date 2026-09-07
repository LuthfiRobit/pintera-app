# Handoff Log: Perbaikan Tautan Orang Tua-Siswa & Konsistensi Identitas Person

> **Dokumen Terkait**:
> - Spec: [`.agents/specs/2026-09-07-orang-tua-siswa-person-tautan.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-07-orang-tua-siswa-person-tautan.md)
> - Implementation Plan: [`.agents/plans/2026-09-07-orang-tua-siswa-person-tautan.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-07-orang-tua-siswa-person-tautan.md)
> - Tanggal Selesai: 7 September 2026
> - Branch: `rbac-v2` (belum di-merge ke `main`)
> - Base commit sebelum plan mulai: `53e8b570`

---

## 1. Apa yang Dikerjakan

Menuntaskan perbaikan 4 bug fungsional pada relasi Orang Tua-Siswa-Person di aplikasi Pintera melalui 3 task fungsional mandiri dengan alur TDD ketat (red-green-refactor) dan 1 task penutup.

### Ringkasan Per Task & Commit Hash:

1. **Task 1: Bug #3 — Pencarian NIK Orang Tua Gagal Total (Prioritas Tertinggi)** (`abbf4aa5`)
   - **Akar Masalah**: `SiswaOrangTuaController::cari()` dan `store()` mencari User dengan `User::where('username', $data['nik'])->first()` tanpa `withoutGlobalScopes()`. Pada saat yang sama, `AkunOrangTuaGenerator::buat()` tidak pernah mengisi `yayasan_id` pada `User` yang dibuat (`User.lembaga_id = null` dan `User.yayasan_id = null`). Kombinasi ini menyebabkan `TenantScope` memblokir pencarian NIK orang tua untuk semua level scope (termasuk yayasan mode "Semua Lembaga").
   - **TDD / Fixture**: Memperbaiki 2 fixture test lama di `tests/Feature/Admin/SiswaOrangTuaLinkingTest.php` yang false-negative-tersamar (sebelumnya fixture kebetulan membuat akun dengan `lembaga_id` yang sama dengan aktor sehingga lolos palsu). Dikonfirmasi KEDUANYA merah sebelum fix.
   - **Fix 3a**: Menambahkan `User::withoutGlobalScopes()->where('username', $data['nik'])->first()` pada method `cari()` dan `store()` di `SiswaOrangTuaController.php`.
   - **Fix 3b**: Menambahkan `'yayasan_id' => $yayasanId,` pada array `User::create()` di `AkunOrangTuaGenerator.php`.
   - **Test & Regresi**: Menambahkan test baru untuk skenario yayasan mode "Semua Lembaga" via lembaga beda; menambahkan assersi `yayasan_id` di `AkunOrangTuaGeneratorTest.php`. Seluruh 15 test di `SiswaOrangTuaLinkingTest` lulus (45 assertions), test unit/feature `AkunOrangTuaGenerator` lulus (37 assertions).

2. **Task 2: Bug #2 — Over-Count `siswa_count` Lintas Lembaga di Index** (`ae1977ee`)
   - **Akar Masalah**: Relasi `OrangTua::siswa()` menggunakan `withoutGlobalScopes()` di level definisi relasi. Akibatnya, `withCount('siswa')` polos di `OrangTuaController::index()` menghitung seluruh anak dari orang tua tersebut lintas lembaga dan yayasan, sehingga admin lembaga-scope melihat jumlah anak yang bukan wewenangnya.
   - **Fix Query**: Mengganti `withCount('siswa')` menjadi closure ter-scope yang memeriksa `widestScopeLevel()`, `$activeLembagaId`, dan `$lembagaIdsYayasan` (identik dengan filter visibilitas orang tua yang sudah ada).
   - **Fix Model & Factory**:
     - `database/factories/OrangTuaFactory.php`: Menambahkan `$orangTua->yayasan_id` pada list `unset` di `afterMaking` agar `OrangTua::factory()->create(['yayasan_id' => ...])` mendelegasikan `yayasan_id` ke `Person` tanpa mencoba meng-insert kolom `yayasan_id` ke tabel `orang_tua`.
     - `app/Models/OrangTua.php`: Memperbaiki `scopeOrderByNama` agar `select('orang_tua.*')` hanya dipanggil jika `$query->getQuery()->columns` masih kosong, mencegah ditimpanya kolom agregat `siswa_count` hasil `withCount()`.
   - **Test**: `tests/Feature/Admin/OrangTuaControllerTest.php` dibuat dengan test `scopes siswa_count on the index to the acting lembaga...` (terbukti RED: nilai 2 vs 1 sebelum fix, GREEN setelah fix). `edit()` diverifikasi tidak disentuh dan tetap menampilkan anak lintas lembaga by design. Seluruh 22 test di `OrangTuaCrudTest` + `OrangTuaControllerTest` lulus (86 assertions).

3. **Task 3: Bug #1 — Filter "Ada Anak"/"Belum Ada Anak" Server-Side, Bukan Snapshot Client Basi** (`f7375241`)
   - **Akar Masalah**: Filter "Ada Anak"/"Belum Ada Anak" di `index.blade.php` murni berupa filter array JavaScript di Alpine.js atas snapshot data statis saat halaman pertama kali dimuat. Karena alur penautan anak dilakukan dari halaman Siswa, perpindahan tab tanpa reload menyebabkan data filter basi.
   - **Fix Controller**: `OrangTuaController::index()` membaca query param `?anak=ada|belum`, menghitung `$totalAda` dan `$totalBelum` dari koleksi sebelum pemfilteran (agar badge angka tetap konsisten), lalu memfilter `$orangTuaList` dengan `->filter()->values()`.
   - **Fix View**: Mengubah tombol "Semua", "Ada Anak", dan "Belum Ada Anak" di `resources/views/admin/orang-tua/index.blade.php` menjadi elemen `<a>` navigasi server-side dengan query param, sambil mempertahankan visual class reaktif Alpine.js. Nilai awal `activeFilter` diinisialisasi dari `$anakFilter`. Filter "Aktif"/"Non-Aktif" tetap dipertahankan client-side karena tidak basi.
   - **Test**: Menambahkan test `filters the orang tua index by anak query param without relying on a stale client snapshot` dan `renders server-side filter links and badge counts on the orang tua index view` di `OrangTuaControllerTest.php`. Keduanya lulus (17 assertions).

4. **Task 4: Penutup — Full Suite, Pint, Handoff Log, Roadmap** (commit ini)
   - Full test suite: 2946 passed, 4 known pre-existing seeder failures (PPDB/presensi day-of-week). Zero regressions.
   - Pint: format code compliant (`vendor/bin/pint --dirty --format agent` passed).

---

## 2. Keputusan Penting yang Diambil

1. **Urutan Pendaftaran Bisnis: Siswa Dulu, Tautkan Orang Tua Kemudian**
   - Dikonfirmasi melalui keputusan bisnis resmi: pendaftaran siswa adalah peristiwa utama. Halaman Data Induk Orang Tua berdiri sendiri berfungsi sebagai alat kelola profil sekunder. Karena itu Task 1 (SiswaOrangTuaController) dijadikan prioritas tertinggi karena merupakan pintu gerbang pendaftaran keluarga utama.
2. **`OrangTuaController::edit()` Sengaja Tidak Diubah**
   - Halaman detail/edit profil orang tua sengaja tetap memuat seluruh anak lintas lembaga (`->withoutGlobalScopes()`), karena `Person` dan `OrangTua` adalah entitas tingkat yayasan (`Person::YayasanScope`). Yang salah hanyalah angka ringkasan `siswa_count` di halaman indeks lembaga, yang kini sudah diperbaiki di Task 2.
3. **Penyesuaian Teknis Task 3 (PHP Collection Filter vs SQL Having)**
   - Pendekatan filter `anak=ada|belum` diterapkan pada level collection PHP setelah query scope selesai dijalankan. Hal ini memungkinkan badge total ("Ada Anak: X", "Belum Ada Anak: Y") dihitung dari set lengkap sebelum filter diaplikasikan, serta menghindari kerumitan `HAVING` pada subset data yang sudah ditarik utuh untuk kebutuhan tabel.
4. **Pencegahan Overwrite Columns di `OrangTua::scopeOrderByNama()`**
   - Ditemukan bahwa pemanggilan `->select('orang_tua.*')` di dalam `scopeOrderByNama` secara default menghapus kolom agregat yang dihasilkan oleh `withCount()`. Penambahan guard `if (empty($query->getQuery()->columns))` memastikan `orang_tua.*` tetap diprioritaskan saat join dengan `persons` tanpa menghapus subquery yang sudah didaftarkan sebelumnya.
5. **Perbaikan `OrangTuaFactory` untuk Delegasi `yayasan_id`**
   - `OrangTuaFactory` menerima `yayasan_id` di `definition()` tetapi lupa di-unset di `afterMaking`, menyebabkan Eloquent mencoba meng-insert ke tabel `orang_tua`. Penambahan `$orangTua->yayasan_id` ke `unset` menyelesaikan masalah ini secara bersih tanpa mengubah skema tabel.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Hasil Full Test Suite**:
   ```
   php artisan test --compact
   Tests:    4 failed, 2946 passed (7984 assertions)
   ```
   4 test yang gagal adalah known pre-existing flaky seeder demo (`M3DemoDataSeederTest` x2, `PresensiSeederTest`, `SesiPembelajaranSeederTest`), tidak ada kaitan dengan modul identitas Orang Tua/Siswa/Person.
2. **Di Luar Scope (TIDAK Dikerjakan)**:
   - **UI admin untuk `MergePersonsAction`**: Mekanisme merge person sudah ada di backend (`MergePersonsAction.php`), tetapi UI admin pemicu sengaja tidak dibangun di task ini sesuai batasan spec. Pencegahan duplikat telah dilakukan di titik pembuatan akun baru.
3. **Status Git**:
   - Branch: `rbac-v2`
   - Belum di-merge ke `main` (keputusan merge berada di tangan user/owner).
   - Working tree bersih dan siap di-review.
