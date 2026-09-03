# Handoff Log: Perbaikan Audit Menyeluruh Modul Akademik — Task 1-9 SELESAI

**Tanggal:** 2026-09-03
**Branch:** `akademik-v2`
**Base commit:** `cd0ecc85`
**HEAD final:** `05acb1e8`
**Spec:** `.agents/specs/2026-09-03-audit-akademik-perbaikan.md`
**Plan:** `.agents/plans/2026-09-03-audit-akademik-perbaikan.md`

> **STATUS: SELESAI TOTAL.** Semua 9 task (Task 9 opsional, tetap dikerjakan sebagai defense-in-depth) sudah diimplementasikan dan direview per-task, DITAMBAH satu final whole-plan review lintas-task yang menemukan 1 bug Important tambahan (lihat §"Final Whole-Plan Review" di bawah) — sudah ditambal dan direview ulang bersih. Full test suite dijalankan sendirian dan diverifikasi independen 2x (oleh implementer dan oleh controller/saya sendiri secara terpisah): **2716 passed → 2717 passed setelah fix terakhir (7422 assertions), 0 failures** kedua kali.

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

## Task 6: Jurnal & Presensi — Dukung Isi Susulan Tanggal Sebelumnya — ✅ SELESAI

**Commit:** `533a3bc` (implementasi awal) + `95524408` (koreksi setelah review)

`JurnalKbmController::index()` sekarang menerima `?tanggal=` (default hari ini, perilaku tanpa parameter TIDAK berubah). Ditambah navigasi tanggal (Sebelumnya/date-picker/Hari Ini) di view.

**Gap ditemukan & ditambal** (kesalahan saya sendiri saat menerjemahkan spec → plan): spec asli (§3.2) mensyaratkan 2 lapis validasi — tolak masa depan DAN tolak sebelum tanggal mulai semester aktif — tapi plan yang saya tulis cuma memuat cek `isFuture()`, lupa batas mundurnya. Reviewer putaran 1 menangkap ini sebagai gap Important terhadap spec asli (bukan cuma brief). Ditambal: cek kedua ditambahkan, fail-open (skip validasi, bukan block) kalau data semester aktif tidak lengkap — supaya tidak mengunci akses guru gara-gara data master kurang.

**Test:** 17/17 `JurnalKbm*` passing setelah koreksi (termasuk test baru utk tolak-sebelum-semester-mulai), dijalankan sendirian.

**Review:** APPROVED bersih setelah koreksi.

---

## Task 7: Halaman Riwayat Persetujuan Rapor — ✅ SELESAI

