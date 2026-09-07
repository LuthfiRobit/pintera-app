# Perbaikan Kebocoran Lintas-Yayasan & Kelalaian Karyawan Pool — Modul Karyawan & Guru Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tutup 6 bug (2 IDOR lintas-yayasan nyata, 2 kelalaian operasional karyawan pool, 1 exception tak tertangkap, 1 gap UX) di modul Karyawan & Guru, dikonfirmasi lewat verifikasi empiris (tinker, transaksi rollback).

**Architecture:** Ganti validasi `exists:` string jadi `Rule::exists()->where('yayasan_id', ...)` di 4 titik; perbaiki 2 resolver SDM supaya tier "per-lembaga" dilewati total untuk pegawai pool (bukan collapse diam-diam jadi query tanpa filter yayasan); tambah pass kedua per-yayasan di command Alpa otomatis + migrasi kolom nullable pendukungnya; tangkap exception race-condition; tambah field pencarian NIK yang hilang.

**Tech Stack:** Laravel 12 / PHP 8.3, Pest, Eloquent (`Rule::exists`, global scope, migrasi ubah kolom + FK).

## Global Constraints

- **Kelompok A (4 titik `exists:`)**: ganti jadi `Rule::exists(...)->where('yayasan_id', $yayasanId)`, `$yayasanId` HARUS sesuai konteks tiap titik (aktor login untuk create, pemilik row untuk update/target yang sudah ada) — JANGAN disamakan semua pakai 1 pola tanpa cek konteks masing-masing (lihat detail per task).
- **Kelompok B (2 resolver)**: pegawai pool (`lembaga_id === null`) WAJIB melewati TOTAL tier "per-lembaga spesifik" (bukan cuma menambah filter yayasan_id ke tier itu — tier itu secara konsep tidak berlaku untuk pool), langsung ke tier nasional/yayasan yang SUDAH benar. Pola PERSIS sama di kedua file, jangan ditulis beda.
- **Kelompok C bergantung urutan pada Kelompok B** (`resolveLibur()` untuk pool butuh fix B.1 dulu, kalau tidak `TypeError` fatal). JANGAN dikerjakan sebelum Kelompok B selesai & hijau.
- **Keputusan bisnis (WAJIB, sudah dikonfirmasi user, JANGAN diubah tanpa lapor balik)**: karyawan pool default SELALU hari kerja kecuali ada entri `KalenderKerjaSdm` eksplisit; `attendance_events.lembaga_id` diubah NULLABLE lewat migrasi (bukan opsi lain yang sempat dipertimbangkan — pakai lembaga representasi, atau skip total).
- Tidak pakai worktree, kerja langsung di branch `rbac-v2`.
- Kelompok E (index Karyawan) adalah fix MINIMAL (tambah field NIK), BUKAN rombak arsitektur SPA jadi server-side — di luar scope.

---

## Task 1: Kelompok A — Validasi `exists:` Tanpa Scope Yayasan (4 Titik)

**Files:**
- Modify: `app/Http/Controllers/Admin/KaryawanController.php` (baris 163, 241)
- Modify: `app/Http/Controllers/Admin/AttendanceConfigurationController.php` (baris ~335, method `storePolicy()`)
- Modify: `app/Http/Controllers/Admin/Guru/JabatanTambahanController.php` (baris 21, method `store()`)
- Test: `tests/Feature/Admin/KaryawanCrudTest.php`, `tests/Feature/Admin/AttendanceConfigurationControllerTest.php`, `tests/Feature/Admin/GuruRelationalProfileTest.php` (SEMUA file SUDAH ADA — tambahkan test baru ke masing-masing, jangan buat file baru)

**Interfaces:**
- Consumes: `Illuminate\Validation\Rule` (built-in Laravel, `Rule::exists($table, $column)->where(...)`).
- Produces: tidak ada — murni perubahan validasi internal, tidak ada signature publik berubah.

### Step 1: Tulis test baru (RED) — `KaryawanController::store()`

Tambahkan ke `tests/Feature/Admin/KaryawanCrudTest.php` (baca dulu helper `actingAs...` yang sudah ada di file itu untuk konsisten pola):

```php
it('rejects a jenis_karyawan_id belonging to a different yayasan on create', function () {
    $manager = actingAsKaryawanManager(); // sesuaikan nama helper dengan yang sudah ada di file
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $jenisYayasanLain = JenisKaryawanMaster::factory()->create(['yayasan_id' => $lembagaLain->yayasan_id]);

    $response = $this->actingAs($manager)->post(route('admin.karyawan.store'), [
        'nama' => 'Karyawan Baru', 'nik' => '3201234567891234',
        'jenis_karyawan_id' => $jenisYayasanLain->id,
    ]);

    $response->assertSessionHasErrors('jenis_karyawan_id');
    expect(Karyawan::where('nama', 'Karyawan Baru')->exists())->toBeFalse();
});
```
(Sesuaikan payload create dengan field yang benar-benar dibutuhkan `validateProfil()` — baca ulang method itu dulu, termasuk kemungkinan butuh `lembaga` aktif via switcher/session tergantung helper aktor yang dipakai.)

### Step 2: Tulis test baru (RED) — `KaryawanController::update()`

```php
it('rejects a jenis_karyawan_id belonging to a different yayasan on update', function () {
    $manager = actingAsKaryawanManager();
    $karyawan = Karyawan::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $jenisYayasanLain = JenisKaryawanMaster::factory()->create(['yayasan_id' => $lembagaLain->yayasan_id]);

    $response = $this->actingAs($manager)->put(route('admin.karyawan.update', $karyawan), [
        'nama' => 'Nama Baru', 'jenis_karyawan_id' => $jenisYayasanLain->id,
    ]);

    $response->assertSessionHasErrors('jenis_karyawan_id');
    expect($karyawan->fresh()->jenis_karyawan_id)->not->toBe($jenisYayasanLain->id);
});
```

### Step 3: Tulis test baru (RED) — `AttendanceConfigurationController::storePolicy()`

Tambahkan ke `tests/Feature/Admin/AttendanceConfigurationControllerTest.php` (baca dulu helper aktor yang sudah dipakai file ini):

```php
it('rejects a jenis_karyawan_id belonging to a different yayasan when creating an attendance policy', function () {
    $manager = actingAsAttendanceConfigManager(); // sesuaikan nama helper existing
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $jenisYayasanLain = JenisKaryawanMaster::factory()->create(['yayasan_id' => $lembagaLain->yayasan_id]);

    $response = $this->actingAs($manager)->post(route('admin.kehadiran-sdm.konfigurasi.policy.store'), [
        'kategori_tipe' => 'karyawan',
        'jenis_karyawan_id' => $jenisYayasanLain->id,
        'jam_masuk' => '07:00',
        'toleransi_menit' => 5,
    ]);

    $response->assertSessionHasErrors('jenis_karyawan_id');
});
```
(Cek route name persis lewat `routes/admin/` untuk `storePolicy()` — nama di atas asumsi, VERIFIKASI dan sesuaikan.)

