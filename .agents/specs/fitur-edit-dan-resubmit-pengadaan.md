# Spec: Fitur Edit & Pengajuan Ulang (Resubmit) Usulan Pengadaan Pasca Revisi

## 1. Latar Belakang & Masalah
Ketika Kepala Sekolah atau Reviewer Workflow meminta revisi pada Step 1 (Status: `RevisionRequired` / `revisi_dibutuhkan`), sistem saat ini belum menyediakan:
1. Controller method `edit` dan `update` pada `PengajuanPengadaanController`.
2. Halaman view `proposal/edit.blade.php` untuk mengubah rincian barang, jumlah, spesifikasi, atau anggaran.
3. Tombol aksi "Edit Usulan" dan "Ajukan Ulang Usulan" (Resubmit) pada `proposal/show.blade.php` dan `proposal/_daftar.blade.php`.
4. Mekanisme reset workflow state pada `SubmitPengajuanAction` untuk mengembalikan proposal yang telah direvisi ke antrean Step 1 Kepala Sekolah.

## 2. Ruang Lingkup (Scope)
- **In-Scope**:
  - `UpdatePengajuanRequest`: FormRequest validasi update usulan dengan pesan bahasa Indonesia.
  - `UpdatePengajuanAction`: Action domain untuk update proposal & sinkronisasi item pengadaan.
  - `SubmitPengajuanAction`: Penanganan resubmit (reset current step ke step 1 & status ke `Submitted`/`InReview`, pencatatan log resubmit).
  - `PengajuanPengadaanController`: Implementasi method `edit()` dan `update()`.
  - `resources/views/portals/lembaga/pengadaan/proposal/edit.blade.php`: Form edit interaktif (Alpine.js) yang terisi data awal proposal dan item-itemnya.
  - `resources/views/portals/lembaga/pengadaan/proposal/show.blade.php`: Banner instruksi revisi, tombol "Edit Usulan", dan tombol "Ajukan Ulang".
  - `resources/views/portals/lembaga/pengadaan/proposal/_daftar.blade.php`: Dropdown action "Edit Usulan" untuk proposal berstatus `Draft` dan `RevisionRequired`.
  - Feature test otomatis `ProposalEditAndResubmitTest`.

## 3. Acceptance Criteria
1. User dengan role pengusul/admin dapat membuka halaman edit proposal pada status `Draft` dan `RevisionRequired`.
2. Form edit menampilkan seluruh item awal yang dapat ditambah, diubah kuantitas/harga, atau dihapus secara reaktif.
3. Proposal yang disimpan ulang memperbarui total estimasi dan rincian item.
4. Proposal yang diajukan ulang (`Resubmit`) kembali berstatus `Submitted` dan langkah aktif workflow kembali ke Step 1 (Kepala Sekolah).
5. Catatan revisi sebelumnya tetap tersimpan di Activity Timeline sebagai audit trail.
