# Spec Desain: Dukungan Agregat Yayasan & Redesign UI/UX Jadwal Piket Guru

**Tanggal:** 2026-09-11  
**Status:** Draf Disetujui  
**Target File:**
- `app/Http/Controllers/Admin/JadwalPiketMingguanController.php`
- `app/Http/Controllers/Admin/PiketHarianController.php`
- `resources/views/portals/lembaga/akademik/piket-guru/index.blade.php`
- `tests/Feature/Admin/JadwalPiketMingguanControllerTest.php`
- `tests/Feature/Admin/PiketHarianControllerTest.php`

---

## 1. Konteks & Masalah

Fitur Jadwal Piket Guru sebelumnya telah melalui audit teknis dan fungsional (Tasks 1-8). Namun terdapat dua gap besar yang ditemukan dalam evaluasi menyeluruh:

1. **Bug Sistemik Multi-Scope (Kegagalan Agregat Yayasan)**:
   - Method `index()` pada `JadwalPiketMingguanController` memanggil `resolveLembagaIdAktif()`, yang mengeksekusi `abort_if($lembagaId === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.')`.
   - Akibatnya, ketika pengguna berscope yayasan (misal Admin Yayasan) berada dalam mode **"Semua Lembaga"** (`session('active_lembaga_id') === null`), halaman `/admin/piket-guru` mengalami **HTTP 422 crash**.
   - Pengguna yayasan tidak dapat memonitor jadwal piket lintas unit sekolah.
2. **Kesenjangan Standar UI/UX Pintera**:
   - Container halaman sempit (`max-w-4xl`), tidak sejalan dengan halaman standar portal Pintera (`max-w-6xl` atau `max-w-7xl`).
   - Tidak ada `<x-scope-badge>` di header yang menjelaskan konteks data aktif.
   - Tidak ada ringkasan metrik / KPI (`<x-stat-tile>`).
   - Tidak ada toolbar filter atau pencarian (pencarian nama guru, filter hari, filter semester, filter lembaga saat agregat).
   - Layout konten bertumpuk vertikal secara ekstrem (>3.200px) tanpa pembagian navigasi yang jelas antara template mingguan, kalender harian, dan override.

---

## 2. Ruang Lingkup (Scope)

### In-Scope:
1. **Dukungan Agregat Yayasan di Backend**:
   - Menghapus pemblokiran `abort_if(null, 422)` pada method `index()` di `JadwalPiketMingguanController`.
   - Mengambil data lintas lembaga milik yayasan ketika mode agregat aktif menggunakan `TenantScope` bawaan model `JadwalPiketMingguan` dan `PiketHarian`.
   - Mendukung filter toolbar: pencarian guru (`search`), filter `hari`, filter `semester_id`, dan filter `lembaga_id` (khusus mode agregat).
   - Di method `create()`: jika mode agregat, redirect dengan pesan bantuan atau perlindungan tombol di UI.
   - Di `PiketHarianController::destroy()`: mengizinkan admin yayasan menghapus override selama lembaga milik yayasan pengguna.
2. **Redesign UI/UX Standar Pintera**:
   - Container responsif `max-w-7xl` dengan padding standar.
   - Header terintegrasi dengan `<x-scope-badge>` dan tombol "Tambah Jadwal" yang kondisional (disabled + tooltip instruktif di mode agregat).
   - Grid 4 KPI Cards menggunakan `<x-stat-tile>`: Total Jadwal Mingguan, Guru Bertugas Piket, Override Manual Aktif, dan Unit Lembaga Terjadwal (atau Hari Tercover).
   - Toolbar Filter & Pencarian responsif.
   - Tabbed Navigation berbasis Alpine.js:
     - **Tab 1: Jadwal Mingguan** (Template mingguan, badge hari, avatar/nama/NIP guru, kolom lembaga saat agregat, aksi edit/hapus).
     - **Tab 2: Kalender Piket Harian** (Kalender 30–60 hari ke depan, badge Otomatis vs Manual, kolom lembaga saat agregat).
     - **Tab 3: Override Manual** (Form tambah override jika 1 lembaga aktif + tabel daftar override mendatang dengan aksi hapus).
   - Standarisasi Empty State dengan icon dan instruksi jelas.

### Out-of-Scope:
- Perubahan model data database atau migrasi baru.
- Fitur pelaporan piket / absensi piket (Fase 2).
- Perubahan logika action `GenerateJadwalPiketHarianAction` dan `RegenerateJadwalPiketHarianAction`.

---

## 3. Asumsi Teknis

1. Model `JadwalPiketMingguan` dan `PiketHarian` menggunakan trait `BelongsToTenant` yang otomatis menerapkan `TenantScope`.
2. Jika pengguna adalah aktor yayasan dan tidak ada `active_lembaga_id` spesifik di sesi, `TenantScope` secara aman membatasi data ke seluruh `lembaga_id` milik yayasan pengguna (`Lembaga::where('yayasan_id', $actingUser->yayasan_id)->select('id')`).
3. Relasi `lembaga` sudah tersedia di model `JadwalPiketMingguan` dan `PiketHarian`.

---

## 4. Kriteria Keberterimaan (Acceptance Criteria)

1. **Akses Mode Agregat Yayasan**:
   - Pengguna dengan scope yayasan dapat membuka `/admin/piket-guru` saat mode "Semua Lembaga" aktif tanpa error 422.
   - Tabel Jadwal Mingguan dan Kalender Harian menampilkan data dari seluruh lembaga di bawah yayasan tersebut.
   - Terdapat kolom/badge `Lembaga` pada baris data saat berada dalam mode agregat yayasan.
2. **Filter & Pencarian**:
   - Pengguna dapat mencari berdasarkan nama guru.
   - Pengguna dapat memfilter berdasarkan Hari (Senin–Sabtu).
   - Pengguna dapat memfilter berdasarkan Semester.
   - Saat mode agregat yayasan, tersedia dropdown filter Lembaga untuk mempersempit tampilan ke 1 unit sekolah tanpa harus switch konteks global.
3. **Guard Aksi Mutasi**:
   - Tombol "Tambah Jadwal" dalam mode agregat yayasan dalam keadaan non-aktif (disabled) disertai tooltip: *"Pilih 1 lembaga aktif melalui pengalih lembaga untuk menambah jadwal piket."*
   - Jika pengguna mengakses `/admin/piket-guru/create` secara langsung saat mode agregat, sistem me-redirect ke index dengan pesan notifikasi ramah.
   - Form Override Manual pada Tab 3 menampilkan notice ramah saat mode agregat agar pengguna memilih lembaga aktif terlebih dahulu.
4. **Desain & UI/UX**:
   - Layout lebar penuh `max-w-7xl`.
   - Badge konteks `<x-scope-badge>` tampil di samping judul.
   - 4 Stat Tiles (`<x-stat-tile>`) tampil akurat dan dinamis.
   - Navigasi tab berjalan mulus tanpa reload halaman (Alpine.js).
   - Semua dialog hapus menggunakan `confirmDialog` standar Pintera.
5. **Testing**:
   - Feature test baru memastikan akses `index()` berhasil (status 200) saat user yayasan tanpa active lembaga (mode agregat).
   - Feature test memastikan filter bekerja di mode agregat maupun 1 lembaga.
   - Seluruh test existing tetap 100% lulus tanpa regresi.
