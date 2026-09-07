# Spec: Perbaikan Tampilan Halaman Index Siswa (Header Scope, Kolom Tabel & Pagination)

- **Tanggal**: 7 September 2026
- **Status**: Draft -> Ready for Implementation
- **Branch**: `rbac-v2`
- **Scope File**:
  - `app/Http/Controllers/Admin/SiswaController.php`
  - `resources/views/admin/siswa/index.blade.php`
  - `resources/views/admin/siswa/_daftar.blade.php`
  - `resources/views/pagination/tailadmin.blade.php`
  - `tests/Feature/Admin/SiswaCrudTest.php`

---

## 1. Latar Belakang & Masalah

Terdapat permintaan perbaikan antarmuka (UI/UX) pada halaman utama Data Induk Siswa (`admin.siswa.index`):
1. **Header Scope Yayasan**: Saat aktor yayasan berada di mode "Semua Lembaga" (tidak memilih lembaga aktif di topbar switcher), header halaman hanya bertuliskan "Siswa" statis. Informasi scope tidak eksplisit. Pengguna menginginkan teks yang jelas:
   - Jika scope yayasan tidak pilih lembaga -> `SISWA SEMUA LEMBAGA`.
   - Jika scope yayasan pilih lembaga -> `SISWA [NAMA LEMBAGA]` (contoh: `SISWA SMA IT PINTERA`).
   - Jika aktor adalah lembaga scope (bukan yayasan) -> tetap default `Siswa`.
2. **Penggabungan Kolom NIS dan Nama**: Tabel siswa saat ini memisahkan kolom NIS dan Nama secara terpisah yang memakan ruang horizontal tabel secara berlebihan di layar. Kolom NIS dan Nama perlu disatukan dengan hierarki visual modern: Nama Siswa di atas (tebal), NIS di bawah (font monospace abu-abu kecil).
3. **Penambahan Kolom Lembaga**: Di tabel daftar siswa, terutama saat yayasan melihat seluruh lembaga atau dalam administrasi lintas entitas, tidak ada informasi lembaga pemilik siswa. Kolom "Lembaga" perlu ditambahkan, dan relasi `lembaga` harus di-eager load di `SiswaController::index()` untuk mencegah N+1 query.
4. **Pagination Kurang Responsif**: Template pagination `resources/views/pagination/tailadmin.blade.php` saat ini hanya mengandalkan satu baris flex horizontal dengan daftar nomor halaman penuh (`1 2 3 4 5 6 7 8...`). Di layar mobile/tablet kecil, tombol halaman bertumpuk (wrapping) atau meluap secara tidak rapi dan sulit ditekan. Dibutuhkan desain pagination responsif dengan tampilan mobile (`sm:hidden`) ringkas (tombol "Sebelumnya" dan "Berikutnya" + indikator halaman aktif) serta tampilan desktop (`hidden sm:flex`) yang rapi.

---

## 2. Rincian Kebutuhan & Desain Solusi

### 2.1 Header Informasi Scope
- Di `SiswaController::index()`, sediakan data scope:
  - `$isYayasan = $user->widestScopeLevel() === 'yayasan';`
  - `$activeLembagaId = $isYayasan ? session('active_lembaga_id') : $user->lembaga_id;`
  - `$activeLembaga = ($isYayasan && $activeLembagaId) ? Lembaga::withoutGlobalScopes()->find($activeLembagaId) : null;`
- Di `resources/views/admin/siswa/index.blade.php`:
  - `<h1>` menampilkan teks sesuai kondisi:
    ```blade
    @if ($isYayasan)
        {{ $activeLembaga ? 'SISWA ' . strtoupper($activeLembaga->nama) : 'SISWA SEMUA LEMBAGA' }}
    @else
        Siswa
    @endif
    ```
  - Ditambahkan badge penanda lembaga di samping header untuk visual yang konsisten dengan desain Pintera.

### 2.2 Penggabungan Kolom NIS & Nama
- Pada `resources/views/admin/siswa/_daftar.blade.php`:
  - `<thead>`: Kolom terpisah `<th>NIS</th>` dan `<th>Nama</th>` digantikan oleh satu kolom `<th>Siswa</th>`.
  - `<tbody>`: Digabung menjadi:
    ```blade
    <td class="px-5 py-3.5">
        <div class="font-semibold text-gray-900">{{ $siswa->nama_lengkap }}</div>
        <div class="font-mono text-xs text-gray-500">{{ $siswa->nis }}</div>
    </td>
    ```

### 2.3 Penambahan Kolom Lembaga
- Pada `app/Http/Controllers/Admin/SiswaController.php`:
  - Eager load `'lembaga'` pada query utama:
    `Siswa::with(['lembaga', 'kelas', 'kelasTerakhir', 'person'])`
- Pada `resources/views/admin/siswa/_daftar.blade.php`:
  - Ditambahkan kolom `<th>Lembaga</th>` setelah kolom Siswa.
  - Baris `<tbody>`:
    ```blade
    <td class="px-5 py-3.5 text-gray-600">
        <div class="font-medium text-gray-800">{{ $siswa->lembaga?->nama ?? '—' }}</div>
    </td>
    ```
  - Total kolom tetap 6 (Aksi, Siswa, Lembaga, Kelas, Asal Data, Status), sehingga `colspan` untuk baris kosong tetap `6`.

### 2.4 Pagination Responsif
- Pada `resources/views/pagination/tailadmin.blade.php`:
  - Sediakan dua varian tampilan dalam satu `<nav>`:
    1. **Mobile (< sm)**: Tampilan tombol navigasi fleksibel dengan tombol "Sebelumnya" dan "Berikutnya" yang ramah sentuhan, dipadukan dengan teks ringkasan "Hal X / Y" dan "Menampilkan X-Y dari Z entri".
    2. **Desktop (>= sm)**: Tampilan penuh dengan ringkasan entri di sisi kiri dan deretan nomor halaman di sisi kanan.

---

## 3. Acceptance Criteria

1. [ ] Aktor yayasan tanpa memilih lembaga di topbar melihat teks header `SISWA SEMUA LEMBAGA`.
2. [ ] Aktor yayasan yang memilih lembaga (mis. "SMA IT Pintera") melihat teks header `SISWA SMA IT PINTERA`.
3. [ ] Aktor lembaga melihat teks header `Siswa`.
4. [ ] Pada tabel daftar siswa, kolom NIS dan Nama telah digabung menjadi satu kolom dengan nama siswa di atas dan NIS di bawah.
5. [ ] Pada tabel daftar siswa, terdapat kolom Lembaga yang menampilkan nama lembaga siswa tanpa menimbulkan N+1 query.
6. [ ] Pagination `pagination.tailadmin` beradaptasi secara mulus di layar mobile (< 640px) dan desktop.
7. [ ] Seluruh test di `SiswaCrudTest` dan test baru untuk fitur ini lulus tanpa regresi.
8. [ ] Laravel Pint lulus tanpa pelanggaran style.