### Step 4: Tulis test baru (RED) — `Guru\JabatanTambahanController::store()`

Tambahkan ke `tests/Feature/Admin/GuruRelationalProfileTest.php` (persis pola test cross-lembaga yang sudah ada di file untuk riwayat-pendidikan, tapi untuk yayasan):

```php
it('rejects attaching a jabatan_tambahan_master belonging to a different yayasan', function () {
    $manager = actingAsGuruManager(); // sesuaikan nama helper existing di file ini
    $guru = Guru::factory()->create(['lembaga_id' => $manager->lembaga_id]);
    $yayasanLain = Yayasan::factory()->create();
    $jabatanYayasanLain = JabatanTambahanMaster::factory()->create(['yayasan_id' => $yayasanLain->id]);

    $response = $this->actingAs($manager)->post(route('admin.guru.jabatan-tambahan.store', $guru), [
        'jabatan_tambahan_master_id' => $jabatanYayasanLain->id,
        'mulai_periode' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('jabatan_tambahan_master_id');
    expect($guru->jabatanTambahan()->where('jabatan_tambahan_master_id', $jabatanYayasanLain->id)->exists())->toBeFalse();
});
```

### Step 5: Jalankan keempat test, verifikasi GAGAL

Run: `php artisan test --filter="belonging to a different yayasan" --compact`
Expected: 4 test FAIL — validasi `exists:` polos saat ini LOLOS untuk ID lintas-yayasan (dikonfirmasi empiris sebelumnya via tinker), jadi `assertSessionHasErrors` gagal karena TIDAK ADA error yang muncul.

### Step 6: Fix — `KaryawanController.php`

Tambah `use Illuminate\Validation\Rule;` ke import (baris setelah `use Illuminate\Support\Facades\DB;`).

Edit `validateProfil()` baris 241:
```php
// Sebelum:
'jenis_karyawan_id' => ['required', 'exists:jenis_karyawan_master,id'],
// Sesudah:
'jenis_karyawan_id' => ['required', Rule::exists('jenis_karyawan_master', 'id')->where('yayasan_id', $yayasanId)],
```
(`$yayasanId` sudah dihitung baris 217-223 di method yang sama — tidak perlu variabel baru.)

Edit `update()` baris 155-164, tambahkan `Rule::exists` ter-scope ke `$karyawan->yayasan_id`:
```php
public function update(Request $request, Karyawan $karyawan): RedirectResponse
{
    $this->authorize('karyawan.edit');

    $data = $request->validate([
        'nama' => ['required', 'string', 'max:255'],
        'email' => ['nullable', 'email', 'max:255'],
        'no_hp' => ['nullable', 'string', 'max:20'],
        'jenis_karyawan_id' => ['required', Rule::exists('jenis_karyawan_master', 'id')->where('yayasan_id', $karyawan->yayasan_id)],
    ]);
    // ...sisa method (DB::transaction dst.) TIDAK berubah
```

### Step 7: Fix — `AttendanceConfigurationController.php`

`Rule` kemungkinan besar BELUM diimport (cek dulu) — tambah `use Illuminate\Validation\Rule;` kalau belum ada.

Edit `storePolicy()` baris awal (sebelum `$request->validate([...])`), tambahkan penghitungan `$yayasanIdUntukValidasi` dari input mentah:
```php
public function storePolicy(Request $request): RedirectResponse
{
    $this->authorize('kehadiran-sdm.kelola-konfigurasi');

    // Dihitung dari input mentah (belum tervalidasi) khusus untuk scoping Rule::exists di
    // bawah -- $yayasanId "resmi" tetap dihitung ulang via resolveYayasanId() setelah validasi
    // seperti sebelumnya, TIDAK diganti, supaya sisa logic method ini tidak berubah.
    $isNasionalMentah = $request->boolean('is_nasional');
    $lembagaIdMentah = $isNasionalMentah ? null : $this->resolveLembagaId($request);
    $yayasanIdUntukValidasi = $this->resolveYayasanId($request, $lembagaIdMentah);

    $data = $request->validate([
        'kategori_tipe' => ['required', 'in:guru,karyawan'],
        'jenis_ptk' => ['required_if:kategori_tipe,guru', 'nullable', 'in:guru_kelas,guru_mapel,kepala_sekolah,tenaga_administrasi,guru_bk'],
        'jenis_karyawan_id' => [
            'required_if:kategori_tipe,karyawan', 'nullable', 'integer',
            Rule::exists('jenis_karyawan_master', 'id')->where('yayasan_id', $yayasanIdUntukValidasi),
        ],
        'jam_masuk' => ['required', 'date_format:H:i'],
        'jam_pulang' => ['nullable', 'date_format:H:i'],
        'toleransi_menit' => ['required', 'integer', 'min:0'],
        'hari_kerja' => ['nullable', 'array'],
        'hari_kerja.*' => ['integer', 'between:0,6'],
        'is_nasional' => ['nullable', 'boolean'],
    ]);
    // ...sisa method (baris $isNasional = ... dst.) TIDAK berubah sama sekali
```

### Step 8: Fix — `Guru\JabatanTambahanController.php`

Tambah `use Illuminate\Validation\Rule;` (belum ada).

Edit `store()`:
```php
public function store(Request $request, Guru $guru): RedirectResponse
{
    $this->authorize('guru.edit');
    $this->ensureTenantScope($request, $guru);

    $yayasanId = $guru->lembaga?->yayasan_id;

    $data = $request->validate([
        'jabatan_tambahan_master_id' => ['required', 'integer', Rule::exists('jabatan_tambahan_master', 'id')->where('yayasan_id', $yayasanId)],
        'mulai_periode' => ['required', 'date'],
        'akhir_periode' => ['nullable', 'date', 'after_or_equal:mulai_periode'],
        'no_sk' => ['nullable', 'string', 'max:100'],
    ]);

    $guru->jabatanTambahan()->attach($data['jabatan_tambahan_master_id'], [
        'mulai_periode' => $data['mulai_periode'],
        'akhir_periode' => $data['akhir_periode'] ?? null,
        'no_sk' => $data['no_sk'] ?? null,
    ]);

    return back()->with('status', 'Jabatan tambahan berhasil ditambahkan.');
}
```

### Step 9: Jalankan keempat test, verifikasi LULUS

Run: `php artisan test --filter="belonging to a different yayasan" --compact`
Expected: 4 test PASS.

### Step 10: Regresi — jalankan seluruh file test yang disentuh

Run: `php artisan test tests/Feature/Admin/KaryawanCrudTest.php tests/Feature/Admin/AttendanceConfigurationControllerTest.php tests/Feature/Admin/GuruRelationalProfileTest.php --compact`
Expected: SEMUA test (existing + baru) PASS, 0 regresi.

