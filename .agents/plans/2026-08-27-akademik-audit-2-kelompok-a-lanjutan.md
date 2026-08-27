# Fix Susulan Kelompok A — Widget Jadwal Siswa & Orang Tua Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menerapkan fix "widget jadwal hari ini bocor lintas tahun ajaran" (sudah diperbaiki untuk guru di Kelompok A) ke 2 consumer yang terlewat: widget jadwal siswa dan orang tua di `DashboardController.php` — sekaligus membenahi `scopeSemesterAktif()` agar aman dipakai di konteks lintas-lembaga orang tua.

**Architecture:** Perubahan tunggal di `JadwalPelajaran::scopeSemesterAktif()` (bypass TenantScope pada subquery semester), lalu diterapkan ke 2 query existing di `DashboardController.php`. Tidak ada file/class baru.

**Tech Stack:** Laravel 12.68, Pest v4, MySQL.

## Global Constraints

- `scopeSemesterAktif()` HARUS membypass `TenantScope` pada subquery `semester` (`whereHas('semester', fn($q) => $q->withoutGlobalScope(TenantScope::class)->where('status_aktif', true))`) — TIDAK BOLEH mengandalkan TenantScope implisit, karena scope ini dipakai baik di konteks tenant-scoped normal (guru, siswa) maupun di konteks `withoutGlobalScope(TenantScope::class)` (orang tua lintas-lembaga).
- Tidak ada penghapusan/mutasi data `jadwal_pelajaran` — jadwal lama tetap riwayat sah, konsisten dengan keputusan Kelompok A.
- `->semesterAktif()` ditambahkan SEBAGAI TAMBAHAN filter pada query siswa/orang tua yang sudah ada — TIDAK mengubah/menghapus pola `withoutGlobalScope(TenantScope::class)` yang sudah benar di kedua branch tsb.
- 2 test regresi existing di `tests/Feature/DashboardTest.php` (skenario guru dari Kelompok A) HARUS tetap PASS tanpa modifikasi assertion.
- Jalankan `vendor/bin/pint --dirty --format agent` sebelum commit.
- Test scoped (`tests/Feature/DashboardTest.php` penuh) — TIDAK PERLU full suite, perubahan ini sempit dan sudah ada regression test existing dari Kelompok A yang jadi pengaman utama.

---

### Task 1: Perbaiki `scopeSemesterAktif()` agar tenant-safe di semua konteks

**Files:**
- Modify: `app/Models/JadwalPelajaran.php`
- Test: `tests/Feature/DashboardTest.php` (regresi existing, tidak ada test baru khusus di task ini — pembuktiannya menyatu dengan Task 2 & 3)

**Interfaces:**
- Produces: `JadwalPelajaran::scopeSemesterAktif(Builder $query): Builder` — signature TIDAK BERUBAH, hanya implementasi internal.

- [ ] **Step 1: Baca file existing untuk konfirmasi baseline**

Baca `app/Models/JadwalPelajaran.php` penuh — pastikan method `scopeSemesterAktif()` masih persis seperti hasil Kelompok A:

```php
    public function scopeSemesterAktif(Builder $query): Builder
    {
        return $query->whereHas('semester', fn (Builder $q) => $q->where('status_aktif', true));
    }
```

Kalau berbeda, STOP dan laporkan ke user.

- [ ] **Step 2: Jalankan test regresi existing dulu, catat baseline PASS**

Run: `php artisan test tests/Feature/DashboardTest.php --filter="today-schedule widget" --compact`
Expected: PASS (2 test, dari Kelompok A) — ini baseline SEBELUM perubahan, memastikan kita mulai dari kondisi hijau.

- [ ] **Step 3: Ubah implementasi `scopeSemesterAktif()`**

Edit `app/Models/JadwalPelajaran.php`. Tambah import:

```php
use App\Models\Scopes\TenantScope;
```

Ubah method (cari method `semester()`, method `scopeSemesterAktif()` ada tepat setelahnya):

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

- [ ] **Step 4: Jalankan ulang test regresi, pastikan MASIH pass (proof perubahan tidak mengubah hasil guru)**

Run: `php artisan test tests/Feature/DashboardTest.php --filter="today-schedule widget" --compact`
Expected: PASS, 2/2 test, HASIL SAMA seperti Step 2 — membuktikan perubahan implementasi tidak mengubah perilaku untuk kasus guru (single-tenant).

