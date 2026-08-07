# Log Akses Klinis UI Redesign Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Merombak UI `akses-log.blade.php` agar setara dengan standar *Premium Museum Quality UX* dari `kasus/index.blade.php`, mencakup penambahan Statistik, Pencarian, dan Tabel Visual yang lebih kaya tanpa menggunakan avatar.

## Global Constraints
- Paginasi harus tetap menggunakan *Server-side pagination* dari Laravel karena data log bisa sangat besar, namun *query string* (`?search=`) harus dipertahankan.
- Visualisasi tabel tidak menggunakan avatar (permintaan pengguna).
- Backend harus diperbarui untuk menangani pencarian (berdasarkan nama pengakses atau nama siswa).

---

### Task 1: Modifikasi Backend (Controller)

**Files:**
- Modify: `app/Http/Controllers/Admin/KasusAksesLogController.php`

**Interfaces:**
- Menangkap `request('search')`.
- Menghitung statistik sederhana: `totalAkses` (sepanjang waktu untuk lembaga terkait) dan `aksesHariIni` (log hari ini).

- `[ ]` **Step 1: Perbarui KasusAksesLogController**
  Modifikasi metode `index` untuk menghitung `$totalAkses` dan `$aksesHariIni`.
- `[ ]` **Step 2: Tambahkan Logika Pencarian**
  Jika ada `request('search')`, terapkan filter pencarian dengan menggunakan `whereHasMorph` pada `subject` (mencari nama siswa) ATAU filter nama *causer* (menggunakan `whereIn` pada causer ID yang sesuai pencarian). Pastikan *query* tidak bocor ke luar *TenantScope* kecuali diperlukan (sama seperti *query* sebelumnya).
- `[ ]` **Step 3: Teruskan Data ke View**
  Gunakan `->paginate(20)->withQueryString()` pada `$logs`. Lewatkan `$totalAkses`, `$aksesHariIni`, dan `request('search')` ke dalam `view()`.
- `[ ]` **Step 4: Commit**
  ```bash
  git add app/Http/Controllers/Admin/KasusAksesLogController.php
  git commit -m "feat(kasus): tambahkan fungsi pencarian dan statistik pada controller akses log"
  ```

---

### Task 2: UI Overhaul (Stats & Search Form)

**Files:**
- Modify: `resources/views/admin/kasus/akses-log.blade.php`

**Interfaces:**
- Menampilkan 2 *Compact Statistic Cards* menggunakan `$totalAkses` dan `$aksesHariIni`.
- Form pencarian statis/semi-reaktif menggunakan form method GET.

- `[ ]` **Step 1: Tambahkan Statistic Cards**
  Di atas form, buat 2 kartu: "Total Akses Riwayat" (ikon riwayat) dan "Akses Hari Ini" (ikon kilat/hari ini).
- `[ ]` **Step 2: Tambahkan Form Filter/Pencarian**
  Gunakan elemen kotak pencarian bergaya SPA (dengan ikon kaca pembesar). Bungkus dengan `<form method="GET">` agar di-*submit* saat pengguna menekan Enter.
- `[ ]` **Step 3: Commit**
  ```bash
  git add resources/views/admin/kasus/akses-log.blade.php
  git commit -m "feat(kasus): tambahkan komponen statistik dan form pencarian log akses"
  ```

---

### Task 3: Table Design Upgrade

**Files:**
- Modify: `resources/views/admin/kasus/akses-log.blade.php`

**Interfaces:**
- Tabel yang lebih estetis dengan penekanan pada tipografi.

- `[ ]` **Step 1: Tingkatkan Tampilan Tabel**
  Beri penekanan gaya teks: nama pengakses dibuat lebih tebal (`font-bold text-gray-900`), nama siswa diberi gaya *badge* ringan atau warna netral (`font-medium text-gray-700`).
- `[ ]` **Step 2: Format Waktu (Timestamp)**
  Ubah rendering format tanggal dari format statis `d M Y H:i` menjadi `<p class="font-bold text-gray-900">{{ $log->created_at->diffForHumans() }}</p><p class="text-gray-500 text-[11px]">{{ $log->created_at->format('d M Y, H:i') }}</p>` (Tergantung locale, ini akan menampilkan "2 jam yang lalu").
- `[ ]` **Step 3: Jalankan Tes Backend**
  Verifikasi tidak ada tes kasus yang pecah akibat modifikasi *controller*.
  Run: `php artisan test --filter Kasus`
  Expected: PASS
- `[ ]` **Step 4: Commit**
  ```bash
  git add resources/views/admin/kasus/akses-log.blade.php
  git commit -m "feat(kasus): percantik tabel log akses dan format timestamp"
  ```
