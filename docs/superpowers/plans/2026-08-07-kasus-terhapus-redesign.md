# Kasus Terhapus UI Redesign Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Merombak UI `terhapus.blade.php` menjadi SPA-Lite (Opsi A) yang setara dengan *Premium Museum Quality UX*, termasuk pencarian, statistik, dan penggunaan `<x-table-actions>` dengan modal konfirmasi.

## Global Constraints
- Backend Server-side pagination dipertahankan.
- Kolom Aksi berada di sebelah kiri (khas *sticky column* Pintera).
- Aksi "Pulihkan" harus menggunakan `confirmDialog`.

---

### Task 1: Modifikasi Backend (Controller)

**Files:**
- Modify: `app/Http/Controllers/Admin/KasusTerhapusController.php`

**Interfaces:**
- Menangkap `request('search')` dan `request('per_page')`.
- Menghitung `$totalTerhapus` dan `$dihapusBulanIni`.

- `[ ]` **Step 1: Perbarui KasusTerhapusController**
  Tangkap *search* dan *per_page*. Hitung statistik dari query dasar.
- `[ ]` **Step 2: Logika Pencarian**
  Jika ada pencarian, lakukan pencarian pada relasi `siswa->nama_lengkap` atau `kategori_masalah`.
- `[ ]` **Step 3: Teruskan ke View**
  Pastikan paginasi menggunakan `$perPage` dan menggunakan `withQueryString()`.
- `[ ]` **Step 4: Commit**
  `git commit -m "feat(kasus): tambah fungsi pencarian dan statistik kasus terhapus"`

---

### Task 2: UI Overhaul (Stats & Search Form)

**Files:**
- Modify: `resources/views/admin/kasus/terhapus.blade.php`

**Interfaces:**
- Menampilkan 2 *Statistic Cards*.
- Form pencarian `<form method="GET">` dengan Alpine state `search` dan `perPage`.

- `[ ]` **Step 1: Tambahkan Statistic Cards**
  Di atas form, buat kartu "Total Kasus Terhapus" dan "Baru Dihapus (Bulan Ini)".
- `[ ]` **Step 2: Tambahkan Form Pencarian**
  Buat form pencarian bergaya SPA, letakkan *hidden input* `per_page` di dalamnya, dan beri `x-ref="filterForm"`.
- `[ ]` **Step 3: Commit**
  `git commit -m "feat(kasus): UI statistik dan form pencarian kasus terhapus"`

---

### Task 3: Table Design & Table Actions

**Files:**
- Modify: `resources/views/admin/kasus/terhapus.blade.php`

**Interfaces:**
- Tabel yang menggunakan `<x-table-actions>`.
- *Dropdown* Tampilkan `per_page` di *header* tabel.

- `[ ]` **Step 1: Header Tabel & Per Page Dropdown**
  Sisipkan `select` `perPage` yang memicu submit form.
- `[ ]` **Step 2: Perombakan Kolom Tabel**
  Pindahkan `<th>Aksi</th>` ke paling kiri dengan kelas `sticky left-0 z-10`.
- `[ ]` **Step 3: Implementasi x-table-actions**
  Bungkus tombol "Pulihkan" di dalam `<x-table-actions>`, ubah menjadi form berwujud *link-button*, dan pasang `@submit.prevent="confirmDialog('Pulihkan Kasus?', 'Kasus ini akan dikembalikan ke daftar aktif beserta seluruh riwayat sesinya.', { confirmLabel: 'Ya, Pulihkan' }).then(confirmed => { if (confirmed) $el.submit() })"`.
- `[ ]` **Step 4: Format Waktu & Pengujian**
  Gunakan `diffForHumans` untuk kolom tanggal. Jalankan `php artisan test --filter Kasus`.
- `[ ]` **Step 5: Commit**
  `git commit -m "feat(kasus): perombakan visual tabel kasus terhapus dengan aksi dropdown"`
