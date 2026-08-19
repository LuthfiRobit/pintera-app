# Spec: RBAC v2 — Konsistensi Penamaan Permission & Perbaikan Tool Sync

- **Document ID / Slug:** `2026-08-20-0900-rbac-v2-permission-consistency`
- **Branch:** `rbac-v2` (terpisah dari `akademik-v2`, tidak menyentuh kerja modul Akademik)
- **Tanggal:** 20 Agustus 2026

---

## 1. Latar Belakang & Masalah

Tool `php artisan permissions:sync` (`app/Console/Commands/SyncPermissions.php`) dimaksudkan untuk memindai `$this->authorize()` di seluruh controller dan menyinkronkannya ke tabel `permissions`, sebagai jaring pengaman supaya nama permission yang dipakai kode selalu terdaftar di database. **Tool ini terbukti rusak** — dikonfirmasi lewat eksekusi regex-nya secara langsung (`php -r`):

```php
$pattern = '/\$this->authorize\(\s*[\'"]([a-z0-9\-]+\.[a-z0-9\-]+)[\'"]\s*\)/';
preg_match($pattern, "\$this->authorize('pengadaan.lpj.submit');", $m);
// hasil: 0 (TIDAK match)
```

Regex-nya cuma menerima **tepat 2 segmen** (1 titik). Permission bersegmen-3 seperti `pengadaan.lpj.submit`, `pengadaan.disbursement.manage`, `pengadaan.lpj.verify` — yang semuanya nyata dipakai dan benar di-assign ke role — sama sekali tidak terdeteksi. Tool ini juga:
- Hanya scan `app/Http/Controllers` — tidak scan `app/Http/Requests`, padahal banyak `FormRequest::authorize()` memeriksa permission langsung (mis. `SubmitPengajuanRaporRequest::authorize()` → `return $this->user()->can('rapor.ajukan');`).
- Hanya menangkap pola `$this->authorize('single.string')` — tidak menangkap `canAny(['a.b', 'c.d'])` yang dipakai beberapa controller (mis. `Lembaga\Rapor\PersetujuanController`, `ApprovalPengadaanController::authorizeApprover()`).

Akibatnya, hasil "stale permission" yang dilaporkan tool ini saat ini **tidak bisa dipercaya** — permission yang sebenarnya aktif dipakai (lewat 3 segmen atau `canAny`/FormRequest) akan salah dilaporkan sebagai tidak terpakai.

Terpisah dari itu, `app/Services/PermissionCatalog.php` (pengelompok tampilan halaman Role & Permission Builder, berdasarkan segmen pertama nama permission) punya `MODULE_LABELS` yang sudah ketinggalan — modul-modul dari pekerjaan sebelumnya (`rapor`, `komponen-penilaian`, `kasus`, `pengadaan`, `sarpras`, dll) belum ada labelnya, jatuh ke fallback `ucfirst()`.

---

## 2. Tujuan

1. Perbaiki `SyncPermissions` supaya benar-benar akurat memindai SEMUA cara permission dicek di kode (controller `authorize()`/`canAny()`, FormRequest `authorize()`).
2. Jalankan audit konsistensi 3 arah: **controller/FormRequest ↔ blade ↔ `PermissionSeeder.php`** — temukan permission yang dipakai tapi tidak terdaftar (selalu gagal akses), terdaftar tapi tidak pernah dipakai (mati), dan pasangan nama yang terlihat seperti typo satu sama lain.
3. **Laporkan semua temuan ke user dulu** — TIDAK ada rename/fix otomatis tanpa persetujuan per-item, karena permission yang sudah di-assign ke role bisa berdampak ke data yang sudah berjalan.
4. Perbarui `PermissionCatalog::MODULE_LABELS` supaya tampilan Role & Permission Builder rapi mencakup semua modul yang benar-benar ada.
5. Tambahkan test regresi permanen — bukan cuma audit sekali jalan — supaya penamaan yang tidak konsisten di masa depan otomatis ketahuan gagal test, bukan luput lagi.

**Di luar scope** (dikonfirmasi lewat brainstorming): UI multi-role assignment (form user pilih >1 role), rename massal permission jadi prefix scope (orang_tua/siswa/guru/lembaga/yayasan), sinkronisasi berbasis nama route.

