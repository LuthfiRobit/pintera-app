# Kickoff: Validasi Scope Backend & Kejujuran Wording — Menu Mata Pelajaran

**Base commit**: `8fbc081b` (`docs(mata-pelajaran): implementation plan validasi scope backend & wording`)
**Branch**: `rbac-v2` (tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit menu "Mata Pelajaran" (lanjutan pola Tahun Ajaran → Kelas → Mata Pelajaran dari `.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md`) menemukan celah backend yang **LEBIH SERIUS** dari 2 menu sebelumnya:

1. **`store()` baca `session('active_lembaga_id')` mentah** untuk aktor yayasan-scope, TANPA validasi ulang bahwa lembaga itu masih milik yayasan aktor. `MataPelajaranController` adalah SATU-SATUNYA controller di antara yang sudah diaudit sesi ini yang TIDAK PERNAH memakai `ResolveLembagaScopeTrait` sama sekali — Kelas, TahunAjaran, Karyawan, Guru semuanya sudah pakai. `CreateMataPelajaranAction` mempercayai `lembaga_id` mentah-mentah TANPA lapisan pertahanan kedua (beda dari `CreateKelasAction` yang selalu 404 kalau kombinasi lembaga salah) — jadi kalau session sempat stale (skenario yang SUDAH punya test eksplisit untuk Kelas), mata pelajaran BENAR-BENAR bisa tersimpan di lembaga luar yayasan aktor. Ini bukan cuma UX membingungkan seperti 2 menu sebelumnya — ini kebocoran data lintas-yayasan nyata.
2. **`create()` (GET) tidak divalidasi** sebelum render form — pola sama seperti bug Kelas yang sudah diperbaiki.
3. **`$isPaud` salah sumber** — diambil dari `auth()->user()->lembaga` (lembaga MILIK AKTOR sendiri, yang untuk aktor yayasan-scope SELALU `null`), bukan dari lembaga yang sedang AKTIF/di-switch. Akibatnya banner "Catatan untuk PAUD" tidak pernah muncul untuk aktor yayasan-scope, bahkan saat mereka sedang mengelola lembaga PAUD.

2 gap frontend tambahan (badge scope, kolom Lembaga di tabel) mengikuti pola yang sudah dipasang di Kelas/Tahun Ajaran/Karyawan/Guru.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-08-mata-pelajaran-scope-wording-audit.md` — spec lengkap, 5 item (3 backend A.1-A.3, 2 frontend B.1-B.2), kode current-vs-fix konkret.
2. `.agents/plans/2026-09-08-mata-pelajaran-scope-wording-audit.md` — 6 task TDD, kode lengkap tiap step, sudah self-review.
3. `.agents/plans/2026-09-08-kelas-scope-wording-audit.md` Task 1 — referensi konkret pola guard `create()` + `scopeHeaderData()` yang direplikasi di sini.
4. `.ai/rules/index.md` lalu file rule yang glob-nya cocok (`controllers.md`, `views.md`, `tests.md`).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **A.1 adalah PRIORITAS TERTINGGI di seluruh plan ini** — ini satu-satunya temuan di rangkaian audit Tahun Ajaran/Kelas/Mata Pelajaran yang benar-benar berupa celah keamanan (bukan cuma UX), kerjakan Task 1 dengan HATI-HATI EKSTRA dan JANGAN lewati test Step 1 (skenario session stale) — kalau test itu sampai tidak ditulis/dijalankan, celah ini bisa lolos tanpa terverifikasi.
- **`resolveActiveLembagaId()` (BUKAN `resolveLembagaId()` trait yang sama)** dipakai di SEMUA titik (guard A.2, fix A.1, `scopeHeaderData()`, fix A.3) — method ini TIDAK PERNAH `abort()`, cukup mengembalikan `null` kalau tidak valid/tidak ada lembaga aktif, dan SUDAH built-in memvalidasi kepemilikan yayasan untuk kasus yayasan-scope. `MataPelajaranController` BELUM PERNAH pakai trait ini — Task 1 Step 5 WAJIB menambahkan `use ResolveLembagaScopeTrait;` ke badan class, JANGAN lupa.
- **`$isPaud` (A.3) WAJIB dihitung terpisah dari `scopeHeaderData()`**, JANGAN reuse `activeLembaga`-nya — `scopeHeaderData()`'s `activeLembaga` HANYA terisi untuk aktor yayasan-scope (`null` untuk lembaga-scope by design, karena badge memang tidak relevan untuk mereka). `$isPaud` harus benar untuk KEDUA jenis aktor, jadi pakai `resolveActiveLembagaId()` + `Lembaga::find()` langsung, terpisah dari pemanggilan `scopeHeaderData()`.
- **`UpdateMataPelajaranAction`/`update()` TIDAK BOLEH disentuh** — sudah aman (`lembaga_id` immutable, TenantScope melindungi route-model-binding).
- **Badge di halaman create SELALU brand color + nama lembaga, TIDAK PERNAH varian ungu** — guard A.2 menjamin ini. Badge di halaman edit pakai `$mataPelajaran->lembaga->nama`, BUKAN `$activeLembaga`.
- **`_daftar.blade.php` adalah partial AJAX yang di-reload TERPISAH** — `scopeHeaderData()` WAJIB dipanggil di KEDUA cabang `index()` (ajax dan non-ajax).
- **`colSpan` baris empty-state tabel WAJIB dihitung dinamis** (`7` atau `8`) — JANGAN di-hardcode.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **Test home**: SEMUA test baru masuk ke `tests/Feature/Admin/MataPelajaranCrudTest.php` (file existing, Pest function-style, sudah punya helper `actingAsMataPelajaranManager(Lembaga $lembaga): User` untuk aktor LEMBAGA-scope). Untuk aktor YAYASAN-scope dan skenario stale-session, plan sudah menyediakan kode setup inline lengkap di tiap test — JANGAN bikin file test baru.
- **2 test PAUD lama SUDAH ADA di file yang sama (baris 158-180) dan HANYA menguji aktor LEMBAGA-scope** — test itu SEBELUMNYA lolos karena `auth()->user()->lembaga` KEBETULAN benar untuk lembaga-scope (mereka memang terikat 1 lembaga tetap). Setelah Task 2, test ini TETAP harus lolos lewat jalur `resolveActiveLembagaId()` yang baru — kalau tiba-tiba gagal, itu regresi nyata.
- **Route mengarah ke controller di namespace `Lembaga\Akademik`, TAPI nama route tetap `admin.mata-pelajaran.*`** — ini konvensi existing yang sengaja begitu (terdaftar di `routes/admin/akademik-master.php`), JANGAN diubah/dianggap salah.
- **Form Mata Pelajaran TIDAK punya field dropdown lintas-model apa pun** (beda dari Kelas yang punya Tahun Ajaran/Wali Kelas/Pola Jam) — jadi TIDAK ADA task setara "scoping dropdown create()/edit()" di plan ini. Kalau menemukan diri mencari task semacam itu, berarti salah asumsi — plan ini memang cuma 6 task, bukan kurang lengkap.
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 6.

## 5. Instruksi Stop-and-Report

- **Kalau Task 1 Step 2 (test stale-session) TERNYATA sudah PASS SEBELUM fix diterapkan** — STOP, ini artinya asumsi audit soal celah A.1 SALAH (mungkin ada guard tersembunyi yang belum terbaca), JANGAN lanjut ke Step 5 tanpa investigasi dulu kenapa test itu sudah hijau padahal kodenya belum diubah.
- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode, catat perbedaannya di laporan task.
- **Kalau full suite Task 6 menunjukkan kegagalan DI LUAR modul Mata Pelajaran** — investigasi dulu apakah terkait perubahan plan ini (seharusnya TIDAK); kalau tidak terkait (mis. seeder demo yang memang sudah dikenal flaky), catat sebagai pre-existing.

## 6. Catatan Serah Terima

- Audit ini melanjutkan pola Tahun Ajaran → Kelas → Mata Pelajaran, TAPI temuan A.1 di sini kelasnya BEDA (celah keamanan nyata, bukan cuma UX kurang informatif) — treat dengan urgensi lebih tinggi dari 2 menu sebelumnya.
- User secara eksplisit meminta kickoff (bukan eksekusi inline) — kerjakan mandiri sampai selesai atau sampai benar-benar BLOCKED butuh keputusan user.
- Setelah Task 6 selesai, JANGAN merge branch `rbac-v2` ke `main` — keputusan terpisah milik user.
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (lihat Task 6 Step 4) — permintaan tambahan kalau user memang menghendaki.

## 7. Mulai dari mana

Mulai dari **Task 1** (fix A.1 store() + guard A.2 create()) di `.agents/plans/2026-09-08-mata-pelajaran-scope-wording-audit.md`, kerjakan berurutan Task 1 → 6. Task 1 → 2 → 3 WAJIB berurutan (Task 2 dan 3 sama-sama butuh `scopeHeaderData()` dari Task 1). Task 4 butuh Task 1+2+3 selesai (variabel `$isYayasan`/`$activeLembaga` harus sudah tersedia di ketiga method). Task 5 butuh Task 2 selesai (eager-load `lembaga` di `index()`).
