# Kickoff: Sidebar — Regroup Menu & Grup Collapsible

**Base commit**: `8401fd66` (`docs(sidebar): implementation plan regroup & collapsible`)
**Branch**: `refactor-view-v2` (dibuat dari `rbac-v2` sebelumnya, tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Client minta gaya sidebar diganti — tapi belum kasih detail spesifik. Analisis penuh `resources/views/layouts/sidebar.blade.php` (239 baris) menemukan masalah STRUKTUR nyata (bukan cuma soal warna): "Kasus Pendampingan" ditempel manual 4x di 4 grup persona berbeda dengan kondisi role yang hampir sama, grup "Kehadiran Saya" namanya sudah tidak akurat lagi, dan grup "Data Induk" berisi 12 item campur aduk (organisasi + SDM + siswa/ortu + 1 item WhatsApp yang tidak nyambung sama sekali).

**Ini spec/plan STRUKTUR SAJA — TIDAK menyentuh warna/tipografi.** Client belum kasih detail visual yang diinginkan; restyle visual (kalau memang itu yang dimaksud) adalah pekerjaan TERPISAH menyusul, butuh input lebih konkret dari client dulu.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-07-sidebar-regroup-collapsible.md` — spec lengkap: analisis masalah, keputusan desain per poin, kode lengkap.
2. `.agents/plans/2026-09-07-sidebar-regroup-collapsible.md` — 3 task, kode lengkap tiap step, sudah self-review.
3. **4 file test sidebar existing** (baca SEMUA sebelum menyentuh kode): `tests/Feature/Admin/KasusPendampinganSidebarTest.php`, `tests/Feature/SidebarPengelompokanTest.php`, `tests/Feature/SidebarOrangTuaAkademikTest.php`, `tests/Feature/SidebarStubMenuHiddenTest.php`. Plan SUDAH mengidentifikasi 1 assertion yang akan pecah (`SidebarPengelompokanTest.php` baris 147-171) dan sudah menulis perbaikannya eksplisit di Task 1 Step 1 — TAPI baca semuanya sendiri juga supaya paham konteks penuh, bukan cuma percaya plan mentah-mentah.
4. `.ai/rules/index.md` lalu file rule yang cocok (`views.md`, `tests.md`).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **TIDAK mengubah palet warna/tipografi/token desain apapun** — `brand-500`, font Outfit, `rounded-2xl`/`shadow-card` dst. TailAdmin-style existing TETAP UTUH. Kalau tergoda "sekalian dirapikan" warnanya, JANGAN — itu di luar scope, butuh keputusan client terpisah.
- **JANGAN pakai `@alpinejs/collapse`** — dikonfirmasi belum terpasang di project ini (`resources/js/*.js`/`package.json` dicek langsung, 0 hasil). Task 2 pakai `x-show`+`x-transition` Alpine bawaan, TANPA install package baru. Kalau implementer tergoda "lebih bagus pakai collapse plugin untuk animasi height yang mulus" — itu penambahan scope/dependency yang butuh persetujuan user dulu, JANGAN diam-diam ditambahkan.
- **Chevron collapse WAJIB pakai `<x-dynamic-component :component="'lucide-chevron-down'">`** — POLA YANG SAMA dengan seluruh ikon lain di file ini. **JANGAN pakai `<x-icon name="chevron-down">`** — itu komponen BEDA (`resources/views/components/icon.blade.php`, skema nama Material-Symbols-style, TIDAK PUNYA `chevron-down`/`chevron_down`) — kalau dipaksa dipakai, akan JATUH ke `@default` dan render ikon PLACEHOLDER SALAH (bukan chevron), BUKAN error yang kelihatan langsung, jadi mudah lolos tanpa disadari kalau tidak dicek visual di browser.
- **Task 1 Step 1 (perbaiki test yang pecah) WAJIB dikerjakan SEBELUM array `$navGroups` diubah** — supaya benar-benar terverifikasi merah karena alasan yang tepat (rename belum terjadi), bukan alasan lain.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 3.
- **Tidak ada Pest test yang bisa memverifikasi interaksi Alpine JS runtime** (buka/tutup accordion via klik) — itu HANYA bisa diverifikasi manual di browser (Task 2 Step 3, WAJIB dilakukan sungguhan, bukan diasumsikan). Kalau ada akses Playwright/browser automation di environment ini, dianjurkan dipakai untuk verifikasi lebih genuine (pola sudah dipakai sesi sebelumnya untuk fitur lain).
- **Task 1 mengubah SELURUH isi array `$navGroups`** (bukan edit parsial baris-per-baris) — plan sudah kasih kode LENGKAP hasil akhirnya, tinggal ganti seluruh blok. Perhatikan 2 blok komentar (SPMB yang dinonaktifkan, penjelasan Tagihan/Verifikasi Pembayaran PPDB-only) yang WAJIB dipertahankan persis, JANGAN terhapus saat replace.
- **Nomor baris berubah setelah Task 1** — Task 2 menyentuh bagian render SETELAH `@endphp`, cek ulang baris aktual di file SETELAH Task 1 selesai, jangan asumsikan nomor baris dari draf plan masih akurat.

## 5. Instruksi Stop-and-Report

- **Kalau full suite Task 3 menemukan kegagalan test LAIN di luar 4 file sidebar yang sudah diketahui** — kemungkinan test lain menyinggung label menu lama (`'Kehadiran Saya'`, `'Data Induk'`, `'Akses & Peran'`) yang tidak ketahuan saat spec/plan ditulis. Grep dulu `grep -rn "'Kehadiran Saya'\|'Data Induk'\|'Akses & Peran'" tests/` SEBELUM menyimpulkan itu pre-existing tidak terkait — kalau ketemu, perbaiki fixture-nya (pola sama seperti Task 1 Step 1), CATAT di laporan sebagai temuan tambahan.
- **Kalau kode `sidebar.blade.php` di lapangan BERBEDA dari yang dikutip plan** (kemungkinan berubah lagi sejak plan ditulis) — STOP, baca versi terkini, sesuaikan, catat perbedaannya di laporan task.
- **Kalau verifikasi browser Task 2 Step 3 menemukan masalah visual/interaksi** (transisi patah, chevron salah arah, grup tidak auto-terbuka saat halaman aktif ada di dalamnya) — perbaiki dulu sebelum lanjut Task 3, JANGAN laporkan "selesai" dengan masalah visual yang diketahui belum beres.

## 6. Catatan Serah Terima

- Plan ini py 1 kasus test-awareness yang lebih dalam dari biasanya: SEMUA 4 file test sidebar sudah dibaca & dianalisis assertion-nya SEBELUM plan ditulis (bukan ditemukan implementer secara reaktif) — 1 pecah teridentifikasi & sudah ada fix-nya di plan, 3 lainnya dikonfirmasi aman. Kalau implementer menemukan analisis ini SALAH (ada test lain yang ternyata juga pecah), itu berarti ada sesuatu yang terlewat saat plan ditulis — investigasi kenapa, jangan cuma tambal tanpa mengerti akar masalahnya.
- User secara eksplisit meminta kickoff kali ini (bukan eksekusi inline) — kerjakan mandiri sampai selesai atau sampai benar-benar BLOCKED butuh keputusan user.
- Setelah Task 3 selesai, JANGAN merge branch `refactor-view-v2` — keputusan terpisah milik user. Branch ini baru dibuat dari `rbac-v2` (yang sendiri belum di-merge ke `main`), jadi berlapis 2 belum-di-merge.

## 7. Mulai dari mana

Mulai dari **Task 1** (regroup array) di `.agents/plans/2026-09-07-sidebar-regroup-collapsible.md`, kerjakan berurutan Task 1 → 3. Task 1 Step 1 (perbaiki test yang akan pecah) HARUS jadi langkah PERTAMA yang benar-benar dikerjakan, sebelum menyentuh array `$navGroups` sama sekali — ikuti urutan TDD yang sudah ditulis di plan, jangan dibalik.