- [ ] **Step 5: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/JadwalPelajaran.php
git commit -m "fix(akademik): scopeSemesterAktif bypass TenantScope agar aman lintas-lembaga"
```

---

### Task 2: Terapkan `->semesterAktif()` ke widget jadwal siswa

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php:123-127`
- Test: `tests/Feature/DashboardTest.php` (tambah 2 test baru)

**Interfaces:**
- Consumes: `JadwalPelajaran::scopeSemesterAktif()` (Task 1).

- [ ] **Step 1: Baca file existing untuk konfirmasi baseline**

Baca `app/Http/Controllers/Admin/DashboardController.php` baris 113-170 (branch `if ($user->hasRole('siswa'))`) — pastikan baris 123-127 persis:

```php
                    $jadwalHariIni = JadwalPelajaran::withoutGlobalScope(TenantScope::class)
                        ->where('kelas_id', $siswa->kelas_id)
                        ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hariIni))
                        ->with(['kelas', 'mataPelajaran', 'jamPelajaran'])
                        ->get();
```

Kalau berbeda, STOP dan laporkan.

- [ ] **Step 2: Tulis test yang gagal**

Tambahkan di akhir `tests/Feature/DashboardTest.php` (gunakan import yang sudah ada di file: `JadwalPelajaran`, `Semester`, `Kelas`, `Siswa`, `JamPelajaran`, `Hari`, `Role`, `User`, `Lembaga`):

```php
it('excludes jadwal pelajaran from a non-active semester from the siswa today-schedule widget', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $lembaga = Lembaga::factory()->create();
    $siswaUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['user_id' => $siswaUser->id, 'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
    $jamPelajaran = JamPelajaran::factory()->create(['hari' => $hariIni]);

    $semesterLama = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    $jadwalLama = JadwalPelajaran::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jamPelajaran->id,
        'semester_id' => $semesterLama->id,
    ]);

    $semesterAktif = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $jadwalAktif = JadwalPelajaran::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jamPelajaran->id,
        'semester_id' => $semesterAktif->id,
    ]);

    // Buktikan dulu jadwal lama benar-benar tersimpan sebelum assert exclusion.
    expect(JadwalPelajaran::where('id', $jadwalLama->id)->exists())->toBeTrue();

    $response = $this->actingAs($siswaUser)->get('/dashboard');

    $response->assertOk();
    $response->assertViewHas('jadwalHariIni', function ($jadwalHariIni) use ($jadwalAktif, $jadwalLama) {
        return $jadwalHariIni->pluck('id')->contains($jadwalAktif->id)
            && ! $jadwalHariIni->pluck('id')->contains($jadwalLama->id);
    });
});

it('shows an empty today-schedule widget for a siswa whose lembaga has no active semester', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $lembaga = Lembaga::factory()->create();
    $siswaUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaUser->assignRole('siswa');
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['user_id' => $siswaUser->id, 'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
    $jamPelajaran = JamPelajaran::factory()->create(['hari' => $hariIni]);
    $semesterTidakAktif = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    JadwalPelajaran::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jamPelajaran->id,
        'semester_id' => $semesterTidakAktif->id,
    ]);

    $response = $this->actingAs($siswaUser)->get('/dashboard');

    $response->assertOk();
    $response->assertViewHas('jadwalHariIni', fn ($jadwalHariIni) => $jadwalHariIni->isEmpty());
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="siswa today-schedule widget" --compact`
Expected: FAIL — `jadwalHariIni` masih berisi `$jadwalLama` karena belum ada filter.

- [ ] **Step 4: Terapkan `->semesterAktif()` di controller**

Edit `app/Http/Controllers/Admin/DashboardController.php:123-127`, ubah dari:

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

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="siswa today-schedule widget" --compact`
Expected: PASS, 2/2 test.

Run juga test existing supaya tidak regresi: `php artisan test tests/Feature/DashboardTest.php --compact`
Expected: semua test di file PASS, 0 failed (termasuk test siswa lama seperti `'passes profile, schedule, and unpaid bills to the siswa dashboard view'` dan `'shows a siswa their latest recorded grade on their own dashboard'`).

- [ ] **Step 6: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/DashboardController.php tests/Feature/DashboardTest.php
git commit -m "fix(akademik): widget jadwal hari ini siswa filter semester aktif"
```

---

