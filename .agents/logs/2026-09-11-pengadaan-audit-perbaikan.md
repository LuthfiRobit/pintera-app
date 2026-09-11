# Handoff Log: Perbaikan Audit Menyeluruh Modul Pengadaan & LPJ Sarpras

- **Tanggal**: 11 September 2026
- **Branch**: `rbac-v2`
- **Spec**: `.agents/specs/2026-09-11-pengadaan-audit-perbaikan.md`
- **Plan**: `.agents/plans/2026-09-11-pengadaan-audit-perbaikan.md`
- **Kickoff**: `.agents/kickoff/2026-09-11-pengadaan-audit-perbaikan-kickoff.md`

---

## 1. Apa yang Dikerjakan

Menutup 14 temuan hasil 2 audit paralel (backend/workflow/scope + frontend/UI-UX/wording) pada modul Pengadaan & LPJ Sarpras — mencakup 4 sub-halaman (proposal pengaju lembaga, inbox approval yayasan, pencairan dana, audit LPJ) di semua role terkait.

### Critical — Keamanan (IDOR)

1. **LPJ bisa diisi untuk proposal lembaga lain** (`app/Http/Controllers/Lembaga/Pengadaan/LpjPengadaanController.php`) — `create()`/`store()` tidak punya guard kepemilikan lembaga sama sekali. Ditambahkan `abort_unless($proposal->lembaga_id === $this->tenantContext->activeLembagaId(), 404)` di kedua method, pola identik dengan `stagingInventory()`/`convertInventory()` di file yang sama.
2. **`TenantContext::activeYayasanId()` fail-open ke yayasan pertama di database** (`app/Domains/Shared/Context/TenantContext.php`) — fallback `Yayasan::first()?->id` diganti `null` (fail-closed). Dampak berantai: 4 controller (`AuditLpjController`, `DisbursementPengadaanController`, `ApprovalPengadaanController`, `RekapAsetGlobalController`) yang punya pola duplikat `?? Yayasan::first()?->id` dibersihkan jadi `abort_if($yayasanId === null, 403, 'Akun Anda belum terhubung ke yayasan manapun...')`.

### Critical — UI

3. **Filter pencarian/status rusak total** di Daftar Proposal & Inbox Approval — `id="wadah-daftar-tabel"` diganti `x-ref="tableContainer"` (JS `data-table-filter.js` mencari `$refs.tableContainer`, bukan elemen ber-`id`), menyamakan pola dengan Disbursement/Audit LPJ yang sudah benar.
4. **Badge status jatuh ke abu-abu** untuk status InReview/Rejected/Disbursed — komponen `<x-badge>` tidak mengenal tone `purple`/`rose`/`indigo` yang dipakai enum Pengadaan. Ditambahkan additive ke `$tones`, 6 tone lama tidak diubah.
5. **Value urgensi salah di form Edit Proposal** (`"darurat"` seharusnya `"kritis"`) — proposal Kritis yang diedit bisa diam-diam turun urgensinya jadi Biasa. Diperbaiki jadi `value="kritis"`, teks disamakan filter existing.

### High

6. **Halaman Audit LPJ salah menampilkan "sudah diverifikasi"** untuk LPJ berstatus RevisionRequired — dipecah jadi 3 cabang `@if/@elseif/@else` eksplisit, cabang baru menampilkan catatan penolakan + status "menunggu unggah ulang".

### Medium

7. **LPJ RevisionRequired buntu** (temuan terbesar, 4 sub-bagian) — sekolah tidak pernah tahu alasan penolakan LPJ, wajib upload ulang SEMUA foto walau cuma 1 item bermasalah:
   - Banner amber + catatan auditor ditampilkan di `proposal/show.blade.php`, tombol berlabel kondisional "Perbaiki & Kirim Ulang LPJ".
   - `StoreLpjRequest`: validasi foto jadi kondisional (`nullable` + cek manual di `withValidator` — wajib HANYA kalau tidak ada file lama untuk item itu).
   - `LpjPengadaanController::store()`: pertahankan path foto lama kalau tidak ada file baru diunggah (bukan overwrite jadi `null`).
   - `lpj/create.blade.php`: prefill dari `$proposal->lpj->items` (bukan reset ke estimasi), tampilkan link "lihat file tersimpan", hapus `required` HTML.
   - **Submit LPJ pertama kali TETAP wajib upload semua foto** (tidak ada `$proposal->lpj` existing) — behavior ini tidak berubah, diverifikasi lewat test regresi.
