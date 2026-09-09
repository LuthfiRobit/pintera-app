# Kickoff: Perbaikan Menu Rekap Rapor

**Base commit**: `2b1e0c87` (`docs(rapor): implementation plan perbaikan Rekap Rapor (8 task, 9 item spec)`)
**Branch**: `rbac-v2` (di sesi ini juga disebut `akademik-v2` — SAMA branch, TETAP di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit backend+frontend menyeluruh menu **Rekap Rapor** (`admin.rapor.index`) menemukan 1 bug kritis (ambiguitas default filter untuk aktor yayasan mode agregat — kelas bug yang SAMA dengan yang baru diperbaiki di menu TP, TAPI dengan resolusi yang BERBEDA), 1 kesenjangan besar (halaman ini terlewat dari gelombang standarisasi badge-scope), beberapa item wording/kelengkapan dokumen, dan 2 permintaan tambahan dari user (adopsi `<x-select>` + reorder dropdown filter).

**Koreksi penting**: `.agents/logs/2026-09-07-audit-scope-yayasan-lembaga-sidebar.md` baris 89 SEBELUMNYA mencatat Rekap Rapor sebagai `✅ ✅` — audit ulang MEMBUKTIKAN catatan itu SUDAH TIDAK AKURAT (lihat Task 7, yang justru memperbaiki catatan itu SETELAH kode benar-benar diperbaiki, bukan sebelumnya).

**Beda penting dengan pola perbaikan TP sebelumnya**: di TP, resolusi ambiguitas default adalah "tampilkan SEMUA data lintas lembaga saat mode agregat". Di Rekap Rapor, resolusinya JUSTRU KEBALIKAN — "JANGAN auto-pilih apa pun, wajib user pilih Tahun Ajaran dulu secara sadar" — karena 1 halaman Rekap Rapor cuma bisa menampilkan rekap 1 kelas pada satu waktu (beda dari TP yang bisa menampilkan banyak TP dari banyak lembaga sekaligus). **JANGAN meniru pola TP secara membabi-buta di sini** — baca penjelasan lengkap di spec Item A kenapa resolusinya harus beda.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-09-rekap-rapor-audit-perbaikan.md` — spec lengkap 9 item (A-I). BACA BAIK-BAIK "Ringkasan" dan "Ketergantungan urutan implementasi" — itu fondasi kenapa Item A+B harus 1 task, dan kenapa urutan C→D, C→H→I harus berurutan.
2. `.agents/plans/2026-09-09-rekap-rapor-audit-perbaikan.md` — 8 task TDD, kode lengkap tiap step, sudah self-review 3x.
3. `tests/Feature/Admin/RaporControllerTest.php` — **WAJIB dibaca utuh SEBELUM mulai Task 1**, bukan cuma dilihat sekilas. 23 test existing di file ini SUDAH menguji beberapa skenario yang RELEVAN dengan spec (deep-link mode agregat, session kosong utk Item C) — plan SUDAH menandai test mana yang jadi regresi wajib-lulus vs yang genuinely perlu ditulis baru. Kalau menulis test baru yang isinya mirip/duplikat salah satu dari 23 test itu, STOP dan cek ulang plan.

## 3. WAJIB Pakai Laravel Boost MCP Tools

Proyek ini punya Laravel Boost (MCP server) terpasang — **PAKAI tools-nya, jangan kerja manual kalau ada tool yang lebih tepat** (sesuai `CLAUDE.md` proyek ini):

- **`search-docs`** — WAJIB dipakai SEBELUM mengandalkan ingatan soal API Laravel/Eloquent yang versi-spesifik (mis. perilaku `$attributes->merge()` di Blade component, method Collection seperti `firstWhere()`). Jangan asumsikan dari memori kalau ada keraguan sedikit pun.
- **`database-schema`** — kalau ragu soal struktur kolom `kelas`, `semester`, `tahun_ajaran`, atau `lembaga` (nama kolom FK, nullable, dst) sebelum menulis query/factory di test, cek lewat tool ini, JANGAN tebak dari nama variabel di kode lama.
- **`browser-logs`** — **KHUSUS Task 6** (migrasi ke `<x-select>` + reorder). Kalau implementer punya akses browser interaktif untuk verifikasi manual, WAJIB cek `browser-logs` setelah membuka halaman Rekap Rapor pasca-perubahan — ini PERSIS tool yang mengungkap bug fatal tersembunyi di redesign TP sebelumnya (Alpine/TomSelect gagal-senyap, tidak terlihat dari tampilan visual biasa). Kalau ada `Uncaught ReferenceError` atau error TomSelect apa pun di log, STOP dan laporkan — JANGAN anggap "kelihatannya baik-baik saja" tanpa cek log ini kalau memang bisa diakses.
- **`last-error`** — kalau ada error backend tak terduga saat menjalankan test/manual check, cek tool ini dulu sebelum menebak-nebak dari pesan error terminal saja.
- **`database-query`** — kalau perlu verifikasi state data secara langsung (mis. cek `active_lembaga_id` session behavior tidak bisa lewat ini, tapi verifikasi hasil seed/factory bisa) selama debugging test yang gagal, pakai ini daripada `php artisan tinker` manual.

Tools ini TERSEDIA sepanjang eksekusi plan — pakai secara proaktif, bukan cuma kalau diminta.

## 4. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Item A dan Item B (Task 1) WAJIB 1 task, JANGAN dipisah** — kode Item A memanggil `scopeHeaderData()` yang didefinisikan Item B. Ini sudah ditegaskan berulang di spec DAN plan, tapi disebutkan lagi di sini karena ini keputusan paling gampang salah kalau task-nya dikerjakan terburu-buru per-item alih-alih per-task.
- **Resolusi ambiguitas Item A BUKAN "tampilkan semua" seperti TP** — resolusinya "jangan auto-pilih apa pun, biarkan `null`". JANGAN "koreksi" ini supaya konsisten dengan TP — itu justru salah, sudah dijelaskan alasannya di spec.
- **Urutan Task 1→6 WAJIB berurutan** (Task 2/3 butuh variabel dari Task 1; Task 6 butuh markup hasil Task 2). Task 4 (PDF) dan Task 5 (tooltip) TIDAK punya ketergantungan teknis, TAPI tetap dikerjakan berurutan karena semuanya menyentuh file yang tumpang tindih (`_hasil.blade.php` disentuh Task 1, 3, 5) — mengerjakan paralel berisiko conflict.
- **Task 7 (update catatan checklist lama) WAJIB PALING TERAKHIR**, setelah Task 1-6 semuanya lulus test dan ter-commit — bukan janji, tapi fakta terverifikasi. JANGAN kerjakan Task 7 duluan "karena gampang".
- **Task 6 mengubah `resources/views/components/select.blade.php` yang dipakai 9 file LAIN di luar Rekap Rapor** (Karyawan, Roles, Siswa, Users, Kasus) — ini DAMPAK DISENGAJA (sudah direkam di `.ai/rules/components.md`, keputusan eksplisit user), BUKAN efek samping tak terduga. JANGAN "batalkan" perubahan komponen bersama ini demi menghindari dampak lintas-file — itu justru tujuannya.
- **Backend inti (`RaporCalculationService`) TIDAK disentuh sama sekali** di plan ini — kalau menemukan sesuatu yang "kelihatannya perlu diperbaiki juga" di situ, itu di luar scope, laporkan saja jangan diperbaiki sendiri.
- **Tidak pakai worktree, tidak pindah branch, TIDAK ADA migrasi database.**

## 5. Fakta Operasional

- **Test home**: SATU-SATUNYA `tests/Feature/Admin/RaporControllerTest.php`. Helper existing: `actingAsRaporViewer(Lembaga $lembaga)` (lembaga-scope) — untuk aktor yayasan-scope, JANGAN bikin helper baru, ikuti pola INLINE yang sudah dipakai test existing baris 329-333 dan 376-380 (`Permission::firstOrCreate(...)` + `Role::firstOrCreate([...], ['scope_level' => 'yayasan'])` + `$role->givePermissionTo([...])` + `User::factory()->create(['yayasan_id' => $yayasan->id])` + `$user->assignRole($role)`).
- **Nama role di test BARU harus unik**, jangan sampai bentrok dengan role existing di file yang sama (`admin_yayasan_rapor`, `yayasan_super_admin_rapor_test` sudah dipakai test existing) — plan sudah pakai suffix unik per test (`_aggregate_test`, `_switched_test`, `_badge_test`, dst), ikuti pola itu kalau menambah test lain.
- **`Kelas::with('lembaga')`** (eager-load) SUDAH digabung ke kode Task 1 — Task 3 (Item D) TIDAK BOLEH menyentuh controller lagi untuk itu, cukup pakai `$selectedKelas->lembaga` langsung di view.
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 8 kalau ada kekhawatiran proses lain masih jalan.

## 6. Instruksi Stop-and-Report

- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode (cari via nama method/nama test, BUKAN nomor baris semata), catat perbedaannya di laporan task.
- **Kalau Task 1 Step 7 (regresi PENUH 27 test) menunjukkan KEGAGALAN APA PUN** — STOP TOTAL, laporkan detail ke user SEBELUM melanjutkan ke Task 2. Task 1 adalah rewrite penuh `index()`, task paling berisiko regresi di plan ini.
- **Kalau menemukan test/skenario LAIN (di luar 9 item A-I yang sudah diidentifikasi) yang JUGA berkaitan dengan ambiguitas scope lembaga/yayasan** — STOP, laporkan ke user dulu sebelum menambah scope sendiri, mengingat menu ini menyangkut nilai rapor siswa (topik sensitif, sama seperti Komponen Penilaian/TP sebelumnya).
- **Kalau Task 6 Step 6 (verifikasi dampak lintas-file `<x-select>`) menunjukkan salah satu dari 9 file lain terlihat RUSAK** (bukan cuma beda visual tipis) — STOP dan laporkan, JANGAN lanjut asumsi aman di semua 9 file tanpa dicek.
- **Kalau ada `browser-logs` menunjukkan error JS apa pun setelah Task 6** (lihat Section 3) — STOP dan laporkan, JANGAN anggap tampilan visual yang terlihat baik sebagai bukti cukup (persis pelajaran dari bug TP sebelumnya).

## 7. Catatan Serah Terima

- Spec ini sudah melalui 4 putaran review eksplisit dalam sesi yang sama (2 kesalahan nyata ditemukan & diperbaiki: konflik diff Item A/D, ketergantungan Item A+B yang awalnya tidak dijelaskan) — versi final sudah akurat terhadap kode aktual per saat spec ditulis, TAPI TETAP ikuti instruksi Stop-and-Report kalau menemukan sesuatu yang tidak cocok.
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (Task 8 Step 4 eksplisit bilang begitu) — kalau user memang menghendaki di sesi ini, itu permintaan tambahan terpisah.
- Setelah Task 8 selesai, JANGAN merge branch ke branch manapun — keputusan terpisah milik user.

## 8. Mulai dari mana

Mulai dari **Task 1** (guard mode agregat + badge scope — paling kritis) di `.agents/plans/2026-09-09-rekap-rapor-audit-perbaikan.md`, kerjakan BERURUTAN Task 1 → 8 (jangan lompat urutan, lihat Section 4 soal ketergantungan antar-task).
