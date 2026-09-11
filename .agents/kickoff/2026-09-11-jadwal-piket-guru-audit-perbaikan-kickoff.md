# Kickoff: Perbaikan Audit Jadwal Piket Guru & Akses Guru Pengganti

**Base commit**: `af6d9b66` (`docs(akademik): plan perbaikan audit jadwal piket guru...`)
**Branch**: `rbac-v2` (TETAP di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit menyeluruh (backend/security/bisnis + UI/UX/frontend/wording) pada fitur Jadwal Piket Guru — **gerbang keamanan** yang menentukan siapa boleh mengisi jurnal/presensi sesi guru lain saat guru pemilik berhalangan hadir. Fitur ini sudah punya spec desain sebelumnya (`.agents/specs/2026-09-06-guru-piket-jurnal-kbm.md`) yang diikuti SANGAT SETIA oleh implementasi (11/12 skenario test wajib ada & lolos) — audit sesi ini menemukan 7 gap yang TIDAK diantisipasi spec lama: bug timezone sistemik yang berdampak langsung ke gerbang akses ini, gap UX berisiko salah kira (guru piket tidak tahu sedang mengisi kelas orang lain), gap visibilitas admin, bug akuntabilitas data, bug kebocoran metadata jadwal, gap konsistensi UI, dan gap cakupan test.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-06-guru-piket-jurnal-kbm.md` — KONTEKS PENTING, bukan bagian plan ini, tapi WAJIB dipahami dulu (desain 2 lapis data `JadwalPiketMingguan`→`PiketHarian`, `PiketAccessChecker`, kolom akuntabilitas `diisi_oleh_guru_id`).
2. `.agents/specs/2026-09-11-jadwal-piket-guru-audit-perbaikan.md` — spec lengkap sesi ini, 4 putaran self-review, kode current-vs-fix KONKRET untuk 7 kelompok temuan (§2.1-§2.7), plus 4 item yang SENGAJA dikeluarkan dari scope (§3).
3. `.agents/plans/2026-09-11-jadwal-piket-guru-audit-perbaikan.md` — 8 task, 4 putaran self-review. Task 1-6 & 8 berisi kode lengkap siap salin; **Task 7 SENGAJA berbeda format** — lihat §3 di bawah.

## 3. Instruksi Khusus untuk Task 7 — Analisa UI/UX, BUKAN Eksekusi Mekanis

**Ini permintaan eksplisit user untuk kickoff ini**: siapa pun (agent lain/subagent) yang mengerjakan Task 7 WAJIB benar-benar MENGANALISA konsistensi UI/UX terhadap standar yang sudah dipakai proyek, BUKAN sekadar menjalankan instruksi "ganti select jadi tomSelectPegawai" secara membabi-buta tanpa berpikir.

Task 7 di plan ditulis dengan 3 langkah analisa eksplisit SEBELUM langkah penerapan:
1. **Baca dulu pola established** — `resources/views/admin/kelas/_form.blade.php` (pola `tomSelectPegawai`) DAN minimal 2-3 halaman lain yang pakai `<x-select>` — pahami KONVENSI-nya, bukan cuma meniru baris kode tanpa paham kenapa.
2. **Bandingkan dengan kondisi `piket-guru/{index,create,edit}.blade.php` SAAT INI** — per elemen `<select>`, tanyakan: apakah ini di dalam loop dengan `:name` dinamis (berpotensi konflik binding — INI PERNAH TERJADI NYATA di audit sesi lain sebelumnya, bukan kekhawatiran teoretis)? Apakah opsi-nya sedikit (≤7-10, cukup `<x-select>` saja) atau banyak (butuh `tomSelectPegawai` yang searchable)?
3. **Baru terapkan** penyesuaian yang benar-benar cocok konteks — TIDAK asal copy-paste.

**Koreksi penting yang SUDAH ditemukan & diperbaiki saat plan ini ditulis** (supaya pelaksana Task 7 tidak mengulang kesalahan yang sama): draft awal plan sempat mengira `$guruOptions` (dibutuhkan `tomSelectPegawai`) dikirim dari controller — SALAH. Setelah verifikasi langsung ke kode, `$guruOptions` ternyata DITURUNKAN LOKAL di Blade lewat blok `@php` (dari `$guruList` yang memang sudah dikirim controller), BUKAN dari controller sama sekali. Task 7 di plan SUDAH diperbaiki mencerminkan fakta ini — TAPI ini contoh KONKRET kenapa langkah "baca dulu, jangan asumsikan" di atas itu penting: bahkan penulis plan ini sempat salah asumsi sebelum verifikasi langsung.

**Kalau pelaksana Task 7 menemukan fakta lain yang berbeda dari asumsi plan** (mis. ternyata ada elemen `<select>` piket-guru yang di dalam loop `:name` dinamis yang tidak disebutkan di plan) — TIDAK APA-APA, itu tanda analisa Task 7 bekerja sebagaimana mestinya. Sesuaikan penerapan berdasarkan temuan nyata, JANGAN dipaksa mengikuti asumsi plan yang ternyata keliru. Laporkan temuan itu di report task.

## 4. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Task 1 (timezone) HARUS scoped ke `now('Asia/Jakarta')` di titik spesifik** — JANGAN mengubah `config/app.php`/`.env` `APP_TIMEZONE` secara global. Ini SENGAJA ditolak (§3 spec) karena blast radius terlalu besar (berdampak ke SELURUH sistem: setiap timestamp, setiap modul lain), butuh audit tersendiri. Kalau tergoda "sekalian benerin global saja", STOP — itu scope creep berisiko besar di luar apa yang diminta.
- **Task 1 WAJIB verifikasi dulu interaksi `Carbon::setTestNow()` + `now('Asia/Jakarta')`** lewat eksperimen `tinker` (sudah dirinci di plan Task 1 Step 1) SEBELUM menulis assertion test — jangan diasumsikan otomatis benar.
- **Task 4 (`diisi_oleh_guru_id`) PALING BERISIKO REGRESI** — mengubah Action inti yang dipakai SEMUA alur isi jurnal (piket maupun bukan). WAJIB jalankan `tests/Feature/Guru` PENUH (bukan cuma file piket) sebagai regresi, sudah dirinci di plan Task 4 Step 6.
- **Task 3 (kalender admin) HARUS aditif** — JANGAN mengganti/menghapus variabel `overrides` yang sudah ada dan dipakai form override existing.
- **4 item SENGAJA TIDAK masuk scope, JANGAN dikerjakan**: perubahan `APP_TIMEZONE` global, validasi semester overlap, race condition locking (`lockForUpdate`), item Fase 2 spec lama (LaporanPiket dkk).
- **JANGAN sentuh `app/Domains/Workflow/*`** sama sekali.

## 5. Fakta Operasional

- **Test file fitur ini pakai Pest function-style** (`it(...)`, helper function biasa seperti `siapkanSesiDanGuruPiketUntukAksesTest()`, `siapkanGuruPiketDanSesiUntukAkuntabilitasTest()`) — dikonfirmasi dari pembacaan langsung. 8 file test piket + 9 file test `JurnalKbm*` terkait, semua path lengkap tercantum di plan Task 8 Step 1.
- **Helper test existing yang WAJIB direuse**: `siapkanSesiDanGuruPiketUntukAksesTest()` (`JurnalKbmPiketAksesTest.php`, dipakai Task 2 & Task 6), `siapkanGuruPiketDanSesiUntukAkuntabilitasTest()` (`JurnalKbmDiisiOlehGuruTest.php`, dipakai Task 4).
- **`array_filter` di Task 4 sudah diverifikasi sintaksnya benar** lewat eksperimen `php -r` sebelum spec ditulis (bukan cuma dibaca sekilas) — perilakunya: buang key `diisi_oleh_guru_id` dari payload SAAT nilainya `null`, sisanya tetap masuk.

## 6. Instruksi Stop-and-Report

- **Kalau test baru di Task manapun ("harus GAGAL sebelum fix") ternyata sudah PASS** — STOP, laporkan detail, jangan asumsikan "berarti sudah aman".
- **Kalau full suite (Task 8 Step 3) menunjukkan kegagalan APA PUN di luar 3 yang sudah dikonfirmasi pre-existing** (`M3DemoDataSeederTest` x2, `SubjekTenantValidationTest`) — STOP TOTAL, laporkan detail SEBELUM melanjutkan.
- **JANGAN jalankan full suite bersamaan dengan proses test lain yang sedang berjalan** — insiden nyata pernah terjadi di sesi ini (audit Pengadaan), 2 proses test paralel ke database test yang sama menghasilkan kegagalan palsu massal.
- **Kalau Task 4 Step 5 (verifikasi test lama "TETAP null") GAGAL** — STOP TOTAL, jangan lanjut ke task lain. Ini task paling berisiko regresi di seluruh plan, kegagalan di titik ini berarti asumsi desain `array_filter` salah dan perlu didiskusikan ulang, bukan langsung ditambal sembarangan.
- **Kalau Task 6 (test regresi `resolveKartu()` piket) GAGAL** — ini BUKAN test TDD biasa (fitur sudah ada, cuma menutup gap cakupan test). Kalau gagal, itu tanda ada bug nyata yang sebelumnya tidak terdeteksi. STOP, laporkan detail, JANGAN modifikasi test supaya lolos tanpa investigasi.

## 7. Catatan Serah Terima

- Spec ditulis dan direview 4 kali dalam sesi yang sama. Plan ditulis dan direview 4 kali, DAN mengalami 1 koreksi nyata pasca-self-review saat verifikasi pra-commit (lihat §3 di atas) — bukti bahwa proses verifikasi "jangan asumsikan, baca kode aslinya" ini bekerja dan penting diikuti pelaksana juga.
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (Task 8 eksplisit hanya verifikasi + laporan) — kalau user memang menghendaki handoff log terpisah setelah eksekusi, itu permintaan tambahan mengikuti pola proyek-proyek sebelumnya di sesi ini.
- Setelah semua task selesai, JANGAN merge/push branch — keputusan terpisah milik user.

## 8. Mulai dari mana

Task 1-6 saling independen (file berbeda-beda), bisa urutan bebas. Task 7 WAJIB setelah Task 1-6 selesai (menyentuh file visual yang sama seperti Task 3/5, ada PRASYARAT eksplisit di plan). Task 8 wajib terakhir.

Disarankan mulai dari **Task 1** (timezone — dampak paling langsung ke gerbang akses, dan hasil analisa `tinker`-nya berguna sebagai konteks buat task lain yang menyentuh logic tanggal) di `.agents/plans/2026-09-11-jadwal-piket-guru-audit-perbaikan.md`.
