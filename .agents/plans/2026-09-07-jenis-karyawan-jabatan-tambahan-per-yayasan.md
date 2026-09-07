# Jenis Karyawan & Jabatan Tambahan Master — Per-Yayasan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ubah `jenis_karyawan_master` dan `jabatan_tambahan_master` dari katalog global lintas-SEMUA-yayasan menjadi data milik 1 yayasan spesifik, tanpa baris nasional/global.

**Architecture:** Tambah kolom `yayasan_id` (NOT NULL, FK) ke kedua tabel + global scope model (`YayasanScope`, pola sama seperti `Person`) + auto-isi `yayasan_id` di Action saat create + guard delete yang dihitung ulang per-yayasan-pemilik-row (bukan global, bukan per-aktor-yang-login).

**Tech Stack:** Laravel 12 / PHP 8.3, Pest, Eloquent (global scope), migrasi dengan backfill data.

## Global Constraints

- **Tidak ada baris nasional/global** — `yayasan_id` NOT NULL di kedua tabel, tanpa pengecualian baris apapun.
- **Backfill data existing**: baris belum dipakai → `yayasan_id = 1`; baris dipakai TEPAT 1 yayasan → assign ke yayasan itu; baris dipakai LEBIH DARI 1 yayasan (tidak terjadi di data sekarang, TETAP WAJIB ditangani, JANGAN disederhanakan) → baris asli ke yayasan pertama (id terkecil), clone + repoint FK untuk yayasan lain.
- **TIDAK mengerjakan** hook seeder onboarding yayasan baru, TIDAK mengerjakan kloning starter catalog ke yayasan lain (di luar scope spec, dicatat di Task 3).
- Model pakai pola PERSIS `Person::booted()` (`static::addGlobalScope(new YayasanScope)`) — BUKAN trait baru (cuma 2 model, trait baru untuk 2 pemakaian melanggar YAGNI).
- `yayasan_id` dihitung di Action/Controller (pola `AkunOrangTuaGenerator::buat($yayasanId)`) — BUKAN masuk DTO (bukan input form, tidak boleh dioverride payload request).
- Guard delete WAJIB scoped ke `yayasan_id` milik ROW yang mau dihapus — BUKAN aktor yang login, BUKAN global lintas semua yayasan. Guru/karyawan di yayasan lain TIDAK BOLEH memblokir delete.
- Tidak pakai worktree, kerja langsung di branch `rbac-v2`.
- Tidak ada unique index level DATABASE untuk kolom `nama` di kedua tabel saat ini (dikonfirmasi dari `database/schema/mysql-schema.sql` — cuma `PRIMARY KEY(id)`), jadi migrasi CUKUP menambah `UNIQUE(yayasan_id, nama)` baru, TIDAK perlu drop index lama.

---

## Task 1: `JenisKaryawanMaster` — Migrasi, Model, Factory, Action, Controller, Test

**Files:**
- Create: migrasi baru via `php artisan make:migration add_yayasan_id_to_jenis_karyawan_master_table --no-interaction`
- Modify: `app/Domains/Sdm/Models/JenisKaryawanMaster.php`
- Modify: `database/factories/JenisKaryawanMasterFactory.php`
- Modify: `app/Domains/Sdm/Actions/JenisKaryawan/CreateJenisKaryawanAction.php`
- Modify: `app/Domains/Sdm/Actions/JenisKaryawan/DeleteJenisKaryawanAction.php`
- Modify: `app/Http/Controllers/Lembaga/Sdm/JenisKaryawanMasterController.php`
- Modify: `tests/Feature/Admin/JenisKaryawanMasterCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\Scopes\YayasanScope` (sudah ada, dipakai `Person`), `App\Models\Scopes\TenantScope` (sudah ada), `App\Models\Karyawan` (kolom `yayasan_id` sudah di `$fillable`, terverifikasi `app/Models/Karyawan.php:27`).
- Produces: `CreateJenisKaryawanAction::execute(JenisKaryawanMasterData $data, int $yayasanId): JenisKaryawanMaster` — signature BARU (tambah parameter `$yayasanId`), dipakai controller di task ini saja (tidak dikonsumsi task lain).

### Step 1: Buat migrasi

```bash
php artisan make:migration add_yayasan_id_to_jenis_karyawan_master_table --no-interaction
```

### Step 2: Isi migrasi

