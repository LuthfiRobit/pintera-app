# Kickoff: Validasi Scope Backend & Kejujuran Wording — Menu Kelas

**Base commit**: `2215aa0f` (`docs(kelas): implementation plan validasi scope backend & wording`)
**Branch**: `rbac-v2` (tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit menu "Kelas" (lanjutan pola audit Tahun Ajaran) awalnya saya labeli "murni frontend" — **user mengoreksi dan benar**: ada 2 gap BACKEND nyata di sini, beda dari Tahun Ajaran yang backend-nya sudah sempurna:

1. **`KelasController::create()` (GET) tidak divalidasi** — `store()` (POST) sudah benar menolak kalau yayasan-scope belum switch lembaga, tapi `create()` yang menampilkan formnya sama sekali tidak mengecek, jadi form selalu terbuka meski submit-nya pasti akan ditolak nanti.
2. **Dropdown Tahun Ajaran/Wali Kelas/Pola Jam di `create()`/`edit()` ikut `TenantScope` AMBIEN** (berbasis scope aktor login), BUKAN di-scope eksplisit ke lembaga TARGET (lembaga yang akan dipakai kelas baru, atau lembaga pemilik kelas yang sedang diedit). Di mode "Semua Lembaga", ini bikin dropdown menampilkan opsi campur dari banyak lembaga tanpa peringatan.

`CreateKelasAction`/`UpdateKelasAction` TETAP AMAN (sudah 404 kalau kombinasi lembaga tidak cocok) — jadi TIDAK ADA risiko korupsi data, hanya UX membingungkan dan submit yang pasti gagal tanpa peringatan di muka. 3 gap FRONTEND tambahan (badge scope, kolom Lembaga di tabel, label filter Tahun Ajaran) mengikuti pola yang sudah dipasang di Tahun Ajaran/Karyawan/Guru.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-08-kelas-scope-wording-audit.md` — spec lengkap, 6 item (3 backend A.1-A.3, 3 frontend B.1-B.3), kode current-vs-fix konkret.
2. `.agents/plans/2026-09-08-kelas-scope-wording-audit.md` — 6 task TDD, kode lengkap tiap step, sudah self-review.
3. `.agents/plans/2026-09-08-tahun-ajaran-scope-wording-audit.md` bagian Task 1 — referensi konkret pola `scopeHeaderData()` yang SAMA PERSIS direplikasi di sini (beda konteks: Kelas TIDAK butuh soft-warning inline seperti modal Tahun Ajaran karena `create()` Kelas adalah halaman TERPISAH yang bisa di-guard langsung di backend).
4. `.ai/rules/index.md` lalu file rule yang glob-nya cocok (`controllers.md`, `views.md`, `tests.md`).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **`CreateKelasAction`/`UpdateKelasAction` TIDAK BOLEH disentuh** — keduanya sudah benar. Plan ini murni mencegah admin SAMPAI DI TITIK bisa memilih opsi yang pasti akan ditolak Action itu.
- **A.2 (`create()`) vs A.3 (`edit()`) pakai sumber lembaga yang BEDA** — `create()` pakai `resolveActiveLembagaId($request->user())` (lembaga yang SEDANG di-switch aktor), sedangkan `edit()` pakai `$kelas->lembaga_id` (lembaga PEMILIK record, TETAP walau aktor sedang mode "Semua Lembaga"). JANGAN disamakan keduanya — kalau `edit()` salah pakai `resolveActiveLembagaId()`, dropdown-nya akan KOSONG TOTAL saat aktor yayasan-scope membuka edit kelas dalam mode "Semua Lembaga" (karena `resolveActiveLembagaId()` akan `null`, lalu `where('lembaga_id', null)` tidak match apa pun).
- **Badge di halaman create SELALU brand color + nama lembaga, TIDAK PERNAH varian ungu "Semua Lembaga"** — guard A.1 menjamin ini. Kalau menemukan diri menulis logic percabangan warna ungu di `create.blade.php`, itu tanda ada yang salah, STOP dan cek ulang.
- **Badge di halaman edit pakai `$kelas->lembaga->nama`, BUKAN `$activeLembaga`** — beda dari index/create yang pakai `$activeLembaga`. Konsisten dengan keputusan A.3 di atas.
- **`_daftar.blade.php` adalah partial AJAX yang di-reload TERPISAH setiap filter berubah** — `scopeHeaderData()` WAJIB dipanggil di KEDUA cabang `index()` (ajax dan non-ajax), bukan cuma cabang halaman penuh. Kalau terlewat, kolom "Lembaga" (B.2) akan salah/hilang setelah user mengubah filter tanpa reload halaman penuh.
- **`colspan` baris empty-state tabel WAJIB dihitung dinamis** (`4` atau `5` tergantung `$isYayasan && !$activeLembaga`) — JANGAN di-hardcode ke salah satu angka.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **Test home**: SEMUA test baru masuk ke `tests/Feature/Admin/KelasCrudTest.php` (file existing, Pest function-style, sudah punya helper `actingAsKelasManager(Lembaga $lembaga): User` untuk aktor LEMBAGA-scope). Untuk aktor YAYASAN-scope, plan sudah menyediakan kode setup inline lengkap di tiap test (ikuti pola test "menolak actor yayasan dengan active_lembaga_id stale..." yang SUDAH ADA di file yang sama, baris 214-236, sebagai referensi gaya) — JANGAN bikin file test baru.
- **Test lama "offers only guru belonging to the current lembaga as wali kelas options" (baris 48-70 file yang sama) SUDAH ADA dan SEBELUMNYA lolos KEBETULAN** lewat `TenantScope` ambien (aktor test itu lembaga-scope) — bukan lewat scoping eksplisit A.2 yang baru. Setelah Task 1 selesai, test ini TETAP harus lolos, sekarang lewat jalur scoping eksplisit yang baru. Kalau test ini tiba-tiba gagal setelah Task 1, itu regresi nyata, JANGAN diabaikan.
- **`Fase` model TIDAK pakai `BelongsToTenant`** (referensi kurikulum nasional, sama untuk semua lembaga) — `faseList` di `create()`/`edit()` TIDAK PERNAH di-scope ke lembaga manapun, tetap `Fase::orderBy('urutan')->get()` polos di semua task.
- **Beberapa test di plan berpotensi PASS lebih awal dari yang "seharusnya"** (Task 1 Step 4, Task 5 Step 2/6) — ini SUDAH didokumentasikan eksplisit di step terkait di plan sebagai perilaku normal (sebagian kondisi kebetulan sudah benar lewat TenantScope ambien untuk kasus lembaga-sudah-di-switch). JANGAN bingung, tetap lanjut ke step implementasi.
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 6.

## 5. Instruksi Stop-and-Report

- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode (bukan nomor baris), catat perbedaannya di laporan task.
- **Kalau full suite Task 6 menunjukkan kegagalan DI LUAR modul Kelas** — investigasi dulu apakah terkait perubahan plan ini (seharusnya TIDAK, karena Action tidak disentuh); kalau tidak terkait (mis. seeder demo yang memang sudah dikenal flaky), catat sebagai pre-existing.
- **Kalau ternyata ada test lain (di luar `KelasCrudTest.php`) yang memanggil `KelasController::create()`/`edit()` dengan asumsi signature LAMA** (tanpa `Request $request` di `edit()`, atau tanpa guard di `create()`) — STOP, laporkan, JANGAN diam-diam disesuaikan tanpa mencatatnya, karena bisa jadi tanda ada pemanggil lain yang perlu diperiksa lebih dalam.

## 6. Catatan Serah Terima

- Audit ini adalah HASIL KOREKSI user atas kesimpulan awal saya yang salah ("murni frontend") — spec & plan SUDAH mengakomodasi koreksi itu sepenuhnya, tidak ada draft lama yang perlu diperhatikan.
- User secara eksplisit meminta kickoff (bukan eksekusi inline) — kerjakan mandiri sampai selesai atau sampai benar-benar BLOCKED butuh keputusan user.
- Setelah Task 6 selesai, JANGAN merge branch `rbac-v2` ke `main` — keputusan terpisah milik user.
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (lihat Task 6 Step 4) — permintaan tambahan kalau user memang menghendaki, bukan bagian implisit kickoff ini.

## 7. Mulai dari mana

Mulai dari **Task 1** (`scopeHeaderData()` + guard `create()` + scoping A.2) di `.agents/plans/2026-09-08-kelas-scope-wording-audit.md`, kerjakan berurutan Task 1 → 6. Task 1 → 2 → 3 WAJIB berurutan (Task 2 dan 3 sama-sama butuh `scopeHeaderData()` dari Task 1; Task 3 mengubah `index()` yang independen dari Task 2's `edit()`, boleh ditukar urutannya kalau ada alasan praktis, tapi TIDAK dengan Task 1). Task 4 butuh Task 1+2+3 selesai (variabel `$isYayasan`/`$activeLembaga` harus sudah tersedia di ketiga method). Task 5 butuh Task 3 selesai (eager-load `lembaga` di `index()`).
