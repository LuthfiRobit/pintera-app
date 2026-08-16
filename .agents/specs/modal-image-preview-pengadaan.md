# Spec: Modal Image & Document Preview with Zoom Controls for Pengadaan

## 1. Background & Tujuan
Pada modul Pengadaan (termasuk usulan belanja, review yayasan, audit LPJ, dan konfirmasi inventarisasi sarpras), pengguna perlu memeriksa berkas bukti secara detail seperti scan nota/faktur pembelian, foto fisik barang tiba, foto brosur referensi, dan bukti transfer sisa kas. 

Sebelumnya, tautan bukti menggunakan `<a href="..." target="_blank">` yang membuka tab baru di peramban. Fitur ini digantikan dengan **Modal Lightbox Preview Interaktif** yang dapat memperbesar (*zoom in*), memperkecil (*zoom out*), menggeser (*pan/drag*), dan memutar (*rotate*) gambar tanpa meninggalkan halaman kerja.

## 2. Scope & Batasan
### In Scope:
1. **Global Alpine Store & Komponen Modal (`<x-image-preview-modal />`)**:
   - Terdaftar di `resources/views/layouts/app.blade.php`.
   - Mendukung kontrol:
     - **Zoom In (`+`)** & **Zoom Out (`-`)** (skala 50% hingga 300%).
     - **Reset Zoom (100% / Fit)**.
     - **Rotasi (Putar 90° Clockwise)** untuk foto struk/nota miring atau terbalik.
     - **Drag to Pan** saat gambar dalam posisi diperbesar (`zoom > 1`).
     - **Keyboard Escape / Click Outside** untuk menutup modal.
     - **Unduh File Asli / Buka PDF di Tab Baru** sebagai opsi lanjutan.
2. **Integrasi ke Seluruh Halaman Pengadaan**:
   - `portals/yayasan/pengadaan/audit-lpj/show.blade.php`:
     - Scan Nota / Faktur per item.
     - Foto Fisik Barang per item.
     - Bukti Transfer / Setoran Pengembalian Sisa Kas ke Yayasan.
   - `portals/lembaga/pengadaan/proposal/show.blade.php`:
     - Foto / Brosur Referensi barang usulan.
     - Bukti dan lampiran pada audit trail / timeline.
   - `portals/yayasan/pengadaan/inbox/review.blade.php`:
     - Foto / Brosur Referensi barang usulan saat evaluasi persetujuan.
   - `portals/lembaga/pengadaan/lpj/staging-inventory.blade.php`:
     - Bukti fisik & nota saat validasi aset sarpras.
3. **Automated Feature Testing**:
   - Memastikan view audit LPJ dan detail pengadaan me-render trigger modal preview dan tidak lagi menggunakan tautan `target="_blank"` mentah untuk gambar bukti.

### Out of Scope:
- Modifikasi backend schema database (hanya perubahan frontend component & blade views).

## 3. Acceptance Criteria
1. Pengguna dapat menekan tombol "Lihat Nota", "Lihat Foto", atau thumbnail bukti untuk membuka modal preview instan tanpa membuka tab baru.
2. Modal menampilkan gambar dengan resolusi jernih dan toolbar kontrol di bagian atas/bawah.
3. Tombol Zoom In (`+`) memperbesar gambar secara bertahap, dan Zoom Out (`-`) memperkecil gambar.
4. Tombol Putar 90° mengubah rotasi gambar searah jarum jam.
5. Ketika gambar di-zoom > 100%, pengguna dapat men-drag (menggeser) gambar dengan mouse.
6. Untuk berkas PDF, modal menyediakan pratinjau atau tombol buka dokumen secara responsif.
7. Seluruh test suite pengadaan tetap hijau (100% PASS).
