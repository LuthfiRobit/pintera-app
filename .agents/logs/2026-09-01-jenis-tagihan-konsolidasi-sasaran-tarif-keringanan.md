# Handoff Log — Konsolidasi Jenis Tagihan (Sasaran, Tarif, Keringanan) & Engine Recalculate

**Tanggal**: 2026-09-01  
**Branch**: `keuangan-v2`  
**Base Commit**: `8c88a55a` (awal SDD: `0050f291` / `8c88a55a`)  
**Head Commit**: `05344997`  
**Dokumen Terkait**:
- Spec: `.agents/specs/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md`
- Plan: `.agents/plans/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md`
- Progress Ledger: `.superpowers/sdd/progress-recalc.md`

---

## 1. Apa yang Dikerjakan

Telah diimplementasikan secara menyeluruh 9 Tahap (17 Task) Subagent-Driven Development (SDD) dengan metodologi TDD ketat untuk fitur **Konsolidasi Konfigurasi Jenis Tagihan & Engine Recalculate Otomatis**:

1. **Stage 1 (Task 1)**: Hapus field mati `'lembaga'` dari kriteria sasaran/tarif karena Jenis Tagihan sudah di-scope per lembaga, serta memperketat validasi kriteria `'kelas'` agar hanya menerima ID kelas milik lembaga aktif.
2. **Stage 2 (Task 2)**: Membuat service `TagihanStatusResolver` tunggal untuk menentukan status tagihan (`lunas`, `sebagian`, `belum_bayar`, mempertahankan `dibatalkan`) dan merefaktor `PaymentAllocationService` agar menggunakan resolver ini.
3. **Stage 3 (Task 3–5)**:
   - Migration kolom `perlu_ditinjau_ulang` (boolean, default false) dan `alasan_perlu_ditinjau` (text nullable) pada tabel `tagihan`.
   - Action `RecalculateTagihanNominalAction` dengan guard overpayment (`net_amount` baru < `paid_amount`), guard skema cicilan aktif, guard `tagihable_type === Siswa::class` (no-op untuk PPDB `Pendaftaran`), dan concurrency locking via `lockForUpdate()`.
   - Action `SelesaikanTinjauanTagihanAction` beserta route `POST admin/tagihan/{tagihan}/selesai-ditinjau` untuk membebaskan flag tinjauan setelah ditangani manual oleh bendahara.
4. **Stage 4 (Task 6)**: Migration kolom `priority` pada `jenis_tagihan_sasaran_grup` beserta backfill deterministik berbasis urutan `id` per jenis tagihan, serta mengubah `TagihanNominalResolver::resolveNominal()` untuk mengevaluasi tarif grup berdasarkan `orderBy('priority')`.
5. **Stage 5 (Task 7)**: Kolom `bisa_digabung` pada `kategori_keringanan` dan logic kalkulasi diskon di `TagihanNominalResolver::resolveDiscount()` yang menjumlahkan diskon combinable di atas diskon non-combinable tertinggi dengan batas atas total nominal (`clamp`).
6. **Stage 6 (Task 8)**: Refactor `SyncJenisTagihanBillingConfigAction` agar *diff-aware* (hanya mendeteksi perubahan riil pada nominal tarif atau nilai/tipe keringanan) dan mengembalikan DTO `SyncBillingConfigResult` guna mencegah *recalc storm*.
7. **Stage 7 (Task 9–11)**:
   - Job `RecalculateTagihanNominalJob` untuk recalculate asynchronous 1 job per tagihan.
   - Trigger #2 & #3 di `JenisTagihanController::update()` saat tarif/keringanan berubah.
   - Trigger #1 di `SiswaKeringananController::store()` dan `destroy()` saat keringanan siswa ditambah/dihapus (recalc sinkron dengan query `tagihable_type = Siswa::class`).
   - Trigger #4 via `ReorderTarifGrupAction` dan route `PATCH admin/jenis-tagihan/{jenisTagihan}/tarif-grup/reorder` yang dispatch recalculate langsung saat urutan prioritas tarif digeser.
8. **Stage 8 (Task 12–14)**:
   - `TagihanDirevisiNotification` (database, mail, WhatsApp template `tagihan_direvisi`) yang dikirim ke kontak utama wali murid jika `net_amount` berubah.
   - Activitylog tracking pada model `Tagihan` dan badge counter review di topbar layout.
   - Halaman admin "Tagihan Perlu Ditinjau" (`GET admin/tagihan/perlu-ditinjau`) lengkap dengan tombol penyelesaian review.
9. **Stage 9 (Task 15–17)**:
   - Endpoint Live Preview Target Sasaran (`POST admin/jenis-tagihan/preview-sasaran`).
   - Endpoint Live Preview Tarif & Keringanan (`POST admin/jenis-tagihan/preview-tarif-keringanan`).
   - UI form Jenis Tagihan terintegrasi: counter sasaran siswa dinamis, counter tarif & keringanan, tombol reorder prioritas tarif (&uarr;&darr;), serta modal pembuatan Kategori Keringanan inline.

---

## 2. Keputusan Penting yang Diambil

1. **Polymorphic Tagihable Guard**: `RecalculateTagihanNominalAction` secara eksplisit memeriksa `$tagihan->tagihable_type !== Siswa::class` pada baris pertama dan langsung mengembalikan no-op tanpa exception untuk tagihan PPDB (`Pendaftaran`).
2. **Trigger #1 Query Binding**: Query untuk trigger penambahan/pembatalan keringanan siswa secara ketat menggunakan `where('tagihable_type', Siswa::class)->where('tagihable_id', $siswa->id)`, **bukan** `person_id`, sesuai 11 Critical Decisions.
3. **Pemisahan Reorder Tarif Endpoint**: Urutan prioritas tarif disimpan langsung via API terpisah `PATCH .../tarif-grup/reorder` dan men-dispatch job recalculate independen dari submit form create/edit utama.
4. **Perlindungan Overpayment & Skema Cicilan**: Jika kalkulasi ulang menghasilkan `net_amount` < `paid_amount` atau jika tagihan terhubung dengan skema cicilan aktif (`skemaCicilan()->exists()`), sistem menandai `perlu_ditinjau_ulang = true` beserta deskripsi alasan tanpa memodifikasi nominal tagihan secara sepihak.
5. **Idempotensi Sync Billing Config**: Perubahan nama sasaran atau penataan ulang kriteria yang nilainya sama tidak memicu recalculate tagihan existing.

---

## 3. Hasil Verifikasi & Testing

- **Suite Keuangan**: 352 passing (865 assertions, 0 failed).
- **Suite Keseluruhan**: 2,642 passing (7,218 assertions, 0 failed).
- **Code Style (Laravel Pint)**: 100% lulus format standar project (`vendor/bin/pint --format agent`).
- **Frontend Bundle**: Vite production build (`npm run build`) berhasil tanpa error (`public/build/assets/app-*` up-to-date).

---

## 4. Hal yang Perlu Direview Manusia / Claude

1. **WhatsApp Notification Provider Staging**: Template WhatsApp `tagihan_direvisi` sudah ditambahkan ke seeder `WhatsAppTemplateSeeder`. Pada production, pastikan template tersebut didaftarkan / disetujui di provider WhatsApp Gateway jika menggunakan provider eksternal (seperti Fonnte / Waba).
2. **Status Git Saat Ini**:
   - Branch: `keuangan-v2`
   - Semua perubahan tersimpan rapi dalam commit-commit atomik per task.
   - Belum di-push ke remote (siap untuk direview dan di-merge/push sesuai workflow branch management tim).
