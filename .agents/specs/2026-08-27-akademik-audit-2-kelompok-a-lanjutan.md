# Fix Susulan Kelompok A — Widget Jadwal Siswa & Orang Tua — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks**: Setelah audit sistematis tahap 2 (Kelompok A/B/C) dinyatakan selesai, user menantang klaim itu dengan pertanyaan eksplisit soal layer siswa/orang tua. Audit lanjutan menemukan 1 bug nyata yang terlewat: fix widget "Jadwal Hari Ini" di Kelompok A HANYA diterapkan ke consumer guru, padahal 2 consumer lain di file yang sama (siswa, orang tua) punya bug identik.

---

## 1. Latar Belakang & Masalah

`DashboardController.php` (satu-satunya surface Akademik yang diakses siswa/orang tua — tidak ada controller/route Akademik terpisah untuk role ini) punya 3 widget "jadwal hari ini" yang query `JadwalPelajaran` langsung: guru (baris 51-56, SUDAH diperbaiki Kelompok A), siswa (baris 123-127, BELUM), orang tua (baris 234-240, BELUM). 2 yang belum diperbaiki punya bug identik: query by `kelas_id` tanpa filter semester aktif, sehingga jadwal dari tahun ajaran lama bisa tercampur dengan jadwal semester berjalan.

**Nuansa tambahan untuk orang tua**: kode branch orang tua sudah sengaja memakai `withoutGlobalScope(TenantScope::class)` di query dasarnya (baris 234) karena satu orang tua bisa punya anak di lembaga berbeda-beda (pola lintas-tenant yang sudah established di codebase ini). `scopeSemesterAktif()` versi Kelompok A (`whereHas('semester', fn($q) => $q->where('status_aktif', true))`) TIDAK membypass `TenantScope` pada subquery `semester` — karena `Semester` model memakai `BelongsToTenant`, subquery ini akan otomatis terfilter ke `lembaga_id` milik USER YANG LOGIN, bukan `lembaga_id` milik masing-masing anak. Untuk guru ini tidak masalah (guru satu lembaga, query jadwalnya sendiri sudah tenant-scoped, otomatis sinkron). Untuk orang tua ini SALAH: kalau diterapkan apa adanya, jadwal anak di lembaga lain (bukan `lembaga_id` milik akun orang tua, yang sering `null`) bisa hilang total dari widget, bukan cuma yang basi.

## 2. Keputusan Desain

### 2.1 — Perbaiki `scopeSemesterAktif()` agar aman di semua konteks tenant

`app/Models/JadwalPelajaran.php`, ubah:

```php
    /**
     * Filter ke jadwal yang semester-nya berstatus aktif. Semua consumer BARU
     * yang menampilkan jadwal "saat ini" (bukan laporan histori) WAJIB
     * memakai scope ini -- lihat riwayat bug widget "Jadwal Hari Ini" guru
     * yang bocor lintas tahun ajaran (audit 27 Agustus 2026), dan susulannya
     * utk siswa/orang tua (audit lanjutan 27 Agustus 2026).
     *
     * Subquery semester SENGAJA membypass TenantScope: semester_id sudah FK
     * langsung ke satu baris semester tertentu (bukan query terbuka lintas
     * tenant), jadi tidak butuh tenant-scope tambahan utk memvalidasi
     * status_aktif-nya -- dan MEMANG HARUS di-bypass supaya scope ini tetap
     * benar dipakai di konteks withoutGlobalScope(TenantScope::class)
     * (mis. widget jadwal anak orang tua lintas-lembaga).
     */
    public function scopeSemesterAktif(Builder $query): Builder
    {
        return $query->whereHas('semester', fn (Builder $q) => $q->withoutGlobalScope(TenantScope::class)->where('status_aktif', true));
    }
```

Tambah import `use App\Models\Scopes\TenantScope;`.

**Kenapa aman untuk guru (regresi tidak berubah)**: hasil query untuk guru identik sebelum/sesudah perubahan ini — guru hanya melihat jadwal dari lembaga-nya sendiri (query dasarnya sudah tenant-scoped tanpa `withoutGlobalScope`), jadi semester yang relevan juga selalu semester lembaga itu. Membypass TenantScope pada subquery semester tidak mengubah himpunan hasil untuk kasus single-tenant, hanya menghapus ketergantungan implisit yang kebetulan benar untuk guru tapi salah untuk orang tua.

### 2.2 — Terapkan `->semesterAktif()` ke widget siswa

`app/Http/Controllers/Admin/DashboardController.php:123-127`, ubah dari:

```php
                    $jadwalHariIni = JadwalPelajaran::withoutGlobalScope(TenantScope::class)
                        ->where('kelas_id', $siswa->kelas_id)
                        ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hariIni))
                        ->with(['kelas', 'mataPelajaran', 'jamPelajaran'])
                        ->get();
```

menjadi:

