# Kickoff: Audit & Perbaikan Menu RPP (Workflow, Wording, Backend)

**Base commit**: `f997da23` (`docs(rpp): implementation plan audit RPP -- workflow, wording, backend (10 task)`)
**Branch**: `akademik-v2` (SETARA `rbac-v2`, tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit mendalam menu RPP (Perangkat Ajar) menemukan **1 bug kritis** dan 9 celah wording/UX/backend minor — dipicu permintaan eksplisit user untuk audit lebih dalam khusus workflow (Draft → Diajukan → Disetujui/Perlu Revisi), memastikan UI/UX dan wording sesuai kondisi backend sebenarnya ("jangan sampai user bingung").

**Temuan kritis (Item A / Task 1)**: tab "Perangkat Ajar Saya" (tab DEFAULT, tanpa perlu utak-atik URL) menampilkan RPP **SEMUA GURU** di lembaga untuk aktor tanpa profil Guru — yaitu role `operator_akademik`, `kepala_sekolah`, `wakasek_kurikulum` (SEMUA staf non-mengajar yang punya `rpp.view`). Root cause: `ListRppAction` melewati filter `where('guru_id', ...)` sepenuhnya kalau `$user->guru === null`, alih-alih menampilkan kosong. **Dibuktikan empiris** (test HTTP sementara, dibuat lalu dihapus setelah verifikasi): operator tanpa profil Guru melihat RPP milik guru lain, dan untuk `operator_akademik` (satu-satunya dari 3 role itu yang juga punya `rpp.kelola`) tombol Edit/Hapus/Ajukan bahkan MUNCUL di baris itu — walau backend tetap 403 saat diklik (`authorizeMilikGuru()` sudah benar). **Backend aman, UI menyesatkan.**

**Item B**: `ListRppAction` memakai `TenantContext::activeLembagaId()` (class BERBEDA dari `ResolveLembagaScopeTrait` yang dipakai semua menu lain sepanjang sesi audit ini) yang TIDAK validasi ulang `session('active_lembaga_id')` terhadap yayasan aktor — menyebabkan verifikator level-yayasan dengan session basi melihat Inbox Verifikasi KOSONG SALAH. `TenantContext` dipakai di 13 file LAIN (Sarpras, Pengadaan) — kemungkinan bug sama ada di sana, TAPI itu di luar scope, sesuai arahan user fokus RPP dulu.

**Item C-I**: wording "Waka Kurikulum" yang salah (3 role bisa verifikasi, bukan cuma 1), empty-state tidak sadar filter, dropdown Status berpotensi membingungkan verifikator, keterangan KPI, badge scope, 2 gap backend minor (validasi lembaga di UpdateRppRequest, urutan hapus-file-sebelum-commit).

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-09-rpp-audit-workflow-perbaikan.md` — spec lengkap 10 item, SUDAH melalui 2 putaran koreksi (baca bagian "KOREKSI dari draf pertama" di Item F dan catatan `$adaFilterAktif` di Item D — penting untuk paham KENAPA strukturnya begini).
2. `.agents/plans/2026-09-09-rpp-audit-workflow-perbaikan.md` — 10 task TDD, kode lengkap tiap step, sudah self-review (termasuk 1 koreksi tambahan saat plan ditulis: syntax factory yang salah di test Task 8, dan penyederhanaan test Task 1 setelah disadari 1 test jadi tautologis dengan yang lain).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Task 1 WAJIB diselesaikan & di-commit PALING AWAL** — ini kebocoran data lintas-guru (privasi), prioritas tertinggi di plan ini.
- **Task 2 dan Task 3 WAJIB selesai SEBELUM Task 4** — Task 4 (badge scope) mengasumsikan `$targetLembagaId` sudah tervalidasi (Task 2) DAN struktur `compact()`/return `view()` cabang ajax sudah dilengkapi (Task 3). Kalau urutan dibalik, kode Task 4 di plan (yang sudah mengasumsikan hasil akhir Task 3) tidak akan cocok dengan kode aktual.
- **`TenantContext` untuk 13 file LAIN di luar RPP TIDAK disentuh** — backlog terpisah, sesuai arahan eksplisit user fokus RPP dulu. JANGAN "sekalian" memperbaiki file Sarpras/Pengadaan lain meski pola bugnya sama.
- **Jalur "admin membuat RPP atas nama guru" (verifikasi guru mengajar kombinasi kelas+mapel) TIDAK diubah** — ini pertanyaan produk yang BELUM dijawab user, di luar scope plan ini.
- **Modal create/edit/verify RPP TETAP pakai POST + reload halaman penuh** — TIDAK dimodernisasi ke AJAX di plan ini, itu pekerjaan besar terpisah.
- **`$stats` KPI TIDAK diubah agar ikut filter kontrol** — Task 7 CUMA menambah teks keterangan, JANGAN mengubah query `ListRppAction` untuk membuat stats ikut filter search/kelas/dst.
- **Task 9 (urutan hapus file) adalah REFACTOR murni** — perilaku observable (file lama terhapus, file baru tersimpan) TIDAK BOLEH berubah, cuma urutan internal. Test Task 9 Step 1-2 SENGAJA memverifikasi baseline LULUS SEBELUM refactor (bukan pola TDD gagal-dulu biasa) — kalau baseline gagal, itu tanda pemahaman kode salah, STOP dan lapor.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **Test home**: SEMUA test baru masuk ke `tests/Feature/Akademik/RppWorkflowTest.php`. File ini SUDAH punya `beforeEach()` dengan setup lengkap (`$this->yayasan`, `$this->lembaga`, `$this->tahunAjaran`, `$this->semester`, `$this->kelas`, `$this->mapel`, `$this->userGuru` role `guru`, `$this->guru`, `$this->userKurikulum` role `wakasek_kurikulum` dengan `rpp.view`+`rpp.kelola`+`rpp.verify`) — PAKAI ULANG context ini, JANGAN duplikasi setup. Permission `rpp.view`/`rpp.kelola`/`rpp.verify` SUDAH di-`firstOrCreate` di `beforeEach()`.
- **`RppControllerIdorTest.php`, `RppKurikulumReportingTest.php`, `StoreRppRequestKelasSemesterTest.php`, `RppVerifyTest.php` TIDAK boleh regresi** — dijalankan di beberapa task sebagai regresi wajib, terutama Task 1 (banyak test IDOR kepemilikan) dan Task 8 (validasi Store vs Update).
- **`Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, ...])` TIDAK butuh `withoutGlobalScopes()`** — factory biasa sudah cukup, `lembaga_id` eksplisit otomatis melewati auto-assign `BelongsToTenant`. JANGAN panggil `withoutGlobalScopes()` di factory (method itu tidak ada di Factory, akan fatal error) — sempat jadi kesalahan di draf plan, sudah diperbaiki sebelum commit.
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 10.

## 5. Instruksi Stop-and-Report

- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode, catat perbedaannya di laporan task.
- **Kalau Task 9 Step 2 (baseline test SEBELUM refactor) GAGAL** — STOP TOTAL sebelum lanjut ke Step 3, laporkan ke user (berarti pemahaman kode `UpdateRppAction` saat ini salah, bukan sekadar bug biasa).
- **Kalau Task 1 Step 5 atau Task 8 Step 5 (regresi `RppControllerIdorTest`) menunjukkan KEGAGALAN APA PUN** — STOP TOTAL, laporkan detail ke user SEBELUM melanjutkan (sinyal perubahan mengganggu proteksi IDOR existing yang sudah teruji).
- **Kalau Task 10 Step 1 (regresi penuh domain RPP) menunjukkan kegagalan DI LUAR yang disebutkan di atas** — investigasi dulu apakah terkait; kalau tidak terkait (pre-existing flaky), catat sebagai temuan terpisah, JANGAN diperbaiki diam-diam tanpa lapor.

## 6. Catatan Serah Terima

- Spec ini lahir dari permintaan user yang SANGAT eksplisit soal kualitas: "RPP menggunakan modul workflow, ui uxnya pun harus sesuai, wordingnya juga. jangan sampai user bingung" — perlakukan SEMUA item (bukan cuma Item A yang kritis) dengan bobot serius, terutama Item C-F (wording/UX workflow) yang jadi fokus eksplisit permintaan user.
- Spec SUDAH melalui 2 putaran audit-ulang eksplisit ("review ulang") — 3 kekeliruan nyata ditemukan & diperbaiki SEBELUM plan ditulis (klaim salah soal `$stats` di Item F, `$adaFilterAktif` kurang cek status di Item D, ambiguitas penggabungan Item D+G/Task3+Task4 di cabang ajax). Plan yang ada SEKARANG sudah mengandung versi yang benar.
- Root-cause besar `TenantContext` (13 file lain) TETAP di luar scope juga di sini — SAMA SEPERTI keputusan `TenantScope` platform-scope di semua spec sebelumnya di rangkaian audit ini.
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (lihat Task 10 Step 4) — kalau user memang menghendaki di sesi ini, itu permintaan tambahan terpisah.
- Setelah Task 10 selesai, JANGAN merge branch `akademik-v2` ke branch manapun — keputusan terpisah milik user.

## 7. Mulai dari mana

Mulai dari **Task 1** (fix kritis kebocoran tab Saya) di `.agents/plans/2026-09-09-rpp-audit-workflow-perbaikan.md`, kerjakan berurutan Task 1 → 10. Task 5, 6, 7, 8, 9 boleh ditukar urutan kalau perlu (independen satu sama lain), TAPI Task 1 harus paling awal, dan Task 2+3 harus selesai sebelum Task 4.
