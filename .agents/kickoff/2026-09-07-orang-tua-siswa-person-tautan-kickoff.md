# Kickoff: Perbaikan Tautan Orang Tua-Siswa & Konsistensi Identitas Person

**Base commit**: `aadcee38` (`docs(rbac): implementation plan perbaikan tautan orang tua-siswa-person`)
**Branch**: `rbac-v2` (tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Laporan awal user: "Data Induk Orang Tua, filter 0 tapi saat dicek detail ada yang tertaut anaknya." Investigasi mendalam (baca kode + verifikasi empiris via `php artisan tinker` terhadap DB nyata `pintera_sdm_app`, semua dibungkus `DB::beginTransaction()`/`DB::rollBack()`) menemukan bug itu BUKAN soal scope yayasan/lembaga (topik spec sebelumnya, `2026-09-07-scope-yayasan-lembaga-menu-fix.md`, sudah selesai & sudah merge-ready di branch ini), melainkan 4 bug fungsional berbeda pada relasi Siswa-OrangTua-Person.

Yang PALING PARAH: `SiswaOrangTuaController` (jalur tautkan orang tua dari halaman Siswa) TERBUKTI GAGAL TOTAL untuk SEMUA level scope — dikonfirmasi lewat 2 tinker terpisah, termasuk skenario paling longgar (yayasan mode "Semua Lembaga"), sama-sama hasil "TIDAK KETEMU". Ini bukan bug kosmetik — ini JALUR PENDAFTARAN KELUARGA UTAMA aplikasi yang rusak.

Melalui diskusi eksplisit dengan user, ada keputusan bisnis penting: **Siswa didaftarkan lebih dulu, Orang Tua ditautkan kemudian dari halaman Siswa — ini alur UTAMA/resmi**, bukan salah satu dari dua alur setara. Halaman Data Induk → Orang Tua (berdiri sendiri) tetap ada tapi berperan sebagai alat KELOLA profil yang sudah ada / jalur sekunder, bukan pintu masuk utama pembuatan relasi baru.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-07-orang-tua-siswa-person-tautan.md` — spec lengkap, berisi Keputusan Bisnis (§ atas), Konteks Arsitektur (`Person`/`YayasanScope`/`CreatePersonAction`), dan 4 bug dengan kode fix konkret + acceptance criteria per bug.
2. `.agents/plans/2026-09-07-orang-tua-siswa-person-tautan.md` — 4 task, kode lengkap tiap step, sudah self-review. Task 1 adalah prioritas tertinggi & PALING RUMIT (perbaikan fixture test dulu sebelum fix, TDD merah-hijau bertahap) — baca Task 1 dua kali sebelum mulai.
3. `.agents/specs/2026-09-07-scope-yayasan-lembaga-menu-fix.md` dan `.agents/logs/2026-09-07-scope-yayasan-lembaga-menu-fix.md` — konteks fix scope yayasan/lembaga di `OrangTuaController::index()` yang BARU SAJA selesai di branch yang sama (Kategori A.3) — Task 2/3 plan ini MEMBANGUN DI ATAS query hasil fix itu (`$lembagaIdsYayasan`/`$activeLembagaId` sudah ada di method, JANGAN dihitung ulang atau ditimpa).
4. `.ai/rules/index.md` lalu setiap file rule yang glob-nya cocok dengan path yang akan disentuh (`controllers.md`, `services.md` — untuk `AkunOrangTuaGenerator` di `app/Services/`, `views.md`, `tests.md`).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Urutan pendaftaran resmi: Siswa dulu, tautkan Orang Tua kemudian dari halaman Siswa.** Ini alasan kenapa Task 1 (bug di `SiswaOrangTuaController`) adalah prioritas tertinggi, bukan Task 2/3 (yang ada di halaman Orang Tua berdiri sendiri, jalur sekunder).
- **Task 1 WAJIB urutan TDD ketat**: perbaiki 2 fixture test lama yang false-negative-tersamar dulu (buat betul-betul MERAH tanpa fix) → baru terapkan fix 3a (`withoutGlobalScopes()`) + 3b (`yayasan_id` di `AkunOrangTuaGenerator`) → baru verifikasi HIJAU. JANGAN langsung tambah test baru tanpa memperbaiki fixture lama dulu — kalau fixture lama tidak diperbaiki, test itu akan terus lulus secara palsu walau bug sungguhan belum diperbaiki (baca penjelasan lengkap "false negative tersamar" di plan Task 1).
- **Pola `withoutGlobalScopes()` di Task 1 WAJIB identik** dengan `OrangTuaController::store()` baris 77 (rujukan yang SUDAH BENAR di codebase) — BUKAN pola baru atau bypass parsial.
- **Task 2 (over-count `siswa_count`) TIDAK mengubah `edit()`** — `edit()` SENGAJA tetap menampilkan anak lintas lembaga (desain benar: `Person::YayasanScope` berbasis yayasan, bukan lembaga, karena orang tua adalah entitas level yayasan). Yang salah HANYA angka ringkasan di `index()`.
- **Task 3 bergantung urutan pada Task 2** — angka `siswa_count` yang dipakai filter `anak=ada|belum` WAJIB sudah ter-scope dari Task 2. Jangan dikerjakan out-of-order atau digabung jadi 1 commit.
- **Task 3 sengaja HANYA mengubah filter "Ada Anak"/"Belum Ada Anak"** dari client-side ke server-side. Filter "Aktif"/"Non-Aktif" di halaman yang sama TIDAK disentuh (tidak basi, bukan bagian dari bug ini) — lihat "Catatan penyesuaian dari spec" di awal Task 3 plan untuk alasan teknis lengkap kenapa pendekatannya PHP-collection-filter, bukan SQL `having()` seperti draft awal spec.
- **TIDAK membangun UI untuk `MergePersonsAction`** — mekanisme merge Person sudah ada & teruji dari proyek lain (`identity-v1-person-master-entity`), di luar cakupan total plan ini. Kalau menemukan indikasi kuat perlu UI merge saat implementasi, STOP dan laporkan ke user, jangan diam-diam dibangun.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **MySQL deadlock risk**: project ini pernah kena deadlock kalau 2 proses test jalan bersamaan — SELALU cek dulu (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` di PowerShell) sebelum full suite di Task 4.
- **DB dev sesungguhnya bernama `pintera_sdm_app`**, BUKAN `pintera_app` (kesalahan pernah terjadi & dikoreksi sesi sebelumnya — kalau perlu tinker manual untuk verifikasi tambahan, pastikan connect ke DB yang benar).
- **`OrangTuaFactory` (`database/factories/OrangTuaFactory.php`) punya perilaku non-obvious**: menerima key `nik`/`nama_lengkap`/`no_hp`/`email`/`alamat`/`user_id`/`yayasan_id` di `create([...])` meski `OrangTua` model sendiri tidak punya kolom-kolom itu (didelegasikan ke `Person::factory()` lewat closure `person_id`, baris 38-61) — baca file itu penuh sebelum menulis fixture test baru di Task 2/3, jangan menebak perilakunya.
- **`actingAsOrangTuaManager()` = lembaga-scope, `actingAsSiswaOrangTuaManager()` = yayasan-scope** (keduanya di `tests/Pest.php:121-162`, yang kedua adalah pembungkus yang pertama + role tambahan). Task 1-3 semuanya butuh kedua varian ini untuk menguji skenario lembaga-scope DAN yayasan-scope — jangan cuma pakai salah satu.
- **Task 3 Step 6 butuh verifikasi manual di browser** (kombinasi filter server-side + Alpine client-side yang tersisa) — WAJIB benar-benar dilakukan dan dilaporkan hasilnya secara jujur di laporan task, JANGAN diasumsikan lolos karena kode "terlihat benar".

