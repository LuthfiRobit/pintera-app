# Plan: Modal Image & Document Preview with Zoom Controls for Pengadaan

## Checklist Langkah Implementasi

- [ ] **Langkah 1: Buat Komponen Global `<x-image-preview-modal />`**
  - Buat `resources/views/components/image-preview-modal.blade.php` dengan Alpine store `$store.imagePreview` yang mencakup:
    - State: `terbuka`, `url`, `judul`, `zoom` (default: 1), `rotation` (default: 0), `posX`, `posY`, `isDragging`.
    - Methods: `buka(url, judul)`, `tutup()`, `zoomIn()`, `zoomOut()`, `resetZoom()`, `rotate()`, `startDrag(e)`, `onDrag(e)`, `stopDrag()`.
  - Daftarkan `<x-image-preview-modal />` di `resources/views/layouts/app.blade.php`.

- [ ] **Langkah 2: Integrasikan Modal Preview pada Halaman Audit LPJ Yayasan**
  - Update `resources/views/portals/yayasan/pengadaan/audit-lpj/show.blade.php`:
    - Ganti link `target="_blank"` pada Scan Nota, Foto Fisik Barang, dan Bukti Transfer Sisa Kas menjadi tombol `@click="$store.imagePreview.buka(...)"`.

- [ ] **Langkah 3: Integrasikan Modal Preview pada Halaman Detail Usulan Proposal**
  - Update `resources/views/portals/lembaga/pengadaan/proposal/show.blade.php`:
    - Tambahkan trigger modal preview untuk foto referensi/brosur barang usulan dan lampiran.

- [ ] **Langkah 4: Integrasikan Modal Preview pada Inbox Review Yayasan & Staging Inventarisasi**
  - Update `resources/views/portals/yayasan/pengadaan/inbox/review.blade.php`.
  - Update `resources/views/portals/lembaga/pengadaan/lpj/staging-inventory.blade.php`.

- [ ] **Langkah 5: Automated Testing & Verifikasi**
  - Buat `tests/Feature/Pengadaan/ImagePreviewModalTest.php` untuk memvalidasi halaman audit LPJ dan detail pengadaan merender elemen preview modal dengan benar.
  - Jalankan `php artisan test tests/Feature/Pengadaan tests/Unit/Pengadaan tests/Feature/Sarpras`.

- [ ] **Langkah 6: Serah Terima & Handoff Log**
  - Tulis `.agents/logs/modal-image-preview-pengadaan.md`.
