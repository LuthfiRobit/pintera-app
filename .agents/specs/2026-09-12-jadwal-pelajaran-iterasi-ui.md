# Spec: 5 Perbaikan UI/UX Lanjutan Modul Jadwal Pelajaran

**Tanggal:** 2026-09-12  
**Branch:** `rbac-v2`  
**File Path:** `.agents/specs/2026-09-12-jadwal-pelajaran-iterasi-ui.md`  

---

## 1. Ringkasan & Konteks

Setelah 9 task perbaikan awal modul Jadwal Pelajaran diselesaikan dan diverifikasi, pengguna memberikan 5 poin perbaikan UI/UX lanjutan:
1. **Tombol Reset Filter meninggalkan ruang kosong** karena pembungkusnya memiliki garis batas bawah dan padding vertikal kosong (`border-b border-gray-100 pb-2`) yang tetap dirender meski filter belum dipilih.
2. **KPI Card dihilangkan saja** karena kurang penting di halaman jadwal pelajaran.
3. **Modal create dan edit diperlebar** dari `sm:max-w-xl` menjadi `sm:max-w-2xl` agar form 3-kolom dan multi-select slot jam lebih leluasa.
4. **Select option belum sepenuhnya menggunakan TomSelect**: beberapa dropdown (`ruangan_id` di modal & fallback, serta `source_semester_id` & `source_kelas_id` di modal duplikasi) masih menggunakan `<select>` polos HTML.
5. **Style "Tampilan Daftar" diperbarui sama dengan style "Daftar Harian" di Pola Jam**: menggunakan tab filter hari horizontal di atas, badge urutan mono, pill rentang waktu dengan durasi menit, label slot, nama mapel/guru/ruangan, serta tombol aksi badge ber-border dengan tooltip.

---

## 2. Scope & Rincian Perubahan

### 2.1 Poin 1: Tata Letak Tombol Reset Filter
- **File:** `resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php`
- **Masalah:** Baris `<div class="flex items-center justify-between border-b border-gray-100 pb-2"><div></div><template x-if="...">...</template></div>` menghasilkan garis bawah menggantung dan ruang kosong vertikal di atas grid parameter.
- **Solusi:** Hapus pembungkus kosong tersebut. Integrasikan tombol `Reset Filter` langsung ke dalam baris header card filter (`Filter Jadwal Pelajaran`), di dalam flex wrapper tombol aksi kanan (`flex flex-wrap items-center gap-2`). Saat filter aktif, tombol Reset Filter tampil bersih dan harmonis tanpa menyisakan ruang kosong apapun.

### 2.2 Poin 2: Eliminasi KPI Cards
- **File:** `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php` & `tests/Feature/Admin/JadwalPelajaranCrudTest.php`
- **Solusi:** Hapus blok `@php $totalMapel = ... @endphp <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">...</div>`.
- **Test:** Perbarui test lama yang meng-assert `'Mata Pelajaran Aktif'` menjadi assertion struktur tab daftar harian.

### 2.3 Poin 3: Perlebar Ukuran Modal
- **File:** `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php`
- **Solusi:** Ubah kelas kontainer modal dari `sm:max-w-xl` menjadi `sm:max-w-2xl`.

### 2.4 Poin 4: TomSelect Universal di Seluruh Modul
- **Modal Form (`_modal-form.blade.php` & `jadwal-pelajaran-filter.js`):**
  - Pasang `x-init="initModalRuanganSelect($el)"` pada select `ruangan_id`.
  - Tambahkan `initModalRuanganSelect(el)` di JS dengan `modalRuanganTomSelect`.
  - Sinkronkan `openCreateModal` (clear ruangan) dan `openEditModal` (setValue ruangan).
- **Modal Duplikasi (`_modal-duplicate.blade.php` & `jadwal-pelajaran-filter.js`):**
  - Pasang `x-init="initDuplicateSemesterSelect($refs.duplicateSemesterSelect)"` pada `source_semester_id`.
  - Pasang `x-init="initDuplicateKelasSelect($refs.duplicateKelasSelect)"` pada `source_kelas_id`.
  - Tambahkan `initDuplicateSemesterSelect` dan `initDuplicateKelasSelect` di JS.
  - Perbarui event `onChange` pada `initDuplicateTahunAjaranSelect` agar mengisi opsi melalui method `addOption()` dan `refreshOptions(false)` pada TomSelect semester dan kelas sumber.
- **Fallback Create & Edit (`create.blade.php`, `edit.blade.php`, & `jadwal-pelajaran-create.js`):**
  - Tambahkan method `initRuanganSelect(el)` pada `jadwalPelajaranCreateForm()`.
  - Pasang `x-ref="ruanganSelect" x-init="initRuanganSelect($refs.ruanganSelect)"` pada `ruangan_id` di `create.blade.php` dan `edit.blade.php`.

### 2.5 Poin 5: Tampilan Daftar Mengadopsi Pola Jam
- **File:** `resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php`
- **Elemen:**
  1. **Tab Filter Hari Horizontal:**
     - Menampilkan daftar hari kerja aktif (`$hariAktif`) di baris atas kontainer list view dengan border bawah.
     - Setiap tab memiliki badge jumlah sesi pada hari tersebut.
     - Klik tab mengubah state `hariAktif`.
  2. **Konten Per Hari:**
     - Dibungkus `x-show="hariAktif === '{{ $daftarHari->value }}'"`.
     - Jika kosong: pesan kosong informatif dan beraksen italic.
     - Setiap item sesi KBM:
       - Badge nomor urutan jam mono (`h-6 w-6 rounded-lg bg-gray-100 font-mono text-xs font-bold text-gray-700`).
       - Pill rentang jam mono (`H:i` &rarr; `H:i`) beraksen brand ring dan gray ring.
       - Durasi menit KBM `(X mnt)`.
       - Label slot jam pelajaran (badge gray rounded-md).
       - Nama Mata Pelajaran (font-bold text-gray-900).
       - Nama Guru Pengampu (dengan icon `person` dan divider kiri di layar desktop).
       - Nama Ruangan (dengan badge brand dan icon `meeting_room`).
       - Tombol aksi Edit dan Hapus bergaya badge tombol ber-border dengan tooltip `<x-tooltip>`, persis seperti di Pola Jam.

---

## 3. Acceptance Criteria

1. Halaman `admin/jadwal-pelajaran` tidak lagi memiliki ruang kosong atau garis pembatas berlebih saat filter belum atau sudah dipilih.
2. KPI cards tidak lagi muncul di halaman index / daftar jadwal pelajaran.
3. Modal create/edit jadwal pelajaran tampil lebih lebar (`max-w-2xl`), memberikan ruang proporsional bagi form 3-kolom dan multi-select.
4. Semua elemen `<select>` di modal jadwal, modal duplikasi, dan form fallback create/edit sudah 100% menggunakan TomSelect.
5. "Tampilan Daftar" memiliki tab filter hari horizontal dan baris data sesi yang mengikuti visual language dari "Daftar Harian" di Pola Jam.
6. Seluruh test di `tests/Feature/Admin/JadwalPelajaranCrudTest.php` pass tanpa error.
7. Linting `vendor/bin/pint --dirty --format agent` bersih tanpa pelanggaran.
8. Build frontend Vite (`npm run build`) sukses tanpa peringatan.