---

## 3. Konvensi Penamaan Permission (ditetapkan, berlaku ke depan)

Format: `modul.turunan.aksi` — urutan tetap (modul dulu, sub-fitur/turunan kalau modul itu punya banyak area otorisasi berbeda, aksi paling akhir). **Jumlah segmen boleh bervariasi** (2 segmen `rapor.verify` maupun 3 segmen `pengadaan.lpj.submit` sama-sama valid) — TIDAK dipaksa seragam, karena memaksa 2 segmen untuk modul yang punya banyak sub-fitur (seperti Pengadaan: proposal/lpj/disbursement/audit-lpj) akan membuat nama permission bertabrakan makna (`pengadaan.submit` jadi ambigu antara submit proposal vs submit LPJ).

---

## 4. Rencana Kerja

### 4.1. Perbaikan `SyncPermissions::scanControllers()`

- Ubah regex penangkap nama permission dari `[a-z0-9\-]+\.[a-z0-9\-]+` (tepat 1 titik) menjadi `[a-z0-9\-]+(?:\.[a-z0-9\-]+)+` (1 titik atau lebih) — supaya menerima berapa pun jumlah segmen.
- Tambah pola kedua untuk menangkap `canAny([...])`: regex mencari `canAny\(\s*\[(.*?)\]\s*\)` lalu ekstrak tiap string permission di dalam array-nya.
- Perluas target scan dari hanya `app/Http/Controllers` menjadi juga `app/Http/Requests` — di direktori itu, permission dicek lewat `$this->user()->can('x.y')` atau `$this->user()?->canAny([...])` di dalam method `authorize()` — regex yang sama (setelah diperluas untuk `can()`/`canAny()`) dipakai di sini juga.
- Method `scanControllers()` di-rename jadi `scanPermissionUsage()` (nama lama tidak lagi akurat karena sekarang scan lebih dari sekadar controllers) — method publik `handle()` yang memanggilnya disesuaikan.

### 4.2. Audit Konsistensi 3 Arah

Bangun (sementara, bisa berupa script/command satu-kali atau bagian dari proses investigasi, TIDAK harus jadi command permanen) yang mengumpulkan 4 sumber data:
1. Semua string permission dari `SyncPermissions::scanPermissionUsage()` yang sudah diperbaiki (controller + FormRequest).
2. Semua string permission dari `@can('...')`/`@canany([...])` di `resources/views/**/*.blade.php` (pola regex serupa).
3. Semua nama permission terdaftar di `database/seeders/PermissionSeeder.php`.
4. (Referensi silang) permission apa saja yang di-assign ke role apa di `database/seeders/RoleSeeder.php`.

Hasil silang-cek disusun jadi laporan dengan 3 kategori:
- **Rusak** — dipakai di controller/FormRequest/blade TAPI tidak ada di `PermissionSeeder.php` → dijamin selalu gagal otorisasi untuk siapa pun.
- **Mati** — terdaftar di seeder TAPI tidak pernah muncul di controller/FormRequest/blade manapun.
- **Mencurigakan mirip** — pasangan nama dengan jarak string dekat (typo-like) yang sebagian dipakai kode dan sebagian lain terdaftar seeder, mengindikasikan salah satunya adalah typo dari yang lain.

Laporan ini disajikan ke user (bentuk teks terstruktur per kategori, dikelompokkan per modul) — **tidak ada perbaikan otomatis**. User memutuskan item mana yang aman diperbaiki langsung dan mana yang perlu penanganan khusus.

### 4.3. Perbarui `PermissionCatalog::MODULE_LABELS`

Setelah daftar modul lengkap diketahui dari hasil audit 4.2 (seluruh segmen-pertama unik di `PermissionSeeder.php`), tambahkan label Indonesia yang rapi untuk modul yang belum ada di `MODULE_LABELS`. Contoh mapping yang sudah bisa dipastikan dari sesi sebelumnya (daftar final akan disesuaikan hasil audit sebenarnya):

