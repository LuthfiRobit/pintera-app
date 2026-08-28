# Fix: lembaga_id Basi pada Update Komponen Penilaian — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `UpdateKomponenPenilaianAction` harus menghitung ulang `lembaga_id` dari semester baru setiap kali `semester_id` berubah, mirror pola yang sudah benar di `CreateKomponenPenilaianAction`, agar baris `komponen_penilaian` tidak pernah berakhir dengan `lembaga_id` yang tidak konsisten dengan `semester_id`-nya sendiri.

**Architecture:** Tambah satu baris derivasi `lembaga_id = Semester::findOrFail($data->semesterId)->lembaga_id` di dalam blok `if (! $dipakai && ...)` yang sudah ada di `UpdateKomponenPenilaianAction::execute()` — satu-satunya jalur kode di mana `semester_id` bisa berubah. Tidak ada perubahan skema, tidak ada guard baru di controller.

**Tech Stack:** Laravel 12, PHP 8.3, Pest v4.

## Global Constraints

- Fix HANYA mengubah `UpdateKomponenPenilaianAction.php` (+1 baris derivasi lembaga_id, +1 import `App\Models\Semester`). Tidak menyentuh `CreateKomponenPenilaianAction.php`, tidak menyentuh guard existing di `KomponenPenilaianController::update()` (baris 166-176), tidak ada migration.
- 4 test update existing di `tests/Feature/Admin/KomponenPenilaianCrudTest.php` (baris 254-352) WAJIB tetap PASS tanpa modifikasi assertion apa pun.
- Test reproduksi bug WAJIB pakai aktor `scope_level: 'yayasan'` (bukan `'lembaga'`) dengan `session('active_lembaga_id')` TIDAK di-set (mode "Semua Lembaga") — aktor lembaga-scoped biasa TIDAK BISA mereproduksi bug ini karena `TenantScope` sudah membuat `Semester::find()` mengembalikan `null` untuk semester lembaga lain, sehingga guard `abort_if($semester === null, 404)` di controller sudah menutup celah itu lebih dulu untuk mereka.
- Test reproduksi HARUS mencakup KEDUA `subjek_type`: `elemen_cp` dan `mata_pelajaran`.
- Hanya jalankan test scoped: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`. TIDAK PERLU full suite untuk fix sekecil ini.

---

### Task 1: Fix `UpdateKomponenPenilaianAction` + Test Regresi & Reproduksi

**Files:**
- Modify: `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`
- Modify: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\Semester::findOrFail(int $id): Semester` (sudah dipakai identik di `CreateKomponenPenilaianAction.php:44`). `App\Domains\Akademik\DataTransferObjects\UpdateKomponenPenilaianData` sudah ada dengan properti `?string $subjekType`, `?int $subjekId`, `?int $semesterId`, `?string $assessmentType`, `?float $bobot`, `string $kode`, `?string $deskripsi`, `?string $kktp`, `?float $kktpMinimal` — tidak berubah.
- Produces: `UpdateKomponenPenilaianAction::execute()` tetap `(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian` — signature tidak berubah, hanya efek sampingnya (kini juga menulis `lembaga_id`) yang bertambah.

- [ ] **Step 1: Baca baseline file yang akan diubah**

File `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php` saat ini (baseline, JANGAN diasumsikan — baca ulang file di repo sebelum edit untuk memastikan tidak ada drift):

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\UpdateKomponenPenilaianData;
use App\Domains\Akademik\Models\KomponenPenilaian;
use Illuminate\Validation\ValidationException;

