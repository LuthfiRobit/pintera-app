# Kickoff: Perbaikan Audit SDM Izin/Cuti (Crash Pool, HTML Rusak, Pencarian Alasan, Riwayat Admin)

**Base commit**: `777e01dd` (`docs(sdm): plan perbaikan audit izin/cuti...`)
**Branch**: `rbac-v2` (TETAP di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit lanjutan modul SDM Izin/Cuti (2 putaran: scope yayasan/lembaga, lalu UI/UX+wiring) menemukan 4 masalah nyata, SEMUANYA murni domain SDM (BUKAN engine `Workflow` bersama — beda dari 3 perbaikan sebelumnya di sesi ini):

1. **Crash 500** — pegawai pool yayasan (`Karyawan.lembaga_id` NULL, pola sah yang sudah didukung sistem) mengajukan izin/cuti mandiri → `QueryException` tidak tertangani karena `pengajuan_izin_cuti.lembaga_id` NOT NULL.
2. **HTML rusak** — empty-state tabel riwayat self-service pegawai, `<tr><td>` tidak pernah ditutup sebelum `@endforelse`.
3. **Fitur pencarian "alasan" tidak berfungsi** — label kolom pencarian admin bilang "Cari Pegawai / Alasan" tapi field `alasan` tidak pernah dikirim ke JS/tidak pernah dicocokkan.
4. **Tidak ada riwayat/arsip admin** — begitu pengajuan diputuskan (Approved/Rejected/dst), hilang selamanya dari daftar admin, tidak ada cara menemukannya lagi dari UI manapun.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-11-sdm-izin-cuti-audit-perbaikan.md` — spec lengkap, 4 putaran self-review, termasuk penjelasan KENAPA perbaikan crash pool TIDAK mendesain ulang supaya pool karyawan benar-benar bisa diproses (§2.1 — butuh step workflow baru berscope yayasan, keputusan produk terpisah).
2. `.agents/plans/2026-09-11-sdm-izin-cuti-audit-perbaikan.md` — 4 task TDD, kode lengkap tiap step, 3 putaran self-review (termasuk verifikasi langsung ke `WorkflowDefinitionSeeder` bahwa urutan role `kepala_sekolah` → `admin_sdm` benar, dan penambahan Step `npm run build` yang tidak eksplisit di spec tapi WAJIB supaya perubahan JS Alpine benar-benar berlaku di browser).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Perbaikan crash pool (Task 1) HANYA menutup crash** (ganti 500 jadi pesan error jelas) — BUKAN membangun kapabilitas baru supaya pegawai pool BENAR-BENAR bisa mengajukan & diproses sampai selesai. Kalau menemukan diri berpikir "lebih baik bikin lembaga_id nullable saja di migration", STOP — itu akan membuat pengajuan macet permanen di step approval (fail-closed `ApproverResolverService` akan selalu menolak karena `$request->approvable?->lembaga_id` tetap NULL), bukan solusi, cuma memindahkan masalah dari "crash saat submit" jadi "macet setelah submit". Sudah dipertimbangkan dan ditolak di spec §2.1 dengan alasan konkret.
- **JANGAN sentuh `app/Domains/Workflow/*`** — ke-4 perbaikan ini murni domain SDM.
- **JANGAN wire `ApprovalStatus::RevisionRequired` ke UI SDM** — ini state yang saat ini TIDAK PERNAH tercapai (jalur satu-satunya, `ApprovalIzinCutiController::decision()`, cuma menerima APPROVE/REJECT). Menambah tombol "Minta Revisi" adalah PENAMBAHAN FITUR, bukan bug fix — butuh keputusan produk terpisah, di luar scope plan ini. Kalau tergoda menambahkannya karena "kelihatan mudah sambil di sini", JANGAN — itu scope creep.
- **Task 3 (Step 3 di plan) — query `index()` HARUS MENGGANTI TOTAL** filter `whereIn('status', [Pending, InReview])` lama dengan `whereHas('approvalRequest')` tanpa filter status, BUKAN ditambahkan di atas filter lama. Kalau cuma ditambah, hasilnya identik dengan sebelum diperbaiki (riwayat tetap tidak pernah muncul) — ini jebakan yang gampang kelewat kalau tidak hati-hati membaca instruksi "ganti", bukan "tambah".
- **Perilaku pill kategori (Semua/Cuti/Sakit/Izin) SENGAJA berubah** ikut `itemsInView` (mengikuti tab Menunggu/Riwayat aktif) — BUKAN bug, jangan "diperbaiki" balik ke total keseluruhan.
- **Stat card "Menunggu Approval" SENGAJA TIDAK ikut berubah** oleh tab aktif (pakai getter `totalPending` terpisah) — supaya admin tetap tahu ada berapa pending walau sedang buka tab Riwayat.
- **Pesan error Task 1 WAJIB teks PERSIS**: `'Pengajuan izin/cuti mandiri belum didukung untuk pegawai pool yayasan (tanpa lembaga tetap). Silakan hubungi admin SDM untuk memprosesnya secara manual.'` (field key `'pegawai'`) — jangan diringkas/diparafrase.

## 4. Fakta Operasional

- **Urutan step workflow `IZIN_CUTI_SDM`** (dikonfirmasi langsung dari `database/seeders/WorkflowDefinitionSeeder.php:85-115` sebelum kickoff ini ditulis): step 1 = `kepala_sekolah` (`is_final_step: false`), step 2 = `admin_sdm` (`is_final_step: true`). Test baru di Task 3 Step 1 memakai urutan approve ini — SUDAH BENAR, tidak perlu dicek ulang.
- **Factory pool karyawan**: `Karyawan::factory()->pool()->create()` (state sudah ada, `database/factories/KaryawanFactory.php:61-66`, set `lembaga_id => null`).
- **File test yang WAJIB dipakai ulang, JANGAN bikin baru yang fungsinya sama**:
  - `tests/Feature/Sdm/AjukanIzinCutiActionTest.php` (Task 1 — tambah di akhir, sebelum fungsi helper `seedKuotaCutiWorkflowForTest_ajukan()`)
  - `tests/Feature/Admin/ApprovalIzinCutiControllerTest.php` (Task 3 — tambah di akhir file, 70 baris existing)
- **Perubahan JS WAJIB di-build**: `npm run build` setelah mengubah `resources/js/approval-izin-cuti-spa.js` (Task 3 Step 10) — tanpa ini, verifikasi manual browser akan menguji bundle LAMA dan implementer bisa salah simpul "perbaikan tidak berfungsi" padahal cuma belum ter-build.
- **`ApprovalStatus` import di `ApprovalIzinCutiController.php` TETAP DIPAKAI** oleh `show()` (baris 41) setelah `index()` diubah — JANGAN dihapus walau `index()` baru tidak lagi memakainya secara eksplisit lewat `whereIn`.

## 5. Instruksi Stop-and-Report

- **Kalau Task 1 Step 2 (test SEBELUM fix) ternyata sudah PASS** (bukan gagal seperti yang diharapkan) — itu tanda ada sesuatu yang sudah berubah dari asumsi spec (mis. constraint DB sudah berbeda). STOP, laporkan ke user sebelum lanjut, jangan asumsikan "berarti sudah aman, skip saja".
- **Kalau Task 3 Step 2 (test SEBELUM fix) ternyata sudah PASS** — sama, tanda `index()` sudah berbeda dari yang didokumentasikan spec/plan. STOP dan laporkan.
- **Kalau full suite (Task 4 Step 3) menunjukkan kegagalan APA PUN di luar 3 yang sudah dikonfirmasi pre-existing** (`M3DemoDataSeederTest` x2, `SubjekTenantValidationTest`) — STOP TOTAL, laporkan detail ke user SEBELUM melanjutkan atau mencoba memperbaikinya sendiri tanpa konsultasi.
- **Kalau verifikasi manual Task 3 Step 11 menunjukkan toggle/pencarian TIDAK berfungsi setelah `npm run build` berhasil** — STOP, laporkan detail persis apa yang terjadi (bukan cuma "tidak berfungsi") — ini kemungkinan bug baru di logika JS yang perlu dianalisis, bukan diasumsikan "coba build ulang saja".

## 6. Catatan Serah Terima

- Spec ditulis dan direview 4 kali dalam sesi yang sama. Plan ditulis dan direview 3 kali (sesuai permintaan user untuk plan ini — lebih sedikit dari spec karena plan hanya memecah kode yang sudah konkret di spec jadi task TDD, bukan mendesain ulang).
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (Task 4 eksplisit hanya verifikasi + laporan full-suite) — kalau user memang menghendaki handoff log terpisah di sesi ini, itu permintaan tambahan setelah plan selesai (mengikuti pola 3 perbaikan Workflow sebelumnya di sesi ini, di mana handoff log memang selalu diminta terpisah setelah eksekusi).
- Setelah semua task selesai, JANGAN merge/push branch — keputusan terpisah milik user.

## 7. Mulai dari mana

Task 1 (crash pool) dan Task 2 (HTML rusak) SALING INDEPENDEN dan independen juga terhadap Task 3 (riwayat admin+pencarian) — bisa dikerjakan urutan bebas atau paralel. Task 4 (regresi penutup) WAJIB terakhir, butuh Task 1-3 semua selesai.

Disarankan mulai dari **Task 1** (severity paling tinggi, paling kritis, paling independen) di `.agents/plans/2026-09-11-sdm-izin-cuti-audit-perbaikan.md`.
