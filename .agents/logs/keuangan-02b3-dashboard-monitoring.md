# Handoff Log: Keuangan 02b3 - Dashboard Monitoring

**Tanggal:** 2026-08-11
**Branch:** `demo`
**Status:** Selesai diimplementasi dan terverifikasi penuh (HTTP tests + Browser subagent verification)
**Spec:** `.agents/specs/keuangan-02b3-dashboard-monitoring.md`
**Plan:** `.agents/plans/keuangan-02b3-dashboard-monitoring.md`

---

## Apa yang Dikerjakan

Implementasi dashboard monitoring per `jenis_tagihan` dengan 7 task lengkap:

### Task 1 - Controller Skeleton & Routing [x]
- Buat `app/Http/Controllers/Admin/JenisTagihanMonitoringController.php` dengan:
  - `index()` - dilindungi `authorize('jenis-tagihan.view')`
  - `batalTagihan()` - dilindungi `authorize('jenis-tagihan.edit')`
- Daftarkan dua route di `routes/admin.php`:
  - `GET admin/jenis-tagihan/{jenisTagihan}/monitoring` -> `admin.jenis-tagihan.monitoring.index`
  - `POST admin/jenis-tagihan/{jenisTagihan}/batal-tagihan/{tagihan}` -> `admin.jenis-tagihan.monitoring.batal`

### Task 2 - Monitoring Button di Index [x]
- Tambah `monitoringUrlTemplate` di `resources/js/jenis-tagihan-table.js`
- Tambah dropdown link "Monitoring" di `resources/views/admin/jenis-tagihan/index.blade.php`
- Hanya muncul untuk jenis_tagihan non-PPDB (`!['pendaftaran', 'daftar_ulang'].includes(item.kategori)`)

### Task 3 - Ringkasan Metrics & View Skeleton [x]
- Query metrics: `total_penerima`, `lunas`, `sebagian`, `belum_bayar`, `dibatalkan`, `total_tertagih`, `total_masuk`
- Buat `resources/views/admin/jenis-tagihan/monitoring/index.blade.php` dengan:
  - Ringkasan cards (4 card grid)
  - Alpine.js tab navigation (Daftar Penerima / Daftar Tunggakan)

### Task 4 - Tab Daftar Penerima [x]
- Query: `Tagihan::with('tagihable')->where('jenis_tagihan_id')->where('status != dibatalkan')->paginate(15)`
- Tabel: Penerima, Status (badge berwarna), Total Tagihan, Sisa Tagihan, Aksi
- Tombol "Batalkan" muncul hanya untuk `status === 'belum_bayar'`

### Task 5 - Tab Daftar Tunggakan [x]
- Query `GROUP BY tagihable_type, tagihable_id` untuk `status IN ('belum_bayar', 'sebagian')`
- Menghitung `SUM(net_amount - paid_amount) AS total_tunggakan` dan `COUNT(*) AS jumlah_tunggakan`
- Diurutkan descending by `total_tunggakan`

### Task 6 - Aksi Batalkan Tagihan [x]
- Form POST ke `batalTagihan()` via modal Alpine JS konfirmasi
- Guard order di controller:
  1. `authorize('jenis-tagihan.edit')` - permission check
  2. Validasi `cancel_reason` required|string|max:255
  3. `if status !== 'belum_bayar' -> abort(422)` - business rule
  4. Tenant scope check: `if tagihan->jenis_tagihan_id !== jenisTagihan->id -> abort(403)` - cross-tenant tamper guard
- Update: `status='dibatalkan'`, `cancelled_by`, `cancelled_at`, `cancel_reason`
- Response: `back()->with('success', ...)` -> redirect dengan flash