Buka file migrasi yang baru dibuat (nama file mengandung timestamp otomatis, cari di `database/migrations/` dengan pola `*_add_yayasan_id_to_jenis_karyawan_master_table.php`), isi persis:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_karyawan_master', function (Blueprint $table) {
            $table->foreignId('yayasan_id')->nullable()->after('id')->constrained('yayasan')->cascadeOnDelete();
        });

        $this->backfillYayasanId();

        Schema::table('jenis_karyawan_master', function (Blueprint $table) {
            $table->unsignedBigInteger('yayasan_id')->nullable(false)->change();
            $table->unique(['yayasan_id', 'nama']);
        });
    }

    /**
     * Untuk tiap baris jenis_karyawan_master: cari yayasan mana saja yang memakainya lewat
     * karyawan.jenis_karyawan_id (Karyawan punya kolom yayasan_id langsung). Belum dipakai
     * sama sekali -> assign ke yayasan_id=1. Dipakai tepat 1 yayasan -> assign ke situ. Dipakai
     * lebih dari 1 yayasan (tidak terjadi di data sekarang, tapi harus ditangani) -> baris asli
     * ke yayasan id terkecil, baris lain di-clone dan karyawan.jenis_karyawan_id direpoint ke
     * clone masing-masing.
     */
    private function backfillYayasanId(): void
    {
        $jenisIds = DB::table('jenis_karyawan_master')->pluck('id');

        foreach ($jenisIds as $jenisId) {
            $yayasanIds = DB::table('karyawan')
                ->where('jenis_karyawan_id', $jenisId)
                ->whereNotNull('yayasan_id')
                ->distinct()
                ->pluck('yayasan_id')
                ->sort()
                ->values();

            if ($yayasanIds->isEmpty()) {
                DB::table('jenis_karyawan_master')->where('id', $jenisId)->update(['yayasan_id' => 1]);

                continue;
            }

            $primaryYayasanId = $yayasanIds->first();
            DB::table('jenis_karyawan_master')->where('id', $jenisId)->update(['yayasan_id' => $primaryYayasanId]);

            foreach ($yayasanIds->skip(1) as $otherYayasanId) {
                $original = DB::table('jenis_karyawan_master')->where('id', $jenisId)->first();

                $cloneId = DB::table('jenis_karyawan_master')->insertGetId([
                    'nama' => $original->nama,
                    'is_konselor' => $original->is_konselor,
                    'yayasan_id' => $otherYayasanId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('karyawan')
                    ->where('jenis_karyawan_id', $jenisId)
                    ->where('yayasan_id', $otherYayasanId)
                    ->update(['jenis_karyawan_id' => $cloneId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('jenis_karyawan_master', function (Blueprint $table) {
            $table->dropUnique(['yayasan_id', 'nama']);
            $table->dropForeign(['yayasan_id']);
            $table->dropColumn('yayasan_id');
        });
    }
};
```

### Step 3: Jalankan migrasi, verifikasi

Run: `php artisan migrate`
Expected: migrasi baru berhasil dijalankan tanpa error. Verifikasi manual cepat:
Run: `php artisan tinker --execute 'echo App\Domains\Sdm\Models\JenisKaryawanMaster::withoutGlobalScopes()->whereNull("yayasan_id")->count();'`
Expected: `0` (semua baris sudah terisi `yayasan_id`).

### Step 4: Model — tambah scope

Edit `app/Domains/Sdm/Models/JenisKaryawanMaster.php`:

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Models\Karyawan;
use App\Models\Scopes\YayasanScope;
use Database\Factories\JenisKaryawanMasterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisKaryawanMaster extends Model
{
    use HasFactory;

    protected static function newFactory(): JenisKaryawanMasterFactory
    {
        return JenisKaryawanMasterFactory::new();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new YayasanScope);
    }

    protected $table = 'jenis_karyawan_master';

    protected $fillable = ['yayasan_id', 'nama', 'is_konselor'];

    protected function casts(): array
    {
        return [
            'is_konselor' => 'boolean',
        ];
    }

    public function karyawan(): HasMany
    {
        return $this->hasMany(Karyawan::class, 'jenis_karyawan_id');
    }
}
```

### Step 5: Factory — tambah `yayasan_id`

Edit `database/factories/JenisKaryawanMasterFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class JenisKaryawanMasterFactory extends Factory
{
    protected $model = JenisKaryawanMaster::class;

    public function definition(): array
    {
        return [
            'yayasan_id' => Yayasan::factory(),
            'nama' => $this->faker->unique()->randomElement(['Psikolog', 'Konselor BK', 'Terapis', 'Pekerja Sosial']),
            'is_konselor' => false,
        ];
    }

    public function konselor(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_konselor' => true,
        ]);
    }
}
```

### Step 6: Action `CreateJenisKaryawanAction` — terima `$yayasanId`

Edit `app/Domains/Sdm/Actions/JenisKaryawan/CreateJenisKaryawanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JenisKaryawan;

use App\Domains\Sdm\DataTransferObjects\JenisKaryawanMasterData;
use App\Domains\Sdm\Models\JenisKaryawanMaster;

final class CreateJenisKaryawanAction
{
    public function execute(JenisKaryawanMasterData $data, int $yayasanId): JenisKaryawanMaster
    {
        return JenisKaryawanMaster::create([
            'nama' => $data->nama,
            'yayasan_id' => $yayasanId,
        ])->loadCount('karyawan');
    }
}
```

### Step 7: Action `DeleteJenisKaryawanAction` — guard scoped ke yayasan pemilik row

Edit `app/Domains/Sdm/Actions/JenisKaryawan/DeleteJenisKaryawanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JenisKaryawan;

use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Scopes\TenantScope;
use Illuminate\Validation\ValidationException;

final class DeleteJenisKaryawanAction
{
    public function execute(JenisKaryawanMaster $jenisKaryawanMaster): void
    {
        $karyawanCount = Karyawan::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $jenisKaryawanMaster->yayasan_id)
            ->where('jenis_karyawan_id', $jenisKaryawanMaster->id)
            ->count();

        if ($karyawanCount > 0) {
            throw ValidationException::withMessages([
                'jenis_karyawan' => "Jenis karyawan tidak dapat dihapus karena masih dipakai oleh {$karyawanCount} karyawan.",
            ]);
        }

        $jenisKaryawanMaster->delete();
    }
}
```

### Step 8: Controller — hitung `$yayasanId`, validasi unique ter-scope

Edit `app/Http/Controllers/Lembaga/Sdm/JenisKaryawanMasterController.php`, method `store()` dan `update()`:

```php
public function store(Request $request, CreateJenisKaryawanAction $action): JsonResponse|RedirectResponse
{
    $this->authorize('jenis-karyawan-master.create');

    $yayasanId = auth()->user()->yayasan_id ?? auth()->user()->lembaga?->yayasan_id;
    abort_if($yayasanId === null, 422, 'Konteks yayasan tidak dapat ditentukan.');

    $data = $request->validate([
        'nama' => ['required', 'string', 'max:255', Rule::unique('jenis_karyawan_master', 'nama')->where('yayasan_id', $yayasanId)],
    ]);

    $item = $action->execute(JenisKaryawanMasterData::fromArray($data), $yayasanId);

    if ($request->wantsJson()) {
        return response()->json([
            'message' => 'Jenis karyawan berhasil ditambahkan.',
            'item' => $item,
        ], 201);
    }

    return back()->with('success', 'Jenis karyawan berhasil ditambahkan.');
}

public function update(Request $request, JenisKaryawanMaster $jenisKaryawanMaster, UpdateJenisKaryawanAction $action): JsonResponse|RedirectResponse
{
    $this->authorize('jenis-karyawan-master.edit');

    $data = $request->validate([
        'nama' => [
            'required', 'string', 'max:255',
            Rule::unique('jenis_karyawan_master', 'nama')
                ->where('yayasan_id', $jenisKaryawanMaster->yayasan_id)
                ->ignore($jenisKaryawanMaster->id),
        ],
    ]);

    $item = $action->execute($jenisKaryawanMaster, JenisKaryawanMasterData::fromArray($data));

    if ($request->wantsJson()) {
        return response()->json([
            'message' => 'Jenis karyawan berhasil diperbarui.',
            'item' => $item,
        ], 200);
    }

    return back()->with('success', 'Jenis karyawan berhasil diperbarui.');
}
```

`index()` dan `destroy()` TIDAK berubah signature-nya — `index()` otomatis ter-scope lewat `YayasanScope` di model (baris `JenisKaryawanMaster::withCount('karyawan')->orderBy('nama')->get()` tetap sama persis, hanya hasil query-nya yang otomatis menyempit).

**Keputusan yang WAJIB didokumentasikan di laporan task, BUKAN diasumsikan diam-diam**: `withCount('karyawan')` di `index()` menghitung lewat relasi `karyawan()` yang TIDAK bypass `TenantScope`-nya `Karyawan` sendiri — untuk aktor lembaga-scope, angka yang tampil adalah "berapa karyawan DI LEMBAGA aktor" (bukan seluruh yayasan), sedangkan untuk aktor yayasan-scope mode "Semua Lembaga" itu otomatis agregat seluruh yayasan (lewat `TenantScope`'s pool pattern, karena `Karyawan.$fillable` punya `yayasan_id`). Ini KONSISTEN dengan cara `index()` yang lain di codebase menampilkan angka "sesuai konteks scope aktor saat ini", BUKAN "total pemakaian di seluruh yayasan pemilik row" seperti guard delete di Step 7 (yang sengaja dihitung LEBIH LUAS, mencakup semua lembaga di yayasan itu, karena delete adalah operasi yang harus aman terlepas dari lembaga mana yang sedang aktif di switcher). Dua angka ini BOLEH berbeda secara sah (index = "pandangan aktor sekarang", guard = "apakah aman dihapus ditinjau dari seluruh yayasan pemilik") — JANGAN disamakan paksa, catat perbedaan ini secara eksplisit di laporan task supaya tidak disalahpahami sebagai bug oleh reviewer.

### Step 9: Perbaiki fixture test existing (2 titik, WAJIB sebelum tambah test baru)

Edit `tests/Feature/Admin/JenisKaryawanMasterCrudTest.php`:

**9a.** Test `'rejects a duplicate nama'` (baris 65-71) — tambahkan `'yayasan_id' => $manager->yayasan_id` supaya baris duplikat berada di yayasan yang SAMA dengan aktor (kalau tidak, setelah `JenisKaryawanMasterFactory` punya default `yayasan_id` random baru, test ini salah menguji skenario "duplikat di yayasan lain" alih-alih "duplikat di yayasan sendiri"):

```php
it('rejects a duplicate nama', function () {
    $manager = actingAsJenisKaryawanManager();
    JenisKaryawanMaster::factory()->create(['nama' => 'Psikolog', 'yayasan_id' => $manager->yayasan_id]);

    $this->actingAs($manager)->postJson(route('admin.jenis-karyawan-master.store'), ['nama' => 'Psikolog'])
        ->assertStatus(422);
});
```

**9b.** Test `'blocks deleting a jenis karyawan that is still in use by a karyawan'` (baris 83-93) — tambahkan `'yayasan_id' => $manager->yayasan_id` eksplisit ke `Karyawan::factory()->create([...])` (`KaryawanFactory` punya default `yayasan_id` random independen, TIDAK otomatis cocok dengan `$manager->yayasan_id` hanya karena `lembaga_id` di-set):

```php
it('blocks deleting a jenis karyawan that is still in use by a karyawan', function () {
    $manager = actingAsJenisKaryawanManager();
    $jenis = JenisKaryawanMaster::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    Karyawan::factory()->create(['jenis_karyawan_id' => $jenis->id, 'lembaga_id' => $lembaga->id, 'yayasan_id' => $manager->yayasan_id]);

    $this->actingAs($manager)->deleteJson(route('admin.jenis-karyawan-master.destroy', $jenis))
        ->assertStatus(422);

    expect(JenisKaryawanMaster::find($jenis->id))->not->toBeNull();
});
```

**9c.** Test lain di file yang sama (`'creates a jenis karyawan via JSON'`, `'updates a jenis karyawan'`, `'deletes a jenis karyawan that is not in use'`) — baca ulang tiap satu, tambahkan `'yayasan_id' => $manager->yayasan_id` ke `JenisKaryawanMaster::factory()->create([...])` di mana pun dipakai, supaya baris yang dioperasikan konsisten milik yayasan aktor (kalau tidak, `update()`/`destroy()` masih akan berhasil karena route-model-binding TIDAK di-scope ketat di controller ini — TAPI test-nya jadi tidak representatif skenario nyata; perbaiki untuk representativitas, bukan karena ada bug 404 yang mengharuskan).

Run: `php artisan test tests/Feature/Admin/JenisKaryawanMasterCrudTest.php --compact`
Expected: SEMUA test existing (7 test) tetap PASS setelah fixture diperbaiki.

### Step 10: Tambah test baru (RED dulu, tulis sebelum Step 4-8 kalau mau TDD murni — TAPI karena scope model/migrasi adalah fondasi yang harus ada duluan supaya kolom `yayasan_id` bahkan bisa dipakai test, urutan praktis di task ini: migrasi+model+factory (Step 1-5) dulu baru test RED-GREEN untuk Action/Controller di Step 6-8. Tambahkan test berikut SETELAH Step 9, verifikasi GREEN langsung karena fix sudah diterapkan di step sebelumnya — TIDAK perlu RED terpisah untuk test BARU ini karena tidak ada regresi tersembunyi yang perlu dibuktikan gagal dulu, beda dengan kasus di plan Orang-Tua-Siswa-Person yang punya fixture false-negative tersamar)**:

```php
it('does not leak jenis karyawan across yayasan boundaries on the index page', function () {
    $managerA = actingAsJenisKaryawanManager();
    $jenisA = JenisKaryawanMaster::factory()->create(['nama' => 'Milik Yayasan A', 'yayasan_id' => $managerA->yayasan_id]);
    JenisKaryawanMaster::factory()->create(['nama' => 'Milik Yayasan B']);

    $response = $this->actingAs($managerA)->getJson(route('admin.jenis-karyawan-master.index'));

    $response->assertOk();
    $ids = collect($response->json('items'))->pluck('id');
    expect($ids)->toContain($jenisA->id);
    expect($ids)->toHaveCount(1);
});

it('assigns yayasan_id from the acting manager automatically, ignoring any yayasan_id in the request payload', function () {
    $manager = actingAsJenisKaryawanManager();
    $lainYayasan = Yayasan::factory()->create();

    $this->actingAs($manager)->postJson(route('admin.jenis-karyawan-master.store'), [
        'nama' => 'Satpam Baru',
        'yayasan_id' => $lainYayasan->id,
    ])->assertCreated();

    $item = JenisKaryawanMaster::where('nama', 'Satpam Baru')->first();
    expect($item->yayasan_id)->toBe($manager->yayasan_id);
});

it('allows two different yayasan to use the exact same jenis karyawan nama', function () {
    $managerA = actingAsJenisKaryawanManager();
    JenisKaryawanMaster::factory()->create(['nama' => 'Satpam']);

    $this->actingAs($managerA)->postJson(route('admin.jenis-karyawan-master.store'), ['nama' => 'Satpam'])
        ->assertCreated();
});

it('does not block deleting a jenis karyawan that is only in use by a karyawan in a different yayasan', function () {
    $managerA = actingAsJenisKaryawanManager();
    $jenisA = JenisKaryawanMaster::factory()->create(['yayasan_id' => $managerA->yayasan_id]);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    Karyawan::factory()->create(['jenis_karyawan_id' => $jenisA->id, 'lembaga_id' => $lembagaB->id, 'yayasan_id' => $yayasanB->id]);

    $this->actingAs($managerA)->deleteJson(route('admin.jenis-karyawan-master.destroy', $jenisA))
        ->assertOk();

    expect(JenisKaryawanMaster::find($jenisA->id))->toBeNull();
});
```

Run: `php artisan test tests/Feature/Admin/JenisKaryawanMasterCrudTest.php --compact`
Expected: 11 test PASS (7 existing + 4 baru).

### Step 11: Pint

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` atau perbaikan otomatis diterapkan.

### Step 12: Commit

```bash
git add database/migrations/*_add_yayasan_id_to_jenis_karyawan_master_table.php \
    app/Domains/Sdm/Models/JenisKaryawanMaster.php \
    database/factories/JenisKaryawanMasterFactory.php \
    app/Domains/Sdm/Actions/JenisKaryawan/CreateJenisKaryawanAction.php \
    app/Domains/Sdm/Actions/JenisKaryawan/DeleteJenisKaryawanAction.php \
    app/Http/Controllers/Lembaga/Sdm/JenisKaryawanMasterController.php \
    tests/Feature/Admin/JenisKaryawanMasterCrudTest.php
git commit -m "fix(sdm): jenis karyawan master jadi per-yayasan, bukan katalog global lintas sistem

Tabel jenis_karyawan_master sebelumnya tidak punya kolom tenant sama
sekali, dibagi lintas SEMUA yayasan di sistem. Tambah yayasan_id NOT
NULL dengan backfill defensif (assign ke pemakai existing, clone+repoint
kalau ada baris dipakai >1 yayasan), scope model via YayasanScope (pola
sama Person), guard delete dihitung ulang per-yayasan-pemilik-row.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: `JabatanTambahanMaster` — Migrasi, Model, Factory Baru, Action, Controller, Test

**Files:**
- Create: migrasi baru via `php artisan make:migration add_yayasan_id_to_jabatan_tambahan_master_table --no-interaction`
- Create: `database/factories/JabatanTambahanMasterFactory.php` (BELUM ADA sama sekali)
- Modify: `app/Domains/Sdm/Models/JabatanTambahanMaster.php`
- Modify: `app/Domains/Sdm/Actions/JabatanTambahan/CreateJabatanTambahanAction.php`
- Modify: `app/Domains/Sdm/Actions/JabatanTambahan/DeleteJabatanTambahanAction.php`
- Modify: `app/Http/Controllers/Lembaga/Sdm/JabatanTambahanMasterController.php`
- Modify: `tests/Feature/Admin/JabatanTambahanMasterCrudTest.php` (rewrite fixture menyeluruh)

**Interfaces:**
- Consumes: `App\Models\Scopes\YayasanScope`, `App\Models\Lembaga` (`Lembaga::where('yayasan_id', ...)->pluck('id')`, pola dipakai berulang sepanjang audit scope yayasan/lembaga sesi ini).
- Produces: `CreateJabatanTambahanAction::execute(JabatanTambahanMasterData $data, int $yayasanId): JabatanTambahanMaster` — signature BARU, sama bentuk dengan Task 1.

### Step 1: Buat migrasi

```bash
php artisan make:migration add_yayasan_id_to_jabatan_tambahan_master_table --no-interaction
```

### Step 2: Isi migrasi

`Guru` TIDAK punya `yayasan_id` langsung (cuma `lembaga_id`, terverifikasi `app/Models/Guru.php:33`) — backfill harus join lewat `guru.lembaga_id` → `lembaga.yayasan_id`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jabatan_tambahan_master', function (Blueprint $table) {
            $table->foreignId('yayasan_id')->nullable()->after('id')->constrained('yayasan')->cascadeOnDelete();
        });

        $this->backfillYayasanId();

        Schema::table('jabatan_tambahan_master', function (Blueprint $table) {
            $table->unsignedBigInteger('yayasan_id')->nullable(false)->change();
            $table->unique(['yayasan_id', 'nama']);
        });
    }

    /**
     * Sama seperti backfill jenis_karyawan_master, tapi lewat guru_jabatan_tambahan (pivot) ->
     * guru.lembaga_id -> lembaga.yayasan_id, karena Guru tidak punya kolom yayasan_id langsung.
     */
    private function backfillYayasanId(): void
    {
        $jabatanIds = DB::table('jabatan_tambahan_master')->pluck('id');

        foreach ($jabatanIds as $jabatanId) {
            $yayasanIds = DB::table('guru_jabatan_tambahan')
                ->join('guru', 'guru_jabatan_tambahan.guru_id', '=', 'guru.id')
                ->join('lembaga', 'guru.lembaga_id', '=', 'lembaga.id')
                ->where('guru_jabatan_tambahan.jabatan_tambahan_master_id', $jabatanId)
                ->distinct()
                ->pluck('lembaga.yayasan_id')
                ->sort()
                ->values();

            if ($yayasanIds->isEmpty()) {
                DB::table('jabatan_tambahan_master')->where('id', $jabatanId)->update(['yayasan_id' => 1]);

                continue;
            }

            $primaryYayasanId = $yayasanIds->first();
            DB::table('jabatan_tambahan_master')->where('id', $jabatanId)->update(['yayasan_id' => $primaryYayasanId]);

            foreach ($yayasanIds->skip(1) as $otherYayasanId) {
                $original = DB::table('jabatan_tambahan_master')->where('id', $jabatanId)->first();

                $cloneId = DB::table('jabatan_tambahan_master')->insertGetId([
                    'nama' => $original->nama,
                    'kelompok' => $original->kelompok,
                    'yayasan_id' => $otherYayasanId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $lembagaIdsOtherYayasan = DB::table('lembaga')->where('yayasan_id', $otherYayasanId)->pluck('id');

                DB::table('guru_jabatan_tambahan')
                    ->join('guru', 'guru_jabatan_tambahan.guru_id', '=', 'guru.id')
                    ->where('guru_jabatan_tambahan.jabatan_tambahan_master_id', $jabatanId)
                    ->whereIn('guru.lembaga_id', $lembagaIdsOtherYayasan)
                    ->update(['guru_jabatan_tambahan.jabatan_tambahan_master_id' => $cloneId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('jabatan_tambahan_master', function (Blueprint $table) {
            $table->dropUnique(['yayasan_id', 'nama']);
            $table->dropForeign(['yayasan_id']);
            $table->dropColumn('yayasan_id');
        });
    }
};
```

### Step 3: Jalankan migrasi, verifikasi

Run: `php artisan migrate`
Expected: sukses.
Run: `php artisan tinker --execute 'echo App\Domains\Sdm\Models\JabatanTambahanMaster::withoutGlobalScopes()->whereNull("yayasan_id")->count();'`
Expected: `0`.

### Step 4: Model — tambah scope

Edit `app/Domains/Sdm/Models/JabatanTambahanMaster.php`:

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Models\Guru;
use App\Models\GuruJabatanTambahan;
use App\Models\Scopes\YayasanScope;
use Database\Factories\JabatanTambahanMasterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JabatanTambahanMaster extends Model
{
    use HasFactory;

    protected static function newFactory(): JabatanTambahanMasterFactory
    {
        return JabatanTambahanMasterFactory::new();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new YayasanScope);
    }

    protected $table = 'jabatan_tambahan_master';

    protected $fillable = ['yayasan_id', 'nama', 'kelompok'];

    public function guru(): BelongsToMany
    {
        return $this->belongsToMany(Guru::class, 'guru_jabatan_tambahan')
            ->withPivot(['mulai_periode', 'akhir_periode', 'no_sk'])
            ->withTimestamps()
            ->using(GuruJabatanTambahan::class);
    }
}
```

(Model ini SEBELUMNYA tidak pakai `HasFactory` sama sekali karena tidak ada factory — sekarang ditambahkan bersamaan dengan Step 5.)

### Step 5: Buat Factory baru

```bash
php artisan make:factory JabatanTambahanMasterFactory --model=App/Domains/Sdm/Models/JabatanTambahanMaster
```

Isi `database/factories/JabatanTambahanMasterFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Domains\Sdm\Models\JabatanTambahanMaster;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\Factory;

class JabatanTambahanMasterFactory extends Factory
{
    protected $model = JabatanTambahanMaster::class;

    public function definition(): array
    {
        return [
            'yayasan_id' => Yayasan::factory(),
            'nama' => $this->faker->unique()->jobTitle(),
            'kelompok' => $this->faker->randomElement(['struktural', 'fungsional']),
        ];
    }
}
```

### Step 6: Action `CreateJabatanTambahanAction`

Edit `app/Domains/Sdm/Actions/JabatanTambahan/CreateJabatanTambahanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JabatanTambahan;

use App\Domains\Sdm\DataTransferObjects\JabatanTambahanMasterData;
use App\Domains\Sdm\Models\JabatanTambahanMaster;

final class CreateJabatanTambahanAction
{
    public function execute(JabatanTambahanMasterData $data, int $yayasanId): JabatanTambahanMaster
    {
        return JabatanTambahanMaster::create([
            'nama' => $data->nama,
            'kelompok' => $data->kelompok,
            'yayasan_id' => $yayasanId,
        ])->loadCount(['guru' => fn ($q) => $q->withoutGlobalScopes()]);
    }
}
```

### Step 7: Action `DeleteJabatanTambahanAction`

Edit `app/Domains/Sdm/Actions/JabatanTambahan/DeleteJabatanTambahanAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions\JabatanTambahan;

use App\Domains\Sdm\Models\JabatanTambahanMaster;
use App\Models\Lembaga;
use Illuminate\Validation\ValidationException;

final class DeleteJabatanTambahanAction
{
    public function execute(JabatanTambahanMaster $jabatanTambahanMaster): void
    {
        $lembagaIdsYayasan = Lembaga::where('yayasan_id', $jabatanTambahanMaster->yayasan_id)->pluck('id');

        $guruCount = $jabatanTambahanMaster->guru()
            ->withoutGlobalScopes()
            ->whereIn('guru.lembaga_id', $lembagaIdsYayasan)
            ->count();

        if ($guruCount > 0) {
            throw ValidationException::withMessages([
                'jabatan' => "Jabatan tidak dapat dihapus karena saat ini masih disandang oleh {$guruCount} Guru aktif. Lepaskan tautan jabatan pada guru bersangkutan sebelum menghapusnya.",
            ]);
        }

        $jabatanTambahanMaster->delete();
    }
}
```

### Step 8: Controller — hitung `$yayasanId`, validasi unique ter-scope

Edit `app/Http/Controllers/Lembaga/Sdm/JabatanTambahanMasterController.php`, method `store()` dan `update()`:

```php
public function store(Request $request, CreateJabatanTambahanAction $action): JsonResponse|RedirectResponse
{
    $this->authorize('jabatan-tambahan-master.create');

    $yayasanId = auth()->user()->yayasan_id ?? auth()->user()->lembaga?->yayasan_id;
    abort_if($yayasanId === null, 422, 'Konteks yayasan tidak dapat ditentukan.');

    $data = $request->validate([
        'nama' => ['required', 'string', 'max:255', Rule::unique('jabatan_tambahan_master', 'nama')->where('yayasan_id', $yayasanId)],
        'kelompok' => ['required', Rule::in(['struktural', 'fungsional'])],
    ]);

    $item = $action->execute(JabatanTambahanMasterData::fromArray($data), $yayasanId);

    if ($request->wantsJson()) {
        return response()->json([
            'message' => 'Jabatan tambahan berhasil dirilis',
            'item' => $item,
        ], 201);
    }

    return back()->with('success', 'Jabatan tambahan berhasil ditambahkan.');
}

public function update(Request $request, JabatanTambahanMaster $jabatanTambahanMaster, UpdateJabatanTambahanAction $action): JsonResponse|RedirectResponse
{
    $this->authorize('jabatan-tambahan-master.edit');

    $data = $request->validate([
        'nama' => [
            'required', 'string', 'max:255',
            Rule::unique('jabatan_tambahan_master', 'nama')
                ->where('yayasan_id', $jabatanTambahanMaster->yayasan_id)
                ->ignore($jabatanTambahanMaster->id),
        ],
        'kelompok' => ['required', Rule::in(['struktural', 'fungsional'])],
    ]);

    $item = $action->execute($jabatanTambahanMaster, JabatanTambahanMasterData::fromArray($data));

    if ($request->wantsJson()) {
        return response()->json([
            'message' => 'Data jabatan berhasil diperbarui',
            'item' => $item,
        ], 200);
    }

    return back()->with('success', 'Jabatan tambahan berhasil diperbarui.');
}
```

`index()`/`destroy()` TIDAK berubah signature. Sama seperti Task 1 Step 8 — angka `withCount(['guru' => fn ($q) => $q->withoutGlobalScopes()])` di `index()` (SUDAH `withoutGlobalScopes()` sejak awal, tidak berubah) itu artinya index SELALU menampilkan total pemakaian LINTAS LEMBAGA (karena `withoutGlobalScopes()` bypass total), TAPI sekarang otomatis ter-scope ke 1 yayasan lewat baris `JabatanTambahanMaster` itu sendiri yang sudah di-`YayasanScope`. Ini KONSISTEN dengan guard delete Step 7 (sama-sama "seluruh yayasan pemilik row") — BEDA dengan Task 1 (`JenisKaryawanMaster::index()` yang TIDAK bypass scope Karyawan). Perbedaan ini WAJAR (mengikuti kode yang SUDAH ADA sebelum fix ini di masing-masing file, tidak diseragamkan paksa) — catat di laporan task.

### Step 9: Rewrite fixture test menyeluruh

`tests/Feature/Admin/JabatanTambahanMasterCrudTest.php` SAAT INI pakai `beforeEach` dengan `User::factory()->create()` TANPA `yayasan_id` — setelah `YayasanScope` aktif, user seperti ini punya `yayasan_id` TIDAK TERDETEKSI (`abort_if($yayasanId === null, 422, ...)` di Step 8 akan menolak SEMUA `store()` di file ini). Tulis ULANG SELURUH file:

```php
<?php

use App\Domains\Sdm\Models\JabatanTambahanMaster;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function actingAsJabatanTambahanManager(): User
{
    $manager = User::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $role = Role::firstOrCreate(['name' => 'yayasan_super_admin_jabatan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    foreach (['jabatan-tambahan-master.view', 'jabatan-tambahan-master.create', 'jabatan-tambahan-master.edit', 'jabatan-tambahan-master.delete'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role->givePermissionTo(['jabatan-tambahan-master.view', 'jabatan-tambahan-master.create', 'jabatan-tambahan-master.edit', 'jabatan-tambahan-master.delete']);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to unauthorized users without view permission', function () {
    $guest = User::factory()->create();
    $this->actingAs($guest)->get(route('admin.jabatan-tambahan-master.index'))->assertForbidden();
});

it('allows authorized admin to store a new master position via JSON', function () {
    $manager = actingAsJabatanTambahanManager();

    $response = $this->actingAs($manager)->postJson(route('admin.jabatan-tambahan-master.store'), [
        'nama' => 'Koordinator IT Sekolah',
        'kelompok' => 'fungsional',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['message', 'item' => ['id', 'nama', 'kelompok', 'guru_count']]);

    $item = JabatanTambahanMaster::where('nama', 'Koordinator IT Sekolah')->first();
    expect($item)->not->toBeNull();
    expect($item->yayasan_id)->toBe($manager->yayasan_id);
});

it('rejects duplicate position name via JSON validation', function () {
    $manager = actingAsJabatanTambahanManager();
    JabatanTambahanMaster::factory()->create(['nama' => 'Wali Kelas', 'kelompok' => 'fungsional', 'yayasan_id' => $manager->yayasan_id]);

    $response = $this->actingAs($manager)->postJson(route('admin.jabatan-tambahan-master.store'), [
        'nama' => 'Wali Kelas',
        'kelompok' => 'fungsional',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['nama']);
});

it('allows updating an existing position via JSON', function () {
    $manager = actingAsJabatanTambahanManager();
    $jabatan = JabatanTambahanMaster::factory()->create(['nama' => 'Wakasek Lama', 'kelompok' => 'struktural', 'yayasan_id' => $manager->yayasan_id]);

    $response = $this->actingAs($manager)->putJson(route('admin.jabatan-tambahan-master.update', $jabatan), [
        'nama' => 'Wakasek Baru',
        'kelompok' => 'struktural',
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Data jabatan berhasil diperbarui']);

    expect($jabatan->fresh()->nama)->toBe('Wakasek Baru');
});

it('allows deleting an unassigned master position via JSON', function () {
    $manager = actingAsJabatanTambahanManager();
    $jabatan = JabatanTambahanMaster::factory()->create(['nama' => 'Jabatan Sementara', 'kelompok' => 'fungsional', 'yayasan_id' => $manager->yayasan_id]);

    $response = $this->actingAs($manager)->deleteJson(route('admin.jabatan-tambahan-master.destroy', $jabatan));

    $response->assertStatus(200)
        ->assertJson(['message' => 'Jabatan telah dihapus permanen.']);

    expect(JabatanTambahanMaster::where('id', $jabatan->id)->exists())->toBeFalse();
});

it('prevents deleting a master position that is currently assigned to a guru', function () {
    $manager = actingAsJabatanTambahanManager();
    $jabatan = JabatanTambahanMaster::factory()->create(['nama' => 'Wali Kelas Aktif', 'kelompok' => 'fungsional', 'yayasan_id' => $manager->yayasan_id]);
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru->jabatanTambahan()->attach($jabatan->id, ['no_sk' => 'SK-001', 'mulai_periode' => '2025-07-01']);

    $response = $this->actingAs($manager)->deleteJson(route('admin.jabatan-tambahan-master.destroy', $jabatan));

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Jabatan tidak dapat dihapus karena saat ini masih disandang oleh 1 Guru aktif. Lepaskan tautan jabatan pada guru bersangkutan sebelum menghapusnya.',
        ]);

    expect(JabatanTambahanMaster::where('id', $jabatan->id)->exists())->toBeTrue();
});

it('renders the reactive SPA portal view cleanly with expected Alpine data bindings and tab bar', function () {
    $manager = actingAsJabatanTambahanManager();
    JabatanTambahanMaster::factory()->create(['nama' => 'Wali Kelas', 'kelompok' => 'fungsional', 'yayasan_id' => $manager->yayasan_id]);
    JabatanTambahanMaster::factory()->create(['nama' => 'Wakasek Kurikulum', 'kelompok' => 'struktural', 'yayasan_id' => $manager->yayasan_id]);

    $response = $this->actingAs($manager)->get(route('admin.jabatan-tambahan-master.index'));

    $response->assertStatus(200)
        ->assertSee('Wali Kelas')
        ->assertSee('Wakasek Kurikulum')
        ->assertSee('Master Jabatan Tambahan')
        ->assertSee('activeFilter')
        ->assertSee('scrollbar-none');
});

it('does not leak jabatan tambahan across yayasan boundaries on the index page', function () {
    $managerA = actingAsJabatanTambahanManager();
    $jabatanA = JabatanTambahanMaster::factory()->create(['nama' => 'Milik Yayasan A', 'yayasan_id' => $managerA->yayasan_id]);
    JabatanTambahanMaster::factory()->create(['nama' => 'Milik Yayasan B']);

    $response = $this->actingAs($managerA)->getJson(route('admin.jabatan-tambahan-master.index'));

    $response->assertOk();
    $ids = collect($response->json('items'))->pluck('id');
    expect($ids)->toContain($jabatanA->id);
    expect($ids)->toHaveCount(1);
});

it('allows two different yayasan to use the exact same jabatan tambahan nama', function () {
    $managerA = actingAsJabatanTambahanManager();
    JabatanTambahanMaster::factory()->create(['nama' => 'Wali Kelas']);

    $this->actingAs($managerA)->postJson(route('admin.jabatan-tambahan-master.store'), [
        'nama' => 'Wali Kelas',
        'kelompok' => 'fungsional',
    ])->assertCreated();
});

it('does not block deleting a jabatan tambahan that is only assigned to a guru in a different yayasan', function () {
    $managerA = actingAsJabatanTambahanManager();
    $jabatanA = JabatanTambahanMaster::factory()->create(['yayasan_id' => $managerA->yayasan_id]);

    $yayasanB = Yayasan::factory()->create();
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasanB->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $guruB->jabatanTambahan()->attach($jabatanA->id, ['no_sk' => 'SK-002', 'mulai_periode' => '2025-07-01']);

    $this->actingAs($managerA)->deleteJson(route('admin.jabatan-tambahan-master.destroy', $jabatanA))
        ->assertOk();

    expect(JabatanTambahanMaster::find($jabatanA->id))->toBeNull();
});
```

**Catatan penyimpangan dari brief poin "buat helper `actingAsJabatanTambahanManager()`"**: nama role diberi suffix `_jabatan` (`yayasan_super_admin_jabatan`) untuk menghindari `Role::firstOrCreate` bentrok dengan role bernama sama persis (`yayasan_super_admin`) yang dipakai `actingAsJenisKaryawanManager()` di file test lain — kedua file jalan di database test yang sama per-test-run (`RefreshDatabase` reset per test, TAPI aman berjaga-jaga kalau role dipakai lintas file dalam skenario tertentu). Kalau saat implementasi ternyata nama yang sama tidak bermasalah (dikonfirmasi tidak ada konflik), boleh disamakan — bukan keputusan kritis.

Run: `php artisan test tests/Feature/Admin/JabatanTambahanMasterCrudTest.php --compact`
Expected: 9 test PASS (7 existing yang direwrite fixture-nya + 2 baru).

### Step 10: Pint

Run: `vendor/bin/pint --dirty --format agent`

### Step 11: Commit

```bash
git add database/migrations/*_add_yayasan_id_to_jabatan_tambahan_master_table.php \
    database/factories/JabatanTambahanMasterFactory.php \
    app/Domains/Sdm/Models/JabatanTambahanMaster.php \
    app/Domains/Sdm/Actions/JabatanTambahan/CreateJabatanTambahanAction.php \
    app/Domains/Sdm/Actions/JabatanTambahan/DeleteJabatanTambahanAction.php \
    app/Http/Controllers/Lembaga/Sdm/JabatanTambahanMasterController.php \
    tests/Feature/Admin/JabatanTambahanMasterCrudTest.php
git commit -m "fix(sdm): jabatan tambahan master jadi per-yayasan, bukan katalog global lintas sistem

Pola identik dengan jenis_karyawan_master (Task sebelumnya): yayasan_id
NOT NULL dengan backfill defensif lewat join guru->lembaga->yayasan
(Guru tidak punya yayasan_id langsung), scope model, guard delete
per-yayasan-pemilik-row. Fixture test file ini ditulis ulang total --
sebelumnya user test tidak punya yayasan_id sama sekali, dan factory
untuk model ini belum pernah ada.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Penutup — Full Suite, Pint, Handoff Log, Roadmap

**Files:**
- Create: `.agents/logs/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md`
- Modify: `PETA_PENGEMBANGAN.md`

**Interfaces:**
- Consumes: commit hash Task 1-2 (`git log --oneline` untuk merangkum).
- Produces: tidak ada — task penutup dokumentasi murni.

### Step 1: Cek proses PHP lain sebelum full suite

Run (PowerShell): `Get-CimInstance Win32_Process -Filter "Name='php.exe'"`
Kalau ada proses `php artisan test` lain berjalan, TUNGGU sampai selesai (risiko deadlock MySQL, sudah beberapa kali terjadi di project ini).

### Step 2: Full Test Suite

Run: `php artisan test --compact`
Expected: SEMUA test lulus KECUALI 4 kegagalan pre-existing yang sudah terdokumentasi berulang kali di handoff log sesi ini (`M3DemoDataSeederTest` x2, `PresensiSeederTest`, `SesiPembelajaranSeederTest` — seeder demo PPDB/presensi, day-of-week-dependent, TIDAK terkait modul SDM). Kalau ada kegagalan LAIN — terutama di `KaryawanCrudTest`, `GuruCrudTest`, `AttendancePolicyTest`, `KuotaCutiConfigTest`, atau test lain yang mereferensikan `jenis_karyawan_id`/`jabatan_tambahan_master_id` — itu regresi nyata dari Task 1-2, STOP dan investigasi sebelum lanjut (kemungkinan besar: factory/fixture lain di luar 2 file yang sudah diperbaiki, yang ternyata juga bergantung pada `JenisKaryawanMaster::factory()`/`JabatanTambahanMaster::create()` tanpa `yayasan_id` — grep dulu `JenisKaryawanMaster::factory\(\)|JabatanTambahanMaster::create\(` di seluruh `tests/` sebelum Task 3 dimulai kalau full suite menunjukkan kegagalan tak terduga).

### Step 3: Pint

Run: `vendor/bin/pint --dirty --format agent`
Expected: passed. Kalau ada perubahan, commit terpisah:
```bash
git add -u
git commit -m "style: pint --dirty setelah plan jenis-karyawan-jabatan-tambahan-per-yayasan

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

### Step 4: Handoff Log

Buat `.agents/logs/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md`, ikuti struktur referensi (`.agents/logs/2026-09-07-orang-tua-siswa-person-tautan.md`): "1. Apa yang Dikerjakan" (Task 1-2 dengan commit hash, disusun dari `git log --oneline` sejak base commit plan ini), "2. Keputusan Penting" (WAJIB sertakan: keputusan bisnis "tanpa baris nasional" dari spec, 2 keputusan "index vs guard delete boleh beda cakupan" dari Step 8 Task 1 & Task 2, dan hasil verifikasi data sebelum migrasi dari spec), "3. Hal yang Masih Perlu Direview" (hasil full suite Step 2, dan poin "Di Luar Scope": hook seeder onboarding yayasan baru + kloning starter catalog ke yayasan lain SENGAJA tidak dikerjakan, dikutip persis dari spec).

### Step 5: Update Roadmap

Tambahkan entri baru ke `PETA_PENGEMBANGAN.md`, ikuti gaya entri lain di file yang sama.

### Step 6: Commit dokumentasi

```bash
git add .agents/logs/2026-09-07-jenis-karyawan-jabatan-tambahan-per-yayasan.md PETA_PENGEMBANGAN.md
git commit -m "docs(sdm): handoff log & update roadmap -- jenis karyawan & jabatan tambahan per-yayasan selesai

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage**: Migrasi+backfill (poin 1 spec) → Task 1 Step 1-3, Task 2 Step 1-3 (masing-masing tabel, backfill defensif utk kasus >1 pemakai TETAP ditulis meski tidak terjadi di data sekarang). Scope model (poin 3) → Task 1 Step 4, Task 2 Step 4. Auto-isi `yayasan_id` di Action (poin 4) → Task 1 Step 6, Task 2 Step 6. Guard delete per-yayasan-pemilik-row (poin 5) → Task 1 Step 7, Task 2 Step 7. Unique constraint composite (poin 7) → sudah termasuk di migrasi Step 2 kedua task + validasi controller Step 8. "Di Luar Scope" (hook onboarding, kloning yayasan lain) → dicatat eksplisit Task 3 Step 4, TIDAK ada task kode untuk itu. Tidak ada requirement spec tanpa task.

**2. Placeholder scan**: Semua step kode berisi snippet lengkap siap tempel (migrasi lengkap termasuk backfill, model lengkap, factory lengkap, seluruh isi ulang file test). Tidak ada "TBD"/"tambahkan validasi" tanpa kode konkret.

**3. Type consistency**: `CreateJenisKaryawanAction::execute(JenisKaryawanMasterData $data, int $yayasanId)` dan `CreateJabatanTambahanAction::execute(JabatanTambahanMasterData $data, int $yayasanId)` — signature paralel konsisten, keduanya dipakai HANYA di controller-nya masing-masing dalam task yang sama (tidak dikonsumsi lintas task, tidak ada risiko drift nama parameter). `Rule::unique(...)->where('yayasan_id', ...)` dipakai konsisten di `store()` (pakai `$yayasanId` aktor) dan `update()` (pakai `$model->yayasan_id` milik row) di KEDUA controller — pola yang sama, bukan ditulis beda tanpa alasan.
