# Handoff Log (Parsial): Perbaikan Audit Menyeluruh Modul Akademik — Task 1-3

**Tanggal:** 2026-09-03
**Branch:** `akademik-v2`
**Base commit:** `cd0ecc85`
**Spec:** `.agents/specs/2026-09-03-audit-akademik-perbaikan.md`
**Plan:** `.agents/plans/2026-09-03-audit-akademik-perbaikan.md`

> **STATUS: PARSIAL.** Dikerjakan lewat `superpowers:subagent-driven-development` HANYA sampai Task 3 selesai, sesuai instruksi eksplisit user ("kerjakan sampai task 3 dulu, tunggu perintah selanjutnya"). **Task 4-9 BELUM dikerjakan** — masih menunggu instruksi lanjutan. Jangan anggap plan ini selesai; full test suite penuh (2698 test) juga BELUM dijalankan ulang (itu bagian "Final Step" plan, baru relevan setelah semua 9 task selesai) — tiap task di bawah sudah diverifikasi lewat regresi test area terkait secara terpisah, bukan full suite.

---

## Task 1: Guard Status — Cegah Reset Rapor yang Sudah Diverifikasi/Disetujui — ✅ SELESAI

**Commit:** `0b7816e7`

`SubmitPengajuanRaporAction::execute()` sekarang menolak (`ValidationException`) kalau `PengajuanRapor` untuk kelas+semester itu sudah berstatus `Diverifikasi` atau `Disetujui` — sebelumnya action ini diam-diam mereset rapor yang sudah disetujui kembali ke `Diajukan` tanpa guard apa pun. Status `Draft`/`Diajukan`/`Ditolak` tetap boleh (re)submit seperti sebelumnya (perilaku normal, tidak berubah).

Ditambahkan juga banner info di `resources/views/portals/guru/rapor/catatan/index.blade.php` untuk status Diverifikasi/Disetujui, dan tombol "Ajukan Rapor" otomatis disabled untuk kedua status itu (kondisi `:disabled` pakai `||`, BUKAN `&&` seperti draf awal brief — brief punya bug logika boolean, sudah dikoreksi implementer dan dikonfirmasi benar oleh reviewer independen).

**Test:** 70/70 passed (131 assertions) — 3 test baru (`SubmitPengajuanRaporActionGuardTest`) + regresi 6 file test Rapor terkait lainnya, dijalankan sendirian.

**Review:** APPROVED bersih (spec compliance + code quality), termasuk verifikasi independen reviewer terhadap 2 deviasi kecil dari brief (fixture seeder tambahan yang memang perlu, dan koreksi logika boolean tombol).

---

## Task 2: Indikator Visual Kelas yang Sudah Diproses di Kenaikan Kelas — ✅ SELESAI

**Commit:** `b68d430c`

Halaman Kenaikan Kelas sekarang menampilkan badge "Sudah diproses / kosong" untuk kelas dengan `siswa_count === 0` (data ini sudah tersedia dari `withCount('siswa')` di controller, tidak ada perubahan controller). Reviewer mengonfirmasi halaman ini tidak punya checkbox pilih-massal yang perlu dikecualikan (cuma checkbox per-baris "salin jadwal" yang tidak relevan dengan guard ini) — jadi bagian brief soal itu memang tidak berlaku, bukan terlewat.

**Test:** 1/1 test baru (`KenaikanKelasIndicatorTest`) passed, dijalankan sendirian & standalone oleh reviewer juga.

**Review:** APPROVED bersih, tidak ada temuan.

---

## Task 3: Blokir Hapus Kurikulum Assignment yang Masih Dipakai Kelas — ✅ SELESAI

**Commit:** `c1fcddcc`

`KurikulumAssignmentController::destroy()` sekarang menolak hapus assignment (redirect dengan `session('error')`) kalau masih ada `Kelas` yang snapshot kurikulumnya cocok dengan assignment itu (`lembaga_id`+`tahun_ajaran_id`+`tingkat`+`kurikulum`) — **kecuali** untuk assignment level nasional (`lembaga_id === null`), yang tetap bisa dihapus tanpa guard ini (dikonfirmasi eksplisit oleh reviewer, logic tidak terbalik). Link ke tool resync (`admin.kurikulum-assignment.resync`, route sudah ada sebelumnya) ditambahkan di halaman edit (index ternyata sudah punya link ini sejak 27 Agustus — dikonfirmasi lewat `git show` oleh reviewer, bukan diklaim mentah-mentah); index ditambah blok `session('error')` supaya pesan guard baru terlihat di UI.

Factory baru `KurikulumAssignmentFactory` dibuat (belum ada sebelumnya) — model `KurikulumAssignment` ikut ditambah `HasFactory`+`newFactory()` override, pola yang sama dipakai ~30 model domain lain (dikonfirmasi lewat grep oleh reviewer, bukan pola baru).

**Test:** 2/2 test baru (`KurikulumAssignmentDestroyGuardTest`) + regresi 24/24 test `KurikulumAssignment` lain + 3/3 `ResyncKurikulumFaseControllerTest`, semua dijalankan sendirian.

**Review:** APPROVED bersih, tidak ada temuan.

---

## Ringkasan Sejauh Ini

| Task | Commit | Status | Test |
|---|---|---|---|
| 1. Guard reset rapor | `0b7816e7` | ✅ | 70/70 |
| 2. Indikator Kenaikan Kelas | `b68d430c` | ✅ | 1/1 |
| 3. Guard hapus Kurikulum Assignment | `c1fcddcc` | ✅ | 2/2 + 27 regresi |

**HEAD saat ini:** `c1fcddcc` (branch `akademik-v2`)

## Yang Masih Pending (Task 4-9)

- Task 4: Sembunyikan 6 menu sidebar stub (Ruang Siswa/Ortu)
- Task 5: Rekap Kehadiran untuk guru mapel (difilter ke sesinya sendiri)
- Task 6: Jurnal & Presensi — dukung isi susulan tanggal sebelumnya
- Task 7: Halaman Riwayat Persetujuan Rapor
- Task 8: Cegah race condition approve/reject rapor ganda
- Task 9 (opsional, Prioritas Rendah): hardening validasi tenant eksplisit di Jadwal Pelajaran

Ledger progress ada di `.superpowers/sdd/progress.md` (git-ignored, scratch) — kalau sesi berikutnya kehilangan konteks, ledger ini + `git log` adalah sumber kebenaran progress yang sebenarnya.

**Lanjutkan dari Task 4** begitu ada instruksi lanjutan dari user — jangan re-dispatch Task 1-3, sudah selesai dan direview bersih.