final class UpdateKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian
    {
        $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

        if (! $dipakai && $data->subjekType !== null && $data->subjekId !== null && $data->semesterId !== null) {
            $komponen->subjek_type = $data->subjekType;
            $komponen->subjek_id = $data->subjekId;
            $komponen->semester_id = $data->semesterId;
            if ($data->assessmentType !== null) {
                $komponen->assessment_type = $data->assessmentType;
            }
        }

        $newBobot = $data->bobot ?? $komponen->bobot;
        $existingSum = KomponenPenilaian::where('subjek_type', $komponen->subjek_type)
            ->where('subjek_id', $komponen->subjek_id)
            ->where('semester_id', $komponen->semester_id)
            ->where('id', '!=', $komponen->id)
            ->sum('bobot');

        if (($existingSum + $newBobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            throw ValidationException::withMessages([
                'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk subjek ini adalah {$remaining}%.",
            ]);
        }

        $komponen->kode = $data->kode;
        $komponen->deskripsi = $data->deskripsi;
        $komponen->bobot = $newBobot;
        $komponen->kktp = $data->kktp;
        $komponen->kktp_minimal = $data->kktpMinimal;
        $komponen->save();

        return $komponen;
    }
}
```

Jika file di repo berbeda dari baseline ini, STOP dan laporkan sebelum melanjutkan.

- [ ] **Step 2: Tulis test yang gagal (regresi negatif dulu, paling mudah dibuktikan)**

Tambahkan helper `actingAsYayasanKomponenManager()` dan 3 test baru di akhir `tests/Feature/Admin/KomponenPenilaianCrudTest.php` (setelah test terakhir, baris 575). Helper mengikuti pola `actingAsPlatformKurikulumManager()` di `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php:113-125`: aktor `scope_level: 'yayasan'`, `lembaga_id` NULL, `yayasan_id` terisi, TANPA `session('active_lembaga_id')` (mode "Semua Lembaga" pada `TenantScope`).

```php
function actingAsYayasanKomponenManager(Yayasan $yayasan): User
{
    Permission::firstOrCreate(['name' => 'komponen-penilaian.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_admin_komponen', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['komponen-penilaian.kelola']);

    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $manager->assignRole($role);

    return $manager;
}

it('recomputes lembaga_id to follow the new semester for elemen_cp when a yayasan actor moves it across lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $elemenCp = \App\Domains\Akademik\Models\ElemenCp::factory()->create();

    $createAction = app(\App\Domains\Akademik\Actions\Penilaian\CreateKomponenPenilaianAction::class);
    $komponen = $createAction->execute(new \App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData(
        subjekType: 'elemen_cp',
        subjekId: $elemenCp->id,
        semesterId: $semesterA->id,
        kode: 'ECP-1',
        deskripsi: 'Deskripsi awal',
        bobot: 100.0,
        kktp: null,
        kktpMinimal: null,
        assessmentType: null,
    ));
    expect($komponen->lembaga_id)->toBe($lembagaA->id);

    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => $elemenCp->id,
        'semester_id' => $semesterB->id,
        'kode' => 'ECP-1',
        'deskripsi' => 'Deskripsi awal',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->semester_id)->toBe($semesterB->id);
    expect($komponen->lembaga_id)->toBe($lembagaB->id);
});

it('recomputes lembaga_id to follow the new semester for mata_pelajaran when a yayasan actor moves it across lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $mapelB = MataPelajaran::factory()->create(['lembaga_id' => $lembagaB->id]);

    $createAction = app(\App\Domains\Akademik\Actions\Penilaian\CreateKomponenPenilaianAction::class);
    $komponen = $createAction->execute(new \App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData(
        subjekType: 'mata_pelajaran',
        subjekId: $mapelA->id,
        semesterId: $semesterA->id,
        kode: 'MP-1',
        deskripsi: 'Deskripsi awal',
        bobot: 100.0,
        kktp: null,
        kktpMinimal: null,
        assessmentType: null,
    ));
    expect($komponen->lembaga_id)->toBe($lembagaA->id);

    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapelB->id,
        'semester_id' => $semesterB->id,
        'kode' => 'MP-1',
        'deskripsi' => 'Deskripsi awal',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->subjek_id)->toBe($mapelB->id);
    expect($komponen->semester_id)->toBe($semesterB->id);
    expect($komponen->lembaga_id)->toBe($lembagaB->id);
});