### Step 11: Pint & Commit

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/KaryawanController.php app/Http/Controllers/Admin/AttendanceConfigurationController.php app/Http/Controllers/Admin/Guru/JabatanTambahanController.php tests/Feature/Admin/KaryawanCrudTest.php tests/Feature/Admin/AttendanceConfigurationControllerTest.php tests/Feature/Admin/GuruRelationalProfileTest.php
git commit -m "fix(sdm): tutup 4 celah validasi exists: yang lupa scope yayasan

Rule bawaan Laravel exists:table,column query mentah ke tabel, tidak
pernah lewat YayasanScope. 4 titik (KaryawanController create+update,
AttendanceConfigurationController storePolicy, Guru JabatanTambahan-
Controller store) menerima ID jenis_karyawan/jabatan_tambahan milik
yayasan lain sebagai valid -- dikonfirmasi empiris via tinker sebelum
fix ini. Diganti Rule::exists()->where('yayasan_id', ...) di semua
titik, scope disesuaikan konteks masing-masing (aktor login untuk
create, pemilik row untuk update/attach ke resource existing).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Kelompok B — Resolver Query Pool Tanpa Scope Yayasan (2 File)

**Files:**
- Modify: `app/Domains/Sdm/Services/AttendancePolicyResolver.php`
- Modify: `app/Domains/Sdm/Services/KuotaCutiResolver.php`
- Test: `tests/Feature/Sdm/AttendancePolicyTenantIsolationTest.php`, `tests/Feature/Sdm/KuotaCutiResolverTest.php` (SUDAH ADA, tambahkan test baru)

**Interfaces:**
- Consumes: `Karyawan.yayasan_id` (kolom langsung, `$fillable`), `Karyawan.lembaga_id` (nullable = pool).
- Produces: `AttendancePolicyResolver::resolvePolicy()`/`resolveConfig()` (via `KuotaCutiResolver`) — signature TIDAK berubah, hanya logic internal. Task 3 (Kelompok C) BERGANTUNG pada fix B.1 (`resolveLibur()`) di task ini — WAJIB task ini selesai & hijau dulu sebelum Task 3 dimulai.

### Step 1: Tulis test baru (RED) — `AttendancePolicyResolver`

Tambahkan ke `tests/Feature/Sdm/AttendancePolicyTenantIsolationTest.php` (file ini SUDAH ADA tapi HANYA menguji bypass TenantScope aktor login, BELUM menguji kebocoran pool lintas-yayasan — tambahkan test BARU, bukan modifikasi yang sudah ada):

```php
it('does not leak another yayasan pool AttendancePolicy to a pool karyawan with the same jenis_karyawan_id number', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();

    $jenisA = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create(['yayasan_id' => $yayasanA->id]);
    $personA = \App\Domains\Identity\Models\Person::factory()->create(['yayasan_id' => $yayasanA->id]);
    $karyawanPoolA = \App\Models\Karyawan::create([
        'person_id' => $personA->id, 'yayasan_id' => $yayasanA->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenisA->id, 'status_aktif' => 'aktif',
    ]);

    \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasanB->id, 'lembaga_id' => null, 'jenis_karyawan_id' => $jenisA->id,
        'jam_masuk' => '07:00', 'toleransi_menit' => 999,
    ]);

    $result = app(AttendancePolicyResolver::class)->resolvePolicy($karyawanPoolA);

    expect($result)->toBeNull();
});

it('resolves the correct pool AttendancePolicy scoped to the pool karyawan own yayasan', function () {
    $yayasanA = Yayasan::factory()->create();
    $jenisA = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create(['yayasan_id' => $yayasanA->id]);
    $personA = \App\Domains\Identity\Models\Person::factory()->create(['yayasan_id' => $yayasanA->id]);
    $karyawanPoolA = \App\Models\Karyawan::create([
        'person_id' => $personA->id, 'yayasan_id' => $yayasanA->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenisA->id, 'status_aktif' => 'aktif',
    ]);
    $policyA = \App\Domains\Sdm\Models\AttendancePolicy::create([
        'yayasan_id' => $yayasanA->id, 'lembaga_id' => null, 'jenis_karyawan_id' => $jenisA->id,
        'jam_masuk' => '08:00', 'toleransi_menit' => 10,
    ]);

    $result = app(AttendancePolicyResolver::class)->resolvePolicy($karyawanPoolA);

    expect($result?->id)->toBe($policyA->id);
});
```

### Step 2: Tulis test baru (RED) — `KuotaCutiResolver`

Tambahkan ke `tests/Feature/Sdm/KuotaCutiResolverTest.php`:

```php
it('does not leak another yayasan pool KuotaCutiConfig to a pool karyawan with the same jenis_karyawan_id number', function () {
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();

    $jenisA = \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create(['yayasan_id' => $yayasanA->id]);
    $personA = \App\Domains\Identity\Models\Person::factory()->create(['yayasan_id' => $yayasanA->id]);
    $karyawanPoolA = \App\Models\Karyawan::create([
        'person_id' => $personA->id, 'yayasan_id' => $yayasanA->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => $jenisA->id, 'status_aktif' => 'aktif',
    ]);

    KuotaCutiConfig::create([
        'yayasan_id' => $yayasanB->id, 'lembaga_id' => null, 'jenis_karyawan_id' => $jenisA->id,
        'jatah_hari_per_tahun' => 999,
    ]);

    expect(app(KuotaCutiResolver::class)->jatahTahunan($karyawanPoolA))->toBeNull();
});
```

### Step 3: Jalankan ketiga test, verifikasi GAGAL

Run: `php artisan test --filter="does not leak another yayasan" --compact`
Expected: 3 test FAIL — dikonfirmasi empiris sebelumnya via tinker (kedua resolver mengembalikan baris milik yayasan B).

### Step 4: Fix — `AttendancePolicyResolver.php`

Tambah import `use App\Domains\Sdm\Models\KalenderKerjaSdm;` dan `use App\Domains\Sdm\Enums\TipeKalenderKerjaSdm;`.

