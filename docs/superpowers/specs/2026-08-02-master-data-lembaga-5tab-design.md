# Spesifikasi Desain: Portal Kelembagaan 5-Tab & Relational Management

**Tanggal**: 2026-08-02  
**Status**: Disetujui / Siap Implementasi  
**Fokus**: Master Data Kelembagaan (`Lembaga`)  

---

## 1. Tujuan dan Ruang Lingkup
Mengubah halaman manajemen detail dan pengubahan data Lembaga (`resources/views/admin/lembaga/edit.blade.php`) dari formulir statik tunggal menjadi **Portal Kelembagaan 5-Tab Interaktif (View-to-Edit Toggle)** yang modern dan berestetika premium sesuai standar `/ui-ux-pro-max` dan `/ui-styling` (TailAdmin).

Selain memperindah presentasi profil lembaga, pengembangan ini membedah 4 entitas relasionil yang ada di database agar sepenuhnya dapat dikelola (Create, Read, Update, Delete) oleh Administrator secara intuitif:
1. `LembagaDataPeriodik` (Fasilitas, infrastruktur listrik/internet, UKS per semester)
2. `EkstrakurikulerLembaga` (Daftar ekskul, jam per minggu, SK)
3. `LayananKhususLembaga` (Layanan khusus Bimbingan Konseling, Klinik, Koperasi, dll)
4. `ProgramInklusiLembaga` (Layanan kebutuhan khusus inklusi)

---

## 2. Persyaratan Utama & Ketentuan UX Khusus
### A. Persistensi Tab & Mode ("Stay on Current Tab")
Sesuai instruksi pengguna: **Jika terjadi proses penambahan, pengubahan, atau penghapusan data pada salah satu tab, setelah proses selesai (atau saat reload/redirect) halaman WAJIB tetap bertahan di tab yang sama dan TIDAK lompat kembali ke tab awal (Profil).**
- **Strategi URL Hash Routing**: Setiap aksi rute controller paska penanganan form wajib menyertakan anchor hash tab target (contoh: `redirect()->to(route('admin.lembaga.edit', $lembaga) . '#ekstrakurikuler')` atau `#data-periodik`, `#layanan-khusus`, `#program-inklusi`).
- **Sinkronisasi Alpine.js & History API**: Pada inisialisasi Alpine.js di halaman edit, state tab aktif (`activeTab`) dipindai langsung dari `window.location.hash`. Ketika pengguna berganti tab secara klik manual, `window.history.replaceState(null, '', '#' + tab)` dieksekusi tanpa reload agar posisi URL senantiasa konsisten. Jika hash tidak kosong (misal `#ekstrakurikuler`), mode secara default diset ke `edit` agar alur kerja administrator tidak putus.

### B. Prinsip Desain Antarmuka (Cognitive Silence & TailAdmin)
- **Mode Lihat (*View Mode*)**: Mengedepankan penyajian visual tabel maupun grid kartu informasi yang bersih dari elemen input, tombol simpan, atau gangguan navigasi yang tidak perlu.
- **Mode Edit (*Edit Mode*)**: Saat tombol toggle di pojok kanan banner ditekonsistenkan menjadi mode edit, form masukan aktif dan setiap tab relasional menampilkan tombol **"+ Tambah Data"** di bagian kanan atas tab serta opsi Edit/Hapus pada tabel/kartu data via modal interaktif Alpine.js.
- **Konfirmasi Tanpa Popup Browser**: Semua penghapusan maupun aksi kritis menggunakan dialog konfirmasi custom (`confirmDialog`) berbasi Alpine.js.

---

