# Rencana Implementasi: Dukungan Agregat Yayasan & Redesign UI/UX Jadwal Piket Guru

> **Untuk agentic workers:** REQUIRED SUB-SKILL: Gunakan `superpowers:subagent-driven-development` atau `superpowers:executing-plans` untuk mengeksekusi rencana ini per task. Setiap step menggunakan checklist (`- [ ]`) untuk pelacakan.

**Goal:** Mengaktifkan dukungan mode agregat "Semua Lembaga" untuk admin yayasan pada fitur Jadwal Piket Guru dan merombak tampilan UI/UX menjadi standar premium Pintera (container responsif, KPI cards, filter toolbar, dan tabbed navigation).

**Architecture:** Controller memanfaatkan `TenantScope` bawaan model `JadwalPiketMingguan` dan `PiketHarian` saat mode agregat (`active_lembaga_id === null`), menyediakan metadata scope (`isYayasan`, `activeLembaga`, `isYayasanAggregate`) dan KPI stats. View Blade menggunakan Alpine.js tabbed navigation (`activeTab`), `<x-stat-tile>`, `<x-scope-badge>`, dan form filter responsif.

**Tech Stack:** Laravel 12, Blade, Tailwind CSS, Alpine.js, Pest PHP.

## Batasan Global
- Tetap di branch `rbac-v2`. Jangan switch branch, jangan merge, jangan push ke remote.
- Jangan mengubah global `APP_TIMEZONE` di `config/app.php`.
- Pertahankan validasi integritas tenant dan isolasi data.

---

## Task 1: Backend Controller Scope Agregat, Filter, & Metrik KPI

**Files:**
- Modify: `app/Http/Controllers/Admin/JadwalPiketMingguanController.php`
- Modify: `app/Http/Controllers/Admin/PiketHarianController.php`
- Test: `tests/Feature/Admin/JadwalPiketMingguanControllerTest.php`
- Test: `tests/Feature/Admin/PiketHarianControllerTest.php`

**Interfaces:**
- Consumes: `ResolveLembagaScopeTrait`, `TenantScope`, `BelongsToTenant`.
- Produces: Data view `index`: `jadwalList`, `overrides`, `piketHarianMendatang`, `stats`, `isYayasan`, `activeLembaga`, `isYayasanAggregate`, `lembagaList`, `semesterList`.

- [ ] **Step 1: Tulis feature test yang gagal untuk mode agregat yayasan**
  - Buat test di `tests/Feature/Admin/JadwalPiketMingguanControllerTest.php`:
    - User yayasan dengan `session(['active_lembaga_id' => null])` mengakses `admin/piket-guru` harus mendapatkan response **HTTP 200** (bukan 422).
    - Memastikan data jadwal dari 2 lembaga berbeda di bawah yayasan yang sama muncul di `jadwalList`.
    - Menguji filter `hari`, `semester_id`, dan `search`.
  - Buat test di `tests/Feature/Admin/PiketHarianControllerTest.php`:
    - User yayasan di mode agregat dapat menghapus override milik salah satu lembaganya.

- [ ] **Step 2: Jalankan test dan pastikan GAGAL (karena masih ada `abort_if(null, 422)`)**
  - Run: `vendor/bin/pest tests/Feature/Admin/JadwalPiketMingguanControllerTest.php --filter="agregat"`

- [ ] **Step 3: Perbaiki `JadwalPiketMingguanController.php`**
  - Tambahkan helper `scopeHeaderData(Request $request): array` (pola standar `KomponenPenilaianController` dan `KelasController`).
  - Hapus pemanggilan `resolveLembagaIdAktif()` di `index()`.
  - Pada `index()`:
    - Tangkap parameter query: `search`, `hari`, `semester_id`, `lembaga_id`.
    - Jika `$activeLembagaId` ada, batasi query ke `$activeLembagaId`.
    - Jika `$isYayasanAggregate`, izinkan filter manual `lembaga_id` jika dipilih; jika tidak, `TenantScope` otomatis membatasi ke seluruh lembaga milik yayasan.
    - Eager load `['guru', 'semester.tahunAjaran', 'lembaga']` pada `JadwalPiketMingguan` dan `['guru', 'lembaga']` pada `PiketHarian`.
    - Hitung ringkasan `$stats`:
      - `totalJadwal`: `$jadwalList->count()`
      - `guruTerjadwal`: `$jadwalList->pluck('guru_id')->unique()->count()`
      - `overrideAktif`: `$overrides->count()`
      - `lembagaTerjadwal`: `$jadwalList->pluck('lembaga_id')->unique()->count()`
    - Di `create()`: jika `$activeLembagaId === null`, redirect ke `index` dengan flash error `'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jadwal piket.'`.
  - Kirimkan data ke view: `jadwalList`, `overrides`, `piketHarianMendatang`, `stats`, `guruList`, `semesterList`, `lembagaList`, dan `...$this->scopeHeaderData($request)`.

