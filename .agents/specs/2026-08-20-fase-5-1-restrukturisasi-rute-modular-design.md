# FASE 5.1: Restrukturisasi Rute Modular — Spec

## 1. Latar Belakang

`routes/admin.php` (368 baris) secara fisik berisi 3 grup route yang tidak berhubungan:

1. **`admin.*`** (baris 68-314) — prefix `/admin`, satu grup `Route::` raksasa yang mencampur ~15 domain (roles/users, lembaga, guru, akademik master data, siswa, SPMB, keuangan, RPP, penilaian/rapor, kasus-admin, sarpras, pengadaan) tanpa pemisahan file.
2. **`kasus.*`** (baris 320-341) — prefix `/kasus`, workflow konseling lintas-role (siswa, orang tua, guru BK). Tidak berada di bawah `/admin` sama sekali, cuma numpang secara fisik di file yang sama.
3. **`guru.*`** (baris 343-368) — prefix `/guru`, portal guru (jurnal KBM, asesmen, catatan rapor). Juga tidak berada di bawah `/admin`.

Master plan modul akademik (`.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`, FASE 5.1) awalnya membayangkan split per SCOPE aktor (`routes/lembaga.php`, `routes/guru.php`, dst, dengan alias backward-compat). Setelah dibahas, arah itu diganti: split per **MODUL domain**, karena:

- Satu blok modul (mis. Sarpras, Pengadaan) di `admin.php` sudah mencampur controller dari namespace `Lembaga\` dan `Yayasan\` dalam satu grup URL — split per scope-aktor akan memaksa memecah satu fitur kohesif jadi dua file, dan butuh keputusan rename + alias yang tidak perlu.
- Axis modul sudah dipakai konsisten di dua tempat lain: nama permission (`modul.turunan.aksi`, RBAC v2) dan folder domain (`app/Domains/<Modul>/`). Mengikuti axis yang sama di routing menjaga satu cara berpikir yang konsisten.
- Zero risk: tidak ada rename nama route atau URL, sehingga tidak butuh alias backward-compat sama sekali.

Checklist master plan FASE 5.1 akan diperbarui mengikuti spec ini (lihat §6).

## 2. Tujuan

Pecah `routes/admin.php` menjadi file-file kecil yang masing-masing punya satu tanggung jawab jelas, **tanpa mengubah satu pun nama route, URI, urutan pendaftaran relatif, atau middleware** — murni pemindahan fisik teks `Route::`.

## 3. Struktur Akhir

```
routes/
  admin.php              ← loader: middleware+prefix+name wrapper, isinya cuma require file modul
  admin/
    roles.php
    lembaga.php
    guru-data.php
    whatsapp-template.php
    akademik-master.php
    siswa.php
    spmb.php
    keuangan.php
    rpp.php
    penilaian-rapor.php
    kasus-admin.php
    sarpras.php
    pengadaan.php
  kasus.php               ← dipindah utuh dari admin.php (bukan bagian grup 'admin')
  guru.php                ← dipindah utuh dari admin.php (bukan bagian grup 'admin')