Ganti `resolvePolicy()` dan `resolveLibur()` LENGKAP:
```php
public function resolvePolicy(Model $pegawai): ?AttendancePolicy
{
    $kolomKategori = $pegawai instanceof Guru ? 'jenis_ptk' : 'jenis_karyawan_id';
    $nilaiKategori = $pegawai instanceof Guru ? $pegawai->jenis_ptk : $pegawai->jenis_karyawan_id;
    $yayasanId = $pegawai->lembaga_id !== null ? $pegawai->lembaga->yayasan_id : $pegawai->yayasan_id;

    if ($pegawai->lembaga_id !== null) {
        $policyLembaga = AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $pegawai->lembaga_id)
            ->where($kolomKategori, $nilaiKategori)
            ->first();

        if ($policyLembaga) {
            return $policyLembaga;
        }
    }

    return AttendancePolicy::withoutGlobalScope(TenantScope::class)
        ->whereNull('lembaga_id')
        ->where('yayasan_id', $yayasanId)
        ->where($kolomKategori, $nilaiKategori)
        ->first();
}

/**
 * @return array{libur: bool, alasan: string}
 */
public function resolveLibur(Model $pegawai, CarbonInterface $tanggal): array
{
    $policy = $this->resolvePolicy($pegawai);

    if ($policy && $policy->hari_kerja !== null) {
        $adalahHariKerja = in_array($tanggal->dayOfWeek, $policy->hari_kerja, true);

        return $adalahHariKerja
            ? ['libur' => false, 'alasan' => 'Hari kerja sesuai kebijakan peran']
            : ['libur' => true, 'alasan' => 'Hari libur sesuai kebijakan peran'];
    }

    if ($pegawai->lembaga_id === null) {
        return $this->resolveLiburPool($pegawai->yayasan_id, $tanggal);
    }

    return $this->kalenderResolver->resolve($pegawai->lembaga, $tanggal);
}

private function resolveLiburPool(int $yayasanId, CarbonInterface $tanggal): array
{
    $entriNasional = KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
        ->whereNull('lembaga_id')
        ->where('yayasan_id', $yayasanId)
        ->where(function ($q) use ($tanggal) {
            $tgl = $tanggal->toDateString();
            $q->whereDate('tanggal', '<=', $tgl)
                ->where(fn ($q2) => $q2->whereDate('tanggal_selesai', '>=', $tgl)
                    ->orWhere(fn ($q3) => $q3->whereNull('tanggal_selesai')->whereDate('tanggal', '>=', $tgl))
                );
        })
        ->first();

    if ($entriNasional) {
        return [
            'libur' => $entriNasional->tipe === TipeKalenderKerjaSdm::Libur,
            'alasan' => $entriNasional->nama,
        ];
    }

    return ['libur' => false, 'alasan' => 'Hari kerja efektif (karyawan pool, default hari kerja)'];
}
```

### Step 5: Fix — `KuotaCutiResolver.php`

Ganti `resolveConfig()` LENGKAP:
```php
public function resolveConfig(Model $pegawai): ?KuotaCutiConfig
{
    $kolomKategori = $pegawai instanceof Guru ? 'jenis_ptk' : 'jenis_karyawan_id';
    $nilaiKategori = $pegawai instanceof Guru ? $pegawai->jenis_ptk : $pegawai->jenis_karyawan_id;
    $yayasanId = $pegawai->lembaga_id !== null ? $pegawai->lembaga->yayasan_id : $pegawai->yayasan_id;

    if ($pegawai->lembaga_id !== null) {
        $spesifikLembaga = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $pegawai->lembaga_id)
            ->where($kolomKategori, $nilaiKategori)
            ->first();
        if ($spesifikLembaga) {
            return $spesifikLembaga;
        }

        $flatLembaga = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $pegawai->lembaga_id)
            ->whereNull('jenis_ptk')
            ->whereNull('jenis_karyawan_id')
            ->first();
        if ($flatLembaga) {
            return $flatLembaga;
        }
    }

    $spesifikNasional = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
        ->whereNull('lembaga_id')
        ->where('yayasan_id', $yayasanId)
        ->where($kolomKategori, $nilaiKategori)
        ->first();
    if ($spesifikNasional) {
        return $spesifikNasional;
    }

    return KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
        ->whereNull('lembaga_id')
        ->where('yayasan_id', $yayasanId)
        ->whereNull('jenis_ptk')
        ->whereNull('jenis_karyawan_id')
        ->first();
}
```
(Method lain di file yang sama — `jatahTahunan()`, `sisaKuota()` — TIDAK berubah.)

### Step 6: Jalankan ketiga test baru, verifikasi LULUS

Run: `php artisan test --filter="does not leak another yayasan" --compact`
Expected: 3 test PASS.

### Step 7: Regresi — seluruh file resolver + test terkait

Run: `php artisan test tests/Unit/Services/AttendancePolicyResolverTest.php tests/Feature/Sdm/AttendancePolicyTenantIsolationTest.php tests/Feature/Sdm/AttendancePolicyControllerTest.php tests/Feature/Sdm/AttendancePolicyViewTest.php tests/Feature/Sdm/AttendancePolicyModelTest.php tests/Feature/Sdm/KuotaCutiResolverTest.php tests/Feature/Sdm/KuotaCutiConfigTest.php tests/Feature/Admin/KuotaCutiConfigControllerTest.php --compact`
Expected: SEMUA PASS, 0 regresi — perhatikan KHUSUS test existing `AttendancePolicyResolverTest.php` yang menguji Guru (selalu `lembaga_id !== null`, harus tetap identik perilakunya karena fix ini murni menambah cabang `if ($pegawai->lembaga_id !== null)` di sekitar logic yang SUDAH ADA, tidak mengubah logic itu sendiri untuk kasus non-pool).

### Step 8: Verifikasi grep — pastikan tidak ada file lain dengan pola sama yang terlewat

Run: `grep -rn "where('lembaga_id', \$pegawai->lembaga_id)" app/Domains/Sdm/Services/`
Expected: HANYA muncul di 2 file yang baru diperbaiki (dan sekarang sudah dibungkus `if ($pegawai->lembaga_id !== null)`) — kalau ada file LAIN dengan pola sama yang belum diperbaiki, STOP dan laporkan sebagai temuan tambahan, jangan diperbaiki diam-diam di luar scope task ini tanpa mencatatnya.

### Step 9: Pint & Commit

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Sdm/Services/AttendancePolicyResolver.php app/Domains/Sdm/Services/KuotaCutiResolver.php tests/Feature/Sdm/AttendancePolicyTenantIsolationTest.php tests/Feature/Sdm/KuotaCutiResolverTest.php
git commit -m "fix(sdm): tutup kebocoran kebijakan presensi & kuota cuti lintas-yayasan utk karyawan pool

AttendancePolicyResolver dan KuotaCutiResolver punya tier resolusi
'per-lembaga' yang untuk karyawan pool (lembaga_id null) diam-diam
collapse jadi query whereNull('lembaga_id') TANPA filter yayasan_id --
bisa cocok dengan baris kebijakan/kuota milik yayasan lain. Dikonfirmasi
empiris via tinker sebelum fix. Tier per-lembaga sekarang dilewati
total untuk pool, langsung ke tier nasional/yayasan yang sudah benar.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Kelompok C — Karyawan Pool Terlewat dari Alur Operasional

**Files:**
- Create: migrasi via `php artisan make:migration make_lembaga_id_nullable_on_attendance_events_table --no-interaction`
- Modify: `app/Console/Commands/TandaiAlpaOtomatisSdm.php`
- Modify: `app/Http/Controllers/Admin/AttendanceConfigurationController.php` (baris ~107, `index()`)
- Modify: `app/Http/Controllers/Admin/AttendanceController.php` (baris ~48-51, `create()`)
- Test: `tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`, `tests/Feature/Admin/AttendanceConfigurationControllerTest.php`, `tests/Feature/Admin/AttendanceControllerTest.php` (SEMUA SUDAH ADA)

