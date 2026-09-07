# Handoff Log: Perbaikan Antarmuka Halaman Index Siswa (Header Scope, Kolom Tabel & Pagination)

> **Dokumen Terkait**:
> - Spec: [`.agents/specs/2026-09-07-siswa-index-ui-perbaikan.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-07-siswa-index-ui-perbaikan.md)
> - Plan: [`.agents/plans/2026-09-07-siswa-index-ui-perbaikan.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-07-siswa-index-ui-perbaikan.md)
> - Tanggal Selesai: 7 September 2026
> - Branch: `rbac-v2` (belum di-merge ke `main`)
> - Base commit: `99efd949`

---

## 1. Apa yang Dikerjakan

Menuntaskan 4 perbaikan antarmuka (UI/UX) pada halaman Data Induk Siswa (`admin.siswa.index`) dengan alur TDD ketat (red-green-refactor) dan penataan responsif mobile/desktop:

### Ringkasan Pekerjaan & Commit Log:

1. **Header Scope Yayasan Dinamis** (`4073e09e`)
   - **Kebutuhan**: Jika scope yayasan tidak memilih lembaga di topbar switcher, header menampilkan `SISWA SEMUA LEMBAGA`. Jika yayasan memilih lembaga (mis. "SMA IT Pintera"), header menampilkan `SISWA SMA IT PINTERA`. Jika pengguna adalah role scope lembaga (bukan yayasan), header menampilkan default `Siswa`.
   - **Implementasi**:
     - `app/Http/Controllers/Admin/SiswaController.php`: Menghitung `$isYayasan` dan `$activeLembaga` (menggunakan `Lembaga::withoutGlobalScopes()->find(...)`), lalu mengirimkannya ke view `admin.siswa.index`.
     - `resources/views/admin/siswa/index.blade.php`: `<h1>` menampilkan teks dinamis huruf kapital sesuai kondisi, dilengkapi badge visual penanda nama lembaga / "Semua Lembaga" di samping header.
   - **Test**: Ditambahkan 3 test TDD di `tests/Feature/Admin/SiswaCrudTest.php` untuk memverifikasi skenario yayasan mode semua lembaga, yayasan mode lembaga terpilih, dan aktor lembaga.

2. **Penggabungan Kolom NIS & Nama Siswa serta Penambahan Kolom Lembaga** (`43e74bc9`)
   - **Kebutuhan**: Menggabungkan kolom NIS dan Nama siswa menjadi satu kolom dengan nama siswa di atas dan NIS di bawah. Menambahkan kolom Lembaga pada tabel siswa.
   - **Implementasi**:
     - `app/Http/Controllers/Admin/SiswaController.php`: Menambahkan eager loading `'lembaga'` pada query utama (`Siswa::with(['lembaga', 'kelas', 'kelasTerakhir', 'person'])`) untuk mencegah N+1 query.
     - `resources/views/admin/siswa/_daftar.blade.php`: Kolom terpisah `<th>NIS</th>` dan `<th>Nama</th>` disatukan menjadi `<th>Siswa</th>` (nama siswa bold di atas, NIS monospace kecil di bawah). Ditambahkan kolom `<th>Lembaga</th>` (`$siswa->lembaga?->nama ?? '—'`). `colspan` untuk pesan tabel kosong tetap `6`.
   - **Test**: Ditambahkan test di `tests/Feature/Admin/SiswaCrudTest.php` untuk memverifikasi ketiadaan header NIS/Nama terpisah, kehadiran header Siswa dan Lembaga, serta rendering nama siswa, NIS, dan nama lembaga.

3. **Perbaikan Responsivitas Pagination** (`0e1b364c`)
   - **Kebutuhan**: Pagination sebelumnya tidak ramah layar kecil (deretan tombol halaman bertumpuk/meluap dan sulit disentuh pada mobile viewport).
   - **Implementasi**:
     - `resources/views/pagination/tailadmin.blade.php`: Dirombak menggunakan layout dual-breakpoint:
       - **Mobile (`sm:hidden`)**: Tombol navigasi sentuh "Sebelumnya" dan "Berikutnya" dengan visual rapi, indikator nomor halaman ringkas "Hal X / Y", serta teks ringkasan "Menampilkan X–Y dari Z entri" di tengah.
       - **Desktop (`hidden sm:flex`)**: Ringkasan entri di sisi kiri dan tombol deretan nomor halaman fleksibel di sisi kanan.
   - **Test**: Ditambahkan test di `tests/Feature/Admin/SiswaCrudTest.php` yang menguji responsivitas pagination dengan 25 data siswa (per_page 10).

4. **Verifikasi, Pint, & Handoff**
   - Seluruh 24 test di `SiswaCrudTest` lulus (78 assertions).
   - Seluruh 58 test suite siswa (`php artisan test --filter="Siswa"`) lulus (188 assertions).
   - Laravel Pint (`vendor/bin/pint --dirty --format agent`) lulus tanpa pelanggaran style.

---

## 2. Keputusan Penting yang Diambil

1. **Eager Loading Relasi `lembaga` di `SiswaController::index()`**
   - Menambahkan `'lembaga'` ke array eager loading `with(['lembaga', ...])` adalah langkah mutlak untuk mencegah timbulnya N+1 database queries saat me-render nama lembaga pada setiap baris siswa di tabel.
2. **Dual Breakpoint Pagination untuk Seluruh Table TailAdmin**
   - Perbaikan pada `resources/views/pagination/tailadmin.blade.php` tidak hanya membuat halaman Siswa responsif, namun juga memberikan dampak positif pada seluruh modul admin dan portal lain yang memanfaatkan template pagination tersebut.
3. **Penyajian Nama & NIS yang Ergonomis**
   - Nama siswa menggunakan `font-semibold text-gray-900` dan NIS menggunakan `font-mono text-xs text-gray-500` di baris berikutnya. Hal ini menghemat ruang horizontal secara signifikan pada perangkat berlayar sedang/kecil tanpa mengurangi keterbacaan data.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Hasil Test Suite Siswa**:
   ```
   php artisan test --filter="Siswa"
   Tests: 58 passed (188 assertions)
   ```
2. **Status Git**:
   - Branch: `rbac-v2`
   - Working tree bersih dan siap diuji secara visual di browser.
   - Tidak di-merge ke `main` (keputusan merge di tangan user).