8. **Catatan wajib diisi saat menolak/minta revisi** (proposal & LPJ) — `notes`/`catatan_verifikasi` jadi `required_if` action Reject/RequestRevision atau `is_approved=0`.
9. **Form review item tidak reflect histori** — state awal Alpine `decisions` sekarang membaca `item->status_item`/`catatan_reviewer` yang sudah ada, bukan selalu default `approved`.

### Low-Medium & Low

10. Route `destroy` proposal dead code → `->except(['destroy'])` di resource route.
11. Wording "Proposal/Usulan/Pengajuan" & "Isi/Unggah LPJ" diseragamkan.
12. 4 dropdown statis/filter dikonversi ke `<x-select>` sesuai konvensi proyek.
13. A11y `label for=/input id=` mismatch diperbaiki di 4 file.
14. Empty-state Disbursement & Audit LPJ disamakan pola (ikon+judul+subjudul) dengan Proposal/Inbox.

---

## 2. Keputusan Penting yang Diambil

1. **Perbaikan §7 (LPJ) HANYA melonggarkan resubmit, bukan submit pertama** — kalau `$proposal->lpj` belum ada sama sekali, `$existingItems` selalu kosong sehingga validasi `required` tetap berlaku penuh. Ini diverifikasi eksplisit lewat test regresi (`test_lpj_requires_scan_nota_and_foto_fisik`, test lama, tetap lolos).
2. **Fail-closed `activeYayasanId()` diterima sebagai perubahan perilaku yang disengaja**, bukan regresi — akun `yayasan_id = null` yang mungkin sudah ada di database akan kehilangan akses ke fitur Pengadaan/Rekap Aset Yayasan (403 dengan pesan jelas), perlu diperbaiki datanya oleh platform admin.
3. **3 item sengaja tidak masuk scope**: validasi nominal `RecordDisbursementAction` vs estimasi disetujui (butuh keputusan produk), preview thumbnail LPJ sebelum submit (nice-to-have), refactor `lpj/create.blade.php` inline `<script>` ke file JS terdaftar (utang teknis terpisah, tidak digabung ke fix LPJ yang sudah menyentuh file sama).
4. **Task `destroy()` TIDAK ditambahkan** — proposal memang tidak pernah didesain bisa dihapus, solusinya mengecualikan route, bukan membangun kapabilitas baru.

---

## 3. Hasil Verifikasi

Seluruh hasil di bagian ini diperoleh dari **review independen** (bukan sekadar mengutip laporan eksekusi) — dijalankan ulang sendiri terhadap kode aktual setelah 11 commit eksekusi selesai.

1. **Test Scoped Pengadaan**:
   - Perintah: `vendor/bin/pest tests/Feature/Pengadaan tests/Unit/Domains/Pengadaan --compact`
   - Hasil: **33 passed (161 assertions)**.
2. **Pint** (file yang diubah): `{"tool":"pint","result":"passed"}` untuk seluruh file domain Pengadaan. Catatan: `routes/admin/pengadaan.php` punya 1 pelanggaran style **pre-existing** (`fully_qualified_strict_types`, `ordered_imports`) yang sudah ada SEBELUM sesi ini menyentuh file itu — bukan regresi dari Task 10 (yang cuma mengubah 1 baris `->except(['destroy'])`), dan tidak muncul di `pint --dirty` (konvensi proyek) karena tidak ada uncommitted diff di file itu.
3. **Full Project Test Suite** — dijalankan **2 kali** (sebelum dan sesudah 1 perbaikan tambahan, lihat poin 4):
   - Percobaan pertama: **3154 passed, 4 failed** — 3 kegagalan pre-existing yang dikenal PLUS 1 kegagalan baru: `Tests\Feature\Sarpras\RekapAsetGlobalTest > superadmin can access dashboard and rekap aset global` (403, seharusnya 200).
   - **Root cause kegagalan ke-4**: test tersebut membuat user `yayasan_super_admin` tanpa mengisi `yayasan_id` sama sekali — sebelumnya lolos karena kebetulan mengandalkan bug fail-open `activeYayasanId()` yang baru saja diperbaiki (fallback ke `Yayasan::first()` yang kebetulan sama dengan yayasan milik test itu sendiri di database test terisolasi). Ini BUKAN alasan membatalkan fix — test-nya yang menguji skenario tidak realistis.
   - **Perbaikan**: `tests/Feature/Sarpras/RekapAsetGlobalTest.php` diberi `'yayasan_id' => $yayasan->id` eksplisit pada pembuatan user (commit `e15af731`), mencerminkan provisioning yang sah (super admin yayasan terikat ke yayasan yang ia kelola).
   - Percobaan kedua (setelah perbaikan): **3155 passed, 3 failed** — kembali ke 3 kegagalan pre-existing yang sudah dikenal sepanjang sesi ini (`M3DemoDataSeederTest` x2, `SubjekTenantValidationTest`). Tidak ada regresi lain.
