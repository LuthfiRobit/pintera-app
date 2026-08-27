# Audit Sistematis Akademik Tahap 2 — Kelompok A (Kritis) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup 3 temuan kritis dari audit sistematis Akademik tahap 2: widget jadwal guru yang bocor lintas tahun ajaran, drift snapshot `kelas.kurikulum`/`fase_id` tanpa mekanisme koreksi, dan nama ekskul bebas tanpa validasi yang bisa lolos ke rapor cetak resmi.

**Architecture:** Task 1 murni perbaikan filter query + query scope baru (tidak ada penghapusan data). Task 2 menambah 1 Action baru + 2 method controller + 1 view baru mengikuti pola Actions/Controllers Akademik yang sudah ada (scoped platform/yayasan/lembaga). Task 3 mengganti input teks bebas jadi `<select>` tervalidasi terhadap master data existing.

**Tech Stack:** Laravel 12.68, Pest v4, MySQL, Blade + Alpine.js (tanpa perubahan JS baru — hanya ganti tag input).

## Global Constraints

- TIDAK ADA penghapusan/mutasi data `jadwal_pelajaran` di Task 1 — hanya filter query. Jadwal lama tetap tersimpan sebagai riwayat sah (lihat spec §1.1.1: FK `sesi_pembelajaran.jadwal_pelajaran_id` cascadeOnDelete, presensi historis akan hilang kalau dihapus).
- Task 2 TIDAK mengubah perilaku "snapshot beku" `UpdateKelasAction`/`UpdateKurikulumAssignmentAction`/`UpdateFaseDefaultMappingAction` — itu keputusan arsitektur disengaja yang sudah di-test (`tests/Feature/Akademik/KelasKurikulumSnapshotTest.php`). Resync HANYA lewat aksi manual eksplisit yang ditulis di Task 2.
- Task 2 resync HARUS menghitung ulang nilai live di server (`KurikulumAssignmentResolver`/`FaseDefaultResolver`), TIDAK PERNAH mempercayai nilai dari input form.
- Task 3: setiap submit form catatan wali kelas SELALU memvalidasi ULANG seluruh array `ekstrakurikuler` terhadap daftar master AKTIF — tidak ada logika "skip validasi baris yang tidak diubah".
- Semua Action baru: `final class`, method tunggal `execute()`, dependency di-inject via constructor (pola `.ai/rules/actions.md`).
- Jalankan `vendor/bin/pint --dirty --format agent` di akhir setiap task sebelum commit.
- Jalankan test scoped per task; jalankan full suite (`php artisan test --compact`) HANYA di Task 3 Step terakhir.

---

### Task 1: Fix widget "Jadwal Hari Ini" guru — filter semester aktif

**Files:**
- Modify: `app/Models/JadwalPelajaran.php`
- Modify: `app/Http/Controllers/Admin/DashboardController.php:51-56`
- Test: `tests/Feature/DashboardTest.php` (tambah 2 test baru)

**Interfaces:**
- Produces: `JadwalPelajaran::scopeSemesterAktif(Builder $query): Builder` — query scope baru yang bisa dipakai consumer lain ke depan sbg default guard.

- [ ] **Step 1: Tulis test yang gagal — jadwal semester lama tidak boleh muncul di widget**

Tambahkan di akhir `tests/Feature/DashboardTest.php` (gunakan import yang sudah ada di file: `JadwalPelajaran`, `Semester`, `Guru`, `Role`, `User`, `Lembaga`, `Kelas`, `JamPelajaran`, `Hari`):

