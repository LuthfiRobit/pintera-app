# Keuangan 02b3: Dashboard Monitoring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun dashboard monitoring per `jenis_tagihan` yang menampilkan ringkasan performa tagihan, daftar siswa penerima, daftar tunggakan, serta fitur untuk membatalkan tagihan.

**Architecture:** 
- Controller mandiri `JenisTagihanMonitoringController` untuk menghindari *bloat* pada `JenisTagihanController`.
- Tampilan satu halaman dengan **Alpine JS Tabs** untuk berpindah antara Ringkasan, Daftar Penerima, dan Daftar Tunggakan.
- Pagination konvensional Laravel untuk `Daftar Penerima` dan `Daftar Tunggakan` mengingat volume data tagihan per institusi bisa mencapai ribuan baris.
- Aksi "Batalkan Tagihan" dieksekusi via HTTP POST (bukan AJAX) atau fetch JS (seperti Proses Tagihan) yang dilindungi dengan Modal Konfirmasi Alpine JS.

**Tech Stack:** Laravel 12, Pest, Alpine.js, Tailwind CSS.

## Global Constraints

- **Blind Spot Alpine JS**: Seperti ditemukan di sesi 2b-1/2b-2, Alpine JS *render-time errors* tidak tertangkap oleh test suite HTTP-level. **Task 7 diwajibkan untuk mengeksekusi manual browser check menggunakan browser subagent** untuk memverifikasi interaktivitas Tab dan Modal konfirmasi, termasuk *submit* form.
- "Batalkan Tagihan" HANYA boleh diproses jika `status` tagihan adalah `belum_bayar`. Membatalkan tagihan berstatus `sebagian` atau `lunas` adalah diluar *scope* (akan masuk ranah *refund* di sub-project berikutnya).
- `paid_amount` saat ini diasumsikan 0 karena integrasi pembayaran belum ada.
- Tidak membangun notifikasi atau *export* laporan.

---

### Task 1: Controller Skeleton & Routing (with Permission/Tenant-Scope)

**Files:**
- Create: `app/Http/Controllers/Admin/JenisTagihanMonitoringController.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/JenisTagihanMonitoringTest.php`

**Interfaces:**
- Produces: Route `admin.jenis-tagihan.monitoring.index` dan `admin.jenis-tagihan.monitoring.batal`.

- [ ] **Step 1: Write the failing test**
  - Uji tenant-scope (akses `jenis_tagihan` lembaga lain akan 404).
  - Uji permission: `index` butuh `jenis-tagihan.view`, `batalTagihan` butuh `jenis-tagihan.edit` (atau role relevan).
- [ ] **Step 2: Run test to verify it fails**
- [ ] **Step 3: Write minimal implementation**
  - Buat `JenisTagihanMonitoringController`. Method `index()` memanggil `$this->authorize('view', $jenisTagihan)`. Method `batalTagihan()` memanggil `$this->authorize('update', $jenisTagihan)`.
  - Daftarkan route di `routes/admin.php` di bawah prefix `jenis-tagihan/{jenisTagihan}` di dalam group middleware `['auth', 'verified']`. Implicit route model binding di Laravel akan secara otomatis mengaplikasikan `TenantScope` untuk `JenisTagihan`.
- [ ] **Step 4: Run test to verify it passes**
- [ ] **Step 5: Commit**

### Task 2: "Monitoring" Button in Index Page

**Files:**
- Modify: `resources/views/admin/jenis-tagihan/index.blade.php`
- Modify: `resources/js/jenis-tagihan-table.js` (tambah `monitoringUrlTemplate`)
- Test: `tests/Feature/Admin/JenisTagihanProsesButtonTest.php` atau test baru.

**Interfaces:**
- Consumes: Route dari Task 1.

- [ ] **Step 1: Write the failing test**
  - Pastikan link Monitoring ada untuk tagihan non-PPDB.
