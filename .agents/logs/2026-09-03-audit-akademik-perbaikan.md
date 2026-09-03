# Handoff Log (Parsial): Perbaikan Audit Menyeluruh Modul Akademik — Task 1-5

**Tanggal:** 2026-09-03
**Branch:** `akademik-v2`
**Base commit:** `cd0ecc85`
**Spec:** `.agents/specs/2026-09-03-audit-akademik-perbaikan.md`
**Plan:** `.agents/plans/2026-09-03-audit-akademik-perbaikan.md`

> **STATUS: PARSIAL.** Dikerjakan lewat `superpowers:subagent-driven-development` sampai Task 5 selesai, sesuai instruksi eksplisit user ("lanjut task 4 dan 5 dulu"). **Task 6-9 BELUM dikerjakan** — masih menunggu instruksi lanjutan. Jangan anggap plan ini selesai; full test suite penuh (2698+ test) juga BELUM dijalankan ulang (itu bagian "Final Step" plan, baru relevan setelah semua 9 task selesai) — tiap task di bawah sudah diverifikasi lewat regresi test area terkait secara terpisah, bukan full suite.

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

## Task 4: Sembunyikan 6 Menu Sidebar Stub (Ruang Siswa & Ruang Orang Tua) — ✅ SELESAI

**Commit:** `1e41adcc`

Comment-out (bukan hapus) 6 item di `resources/views/layouts/sidebar.blade.php`: Ruang Siswa (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya) dan Ruang Orang Tua (Nilai Anak, Jadwal Anak, Riwayat Izin/Sakit Anak) — semuanya sebelumnya mengarah ke halaman stub `dalam-pengembangan`. Item Keuangan (Dompet & Tagihan Saya/Tagihan/Riwayat) dan Kasus Pendampingan di kedua blok yang sama TIDAK disentuh — diverifikasi baris-per-baris oleh reviewer. Test lama `SidebarPengelompokanTest.php` disesuaikan (2 test yang tadinya assert 6 label itu ada), bukan dilemahkan.

**Temuan tambahan (bukan blocker, perlu keputusan terpisah)**: `resources/views/layouts/bottom-nav.blade.php` (navigasi mobile, komponen terpisah dari sidebar) TERNYATA JUGA memuat label placeholder yang sama untuk siswa & sebagian besar item orang tua. Task ini sengaja tidak menyentuhnya (di luar scope brief yang eksplisit cuma sebut `sidebar.blade.php`). **Rekomendasi**: kalau tujuannya konsistensi penuh (stub tidak bisa diakses dari UI manapun), bottom-nav.blade.php perlu task serupa terpisah — belum dikerjakan.

**Test:** 67/67 passed (235 assertions), dijalankan sendirian.

**Review:** APPROVED bersih.

---

## Task 5: Rekap Kehadiran untuk Guru Mapel (Difilter ke Sesinya Sendiri) — ✅ SELESAI

**Commit:** `7949654f` (implementasi awal) + `ebf7bd1e` (koreksi setelah review)

Guru mapel (bukan wali kelas) sekarang bisa membuka Rekap Kehadiran, tapi datanya difilter cuma ke sesi yang dia ajar sendiri. Wali kelas tetap dapat rekap penuh lintas-mapel — perilaku ini TIDAK berubah (diverifikasi lewat 7 test lama `RekapKehadiranControllerTest.php` yang sama sekali tidak tersentuh oleh 2 commit task ini).

**Konflik desain ditemukan & diputuskan di tengah eksekusi** (dicatat di sini supaya tidak terulang kalau ada task lain yang menyentuh area serupa): implementer pertama menentukan "kelas mana yang bisa diakses guru mapel" lewat `SesiPembelajaran` (sesi harian yang sudah digenerate) karena test contoh di brief cuma bikin `SesiPembelajaran`, bukan `JadwalPelajaran`. Ini **ditolak** dan dikoreksi — keputusan final: akses kelas HARUS lewat `JadwalPelajaran` (jadwal resmi semester, pola yang sama dipakai `Guru\KomponenPenilaianController`/`Guru\AsesmenController`), supaya guru yang terjadwal tapi belum sempat isi jurnal hari itu tidak kehilangan akses ke rekap historis kelasnya. Perhitungan/agregasi presensi yang ditampilkan tetap berdasarkan `sesi_pembelajaran.guru_id` (2 concern yang berbeda, sudah dipisah dengan benar di versi final: `PresensiAggregationService::agregasiPerKelas(int $kelasId, ?Semester $semester = null, ?int $guruId = null)`, backward compatible).

**Test:** 9/9 `RekapKehadiran*` (baru + regresi wali kelas) passing setelah koreksi, dijalankan sendirian.

**Review:** APPROVED bersih setelah koreksi (reviewer memverifikasi ulang bahwa 2 concern — akses vs perhitungan — sudah benar terpisah di semua tempat, bukan tertukar).

---

## Ringkasan Sejauh Ini

| Task | Commit | Status | Test |
|---|---|---|---|
| 1. Guard reset rapor | `0b7816e7` | ✅ | 70/70 |
| 2. Indikator Kenaikan Kelas | `b68d430c` | ✅ | 1/1 |
| 3. Guard hapus Kurikulum Assignment | `c1fcddcc` | ✅ | 2/2 + 27 regresi |
| 4. Sembunyikan menu sidebar stub | `1e41adcc` | ✅ | 67/67 |
| 5. Rekap Kehadiran guru mapel | `7949654f`+`ebf7bd1e` | ✅ | 9/9 |

**HEAD saat ini:** `ebf7bd1e` (branch `akademik-v2`)

## Yang Masih Pending (Task 6-9)

- Task 6: Jurnal & Presensi — dukung isi susulan tanggal sebelumnya
- Task 7: Halaman Riwayat Persetujuan Rapor
- Task 8: Cegah race condition approve/reject rapor ganda
- Task 9 (opsional, Prioritas Rendah): hardening validasi tenant eksplisit di Jadwal Pelajaran

**Catatan terbuka (belum jadi task resmi)**: `bottom-nav.blade.php` (mobile) masih punya label stub yang sama seperti yang disembunyikan di Task 4 — perlu keputusan user apakah dibuatkan task serupa.

Ledger progress ada di `.superpowers/sdd/progress.md` (git-ignored, scratch) — kalau sesi berikutnya kehilangan konteks, ledger ini + `git log` adalah sumber kebenaran progress yang sebenarnya.

**Lanjutkan dari Task 6** begitu ada instruksi lanjutan dari user — jangan re-dispatch Task 1-5, sudah selesai dan direview bersih.