```php
it('excludes jadwal pelajaran from a non-active semester from the guru today-schedule widget', function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $lembaga = Lembaga::factory()->create();
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole('guru');
    $guru = Guru::factory()->create(['user_id' => $guruUser->id, 'lembaga_id' => $lembaga->id]);

    $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
    $jamPelajaran = JamPelajaran::factory()->create(['hari' => $hariIni]);

    $semesterLama = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Kelas Lama 2025']);
    $jadwalLama = JadwalPelajaran::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelasLama->id,
        'guru_id' => $guru->id,
        'jam_pelajaran_id' => $jamPelajaran->id,
        'semester_id' => $semesterLama->id,
    ]);

    $semesterAktif = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $kelasAktif = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Kelas Aktif 2026']);
    JadwalPelajaran::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelasAktif->id,
        'guru_id' => $guru->id,
        'jam_pelajaran_id' => $jamPelajaran->id,
        'semester_id' => $semesterAktif->id,
    ]);

    // Buktikan dulu jadwal lama benar-benar tersimpan sebelum assert exclusion.
    expect(JadwalPelajaran::where('id', $jadwalLama->id)->exists())->toBeTrue();

    $response = $this->actingAs($guruUser)->get('/dashboard');

    $response->assertOk();
    $response->assertViewHas('jadwalHariIni', function ($jadwalHariIni) use ($kelasAktif, $kelasLama) {
        return $jadwalHariIni->pluck('kelas_id')->contains($kelasAktif->id)
            && ! $jadwalHariIni->pluck('kelas_id')->contains($kelasLama->id);
    });
});

it('shows an empty today-schedule widget for a guru whose lembaga has no active semester', function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $lembaga = Lembaga::factory()->create();
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole('guru');
    $guru = Guru::factory()->create(['user_id' => $guruUser->id, 'lembaga_id' => $lembaga->id]);

    $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
    $jamPelajaran = JamPelajaran::factory()->create(['hari' => $hariIni]);
    $semesterTidakAktif = Semester::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    JadwalPelajaran::factory()->create([
        'lembaga_id' => $lembaga->id,
        'guru_id' => $guru->id,
        'jam_pelajaran_id' => $jamPelajaran->id,
        'semester_id' => $semesterTidakAktif->id,
    ]);

    $response = $this->actingAs($guruUser)->get('/dashboard');

    $response->assertOk();
    $response->assertViewHas('jadwalHariIni', fn ($jadwalHariIni) => $jadwalHariIni->isEmpty());
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="excludes jadwal pelajaran from a non-active semester" --compact`
Expected: FAIL — `jadwalHariIni` masih berisi `$kelasLama` karena belum ada filter semester aktif.

- [ ] **Step 3: Tambah query scope `semesterAktif` di model `JadwalPelajaran`**

Edit `app/Models/JadwalPelajaran.php` — tambah import dan method (setelah method `semester()`, sebelum penutup class):

```php
use Illuminate\Database\Eloquent\Builder;
```

```php
    /**
     * Filter ke jadwal yang semester-nya berstatus aktif. Semua consumer BARU
     * yang menampilkan jadwal "saat ini" (bukan laporan histori) WAJIB
     * memakai scope ini -- lihat riwayat bug widget "Jadwal Hari Ini" guru
     * yang bocor lintas tahun ajaran (audit 27 Agustus 2026).
     */
    public function scopeSemesterAktif(Builder $query): Builder
    {
        return $query->whereHas('semester', fn (Builder $q) => $q->where('status_aktif', true));
    }
```

- [ ] **Step 4: Terapkan scope di `DashboardController`**

Edit `app/Http/Controllers/Admin/DashboardController.php:51-56`, ubah dari:

```php
            $jadwalHariIni = $user->guru === null
                ? collect()
                : JadwalPelajaran::where('guru_id', $user->guru->id)
                    ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hariIni))
                    ->with(['kelas', 'mataPelajaran', 'jamPelajaran'])
                    ->get();
```

menjadi:

```php
            $jadwalHariIni = $user->guru === null
                ? collect()
                : JadwalPelajaran::where('guru_id', $user->guru->id)
                    ->semesterAktif()
                    ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hariIni))
                    ->with(['kelas', 'mataPelajaran', 'jamPelajaran'])
                    ->get();
```

(Tidak perlu import baru — `JadwalPelajaran` sudah di-import di baris 19.)

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="today-schedule widget" --compact`
Expected: PASS (2 test baru), dan pastikan test existing baris 128-138 (`'passes teaching schedule and progressKelasWali to the guru dashboard view'`) dan baris 194+ (`'shows an orang tua the latest recorded grade for their linked child'`, yang juga membuat `JadwalPelajaran` tanpa `semester_id` eksplisit — factory default `Semester::factory()` membuat semester baru dengan `status_aktif` default) TETAP PASS:

Run: `php artisan test --filter=DashboardTest --compact`
Expected: semua test di file PASS, 0 failed.

**PERINGATAN**: kalau test existing di baris 194+ gagal karena `Semester::factory()` default TIDAK membuat `status_aktif=true`, cek `database/factories/SemesterFactory.php` — kalau defaultnya `false`, test itu sendiri (bukan kode Task 1) yang perlu diperbaiki dengan menambahkan `'status_aktif' => true` eksplisit pada baris pembuatan `JadwalPelajaran`/`Semester` terkait test tsb (test itu memang harus mensimulasikan semester aktif, jadi ini perbaikan test yang sah, bukan penyimpangan dari plan — laporkan di report akhir task ini).

- [ ] **Step 6: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/JadwalPelajaran.php app/Http/Controllers/Admin/DashboardController.php tests/Feature/DashboardTest.php
git commit -m "fix(akademik): widget jadwal hari ini guru filter semester aktif"
```

---

### Task 2: Resync manual drift `kelas.kurikulum` / `kelas.fase_id`