it('does not touch lembaga_id when updating a komponen without changing semester_id', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $createAction = app(\App\Domains\Akademik\Actions\Penilaian\CreateKomponenPenilaianAction::class);
    $komponen = $createAction->execute(new \App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData(
        subjekType: 'mata_pelajaran',
        subjekId: $mapel->id,
        semesterId: $semester->id,
        kode: 'MP-STABIL',
        deskripsi: 'Deskripsi awal',
        bobot: 50.0,
        kktp: null,
        kktpMinimal: null,
        assessmentType: null,
    ));
    $lembagaIdSebelum = $komponen->lembaga_id;

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'deskripsi' => 'Deskripsi diubah tanpa ganti semester',
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->deskripsi)->toBe('Deskripsi diubah tanpa ganti semester');
    expect($komponen->lembaga_id)->toBe($lembagaIdSebelum);
});
```

**PENTING — constructor `KomponenPenilaianData` aktual** (`app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`) berbeda dari draf di atas:

```php
public function __construct(
    public string $subjekType,
    public int $subjekId,
    public int $semesterId,
    public ?string $kode,
    public string $deskripsi,
    public int $bobot,          // int, BUKAN float
    public ?string $kktp,
    public ?int $kktpMinimal,   // int, BUKAN float
    public ?string $assessmentType,
) {}
```

Di ketiga pemanggilan `new \App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData(...)` pada Step 2, ganti `bobot: 100.0` → `bobot: 100`, `bobot: 50.0` → `bobot: 50`, dan `kktpMinimal: null` tetap `null` (sudah benar). Gunakan named arguments persis seperti signature di atas.

- [ ] **Step 3: Jalankan test untuk memastikan 2 test reproduksi GAGAL (bug masih ada)**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --filter="recomputes lembaga_id" --compact`
Expected: FAIL — `expect($komponen->lembaga_id)->toBe($lembagaB->id)` gagal karena `lembaga_id` aktual masih `$lembagaA->id`.

Run juga test regresi negatif untuk memastikan itu PASS dari awal (karena `semester_id` tidak berubah, tidak ada perubahan perilaku yang diharapkan):
Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --filter="does not touch lembaga_id" --compact`
Expected: PASS (ini bukan bukti bug, hanya baseline aman).

- [ ] **Step 4: Implementasi minimal fix**

Edit `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\UpdateKomponenPenilaianData;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Models\Semester;
use Illuminate\Validation\ValidationException;

final class UpdateKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian
    {
        $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

        if (! $dipakai && $data->subjekType !== null && $data->subjekId !== null && $data->semesterId !== null) {
            $komponen->subjek_type = $data->subjekType;
            $komponen->subjek_id = $data->subjekId;
            $komponen->semester_id = $data->semesterId;
            $komponen->lembaga_id = Semester::findOrFail($data->semesterId)->lembaga_id;
            if ($data->assessmentType !== null) {
                $komponen->assessment_type = $data->assessmentType;
            }
        }

        $newBobot = $data->bobot ?? $komponen->bobot;
        $existingSum = KomponenPenilaian::where('subjek_type', $komponen->subjek_type)
            ->where('subjek_id', $komponen->subjek_id)
            ->where('semester_id', $komponen->semester_id)
            ->where('id', '!=', $komponen->id)
            ->sum('bobot');

        if (($existingSum + $newBobot) > 100) {
            $remaining = max(0, 100 - $existingSum);
            throw ValidationException::withMessages([
                'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk subjek ini adalah {$remaining}%.",
            ]);
        }

        $komponen->kode = $data->kode;
        $komponen->deskripsi = $data->deskripsi;
        $komponen->bobot = $newBobot;
        $komponen->kktp = $data->kktp;
        $komponen->kktp_minimal = $data->kktpMinimal;
        $komponen->save();

        return $komponen;
    }
}
```

Satu-satunya perubahan dari baseline: `use App\Models\Semester;` ditambah, dan baris `$komponen->lembaga_id = Semester::findOrFail($data->semesterId)->lembaga_id;` ditambah di dalam blok `if`. Tidak ada baris lain yang berubah.

- [ ] **Step 5: Jalankan seluruh file test dan pastikan semua PASS**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`
Expected: PASS untuk seluruh test di file ini (baseline + 3 test baru), termasuk 4 test update existing di baris 254-352 yang harus tetap PASS tanpa modifikasi assertion.

