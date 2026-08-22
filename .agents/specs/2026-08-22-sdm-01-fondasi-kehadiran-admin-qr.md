# Kehadiran SDM — Sub-project 1: Fondasi + Admin Manual + QR Statis — Spec

## 1. Latar Belakang

Branch `sdm-v1` dibuat untuk modul baru **Kehadiran SDM** (absensi pegawai: guru + karyawan), berdasarkan PRD yang ditulis pemilik produk (`prd_modul_kehadiran_sdm.md`). PRD tersebut mendefinisikan konsep domain lengkap (Attendance Method, Method Configuration, Point, Event, Record, Policy) dan merekomendasikan MVP 7 poin. Setelah sesi brainstorming, cakupan MVP itu dipecah jadi 4 sub-project bertahap (per keputusan user, "Pecah jadi sub-project bertahap"):

1. **Sub-project 1 (spec ini): Fondasi + Admin Manual + QR Statis** — model data inti, konfigurasi metode per lembaga, catat kehadiran manual oleh admin, catat kehadiran via scan QR statis pegawai.
2. Sub-project 2: Kalender Kerja SDM (independen dari kalender akademik).
3. Sub-project 3: Attendance Policy per jenis_ptk/jenis_karyawan_id (jam kerja, toleransi, deteksi terlambat).
4. Sub-project 4: Izin/Cuti berjenjang lewat reuse domain `App\Domains\Workflow`.

Sub-project 1 SENGAJA tidak mencakup deteksi keterlambatan (butuh Policy dari Sub-project 3) atau validasi hari libur (butuh Kalender dari Sub-project 2) — admin mencatat status apa adanya untuk sekarang, lihat §7.

**Prinsip pemisahan wajib**: kehadiran KERJA pegawai (modul ini) BERBEDA dari kehadiran/pelaksanaan sesi pembelajaran murid (`SesiPembelajaran`/`Presensi` murid di `App\Domains\Akademik`, sudah ada). Modul ini tidak menyentuh, tidak bergantung pada, dan tidak boleh tertukar dengan itu.

## 2. Keputusan Desain (hasil brainstorming, ringkas)

| Topik | Keputusan |
|---|---|
| Kewenangan yayasan vs lembaga atas konfigurasi metode absensi | Yayasan sediakan default, lembaga bebas override per lembaga |
| Kalender kerja | Terpisah/independen dari kalender akademik (Sub-project 2, di luar cakupan spec ini) |
| Role RBAC baru | `admin_sdm` — dinamis lewat Spatie Permission, TIDAK ADA hardcode nama role di kode manapun (lihat §6) |
| Model pegawai yang diabsen | Polymorphic — bisa `App\Models\Guru` ATAU `App\Models\Karyawan` (dua tabel terpisah, TIDAK digabung — blast radius Guru 99 file vs Karyawan 15 file terlalu besar untuk digabung sekarang) |
| Metode MVP | Admin manual (selalu ada, fallback) + QR statis (Employee QR, discan petugas via kiosk) |
| Model QR | Statis per pegawai, token acak unik di-generate sistem (BUKAN NIK/ID mentah) |
| Siapa yang scan | Petugas login akun admin biasa (bukan akun kiosk terpisah), buka halaman scan |
| Akses pegawai ke QR sendiri | Guru & Karyawan yang punya akun login bisa lihat QR miliknya sendiri (self-service view, bukan self-service check-in) |
| Attendance Point | Disertakan dari MVP, opsional/nullable |
| Tenant scope | Reuse mekanisme `BelongsToTenant` + `TenantScope` yang sudah ada (lihat §5.1) — yayasan bisa akses lembaga manapun di bawahnya SETELAH switch-lembaga, persis seperti modul Akademik lain |

## 3. Struktur Domain