**Files:**
- Create: `app/Domains/Akademik/Actions/Kelas/ResyncKurikulumFaseKelasAction.php`
- Create: `app/Http/Controllers/Admin/ResyncKurikulumFaseController.php`
- Create: `resources/views/admin/kurikulum-assignment/resync.blade.php`
- Modify: `routes/admin/akademik-master.php`
- Modify: `resources/views/admin/kurikulum-assignment/index.blade.php` (tambah link ke halaman resync)
- Test: `tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php` (baru)

**Interfaces:**
- Consumes: `KurikulumAssignmentResolver::resolve(int $tahunAjaranId, string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): KurikulumFramework` (throws `KurikulumAssignmentNotFoundException`), `FaseDefaultResolver::resolve(string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): ?Fase` — keduanya sudah ada, tidak diubah.
- Produces: `ResyncKurikulumFaseKelasAction::hitungDiff(int $lembagaId, int $tahunAjaranId): array<int, array{kelas: Kelas, kurikulumLama: ?string, kurikulumBaru: ?string, faseLamaId: ?int, faseBaruId: ?int, faseBaruNama: ?string}>` dan `ResyncKurikulumFaseKelasAction::terapkan(array $kelasIds): void`.

- [ ] **Step 1: Tulis test yang gagal — hitungDiff mendeteksi kelas yang driftnya sudah tidak sesuai**

Buat `tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php`:

```php
<?php

use App\Domains\Akademik\Actions\Kelas\ResyncKurikulumFaseKelasAction;
use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanResyncFixture(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    return [$lembaga, $ta];
}

it('detects a kelas whose stored kurikulum no longer matches the live assignment', function () {
    [$lembaga, $ta] = siapkanResyncFixture();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    $kelas = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13',
    ]);

    // Admin mengoreksi assignment SETELAH kelas dibuat -- ini skenario drift-nya.
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $action = app(ResyncKurikulumFaseKelasAction::class);
    $diff = $action->hitungDiff($lembaga->id, $ta->id);

    expect($diff)->toHaveCount(1);
    expect($diff[0]['kelas']->id)->toBe($kelas->id);
    expect($diff[0]['kurikulumLama'])->toBe('k13');
    expect($diff[0]['kurikulumBaru'])->toBe('merdeka');
});

it('excludes a kelas whose stored kurikulum already matches the live assignment', function () {
    [$lembaga, $ta] = siapkanResyncFixture();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'merdeka',
    ]);
    Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'merdeka',
    ]);

    $action = app(ResyncKurikulumFaseKelasAction::class);
    $diff = $action->hitungDiff($lembaga->id, $ta->id);

    expect($diff)->toBeEmpty();
});

it('excludes a kelas whose assignment cannot be resolved at all', function () {
    [$lembaga, $ta] = siapkanResyncFixture();
    // TIDAK ADA KurikulumAssignment sama sekali untuk kombinasi ini.
    Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13',
    ]);

    $action = app(ResyncKurikulumFaseKelasAction::class);
    $diff = $action->hitungDiff($lembaga->id, $ta->id);

    expect($diff)->toBeEmpty();
});

it('does not include kelas from a different lembaga in the diff', function () {
    [$lembaga, $ta] = siapkanResyncFixture();
    [$lembagaLain, $taLain] = siapkanResyncFixture();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $taLain->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13']);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $taLain->id, 'tingkat' => '1', 'kurikulum' => 'k13']);

    KurikulumAssignment::where('tahun_ajaran_id', $taLain->id)->first()->update(['kurikulum' => 'merdeka']);

    $action = app(ResyncKurikulumFaseKelasAction::class);
    $diff = $action->hitungDiff($lembaga->id, $ta->id);

    expect($diff)->toBeEmpty();
    expect(Kelas::find($kelasLain->id)->kurikulum->value)->toBe('k13'); // belum di-resync, cuma bukti data lembaga lain tak tersentuh
});

it('applies resync only to the selected kelas ids, recomputing values on the server', function () {
    [$lembaga, $ta] = siapkanResyncFixture();
    $fase = Fase::factory()->create();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13', 'fase_id' => null]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '2', 'kurikulum' => 'k13', 'fase_id' => null]);

    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $action = app(ResyncKurikulumFaseKelasAction::class);
    $action->terapkan([$kelasA->id]);

    expect($kelasA->fresh()->kurikulum->value)->toBe('merdeka');
    expect($kelasB->fresh()->kurikulum->value)->toBe('k13'); // tidak dicentang, tidak berubah
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php --compact`
Expected: FAIL — class `ResyncKurikulumFaseKelasAction` belum ada.

- [ ] **Step 3: Buat Action**

