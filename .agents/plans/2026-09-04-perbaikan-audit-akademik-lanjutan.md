# Perbaikan Audit Akademik Lanjutan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup 1 IDOR Critical (2 pintu berbeda: nilai ditulis DAN akses baris existing) + 3 bug Important (RPP verify gagal utk yayasan, bentrok jadwal berbasis ID bukan waktu, precedence resolver Fase/Kurikulum salah) di modul Akademik.

**Architecture:** `ResolveLembagaScopeTrait` (baru) dipakai `AssignKurikulumAction`/`SetFaseDefaultMappingAction` (baru) untuk pintu CREATE (nilai `lembaga_id` yang ditulis tidak pernah dipercaya dari request untuk actor non-platform). `authorizeExistingAssignmentScope()`/`authorizeExistingMappingScope()` (method controller baru, BUKAN Action — tidak ada nilai baru yang ditulis di edit/update/destroy) menutup pintu AKSES ke baris existing. Perbaikan RPP/Jadwal/Resolver berdiri sendiri, independen dari 2 Action baru itu.

**Tech Stack:** Laravel 12, PHP 8.3, Pest.

## Global Constraints

- TIDAK PAKAI Model Observer/`saving()` hook — melanggar `.ai/rules/models.md` ("No model Observers or lifecycle-hook closures"). Pola yang dipakai: "derive, jangan validate" (`resolveLembagaId` untuk nilai BARU) DITAMBAH pemeriksaan eksplisit di controller untuk baris EXISTING (`authorizeExisting*Scope`) — dua mekanisme berbeda untuk dua pintu berbeda, BUKAN satu Observer yang menutup keduanya sekaligus.
- Global assignment (`lembaga_id = NULL`) HANYA boleh dibuat/diubah/dihapus oleh `platform` — berlaku KONSISTEN untuk `KurikulumAssignment` DAN `FaseDefaultMapping`, di KEDUA pintu (create maupun akses baris existing).
- `session('active_lembaga_id')` WAJIB diverifikasi ulang di titik pakai (`Lembaga::where('id', ...)->where('yayasan_id', $actor->yayasan_id)->exists()`) — jangan percaya mentah-mentah walau sudah diverifikasi saat di-SET di `ResolveTenant.php`.
- Actor `lembaga`-scope biasa di `index()` TIDAK disentuh — filter existing-nya (`whereNull('lembaga_id')->orWhere('lembaga_id', $request->user()->lembaga_id)`) sudah benar sejak awal.
- 2 temuan Minor (race condition bobot Komponen Penilaian, fallback guru acak `RppController`) TIDAK masuk plan ini sama sekali.
- Pola session-staleness di `GuruController`/`JalurPpdbController`/`KalenderAkademikController`/`PengaturanAkademikController`/`GelombangPpdbController` TIDAK disentuh — di luar scope.
- Test existing yang meng-encode perilaku LAMA (yayasan = platform) HARUS ditulis ulang sesuai aturan baru, BUKAN dihapus diam-diam atau dibiarkan merah — lihat catatan eksplisit di Task 2 dan 3.
- Tidak pindah branch, tetap di `akademik-v2`.

---

## Task 1: `ResolveLembagaScopeTrait`

**Files:**
- Create: `app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php`
- Test: `tests/Unit/Support/ResolveLembagaScopeTraitTest.php`

**Interfaces:**
- Produksi: `trait ResolveLembagaScopeTrait` dengan method `resolveLembagaId(User $actor, ?int $lembagaIdDiminta): ?int` (private, dipakai class yang `use` trait ini). Task 2 dan 3 memakai trait ini persis dengan signature ini.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/Support/ResolveLembagaScopeTraitTest.php`:

```php
<?php

use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;

function objekPakaiResolveLembagaScope(): object
{
    return new class
    {
        use ResolveLembagaScopeTrait;

        public function panggilResolve(User $actor, ?int $lembagaIdDiminta): ?int
        {
            return $this->resolveLembagaId($actor, $lembagaIdDiminta);
        }
    };
}

it('platform bebas memilih lembaga_id apapun, termasuk null (global)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $platform = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => null]);
    $platform->assignRole(\App\Models\Role::firstOrCreate(['name' => 'platform_admin_uji', 'guard_name' => 'web'], ['scope_level' => 'platform']));

    $obj = objekPakaiResolveLembagaScope();

    expect($obj->panggilResolve($platform, $lembaga->id))->toBe($lembaga->id);
    expect($obj->panggilResolve($platform, null))->toBeNull();
});

it('yayasan memakai session active_lembaga_id, MENGABAIKAN lembagaIdDiminta sama sekali', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $actor = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $actor->assignRole(\App\Models\Role::firstOrCreate(['name' => 'yayasan_admin_uji', 'guard_name' => 'web'], ['scope_level' => 'yayasan']));
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $obj = objekPakaiResolveLembagaScope();

    // lembagaIdDiminta = $lembagaLain->id sengaja BEDA dari session -- harus diabaikan total.
    expect($obj->panggilResolve($actor, $lembagaLain->id))->toBe($lembagaAktif->id);
});