- [ ] **Step 2: Run test to verify it fails**
- [ ] **Step 3: Write minimal implementation**
  - Tambahkan `monitoringUrlTemplate: @js(route('admin.jenis-tagihan.monitoring.index', ['jenisTagihan' => '__ID__']))` di konfigurasi `jenisTagihanTable`.
  - Tambahkan method `monitoringUrl(item)` di `jenis-tagihan-table.js`.
  - Tambahkan `<x-dropdown-link x-bind:href="monitoringUrl(item)">Monitoring</x-dropdown-link>` di `index.blade.php` (di dalam `template x-if="!['pendaftaran', 'daftar_ulang'].includes(item.kategori)"`).
- [ ] **Step 4: Run test to verify it passes**
- [ ] **Step 5: Commit**

### Task 3: Ringkasan Metrics & View Skeleton

**Files:**
- Create: `resources/views/admin/jenis-tagihan/monitoring/index.blade.php`
- Modify: `app/Http/Controllers/Admin/JenisTagihanMonitoringController.php`
- Test: `tests/Feature/Admin/JenisTagihanMonitoringTest.php`

**Interfaces:**
- Produces: Variabel `$ringkasan` (total_siswa, lunas, sebagian, belum_bayar, dibatalkan, total_tertagih, total_masuk).

- [ ] **Step 1: Write the failing test**
  - Buat dummy `Siswa` dan `Tagihan` dengan status berbeda, pastikan `$ringkasan` di view memiliki angka yang akurat.
- [ ] **Step 2: Run test to verify it fails**
- [ ] **Step 3: Write minimal implementation**
  - Di `index()`, hitung agregasi `$jenisTagihan->tagihan()`: total penerima (`count`), jumlah per status, total tertagih (`sum(net_amount)` where not dibatalkan).
  - Buat view `monitoring/index.blade.php` yang memiliki `x-data="{ activeTab: 'penerima', modalBatalkan: false, selectedTagihan: null }"`.
  - Buat bagian "Ringkasan" (Card grid di atas tabs).
  - Buat skeleton navigasi Tabs (Penerima & Tunggakan) menggunakan `x-show="activeTab === 'penerima'"`.
- [ ] **Step 4: Run test to verify it passes**
- [ ] **Step 5: Commit**

### Task 4: Tab "Daftar Penerima"

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTagihanMonitoringController.php`
- Modify: `resources/views/admin/jenis-tagihan/monitoring/index.blade.php`
- Test: `tests/Feature/Admin/JenisTagihanMonitoringTest.php`

**Interfaces:**
- Produces: Variabel `$penerima` (`LengthAwarePaginator` dari `Tagihan` beserta relasi `tagihable`).

- [ ] **Step 1: Write the failing test**
  - Uji apakah view `index` merender list tagihan, lengkap dengan nominal dan tombol "Batalkan" jika status `belum_bayar`.
- [ ] **Step 2: Run test to verify it fails**
- [ ] **Step 3: Write minimal implementation**
  - Di `index()`, ambil tagihan dengan pagination `->paginate(50, ['*'], 'penerima_page')`.
  - Di view, buat tabel: Nama Siswa, Periode, Nominal, Diskon, Net, Terbayar, Status, Aksi.
  - Tampilkan tombol "Batalkan" (trigger Modal Alpine JS) HANYA untuk status `belum_bayar`.
- [ ] **Step 4: Run test to verify it passes**
- [ ] **Step 5: Commit**

### Task 5: Tab "Daftar Tunggakan"

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTagihanMonitoringController.php`
- Modify: `resources/views/admin/jenis-tagihan/monitoring/index.blade.php`
- Test: `tests/Feature/Admin/JenisTagihanMonitoringTest.php`

**Interfaces:**
- Produces: Variabel `$tunggakan` (`LengthAwarePaginator` hasil GROUP BY tagihable).

- [ ] **Step 1: Write the failing test**
  - Uji kalkulasi total tunggakan per siswa (rekap lintas periode).