Buat `app/Domains/Akademik/Actions/Kelas/ResyncKurikulumFaseKelasAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kelas;

use App\Domains\Akademik\Exceptions\KurikulumAssignmentNotFoundException;
use App\Domains\Akademik\Services\FaseDefaultResolver;
use App\Domains\Akademik\Services\KurikulumAssignmentResolver;
use App\Models\Kelas;
use App\Models\Lembaga;
use Illuminate\Support\Facades\DB;

final class ResyncKurikulumFaseKelasAction
{
    public function __construct(
        private readonly KurikulumAssignmentResolver $kurikulumResolver,
        private readonly FaseDefaultResolver $faseResolver,
    ) {}

    /**
     * @return array<int, array{kelas: Kelas, kurikulumLama: ?string, kurikulumBaru: ?string, faseLamaId: ?int, faseBaruId: ?int, faseBaruNama: ?string}>
     */
    public function hitungDiff(int $lembagaId, int $tahunAjaranId): array
    {
        $lembaga = Lembaga::findOrFail($lembagaId);
        $kelasList = Kelas::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $tahunAjaranId)->get();

        $diff = [];

        foreach ($kelasList as $kelas) {
            try {
                $kurikulumBaru = $this->kurikulumResolver->resolve(
                    tahunAjaranId: $tahunAjaranId,
                    bentukPendidikan: $lembaga->bentuk_pendidikan,
                    tingkat: $kelas->tingkat,
                    lembagaId: $lembagaId,
                );
            } catch (KurikulumAssignmentNotFoundException) {
                continue;
            }

            $faseBaru = $this->faseResolver->resolve(
                bentukPendidikan: $lembaga->bentuk_pendidikan,
                tingkat: $kelas->tingkat,
                lembagaId: $lembagaId,
            );

            $kurikulumLamaValue = $kelas->kurikulum?->value;
            $kurikulumBaruValue = $kurikulumBaru->value;
            $faseLamaId = $kelas->fase_id;
            $faseBaruId = $faseBaru?->id;

            if ($kurikulumLamaValue === $kurikulumBaruValue && $faseLamaId === $faseBaruId) {
                continue;
            }

            $diff[] = [
                'kelas' => $kelas,
                'kurikulumLama' => $kurikulumLamaValue,
                'kurikulumBaru' => $kurikulumBaruValue,
                'faseLamaId' => $faseLamaId,
                'faseBaruId' => $faseBaruId,
                'faseBaruNama' => $faseBaru?->nama,
            ];
        }

        return $diff;
    }

    /**
     * @param  array<int, int>  $kelasIds
     */
    public function terapkan(array $kelasIds): void
    {
        DB::transaction(function () use ($kelasIds) {
            foreach (Kelas::whereIn('id', $kelasIds)->get() as $kelas) {
                $lembaga = Lembaga::findOrFail($kelas->lembaga_id);

                try {
                    $kurikulumBaru = $this->kurikulumResolver->resolve(
                        tahunAjaranId: $kelas->tahun_ajaran_id,
                        bentukPendidikan: $lembaga->bentuk_pendidikan,
                        tingkat: $kelas->tingkat,
                        lembagaId: $kelas->lembaga_id,
                    );
                } catch (KurikulumAssignmentNotFoundException) {
                    continue;
                }

                $faseBaru = $this->faseResolver->resolve(
                    bentukPendidikan: $lembaga->bentuk_pendidikan,
                    tingkat: $kelas->tingkat,
                    lembagaId: $kelas->lembaga_id,
                );

                $kelas->update([
                    'kurikulum' => $kurikulumBaru,
                    'fase_id' => $faseBaru?->id,
                ]);
            }
        });
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php --compact`
Expected: PASS, 5/5 test.

- [ ] **Step 5: Tulis test controller (route + otorisasi + tampilan diff)**

Tambahkan di file test yang sama, sebelum penutup (atau file baru `tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php` — pakai file baru ini supaya lebih jelas terpisah dari test Action):

```php
<?php

use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanResyncControllerUser(): array
{
    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.edit'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'operator_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.edit']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return [$manager, $lembaga, $ta];
}

it('shows the diff table for kelas whose kurikulum drifted from the live assignment', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.resync', [
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id,
    ]));

    $response->assertOk();
    $response->assertSee($kelas->nama);
});

it('applies resync via POST and redirects with success status', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();

    KurikulumAssignment::create([
        'lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => null, 'kurikulum' => 'k13',
    ]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id, 'tingkat' => '1', 'kurikulum' => 'k13']);
    KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()->update(['kurikulum' => 'merdeka']);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.resync.apply'), [
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'kelas_ids' => [$kelas->id],
    ])->assertRedirect(route('admin.kurikulum-assignment.resync', ['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $ta->id]));

    expect($kelas->fresh()->kurikulum->value)->toBe('merdeka');
});

it('rejects resync for a kelas belonging to a different lembaga (cross-tenant guard)', function () {
    [$manager, $lembaga, $ta] = siapkanResyncControllerUser();
    $lembagaLain = Lembaga::factory()->create();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id])->id, 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.resync.apply'), [
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $ta->id,
        'kelas_ids' => [$kelasLain->id],
    ])->assertForbidden();

    expect($kelasLain->fresh()->kurikulum->value)->toBe('k13');
});
```