it('yayasan tanpa active_lembaga_id di sesi ditolak dengan 422', function () {
    $yayasan = Yayasan::factory()->create();
    $actor = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $actor->assignRole(\App\Models\Role::firstOrCreate(['name' => 'yayasan_admin_uji2', 'guard_name' => 'web'], ['scope_level' => 'yayasan']));
    session()->forget('active_lembaga_id');

    $obj = objekPakaiResolveLembagaScope();

    expect(fn () => $obj->panggilResolve($actor, null))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('yayasan dengan active_lembaga_id stale (lembaga di luar yayasannya) ditolak dengan 422', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaMilikYayasanLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $actor = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $actor->assignRole(\App\Models\Role::firstOrCreate(['name' => 'yayasan_admin_uji3', 'guard_name' => 'web'], ['scope_level' => 'yayasan']));
    session(['active_lembaga_id' => $lembagaMilikYayasanLain->id]);

    $obj = objekPakaiResolveLembagaScope();

    expect(fn () => $obj->panggilResolve($actor, null))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('lembaga-scope selalu memakai lembaga_id miliknya sendiri, mengabaikan lembagaIdDiminta', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $actor = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);

    $obj = objekPakaiResolveLembagaScope();

    expect($obj->panggilResolve($actor, $lembagaLain->id))->toBe($lembagaSaya->id);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=ResolveLembagaScopeTraitTest`
Expected: FAIL — `App\Domains\Akademik\Support\ResolveLembagaScopeTrait` belum ada.

- [ ] **Step 3: Buat trait**

Buat direktori `app/Domains/Akademik/Support/` kalau belum ada. Buat `app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Support;

use App\Models\Lembaga;
use App\Models\User;

trait ResolveLembagaScopeTrait
{
    private function resolveLembagaId(User $actor, ?int $lembagaIdDiminta): ?int
    {
        return match ($actor->widestScopeLevel()) {
            'platform' => $lembagaIdDiminta,
            'yayasan' => $this->resolveLembagaIdUntukYayasan($actor),
            default => $actor->lembaga_id,
        };
    }

    private function resolveLembagaIdUntukYayasan(User $actor): int
    {
        $lembagaId = session('active_lembaga_id');
        abort_if($lembagaId === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum melakukan aksi ini.');

        $milikYayasan = Lembaga::where('id', $lembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();
        abort_unless($milikYayasan, 422, 'Lembaga aktif di sesi Anda tidak valid untuk yayasan Anda saat ini. Pilih ulang lembaga aktif melalui pengalih lembaga.');

        return $lembagaId;
    }
}
```

- [ ] **Step 4: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=ResolveLembagaScopeTraitTest`
Expected: PASS (5 test).

- [ ] **Step 5: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php tests/Unit/Support/ResolveLembagaScopeTraitTest.php
git commit -m "feat(akademik): ResolveLembagaScopeTrait -- lembaga_id non-platform tidak pernah dari request"
```

---

## Task 2: `KurikulumAssignment` — Tutup 2 Pintu IDOR

**Files:**
- Create: `app/Domains/Akademik/Actions/KurikulumAssignment/AssignKurikulumAction.php`
- Modify: `app/Http/Controllers/Admin/KurikulumAssignmentController.php`
- Modify: `resources/views/admin/kurikulum-assignment/create.blade.php`, `edit.blade.php` (hapus dropdown lembaga utk non-platform)
- Modify (rewrite test lama, TIDAK dihapus diam-diam): `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`
- Test baru: `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (ditambahkan di file yang sama)

**Interfaces:**
- Konsumsi: `ResolveLembagaScopeTrait` dari Task 1.
- Produksi: `AssignKurikulumAction::executeCreate(User $actor, array $validated): KurikulumAssignment` — dipakai `store()`. `KurikulumAssignmentController::authorizeExistingAssignmentScope(User $actor, ?int $existingLembagaId): void` — dipakai `edit()`/`update()`/`destroy()`.

- [ ] **Step 1: Baca 3 test yang HARUS ditulis ulang (bukan dihapus)**

Baca `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` baris 113-190. Ada 1 helper (`actingAsPlatformKurikulumManager()`, baris 113-126) dan 3 test (baris 144-190) yang meng-encode perilaku LAMA (aktor `scope_level: 'yayasan'` diberi hak akses seperti `platform` sungguhan). Ini akan ditulis ulang di Step 6 — CATAT dulu isinya sekarang, jangan diedit di step ini.

- [ ] **Step 2: Tulis test BARU yang gagal (pintu #1 — nilai yang ditulis)**

Tambahkan di akhir `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php` (helper `actingAsPlatformKurikulumManager` akan diganti nanti di Step 6 jadi 2 helper terpisah — untuk sekarang tulis test baru pakai helper baru `actingAsYayasanKurikulumManager`/`actingAsPlatformScopeKurikulumManager` yang akan dibuat di Step 6):

```php
it('memakai session active_lembaga_id untuk lembaga_id assignment yayasan, mengabaikan lembaga_id yang dikirim di body request', function () {
    $manager = actingAsYayasanKurikulumManager();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $taLembagaAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembagaAktif->id]);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'lembaga_id' => $lembagaLain->id, // dicoba dipaksakan, HARUS diabaikan
        'tahun_ajaran_id' => $taLembagaAktif->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    $assignment = KurikulumAssignment::where('tahun_ajaran_id', $taLembagaAktif->id)->first();
    expect($assignment->lembaga_id)->toBe($lembagaAktif->id);
});

it('menolak yayasan membuat assignment global (lembaga_id null) -- hanya platform yang boleh', function () {
    $manager = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'lembaga_id' => '',
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ]);

    // lembaga_id yayasan SELALU dari session, tidak pernah null walau field 'lembaga_id' dikirim kosong.
    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()?->lembaga_id)->toBe($lembaga->id);
});

it('yayasan tanpa active_lembaga_id di sesi ditolak dengan pesan jelas saat membuat assignment', function () {
    $manager = actingAsYayasanKurikulumManager();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    session()->forget('active_lembaga_id');

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertStatus(422);
});

it('platform BISA membuat assignment global (lembaga_id null)', function () {
    $manager = actingAsPlatformScopeKurikulumManager();
    $lembaga = Lembaga::factory()->create(['bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()?->lembaga_id)->toBeNull();
});

it('platform BISA membuat assignment untuk lembaga manapun lintas yayasan', function () {
    $manager = actingAsPlatformScopeKurikulumManager();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaYayasanLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id, 'bentuk_pendidikan' => 'SD']);
    $ta = TahunAjaran::factory()->create(['lembaga_id' => $lembagaYayasanLain->id]);

    $this->actingAs($manager)->post(route('admin.kurikulum-assignment.store'), [
        'lembaga_id' => $lembagaYayasanLain->id,
        'tahun_ajaran_id' => $ta->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'kurikulum' => 'merdeka',
    ])->assertRedirect(route('admin.kurikulum-assignment.index'));

    expect(KurikulumAssignment::where('tahun_ajaran_id', $ta->id)->first()?->lembaga_id)->toBe($lembagaYayasanLain->id);
});
```

- [ ] **Step 3: Tulis test BARU yang gagal (pintu #2 — akses baris existing)**

Tambahkan di file yang sama:

```php
it('menolak yayasan A mengedit/update/hapus assignment milik lembaga di yayasan B (akses baris existing, BUKAN soal nilai ditulis)', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id, 'bentuk_pendidikan' => 'SD']);
    $taB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $assignmentB = KurikulumAssignment::create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $taB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.edit', $assignmentB))->assertForbidden();
    $this->actingAs($managerA)->put(route('admin.kurikulum-assignment.update', $assignmentB), [
        'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'merdeka',
    ])->assertForbidden();
    $this->actingAs($managerA)->delete(route('admin.kurikulum-assignment.destroy', $assignmentB))->assertForbidden();

    expect($assignmentB->fresh()->kurikulum->value)->toBe('k13');
});

it('menolak yayasan mengedit/hapus assignment GLOBAL (lembaga_id null) -- hanya platform yang boleh', function () {
    $manager = actingAsYayasanKurikulumManager();
    $ta = TahunAjaran::factory()->create();
    $assignmentGlobal = KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $ta->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $this->actingAs($manager)->delete(route('admin.kurikulum-assignment.destroy', $assignmentGlobal))->assertForbidden();

    expect(KurikulumAssignment::find($assignmentGlobal->id))->not->toBeNull();
});

it('platform TETAP lihat SEMUA assignment lintas yayasan di index (regresi negatif)', function () {
    $manager = actingAsPlatformScopeKurikulumManager();
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasanA->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $taA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $taB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $assignmentA = KurikulumAssignment::create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $taA->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    $assignmentB = KurikulumAssignment::create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $taB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);

    $response = $this->actingAs($manager)->get(route('admin.kurikulum-assignment.index'));

    $response->assertViewHas('assignmentList', function ($list) use ($assignmentA, $assignmentB) {
        return $list->contains('id', $assignmentA->id) && $list->contains('id', $assignmentB->id);
    });
});

it('yayasan cuma lihat assignment global + milik yayasannya sendiri di index, TIDAK lihat milik yayasan lain', function () {
    $managerA = actingAsYayasanKurikulumManager();
    $yayasanB = Yayasan::factory()->create();
    $lembagaMilikSendiri = Lembaga::factory()->create(['yayasan_id' => $managerA->yayasan_id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $taSendiri = TahunAjaran::factory()->create(['lembaga_id' => $lembagaMilikSendiri->id]);
    $taB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $assignmentSendiri = KurikulumAssignment::create(['lembaga_id' => $lembagaMilikSendiri->id, 'tahun_ajaran_id' => $taSendiri->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    $assignmentB = KurikulumAssignment::create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $taB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kurikulum' => 'k13']);
    $assignmentGlobal = KurikulumAssignment::create(['lembaga_id' => null, 'tahun_ajaran_id' => $taSendiri->id, 'bentuk_pendidikan' => 'SMP', 'tingkat' => '7', 'kurikulum' => 'k13']);

    $response = $this->actingAs($managerA)->get(route('admin.kurikulum-assignment.index'));

    $response->assertViewHas('assignmentList', function ($list) use ($assignmentSendiri, $assignmentB, $assignmentGlobal) {
        return $list->contains('id', $assignmentSendiri->id)
            && $list->contains('id', $assignmentGlobal->id)
            && ! $list->contains('id', $assignmentB->id);
    });
});
```

- [ ] **Step 4: Jalankan test baru, pastikan gagal**

Run: `php artisan test --filter=KurikulumAssignmentControllerTest`
Expected: FAIL — helper `actingAsYayasanKurikulumManager`/`actingAsPlatformScopeKurikulumManager` belum ada, dan logic lama masih memperlakukan yayasan seperti platform.

- [ ] **Step 5: Buat `AssignKurikulumAction`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KurikulumAssignment;

use App\Domains\Akademik\DataTransferObjects\KurikulumAssignmentData;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\User;

final class AssignKurikulumAction
{
    use ResolveLembagaScopeTrait;

    public function __construct(
        private readonly CreateKurikulumAssignmentAction $createKurikulumAssignmentAction,
    ) {}

    public function executeCreate(User $actor, string $bentukPendidikan, ?string $tingkat, string $kurikulum, ?int $lembagaIdDiminta, int $tahunAjaranId): KurikulumAssignment
    {
        $lembagaId = $this->resolveLembagaId($actor, $lembagaIdDiminta);

        return $this->createKurikulumAssignmentAction->execute(new KurikulumAssignmentData(
            bentukPendidikan: $bentukPendidikan,
            tingkat: $tingkat,
            kurikulum: $kurikulum,
            lembagaId: $lembagaId,
            tahunAjaranId: $tahunAjaranId,
        ));
    }
}
```

- [ ] **Step 6: Update `KurikulumAssignmentController`**

Baca file penuh dulu untuk konfirmasi baris (mungkin sudah bergeser dari yang dibaca sebelumnya). Ganti method `store()` (baris 60-95 versi lama) — hapus logic `$isPlatformOrYayasan`/`$lembagaId`/`authorizeAssignmentScope` manual, ganti pemanggilan jadi lewat `AssignKurikulumAction`.

**Catatan desain**: `store()` butuh tahu `$lembagaId` SEBELUM memanggil Action (untuk validasi tahun-ajaran-milik-lembaga-mana dan cek duplikat). Untuk menghindari duplikasi logic, controller sendiri (bukan cuma Action) perlu `use ResolveLembagaScopeTrait;` juga, dan panggil `$this->resolveLembagaId($request->user(), $lembagaIdDiminta)` SEKALI di awal `store()`, simpan ke variabel, dan teruskan `$lembagaIdDiminta` (nilai MENTAH sebelum resolusi) ke `AssignKurikulumAction` — Action akan resolve ulang secara independen dan mendapat hasil yang SAMA (sumbernya identik: `$actor` dan session tidak berubah dalam 1 request), tanpa controller dan Action harus saling percaya hasil resolusi satu sama lain:

```php
    public function store(StoreKurikulumAssignmentRequest $request, AssignKurikulumAction $action): RedirectResponse
    {
        $this->authorize('kurikulum-assignment.create');

        $validated = $request->validated();
        $tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;
        $lembagaIdDiminta = $request->user()->widestScopeLevel() === 'platform' ? ($validated['lembaga_id'] ?? null) : null;
        $lembagaId = $this->resolveLembagaId($request->user(), $lembagaIdDiminta);

        if ($lembagaId !== null) {
            $tahunAjaranValid = TahunAjaran::whereKey($validated['tahun_ajaran_id'])->where('lembaga_id', $lembagaId)->exists();
            if (! $tahunAjaranValid) {
                return back()->withErrors(['tahun_ajaran_id' => 'Tahun ajaran yang dipilih bukan milik lembaga ini.'])->withInput();
            }
        }

        if (KurikulumAssignment::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        $action->executeCreate($request->user(), $validated['bentuk_pendidikan'], $tingkat, $validated['kurikulum'], $lembagaIdDiminta, (int) $validated['tahun_ajaran_id']);

        return redirect()->route('admin.kurikulum-assignment.index')->with('status', 'Assignment kurikulum berhasil disimpan.');
    }
```

Tambahkan `use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;` ke import, dan `use ResolveLembagaScopeTrait;` di dalam class body `KurikulumAssignmentController`.

Ganti `edit()`, `update()`, `destroy()` — ganti SEMUA pemanggilan `$this->authorizeAssignmentScope($request, $kurikulumAssignment->lembaga_id)` jadi `$this->authorizeExistingAssignmentScope($request->user(), $kurikulumAssignment->lembaga_id)`.

Hapus method `isPlatformOrYayasan()` dan `authorizeAssignmentScope()` lama (baris 156-172 versi awal), ganti dengan:

```php
    private function authorizeExistingAssignmentScope(\App\Models\User $actor, ?int $existingLembagaId): void
    {
        if ($actor->widestScopeLevel() === 'platform') {
            return;
        }

        if ($existingLembagaId === null) {
            abort(403, 'Assignment global hanya bisa diubah/dihapus oleh Platform Admin.');
        }

        if ($actor->widestScopeLevel() === 'yayasan') {
            $milikYayasan = Lembaga::where('id', $existingLembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();
            abort_unless($milikYayasan, 403, 'Assignment ini bukan milik yayasan Anda.');

            return;
        }

        abort_unless($existingLembagaId === $actor->lembaga_id, 403);
    }
```

Ganti `create()` — dropdown `lembagaList` cuma untuk platform:
```php
    public function create(Request $request): View
    {
        $this->authorize('kurikulum-assignment.create');

        $isPlatform = $request->user()->widestScopeLevel() === 'platform';

        return view('admin.kurikulum-assignment.create', [
            'kurikulumList' => KurikulumFramework::cases(),
            'bentukPendidikanList' => BentukPendidikan::cases(),
            'tahunAjaranList' => $this->tahunAjaranListForScope($request),
            'lembagaList' => $isPlatform ? Lembaga::orderBy('nama')->get() : collect(),
            'isPlatform' => $isPlatform,
        ]);
    }
```

Ganti `index()` — filter yayasan cuma lihat global + lembaga di bawah yayasannya:
```php
    public function index(Request $request): View
    {
        $this->authorize('kurikulum-assignment.view');

        $scope = $request->user()->widestScopeLevel();
        $query = KurikulumAssignment::with(['lembaga', 'tahunAjaran']);

        if ($scope === 'yayasan') {
            $lembagaIds = Lembaga::where('yayasan_id', $request->user()->yayasan_id)->pluck('id');
            $query->where(function ($q) use ($lembagaIds) {
                $q->whereNull('lembaga_id')->orWhereIn('lembaga_id', $lembagaIds);
            });
        } elseif ($scope !== 'platform') {
            $query->where(function ($q) use ($request) {
                $q->whereNull('lembaga_id')->orWhere('lembaga_id', $request->user()->lembaga_id);
            });
        }

        return view('admin.kurikulum-assignment.index', [
            'assignmentList' => $query->orderByDesc('tahun_ajaran_id')->orderBy('bentuk_pendidikan')->orderByRaw('tingkat IS NULL')->orderBy('tingkat')->get(),
            'isPlatformOrYayasan' => in_array($scope, ['platform', 'yayasan'], true),
        ]);
    }
```

- [ ] **Step 7: Tulis ulang view `create.blade.php`/`edit.blade.php` — dropdown lembaga cuma untuk platform**

Baca `resources/views/admin/kurikulum-assignment/create.blade.php`. Cari blok yang merender `<select name="lembaga_id">` berdasarkan `$isPlatformOrYayasan` (variabel lama) — ganti kondisinya ke `$isPlatform` (variabel baru dari Step 6), dan untuk non-platform tampilkan teks info read-only nama lembaga aktif (`session('active_lembaga_id')` di-resolve ke nama lembaga di controller kalau perlu, atau cukup teks generik "Assignment ini akan dibuat untuk lembaga yang sedang aktif di sesi Anda."). Detail exact markup disesuaikan dengan struktur file aktual (baca dulu sebelum edit) — prinsipnya: dropdown lembaga+opsi "Global" HANYA muncul kalau `$isPlatform` true.

- [ ] **Step 8: Tulis ulang 4 test lama yang meng-encode perilaku LAMA**

Di `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`, ganti helper `actingAsPlatformKurikulumManager()` (baris 113-126 versi lama) jadi 2 helper terpisah:

```php
function actingAsYayasanKurikulumManager(): User
{
    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $yayasan = Yayasan::factory()->create();
    $role = Role::firstOrCreate(['name' => 'yayasan_admin_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete']);

    $manager = User::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null]);
    $manager->assignRole($role);

    return $manager;
}

function actingAsPlatformScopeKurikulumManager(): User
{
    foreach (['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'platform_admin_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'platform']);
    $role->givePermissionTo(['kurikulum-assignment.view', 'kurikulum-assignment.create', 'kurikulum-assignment.edit', 'kurikulum-assignment.delete']);

    $manager = User::factory()->create(['yayasan_id' => null, 'lembaga_id' => null]);
    $manager->assignRole($role);

    return $manager;
}
```

Ganti isi 3 test yang tadinya memakai `actingAsPlatformKurikulumManager()` (baris 144-190 versi lama: `'allows platform/yayasan to store with an explicit lembaga_id...'`, `'rejects platform/yayasan store when explicit lembaga_id does not match...'`, `'does not reject a platform/yayasan store for ownership when lembaga_id is left null...'`) — HAPUS ketiganya (perilaku yang mereka uji sekarang SALAH secara sengaja diganti), gantikan dengan test-test baru yang SUDAH ditulis di Step 2 dan 3 di atas (jangan duplikasi, cukup pastikan 3 test lama itu tidak ada lagi di file, sudah tergantikan test baru yang lebih presisi).

- [ ] **Step 9: Jalankan semua test, pastikan lolos**

Run: `php artisan test --filter=KurikulumAssignmentControllerTest`
Expected: PASS semua.

Run: `php artisan test --filter=KurikulumAssignmentDestroyGuardTest`
Expected: kemungkinan test `'menolak hapus Kurikulum Assignment lembaga lain oleh aktor yayasan meski active_lembaga_id-nya beda'` (baris 47-74) GAGAL — guard baru (`authorizeExistingAssignmentScope`) menolak dengan 403 SEBELUM sempat sampai ke pengecekan "masih dipakai Kelas" yang jadi fokus test itu, padahal test mengharapkan redirect+session-error (bukan 403). **Kalau gagal**: baca ulang test itu, perbaiki SETUP-nya (bukan assertion-nya) supaya `$userYayasan` benar-benar berada di yayasan yang SAMA dengan `$lembagaB` (tambahkan `'yayasan_id' => $lembagaB->yayasan_id` ke factory `$userYayasan`) — dengan begitu guard otorisasi baru LOLOS (actor memang berhak akses lembaga B), dan request lanjut ke pengecekan "masih dipakai Kelas" yang jadi fokus asli test tersebut, sehingga assertion lama (redirect+session-error, assignment tidak terhapus) tetap valid dan somad tujuan test (TenantScope tidak menyamarkan guard in-use) tetap teruji dengan cara yang benar.

- [ ] **Step 10: Regresi test terkait lain**

Run: `php artisan test --filter=KurikulumAssignment`
Expected: semua PASS.

- [ ] **Step 11: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Actions/KurikulumAssignment/AssignKurikulumAction.php app/Http/Controllers/Admin/KurikulumAssignmentController.php resources/views/admin/kurikulum-assignment/create.blade.php resources/views/admin/kurikulum-assignment/edit.blade.php tests/Feature/Akademik/KurikulumAssignmentControllerTest.php tests/Feature/Admin/KurikulumAssignmentDestroyGuardTest.php
git commit -m "fix(akademik): tutup 2 pintu IDOR KurikulumAssignment -- nilai ditulis (resolveLembagaId) dan akses baris existing (authorizeExistingAssignmentScope)"
```

---

## Task 3: `FaseDefaultMapping` — Cerminan Task 2

**Files:**
- Create: `app/Domains/Akademik/Actions/FaseMapping/SetFaseDefaultMappingAction.php`
- Modify: `app/Http/Controllers/Admin/FaseDefaultMappingController.php`
- Modify: `resources/views/admin/fase-mapping/create.blade.php`, `edit.blade.php`
- Modify (rewrite test lama): `tests/Feature/Akademik/FaseDefaultMappingControllerTest.php`

**Interfaces:**
- Konsumsi: `ResolveLembagaScopeTrait` dari Task 1 (pola identik Task 2, TIDAK bergantung pada `AssignKurikulumAction`).

- [ ] **Step 1: Identifikasi 3 test lama yang meng-encode perilaku LAMA**

Di `tests/Feature/Akademik/FaseDefaultMappingControllerTest.php`:
- `'lets a yayasan-scope user create a platform-wide mapping'` (baris 71-83) — HARUS diganti jadi menguji PENOLAKAN.
- `'lets a yayasan-scope user create a mapping for any specific lembaga'` (baris 85-98) — HARUS diganti (lembaga_id yayasan dari session, bukan request).
- `'lets a yayasan-scope user delete any lembaga's mapping'` (baris 148-157) — **ini persis skenario IDOR yang ditutup**, HARUS diganti jadi menguji PENOLAKAN untuk lembaga di luar yayasan actor.

Juga perhatikan: `buatUserDenganRole('yayasan_super_admin')` (baris 13-36) tidak pernah set `yayasan_id` pada user yang dibuat — perlu ditambah parameter `?int $yayasanId = null` supaya test bisa mengontrol yayasan_id actor secara eksplisit.

- [ ] **Step 2: Tulis test BARU yang gagal (cerminan Task 2 Step 2-3, disesuaikan struktur `FaseDefaultMapping` yang tidak punya `tahun_ajaran_id`)**

Ubah `buatUserDenganRole()` (baris 13-36) jadi menerima `?int $yayasanId = null`:
```php
function buatUserDenganRole(string $roleName, ?int $lembagaId = null, ?int $yayasanId = null): User
{
    foreach (['fase-mapping.view', 'fase-mapping.create', 'fase-mapping.edit', 'fase-mapping.delete'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], ['scope_level' => match ($roleName) {
        'operator_akademik' => 'lembaga',
        'yayasan_super_admin' => 'yayasan',
        'platform_super_admin' => 'platform',
        default => 'lembaga',
    }]);

    if ($roleName === 'operator_akademik') {
        $role->givePermissionTo(['fase-mapping.view', 'fase-mapping.create', 'fase-mapping.edit', 'fase-mapping.delete']);
    }
    if (in_array($roleName, ['yayasan_super_admin', 'platform_super_admin'], true)) {
        $role->givePermissionTo(Permission::query()->pluck('name')->all());
    }

    $user = User::factory()->create(['lembaga_id' => $lembagaId, 'yayasan_id' => $yayasanId]);
    $user->assignRole($role);

    return $user;
}
```

Tambahkan test baru (setelah `use App\Models\Yayasan;` ditambahkan ke import):
```php
it('memakai session active_lembaga_id untuk lembaga_id mapping yayasan, mengabaikan lembaga_id yang dikirim di body', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $yayasan = Yayasan::factory()->create();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = buatUserDenganRole('yayasan_super_admin', null, $yayasan->id);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'lembaga_id' => $lembagaLain->id,
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
    ])->assertRedirect(route('admin.fase-mapping.index'));

    expect(FaseDefaultMapping::first()->lembaga_id)->toBe($lembagaAktif->id);
});

it('menolak yayasan membuat mapping global -- hanya platform yang boleh', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $yayasan = Yayasan::factory()->create();
    $lembagaAktif = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = buatUserDenganRole('yayasan_super_admin', null, $yayasan->id);
    session(['active_lembaga_id' => $lembagaAktif->id]);

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'lembaga_id' => '',
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
    ]);

    expect(FaseDefaultMapping::first()?->lembaga_id)->toBe($lembagaAktif->id);
});

it('platform BISA membuat mapping global', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $user = buatUserDenganRole('platform_super_admin');

    $this->actingAs($user)->post(route('admin.fase-mapping.store'), [
        'bentuk_pendidikan' => 'SD',
        'tingkat' => '1',
        'fase_id' => $fase->id,
    ])->assertRedirect(route('admin.fase-mapping.index'));

    expect(FaseDefaultMapping::first()->lembaga_id)->toBeNull();
});

it('menolak yayasan A menghapus mapping milik lembaga di yayasan B', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $yayasanA = Yayasan::factory()->create();
    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $mapping = FaseDefaultMapping::create(['lembaga_id' => $lembagaB->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $userA = buatUserDenganRole('yayasan_super_admin', null, $yayasanA->id);

    $this->actingAs($userA)->delete(route('admin.fase-mapping.destroy', $mapping))->assertForbidden();

    expect(FaseDefaultMapping::find($mapping->id))->not->toBeNull();
});

it('mengizinkan yayasan menghapus mapping milik lembaga di yayasannya sendiri', function () {
    $fase = Fase::create(['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1]);
    $yayasan = Yayasan::factory()->create();
    $lembagaSendiri = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $mapping = FaseDefaultMapping::create(['lembaga_id' => $lembagaSendiri->id, 'bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'fase_id' => $fase->id]);
    $user = buatUserDenganRole('yayasan_super_admin', null, $yayasan->id);

    $this->actingAs($user)->delete(route('admin.fase-mapping.destroy', $mapping))->assertRedirect(route('admin.fase-mapping.index'));

    expect(FaseDefaultMapping::find($mapping->id))->toBeNull();
});
```

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=FaseDefaultMappingControllerTest`
Expected: FAIL.

- [ ] **Step 4: Buat `SetFaseDefaultMappingAction`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\FaseMapping;

use App\Domains\Akademik\DataTransferObjects\FaseDefaultMappingData;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\User;

final class SetFaseDefaultMappingAction
{
    use ResolveLembagaScopeTrait;

    public function __construct(
        private readonly CreateFaseDefaultMappingAction $createFaseDefaultMappingAction,
    ) {}

    public function executeCreate(User $actor, string $bentukPendidikan, ?string $tingkat, int $faseId, ?int $lembagaIdDiminta): FaseDefaultMapping
    {
        $lembagaId = $this->resolveLembagaId($actor, $lembagaIdDiminta);

        return $this->createFaseDefaultMappingAction->execute(new FaseDefaultMappingData(
            bentukPendidikan: $bentukPendidikan,
            tingkat: $tingkat,
            faseId: $faseId,
            lembagaId: $lembagaId,
        ));
    }
}
```

- [ ] **Step 5: Update `FaseDefaultMappingController`** — pola identik Task 2 Step 6

Tambahkan `use ResolveLembagaScopeTrait;` di controller. Ganti `store()`:
```php
    public function store(StoreFaseDefaultMappingRequest $request, SetFaseDefaultMappingAction $action): RedirectResponse
    {
        $this->authorize('fase-mapping.create');

        $validated = $request->validated();
        $tingkat = $validated['tingkat'] !== '' ? ($validated['tingkat'] ?? null) : null;
        $lembagaIdDiminta = $request->user()->widestScopeLevel() === 'platform' ? ($validated['lembaga_id'] ?? null) : null;
        $lembagaId = $this->resolveLembagaId($request->user(), $lembagaIdDiminta);

        if (FaseDefaultMapping::where('lembaga_id', $lembagaId)->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
            return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada mapping default untuk kombinasi jenjang dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
        }

        $action->executeCreate($request->user(), $validated['bentuk_pendidikan'], $tingkat, (int) $validated['fase_id'], $lembagaIdDiminta);

        return redirect()->route('admin.fase-mapping.index')->with('status', 'Mapping default berhasil disimpan.');
    }
```

Ganti `edit()`/`update()`/`destroy()`: ganti `authorizeMappingScope($request, $faseMapping->lembaga_id)` jadi `authorizeExistingMappingScope($request->user(), $faseMapping->lembaga_id)`. Hapus `isPlatformOrYayasan()`/`authorizeMappingScope()` lama, ganti:
```php
    private function authorizeExistingMappingScope(\App\Models\User $actor, ?int $existingLembagaId): void
    {
        if ($actor->widestScopeLevel() === 'platform') {
            return;
        }

        if ($existingLembagaId === null) {
            abort(403, 'Mapping global hanya bisa diubah/dihapus oleh Platform Admin.');
        }

        if ($actor->widestScopeLevel() === 'yayasan') {
            $milikYayasan = Lembaga::where('id', $existingLembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();
            abort_unless($milikYayasan, 403, 'Mapping ini bukan milik yayasan Anda.');

            return;
        }

        abort_unless($existingLembagaId === $actor->lembaga_id, 403);
    }
```

Ganti `create()` — dropdown lembaga cuma platform (pola identik Task 2 Step 6). Ganti `index()` — filter yayasan cuma lihat global + lembaga di bawah yayasannya (pola identik Task 2 Step 6, tanpa `orderByDesc('tahun_ajaran_id')` karena `FaseDefaultMapping` tidak punya kolom itu).

- [ ] **Step 6: Update view `create.blade.php`/`edit.blade.php`** — pola identik Task 2 Step 7.

- [ ] **Step 7: Hapus 3 test lama yang meng-encode perilaku LAMA**

Hapus `'lets a yayasan-scope user create a platform-wide mapping'`, `'lets a yayasan-scope user create a mapping for any specific lembaga'`, `'lets a yayasan-scope user delete any lembaga's mapping'` dari `tests/Feature/Akademik/FaseDefaultMappingControllerTest.php` — sudah tergantikan test baru di Step 2.

- [ ] **Step 8: Jalankan semua test, pastikan lolos**

Run: `php artisan test --filter=FaseDefaultMapping`
Expected: semua PASS.

- [ ] **Step 9: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Actions/FaseMapping/SetFaseDefaultMappingAction.php app/Http/Controllers/Admin/FaseDefaultMappingController.php resources/views/admin/fase-mapping/create.blade.php resources/views/admin/fase-mapping/edit.blade.php tests/Feature/Akademik/FaseDefaultMappingControllerTest.php
git commit -m "fix(akademik): tutup 2 pintu IDOR FaseDefaultMapping -- cerminan perbaikan KurikulumAssignment"
```

---

## Task 4: `RppController::verify()` — Pola `effectiveLembagaId`

**Files:**
- Modify: `app/Http/Controllers/Admin/RppController.php:258-271`
- Test: cari/buat `tests/Feature/Admin/RppVerifyTest.php` (cek dulu apakah sudah ada test file utk `RppController::verify()`, kalau ada tambahkan di situ)

**Interfaces:** Tidak ada — perbaikan berdiri sendiri, tidak dipakai task lain.

- [ ] **Step 1: Baca method `verify()` saat ini**

Baca `app/Http/Controllers/Admin/RppController.php` baris 255-280 untuk konfirmasi struktur (nomor baris bisa bergeser).

- [ ] **Step 2: Tulis test yang gagal**

Cek dulu dengan `php artisan route:list --name=rpp` apakah route verify sudah punya nama pasti (mis. `admin.rpp.verify`). Tambahkan test baru:

```php
it('lets a yayasan-scope actor verify an RPP belonging to a lembaga under their own yayasan', function () {
    Permission::firstOrCreate(['name' => 'rpp.verify', 'guard_name' => 'web']);
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $role = Role::firstOrCreate(['name' => 'yayasan_verify_rpp', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->givePermissionTo(['rpp.verify']);
    $verifier = User::factory()->create(['yayasan_id' => $yayasan->id, 'lembaga_id' => null]);
    $verifier->assignRole($role);
    session(['active_lembaga_id' => $lembaga->id]);

    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $rpp = Rpp::factory()->create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'status' => StatusRpp::Diajukan]);

    $response = $this->actingAs($verifier)->patch(route('admin.rpp.verify', $rpp), ['status' => StatusRpp::Disetujui->value]);

    $response->assertRedirect();
    expect($rpp->fresh()->status)->toBe(StatusRpp::Disetujui);
});
```

Sesuaikan nama route, field request (`status` vs nama lain), dan factory `Rpp`/`Guru` dengan struktur aktual (baca `StoreRppRequest`/`VerifyRppRequest`/route list dulu untuk memastikan nama field dan route benar sebelum menulis test final).

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="lets a yayasan-scope actor verify an RPP"`
Expected: FAIL — `ValidationException` "tidak berwenang" dilempar (bug lama).

- [ ] **Step 4: Perbaiki `RppController::verify()`**

Ganti:
```php
verifierLembagaId: (int) $request->user()->lembaga_id,
```
Menjadi:
```php
$effectiveLembagaId = $request->user()->widestScopeLevel() === 'yayasan'
    ? session('active_lembaga_id')
    : $request->user()->lembaga_id;

abort_if($effectiveLembagaId === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum memverifikasi RPP.');
```
(tambahkan sebelum pemanggilan `$this->verifyRppAction->execute(...)`, lalu pakai `verifierLembagaId: (int) $effectiveLembagaId`) — pola identik `VerifyPengajuanRaporAction.php:27-29`.

- [ ] **Step 5: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter="lets a yayasan-scope actor verify an RPP"`
Expected: PASS.

- [ ] **Step 6: Regresi RPP**

Run: `php artisan test --filter=Rpp`
Expected: semua PASS (termasuk verify oleh actor lembaga-scope biasa yang sudah ada sebelumnya, tidak boleh regresi).

- [ ] **Step 7: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/RppController.php tests/Feature/Admin/RppVerifyTest.php
git commit -m "fix(akademik): verifikasi RPP oleh actor yayasan pakai effectiveLembagaId, bukan lembaga_id mentah yang selalu null"
```

---

## Task 5: Bentrok Jadwal Berbasis Waktu, Bukan ID Slot

**Files:**
- Modify: `app/Domains/Akademik/Actions/Jadwal/CreateJadwalPelajaranAction.php:52-63`
- Modify: `app/Domains/Akademik/Actions/Jadwal/UpdateJadwalPelajaranAction.php:54-66`
- Modify: `app/Domains/Sarpras/Actions/ValidateRoomClashAction.php`
- Test: `tests/Feature/Admin/JadwalPelajaranCrudTest.php` (cek nama file test existing dulu, tambahkan di situ) atau file baru `tests/Feature/Admin/JadwalPelajaranBentrokWaktuTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/Admin/JadwalPelajaranBentrokWaktuTest.php`:

```php
<?php

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use Spatie\Permission\Models\Permission;

it('menolak guru mengajar 2 kelas pada jam yang sama walau kelas-kelas itu pakai Pola Jam berbeda', function () {
    Permission::firstOrCreate(['name' => 'jadwal-pelajaran.create', 'guard_name' => 'web']);
    $lembaga = Lembaga::factory()->create();
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->givePermissionTo('jadwal-pelajaran.create');

    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    // Pola Jam A: dipakai kelas 7A. Jam ke-1 = 07:00-07:40.
    $polaJamA = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaJamA->id, 'hari' => 'senin', 'jam_mulai' => '07:00', 'jam_selesai' => '07:40']);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaJamA->id]);

    // Pola Jam B: dipakai kelas 8B, BEDA ID tapi jam wall-clock SAMA PERSIS (07:00-07:40 hari senin).
    $polaJamB = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamB = JamPelajaran::factory()->create(['pola_jam_id' => $polaJamB->id, 'hari' => 'senin', 'jam_mulai' => '07:00', 'jam_selesai' => '07:40']);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaJamB->id]);

    // Guru sudah dijadwalkan di kelas A pada jam A.
    $mapel = \App\Domains\Akademik\Models\MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Models\JadwalPelajaran::create([
        'kelas_id' => $kelasA->id, 'jam_pelajaran_id' => $jamA->id, 'guru_id' => $guru->id,
        'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);

    // Coba jadwalkan guru yang SAMA di kelas B pada jam B (ID beda, waktu wall-clock sama) -- HARUS ditolak.
    $response = $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasB->id, 'jam_pelajaran_id' => $jamB->id, 'guru_id' => $guru->id,
        'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id,
    ]);

    $response->assertSessionHasErrors('guru_id');
    expect(\App\Models\JadwalPelajaran::where('kelas_id', $kelasB->id)->exists())->toBeFalse();
});
```

Sesuaikan nama route (`admin.jadwal-pelajaran.store`), field request, dan factory yang dipakai dengan struktur aktual `JadwalPelajaranController`/`StoreJadwalPelajaranRequest` (baca dulu untuk konfirmasi nama field persis sebelum finalisasi test).

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menolak guru mengajar 2 kelas pada jam yang sama"`
Expected: FAIL — request berhasil (bukti bug: bentrok berbasis ID slot tidak mendeteksi 2 slot beda-ID dengan jam sama).

- [ ] **Step 3: Perbaiki `CreateJadwalPelajaranAction`**

Baca file penuh dulu (baris bisa bergeser). Sebelum blok "Validasi Bentrok Guru Pengampu", tambahkan resolusi `$jamPelajaranBaru`:
```php
        $jamPelajaranBaru = \App\Domains\Akademik\Models\JamPelajaran::findOrFail($data->jamPelajaranId);
```
Ganti blok bentrok guru (baris 52-63 versi lama):
```php
        // 3. Validasi Bentrok Guru Pengampu (berbasis waktu wall-clock, bukan ID slot --
        // 2 Pola Jam berbeda bisa punya jam_pelajaran_id berbeda untuk jam yang sama persis).
        $isGuruClash = JadwalPelajaran::query()
            ->where('guru_id', $data->guruId)
            ->where('semester_id', $data->semesterId)
            ->whereHas('jamPelajaran', function ($q) use ($jamPelajaranBaru) {
                $q->where('hari', $jamPelajaranBaru->hari)
                    ->where('jam_mulai', '<', $jamPelajaranBaru->jam_selesai)
                    ->where('jam_selesai', '>', $jamPelajaranBaru->jam_mulai);
            })
            ->exists();

        if ($isGuruClash) {
            throw ValidationException::withMessages([
                'guru_id' => 'Guru ini sudah mengajar kelas lain pada jam dan semester yang sama.',
            ]);
        }
```

- [ ] **Step 4: Perbaiki `UpdateJadwalPelajaranAction`** — pola identik, tambahkan `->where('id', '!=', $jadwal->id)` ke query (mengabaikan self-record, seperti versi lama).

- [ ] **Step 5: Perbaiki `ValidateRoomClashAction`**

Ganti:
```php
    public function execute(
        int $ruanganId,
        int $semesterId,
        int $jamPelajaranId,
        ?int $ignoreJadwalId = null
    ): bool {
        $jamPelajaranBaru = \App\Domains\Akademik\Models\JamPelajaran::findOrFail($jamPelajaranId);

        $query = JadwalPelajaran::query()
            ->where('ruangan_id', $ruanganId)
            ->where('semester_id', $semesterId)
            ->whereHas('jamPelajaran', function ($q) use ($jamPelajaranBaru) {
                $q->where('hari', $jamPelajaranBaru->hari)
                    ->where('jam_mulai', '<', $jamPelajaranBaru->jam_selesai)
                    ->where('jam_selesai', '>', $jamPelajaranBaru->jam_mulai);
            });

        if ($ignoreJadwalId) {
            $query->where('id', '!=', $ignoreJadwalId);
        }

        return $query->exists();
    }
```

- [ ] **Step 6: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter="menolak guru mengajar 2 kelas pada jam yang sama"`
Expected: PASS.

- [ ] **Step 7: Regresi Jadwal Pelajaran**

Run: `php artisan test --filter=JadwalPelajaran`
Expected: semua PASS (termasuk test bentrok berbasis slot yang sama, mis. 2 kelas yang MEMANG pakai `jam_pelajaran_id` sama persis harus tetap terdeteksi bentrok — perbaikan ini superset dari deteksi lama, bukan pengganti yang lebih sempit).

- [ ] **Step 8: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Actions/Jadwal/CreateJadwalPelajaranAction.php app/Domains/Akademik/Actions/Jadwal/UpdateJadwalPelajaranAction.php app/Domains/Sarpras/Actions/ValidateRoomClashAction.php tests/Feature/Admin/JadwalPelajaranBentrokWaktuTest.php
git commit -m "fix(akademik): deteksi bentrok guru/ruangan berbasis waktu wall-clock, bukan jam_pelajaran_id mentah"
```

---

## Task 6: Precedence Resolver Fase/Kurikulum

**Files:**
- Modify: `app/Domains/Akademik/Services/FaseDefaultResolver.php`
- Modify: `app/Domains/Akademik/Services/KurikulumAssignmentResolver.php`
- Test: `tests/Unit/Services/FaseDefaultResolverTest.php`, `tests/Unit/Services/KurikulumAssignmentResolverTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal (reproduksi persis skenario bug)**

Tambahkan di `tests/Unit/Services/FaseDefaultResolverTest.php`:

```php
it('tidak membiarkan tingkat spesifik yang TIDAK cocok mengalahkan catch-all dalam tier lembaga yang sama', function () {
    $faseTingkat5 = buatFaseResolverTest('lima', 5);
    $faseCatchAll = buatFaseResolverTest('umum', 0);
    $lembaga = Lembaga::factory()->create();

    // Row A: override utk tingkat 5 SAJA -- tidak relevan utk permintaan tingkat 7.
    FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SMP', 'tingkat' => '5', 'fase_id' => $faseTingkat5->id]);
    // Row B: catch-all utk lembaga ini (tingkat null) -- INI yang seharusnya dipilih utk tingkat 7.
    FaseDefaultMapping::create(['lembaga_id' => $lembaga->id, 'bentuk_pendidikan' => 'SMP', 'tingkat' => null, 'fase_id' => $faseCatchAll->id]);

    $hasil = app(FaseDefaultResolver::class)->resolve('SMP', '7', $lembaga->id);

    expect($hasil?->kode)->toBe('umum'); // BUKAN 'lima' -- tingkat 5 tidak cocok dengan permintaan tingkat 7
});
```

Tambahkan test cerminan di `tests/Unit/Services/KurikulumAssignmentResolverTest.php` (baca dulu struktur file existing untuk pola factory/setup yang benar, sesuaikan — `KurikulumAssignmentResolver::resolve()` butuh `$tahunAjaranId` tambahan sebagai parameter pertama).

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="tidak membiarkan tingkat spesifik yang TIDAK cocok"`
Expected: FAIL — hasil `'lima'`, bukan `'umum'` (bukti bug).

- [ ] **Step 3: Perbaiki `FaseDefaultResolver`**

Ganti isi `resolve()`:
```php
    public function resolve(string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): ?Fase
    {
        $query = FaseDefaultMapping::where('bentuk_pendidikan', $bentukPendidikan)
            ->where(function ($q) use ($lembagaId) {
                $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->where(function ($q) use ($tingkat) {
                $q->where('tingkat', $tingkat)->orWhereNull('tingkat');
            })
            ->orderByRaw('lembaga_id IS NULL')
            ->orderByRaw('tingkat IS NULL');

        $match = $query->first();

        return $match?->fase;
    }
```

- [ ] **Step 4: Perbaiki `KurikulumAssignmentResolver`** — pola identik, tambahkan filter WHERE `tingkat` yang sama sebelum `orderBy`, pertahankan filter `tahun_ajaran_id` yang sudah ada (EKSAK, tidak masuk precedence).

- [ ] **Step 5: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=FaseDefaultResolverTest`
Run: `php artisan test --filter=KurikulumAssignmentResolverTest`
Expected: semua PASS, termasuk test lama (precedence lembaga-vs-global dan tingkat-exact-vs-catchall yang SUDAH ADA sebelumnya) — perbaikan ini menambah filter, bukan mengubah urutan `orderBy` yang sudah benar untuk kasus-kasus itu.

- [ ] **Step 6: Regresi konsumen resolver**

Run: `php artisan test --filter=KelasController`
Expected: semua PASS (endpoint `faseSuggestion` konsumen `FaseDefaultResolver`).

- [ ] **Step 7: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Services/FaseDefaultResolver.php app/Domains/Akademik/Services/KurikulumAssignmentResolver.php tests/Unit/Services/FaseDefaultResolverTest.php tests/Unit/Services/KurikulumAssignmentResolverTest.php
git commit -m "fix(akademik): resolver Fase/Kurikulum -- filter tingkat di WHERE, bukan cuma ORDER BY, cegah tingkat tak cocok mengalahkan catch-all"
```

---

## Task 7: Full Test Suite Final

**Files:** Tidak ada file diubah — verifikasi akhir.

- [ ] **Step 1: Pastikan tidak ada proses test lain berjalan**

Run: `ps aux | grep artisan | grep -v grep`
Expected: kosong.

- [ ] **Step 2: Jalankan full suite sendirian**

Run: `php artisan test --compact`
Expected: SEMUA test PASS, 0 failures (kecuali kegagalan yang SUDAH DIKETAHUI tidak berkaitan, mis. test SPMB dengan data Faker acak mengandung apostrof — kalau muncul, jalankan ulang sendirian untuk konfirmasi flaky).

- [ ] **Step 3: Pint final**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` atau auto-fix tanpa error.

---

## Self-Review

**1. Spec coverage**: §2.1 (IDOR create + existing-row) → Task 2 & 3. §2.2 (RPP verify) → Task 4. §2.3 (bentrok jadwal) → Task 5. §2.4 (resolver precedence) → Task 6. §4 test #1-18 + #15a/b/c → dipetakan ke Task 2/3 (test baru menggantikan sebagian nomor spec yang diringkas jadi test lebih presisi setelah investigasi test existing). §3 Non-Goals (Observer, 2 Minor, session-staleness controller lain) → tidak ada task yang menyentuhnya.

**2. Placeholder scan**: tidak ada TBD; beberapa step (Task 4 Step 2, Task 5 Step 1) minta implementer baca struktur aktual dulu SEBELUM finalisasi nama field/route karena belum dikonfirmasi persis saat plan ditulis — ini instruksi eksplisit "baca dulu", bukan placeholder kosong.

**3. Type consistency**: `resolveLembagaId(User $actor, ?int $lembagaIdDiminta): ?int` dipakai identik nama/parameter di Task 1, 2, 3. `authorizeExistingAssignmentScope`/`authorizeExistingMappingScope` dipakai konsisten di Task 2/3.

**4. Temuan tambahan penting yang ditemukan SAAT menulis plan ini (bukan di spec)**: 4 test existing (`KurikulumAssignmentControllerTest.php` x3 lewat helper `actingAsPlatformKurikulumManager` yang sebenarnya bikin actor YAYASAN, dan `FaseDefaultMappingControllerTest.php` x3) meng-encode perilaku LAMA (yayasan=platform) sebagai "benar". Task 2 Step 1 & 8, Task 3 Step 1 & 7 menangani ini eksplisit — bukan dibiarkan merah atau dihapus diam-diam, tapi ditulis ulang untuk menguji aturan BARU. `KurikulumAssignmentDestroyGuardTest.php` juga punya 1 test yang perlu penyesuaian SETUP (bukan assertion) di Task 2 Step 9.
