# Spesifikasi Teknis: Modul Pengadaan Sarpras & LPJ (Procurement & Dynamic Approval Settlement)

- **Slug:** `modul-pengadaan-dan-lpj`
- **Domain:** `App\Domains\Pengadaan\` & `App\Domains\Workflow\`
- **Status:** Draft / Ready for Plan
- **Tanggal:** 16 Agustus 2026

---

## 1. Tujuan & Latar Belakang

Modul Pengadaan Sarpras & LPJ dirancang untuk menyediakan tata kelola belanja operasional dan fasilitas sekolah yang transparan, akuntabel, serta terintegrasi penuh dengan data master sarana & prasarana. Modul ini menjembatani kebutuhan riil unit sekolah (Lembaga) dengan persetujuan yayasan melalui **Engine Persetujuan Dinamis (*Dynamic Approval Engine*)**, pencairan dana kas, verifikasi LPJ berbasis bukti nota/foto, dan **otomatisasi inventarisasi barang (*Auto-Inventory Onboarding*)** ke ruangan tujuan di Master Sarpras.

---

## 2. Ruang Lingkup (Scope)

### A. In-Scope:
1. **Dynamic Approval Workflow Engine (`App\Domains\Workflow\`):**
   - Skema alur persetujuan yang dapat dikonfigurasi dinamis oleh Super Admin (definisi langkah, role/scope penilai, urutan sekuensial).
   - Default flow pengadaan:
     - **Langkah 1 (Internal Lembaga):** Kepala Sekolah (`kepala_sekolah`).
     - **Langkah 2 (Final Yayasan):** Pengurus / Bendahara Yayasan (`bendahara_yayasan` / `pengurus_yayasan`).
   - Audit trail riwayat persetujuan lengkap (`approval_logs`) mencakup nama penilai, timestamp, status keputusan, dan catatan revisi/alasan penolakan.
2. **Manajemen Pengajuan Pengadaan (`App\Domains\Pengadaan\`):**
   - Pembuatan proposal pengadaan (judul, latar belakang/urgensi, estimasi anggaran).
   - Input item belanja detail: nama barang, spesifikasi, estimasi harga satuan, kuantitas & satuan, target `ruangan_id`, `kategori_aset_id`, tipe pencatatan (`unit` / `batch`), dan referensi foto/link acuan.
   - Mekanisme persetujuan parsial per item dan siklus *Revision & Resubmit*.
3. **Pencairan Dana Kas (*Disbursement*):**
   - Pencatatan tanggal cair, nominal uang cair dari kas yayasan, dan upload bukti transfer / tanda terima kas.
4. **Pelaporan Pertanggungjawaban (LPJ) & Realisasi Anggaran:**
   - Input harga riil dan total belanja per item nota.
   - Upload file scan nota/faktur asli dan foto fisik barang yang tiba di sekolah.
   - Perhitungan otomatis surplus (sisa dana yang wajib dikembalikan) atau defisit anggaran belanja.
5. **Jembatan Otomasi Inventarisasi Sarpras (*Auto-Inventory Onboarding*):**
   - Saat LPJ disetujui (*Settled*), sistem menyiapkan draft staging konversi.
   - Model Hybrid: Auto-split item `unit` menjadi N record barcode unik, dan item `batch` menjadi 1 record kuantitas N di ruangan tujuan.
   - Antarmuka review/edit nomor seri (*Serial Number*) sebelum *instant commit* ke tabel `aset_barang`.
6. **Antarmuka Pengguna (UI/UX Standard):**
   - Portal Lembaga: Form wizard pengajuan, stepper tracking status, form upload LPJ, dan review staging inventaris.
   - Portal Yayasan: Inbox persetujuan masuk, panel approval parsial, form pencairan kas, dan audit verifikasi LPJ.

### B. Out-of-Scope:
- Manajemen lelang vendor multi-kontraktor pihak ketiga (hanya mencakup pengadaan operasional & sarpras internal sekolah).
- Integrasi Payment Gateway otomatis untuk pencairan dana (pencairan dicatat secara mutasi kas/transfer manual oleh bendahara yayasan).

---

## 3. Desain Arsitektur & Struktur Data

### A. Tabel Database & Schema

```text
[ workflow_definitions ] ──< [ workflow_steps ]
          │
[ pengajuan_pengadaan ] ──< [ pengajuan_pengadaan_item ]
          │                           │
[ approval_requests ] ──< [ approval_logs ]
          │