```

### 3.1 Pemetaan baris sumber → file tujuan (dari `routes/admin.php` versi saat ini, commit `a7ce013`)

| File tujuan | Baris sumber | Isi |
|---|---|---|
| `routes/admin/roles.php` | 70-73 | roles, users (+ toggle-active) |
| `routes/admin/lembaga.php` | 74-93 | lembaga, pengaturan-yayasan, data-periodik/ekstrakurikuler/layanan-khusus/program-inklusi |
| `routes/admin/guru-data.php` | 94-115 | guru (CRUD, status), riwayat-pendidikan, sertifikasi, jabatan-tambahan (+ master), jenis-karyawan-master |
| `routes/admin/whatsapp-template.php` | 117-118 | whatsapp-template |
| `routes/admin/akademik-master.php` | 119-120, 134-138, 146-153, 224-244 | mata-pelajaran, kelas, kalender-akademik, pengaturan-akademik, tahun-ajaran, semester, pola-jam, jam-pelajaran, jadwal-pelajaran |
| `routes/admin/siswa.php` | 121-133, 139-144 | siswa (CRUD, generate-akun, status, reset-password), siswa-orang-tua, orang-tua, karyawan, siswa-spmb-daftar, siswa-import |
| `routes/admin/spmb.php` | 155-183, 185-186 | jenis-tes, gelombang-ppdb, jalur-ppdb, formulir-field, dokumen-syarat, seleksi, spmb-konfigurasi, spmb-pendaftaran (termasuk `tagihan.susulan` baris 181, tetap di sini karena posisinya di antara baris spmb-pendaftaran lain dan urutannya bergantung pada blok itu), sk-ppdb |
| `routes/admin/keuangan.php` | 188-222 | jenis-tagihan, kategori-keringanan, tagihan, cicilan, pembayaran, manual-payment, virtual-account |
| `routes/admin/rpp.php` | 247-253 | rpp |
| `routes/admin/penilaian-rapor.php` | 255-271 | komponen-penilaian, rapor (+ persetujuan), kenaikan-kelas |
| `routes/admin/kasus-admin.php` | 273-279 | kasus (index, triase, assign-konselor, destroy, restore, log-akses, terhapus) — CATATAN: ini berbeda dari `routes/kasus.php` (§3 bawah), yang merupakan workflow lintas-role di luar prefix admin |
| `routes/admin/sarpras.php` | 282-292 | sarpras.* |
| `routes/admin/pengadaan.php` | 295-313 | pengadaan.* |
| `routes/kasus.php` | 316-341 (termasuk `Route::bind('kasus', ...)` di 320-324) | workflow kasus lintas-role, prefix `/kasus`, TIDAK di bawah admin |
| `routes/guru.php` | 343-368 | portal guru (jurnal-kbm, asesmen, komponen-penilaian, rapor catatan), prefix `/guru`, TIDAK di bawah admin |

Setiap `use` statement (baris 3-65) ikut dipindah ke file tujuan masing-masing kontroller dipakai (hapus dari `admin.php` yang jadi loader, kecuali yang masih dipakai `Route::bind`).

### 3.2 Bentuk `routes/admin.php` setelah refactor

```php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    require base_path('routes/admin/roles.php');
    require base_path('routes/admin/lembaga.php');
    require base_path('routes/admin/guru-data.php');
    require base_path('routes/admin/whatsapp-template.php');
    require base_path('routes/admin/akademik-master.php');
    require base_path('routes/admin/siswa.php');
    require base_path('routes/admin/spmb.php');
    require base_path('routes/admin/keuangan.php');
    require base_path('routes/admin/rpp.php');
    require base_path('routes/admin/penilaian-rapor.php');
    require base_path('routes/admin/kasus-admin.php');
    require base_path('routes/admin/sarpras.php');
    require base_path('routes/admin/pengadaan.php');
});
```

Setiap file di `routes/admin/*.php` **tidak** membungkus isinya dengan `Route::group` prefix/name tambahan untuk level admin (karena sudah diwariskan dari wrapper di atas) — isinya persis potongan `Route::` yang dipindah, plus blok `Route::prefix('sarpras')->name('sarpras.')->group(...)` dst yang SUDAH ADA di source tetap dipertahankan apa adanya (itu sub-grouping yang sudah benar, bukan yang mau dihapus).

`routes/kasus.php` dan `routes/guru.php` masing-masing berdiri sebagai file top-level baru, isinya persis baris 316-341 dan 343-368 dipindah apa adanya (termasuk `Route::bind('kasus', ...)`).

Pendaftarannya mengikuti pola yang SUDAH ADA di `routes/web.php` untuk `spmb.php`/`portal.php` — bukan lewat `bootstrap/app.php` (yang cuma mendaftarkan `web.php` sebagai entry point tunggal, lihat `->withRouting(web: __DIR__.'/../routes/web.php', ...)`). Tambahkan 2 baris `require` baru di `routes/web.php` setelah baris `require __DIR__.'/admin.php';` (baris 30):

```php
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/kasus.php';
require __DIR__.'/guru.php';
```

## 4. Aturan Pengerjaan (mengikat semua task)

1. **Tidak ada perubahan** nama route, URI, urutan relatif pendaftaran, atau middleware apa pun. Ini murni potong-tempel teks.
2. Blok yang urutan pendaftarannya saling bergantung (contoh: `siswa/generate-akun-massal` harus terdaftar sebelum `siswa/{siswa}` supaya tidak ketabrak wildcard resource) dipindah sebagai satu unit ke file yang sama, tidak dipisah lintas file.
3. Satu file/modul tujuan = satu commit.
4. Verifikasi wajib tiap commit: bandingkan **output penuh** `php artisan route:list` sebelum vs sesudah ekstraksi modul tersebut. Named route, URI, method, action (FQCN controller + method), dan middleware harus identik untuk setiap baris — TIDAK boleh ada yang hilang, berubah, atau bertambah.
5. Full test suite (`php artisan test`) dijalankan **sekali** di akhir seluruh proses (bukan per-task), karena setiap task sudah diverifikasi lewat diff `route:list` yang matematis membuktikan tidak ada perubahan behavior.

## 5. Di Luar Cakupan

- Migrasi namespace controller ke module-first. Controller tetap di `Admin\`, `Lembaga\`, `Yayasan\`, `Guru\` seperti sekarang; hanya file route yang ditata ulang.
- Perubahan nama route, alias, atau backward-compat redirect — tidak diperlukan karena tidak ada nama yang berubah.
- FASE 5.2 (Auto-Discovery Permissions Sync run) dan FASE 5.3 (Full Regression) dari master plan — keduanya langkah terpisah setelah 5.1 selesai, tidak termasuk spec ini.

## 6. Update Master Plan

Baris 120-122 di `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md` (checklist FASE 5.1) akan diperbarui saat plan implementasi ditulis, mengganti deskripsi split-per-scope-aktor + alias dengan split-per-modul + zero-rename sesuai spec ini.

## 7. Testing / Verifikasi

- Per task: diff `php artisan route:list` (full, bukan filtered) sebelum/sesudah — harus identik.
- Akhir: `php artisan test` full suite, harus tetap hijau (baseline saat ini: 1861 passed, 0 failed).
- Tidak ada test baru yang perlu ditulis — tidak ada logic baru, murni reorganisasi file routing.

## 8. Asumsi

- Baseline `routes/admin.php` yang dipetakan di §3.1 adalah versi pada commit `a7ce013` (HEAD `rbac-v2` saat spec ini ditulis). Kalau ada commit baru yang mengubah `admin.php` sebelum plan dieksekusi, pemetaan baris perlu diverifikasi ulang terhadap versi terbaru sebelum implementasi dimulai.
