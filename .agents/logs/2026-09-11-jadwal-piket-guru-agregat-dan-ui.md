# Handoff Log: Dukungan Agregat Yayasan & Redesign UI/UX Jadwal Piket Guru

**Tanggal:** 2026-09-11  
**Branch:** `rbac-v2` (Local Only — Tidak dimerge, tidak dipush)  
**Dokumen Terkait:**
- Spec: [`.agents/specs/2026-09-11-jadwal-piket-guru-agregat-dan-ui.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-11-jadwal-piket-guru-agregat-dan-ui.md)
- Plan: [`.agents/plans/2026-09-11-jadwal-piket-guru-agregat-dan-ui.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-11-jadwal-piket-guru-agregat-dan-ui.md)
- Handoff Log Sebelumnya: [`.agents/logs/2026-09-11-jadwal-piket-guru-audit-perbaikan.md`](file:///d:/laragon/www/pintera-app/.agents/logs/2026-09-11-jadwal-piket-guru-audit-perbaikan.md)

---

## 1. Apa yang Dikerjakan

1. **Dukungan Mode Agregat Yayasan ("Semua Lembaga")**:
   - Memperbaiki `JadwalPiketMingguanController::index()` sehingga tidak lagi memicu HTTP 422 saat `session('active_lembaga_id') === null`.
   - Mengaktifkan pembatasan multi-tenant otomatis via `TenantScope` pada model `JadwalPiketMingguan` dan `PiketHarian` untuk mengambil seluruh jadwal dari semua unit lembaga di bawah yayasan pengguna.
   - Menambahkan filter toolbar: pencarian nama guru (`search`), filter `hari` (Senin–Sabtu), filter `semester_id`, dan dropdown filter `lembaga_id` (hanya muncul di mode agregat).
   - Menambahkan perhitungan 4 ringkasan metrik KPI: `totalJadwal`, `guruTerjadwal`, `overrideAktif`, dan `lembagaTerjadwal`.
   - Menambahkan proteksi pada `create()`: jika pengguna berada dalam mode "Semua Lembaga", pengguna diarahkan kembali ke index dengan notifikasi flash yang ramah.
   - Memperbaiki `PiketHarianController::destroy()` agar admin yayasan di mode agregat dapat menghapus baris override manual milik unit sekolahnya.
   - Commit backend: `689fccb1`.

2. **Redesign UI/UX Standar Pintera**:
   - Melebarkan container dari `max-w-4xl` sempit menjadi container responsif `max-w-7xl` dengan padding `px-4 sm:px-6 lg:px-8`.
   - Header terintegrasi dengan `<x-scope-badge :is-yayasan="$isYayasan" :active-lembaga="$activeLembaga" />` dan tombol "+ Tambah Jadwal" yang otomatis disabled dengan tooltip saat mode agregat.
   - 4 Stat Tiles (`<x-stat-tile>`) dengan tone warna dan icon Pintera (`calendar_month`, `school`, `edit_calendar`, `apartment`/`date_range`).
   - Card Toolbar Filter & Pencarian responsif dengan tombol submit dan tautan Reset Filter.
   - Tabbed Navigation berbasis Alpine.js (`x-data="{ activeTab: 'mingguan' }"`):
     - **Tab 1: Jadwal Mingguan**: Tabel template mingguan dengan avatar inisial guru, badge hari berwarna, semester, kolom Lembaga (saat agregat), dan aksi Edit / Hapus via `confirmDialog`.
     - **Tab 2: Kalender Piket Harian**: Banner info sinkronisasi otomatis, tabel 60 baris mendatang dengan badge status (*Otomatis* vs *Override Manual*), dan kolom Lembaga.
     - **Tab 3: Override Manual**: Form tambah override dengan `tomSelectPegawai` (saat 1 lembaga aktif) atau notice banner amber informatif (saat mode agregat yayasan), serta tabel daftar override aktif mendatang dengan aksi hapus.
     - Empty states modern untuk setiap tab.
   - Rebuild bundle Vite via `npm.cmd run build`.
   - Commit UI: `b8580dc0`.

3. **Verifikasi Visual & Regresi Pengujian**:
   - Verifikasi dev-server via browser subagent pada mode agregat (`?switch_lembaga=all`) dan mode 1 lembaga (`?switch_lembaga=1`). Seluruh navigasi tab, filter, badge, dan modal konfirmasi berjalan mulus.
   - 20 controller tests di `JadwalPiketMingguanControllerTest` & `PiketHarianControllerTest`: **PASSED (100%)**.
   - 15 test suite terkait (80 tests, 192 assertions): **PASSED (100%)** dalam 14.25 detik.
   - Laravel Pint: **PASSED** (`clean`).

---

## 2. Keputusan Penting yang Diambil

1. **Pemanfaatan `TenantScope` Bawaan Model**:
   - Model `JadwalPiketMingguan` dan `PiketHarian` sudah memiliki trait `BelongsToTenant`. Ketika `$targetLembagaId` bernilai null pada aktor yayasan, `TenantScope` secara otomatis menambahkan klausa `whereIn('lembaga_id', Lembaga::where('yayasan_id', $yayasanId)->select('id'))`. Dengan demikian, tidak diperlukan query join manual yang rumit, menjaga kode tetap aman dan konsisten dengan arsitektur multi-tenancy Pintera.

2. **Guarding Aksi Mutasi pada Mode Agregat**:
   - Penambahan jadwal mingguan maupun override manual secara bisnis mutlak memerlukan identitas unit sekolah (`lembaga_id`). Pada mode "Semua Lembaga", tombol "+ Tambah Jadwal" ditampilkan dalam keadaan disabled dengan tooltip yang mengarahkan pengguna untuk memilih unit lembaga di topbar terlebih dahulu (mengikuti standar yang dipakai pada modul `KomponenPenilaian` dan `Kelas`).
   - Pada Tab Override Manual, form input digantikan oleh banner amber panduan yang ramah saat mode agregat aktif.

3. **Otorisasi Hapus Override Lintas Lembaga**:
   - Di `PiketHarianController::destroy()`, pemeriksaan kepemilikan diperluas: jika aktor berscope yayasan, otorisasi dicek melalui `$piketHarian->lembaga->yayasan_id === $actingUser->yayasan_id`, sehingga admin yayasan di mode agregat dapat menghapus override tanpa terblokir error 403.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Status Git**:
   - Branch: `rbac-v2`
   - Semua perubahan tersimpan secara lokal dan belum dimerge ataupun dipush ke origin.
2. **Cakupan Pengujian**:
   - Sebanyak 80 tests di 15 test suite terkait lulus dengan 192 assertions.
   - Tidak ada file di luar modul piket (`JadwalPiketMingguanController`, `PiketHarianController`, dan view `piket-guru/index.blade.php`) yang terpengaruh.