### Task 7 - Manual Browser Verification [x]
- Verifikasi otomatis menggunakan browser subagent (Puppeteer/Chrome) di `http://127.0.0.1:8000`:
  1. **Dropdown Aksi di Index (`/admin/jenis-tagihan`)**: Klik tombol "Aksi" pada baris SPP Bulanan (ID 9) berhasil menampilkan dropdown menu dengan link "Monitoring".
  2. **Navigasi ke Monitoring**: Klik link "Monitoring" berhasil mengarah ke `http://127.0.0.1:8000/admin/jenis-tagihan/9/monitoring`.
  3. **Alpine JS Tab Switching**: Klik tab "Daftar Tunggakan" berhasil beralih tampilan dan menampilkan tabel tunggakan (Siswa, Jumlah Tagihan, Total Tunggakan). Klik kembali ke "Daftar Penerima" berhasil kembali tanpa kendala.
  4. **Modal Batalkan Tagihan**: Klik tombol "Batalkan" pada baris siswa dengan status "Belum Bayar" berhasil membuka modal Alpine JS. Form `:action="cancelUrl"` terikat secara dinamis ke URL endpoint pembatalan tagihan yang bersangkutan.
  5. **Submit Pembatalan**: Pengisian alasan pembatalan dan submit form berhasil mengeksekusi request POST. Halaman ter-redirect dengan flash message hijau "Tagihan berhasil dibatalkan."
  6. **Pembaruan State UI**: Tagihan yang dibatalkan langsung tereksklusi dari daftar aktif tab "Daftar Penerima", dan metrik ringkasan kartu terbarui secara konsisten (Total Tertagih berkurang, Belum Bayar berkurang, status dibatalkan tercatat).

---

## Keputusan Penting yang Diambil

1. **Layout tag `<x-app-layout>`** - View menggunakan `<x-app-layout>` (konsisten dengan semua admin views lain).
2. **Tenant scope check di `batalTagihan()`** - Controller ditambahi guard `$tagihan->jenis_tagihan_id !== $jenisTagihan->id -> 403` karena Laravel implicit binding pada `Tagihan` tidak otomatis scope ke `jenis_tagihan_id`.
3. **`total_penerima` = COUNT ALL tagihan** - Termasuk yang dibatalkan, sesuai dengan semantik "total pernah tertagih". Kontras dengan `total_tertagih` yang mengecualikan tagihan dibatalkan.
4. **Pagination param name berbeda** - Penerima menggunakan `penerima_page` dan tunggakan menggunakan `tunggakan_page` agar paginasi keduanya independen di halaman yang sama.
5. **`MAX(id)` di query tunggakan** - Digunakan untuk kompatibilitas MySQL `ONLY_FULL_GROUP_BY` pada Eloquent paginator.
6. **Form POST standar dengan modal Alpine JS** - Menggunakan form POST tradisional dengan flash alert, konsisten dengan arsitektur form Laravel.

---

## Hal yang Dicatat untuk Review & Future Enhancement

### ?? Known Limitation & Future Enhancement (Tagihan Dibatalkan)
Tagihan berstatus `dibatalkan` saat ini tidak tampil di tabel Daftar Penerima (hanya terhitung di Ringkasan card). Filter toggle untuk menampilkannya adalah enhancement kandidat untuk sub-project berikutnya, bukan bagian dari scope 2b-3.

### ?? Technical Debt / Risk Area (Alpine JS)
Seperti pada 2b-1 dan 2b-2, error render-time pada template Alpine JS tidak terdeteksi oleh HTTP unit/feature tests standar. Oleh karena itu, manual browser verification / E2E visual check tetap menjadi protokol verifikasi yang wajib dijalankan setiap kali ada modifikasi view Blade yang menggunakan Alpine data bindings.

### ?? Git State
- Branch: `demo`
- Status: Siap direview / merge. Tidak di-push ke remote (menunggu keputusan user).

---

## Test Coverage

```
73 tests passed, 180 assertions (Full Keuangan suite)
10 tests passed, 33 assertions (Admin JenisTagihan Monitoring & Button suite)

- tests/Feature/Admin/JenisTagihanMonitoringTest.php: 8 tests passed
  * it denies access to monitoring page without jenis-tagihan.view permission
  * it enforces tenant scope on monitoring page
  * it allows access to monitoring page with proper permission and tenant
  * it calculates ringkasan metrics correctly
  * it lists daftar penerima correctly with pagination
  * it lists daftar tunggakan correctly with pagination
  * it can cancel a tagihan if status is belum_bayar
  * it cannot cancel a tagihan if status is not belum_bayar
- tests/Feature/Admin/JenisTagihanProsesButtonTest.php: 2 tests passed
  * it renders the Proses Tagihan action for a non-ppdb jenis_tagihan on the index page
  * it renders the Monitoring action for a non-ppdb jenis_tagihan on the index page
- tests/Feature/Keuangan/: 63 tests passed
```