Jika ada test lain di file ini yang FAIL, cek dulu apakah kegagalan itu terkait fix (misal ada test lain yang secara implisit mengasumsikan `lembaga_id` TIDAK berubah saat `semester_id` berubah lewat action ini) sebelum melanjutkan — laporkan sebagai temuan BLOCKED jika ditemukan, jangan diam-diam mengubah assertion test existing untuk membuatnya lolos.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "fix(akademik): recompute lembaga_id saat semester_id berubah pada update komponen penilaian"
```

---

## Self-Review

**1. Spec coverage:**
- §2 Keputusan Desain (fix 1 baris + import) → Task 1 Step 4. ✅
- §4.1 Regresi wajib (4 test existing tetap PASS tanpa modifikasi) → Task 1 Step 5 eksplisit melarang modifikasi assertion existing. ✅
- §4.2 Bug reproduction aktor yayasan, kedua subjek_type → Task 1 Step 2, dua test (`elemen_cp` dan `mata_pelajaran`), pakai `actingAsYayasanKomponenManager()` tanpa `active_lembaga_id`. ✅
- §4.3 Kasus tidak berubah (regresi negatif) → Task 1 Step 2, test `does not touch lembaga_id when updating a komponen without changing semester_id`. ✅
- §5 Ringkasan file → cocok dengan Task 1 Files. ✅
- §3 Non-Goals → tidak ada task yang menambah guard elemen_cp baru di controller, tidak mengubah guard mata_pelajaran existing, tidak mengubah `CreateKomponenPenilaianAction`, tidak ada migration. Confirmed no task does any of these. ✅

**2. Placeholder scan:** Tidak ada TBD/TODO. Semua kode test dan implementasi lengkap dan bisa langsung dijalankan.

**3. Type consistency:** `UpdateKomponenPenilaianAction::execute()` signature tidak berubah di seluruh plan. `KomponenPenilaianData` constructor dipakai dengan named arguments — Step 2 secara eksplisit menginstruksikan implementer untuk memverifikasi urutan/nama properti terhadap file aktual sebelum menulis test, mengantisipasi drift constructor yang tidak terlihat dari plan ini.

---

## Konteks Tambahan untuk Kickoff

- Route `admin.komponen-penilaian.update` mengarah ke `KomponenPenilaianController::update()` — guard existing di sana (baris 166-176) TIDAK diubah oleh plan ini; guard itu hanya membandingkan `subjek.lembaga_id` vs `semester.lembaga_id` untuk `mata_pelajaran`, TIDAK PERNAH memblokir ownership terhadap `lembaga_id` asli `$komponenPenilaian` — itulah sebabnya aktor yayasan bisa lolos sampai ke `UpdateKomponenPenilaianAction`.
- `KomponenPenilaian` model pakai `BelongsToTenant` — route-model-binding `$komponenPenilaian` di controller sudah ter-scope `TenantScope`, tapi untuk aktor yayasan mode "Semua Lembaga", scope itu mengizinkan akses ke komponen dari lembaga manapun di bawah yayasan yang sama (lihat `TenantScope::apply()` cabang yayasan: `whereIn(lembaga_id, Lembaga::where('yayasan_id', ...))`).