- [ ] **Step 4: Perbaiki `PiketHarianController.php`**
  - Di `destroy()`: izinkan penghapusan jika `$isYayasan` dan `$piketHarian->lembaga->yayasan_id === $actingUser->yayasan_id`.

- [ ] **Step 5: Jalankan test untuk memastikan LOLOS**
  - Run: `vendor/bin/pest tests/Feature/Admin/JadwalPiketMingguanControllerTest.php tests/Feature/Admin/PiketHarianControllerTest.php --compact`

- [ ] **Step 6: Commit Task 1**
  - Commit: `feat(piket): dukung mode agregat yayasan di JadwalPiketMingguanController dan PiketHarianController`

---

## Task 2: Redesign View Blade `piket-guru/index.blade.php` Sesuai Standar Pintera

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/piket-guru/index.blade.php`

**Interfaces:**
- Consumes: Data view dari Task 1 (`stats`, `isYayasan`, `activeLembaga`, `isYayasanAggregate`, `lembagaList`, dsb.).
- Produces: Tampilan UI/UX modern dengan tabbed navigation, KPI cards, scope badge, dan filter toolbar.

- [ ] **Step 1: Rancang ulang struktur layout view**
  - Container responsif: `mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8`.
  - **Header**:
    - Judul "Jadwal Piket Guru", subtitle deskriptif.
    - Komponen `<x-scope-badge :is-yayasan="$isYayasan" :active-lembaga="$activeLembaga" />`.
    - Tombol "Tambah Jadwal":
      - Jika 1 lembaga aktif: `<x-link-button href="{{ route('admin.piket-guru.create') }}">...`
      - Jika agregat: `<x-tooltip text="Pilih 1 lembaga aktif melalui pengalih lembaga untuk menambah jadwal piket."><x-secondary-button disabled ...>`
  - **KPI Cards Grid (4 Cards)**:
    - `<x-stat-tile tone="blue" label="Total Jadwal" :value="$stats['totalJadwal']" icon="calendar_month" />`
    - `<x-stat-tile tone="green" label="Guru Piket" :value="$stats['guruTerjadwal']" icon="school" />`
    - `<x-stat-tile tone="amber" label="Override Aktif" :value="$stats['overrideAktif']" icon="edit_calendar" />`
    - `<x-stat-tile tone="indigo" :label="$isYayasanAggregate ? 'Unit Terjadwal' : 'Hari Tercover'" :value="$stats['lembagaTerjadwal']" icon="apartment" />`
  - **Filter & Search Toolbar Card**:
    - Form filter GET ke route `admin.piket-guru.index`.
    - Input Search nama guru.
    - Dropdown Filter Hari (Semua Hari, Senin–Sabtu).
    - Dropdown Filter Semester.
    - Dropdown Filter Lembaga (hanya render jika `$isYayasanAggregate`).
    - Tombol Reset Filter jika ada parameter aktif.
  - **Tabbed Navigation Container (`x-data="{ activeTab: 'mingguan' }"`):**
    - Nav tab pill dengan counter badge untuk setiap tab.
    - **Tab 1: Jadwal Mingguan**:
      - Tabel jadwal piket mingguan dengan header rapi.
      - Kolom: Guru (nama + avatar initial + NIP/NUPTK), Hari (badge pill warna hari), Semester, [Kolom Lembaga saat `$isYayasanAggregate`], Aksi (Edit, Hapus).
      - Empty state modern dengan icon & tombol buat jadwal.
    - **Tab 2: Kalender Piket Harian**:
      - Subtitle penjelasan sinkronisasi harian.
      - Tabel 60 hari mendatang: Tanggal (format Indonesia), Guru Piket, Sumber (Badge `Otomatis` vs `Manual`), [Kolom Lembaga saat `$isYayasanAggregate`].
    - **Tab 3: Override Manual**:
      - Jika 1 lembaga aktif: Card form tambah override dengan `tomSelectPegawai` + input tanggal.
      - Jika agregat yayasan: Notice banner informatif amber bahwa form penambahan membutuhkan pemilihan 1 lembaga aktif di topbar.
      - Tabel daftar override manual mendatang dengan tombol hapus (`confirmDialog`).

- [ ] **Step 2: Compile asset Vite**
  - Run: `npm.cmd run build`

- [ ] **Step 3: Jalankan test feature admin untuk memastikan tidak ada Blade syntax error**
  - Run: `vendor/bin/pest tests/Feature/Admin/JadwalPiketMingguanControllerTest.php tests/Feature/Admin/PiketHarianControllerTest.php --compact`

- [ ] **Step 4: Commit Task 2**
  - Commit: `style(piket): redesign index piket guru dengan KPI cards, filter toolbar, tab navigation, dan scope badge`

---

## Task 3: Verifikasi Visual Dev-Server & Regresi Penuh

**Files:**
- Test: `tests/Feature/Admin/JadwalPiketMingguanControllerTest.php`
- Test: Seluruh 15 test suite piket & jurnal

- [ ] **Step 1: Uji visual dev-server via browser subagent**
  - Buka `http://127.0.0.1:8000/admin/piket-guru?switch_lembaga=all` (mode Semua Lembaga / agregat):
    - Pastikan halaman berhasil tampil (HTTP 200).
    - Scope badge "Semua Lembaga" terlihat.
    - KPI cards terisi.
    - Kolom lembaga terlihat di tabel jadwal dan kalender.
    - Tombol tambah jadwal dalam keadaan disabled dengan tooltip.
  - Buka `http://127.0.0.1:8000/admin/piket-guru?switch_lembaga=1` (mode 1 lembaga):
    - Scope badge menampilkan nama lembaga.
    - Tombol tambah jadwal aktif.
    - Form override manual aktif.
  - Uji perpindahan tab (Mingguan -> Kalender -> Override) tanpa reload halaman.
  - Ambil screenshot untuk bukti verifikasi.