4. **Insiden proses tambahan yang ditemukan & diselesaikan saat review**: ditemukan 2 entri unmerged (`DU`) di index git (`.superpowers/sdd/task-2-report.md`, `task-5-report.md`) — sisa operasi git yang terinterupsi sebelumnya, di luar proses audit ini. Dibersihkan dengan `git rm --cached` karena `.superpowers/` memang sudah di-gitignore (file scratch/ledger, bukan kode).

---

## 4. Git State & Hal yang Perlu Direview

- **Branch aktif**: `rbac-v2` (local ahead of `origin/rbac-v2`).
- **Commit History untuk Task Ini**:
  - `bb67538a` — `docs(pengadaan): spec perbaikan audit menyeluruh...`
  - `7a2572c0` — `docs(pengadaan): plan perbaikan audit menyeluruh...`
  - `e164dbdd` — `docs(pengadaan): kickoff perbaikan audit menyeluruh...`
  - `1235a389` — `fix(pengadaan): cegah IDOR pada create/store LPJ lintas lembaga`
  - `c171daa7` — `fix(shared): fail-closed TenantContext::activeYayasanId(), hapus fallback fail-open lintas 4 controller`
  - `2ed5eee3` — `fix(pengadaan): perbaiki filter AJAX rusak di Daftar Proposal dan Inbox Approval`
  - `5dd92969` — `fix(ui): tambah tone purple/rose/indigo yang hilang di komponen x-badge`
  - `7d688a9d` — `fix(pengadaan): perbaiki value urgensi 'darurat' yang salah jadi 'kritis' di form edit proposal`
  - `797d6aab` — `fix(pengadaan): perbaiki status card LPJ RevisionRequired di halaman audit LPJ`
  - `dc3f72e2` — `fix(pengadaan): tampilkan catatan penolakan LPJ ke sekolah, izinkan resubmit parsial tanpa upload ulang semua file`
  - `13d08324` — `fix(pengadaan): wajibkan catatan saat menolak/minta revisi proposal atau LPJ`
  - `3ad90474` — `fix(pengadaan): form review item tampilkan histori keputusan sebelumnya, bukan selalu default approved`
  - `bbeda18a` — `fix(pengadaan): hapus route destroy proposal yang dead code`
  - `1bedac71` — `style(pengadaan): samakan wording, pakai <x-select> di dropdown statis/filter, perbaiki a11y label, samakan empty-state`
  - `e15af731` — `fix(test): perbaiki fixture RekapAsetGlobalTest yang tanpa sadar mengandalkan fail-open activeYayasanId()` (ditemukan & diperbaiki saat review pasca-eksekusi)
- **Review Points untuk Manusia/Claude**:
  - Setiap commit fix/style di atas SUDAH diverifikasi diff-nya cocok persis dengan kode di plan — dibaca satu per satu, bukan diasumsikan dari nama commit.
  - Checkbox di `.agents/plans/2026-09-11-pengadaan-audit-perbaikan.md` **tidak ditandai selesai** (`[ ]` semua) walau task-nya sudah dikerjakan — proses eksekusi tidak melakukan langkah "tandai plan selesai" seperti pola project lain di sesi ini. Tidak mempengaruhi kebenaran kode, hanya catatan housekeeping kalau ingin dirapikan.
  - Query dampak operasional yang diminta plan Task 2 Step 6 (`User::whereNull('yayasan_id')->whereHas('roles', ...)->count()`) **tidak tercatat hasilnya** di commit manapun — kalau ingin tahu skala dampak fail-closed di database produksi/staging, perlu dijalankan manual sebelum deploy.