## 3. Arsitektur Backend & Rute
Untuk menjaga struktur kode berpedoman *Single Responsibility Principle* (SRP), penanganan operasi CRUD untuk masing-masing relasi dipisah ke dalam 4 sub-controller terdedikasikan pada folder `App\Http\Controllers\Admin\Lembaga\`:
1. `DataPeriodikController` (`store`, `update`, `destroy`)
2. `EkstrakurikulerController` (`store`, `update`, `destroy`)
3. `LayananKhususController` (`store`, `update`, `destroy`)
4. `ProgramInklusiController` (`store`, `update`, `destroy`)

### Definisi Rute (`routes/admin.php`)
```php
Route::prefix('admin/lembaga/{lembaga}')->name('admin.lembaga.')->group(function () {
    // Data Periodik
    Route::post('data-periodik', [App\Http\Controllers\Admin\Lembaga\DataPeriodikController::class, 'store'])->name('data-periodik.store');
    Route::put('data-periodik/{dataPeriodik}', [App\Http\Controllers\Admin\Lembaga\DataPeriodikController::class, 'update'])->name('data-periodik.update');
    Route::delete('data-periodik/{dataPeriodik}', [App\Http\Controllers\Admin\Lembaga\DataPeriodikController::class, 'destroy'])->name('data-periodik.destroy');

    // Ekstrakurikuler
    Route::post('ekstrakurikuler', [App\Http\Controllers\Admin\Lembaga\EkstrakurikulerController::class, 'store'])->name('ekstrakurikuler.store');
    Route::put('ekstrakurikuler/{ekstrakurikuler}', [App\Http\Controllers\Admin\Lembaga\EkstrakurikulerController::class, 'update'])->name('ekstrakurikuler.update');
    Route::delete('ekstrakurikuler/{ekstrakurikuler}', [App\Http\Controllers\Admin\Lembaga\EkstrakurikulerController::class, 'destroy'])->name('ekstrakurikuler.destroy');

    // Layanan Khusus
    Route::post('layanan-khusus', [App\Http\Controllers\Admin\Lembaga\LayananKhususController::class, 'store'])->name('layanan-khusus.store');
    Route::put('layanan-khusus/{layananKhusus}', [App\Http\Controllers\Admin\Lembaga\LayananKhususController::class, 'update'])->name('layanan-khusus.update');
    Route::delete('layanan-khusus/{layananKhusus}', [App\Http\Controllers\Admin\Lembaga\LayananKhususController::class, 'destroy'])->name('layanan-khusus.destroy');

    // Program Inklusi
    Route::post('program-inklusi', [App\Http\Controllers\Admin\Lembaga\ProgramInklusiController::class, 'store'])->name('program-inklusi.store');
    Route::put('program-inklusi/{programInklusi}', [App\Http\Controllers\Admin\Lembaga\ProgramInklusiController::class, 'update'])->name('program-inklusi.update');
    Route::delete('program-inklusi/{programInklusi}', [App\Http\Controllers\Admin\Lembaga\ProgramInklusiController::class, 'destroy'])->name('program-inklusi.destroy');
});
```

---

## 4. Rincian Komponen 5-Tab pada Halaman Edit Lembaga
Halaman `resources/views/admin/lembaga/edit.blade.php` akan memuat 5 struktur tab:

1. **Tab 1: Profil Sekolah (`#profil`)**
   - **Mode Lihat**: Grid kartu detail identitas lembaga (NPSN, NSS, Bentuk Pendidikan, Akreditasi, Status Kepemilikan), Kontak & Lokasi GPS, Kepala Sekolah, serta Informasi Rekening Bank & NPWP.
   - **Mode Edit**: Formulir pengubahan data utama (persis seperti kemampuan edit eksisting di `_form.blade.php` namun ditransformasi ke susunan yang jauh lebih rapi).

2. **Tab 2: Data Periodik (`#data-periodik`)**
   - Menampilkan catatan kondisi sekolah berdasarkan Semester.
   - Field: Pilihan `semester_id`, `waktu_penyelenggaraan` (Pagi/Siang/Kombinasi), `sumber_listrik`, `daya_listrik` (Watt), `akses_internet` (Speed/ISP), `status_bos` (Boolean), `sertifikasi_iso`, `ketersediaan_air_bersih`, `kecukupan_air_bersih`, `jumlah_tempat_cuci_tangan`, `jumlah_jamban`, `stratifikasi_uks`, `media_kie_sanitasi`.
   - Modifikasi dilakukan melalui modal popup alpine.

3. **Tab 3: Ekstrakurikuler (`#ekstrakurikuler`)**
   - Menampilkan daftar kegiatan ekstra di lingkungan lembaga.
   - Field: `jenis_ekskul`, `nama_ekskul`, `no_sk`, `tanggal_sk`, `jam_per_minggu`.

4. **Tab 4: Layanan Khusus (`#layanan-khusus`)**
   - Menampilkan fasilitas khusus instrumen pendidikan.
   - Field: `jenis_layanan`, `no_sk`, `tmt`, `tst`, `keterangan`.

5. **Tab 5: Program Inklusi (`#program-inklusi`)**
   - Menampilkan program bantuan serta pelayanan khusus berkebutuhan inklusi.
   - Field: `kebutuhan_khusus`, `no_sk`, `tanggal_sk`, `tmt`, `tst`, `keterangan`.

---

## 5. Rencana Pengujian (Test-Driven Development)
Sebuah test case khusus `Tests\Feature\Admin\LembagaRelationalManagementTest` akan diciptakan dengan cakupan asersi:
- **Otorisasi**: Peng Pengguna tanpa hak akses atau pengguna tenant lain dipastikan menerima response `403 Forbidden`.
- **Aksi CRUD Relasional**: Pengujian lengkap memvalidasi pembuatan (store), pengubahan (update), dan penghapusan (delete) untuk keempat relasi lembaga.
- **Persistensi Hash & Notifikasi**: Verifikasi bahwa setiap proses berhasil akan me-redirect pengguna ke URL berakhiran hash tab yang sesuai (`#data-periodik`, `#ekstrakurikuler`, dll) dengan session notifikasi `success`.
- **Zero Regressions**: Seluruh pengujian eksisting pada modul lembaga (`LembagaCrudTest`, `LembagaDataTest`, dsb.) harus lolos tanpa kesalahan.