```php
'rapor' => 'Rapor',
'komponen-penilaian' => 'Komponen Penilaian (TP)',
'asesmen' => 'Asesmen',
'presensi' => 'Presensi & Jurnal KBM',
'kasus' => 'Manajemen Kasus Siswa',
'pengadaan' => 'Pengadaan Sarpras',
'sarpras' => 'Sarana & Prasarana',
'rpp' => 'Perangkat Ajar (RPP)',
'jadwal-pelajaran' => 'Jadwal Pelajaran',
'kelas' => 'Kelas',
'mata-pelajaran' => 'Mata Pelajaran',
'kenaikan-kelas' => 'Kenaikan Kelas',
'karyawan' => 'Karyawan',
'jenis-karyawan-master' => 'Jenis Karyawan',
'pola-jam' => 'Pola Jam',
'jam-pelajaran' => 'Jam Pelajaran',
'kalender-akademik' => 'Kalender Akademik',
'pengaturan-akademik' => 'Pengaturan Akademik',
'tahun-ajaran' => 'Tahun Ajaran',
```

### 4.4. Test Regresi Permanen

Tambahkan test baru (mis. `tests/Unit/PermissionConsistencyTest.php`) yang menjalankan logic audit 4.2's kategori "Rusak" (dipakai di kode tapi tidak ada di seeder) sebagai assertion — GAGAL kalau ditemukan satu saja permission yang dipakai controller/FormRequest/blade tapi tidak terdaftar di `PermissionSeeder.php`. Test ini jadi jaring pengaman permanen: siapa pun (manusia atau agent) yang menambah `$this->authorize('modul.baru.aksi')` di masa depan tapi lupa mendaftarkannya di seeder akan langsung ketahuan lewat kegagalan test, bukan baru ketahuan saat user melapor bug akses.

Kategori "Mati" (terdaftar tapi tidak dipakai) TIDAK dijadikan assertion gagal otomatis — permission yang belum dipakai kode bisa jadi sengaja disiapkan lebih dulu untuk fitur yang belum jalan, jadi ini tetap laporan manual saja, bukan hard-fail.

### 4.5. Test untuk `SyncPermissions` sendiri

`SyncPermissions` saat ini tidak punya test sama sekali. Tambahkan `tests/Feature/Console/SyncPermissionsTest.php` yang membuktikan regex baru menangkap permission 2 segmen, 3 segmen, dari `canAny([...])`, dan dari file di `app/Http/Requests` — supaya kerusakan yang baru saja ditemukan (dan diperbaiki) tidak terulang tanpa terdeteksi.

---

## 5. Testing

- `tests/Feature/Console/SyncPermissionsTest.php` (baru) — lihat §4.5.
- `tests/Unit/PermissionConsistencyTest.php` (baru) — lihat §4.4.
- Test existing untuk `PermissionCatalog` (kalau ada) atau buat baru — buktikan `MODULE_LABELS` yang baru ditambahkan benar-benar dipakai (bukan fallback `ucfirst()`) untuk modul-modul di §4.3.
- Regression: `RoleBuilderTest.php` dan test lain yang menyentuh `permissions:sync`/`PermissionCatalog` harus tetap hijau tanpa perubahan assertion.

---

## 6. Asumsi & Keputusan Desain

1. Audit 4.2 dilakukan sekali sebagai bagian dari implementasi (menghasilkan laporan ke user), TAPI mesin pemeriksaannya (regex scan) menjadi permanen lewat test 4.4 — bukan command satu-kali yang dibuang setelah dipakai.
2. Tidak ada perubahan pada `RoleSeeder.php`/penamaan permission mana pun sampai user menyetujui tiap temuan spesifik dari laporan audit — spec ini hanya mencakup kerja MEMBANGUN kemampuan audit + menyajikan laporan, BUKAN kerja memperbaiki tiap temuan (itu langkah lanjutan setelah user review).
3. `MODULE_LABELS` di §4.3 adalah perkiraan awal berdasarkan modul yang sudah diketahui dari sesi-sesi sebelumnya — daftar final ditentukan saat implementasi benar-benar membaca isi `PermissionSeeder.php` saat itu (bisa saja ada modul lain yang belum tercatat di spec ini).