- [ ] **Step 2: Jalankan 15 test suite terkait**
  - Run: `vendor/bin/pest tests/Feature/Admin/JadwalPiketMingguanControllerTest.php tests/Feature/Admin/PiketHarianControllerTest.php tests/Feature/Guru/JurnalKbmPiketAksesTest.php tests/Feature/Guru/JurnalKbmSesiPiketTest.php tests/Unit/Domains/Akademik/GenerateJadwalPiketHarianActionTest.php tests/Unit/Domains/Akademik/PiketAccessCheckerTest.php tests/Unit/Domains/Akademik/PiketModelsTest.php tests/Unit/Domains/Akademik/RegenerateJadwalPiketHarianActionTest.php tests/Feature/Akademik/JurnalKbmTanggalSusulanTest.php tests/Feature/Guru/JurnalKbmResolveKartuTest.php tests/Feature/Guru/JurnalKbmBatasEditTest.php tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php tests/Feature/Akademik/JurnalKbmAdaptiveTest.php tests/Feature/Guru/JurnalKbmControllerTest.php tests/Feature/Guru/JurnalKbmTenantScopeTest.php --compact`
  - Expected: Semua PASS 100%.

- [ ] **Step 3: Jalankan Pint**
  - Run: `vendor/bin/pint --dirty --format agent`
  - Expected: `{"tool":"pint","result":"passed"}`.

- [ ] **Step 4: Update handoff log `.agents/logs/2026-09-11-jadwal-piket-guru-agregat-dan-ui.md`**
  - Tulis ringkasan hasil kerja, keputusan teknis, dan verifikasi visual.