### Task 3: Terapkan `->semesterAktif()` ke widget jadwal orang tua (termasuk skenario lintas-lembaga)

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php:234-240`
- Test: `tests/Feature/DashboardTest.php` (tambah 3 test baru)

**Interfaces:**
- Consumes: `JadwalPelajaran::scopeSemesterAktif()` (Task 1).

- [ ] **Step 1: Baca file existing untuk konfirmasi baseline**

Baca `app/Http/Controllers/Admin/DashboardController.php` baris 172-255 (branch `if ($user->hasRole('orang_tua'))`) — pastikan baris 230-243 persis:

```php
                $kelasIds = $anakList->pluck('kelas_id')->filter()->all();
                $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
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

Kalau berbeda, STOP dan laporkan.

- [ ] **Step 2: Tulis test yang gagal — termasuk skenario kritis lintas-lembaga**

Tambahkan di akhir `tests/Feature/DashboardTest.php` (gunakan import yang sudah ada: `OrangTua` — cek dulu apakah sudah di-import, kalau belum tambahkan `use App\Models\OrangTua;`):

```php
it('excludes jadwal pelajaran from a non-active semester from the orang tua children-schedule widget', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $lembaga = Lembaga::factory()->create();
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
    $jamPelajaran = JamPelajaran::factory()->create(['hari' => $hariIni]);

    $semesterLama = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    $jadwalLama = JadwalPelajaran::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jamPelajaran->id,
        'semester_id' => $semesterLama->id,
    ]);

    $semesterAktif = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $jadwalAktif = JadwalPelajaran::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => $jamPelajaran->id,
        'semester_id' => $semesterAktif->id,
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    expect(JadwalPelajaran::where('id', $jadwalLama->id)->exists())->toBeTrue();

    $response = $this->actingAs($orangTuaUser)->get('/dashboard');

    $response->assertOk();
    $response->assertViewHas('jadwalAnakHariIni', function ($jadwalAnakHariIni) use ($jadwalAktif, $jadwalLama) {
        return $jadwalAnakHariIni->pluck('id')->contains($jadwalAktif->id)
            && ! $jadwalAnakHariIni->pluck('id')->contains($jadwalLama->id);
    });
});

it('includes active-semester jadwal for children in two DIFFERENT lembaga (cross-tenant orang tua)', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
    $jamPelajaran = JamPelajaran::factory()->create(['hari' => $hariIni]);

    $lembagaX = Lembaga::factory()->create();
    $kelasX = Kelas::factory()->create(['lembaga_id' => $lembagaX->id]);
    $siswaX = Siswa::factory()->create(['lembaga_id' => $lembagaX->id, 'kelas_id' => $kelasX->id]);
    $semesterAktifX = Semester::factory()->create(['lembaga_id' => $lembagaX->id, 'status_aktif' => true]);
    $jadwalAktifX = JadwalPelajaran::factory()->create([
        'lembaga_id' => $lembagaX->id, 'kelas_id' => $kelasX->id,
        'jam_pelajaran_id' => $jamPelajaran->id, 'semester_id' => $semesterAktifX->id,
    ]);

    $lembagaY = Lembaga::factory()->create();
    $kelasY = Kelas::factory()->create(['lembaga_id' => $lembagaY->id]);
    $siswaY = Siswa::factory()->create(['lembaga_id' => $lembagaY->id, 'kelas_id' => $kelasY->id]);
    $semesterAktifY = Semester::factory()->create(['lembaga_id' => $lembagaY->id, 'status_aktif' => true]);
    $jadwalAktifY = JadwalPelajaran::factory()->create([
        'lembaga_id' => $lembagaY->id, 'kelas_id' => $kelasY->id,
        'jam_pelajaran_id' => $jamPelajaran->id, 'semester_id' => $semesterAktifY->id,
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswaX->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);
    $orangTua->siswa()->attach($siswaY->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    $response = $this->actingAs($orangTuaUser)->get('/dashboard');

    $response->assertOk();
    $response->assertViewHas('jadwalAnakHariIni', function ($jadwalAnakHariIni) use ($jadwalAktifX, $jadwalAktifY) {
        return $jadwalAnakHariIni->pluck('id')->contains($jadwalAktifX->id)
            && $jadwalAnakHariIni->pluck('id')->contains($jadwalAktifY->id);
    });
});

it('excludes only the non-active-semester jadwal of one child while keeping the other child active jadwal (cross-tenant orang tua)', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
    $jamPelajaran = JamPelajaran::factory()->create(['hari' => $hariIni]);

    $lembagaX = Lembaga::factory()->create();
    $kelasX = Kelas::factory()->create(['lembaga_id' => $lembagaX->id]);
    $siswaX = Siswa::factory()->create(['lembaga_id' => $lembagaX->id, 'kelas_id' => $kelasX->id]);
    $semesterLamaX = Semester::factory()->create(['lembaga_id' => $lembagaX->id, 'status_aktif' => false]);
    $jadwalLamaX = JadwalPelajaran::factory()->create([
        'lembaga_id' => $lembagaX->id, 'kelas_id' => $kelasX->id,
        'jam_pelajaran_id' => $jamPelajaran->id, 'semester_id' => $semesterLamaX->id,
    ]);

    $lembagaY = Lembaga::factory()->create();
    $kelasY = Kelas::factory()->create(['lembaga_id' => $lembagaY->id]);
    $siswaY = Siswa::factory()->create(['lembaga_id' => $lembagaY->id, 'kelas_id' => $kelasY->id]);
    $semesterAktifY = Semester::factory()->create(['lembaga_id' => $lembagaY->id, 'status_aktif' => true]);
    $jadwalAktifY = JadwalPelajaran::factory()->create([
        'lembaga_id' => $lembagaY->id, 'kelas_id' => $kelasY->id,
        'jam_pelajaran_id' => $jamPelajaran->id, 'semester_id' => $semesterAktifY->id,
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswaX->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);
    $orangTua->siswa()->attach($siswaY->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    expect(JadwalPelajaran::where('id', $jadwalLamaX->id)->exists())->toBeTrue();

    $response = $this->actingAs($orangTuaUser)->get('/dashboard');

    $response->assertOk();
    $response->assertViewHas('jadwalAnakHariIni', function ($jadwalAnakHariIni) use ($jadwalAktifY, $jadwalLamaX) {
        return $jadwalAnakHariIni->pluck('id')->contains($jadwalAktifY->id)
            && ! $jadwalAnakHariIni->pluck('id')->contains($jadwalLamaX->id);
    });
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="orang tua children-schedule widget|cross-tenant orang tua" --compact`
Expected: FAIL — ketiga test gagal karena belum ada filter `semesterAktif()`, dan skenario lintas-lembaga belum teruji sama sekali.