**Interfaces:**
- Consumes: `AttendancePolicyResolver::resolveLibur()` (fix Task 2, WAJIB task itu selesai dulu).
- Produces: tidak ada — perubahan internal command + query controller.

### Step 1: Migrasi — `attendance_events.lembaga_id` jadi nullable

```bash
php artisan make:migration make_lembaga_id_nullable_on_attendance_events_table --no-interaction
```
Isi:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropForeign(['lembaga_id']);
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->unsignedBigInteger('lembaga_id')->nullable()->change();
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->foreign('lembaga_id')->references('id')->on('lembaga')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropForeign(['lembaga_id']);
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->unsignedBigInteger('lembaga_id')->nullable(false)->change();
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->foreign('lembaga_id')->references('id')->on('lembaga')->cascadeOnDelete();
        });
    }
};
```
Run: `php artisan migrate`
Expected: sukses. (`down()` akan gagal kalau sudah ada baris `lembaga_id IS NULL` di data saat itu — ini sengaja/lossy, dokumentasikan di komentar migrasi kalau perlu, TIDAK perlu ditangani otomatis.)

### Step 2: Tulis test baru (RED) — `TandaiAlpaOtomatisSdm` untuk karyawan pool

Tambahkan ke `tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php` (ikuti PERSIS pola `Carbon::setTestNow()`/`Carbon::setTestNow()` reset yang sudah dipakai di semua test lain di file ini):

```php
it('marks an active pool karyawan with no attendance record as Alpa for yesterday, using AttendanceRecord not lembaga-specific', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-25 01:00:00')); // Tuesday
    $yayasan = Yayasan::factory()->create();
    $person = \App\Domains\Identity\Models\Person::factory()->create(['yayasan_id' => $yayasan->id]);
    $karyawanPool = Karyawan::create([
        'person_id' => $person->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create(['yayasan_id' => $yayasan->id])->id,
        'status_aktif' => 'aktif',
    ]);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    $record = AttendanceRecord::where('pegawai_type', Karyawan::class)->where('pegawai_id', $karyawanPool->id)->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe(AttendanceStatus::Alpa);

    Carbon::setTestNow();
});

