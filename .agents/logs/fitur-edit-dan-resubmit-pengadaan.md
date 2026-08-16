# Handoff Log: Fitur Edit & Pengajuan Ulang (Resubmit) Usulan Pengadaan Pasca Revisi

## Apa yang dikerjakan
1. **Penyediaan Fitur Edit Usulan Pengadaan**:
   - Dibuat `app/Http/Requests/Pengadaan/UpdatePengajuanRequest.php` untuk validasi form update proposal & rincian item dengan pesan bahasa Indonesia.
   - Dibuat `app/Domains/Pengadaan/Actions/UpdatePengajuanAction.php` untuk update data usulan dan sinkronisasi baris item.
   - Diimplementasikan method `edit()` dan `update()` pada `app/Http/Controllers/Lembaga/Pengadaan/PengajuanPengadaanController.php`.
   - Dibuat view template `resources/views/portals/lembaga/pengadaan/proposal/edit.blade.php` dengan Alpine.js.
2. **Dialog Konfirmasi Hapus Item & Per-Item Status Lock**:
   - **Dialog Konfirmasi**: Menghubungkan fungsi `hapusItem(index)` pada form `edit.blade.php` dan `create.blade.php` dengan modal `confirmDialog()` standar Pintera, sehingga penghapusan baris item harus dikonfirmasi terlebih dahulu oleh pengguna.
   - **Per-Item Status Lock**:
     - Item yang berstatus `Approved` (*Disetujui Reviewer*): Otomatis dikunci (*read-only / locked fields*), tombol hapus digantikan dengan badge *"Item Terverifikasi"*, dan datanya dikirimkan secara aman melalui input terproteksi agar tidak bisa diubah sembarangan.
     - Item yang berstatus `Rejected` (*Perlu Revisi / Ditolak*): Menampilkan badge merah dengan catatan reviewer, serta field input terbuka untuk diperbaiki atau dihapus jika dibatalkan.
     - Item berstatus `Pending` / Baru: Terbuka untuk diedit atau dihapus.
3. **Penanganan Alur Pengajuan Ulang (Resubmit)**:
   - Disempurnakan `app/Domains/Pengadaan/Actions/SubmitPengajuanAction.php` sehingga ketika proposal direvisi dan diajukan ulang (`Resubmit`), sistem mereset langkah aktif pada alur persetujuan kembali ke Step 1 (Kepala Sekolah).
4. **Automated Testing**:
   - Dibuat `tests/Feature/Pengadaan/ProposalEditAndResubmitTest.php` yang memvalidasi alur lengkap:
     - Revisi total proposal.
     - Partial item revision & preserving `Approved` status items.
   - Seluruh 14 feature & unit test suite (96 assertions) lulus 100%.

## Keputusan penting yang diambil
- Item yang telah disetujui reviewer pada step sebelumnya dipertahankan status `Approved`-nya dan dikunci dari pengeditan form, sehingga pengusul fokus merevisi item yang ditolak / diminta perbaikan saja.

## Hal yang masih perlu direview manusia/Claude
- Branch saat ini: `akademik-v2`.
- Seluruh flow dan validasi dialog konfirmasi telah teruji otomatis tanpa error.