**Commit:** `acfab2c` (implementasi awal) + `901b39c0` (koreksi #1) + `3479383f` (koreksi #2)

Tab "Riwayat" ditambahkan di halaman Persetujuan Rapor (`?tab=riwayat`) — wakasek/kepsek sekarang bisa melihat kembali pengajuan yang sudah Disetujui/Ditolak (sebelumnya hilang total dari daftar begitu diputuskan). Tab default tanpa parameter tidak berubah perilakunya.

**2 putaran gap ditemukan & ditambal** (bukan bug implementer, murni scope yang kurang lengkap di plan saya):
1. **Putaran 1**: link "Review & Keputusan" di baris tab Riwayat mengarah ke `show()` yang akan 404 (guard lama cuma izinkan status yang PERSIS `statusUntukAktor()` aktor). Ditambal: `show()` sekarang bisa dibuka read-only untuk status Disetujui/Ditolak (form keputusan disembunyikan otomatis untuk kasus ini) — `decision()` (endpoint submit keputusan) TIDAK ikut dilonggarkan, tetap ketat seperti semula.
2. **Putaran 2**: tampilan tanggal keputusan di view read-only salah/kosong untuk kasus reject-langsung-dari-status-Diajukan (kolom `diverifikasi_pada` yang dipakai ternyata tidak pernah terisi di jalur reject itu). Ditambal: sumber tanggal+nama pengambil keputusan diganti pakai log approval terakhir (`approvalRequest->logs`, sudah di-eager-load, tidak ada query N+1 baru) yang andal di semua jalur keputusan, bukan kolom timestamp langsung yang ternyata jalurnya tidak konsisten.

**Test:** 17/17 `RaporPersetujuanControllerTest` + test riwayat, semua passing setelah 2 putaran koreksi, dijalankan sendirian.

**Review:** APPROVED bersih setelah 2 putaran fix — reviewer putaran akhir mengonfirmasi tidak ada temuan baru dan otorisasi tulis (`decision()`) tidak ikut terlonggar oleh perluasan akses baca (`show()`).

---

## Task 8: Cegah Race Condition Approve/Reject Rapor Ganda — ✅ SELESAI

**Commit:** `ce21ed35`

Tambah `lockForUpdate()` pada row `PengajuanRapor` di dalam `DB::transaction()` untuk `ApprovePengajuanRaporAction` dan `VerifyPengajuanRaporAction` — menutup celah row-level lock selain guard status yang sudah ada di `ProcessApprovalAction`. Tanpa lock ini, dua request approve/verify konkuren pada row yang sama secara teoretis bisa lolos guard status bersamaan sebelum salah satunya commit.

**Test:** `tests/Feature/Akademik/RaporApprovalLockTest.php` (baru, 67 baris) — ditambahkan bersama fix, passing.

**Review:** Diselesaikan sebelum Task 9 dimulai (base commit Task 9 = `ce21ed35`, sudah termasuk fix ini).

---

## Task 9 (Opsional, Prioritas Rendah): Hardening Validasi Tenant Eksplisit di Jadwal Pelajaran — ✅ SELESAI

**Commit:** `4f8e3bdd`

Tambah guard tenant eksplisit di awal `JadwalPelajaranController::store()` dan `::update()`, konsisten dengan pola yang sudah ada di `duplicate()`:

```php
$lembagaId = $request->user()->widestScopeLevel() === 'yayasan' ? session('active_lembaga_id') : $request->user()->lembaga_id;
if ($lembagaId) {
    abort_if($kelas->lembaga_id !== $lembagaId, 404);
}
```

Ini murni defense-in-depth — `TenantScope` global sudah menutup akses cross-tenant ini di level model sebelum guard eksplisit ditambahkan (dikonfirmasi: test baru sudah PASS sebelum guard ditambahkan, sesuai ekspektasi brief). Guard dibungkus `if ($lembagaId)` (bukan literal seperti contoh di brief) supaya tidak mematahkan test existing untuk actor yayasan tanpa `active_lembaga_id` di session — detail lengkap ada di `.superpowers/sdd/task-9-report.md`.

**Test:** `tests/Feature/Admin/JadwalPelajaranTenantGuardTest.php` (baru, 1 test) + regresi penuh `JadwalPelajaran` 58/58 passed (164 assertions), dijalankan sendirian.

**Review:** Self-review oleh implementer (task opsional low-priority, tidak ada review terpisah diminta) — lihat `.superpowers/sdd/task-9-report.md` untuk detail lengkap termasuk 1 deviasi dari kode literal brief yang perlu diketahui reviewer mana pun yang membaca ulang.

---

## Ringkasan Final — Semua 9 Task

| Task | Commit | Status | Test |
|---|---|---|---|
| 1. Guard reset rapor | `0b7816e7` | ✅ | 70/70 |
| 2. Indikator Kenaikan Kelas | `b68d430c` | ✅ | 1/1 |
| 3. Guard hapus Kurikulum Assignment | `c1fcddcc` | ✅ | 2/2 + 27 regresi |
| 4. Sembunyikan menu sidebar stub | `1e41adcc` | ✅ | 67/67 |
| 5. Rekap Kehadiran guru mapel | `7949654f`+`ebf7bd1e` | ✅ | 9/9 |
| 6. Jurnal & Presensi susulan tanggal | `533a3bc`+`95524408` | ✅ | 17/17 |
| 7. Riwayat Persetujuan Rapor | `acfab2c`+`901b39c0`+`3479383f` | ✅ | 17/17 |
| 8. Race condition approve/reject rapor | `ce21ed35` | ✅ | test baru + regresi |
| 9. Hardening tenant Jadwal Pelajaran (opsional) | `4f8e3bdd` | ✅ | 58/58 |
| Final review: fix TenantScope bypass Kurikulum Assignment | `05acb1e8` | ✅ | 3/3 |

**HEAD final:** `05acb1e8` (branch `akademik-v2`)

## Final Whole-Plan Review (Lintas-Task) — 1 Temuan Important Ditambal

Setelah Task 9 selesai, dijalankan satu review tambahan atas SELURUH diff plan (16 commit, `cd0ecc85..4f8e3bdd`) khusus mencari interaksi lintas-task yang tidak mungkin ketahuan dari review per-task terisolasi. Ditemukan 1 bug nyata:

**Guard hapus Kurikulum Assignment (Task 3) silently defeated oleh `TenantScope` untuk actor yayasan.** `KurikulumAssignmentController::destroy()` menghitung `Kelas` terdampak sebelum mengizinkan hapus — tapi `Kelas` memakai `BelongsToTenant`/`TenantScope` global scope yang otomatis menambahkan `WHERE lembaga_id = session('active_lembaga_id')` ke SEMUA query terhadapnya. Untuk actor yayasan yang lembaga aktifnya (session) BEDA dari lembaga assignment yang mau dihapus — skenario yang valid, karena `authorizeAssignmentScope()` di controller yang sama memang mengizinkan yayasan actor menghapus assignment lembaga MANAPUN, tidak terikat ke lembaga aktif — query guard jadi efektif `WHERE lembaga_id = B AND lembaga_id = A` → selalu 0 hasil → guard tidak pernah memblokir, membuka kembali persis bug yang Task 3 tujukan untuk ditutup. Test Task 3 yang asli tidak menangkap ini karena cuma pakai actor lembaga biasa (lembaga_id user selalu sama dengan lembaga assignment).

**Ditambal** (`05acb1e8`): query guard sekarang `Kelas::withoutGlobalScope(TenantScope::class)->where('lembaga_id', $kurikulumAssignment->lembaga_id)->...` — bypass scope implisit, pakai `lembaga_id` milik assignment sebagai filter eksplisit yang sudah otentik. Test regresi baru ditambahkan (actor yayasan, lembaga aktif A, coba hapus assignment lembaga B yang masih dipakai Kelas B — assert tetap ditolak). Review ulang: APPROVED, tidak ada efek samping (bypass scope cuma di 1 query ini, tidak di tempat lain).

Area lain yang dicek tapi TIDAK ada temuan baru: interaksi Task 5×6 (JadwalPelajaran vs SesiPembelajaran — beda controller, tidak ada konflik), Task 7×1 (perluasan akses baca `show()` tidak bocor ke status yang harusnya terkunci), Task 7×8 (read-only `show()` tidak butuh lock, tidak konflik dengan `lockForUpdate()`), dan pola guard tenant lain yang ditambahkan plan ini (Task 7 riwayat, Task 9 JadwalPelajaran) — keduanya aman dari kelas bug yang sama.

## Full Test Suite (Final Step, dijalankan 2x independen)

1. Setelah Task 9 (`4f8e3bdd`): **2716 passed (7418 assertions), 0 failures**, dijalankan sendirian oleh implementer, lalu diverifikasi ULANG independen oleh controller sesi ini — hasil identik.
2. Setelah fix final whole-plan review (`05acb1e8`): **2717 passed (7422 assertions), 0 failures**, dijalankan sendirian.

Keduanya dijalankan dengan `ps aux | grep artisan` dicek bersih dulu (tidak ada proses `php artisan test` lain bersamaan). `vendor/bin/pint --dirty --format agent` dijalankan setelah masing-masing — tidak ada perubahan format yang perlu di-commit di kedua titik itu.

## Catatan Pola Berulang (untuk plan/audit berikutnya)

**~~3 dari 9 task (5, 6, 7) memerlukan koreksi setelah review pertama~~ — KOREKSI, lihat "Putaran Verifikasi Ulang" di bawah: sebenarnya 4 dari 9 (5, 6, 7, DAN 2).** Bukan karena implementer salah kerja, tapi karena PLAN yang ditulis kurang lengkap dibanding spec asli, atau melewatkan file/skenario terkait. Untuk audit/plan berikutnya: baca ulang spec asli secara utuh sebelum menganggap brief plan sudah lengkap, dan pertimbangkan file-file/skenario terkait yang mungkin tidak eksplisit disebut di plan tapi terpengaruh perubahan — termasuk MENJALANKAN skenario yang diklaim "sudah aman" (bukan cuma membaca kode), karena reviewer Task 2 salah menyimpulkan "tidak ada yang perlu dikecualikan" tanpa benar-benar mencoba submit form dengan baris kosong.

**Catatan terbuka (belum jadi task resmi, di luar scope plan ini, JANGAN dikerjakan tanpa instruksi user)**: `bottom-nav.blade.php` (mobile) masih punya label stub yang sama seperti yang disembunyikan di Task 4 — sudah ditanyakan ke user 2026-09-03, user menjawab "abaikan saja". Tetap dicatat di sini untuk histori, TIDAK PERLU ditanyakan lagi atau dikerjakan di masa depan kecuali user mengubah keputusan.

Ledger progress ada di `.superpowers/sdd/progress.md` (git-ignored, scratch).

---

## Putaran Verifikasi Ulang (Post-SELESAI-TOTAL, diminta user 2026-09-03)

User secara eksplisit meminta review ulang lebih skeptis terhadap klaim "3 dari 9 task perlu koreksi", curiga masih ada gap lain yang lolos. Dilakukan pengecekan ulang SETIAP requirement di spec asli (bukan cuma plan) terhadap kode final, per baris demi baris untuk file-file frontend (§2.1, §2.3, §2.4, §3.1, §3.2, §3.3) — semuanya CONFIRMED PRESENT dan sesuai, TIDAK ada gap baru di area itu.

Ditemukan 2 gap nyata tambahan lewat pengujian aktif (bukan cuma baca kode):

1. **Task 2 (Kenaikan Kelas) — gap yang lolos dari reviewer aslinya.** Spec §2.2 minta kelas kosong "jangan centang/include secara default di form submit massal". Reviewer Task 2 memeriksa kode dan menyimpulkan "tidak ada checkbox pilih-massal di halaman ini, jadi bagian ini tidak berlaku" — kesimpulan yang BENAR secara literal (memang tidak ada checkbox) tapi SALAH secara substansi: form mapping tetap mewajibkan setiap baris kelas (termasuk yang kosong) diisi `kelas_baru_id` kalau tindakan defaultnya "naik" (`required_if` validation) — dibuktikan dengan test probe langsung (POST submit dengan kelas kosong tanpa `kelas_baru_id`) yang mengonfirmasi submission GAGAL dengan validation error, memblokir SELURUH proses kenaikan kelas massal kalau ada 1 saja kelas kosong yang tidak disentuh admin. Ditambal: opsi baru `"lewati"` di select tindakan, dipilih default untuk `siswa_count === 0` (admin tetap bisa override manual), `ProsesKenaikanKelasAction` skip baris ber-tindakan `lewati` tanpa efek. Commit `0e8d4405`. Test baru: 2 test UX (default-selected + submit-massal-tanpa-error) + 1 test lama (`KenaikanKelasControllerUxTest` skenario tingkat-akhir) diperbaiki fixture-nya (tadinya kebetulan siswa_count=0 juga, jadi sekarang butuh Siswa eksplisit supaya tetap menguji jalur naik/lulus, bukan jalur lewati yang baru).
2. **`JurnalKbmController::index()` — robustness gap yang pernah dicatat Minor oleh reviewer Task 6 tapi tidak pernah ditambal.** `Carbon::parse($tanggalInput)` pada string bukan-tanggal (mis. `?tanggal=bukan-tanggal`) melempar exception tak tertangani → 500, bukan redirect-dengan-pesan seperti 2 validasi lain di method yang sama (masa depan / sebelum semester). Ditambal: bungkus `try/catch`, redirect dengan `session('error')` yang jelas. Commit `76115a80`. Test baru: 1 test di `JurnalKbmTanggalSusulanTest.php`.

Item lain yang dicek dan CONFIRMED tidak ada gap: §2.1 banner+disable tombol (`catatan/index.blade.php`), §2.3 link resync di `kurikulum-assignment/index.blade.php`, §2.4 sidebar comment-out (`sidebar.blade.php`, commit `1e41adcc`, sudah benar), §3.1 indikator rekap difilter (`rekap.blade.php:83-86`), §3.2 navigasi tanggal (`jurnal-kbm/index.blade.php:24-26`), §3.3 tab Riwayat + `show()` read-only (`persetujuan/index.blade.php`, `show.blade.php`), §3.4 `lockForUpdate()` + test race condition (`RaporApprovalLockTest.php`).

**HEAD final (setelah putaran verifikasi ulang):** `0e8d4405` (branch `akademik-v2`). Full test suite dijalankan ulang standalone (dicek dulu tidak ada proses `php artisan test` lain aktif): **2720 passed (7431 assertions), 0 failures** — persis +3 dari 2717 sebelumnya (3 test baru dari 2 fix di atas). `vendor/bin/pint --dirty --format agent` bersih di kedua commit.

**Pelajaran untuk audit berikutnya**: kesimpulan reviewer "tidak ada X di kode, jadi requirement Y tidak berlaku" HARUS diverifikasi dengan mencoba skenario end-to-end (submit form sungguhan), bukan cuma pembacaan statis — gap Task 2 lolos persis karena baik implementer, reviewer, maupun review akhir plan semuanya cuma membaca struktur form tanpa mencoba submit baris kosong yang tidak disentuh.

**Plan `.agents/plans/2026-09-03-audit-akademik-perbaikan.md` dianggap SELESAI TOTAL** per putaran verifikasi ulang ini (per 2026-09-03), dengan 2 gap tambahan di atas sudah ditambal dan diregresi.