it('does not mark a pool karyawan Alpa when an explicit KalenderKerjaSdm nasional entry marks the day libur', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-25 01:00:00')); // Tuesday
    $yayasan = Yayasan::factory()->create();
    $person = \App\Domains\Identity\Models\Person::factory()->create(['yayasan_id' => $yayasan->id]);
    $karyawanPool = Karyawan::create([
        'person_id' => $person->id, 'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'jenis_karyawan_id' => \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create(['yayasan_id' => $yayasan->id])->id,
        'status_aktif' => 'aktif',
    ]);
    \App\Domains\Sdm\Models\KalenderKerjaSdm::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => null,
        'nama' => 'Libur Nasional Test', 'tanggal' => '2026-08-24', 'tanggal_selesai' => null,
        'tipe' => \App\Domains\Sdm\Enums\TipeKalenderKerjaSdm::Libur,
    ]);

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    expect(AttendanceRecord::where('pegawai_type', Karyawan::class)->where('pegawai_id', $karyawanPool->id)->exists())->toBeFalse();

    Carbon::setTestNow();
});
```
(VERIFIKASI kolom `KalenderKerjaSdm` yang benar saat implementasi — baca model/migrasi tabel itu langsung dulu untuk memastikan nama kolom persis, terutama `tanggal_selesai` nullable atau tidak.)

### Step 3: Jalankan test baru, verifikasi GAGAL

Run: `php artisan test --filter="pool karyawan" --compact`
Expected: FAIL — command belum memproses karyawan pool sama sekali (0 `AttendanceRecord` dibuat untuk test pertama).

### Step 4: Fix — `TandaiAlpaOtomatisSdm.php`

Tambah `use App\Models\Yayasan;` ke import.

Edit `handle()`:
```php
public function handle(): int
{
    $tanggal = now()->subDay()->toImmutable();
    $jumlahDitandai = 0;

    foreach (Lembaga::all() as $lembaga) {
        $pegawaiList = collect()
            ->concat(Guru::where('lembaga_id', $lembaga->id)->where('status_aktif', 'aktif')->get())
            ->concat(Karyawan::where('lembaga_id', $lembaga->id)->where('status_aktif', 'aktif')->get())
            ->filter(fn ($pegawai) => ! $this->resolver->resolveLibur($pegawai, $tanggal)['libur'])
            ->filter(fn ($pegawai) => ! $this->punyaPengajuanPending($pegawai, $tanggal));

        $jumlahDitandai += $this->tandaiPegawaiTanpaRecord($pegawaiList, $lembaga, $tanggal);
    }

    // Pass kedua: karyawan pool (lembaga_id null), diproses per-yayasan. Guru TIDAK punya
    // konsep pool (selalu lembaga_id terisi), tidak perlu pass tambahan untuk Guru.
    foreach (Yayasan::all() as $yayasan) {
        $karyawanPoolList = Karyawan::whereNull('lembaga_id')
            ->where('yayasan_id', $yayasan->id)
            ->where('status_aktif', 'aktif')
            ->get()
            ->filter(fn ($pegawai) => ! $this->resolver->resolveLibur($pegawai, $tanggal)['libur'])
            ->filter(fn ($pegawai) => ! $this->punyaPengajuanPending($pegawai, $tanggal));

        $jumlahDitandai += $this->tandaiPegawaiTanpaRecordPool($karyawanPoolList, $yayasan, $tanggal);
    }

    $this->info("{$jumlahDitandai} pegawai ditandai Alpa otomatis untuk tanggal {$tanggal->toDateString()}.");

    return self::SUCCESS;
}
```

Tambah method baru (JANGAN modifikasi `tandaiPegawaiTanpaRecord()` existing):
```php
private function tandaiPegawaiTanpaRecordPool(Collection $pegawaiList, Yayasan $yayasan, \Carbon\CarbonImmutable $tanggal): int
{
    $jumlah = 0;

    foreach ($pegawaiList as $pegawai) {
        $sudahAda = AttendanceRecord::where('pegawai_type', $pegawai::class)
            ->where('pegawai_id', $pegawai->id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->exists();

        if ($sudahAda) {
            continue;
        }

        $pegawai->attendanceEvents()->create([
            'lembaga_id' => null,
            'method' => AttendanceMethod::System,
            'arah' => 'masuk',
            'status' => AttendanceStatus::Alpa,
            'waktu' => $tanggal->setTime(23, 59),
            'dicatat_oleh_user_id' => null,
            'catatan' => 'Ditandai otomatis oleh sistem — tidak ada aktivitas kehadiran pada hari kerja ini (karyawan pool yayasan).',
        ]);

        $this->aggregator->sync($pegawai, $tanggal);
        $jumlah++;
    }

    return $jumlah;
}
```

### Step 5: Jalankan test baru, verifikasi LULUS

Run: `php artisan test --filter="pool karyawan" --compact`
Expected: PASS.

### Step 6: Regresi — seluruh file test command

Run: `php artisan test tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php --compact`
Expected: SEMUA PASS (existing + baru), termasuk test Guru/Karyawan ber-lembaga yang TIDAK BOLEH berubah perilakunya.

### Step 7: Tulis test baru (RED) — `AttendanceConfigurationController::index()` dropdown karyawan pool

Tambahkan ke `tests/Feature/Admin/AttendanceConfigurationControllerTest.php`:
```php
it('includes pool karyawan in the karyawanList picker when a lembaga is active', function () {
    $manager = actingAsAttendanceConfigManager(); // sesuaikan nama helper existing
    $person = \App\Domains\Identity\Models\Person::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $karyawanPool = Karyawan::create([
        'person_id' => $person->id, 'yayasan_id' => $manager->yayasan_id, 'lembaga_id' => null,
        'jenis_karyawan_id' => \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create(['yayasan_id' => $manager->yayasan_id])->id,
        'status_aktif' => 'aktif',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.kehadiran-sdm.konfigurasi.index'));

    $response->assertOk();
    $ids = collect($response->viewData('karyawanList'))->pluck('id');
    expect($ids)->toContain((string) $karyawanPool->id);
});
```
(VERIFIKASI route name & mekanisme aktivasi lembaga aktif yang dipakai helper existing file ini.)

### Step 8: Tulis test baru (RED) — `AttendanceController::create()` dropdown karyawan pool

Pola sama, tambahkan ke `tests/Feature/Admin/AttendanceControllerTest.php`:
```php
it('includes pool karyawan in the karyawanList picker when a lembaga is active', function () {
    $manager = actingAsAttendanceManager(); // sesuaikan nama helper existing
    $person = \App\Domains\Identity\Models\Person::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $karyawanPool = Karyawan::create([
        'person_id' => $person->id, 'yayasan_id' => $manager->yayasan_id, 'lembaga_id' => null,
        'jenis_karyawan_id' => \App\Domains\Sdm\Models\JenisKaryawanMaster::factory()->create(['yayasan_id' => $manager->yayasan_id])->id,
        'status_aktif' => 'aktif',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.kehadiran-sdm.create'));

    $response->assertOk();
    $ids = collect($response->viewData('karyawanList'))->pluck('id');
    expect($ids)->toContain((string) $karyawanPool->id);
});
```

### Step 9: Jalankan kedua test, verifikasi GAGAL

Run: `php artisan test --filter="includes pool karyawan in the karyawanList picker" --compact`
Expected: FAIL — dropdown belum pool-aware.

### Step 10: Fix — `AttendanceConfigurationController.php` baris ~106-109

```php
$karyawanList = $lembagaId
    ? Karyawan::withoutGlobalScope(TenantScope::class)
        ->where(function ($q) use ($lembagaId, $yayasanId) {
            $q->where('lembaga_id', $lembagaId)
                ->orWhere(fn ($q2) => $q2->whereNull('lembaga_id')->where('yayasan_id', $yayasanId));
        })
        ->with('person')->orderByNama()->get(['karyawan.id', 'karyawan.nama', 'karyawan.email', 'karyawan.person_id'])
        ->map(fn ($k) => ['id' => (string) $k->id, 'nama' => $k->nama, 'subtext' => $k->email ?? ''])->values()
    : collect();
```
(`$yayasanId` sudah tersedia di baris 51 method ini, `TenantScope`/`Lembaga` sudah diimport.)

### Step 11: Fix — `AttendanceController.php` baris ~44-51

```php
public function create(Request $request): View
{
    $this->authorize('kehadiran-sdm.catat');

    $lembagaId = $this->resolveLembagaId($request);
    $yayasanId = $lembagaId ? Lembaga::find($lembagaId)?->yayasan_id : null;

    $guruList = $lembagaId
        ? Guru::where('lembaga_id', $lembagaId)->with('person')->orderByNama()->get(['guru.id', 'guru.nama', 'guru.nip', 'guru.nuptk', 'guru.person_id'])
            ->map(fn ($g) => ['id' => (string) $g->id, 'nama' => $g->nama, 'subtext' => $g->nip ? 'NIP: '.$g->nip : ($g->nuptk ? 'NUPTK: '.$g->nuptk : '')])->values()
        : collect();
    $karyawanList = $lembagaId
        ? Karyawan::withoutGlobalScope(TenantScope::class)
            ->where(function ($q) use ($lembagaId, $yayasanId) {
                $q->where('lembaga_id', $lembagaId)
                    ->orWhere(fn ($q2) => $q2->whereNull('lembaga_id')->where('yayasan_id', $yayasanId));
            })
            ->with('person')->orderByNama()->get(['karyawan.id', 'karyawan.nama', 'karyawan.email', 'karyawan.person_id'])
            ->map(fn ($k) => ['id' => (string) $k->id, 'nama' => $k->nama, 'subtext' => $k->email ?? ''])->values()
        : collect();
    $titikAbsen = $lembagaId ? AttendancePoint::where('lembaga_id', $lembagaId)->where('is_active', true)->orderBy('nama')->get() : collect();

    return view('admin.kehadiran-sdm.create', [
        'guruList' => $guruList,
        'karyawanList' => $karyawanList,
        'titikAbsen' => $titikAbsen,
        // ...variabel lain di return view() yang sudah ada TIDAK berubah
```
Tambah `use App\Models\Lembaga;` dan `use App\Models\Scopes\TenantScope;` ke import (dikonfirmasi KEDUANYA belum ada di file ini).

### Step 12: Jalankan kedua test, verifikasi LULUS

Run: `php artisan test --filter="includes pool karyawan in the karyawanList picker" --compact`
Expected: PASS.

### Step 13: Regresi — seluruh file yang disentuh task ini

Run: `php artisan test tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php tests/Feature/Admin/AttendanceConfigurationControllerTest.php tests/Feature/Admin/AttendanceConfigurationKalenderControllerTest.php tests/Feature/Admin/AttendanceControllerTest.php --compact`
Expected: SEMUA PASS, 0 regresi.

### Step 14: Pint & Commit

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/*_make_lembaga_id_nullable_on_attendance_events_table.php \
    app/Console/Commands/TandaiAlpaOtomatisSdm.php \
    app/Http/Controllers/Admin/AttendanceConfigurationController.php \
    app/Http/Controllers/Admin/AttendanceController.php \
    tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php \
    tests/Feature/Admin/AttendanceConfigurationControllerTest.php \
    tests/Feature/Admin/AttendanceControllerTest.php
git commit -m "fix(sdm): sertakan karyawan pool di Alpa otomatis & dropdown pemilih karyawan

Karyawan pool (lembaga_id null) sebelumnya tidak pernah diproses
sdm:tandai-alpa-otomatis (loop cuma per-lembaga) dan tidak muncul di
dropdown pemilih karyawan Konfigurasi Kehadiran/Catat Manual (query
belum pool-aware, tidak konsisten dgn bagian lain controller yg sama).
Ditambah pass kedua per-yayasan di command + migrasi attendance_events.
lembaga_id jadi nullable (keputusan bisnis dikonfirmasi user).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: Kelompok D — `PersonAlreadyExistsException` Tidak Ditangkap di `GuruController::store()`

**Files:**
- Modify: `app/Http/Controllers/Admin/GuruController.php`
- Test: `tests/Feature/Admin/GuruCrudTest.php` (SUDAH ADA)

**Interfaces:**
- Consumes: `App\Domains\Identity\Exceptions\PersonAlreadyExistsException` (sudah ada).
- Produces: tidak ada.

### Step 1: Tulis test baru (RED)

Tambahkan ke `tests/Feature/Admin/GuruCrudTest.php` (baca dulu helper aktor existing di file):

```php
it('shows a friendly validation error instead of a 500 when CreatePersonAction throws during a race condition', function () {
    $manager = actingAsGuruManager(); // sesuaikan nama helper existing

    // Simulasikan race condition: Person dengan nik_hash yang akan dicari sudah ada SEBELUM
    // validateProfil()'s pre-check sempat jalan -- pre-check itu sendiri seharusnya menangkap
    // ini di kondisi normal, jadi test ini fokus ke jalur exception CreatePersonAction, bukan
    // pre-check. Cara paling sederhana mensimulasikan: panggil controller method langsung
    // dengan Person yang SENGAJA dibuat SETELAH validasi tapi SEBELUM CreatePersonAction --
    // kalau ini sulit disimulasikan lewat HTTP test biasa, gunakan mock/partial untuk
    // memastikan exception yang dilempar tertangkap dengan benar (VERIFIKASI pendekatan
    // paling praktis saat implementasi, boleh unit-test CreatePersonAction exception path
    // terpisah dari full HTTP round-trip kalau itu lebih straightforward).
    $existingPerson = \App\Domains\Identity\Models\Person::factory()->create([
        'yayasan_id' => $manager->lembaga->yayasan_id,
        'nik' => '3201234567891111',
    ]);

    $response = $this->actingAs($manager)->post(route('admin.guru.store'), [
        'nik' => '3201234567891111', 'nip' => '198001012020121001', 'nama' => 'Guru Baru',
        'email' => 'guru.baru@example.test', 'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas',
        'status_kepegawaian' => 'GTY',
    ]);

    $response->assertSessionHasErrors('nik');
    $response->assertStatus(302); // redirect, BUKAN 500
});
```
(Catatan: skenario ini SEBENARNYA sudah tertangkap oleh pre-check `validateProfil()` yang sudah ada, sehingga test ini KEMUNGKINAN BESAR sudah PASS bahkan SEBELUM fix Kelompok D diterapkan — itu KARENA pre-check menutup kasus sequential ini. Fix Kelompok D murni untuk celah RACE CONDITION konkuren yang tidak bisa direproduksi murni lewat 1 HTTP request test biasa. Test ini tetap bernilai sebagai regresi umum "duplicate NIK tidak 500", TAPI TIDAK membuktikan fix Kelompok D secara spesifik. Kalau implementer punya cara lebih baik mensimulasikan race condition asli (concurrent request), pertimbangkan menambahkannya; kalau tidak, test di atas + code review manual bahwa try/catch sudah benar ditulis cukup untuk task ini.)

### Step 2: Jalankan test, verifikasi PASS (karena pre-check sudah menangani skenario sequential)

Run: `php artisan test --filter="shows a friendly validation error instead of a 500 when CreatePersonAction throws" --compact`
Expected: PASS (bahkan sebelum fix Kelompok D — lihat catatan Step 1).

### Step 3: Terapkan fix Kelompok D (defense-in-depth, bukan dibuktikan RED oleh test di atas)

Tambah `use App\Domains\Identity\Exceptions\PersonAlreadyExistsException;` ke import.

Edit `store()`:
```php
public function store(Request $request): RedirectResponse
{
    $this->authorize('guru.create');

    $data = $this->validateProfil($request);

    $lembagaId = $this->resolveLembagaId($request);
    if ($lembagaId === null) {
        return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah data guru.'])->withInput();
    }

    try {
        DB::transaction(function () use ($data, $lembagaId) {
            $person = app(CreatePersonAction::class)->execute(
                identityData: [
                    'nama_lengkap' => $data['nama'],
                    'nik' => $data['nik'] ?? null,
                    'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                    'tempat_lahir' => $data['tempat_lahir'] ?? null,
                    'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
                    'agama' => $data['agama'] ?? null,
                    'kewarganegaraan' => $data['kewarganegaraan'] ?? 'WNI',
                    'no_hp' => $data['no_hp'] ?? null,
                    'email' => $data['email'],
                    'alamat_jalan' => $data['alamat_jalan'] ?? null,
                    'rt' => $data['rt'] ?? null,
                    'rw' => $data['rw'] ?? null,
                    'desa_kelurahan' => $data['desa_kelurahan'] ?? null,
                    'kecamatan' => $data['kecamatan'] ?? null,
                    'kabupaten_kota' => $data['kabupaten_kota'] ?? null,
                    'provinsi' => $data['provinsi'] ?? null,
                    'kode_pos' => $data['kode_pos'] ?? null,
                ],
                lembagaId: $lembagaId,
                actingYayasanId: null,
            );

            $user = User::create([
                'name' => $data['nama'],
                'email' => $data['email'],
                'password' => Hash::make($data['nip']),
                'lembaga_id' => $lembagaId,
                'email_verified_at' => now(),
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $user->assignRole('guru');
            $person->update(['user_id' => $user->id]);

            Guru::create([
                'person_id' => $person->id,
                'lembaga_id' => $lembagaId,
                'nuptk' => $data['nuptk'] ?? null,
                'nip' => $data['nip'],
                'jenis_ptk' => $data['jenis_ptk'],
                'status_kepegawaian' => $data['status_kepegawaian'],
                'golongan_pangkat' => $data['golongan_pangkat'] ?? null,
                'tmt_tugas' => $data['tmt_tugas'] ?? null,
                'tmt_pns' => $data['tmt_pns'] ?? null,
                'status_aktif' => 'aktif',
                'kapasitas_kasus_aktif' => $data['kapasitas_kasus_aktif'] ?? null,
            ]);
        });
    } catch (PersonAlreadyExistsException $exception) {
        return back()
            ->withErrors(['nik' => 'NIK ini sudah terdaftar untuk profil lain di yayasan ini.'])
            ->withInput();
    }

    return redirect()->route('admin.guru.index')->with('status', 'Data guru & akun berhasil dibuat.');
}
```

### Step 4: Regresi — seluruh file test Guru

Run: `php artisan test tests/Feature/Admin/GuruCrudTest.php tests/Feature/Admin/GuruRelationalProfileTest.php --compact`
Expected: SEMUA PASS, 0 regresi.

### Step 5: Pint & Commit

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/GuruController.php tests/Feature/Admin/GuruCrudTest.php
git commit -m "fix(guru): tangkap PersonAlreadyExistsException di GuruController::store()

Race condition submit ganda (2 request konkuren lolos pre-check NIK
yang sama sebelum salah satunya masuk CreatePersonAction) bisa
memunculkan 500 mentah alih-alih pesan validasi ramah. Defense-in-depth
di atas pre-check yang sudah ada.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 5: Kelompok E — Index Karyawan: Tambah Pencarian NIK

**Files:**
- Modify: `resources/views/admin/karyawan/index.blade.php`
- Test: `tests/Feature/Admin/KaryawanCrudTest.php` (SUDAH ADA)

**Interfaces:** Tidak ada — murni penambahan field ke payload JSON + kondisi filter client-side Alpine.

### Step 1: Tulis test baru (RED)

Tambahkan ke `tests/Feature/Admin/KaryawanCrudTest.php`:

```php
it('includes nik in the index page payload for client-side search', function () {
    $manager = actingAsKaryawanManager();
    $person = \App\Domains\Identity\Models\Person::factory()->create(['yayasan_id' => $manager->yayasan_id, 'nik' => '3201234567895555']);
    $karyawan = Karyawan::create([
        'person_id' => $person->id, 'yayasan_id' => $manager->yayasan_id, 'lembaga_id' => $manager->lembaga_id,
        'jenis_karyawan_id' => JenisKaryawanMaster::factory()->create(['yayasan_id' => $manager->yayasan_id])->id,
        'status_aktif' => 'aktif',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.karyawan.index'));

    $response->assertOk();
    $response->assertSee('3201234567895555');
});
```

### Step 2: Jalankan test, verifikasi GAGAL

Run: `php artisan test --filter="includes nik in the index page payload" --compact`
Expected: FAIL — `nik` belum ada di payload JSON, `assertSee` tidak menemukan string itu.

### Step 3: Fix — `resources/views/admin/karyawan/index.blade.php`

Edit `$spaItems` mapping (baris 228-244), sisipkan setelah `'nama' => $k->nama,`:
```php
'nama' => $k->nama,
'nik' => $k->person?->nik,
```

Edit `filteredItems` getter Alpine (baris ~285), kondisi saat ini:
```js
res = res.filter(i => i.nama.toLowerCase().includes(q) || i.jenis_nama.toLowerCase().includes(q) || i.lembaga_nama.toLowerCase().includes(q));
```
Sesudah:
```js
res = res.filter(i => i.nama.toLowerCase().includes(q) || (i.nik && i.nik.toLowerCase().includes(q)) || i.jenis_nama.toLowerCase().includes(q) || i.lembaga_nama.toLowerCase().includes(q));
```

### Step 4: Jalankan test, verifikasi LULUS

Run: `php artisan test --filter="includes nik in the index page payload" --compact`
Expected: PASS.

### Step 5: Verifikasi manual di browser

Buka halaman Data Induk Karyawan, ketik sebagian NIK di kotak pencarian, assert baris yang cocok muncul. Laporkan hasil verifikasi jujur di laporan task.

### Step 6: Regresi & Pint

Run: `php artisan test tests/Feature/Admin/KaryawanCrudTest.php --compact`
```bash
vendor/bin/pint --dirty --format agent
```

### Step 7: Commit

```bash
git add resources/views/admin/karyawan/index.blade.php tests/Feature/Admin/KaryawanCrudTest.php
git commit -m "fix(karyawan): tambah pencarian NIK di index (sebelumnya tidak bisa dicari sama sekali)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 6: Penutup — Full Suite, Pint, Handoff Log, Roadmap

**Files:**
- Create: `.agents/logs/2026-09-07-karyawan-guru-lintas-yayasan-audit.md`
- Modify: `PETA_PENGEMBANGAN.md`

### Step 1: Cek proses PHP lain sebelum full suite

Run (PowerShell): `Get-CimInstance Win32_Process -Filter "Name='php.exe'"` — tunggu kalau ada `php artisan test` lain berjalan.

### Step 2: Full Test Suite

Run: `php artisan test --compact`
Expected: SEMUA lulus KECUALI 4 kegagalan pre-existing yang sudah berulang kali terdokumentasi (`M3DemoDataSeederTest` x2, `PresensiSeederTest`, `SesiPembelajaranSeederTest`). Kegagalan LAIN = regresi nyata, STOP dan investigasi.

### Step 3: Pint

Run: `vendor/bin/pint --dirty --format agent`

### Step 4: Handoff Log

Buat `.agents/logs/2026-09-07-karyawan-guru-lintas-yayasan-audit.md`, ikuti struktur referensi (`.agents/logs/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md`): rangkuman Task 1-5 dengan commit hash, keputusan penting (2 keputusan bisnis dikonfirmasi user), hasil full suite, "Di Luar Scope" dikutip dari spec.

### Step 5: Update Roadmap

Tambahkan entri baru ke `PETA_PENGEMBANGAN.md`.

### Step 6: Commit dokumentasi

```bash
git add .agents/logs/2026-09-07-karyawan-guru-lintas-yayasan-audit.md PETA_PENGEMBANGAN.md
git commit -m "docs(sdm): handoff log & update roadmap -- perbaikan lintas-yayasan Karyawan & Guru selesai

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage**: Kelompok A (4 titik) → Task 1. Kelompok B (2 resolver) → Task 2. Kelompok C (migrasi + command + 2 dropdown) → Task 3, dependency eksplisit pada Task 2 dicatat di Global Constraints & Interfaces Task 3. Kelompok D → Task 4 (termasuk catatan jujur bahwa test HTTP biasa tidak sepenuhnya membuktikan celah race condition, defense-in-depth tetap diterapkan). Kelompok E → Task 5. Keputusan bisnis (default hari kerja pool, migrasi nullable) → tercermin di Task 2 (`resolveLiburPool`) dan Task 3 (migrasi + command). "Di Luar Scope" dari spec → tidak ada task untuk itu, dicatat di Task 6 handoff log.

**2. Placeholder scan**: Semua step kode berisi snippet lengkap. 1 pengecualian disengaja & didokumentasikan (Task 4 Step 1, test race-condition yang jujur diakui tidak sepenuhnya membuktikan skenario konkuren asli — bukan placeholder, tapi keterbatasan teknis yang diakui eksplisit, dengan fix tetap diterapkan sebagai defense-in-depth).

**3. Type consistency**: `resolvePolicy()`/`resolveConfig()`/`resolveLibur()` signature tidak berubah dari Task 2 ke Task 3 (Task 3 cuma memanggilnya, tidak mendefinisikan ulang). `$yayasanId` dihitung dengan pola SAMA persis di semua titik yang butuh (`Lembaga::find($lembagaId)?->yayasan_id` untuk konteks lembaga aktif, `$pegawai->lembaga->yayasan_id` vs `$pegawai->yayasan_id` untuk konteks resolver pool) — konsisten lintas Task 1-3, tidak ditulis beda tanpa alasan.