## 5. Instruksi Stop-and-Report

- **Kalau menemukan `SiswaOrangTuaController.php`, `OrangTuaController.php`, `AkunOrangTuaGenerator.php`, atau `index.blade.php` BERBEDA dari yang dikutip plan** (kemungkinan berubah lagi sejak plan ditulis) — STOP, baca versi terkini, sesuaikan implementasi mengikuti struktur SEKARANG, catat di laporan task apa yang berbeda dan kenapa.
- **Kalau test regresi Task 1 Step 7-8 gagal** untuk test LAIN yang tidak disebut eksplisit di plan (bukan 2 fixture yang memang diperbaiki) — itu BUKAN hal wajar, STOP dan laporkan sebagai BLOCKED, jangan asumsikan "pasti akan terperbaiki nanti".
- **Kalau ragu soal cakupan** (mis. apakah suatu perbaikan tambahan yang ditemukan saat implementasi termasuk dalam 4 bug ini atau sebenarnya bug ke-5 yang belum tercatat) — tanyakan/catat sebagai temuan terpisah, jangan diam-diam diperbaiki di luar scope task yang sedang jalan.

## 6. Catatan Serah Terima

- Spec ini sudah melalui 1 putaran self-review yang menemukan false-negative tersamar di 2 test existing (`SiswaOrangTuaLinkingTest.php` baris 24-40 dan 237-252) — plan sudah mengakomodasi perbaikannya secara eksplisit di Task 1. Kalau menemukan test lain dengan pola serupa (fixture yang kebetulan lolos scope tanpa benar-benar menguji bug) saat implementasi, catat sebagai temuan tambahan di laporan task, boleh diperbaiki kalau langsung relevan dengan file yang sedang disentuh.
- User secara eksplisit meminta kickoff kali ini (bukan eksekusi inline di sesi yang sama) — kerjakan mandiri sampai selesai atau sampai benar-benar BLOCKED butuh keputusan user.
- Setelah Task 4 selesai, JANGAN merge branch `rbac-v2` ke `main` — itu keputusan terpisah milik user (branch ini juga masih membawa hasil 7-task scope yayasan/lembaga sebelumnya yang belum di-merge).

## 7. Mulai dari mana

Mulai dari **Task 1** (`SiswaOrangTuaController` + `AkunOrangTuaGenerator`, prioritas tertinggi) di `.agents/plans/2026-09-07-orang-tua-siswa-person-tautan.md`, kerjakan berurutan Task 1 → 4. Task 1 adalah yang paling kritis secara bisnis (jalur pendaftaran utama) DAN paling rumit prosesnya (perbaikan fixture dulu, TDD bertahap) — baca konteks "fixture yang harus dipahami dulu" di awal Task 1 plan sampai benar-benar paham sebelum mengedit apa pun.