[ lpj_pengadaan ] ──< [ lpj_pengadaan_item ] ──> (Triggers CreateAsetBarangAction)
```

1. **`workflow_definitions` & `workflow_steps`:**
   - Menyimpan template alur persetujuan dinamis (kode flow, nama flow, urutan step, target role, scope level).
2. **`pengajuan_pengadaan`:**
   - `id`, `yayasan_id`, `lembaga_id`, `nomor_pengajuan` (e.g. `PR/2026/08/SMP/0001`), `judul_pengajuan`, `latar_belakang`, `tingkat_urgensi` (`biasa`, `mendesak`, `kritis`), `total_estimasi`, `status` (`draft`, `submitted`, `in_review`, `revision_required`, `approved`, `rejected`, `disbursed`, `completed`), `created_by_user_id`.
3. **`pengajuan_pengadaan_item`:**
   - `id`, `pengajuan_pengadaan_id`, `kategori_aset_id`, `target_ruangan_id`, `nama_barang`, `merk`, `spesifikasi`, `qty`, `satuan`, `estimasi_harga_satuan`, `total_estimasi`, `tipe_pencatatan` (`unit`, `batch`), `foto_referensi_path`, `status_item` (`pending`, `approved`, `rejected`), `catatan_reviewer`.
4. **`approval_requests` & `approval_logs`:**
   - `id`, `approvable_type`, `approvable_id`, `current_step_id`, `status` (`pending`, `approved`, `rejected`, `revision_required`).
   - Log mencakup: `user_id`, `step_number`, `action` (`approve`, `reject`, `request_revision`), `notes`, `created_at`.
5. **`lpj_pengadaan` & `lpj_pengadaan_item`:**
   - `pengajuan_pengadaan_id`, `total_realisasi`, `selisih_dana`, `bukti_kembali_sisa_dana_path`, `status_lpj` (`draft`, `submitted`, `verified`, `revision_required`).
   - Per item: `pengajuan_item_id`, `harga_satuan_riil`, `total_riil`, `foto_nota_path`, `foto_fisik_barang_path`, `status_konversi_sarpras` (`pending`, `converted`).

---

## 4. Business Actions & State Machine

1. **`SubmitPengajuanAction`:** Validasi kelengkapan proposal, generate nomor pengajuan otomatis, dan inisialisasi `ApprovalRequest` ke Step 1 (Kepala Sekolah).
2. **`ProcessApprovalStepAction`:** Memproses keputusan reviewer per langkah (mendukung approval parsial item, rejection, atau permintaan revisi). Jika step terakhir disetujui, update status pengajuan menjadi `APPROVED`.
3. **`RecordDisbursementAction`:** Mencatat data pencairan kas oleh bendahara yayasan dan mengubah status pengajuan menjadi `DISBURSED`.
4. **`SubmitLpjAction`:** Menghitung total realisasi harga riil, memverifikasi lampiran bukti nota, dan meneruskan LPJ ke yayasan untuk audit.
5. **`VerifyLpjAndGenerateInventoryAction`:** Saat LPJ berstatus `VERIFIED`, sistem men-generate record `AsetBarang` pada domain `Sarpras` di ruangan tujuan dengan status sumber perolehan `Pengadaan`.

---

## 5. Matriks Hak Akses (RBAC Permissions)

- `pengadaan.proposal.create`: Admin Sarpras / Guru Pengusul
- `pengadaan.proposal.view`: Guru, Kepala Sekolah, Yayasan
- `pengadaan.approval.internal`: Kepala Sekolah (Step 1)
- `pengadaan.approval.yayasan`: Pengurus Yayasan (Step 2)
- `pengadaan.disbursement.manage`: Bendahara Yayasan
- `pengadaan.lpj.submit`: Admin Sarpras Sekolah
- `pengadaan.lpj.verify`: Auditor / Keuangan Yayasan
- `workflow.config.manage`: Super Admin (Pengaturan dinamis langkah approval)

---

## 6. Kriteria Penerimaan (Acceptance Criteria)

- [ ] Super Admin dapat mengonfigurasi tahapan persetujuan dinamis (*approval steps*).
- [ ] Sekolah dapat membuat proposal pengadaan barang lengkap dengan penentuan target ruangan dan kategori aset.
- [ ] Kepala Sekolah dan Yayasan dapat melakukan review, revisi, penolakan, atau persetujuan parsial per item proposal.
- [ ] Bendahara Yayasan dapat mencatat pencairan dana dan mengunggah bukti kas.
- [ ] Sekolah dapat mengunggah LPJ belanja lengkap dengan rincian nota dan foto fisik barang.
- [ ] Sistem secara otomatis menghitung selisih surplus/defisit anggaran belanja.
- [ ] Saat LPJ disetujui Yayasan, barang yang telah dibeli dapat langsung di-*publish* menjadi data `AsetBarang` resmi di Master Sarpras tanpa perlu input manual ulang dari nol.
- [ ] 100% automated test coverage pada setiap Action, FormRequest, dan Controller.