- [ ] **Step 4: Terapkan `->semesterAktif()` di controller**

Edit `app/Http/Controllers/Admin/DashboardController.php:230-243`, ubah dari:

```php
                $kelasIds = $anakList->pluck('kelas_id')->filter()->all();
                $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
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
                $kelasIds = $anakList->pluck('kelas_id')->filter()->all();
                $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
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

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="orang tua children-schedule widget|cross-tenant orang tua" --compact`
Expected: PASS, 3/3 test — termasuk kedua skenario lintas-lembaga, yang membuktikan fix Task 1 (bypass TenantScope pada subquery semester) benar-benar diperlukan dan bekerja.

- [ ] **Step 6: Jalankan seluruh `DashboardTest.php` sbg checkpoint akhir (test scoped, BUKAN full suite)**

Run: `php artisan test tests/Feature/DashboardTest.php --compact`
Expected: 0 failed. Catat angka pasti (jumlah test/assertion) di laporan akhir — file ini sekarang berisi test guru (Kelompok A) + siswa + orang tua (termasuk lintas-lembaga) untuk widget jadwal, semuanya harus hijau bersamaan.

**Tidak perlu full suite** — perubahan plan ini sempit (1 method + 2 query di 1 controller), dan `DashboardTest.php` sudah menjadi regression suite yang representatif untuk seluruh permukaan yang tersentuh.

- [ ] **Step 7: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/DashboardController.php tests/Feature/DashboardTest.php
git commit -m "fix(akademik): widget jadwal hari ini orang tua filter semester aktif per anak"
```

- [ ] **Step 8: Catat penyelesaian fix susulan di PETA_PENGEMBANGAN.md**

Baca dulu bagian "Audit Sistematis Akademik Tahap 2" existing (paragraf Kelompok A), tambahkan 1 kalimat tindak lanjut bahwa widget jadwal siswa & orang tua (yang sempat terlewat saat Kelompok A) sudah diperbaiki pada tanggal hari ini, termasuk perbaikan `scopeSemesterAktif()` agar tenant-safe untuk skenario orang tua lintas-lembaga.

```bash
git add PETA_PENGEMBANGAN.md
git commit -m "docs: catat fix susulan widget jadwal siswa & orang tua Kelompok A"
```
