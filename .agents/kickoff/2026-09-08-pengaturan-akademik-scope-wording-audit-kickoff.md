# Kickoff: Kejujuran Wording & UX Akses Tanpa Lembaga Aktif — Menu Pengaturan Akademik

**Base commit**: `ee1d912c` (`docs(pengaturan-akademik): implementation plan wording & UX akses`)
**Branch**: `rbac-v2` (tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit menu "Pengaturan Akademik" (lanjutan Tahun Ajaran → Kelas → Mata Pelajaran → Pengaturan Akademik). Backend-nya SUDAH SANGAT SOLID — `PengaturanAkademikController`/`KalenderAkademikController` sudah pakai `resolveActiveLembagaId()` di semua titik, entri "nasional" vs "lembaga" sudah dipisah permission, cross-lembaga sudah `abort(404)`. TIDAK ADA perubahan backend logic scope di plan ini.

2 temuan murni UX/frontend:
1. **Halaman tidak pernah menampilkan nama lembaga yang sedang dikonfigurasi** — `$lembaga` sudah dikirim controller sejak awal, tapi `->nama`-nya tidak pernah dirender di mana pun.
2. **User secara langsung mengoreksi**: aktor yang belum switch lembaga di-redirect PAKSA ke `/dashboard` (halaman yang sama sekali tidak terkait), bukan diberi tahu di tempat. Ini TIDAK KONSISTEN dengan pola sisa aplikasi — `create()` Kelas/Mata Pelajaran redirect ke index MODUL YANG SAMA, dan 2 halaman lain (`pembayaran/index.blade.php`, `tagihan/index.blade.php`) SUDAH LAMA memakai pola "tampilkan halaman + notice inline, tidak pernah redirect" via flag `$lembagaBelumDipilih`.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-08-pengaturan-akademik-scope-wording-audit.md` — spec lengkap, 2 item, kode current-vs-fix konkret.
2. `.agents/plans/2026-09-08-pengaturan-akademik-scope-wording-audit.md` — 3 task TDD, kode lengkap tiap step, sudah self-review.
3. `resources/views/portals/lembaga/keuangan/pembayaran/index.blade.php` baris 9-12 — referensi POLA (bukan styling) `$lembagaBelumDipilih` yang sudah berpreseden, direplikasi dengan design system modern di plan ini.

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **`index()` SETELAH fix TIDAK PERNAH redirect lagi** — return type murni `View`. Import `Illuminate\Http\RedirectResponse` WAJIB DIHAPUS dari controller (satu-satunya pemakaian ada di signature method ini, dikonfirmasi lewat grep saat spec ditulis).
- **2 test EXISTING di `PengaturanAkademikControllerTest.php` (baris ±194 dan ±208) WAJIB DIUBAH, bukan ditambah baru** — Task 1 Step 1 memberikan kode pengganti PERSIS. JANGAN menambahkan test baru di sampingnya sambil membiarkan yang lama tetap `assertRedirect()` — itu akan membuat 2 test saling bertentangan.
- **Skenario "tidak ada lembaga aktif" dan "session stale lintas-yayasan" mendapat perlakuan IDENTIK** (empty-state yang SAMA) — `resolveActiveLembagaId()` mengembalikan `null` untuk keduanya, controller TIDAK BISA dan TIDAK PERLU membedakannya. JANGAN menambahkan logic untuk membedakan 2 skenario ini.
- **Task 2 Step 3 (bungkus blok tab dengan `@if`/`@else`/`@endif`) adalah titik PALING BERISIKO di seluruh plan ini** — edit ini menyisipkan wrapper di SEKELILING blok 272 baris tanpa menyentuh isinya. WAJIB verifikasi keseimbangan tag `<div>` sebelum commit (hitung ulang pembuka vs penutup) — kesalahan di sini TIDAK akan memunculkan error PHP yang jelas, cuma tampilan Blade yang berantakan/rusak secara diam-diam.
- **Badge nama lembaga (Item 1) SATU warna saja (brand)** — halaman ini TIDAK PERNAH punya mode "Semua Lembaga" (beda dari Tahun Ajaran/Kelas/Mata Pelajaran), jadi JANGAN menambahkan percabangan warna ungu seperti di menu-menu lain.
- **`updateHariAktif()`/`updateBatasEditAbsen()` TIDAK DISENTUH** — sudah benar (422 JSON).
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **File view yang diedit (`akademik.blade.php`) punya indentasi TIDAK KONSISTEN** di blok yang akan dibungkus (dicatat eksplisit di spec) — SALIN struktur closing tag yang ADA SEKARANG apa adanya, jangan "merapikan" indentasi sambil jalan (menambah risiko salah hitung tag).
- **Task 1 Step 4 SENGAJA mendokumentasikan bahwa 2 test masih gagal setelah Task 1 selesai** (dengan alasan BEDA dari sebelumnya — sekarang gagal karena `assertSee('Pilih Lembaga Aktif Dulu')` belum ketemu, bukan lagi soal redirect) — INI NORMAL, bukan tanda Task 1 salah. Test itu akan hijau setelah Task 2 selesai.
- **Task 2 Step 6 test badge kemungkinan sudah PASS begitu Step 2 selesai** (sebelum secara eksplisit "seharusnya" ditulis di Step 5) — pola yang sama seperti ditemukan di audit-audit sebelumnya (Kelas, Mata Pelajaran), bukan indikasi kesalahan.
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 3.

## 5. Instruksi Stop-and-Report

- **Kalau setelah Task 2 selesai, tampilan halaman Pengaturan Akademik terlihat rusak/berantakan saat verifikasi manual browser** (Task 3 Step 3) — STOP, kemungkinan besar ada tag `<div>` yang salah hitung di Task 2 Step 3. Hitung ulang dengan teliti sebelum melanjutkan, JANGAN asumsikan "test hijau berarti pasti benar" — test Pest tidak mendeteksi HTML yang secara struktur salah tapi masih valid secara string-matching.
- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode, catat perbedaannya di laporan task.
- **Kalau full suite Task 3 menunjukkan kegagalan DI LUAR modul Pengaturan Akademik/Kalender Akademik** — investigasi dulu apakah terkait perubahan plan ini (seharusnya TIDAK); kalau tidak terkait, catat sebagai pre-existing.

## 6. Catatan Serah Terima

- Item 2 (empty-state) lahir dari koreksi LANGSUNG user terhadap temuan awal saya ("kurang secara user experience") — bukan hasil audit murni, jadi pastikan hasil akhirnya benar-benar menjawab keluhan itu: user TIDAK BOLEH lagi merasa "diusir" saat mengakses halaman ini tanpa lembaga aktif.
- User secara eksplisit meminta kickoff (bukan eksekusi inline) — kerjakan mandiri sampai selesai atau sampai benar-benar BLOCKED butuh keputusan user.
- Setelah Task 3 selesai, JANGAN merge branch `rbac-v2` ke `main` — keputusan terpisah milik user.
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (lihat Task 3 Step 4) — permintaan tambahan kalau user memang menghendaki.

## 7. Mulai dari mana

Mulai dari **Task 1** (controller berhenti redirect + ubah 2 test existing) di `.agents/plans/2026-09-08-pengaturan-akademik-scope-wording-audit.md`, kerjakan berurutan Task 1 → 3. Task 1 dan 2 WAJIB berurutan (Task 2 melengkapi apa yang Task 1 sengaja tinggalkan gagal — lihat catatan Step 4 Task 1).