Domain baru `App\Domains\Sdm\`, mengikuti pola domain existing (`App\Domains\Kasus`, `App\Domains\Akademik`):

```
app/Domains/Sdm/
├── Actions/
│   ├── RecordManualAttendanceAction.php
│   ├── ScanQrAttendanceAction.php
│   ├── SetAttendanceMethodConfigurationAction.php
│   └── GenerateEmployeeQrTokenAction.php
├── DataTransferObjects/
│   ├── RecordManualAttendanceData.php
│   └── ScanQrAttendanceData.php
├── Enums/
│   ├── AttendanceMethod.php
│   └── AttendanceStatus.php
├── Models/
│   ├── AttendanceMethodConfiguration.php
│   ├── AttendancePoint.php
│   ├── AttendanceEvent.php
│   ├── AttendanceRecord.php
│   └── EmployeeQrCode.php
└── Services/
    └── AttendanceRecordAggregator.php
```

Controller TETAP di `app/Http/Controllers/Admin/` (konvensi existing — Controller bukan bagian domain, cukup thin, panggil Action), sesuai `.agents/skills/laravel-feature-standard/SKILL.md`:

```
app/Http/Controllers/Admin/
├── AttendanceConfigurationController.php   (kelola metode per lembaga)
├── AttendanceController.php                (input manual, daftar kehadiran)
├── AttendanceQrScanController.php          (halaman scan + endpoint scan)
└── EmployeeQrCodeController.php            (lihat QR sendiri — guru/karyawan)
```

## 4. Data Model

### 4.1 `attendance_method_configurations`

Konfigurasi metode absensi AKTIF per lembaga. Yayasan set default (baris dengan `lembaga_id` null tapi `yayasan_id` terisi — pola "pool" yang sudah ada, lihat `Karyawan.lembaga_id` nullable + TenantScope §5.1), lembaga override dengan baris `lembaga_id` terisi.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `yayasan_id` | FK `yayasan` | selalu terisi |
| `lembaga_id` | FK `lembaga`, nullable | null = baris default yayasan; terisi = override lembaga tsb |
| `method` | string | value dari enum `AttendanceMethod` (`admin`, `qr`) |
| `is_enabled` | boolean, default true | |
| `created_at`, `updated_at` | timestamp | |

Unique constraint: `(yayasan_id, lembaga_id, method)` — cegah baris duplikat.

Resolusi efektif (dipakai `AttendanceRecordAggregator`): kalau ada baris `lembaga_id = X` untuk method tsb → pakai itu; kalau tidak ada → fallback ke baris `lembaga_id = null` milik yayasan yang sama. `admin` method dianggap SELALU aktif secara implisit (fallback tak bisa dimatikan) — baris konfigurasinya boleh ada untuk keperluan UI toggle "tampilkan sebagai opsi utama", tapi backend tidak pernah menolak input manual admin walau `is_enabled=false`.

### 4.2 `attendance_points`

Titik/lokasi fisik opsional tempat absensi dilakukan (mis. "Gerbang Utama", "Ruang TU"). Opsional — event boleh tidak terkait point manapun.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `lembaga_id` | FK `lembaga` | wajib terisi (point selalu milik 1 lembaga spesifik, tidak ada konsep pool untuk ini) |
| `nama` | string | |
| `is_active` | boolean, default true | |
| `created_at`, `updated_at` | timestamp | |

### 4.3 `attendance_events` (immutable, audit trail)

Satu baris = satu kejadian absen tunggal (1 scan, 1 input manual). TIDAK PERNAH di-update/delete setelah dibuat — koreksi dilakukan dengan menambah event baru, bukan mengubah yang lama (audit trail requirement dari PRD).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `lembaga_id` | FK `lembaga` | |
| `pegawai_type` | string | morph type — `Guru` atau `Karyawan` |
| `pegawai_id` | bigint | morph id |
| `attendance_point_id` | FK `attendance_points`, nullable | |
| `method` | string | value `AttendanceMethod` (`admin`, `qr`) |
| `arah` | string enum | `masuk` atau `pulang` |
| `status` | string | value `AttendanceStatus` (`hadir`, `izin`, `sakit`, `alpa`) — lihat §4.5 catatan status |
| `waktu` | datetime | waktu kejadian aktual (bisa beda dari `created_at` kalau admin input mundur) |
| `dicatat_oleh_user_id` | FK `users`, nullable | siapa yang menginput (null untuk QR self-scan kalau nanti ada; untuk MVP selalu terisi karena admin/petugas yang aksi) |
| `catatan` | text, nullable | |
| `created_at` | timestamp | TIDAK ADA `updated_at` — model immutable |

Index: `(pegawai_type, pegawai_id, waktu)`, `(lembaga_id, waktu)`.

### 4.4 `attendance_records` (agregat harian, mutable)

Satu baris = satu ringkasan kehadiran pegawai per hari. Dibentuk/diperbarui dari 1 atau lebih `attendance_events` pada tanggal yang sama (PRD eksplisit: 1 record bisa berasal dari beberapa event beda metode, mis. check-in QR + check-out admin manual).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `lembaga_id` | FK `lembaga` | |
| `pegawai_type` | string | morph type |
| `pegawai_id` | bigint | morph id |
| `tanggal` | date | |
| `status` | string | value `AttendanceStatus`, hasil resolusi dari event-event hari itu (lihat §7 aturan resolusi MVP) |
| `waktu_masuk` | datetime, nullable | dari event `arah=masuk` paling awal hari itu |
| `waktu_pulang` | datetime, nullable | dari event `arah=pulang` paling akhir hari itu |
| `created_at`, `updated_at` | timestamp | |

Unique constraint: `(pegawai_type, pegawai_id, tanggal)`.

### 4.5 Catatan Status (mengikuti PRD)

Enum `AttendanceStatus` untuk Sub-project 1 HANYA berisi 4 nilai: `hadir`, `izin`, `sakit`, `alpa`. **Terlambat SENGAJA TIDAK jadi status terpisah** — PRD eksplisit menyebut ini sebaiknya jadi atribut (`is_late`, `late_minutes`) demi fleksibilitas payroll masa depan. Kolom itu BELUM ditambahkan di Sub-project 1 (butuh Attendance Policy dari Sub-project 3 untuk tahu threshold jam kerja) — akan ditambahkan sebagai kolom baru di `attendance_records` pada spec Sub-project 3, bukan sekarang. Jangan buat status `terlambat` di enum manapun pada sub-project ini.

### 4.6 `pegawai_qr_codes` (nama tabel: `employee_qr_codes`)

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `pegawai_type` | string | morph type |
| `pegawai_id` | bigint | morph id |
| `token` | string(64), unique | token acak (`Str::random(48)` atau setara), digenerate sistem — TIDAK PERNAH memuat NIK/ID/data pribadi mentah |
| `is_active` | boolean, default true | untuk revoke/regenerate tanpa hapus histori |
| `created_at`, `updated_at` | timestamp | |

Unique constraint: `(pegawai_type, pegawai_id)` WHERE `is_active = true` — via partial unique index kalau driver DB mendukung, atau divalidasi di level Action (`GenerateEmployeeQrTokenAction` menonaktifkan token lama sebelum membuat baru) kalau tidak. Token TIDAK exp — hanya bisa di-revoke manual oleh admin (regenerate).

## 5. Tenant Isolation (yayasan + switch-lembaga)

### 5.1 Mekanisme yang di-reuse (SUDAH ADA, wajib dipakai, bukan dibuat baru)

Semua model tenant-scoped baru (`AttendanceMethodConfiguration`, `AttendancePoint`, `AttendanceEvent`, `AttendanceRecord`) WAJIB pakai trait `App\Models\Concerns\BelongsToTenant`, yang otomatis mendaftarkan `App\Models\Scopes\TenantScope` sebagai global scope. Perilaku scope ini (dibaca dari `app/Models/Scopes/TenantScope.php`, JANGAN diduplikasi/ditulis ulang):

- Aktor dengan `widestScopeLevel() === 'lembaga'` (mis. role `admin_sdm` di-set `scope_level: lembaga`, lihat §6) → query otomatis difilter `lembaga_id = $actingUser->lembaga_id`. Tidak perlu kode manual apapun di controller untuk filtering baca.
- Aktor dengan `widestScopeLevel() === 'yayasan'` (mis. `yayasan_super_admin`) → query otomatis difilter berdasar `session('active_lembaga_id')` kalau sudah pilih lembaga (via pengalih lembaga yang sudah ada di UI), atau ke seluruh lembaga milik yayasannya kalau belum pilih ("Semua Lembaga"). **Ini menjawab langsung pertanyaan user**: aktor yayasan TETAP bisa akses data Kehadiran SDM lembaga manapun di bawahnya, syaratnya switch-lembaga dulu — persis pola yang sudah dipakai `TahunAjaranController`, `PolaJamController`, `KelasController`, dst.

`AttendanceMethodConfiguration` PENGECUALIAN SEBAGIAN: baris default yayasan (`lembaga_id = null`) harus tetap terlihat oleh aktor yayasan tanpa perlu switch-lembaga (untuk mengelola default-nya sendiri). `EmployeeQrCode` TIDAK pakai `BelongsToTenant` langsung (tidak punya kolom `lembaga_id` sendiri) — isolasinya didapat transitif lewat relasi morph ke `Guru`/`Karyawan` yang sudah tenant-scoped duluan.

### 5.2 Pola controller untuk operasi tulis (create/update)

Sama seperti `GuruController::resolveLembagaId()` dan `TahunAjaranController::store()` — setiap Controller yang membuat data tenant-scoped baru (mis. `AttendanceController@store` untuk input manual) WAJIB resolve `lembaga_id` eksplisit:

```php
private function resolveLembagaId(Request $request): ?int
{
    if ($request->user()->widestScopeLevel() === 'yayasan') {
        return session('active_lembaga_id');
    }

    return $request->user()->lembaga_id;
}
```

Kalau `widestScopeLevel() === 'yayasan'` dan `session('active_lembaga_id')` masih null saat mencoba create → tolak dengan pesan eksplisit ("Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu"), sama seperti `TahunAjaranController::store()`.

## 6. RBAC

Role baru `admin_sdm` ditambahkan ke `database/seeders/RoleSeeder.php`:

```php
'admin_sdm' => ['scope_level' => 'lembaga', 'is_protected' => false],
```

`scope_level: lembaga` (bukan yayasan) — role ini secara default HR-admin per lembaga. Aktor yayasan (`yayasan_super_admin`) tetap bisa mengelola Kehadiran SDM lintas lembaga lewat switch-lembaga (§5), TANPA perlu `admin_sdm` sendiri berscope yayasan. Kalau nanti dibutuhkan role tambahan berscope yayasan untuk SDM (di luar cakupan sub-project ini), tinggal permission `kehadiran-sdm.*` di-assign ke role scope-yayasan yang sesuai — mekanismenya sudah generik, tidak perlu kode baru.

Permission baru (format `[domain].[action]`, konsisten `laravel-feature-standard/SKILL.md`), ditambahkan ke `database/seeders/PermissionSeeder.php`:

| Permission | Dipakai untuk |
|---|---|
| `kehadiran-sdm.view` | Lihat daftar kehadiran, konfigurasi metode, daftar attendance point |
| `kehadiran-sdm.catat` | Input manual + akses halaman scan QR |
| `kehadiran-sdm.kelola-konfigurasi` | Ubah metode aktif per lembaga, kelola attendance point, generate/revoke QR pegawai |
| `kehadiran-sdm.lihat-qr-sendiri` | Pegawai (guru/karyawan dengan akun) lihat QR miliknya sendiri |

`admin_sdm` diberi keempat permission via `RoleSeeder`. `yayasan_super_admin` otomatis dapat semua permission (baris `givePermissionTo(Permission::all())` sudah ada, tidak perlu diubah). `guru` dan role pegawai lain (`karyawan_pool`, `karyawan_lembaga`) diberi HANYA `kehadiran-sdm.lihat-qr-sendiri`.

**WAJIB**: TIDAK ADA `hasRole('admin_sdm')` atau nama role string apapun dihardcode di Controller/Action/Middleware manapun. Semua authorization check pakai `$this->authorize('kehadiran-sdm.xxx')` (Spatie Permission via permission, bukan role) — sesuai temuan audit RBAC hari ini yang menemukan & memperbaiki hardcode role phantom di `ApproverResolverService`.

## 7. Alur Utama

### 7.1 `RecordManualAttendanceAction` (input manual admin)

Input: `pegawai_type`, `pegawai_id`, `arah` (`masuk`/`pulang`), `status`, `waktu`, `catatan?`, `attendance_point_id?`, `lembaga_id` (dari `resolveLembagaId()`), `dicatat_oleh_user_id` (`auth()->id()`).

1. Validasi pegawai (`Guru`/`Karyawan`) ditemukan DAN `lembaga_id`-nya cocok dengan lembaga yang sedang aktif (cegah admin lembaga A mencatat kehadiran pegawai lembaga B — defense-in-depth di atas TenantScope, karena `pegawai_id` datang dari input user).
2. Buat `AttendanceEvent` baru (method = `admin`).
3. Panggil `AttendanceRecordAggregator::sync($pegawai, $tanggal)` untuk membentuk/update `AttendanceRecord` hari itu (lihat §7.3).

Status `izin`/`sakit`/`alpa` di Sub-project 1 di-set LANGSUNG oleh admin tanpa approval berjenjang (beda dari Sub-project 4 nanti, yang menambahkan alur pegawai mengajukan sendiri lewat Workflow domain dengan approval). Ini cakupan MVP paling sederhana: admin adalah satu-satunya yang bisa menulis status apapun di Sub-project 1.

### 7.2 `ScanQrAttendanceAction` (scan oleh petugas via kiosk)

Input: `token` (dari hasil scan QR), `arah`, `attendance_point_id?`, `lembaga_id` (dari `resolveLembagaId()` milik PETUGAS yang login, bukan pegawai yang discan), `dicatat_oleh_user_id` (`auth()->id()` petugas).

1. Cari `EmployeeQrCode` aktif berdasar `token` (query TANPA tenant scope tambahan dulu, karena token unik global — tapi setelah ketemu, WAJIB verifikasi pegawai pemilik token itu berada di lembaga yang sama dengan petugas yang scan; kalau beda lembaga → tolak dengan pesan jelas, bukan diam-diam sukses lintas tenant).
2. Kalau token tidak ditemukan/nonaktif → tolak dengan pesan jelas ("QR tidak valid atau sudah tidak aktif").
3. Buat `AttendanceEvent` (method = `qr`, status = `hadir` — QR scan selalu berarti hadir, tidak ada opsi status lain lewat jalur ini).
4. Panggil `AttendanceRecordAggregator::sync()` sama seperti §7.1.

### 7.3 `AttendanceRecordAggregator::sync(Model $pegawai, CarbonImmutable $tanggal)`

Ambil semua `AttendanceEvent` milik `$pegawai` pada `$tanggal`, urutkan by `waktu`:

- `waktu_masuk` = `waktu` dari event pertama berarah `masuk` (kalau ada).
- `waktu_pulang` = `waktu` dari event terakhir berarah `pulang` (kalau ada).
- `status` resolusi MVP (urutan prioritas sederhana, tanpa kalkulasi keterlambatan): kalau ADA event manapun berstatus `izin`/`sakit`/`alpa` pada hari itu → pakai status itu (event non-`hadir` selalu override); kalau tidak ada dan ADA minimal 1 event → `hadir`; kalau tidak ada event sama sekali untuk hari itu → record TIDAK dibuat sama sekali (bukan `alpa` otomatis — penentuan alpa-karena-tidak-hadir butuh Kalender Kerja dari Sub-project 2 untuk tahu itu hari kerja atau bukan, di luar cakupan sub-project ini).
- `AttendanceRecord::updateOrCreate(['pegawai_type' => ..., 'pegawai_id' => ..., 'tanggal' => $tanggal], [...])`.

## 8. Yang TIDAK Berubah / Di Luar Cakupan Sub-project 1

- Tidak ada deteksi/kalkulasi keterlambatan (`is_late`, `late_minutes`) — Sub-project 3.
- Tidak ada validasi hari libur/hari kerja — Sub-project 2. Admin BISA mencatat kehadiran di tanggal manapun tanpa penolakan sistem untuk sekarang.
- Tidak ada alur pengajuan izin/cuti oleh pegawai sendiri maupun approval berjenjang — Sub-project 4. Sub-project 1 hanya admin-input-langsung.
- Tidak menyentuh `App\Domains\Akademik\Models\SesiPembelajaran`/`Presensi` murid sama sekali.
- Tidak ada metode RFID/biometric/API — fondasi enum `AttendanceMethod` disiapkan agar BISA ditambah nanti (`admin`, `qr` dulu, value baru tinggal ditambah ke enum PHP tanpa migrasi schema attendance_events karena kolom `method` sudah string generik), tapi tidak diimplementasi sekarang.
- Tidak ada payroll/lembur/shift kompleks/GPS — sesuai non-goals PRD.
- Tidak ada perubahan pada model/tabel `Guru`, `Karyawan`, `Lembaga` existing — hanya ditambah relasi `morphMany` baru (`attendanceEvents()`, `attendanceRecords()`, `employeeQrCode()`) di kedua model, tidak ada perubahan kolom.

## 9. Testing

- Unit/feature test per Action: `RecordManualAttendanceAction`, `ScanQrAttendanceAction`, `GenerateEmployeeQrTokenAction`, `SetAttendanceMethodConfigurationAction`.
- Test tenant isolation eksplisit (pola yang sudah established di proyek ini, WAJIB karena riwayat bug cross-tenant IDOR berulang di modul lain): admin lembaga A tidak bisa mencatat/melihat kehadiran pegawai lembaga B; aktor yayasan TANPA switch-lembaga hanya lihat gabungan lembaga miliknya sendiri; aktor yayasan SETELAH switch-lembaga bisa create/lihat data lembaga yang dipilih.
- Test resolusi `AttendanceRecordAggregator`: multi-event sehari (QR masuk + admin manual pulang) menghasilkan 1 record dengan `waktu_masuk`+`waktu_pulang` terisi; event `izin` override status `hadir` pada hari yang sama.
- Test QR scan lintas-lembaga ditolak (petugas lembaga A scan token pegawai lembaga B → gagal, pesan jelas).
- Test RBAC: user tanpa permission `kehadiran-sdm.*` mendapat 403 di setiap endpoint baru.
- Full suite HANYA di task terakhir plan implementasi, dan HANYA setelah minta izin user dulu (kebijakan testing proyek ini).

## 10. Asumsi

- Baseline: commit `209e8af` di branch `sdm-v1` saat spec ini ditulis, working tree bersih.
- Model `Guru` dan `Karyawan` TIDAK digabung pada sub-project ini (sudah diputuskan lewat brainstorming) — relasi polymorphic `pegawai_type`/`pegawai_id` di `attendance_events`, `attendance_records`, `employee_qr_codes` mengarah ke `App\Models\Guru::class` atau `App\Models\Karyawan::class` via Eloquent morph map (WAJIB didaftarkan eksplisit di `AppServiceProvider` atau provider domain, bukan pakai FQCN mentah di kolom `pegawai_type`, demi konsistensi kalau nanti model dipindah namespace seperti migrasi domain Akademik yang sedang berjalan paralel).
- Plan implementasi WAJIB grep ulang struktur `RoleSeeder.php`/`PermissionSeeder.php` sebelum eksekusi kalau ada commit baru masuk branch ini di antara waktu spec ditulis dan plan dieksekusi.