```php
                    $jadwalHariIni = JadwalPelajaran::withoutGlobalScope(TenantScope::class)
                        ->where('kelas_id', $siswa->kelas_id)
                        ->semesterAktif()
                        ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hariIni))
                        ->with(['kelas', 'mataPelajaran', 'jamPelajaran'])
                        ->get();
```

### 2.3 — Terapkan `->semesterAktif()` ke widget orang tua

`app/Http/Controllers/Admin/DashboardController.php:234-240`, ubah dari:

```php
                $jadwalAnakHariIni = empty($kelasIds)
                    ? collect()
                    : JadwalPelajaran::withoutGlobalScope(TenantScope::class)
                        ->whereIn('kelas_id', $kelasIds)
                        ->whereHas('jamPelajaran', fn ($q) => $q->withoutGlobalScope(TenantScope::class)->where('hari', $hariIni))
                        ->with([
                            'kelas' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                            'mataPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                            'jamPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                        ])
                        ->get();
```

menjadi:

```php
                $jadwalAnakHariIni = empty($kelasIds)
                    ? collect()
                    : JadwalPelajaran::withoutGlobalScope(TenantScope::class)
                        ->whereIn('kelas_id', $kelasIds)
                        ->semesterAktif()
                        ->whereHas('jamPelajaran', fn ($q) => $q->withoutGlobalScope(TenantScope::class)->where('hari', $hariIni))
                        ->with([
                            'kelas' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                            'mataPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                            'jamPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                        ])
                        ->get();
```

## 3. Non-Goals (eksplisit di luar scope)

- Tidak ada perubahan skema, tidak ada penghapusan data `jadwal_pelajaran` (konsisten dengan keputusan Kelompok A — jadwal lama tetap riwayat sah).
- Tidak menyentuh widget `nilaiTerbaru` siswa/ortu (sudah benar, memakai filter `JenisAsesmen::masukRapor()` dari fix sebelumnya).
- Tidak menyentuh area lain di luar 2 query ini — audit lanjutan sudah memastikan tidak ada consumer Akademik lain untuk siswa/orang tua di luar `DashboardController.php` (tidak ada controller/route Akademik terpisah untuk role ini; "Jadwal Minggu Ini" di view siswa hanya widget kalender statis, tidak query database).
- Tidak mengubah perilaku `withoutGlobalScope(TenantScope::class)` yang sudah ada di kedua branch — `->semesterAktif()` ditambahkan SEBAGAI TAMBAHAN filter, bukan pengganti pola tenant-bypass yang sudah benar.

## 4. Testing (acceptance criteria wajib)

**4.1 — `JadwalPelajaran::scopeSemesterAktif()` (regresi wajib, test existing)**:
- 2 test Kelompok A di `tests/Feature/DashboardTest.php` (skenario guru: jadwal lama vs aktif, dan lembaga tanpa semester aktif) HARUS tetap PASS tanpa modifikasi assertion apa pun.

**4.2 — Widget jadwal siswa**:
- Siswa dengan jadwal di semester AKTIF dan semester LAMA (kelas sama, tahun ajaran berbeda) pada hari yang sama → `$jadwalHariIni` HANYA berisi jadwal semester aktif. Assert existence dulu (jadwal lama benar-benar tersimpan) sebelum assert exclusion.
- Siswa yang lembaganya belum punya semester aktif → `$jadwalHariIni` kosong, tidak error.

**4.3 — Widget jadwal orang tua (termasuk skenario lintas-lembaga)**:
- Orang tua dengan 1 anak, jadwal di semester AKTIF dan semester LAMA → `$jadwalAnakHariIni` HANYA berisi jadwal semester aktif.
- **Skenario kritis lintas-lembaga**: orang tua dengan 2 anak di 2 LEMBAGA BERBEDA (akun orang tua sendiri `lembaga_id = null`, konsisten dengan pola test existing di `DashboardTest.php`) — anak A di lembaga X dengan jadwal semester aktif lembaga X, anak B di lembaga Y dengan jadwal semester aktif lembaga Y (semester aktif berbeda ID, masing-masing `status_aktif=true` di lembaganya sendiri) → `$jadwalAnakHariIni` HARUS berisi jadwal KEDUA anak (bukan cuma satu, bukan kosong) — ini pembuktian bahwa fix TenantScope-bypass di §2.1 benar-benar bekerja, bukan cuma "tidak error" tapi teruji secara semantik cross-tenant.
- Anak dengan jadwal semester lama di salah satu lembaga → jadwal itu TIDAK muncul, sementara jadwal semester aktif anak lain tetap muncul (buktikan filter bekerja independen per anak/lembaga, bukan all-or-nothing).

## 5. Ringkasan Perubahan File

```text
app/Models/JadwalPelajaran.php                        [scopeSemesterAktif() bypass TenantScope pada subquery semester]
app/Http/Controllers/Admin/DashboardController.php    [+semesterAktif() di widget siswa & orang tua]
tests/Feature/DashboardTest.php                       [+test siswa, +test orang tua termasuk skenario lintas-lembaga]
```