- [ ] **Step 6: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php --compact`
Expected: FAIL — route `admin.kurikulum-assignment.resync` belum ada.

- [ ] **Step 7: Buat Controller**

Buat `app/Http/Controllers/Admin/ResyncKurikulumFaseController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Actions\Kelas\ResyncKurikulumFaseKelasAction;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class ResyncKurikulumFaseController extends BaseController
{
    use AuthorizesRequests;

    public function __construct(private readonly ResyncKurikulumFaseKelasAction $action) {}

    public function index(Request $request): View
    {
        $this->authorize('kurikulum-assignment.view');

        $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
        $lembagaId = $request->query('lembaga_id') !== null ? (int) $request->query('lembaga_id') : ($isPlatformOrYayasan ? null : $request->user()->lembaga_id);
        $tahunAjaranId = $request->query('tahun_ajaran_id') !== null ? (int) $request->query('tahun_ajaran_id') : null;

        $diff = [];
        if ($lembagaId !== null && $tahunAjaranId !== null) {
            $this->authorizeScope($request, $lembagaId);
            $diff = $this->action->hitungDiff($lembagaId, $tahunAjaranId);
        }

        return view('admin.kurikulum-assignment.resync', [
            'lembagaList' => $isPlatformOrYayasan ? Lembaga::orderBy('nama')->get() : collect([$request->user()->lembaga]),
            'tahunAjaranList' => $lembagaId !== null ? TahunAjaran::where('lembaga_id', $lembagaId)->orderByDesc('tanggal_mulai')->get() : collect(),
            'lembagaId' => $lembagaId,
            'tahunAjaranId' => $tahunAjaranId,
            'diff' => $diff,
            'isPlatformOrYayasan' => $isPlatformOrYayasan,
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.edit');

        $validated = $request->validate([
            'lembaga_id' => ['required', 'integer', 'exists:lembaga,id'],
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajaran,id'],
            'kelas_ids' => ['required', 'array', 'min:1'],
            'kelas_ids.*' => ['integer'],
        ]);

        $this->authorizeScope($request, (int) $validated['lembaga_id']);

        $kelasMilikLembaga = Kelas::where('lembaga_id', $validated['lembaga_id'])
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
            ->whereIn('id', $validated['kelas_ids'])
            ->pluck('id');

        abort_unless($kelasMilikLembaga->count() === count($validated['kelas_ids']), 403);

        $this->action->terapkan($kelasMilikLembaga->all());

        return redirect()
            ->route('admin.kurikulum-assignment.resync', ['lembaga_id' => $validated['lembaga_id'], 'tahun_ajaran_id' => $validated['tahun_ajaran_id']])
            ->with('status', 'Kurikulum/fase kelas terpilih berhasil disinkronkan.');
    }

    private function isPlatformOrYayasan(Request $request): bool
    {
        return in_array($request->user()->widestScopeLevel(), ['platform', 'yayasan'], true);
    }

    private function authorizeScope(Request $request, int $lembagaId): void
    {
        abort_unless($this->isPlatformOrYayasan($request) || $lembagaId === $request->user()->lembaga_id, 403);
    }
}
```

- [ ] **Step 8: Tambah route**

Edit `routes/admin/akademik-master.php`, tambahkan setelah blok `kurikulum-assignment.*` (setelah baris `destroy`):

```php
Route::get('kurikulum-assignment/resync', [ResyncKurikulumFaseController::class, 'index'])->name('kurikulum-assignment.resync');
Route::post('kurikulum-assignment/resync', [ResyncKurikulumFaseController::class, 'apply'])->name('kurikulum-assignment.resync.apply');
```

Tambahkan import di bagian atas file: `use App\Http\Controllers\Admin\ResyncKurikulumFaseController;`

- [ ] **Step 9: Buat view resync**

Buat `resources/views/admin/kurikulum-assignment/resync.blade.php`:

```blade
<x-app-layout>
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <div>
            <h1 class="font-display text-lg font-bold text-gray-900">Cek & Perbaiki Kurikulum/Fase Kelas</h1>
            <p class="text-xs text-gray-500">Alat koreksi manual untuk kelas yang kurikulum/fase tersimpannya sudah tidak sesuai dengan assignment terbaru. Tidak ada yang berubah otomatis -- pilih kelas yang mau disinkronkan.</p>
        </div>

        <form method="GET" action="{{ route('admin.kurikulum-assignment.resync') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            @if ($isPlatformOrYayasan)
                <div>
                    <label class="block text-xs font-semibold text-gray-700">Lembaga</label>
                    <select name="lembaga_id" class="mt-1 rounded-lg border-gray-200 text-sm" onchange="this.form.submit()">
                        <option value="">— Pilih Lembaga —</option>
                        @foreach ($lembagaList as $l)
                            <option value="{{ $l->id }}" @selected($lembagaId === $l->id)>{{ $l->nama }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="block text-xs font-semibold text-gray-700">Tahun Ajaran</label>
                <select name="tahun_ajaran_id" class="mt-1 rounded-lg border-gray-200 text-sm">
                    <option value="">— Pilih Tahun Ajaran —</option>
                    @foreach ($tahunAjaranList as $ta)
                        <option value="{{ $ta->id }}" @selected($tahunAjaranId === $ta->id)>{{ $ta->nama }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Cek Drift</button>
        </form>

        @if ($lembagaId !== null && $tahunAjaranId !== null)
            <form method="POST" action="{{ route('admin.kurikulum-assignment.resync.apply') }}" class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                @csrf
                <input type="hidden" name="lembaga_id" value="{{ $lembagaId }}">
                <input type="hidden" name="tahun_ajaran_id" value="{{ $tahunAjaranId }}">

                @if (empty($diff))
                    <p class="p-6 text-sm text-gray-500">Tidak ada kelas yang perlu disinkronkan -- semua kelas di kombinasi ini sudah sesuai dengan assignment terbaru.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600"><input type="checkbox" onclick="document.querySelectorAll('.resync-row').forEach(c => c.checked = this.checked)"></th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kelas</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kurikulum: Lama → Seharusnya</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Fase: Lama → Seharusnya</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($diff as $row)
                                <tr>
                                    <td class="px-4 py-3"><input type="checkbox" name="kelas_ids[]" value="{{ $row['kelas']->id }}" class="resync-row"></td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['kelas']->nama }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $row['kurikulumLama'] ?? '-' }} → {{ $row['kurikulumBaru'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $row['faseLamaId'] ?? '-' }} → {{ $row['faseBaruNama'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="p-4">
                        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Sinkronkan yang Dicentang</button>
                    </div>
                @endif
            </form>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 10: Tambah link dari halaman index kurikulum-assignment**

Edit `resources/views/admin/kurikulum-assignment/index.blade.php`, tambahkan link baru di sebelah tombol "Tambah Assignment" (baris 12-17):

```blade
            @can('kurikulum-assignment.view')
                <a href="{{ route('admin.kurikulum-assignment.resync') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Cek & Perbaiki Kurikulum/Fase
                </a>
            @endcan
```

- [ ] **Step 11: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/ResyncKurikulumFase --compact`
Expected: PASS, semua test (Action + Controller) lulus.

- [ ] **Step 12: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Actions/Kelas/ResyncKurikulumFaseKelasAction.php app/Http/Controllers/Admin/ResyncKurikulumFaseController.php resources/views/admin/kurikulum-assignment/resync.blade.php resources/views/admin/kurikulum-assignment/index.blade.php routes/admin/akademik-master.php tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php
git commit -m "feat(akademik): aksi resync manual drift kurikulum/fase kelas"
```

---

### Task 3: Validasi ekskul di rapor — dropdown dari master data

**Files:**
- Modify: `app/Http/Controllers/Guru/RaporController.php:122-153` (method `edit`)
- Modify: `app/Http/Requests/Akademik/StoreCatatanWaliKelasRequest.php`
- Modify: `resources/views/portals/guru/rapor/catatan/edit.blade.php:60-72`
- Test: `tests/Feature/Akademik/CatatanWaliKelasEkstrakurikulerValidationTest.php` (baru)

**Interfaces:**
- Consumes: `App\Models\EkstrakurikulerLembaga` (existing model, `nama_ekskul` fillable field).
- Produces: view `portals.guru.rapor.catatan.edit` menerima variabel baru `ekskulOptions` (Collection<string>).

- [ ] **Step 1: Tulis test yang gagal — submit nama ekskul tak terdaftar harus ditolak**

Buat `tests/Feature/Akademik/CatatanWaliKelasEkstrakurikulerValidationTest.php`:

```php
<?php

use App\Models\EkstrakurikulerLembaga;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanWaliKelasUser(): array
{
    Permission::firstOrCreate(['name' => 'rapor.input-wali', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'wali_kelas', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo('rapor.input-wali');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruUser->assignRole($role);
    $guru = Guru::factory()->create(['user_id' => $guruUser->id, 'lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'wali_kelas_guru_id' => $guru->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $semester = Semester::factory()->create(['lembaga_id' => $lembaga->id]);

    return [$guruUser, $lembaga, $siswa, $semester];
}

it('shows ekskul options from the lembaga master data on the catatan wali kelas form', function () {
    [$guruUser, $lembaga, $siswa, $semester] = siapkanWaliKelasUser();
    EkstrakurikulerLembaga::create(['lembaga_id' => $lembaga->id, 'jenis_ekskul' => 'wajib', 'nama_ekskul' => 'Pramuka']);
    EkstrakurikulerLembaga::create(['lembaga_id' => $lembaga->id, 'jenis_ekskul' => 'pilihan', 'nama_ekskul' => 'Futsal']);

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.edit', ['siswa' => $siswa->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertSee('Pramuka');
    $response->assertSee('Futsal');
});

it('saves catatan wali kelas when the ekskul name matches the lembaga master data', function () {
    [$guruUser, $lembaga, $siswa, $semester] = siapkanWaliKelasUser();
    EkstrakurikulerLembaga::create(['lembaga_id' => $lembaga->id, 'jenis_ekskul' => 'wajib', 'nama_ekskul' => 'Pramuka']);

    $this->actingAs($guruUser)->put(route('guru.rapor.catatan.update', $siswa), [
        'semester_id' => $semester->id,
        'ekstrakurikuler' => [['nama' => 'Pramuka', 'peran' => 'Anggota']],
    ])->assertRedirect();

    expect(\App\Domains\Akademik\Models\CatatanWaliKelas::where('siswa_id', $siswa->id)->first()->ekstrakurikuler)
        ->toBe([['nama' => 'Pramuka', 'peran' => 'Anggota']]);
});

it('rejects an ekskul name that is not registered in the lembaga master data', function () {
    [$guruUser, $lembaga, $siswa, $semester] = siapkanWaliKelasUser();
    EkstrakurikulerLembaga::create(['lembaga_id' => $lembaga->id, 'jenis_ekskul' => 'wajib', 'nama_ekskul' => 'Pramuka']);

    $this->actingAs($guruUser)->put(route('guru.rapor.catatan.update', $siswa), [
        'semester_id' => $semester->id,
        'ekstrakurikuler' => [['nama' => 'Ekskul Fiktif Tidak Terdaftar', 'peran' => 'Anggota']],
    ])->assertSessionHasErrors('ekstrakurikuler.0.nama');

    expect(\App\Domains\Akademik\Models\CatatanWaliKelas::where('siswa_id', $siswa->id)->exists())->toBeFalse();
});

it('rejects an ekskul name that belongs to a different lembaga (tenant isolation)', function () {
    [$guruUser, $lembaga, $siswa, $semester] = siapkanWaliKelasUser();
    $lembagaLain = Lembaga::factory()->create();
    EkstrakurikulerLembaga::create(['lembaga_id' => $lembagaLain->id, 'jenis_ekskul' => 'wajib', 'nama_ekskul' => 'Ekskul Lembaga Lain']);

    $this->actingAs($guruUser)->put(route('guru.rapor.catatan.update', $siswa), [
        'semester_id' => $semester->id,
        'ekstrakurikuler' => [['nama' => 'Ekskul Lembaga Lain', 'peran' => 'Anggota']],
    ])->assertSessionHasErrors('ekstrakurikuler.0.nama');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Akademik/CatatanWaliKelasEkstrakurikulerValidationTest.php --compact`
Expected: FAIL — 3 dari 4 test gagal (form belum kirim `ekskulOptions`, validasi belum ada).

- [ ] **Step 3: Update `StoreCatatanWaliKelasRequest`**

Edit `app/Http/Requests/Akademik/StoreCatatanWaliKelasRequest.php`. Tambah import:

```php
use App\Models\EkstrakurikulerLembaga;
use Illuminate\Validation\Rule;
```

Ubah baris `'ekstrakurikuler.*.nama' => ['required_with:ekstrakurikuler', 'string', 'max:255'],` menjadi:

```php
            'ekstrakurikuler.*.nama' => [
                'required_with:ekstrakurikuler',
                Rule::in($this->ekskulOptionsUntukSiswa()),
            ],
```

Tambah method baru di akhir class (sebelum `}` penutup), sebelum method `toDTO`:

```php
    /**
     * @return array<int, string>
     */
    private function ekskulOptionsUntukSiswa(): array
    {
        $siswa = $this->route('siswa');

        if ($siswa === null) {
            return [];
        }

        return EkstrakurikulerLembaga::where('lembaga_id', $siswa->lembaga_id)->pluck('nama_ekskul')->all();
    }
```

- [ ] **Step 4: Update `Guru\RaporController::edit()`**

Edit `app/Http/Controllers/Guru/RaporController.php`. Tambah import: `use App\Models\EkstrakurikulerLembaga;`

Ubah blok `return view(...)` di method `edit()` (baris 145-153) dari:

```php
        return view('portals.guru.rapor.catatan.edit', [
            'siswa' => $siswa,
            'semester' => $semester,
            'catatan' => $catatan,
            'siswaSebelumnya' => $siswaSebelumnya,
            'siswaBerikutnya' => $siswaBerikutnya,
            'tampilkanAntropometri' => in_array($bentukPendidikan, self::JENJANG_ANTROPOMETRI, true),
            'tampilkanPklInfo' => $bentukPendidikan === 'SMK',
        ]);
```

menjadi:

```php
        return view('portals.guru.rapor.catatan.edit', [
            'siswa' => $siswa,
            'semester' => $semester,
            'catatan' => $catatan,
            'siswaSebelumnya' => $siswaSebelumnya,
            'siswaBerikutnya' => $siswaBerikutnya,
            'tampilkanAntropometri' => in_array($bentukPendidikan, self::JENJANG_ANTROPOMETRI, true),
            'tampilkanPklInfo' => $bentukPendidikan === 'SMK',
            'ekskulOptions' => EkstrakurikulerLembaga::where('lembaga_id', $siswa->lembaga_id)->orderBy('nama_ekskul')->pluck('nama_ekskul'),
        ]);
```

- [ ] **Step 5: Update view — ganti input teks jadi select**

Edit `resources/views/portals/guru/rapor/catatan/edit.blade.php:67`, ubah dari:

```blade
                        <input type="text" :name="`ekstrakurikuler[${index}][nama]`" x-model="row.nama" placeholder="Nama kegiatan" class="sm:col-span-2 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
```

menjadi:

```blade
                        <select :name="`ekstrakurikuler[${index}][nama]`" x-model="row.nama" class="sm:col-span-2 rounded-lg border-gray-200 text-xs focus:border-brand-500 focus:ring-brand-500">
                            <option value="">— Pilih Ekskul —</option>
                            @foreach ($ekskulOptions as $namaEkskul)
                                <option value="{{ $namaEkskul }}">{{ $namaEkskul }}</option>
                            @endforeach
                            @if ($catatan->ekstrakurikuler)
                                @foreach (collect($catatan->ekstrakurikuler)->pluck('nama')->diff($ekskulOptions)->filter() as $namaLama)
                                    <option value="{{ $namaLama }}">{{ $namaLama }} (tidak terdaftar lagi)</option>
                                @endforeach
                            @endif
                        </select>
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/CatatanWaliKelasEkstrakurikulerValidationTest.php --compact`
Expected: PASS, 4/4 test.

- [ ] **Step 7: Jalankan full test suite (WAJIB tanpa filter — ini adalah task terakhir Kelompok A)**

Run: `php artisan test --compact`
Expected: 0 failed. Catat angka pasti (passed/skipped/assertions) di laporan akhir.

**PERHATIAN**: kalau ada test existing lain yang memakai `StoreCatatanWaliKelasRequest`/form catatan wali kelas dengan nama ekskul bebas (bukan dari master data) dan jadi gagal, JANGAN diam-diam melonggarkan validasi baru — perbaiki test tsb supaya memakai `EkstrakurikulerLembaga::create(...)` dulu untuk data yang valid, konsisten dengan pola existence-then-exclusion proyek ini.

- [ ] **Step 8: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Guru/RaporController.php app/Http/Requests/Akademik/StoreCatatanWaliKelasRequest.php resources/views/portals/guru/rapor/catatan/edit.blade.php tests/Feature/Akademik/CatatanWaliKelasEkstrakurikulerValidationTest.php
git commit -m "fix(akademik): validasi nama ekskul di catatan wali kelas terhadap master data lembaga"
```

- [ ] **Step 9: Catat penyelesaian Kelompok A di PETA_PENGEMBANGAN.md**

Baca dulu bagian existing terkait audit sistematis tahap 2 (dicatat sebelumnya sbg temuan 10 poin), tambahkan catatan bahwa Kelompok A (poin #1-3: widget jadwal, drift kurikulum/fase, validasi ekskul) SELESAI dengan tanggal hari ini, dan Kelompok B (Kenaikan Kelas UX) + Kelompok C (RPP reporting + test coverage) masih menyusul terpisah.

```bash
git add PETA_PENGEMBANGAN.md
git commit -m "docs: catat penyelesaian Kelompok A audit sistematis tahap 2 akademik"
```
