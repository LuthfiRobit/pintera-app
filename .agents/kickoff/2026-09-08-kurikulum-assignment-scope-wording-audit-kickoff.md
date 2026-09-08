# Kickoff: Perbaikan Kritis & Kejujuran Wording — Menu Kurikulum Assignment

**Base commit**: `bb98d732` (`docs(kurikulum-assignment): implementation plan perbaikan kritis & wording`)
**Branch**: `akademik-v2` (SETARA `rbac-v2`, tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit menu "Pengaturan Kurikulum" (Kurikulum Assignment) — user sendiri menilai halaman ini "sangat ambigu" sebelum audit dimulai, dan audit membenarkan itu: halaman ini punya 2 sumbu scope yang tercampur (scope AKTOR: platform/yayasan/lembaga, DAN scope ASSIGNMENT: global "Platform Default" vs spesifik 1 lembaga). Ditemukan:

1. **🔴 KRITIS — crash 500** untuk aktor platform-scope membuka halaman edit assignment APA PUN. `edit()` tidak mengirim `$lembagaList`, tapi `_form.blade.php` unconditional `@foreach ($lembagaList...)` untuk platform. TIDAK ADA test yang pernah mengeksekusi jalur ini — bug ini baru diketahui lewat pembacaan kode langsung, belum pernah dilaporkan pengguna nyata (kemungkinan platform-scope jarang dipakai untuk edit di produksi).
2. **🔴 `$canManage` di index tidak sinkron dengan backend** — yayasan-scope melihat tombol Edit/Hapus AKTIF di baris "Platform Default" (global), padahal backend `authorizeExistingAssignmentScope()` pasti `abort(403)`.
3. **🟡 `store()` raw `abort(422)`** untuk yayasan-scope tanpa lembaga aktif — bukan redirect ramah seperti pola yang sudah diperbaiki di Kelas/TahunAjaran/MataPelajaran.
4. 3 gap wording (badge create, guard create, catatan index selalu agregat).

**Keputusan produk yang SUDAH DIKONFIRMASI user secara eksplisit**: assignment global TETAP eksklusif Platform Admin — backend TIDAK diubah wewenangnya, cuma VIEW yang disinkronkan ke backend yang sudah benar.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-08-kurikulum-assignment-scope-wording-audit.md` — spec lengkap, 6 item, kode current-vs-fix konkret, plus analisis blast-radius eksplisit (dikonfirmasi terkurung di Controller+View+Test).
2. `.agents/plans/2026-09-08-kurikulum-assignment-scope-wording-audit.md` — 6 task TDD, kode lengkap tiap step, sudah self-review.
3. `.agents/plans/2026-09-08-kelas-scope-wording-audit.md` — referensi pola guard `create()`/`store()` yang direplikasi di sini (Task 3, 4).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **`KurikulumAssignmentResolver`, `CreateKelasAction`, `AssignKurikulumAction`, `UpdateKurikulumAssignmentAction`, model `KurikulumAssignment`, skema tabel TIDAK BOLEH disentuh** — blast-radius dikonfirmasi eksplisit terkurung di `KurikulumAssignmentController` + 3 Blade view + test file miliknya sendiri. Task 6 Step 2 secara khusus menjalankan regresi modul Kelas untuk MEMBUKTIKAN ini, bukan cuma percaya klaim spec.
- **`authorizeExistingAssignmentScope()` (dipakai `edit()`/`update()`/`destroy()`) TIDAK DIUBAH sama sekali** — Task 2 menambah method BARU `canManageAssignment()` (return bool, mirror kondisi yang SAMA) khusus untuk `index()`'s tampilan tombol, BUKAN refactor method existing.
- **Task 1 (crash fix) SENGAJA dibuat task TERKECIL & TERPISAH dari perbaikan wording lain** (teks "lembaga yang sedang aktif" BARU disempurnakan di Task 4, bukan Task 1) — supaya fix produksi paling urgent ini bisa di-review/di-deploy TERPISAH secepat mungkin tanpa menunggu task wording selesai. JANGAN menggabungkan Task 1 dengan Task 4 meski sama-sama menyentuh `_form.blade.php`.
- **Mode EDIT `_form.blade.php` SELALU read-only untuk "Berlaku Untuk", untuk SEMUA scope aktor termasuk platform** — `edit.blade.php` TIDAK PERLU dan TIDAK BOLEH diubah untuk mengirim `lembagaList`. Kalau menemukan diri berpikir "tinggal tambah lembagaList ke edit() supaya dropdown-nya jalan" — itu SALAH ARAH, baca ulang Keputusan #2 di spec (dropdown di edit itu sendiri menyesatkan karena `lembaga_id` immutable, bukan cuma soal crash).
- **Label "Read-only (Platform)" TIDAK PERLU diubah** — dianalisis di spec (Keputusan #3): setelah `canManageAssignment()` benar, label ini OTOMATIS selalu akurat. JANGAN menambahkan logic percabangan label baru.
- **TIDAK ADA badge "Semua Lembaga vs 1 Lembaga" di index** — index SENGAJA selalu agregat, tidak pernah menyempit walau lembaga aktif di-switch (beda dari Kelas/TahunAjaran/MataPelajaran). Badge cuma di halaman CREATE. Task 5 cuma menambah CATATAN PENJELAS, BUKAN mengubah perilaku query.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **Test home**: SEMUA test baru masuk ke `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (PERHATIKAN path — `Feature/Akademik/`, BUKAN `Feature/Admin/` seperti kebanyakan modul lain). Sudah punya 3 helper: `actingAsKurikulumAssignmentManager(Lembaga $lembaga)` (lembaga-scope), `actingAsYayasanKurikulumManager()` (yayasan-scope), `actingAsPlatformScopeKurikulumManager()` (platform-scope) — JANGAN bikin helper baru, JANGAN bikin file test baru.
- **1 test existing (baris ±195) WAJIB DIUBAH di Task 3** ("yayasan tanpa active_lembaga_id di sesi ditolak..." dari `assertStatus(422)` jadi `assertRedirect()`+`assertSessionHasErrors()`) — plan sudah menyediakan kode pengganti persis.
- **Variabel `isPlatformOrYayasan` DIHAPUS dari `index()`** (diganti `isYayasan`) — nama variabel yang SAMA dipakai di `FaseDefaultMappingController`/`ResyncKurikulumFaseController` TAPI itu instance TERPISAH di file BERBEDA, TIDAK TERKAIT, JANGAN ikut diubah.
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 6.

## 5. Instruksi Stop-and-Report

- **Kalau Task 1 Step 2 (test crash) TERNYATA sudah PASS SEBELUM fix diterapkan** — STOP, ini artinya bug crash yang dilaporkan spec SALAH/sudah tidak ada, JANGAN lanjut tanpa investigasi kenapa (mungkin ada perubahan lain sejak spec ditulis).
- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode, catat perbedaannya di laporan task.
- **Kalau Task 6 Step 2 (regresi modul Kelas) menunjukkan KEGAGALAN APA PUN** — STOP TOTAL, JANGAN lanjut ke Step 3/4/5. Ini sinyal blast-radius TERNYATA TIDAK terkurung seperti yang dianalisis — laporkan detail kegagalannya ke user SEBELUM melakukan apa pun lagi, karena ini bertentangan langsung dengan premis dasar seluruh spec ini.
- **Kalau full suite Task 6 Step 1 menunjukkan kegagalan DI LUAR modul Kurikulum Assignment** (di luar yang sudah dicek Step 2) — investigasi dulu apakah terkait; kalau tidak terkait (mis. seeder demo yang memang sudah dikenal flaky), catat sebagai pre-existing.

## 6. Catatan Serah Terima

- Ini AUDIT PALING SERIUS sejauh ini di rangkaian (Tahun Ajaran → Kelas → Mata Pelajaran → Pengaturan Akademik → Kurikulum Assignment) — satu-satunya yang menemukan crash produksi nyata (bukan cuma UX/wording). Perlakukan Task 1 dengan prioritas tertinggi.
- Keputusan "assignment global eksklusif Platform Admin" adalah HASIL KONFIRMASI LANGSUNG user menjawab pertanyaan eksplisit ("opsi 1 apa opsi 2") — bukan asumsi/tebakan, jangan dipertanyakan ulang.
- User secara eksplisit meminta kickoff (bukan eksekusi inline) — kerjakan mandiri sampai selesai atau sampai benar-benar BLOCKED butuh keputusan user.
- Setelah Task 6 selesai, JANGAN merge branch `akademik-v2` ke branch manapun — keputusan terpisah milik user.
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (lihat Task 6 Step 5) — permintaan tambahan kalau user memang menghendaki.

## 7. Mulai dari mana

Mulai dari **Task 1** (fix crash — PALING URGENT) di `.agents/plans/2026-09-08-kurikulum-assignment-scope-wording-audit.md`, kerjakan berurutan Task 1 → 6. Task 4 (badge/nama lembaga) BUTUH Task 1 selesai lebih dulu (menyentuh file `_form.blade.php` yang sama, struktur hasil Task 1 jadi basis Task 4 Step 5). Task 2 dan 3 independen satu sama lain tapi disarankan tetap berurutan sesuai plan. Task 5 butuh Task 2 selesai (variabel `$isYayasan`).
