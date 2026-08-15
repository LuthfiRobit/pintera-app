# Perbaikan UI Admin Virtual Account Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memperbaiki dan menyelaraskan antarmuka (UI/UX) halaman Admin Virtual Account dengan standar Pintera (adopsi tabel aksi kiri Jenis Tagihan, modal Jadwal Pelajaran, optgroup kelas per Tahun Ajaran, card KPI, select-all, dan modal top-up manual dummy).

**Architecture:** Modifikasi view Blade, controller query untuk KPI & grouping kelas, penambahan modal Blade baru, dan penyempurnaan komponen Alpine.js untuk seleksi massal & click-to-select.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Tailwind CSS, Pest PHP.

---

### Task 1: Controller KPI Metrics & Grouped Kelas
**Files:**
- Modify: `app/Http/Controllers/Admin/VirtualAccountController.php:19-62`
- Test: `tests/Feature/Admin/VirtualAccountControllerTest.php`

- [ ] **Step 1: Update controller `index()` method**
  - Hitung `$totalVa` (count VA permanen aktif di lembaga admin), `$totalSaldo` (sum saldo wallet ber-VA di lembaga admin), dan `$totalBelumVa` (count siswa aktif tanpa permanent VA di lembaga admin).
  - Ambil `$kelasList` dengan relasi `tahunAjaran` dan kelompokkan dengan `$kelasList->groupBy(fn ($k) => $k->tahunAjaran?->nama ?? 'Tanpa Tahun Ajaran')`.
  - Teruskan `$totalVa`, `$totalSaldo`, `$totalBelumVa`, dan `$kelasListGrouped` ke view `admin.virtual-account.index`.

- [ ] **Step 2: Update test case in `VirtualAccountControllerTest.php`**
  - Pastikan test index tetap assertOk dan melihat data summary KPI.

- [ ] **Step 3: Run test**
  - Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php`
  - Expected: PASS

---

### Task 2: Index Page UI (KPI Cards, Header Buttons & Optgroup Filter)
**Files:**
- Modify: `resources/views/admin/virtual-account/index.blade.php`

- [ ] **Step 1: Tambahkan 3 KPI Metric Cards di atas Card Filter**
  - Card 1: Total Siswa Ber-VA (icon `credit-card` / `payments`, label "Total Siswa Ber-VA", nilai count).
  - Card 2: Total Saldo Terkumpul (icon `payments` / `wallet`, label "Total Saldo Terkumpul", nilai `Rp...`).
  - Card 3: Belum Memiliki VA (icon `person_add` / `group`, label "Belum Memiliki VA", nilai count).
- [ ] **Step 2: Perbarui Tombol Export & Generate**
  - Tambahkan icon SVG (`x-icon name="description"` untuk export, `x-icon name="add"` untuk generate).
  - Tambahkan `title="Ekspor daftar nomor Virtual Account ke file Excel"` dan `title="Buat nomor Virtual Account baru untuk siswa"`.
- [ ] **Step 3: Perbarui Filter Kelas dengan Optgroup**
  - Gunakan `@foreach ($kelasListGrouped as $tahunAjaranNama => $kelasList)` dengan `<optgroup label="{{ $tahunAjaranNama }}">`.

---

### Task 3: Table Design with Left Dropdown Action & Siswa NIS
**Files:**
- Modify: `resources/views/admin/virtual-account/_daftar.blade.php`

- [ ] **Step 1: Pindahkan kolom Aksi ke sisi paling kiri (sticky left)**
  - `<thead>`: `<th class="sticky left-0 z-10 bg-white px-5 py-3.5">Aksi</th>`.
  - `<tbody>`: `<td class="sticky left-0 z-10 bg-white px-5 py-4 shadow-[1px_0_0_0_#f3f4f6]">` dengan `<x-table-actions>`.
  - Menu 1: `<x-dropdown-link href="#" @click.prevent="$dispatch('open-riwayat-modal', { siswaId: ..., siswaNama: ... })">Lihat Riwayat</x-dropdown-link>`.
  - Menu 2: `<x-dropdown-link href="#" @click.prevent="$dispatch('open-topup-modal', { siswaId: ..., siswaNama: ..., vaNumber: ..., balance: ... })">Top-up Saldo</x-dropdown-link>`.
- [ ] **Step 2: Tampilkan Nama Siswa beserta NIS di bawahnya**
  - `<p class="font-bold text-gray-900">{{ $va->wallet->siswa->nama_lengkap ?? '-' }}</p>`
  - `<p class="font-mono text-xs text-gray-400">NIS: {{ $va->wallet->siswa->nis ?? '-' }}</p>`

---

### Task 4: Modal Top-up Saldo Manual (UI Dummy)
**Files:**
- Create: `resources/views/admin/virtual-account/_topup-modal.blade.php`
- Modify: `resources/views/admin/virtual-account/index.blade.php` (include modal)

- [ ] **Step 1: Buat view `_topup-modal.blade.php`**
  - Menggunakan struktur modal `jadwal-pelajaran` (backdrop blur/transition, rounded-2xl, shadow-elevated, close button cancel icon).
  - Alpine state: `open`, `siswaId`, `siswaNama`, `vaNumber`, `balance`, `amount`, `catatan`.
  - Event listener: `x-on:open-topup-modal.window="..."`.
  - Form UI dummy: Nama Siswa (disabled), Nomor VA (disabled), Saldo Saat Ini (disabled), Input Nominal Top-up (Rp), Catatan.
  - Tombol Batal & Simpan Top-up (menampilkan alert/toast dan menutup modal).

---

### Task 5: Penyelarasan Style Modal Riwayat
**Files:**
- Modify: `resources/views/admin/virtual-account/_riwayat-modal.blade.php`

- [ ] **Step 1: Selaraskan style modal riwayat**
  - Tambahkan backdrop transition (`bg-gray-900/60`), `rounded-2xl`, header icon (`x-icon name="history"`), dan close icon (`x-icon name="cancel"`).

---

### Task 6: Modal Generate VA (Optgroup, Select All & Row Click)
**Files:**
- Modify: `resources/views/admin/virtual-account/_generate-modal.blade.php`
- Modify: `resources/js/virtual-account-generate-modal.js`

- [ ] **Step 1: Update `virtual-account-generate-modal.js`**
  - Tambahkan method `toggleSelectAll()` dan computed getter/check `isAllSelected`.
- [ ] **Step 2: Update `_generate-modal.blade.php`**
  - Selaraskan style modal dengan tema standar `jadwal-pelajaran`.
  - Gunakan `<optgroup>` per Tahun Ajaran untuk dropdown filter kelas modal.
  - Tambahkan checkbox "Pilih Semua" pada `<thead>`.
  - Jadikan baris `<tr>` calon siswa dapat diklik langsung (`@click="toggleSiswa(siswa.id)" cursor-pointer`).

---

### Task 7: Build Assets & Verification Test Sweep
**Files:**
- Run: `npm.cmd run build`
- Run: `php artisan test tests/Feature/Admin/VirtualAccountControllerTest.php tests/Feature/Admin/VirtualAccountAuthorizationTest.php`

- [ ] **Step 1: Build frontend bundle**
- [ ] **Step 2: Run all test suites to ensure 100% PASS**
- [ ] **Step 3: Write handoff log & commit**