- [ ] **Step 2: Run test to verify it fails**
- [ ] **Step 3: Write minimal implementation**
  - Di `index()`, query tunggakan: `select tagihable_id, tagihable_type, sum(net_amount - paid_amount) as total_tunggakan from tagihan where jenis_tagihan_id = ? and status in ('belum_bayar', 'sebagian') group by tagihable_type, tagihable_id order by total_tunggakan desc`.
  - Paginate query ini `->paginate(50, ['*'], 'tunggakan_page')`. (Gunakan eager loading manual atau JOIN untuk relasi Siswa agar performa baik).
  - Tampilkan di tab Tunggakan.
- [ ] **Step 4: Run test to verify it passes**
- [ ] **Step 5: Commit**

### Task 6: Action "Batalkan Tagihan"

**Files:**
- Modify: `app/Http/Controllers/Admin/JenisTagihanMonitoringController.php` (method `batalTagihan`)
- Modify: `resources/views/admin/jenis-tagihan/monitoring/index.blade.php` (Modal Alpine JS form POST)
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/JenisTagihanMonitoringTest.php`

**Interfaces:**
- Consumes: `cancel_reason` text dari form input modal.

- [ ] **Step 1: Write the failing test**
  - POST request membatalkan tagihan `belum_bayar` dengan alasan sukses.
  - POST request menolak membatalkan tagihan `sebagian` mengembalikan `422 Unprocessable Entity`.
- [ ] **Step 2: Run test to verify it fails**
- [ ] **Step 3: Write minimal implementation**
  - Method `batalTagihan(Request $request, JenisTagihan $jenisTagihan, Tagihan $tagihan)` memvalidasi `cancel_reason` `required|string|max:1000`.
  - Cek jika `$tagihan->status !== 'belum_bayar'`, lemparkan `ValidationException` atau return response JSON/View dengan HTTP code `422`.
  - Update `$tagihan->update(['status' => 'dibatalkan', 'cancelled_by' => auth()->id(), 'cancelled_at' => now(), 'cancel_reason' => $request->cancel_reason])`.
  - Jika via form biasa (bukan AJAX), gunakan `back()->with('success', 'Tagihan berhasil dibatalkan.')`. Jika AJAX, response JSON 200.
  - Di view, lengkapi Alpine modal untuk mensubmit URL POST dengan alasan pembatalan.
- [ ] **Step 4: Run test to verify it passes**
- [ ] **Step 5: Commit**

### Task 7: Manual Browser Verification

**Files:**
- Modify: `.agents/logs/keuangan-02b3-dashboard-monitoring.md` (Handoff Log)

**Interfaces:**
- Consumes: Semua fungsionalitas UI yang telah dibangun di atas.

- [ ] **Step 1: Siapkan Handoff Log**
  - Buat file log `.agents/logs/keuangan-02b3-dashboard-monitoring.md`.
- [ ] **Step 2: Dispatch Browser Subagent**
  - Instruksikan subagent untuk login sebagai `admin_keuangan`.
  - Masuk ke salah satu dashboard Monitoring `jenis_tagihan`.
  - Verifikasi: Card Ringkasan ter-render (tidak ada JS error).
  - Verifikasi: Pindah ke tab "Daftar Tunggakan" dan "Daftar Penerima" berjalan lancar dengan *Alpine click*.
  - Verifikasi Batalkan: Klik aksi "Batalkan" untuk tagihan berstatus *belum_bayar*.
  - Verifikasi: Modal Alpine muncul, isi alasan, lalu *submit* form.
  - Verifikasi: Halaman termuat ulang (atau merespons JSON) dan status tagihan tersebut sukses berubah menjadi `dibatalkan` di UI *Daftar Penerima*.
- [ ] **Step 3: Dokumentasikan Hasil**
  - Catat bukti eksekusi manual (apakah ada bug render-time yang terdeteksi) ke dalam Handoff Log.
- [ ] **Step 4: Commit**
