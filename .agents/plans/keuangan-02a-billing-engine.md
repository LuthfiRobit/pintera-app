# Keuangan Sub-project 2a: Billing Engine — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **This project also requires `.agents/AGENTS.md`'s 7-stage workflow** — if you are not Claude Code, read that file before starting; it overrides some of this skill's own defaults (e.g. save locations).

**Goal:** Build the backend engine that generates `tagihan` rows for enrolled students (SPP/dynamic billing) — a service that evaluates per-`jenis_tagihan` targeting/pricing/discount rules and inserts idempotent `tagihan` rows, invokable via cron, an admin manual-trigger command, and 3 domain events (new student, student changes class, bill type activated).

**Architecture:** Three focused services (`JenisTagihanSasaranMatcher` for target-student evaluation, `TagihanNominalResolver` for nominal+discount resolution, `TagihanBillingGenerator` for orchestration + audit logging), two Artisan commands (manual + cron entry points), and 3 Laravel events/listeners wired via model lifecycle hooks (Laravel 12 auto-discovers listeners — no manual `EventServiceProvider` registration needed). No UI in this plan — that is Sub-project 2b, a separate plan written after this one lands.

**Tech Stack:** Laravel 12, Eloquent, Pest tests with `RefreshDatabase`, Laravel's built-in scheduler (`routes/console.php`).

## Global Constraints

- **No queue/Redis** — every generation path (cron, manual, event) runs synchronously in-request or in-process, per the sub-project 1 spec's MVP decision. Do not introduce `ShouldQueue` anywhere in this plan.
- **`app/Services/` is flat** — no subdirectories. New services go directly in `app/Services/`, matching every existing service in this codebase (`TagihanGenerator.php`, `TugasBatchGenerator.php`, etc. — checked via `ls app/Services/`, zero subdirectories exist).
- **Bypass `TenantScope` explicitly in these services.** `Siswa` and `JenisTagihan` both use the `BelongsToTenant` trait, which filters `WHERE lembaga_id = ...` based on the *currently authenticated user's session* (`app/Models/Scopes/TenantScope.php`). The billing engine's job is to act on the `lembaga_id` that belongs to the *domain object being processed* (the siswa's own lembaga, or the jenis_tagihan's own lembaga) — not whatever `active_lembaga_id` happens to be in the acting admin's session at the moment an event fires. If these two ever diverge (e.g. a yayasan-scoped admin has a different lembaga selected in their session switcher than the siswa they just created), an un-bypassed query would silently return zero rows and skip billing generation with no error. This exact class of cross-tenant scoping bug has recurred repeatedly elsewhere in this codebase.

This applies specifically to queries whose job is "find everything belonging to *this already-known domain object's* `lembaga_id`" (Task 2's `resolveTargetSiswa`, Task 6's cron sweep, Task 7/8's event listeners) — those **must** call `->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)` and then explicitly filter by the correct `lembaga_id` themselves (never omit the explicit filter just because the scope is bypassed). It does **not** apply to Task 5's manual-trigger command, where `JenisTagihan::find($id)` should keep the default tenant scope — an admin manually processing a `jenis_tagihan_id` legitimately should only be able to reach one within their own session's lembaga; that scoping is desired defense there, not a bug to route around.
- **`tagihan.jatuh_tempo`** (existing column, `date` nullable, added in the original PPDB migration) is the due-date column for SPP/dynamic tagihan too — there is no separate `due_date` column. Do not add a new column.
- **`tagihan.kategori`** on a generated row is copied directly from `jenis_tagihan.kategori` — both use the identical widened enum values (`pendaftaran,daftar_ulang,spp,tahunan,kegiatan,custom`) as of Sub-project 1, so no translation is needed.
- **Discount storage convention** (not stated explicitly in the spec, decided here for consistency): `tagihan.discount_amount` always stores the **computed Rupiah value**, regardless of whether the winning `jenis_tagihan_keringanan` rule is `fixed` or `persen` (for `persen`, this is `nominal * nilai / 100`, rounded to 2 decimals). `tagihan.discount_type` stores which rule type won, for display/audit only — it is never used to re-derive the amount. This makes `net_amount = nominal - discount_amount` always correct arithmetic without the reader needing to know the discount type.
- **"Ambil nilai potongan terbesar"** (spec's rule for a siswa matching multiple keringanan categories) means largest **computed Rupiah discount**, not largest raw `nilai` field — a 10% discount on a 5,000,000 nominal (500,000) beats a fixed 100,000 discount, even though "10" < "100,000" as raw numbers.

---

### Task 1: `billing_job_logs` table + `BillingJobLog` model

**Files:**
- Create: `database/migrations/2026_08_11_090000_create_billing_job_logs_table.php`
- Create: `app/Models/BillingJobLog.php`
- Test: `tests/Feature/Keuangan/BillingJobLogTest.php`

**Interfaces:**
- Consumes: `App\Models\JenisTagihan` (existing, from Sub-project 1)
- Produces: `BillingJobLog::create(['jenis_tagihan_id' => int, 'trigger_type' => 'cron'|'manual'|'event', 'trigger_event' => ?string, 'period' => ?string, 'bills_generated' => int, 'status' => 'success'|'partial'|'failed', 'error_log' => ?array, 'executed_at' => Carbon|string])`, relation `BillingJobLog::jenisTagihan(): BelongsTo`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/BillingJobLogTest.php

use App\Models\BillingJobLog;
use App\Models\JenisTagihan;

it('stores a billing job log with an error_log array and relates back to jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create();

    $log = BillingJobLog::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'trigger_type' => 'cron',
        'trigger_event' => null,
        'period' => '2026-09',
        'bills_generated' => 3,
        'status' => 'partial',
        'error_log' => [['siswa_id' => 42, 'message' => 'Something failed']],
        'executed_at' => now(),
    ]);

    expect($log->fresh()->error_log)->toBe([['siswa_id' => 42, 'message' => 'Something failed']]);
    expect($log->jenisTagihan->id)->toBe($jenisTagihan->id);
});

it('allows a null error_log and trigger_event for a clean cron run', function () {
    $jenisTagihan = JenisTagihan::factory()->create();

    $log = BillingJobLog::create([
        'jenis_tagihan_id' => $jenisTagihan->id,
        'trigger_type' => 'cron',
        'trigger_event' => null,
        'period' => '2026-09',
        'bills_generated' => 5,
        'status' => 'success',
        'error_log' => null,
        'executed_at' => now(),
    ]);

    expect($log->fresh()->error_log)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=BillingJobLogTest`
Expected: FAIL — `Class "App\Models\BillingJobLog" not found`

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_11_090000_create_billing_job_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_job_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_tagihan_id')->constrained('jenis_tagihan')->cascadeOnDelete();
            $table->enum('trigger_type', ['cron', 'manual', 'event']);
            $table->string('trigger_event')->nullable();
            $table->string('period', 7)->nullable();
            $table->unsignedInteger('bills_generated');
            $table->enum('status', ['success', 'partial', 'failed']);
            $table->json('error_log')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_job_logs');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php
// app/Models/BillingJobLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingJobLog extends Model
{
    protected $table = 'billing_job_logs';

    protected $fillable = [
        'jenis_tagihan_id', 'trigger_type', 'trigger_event', 'period',
        'bills_generated', 'status', 'error_log', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'error_log' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }
}
```

- [ ] **Step 5: Run migration and test to verify it passes**

Run: `php artisan migrate && php artisan test --filter=BillingJobLogTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_11_090000_create_billing_job_logs_table.php app/Models/BillingJobLog.php tests/Feature/Keuangan/BillingJobLogTest.php
git commit -m "feat(keuangan): add billing_job_logs table for generator audit trail"
```

---

### Task 2: `JenisTagihanSasaranMatcher` service

**Files:**
- Create: `app/Services/JenisTagihanSasaranMatcher.php`
- Test: `tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php`

**Interfaces:**
- Consumes: `App\Models\Siswa`, `App\Models\JenisTagihan`, `App\Models\JenisTagihanSasaranGrup`, `App\Models\JenisTagihanSasaranKriteria` (all existing, from Sub-project 1)
- Produces: `JenisTagihanSasaranMatcher::resolveTargetSiswa(JenisTagihan $jenisTagihan): Collection<int, Siswa>` (each with `kelas` eager-loaded), `JenisTagihanSasaranMatcher::siswaMatchesGrup(Siswa $siswa, JenisTagihanSasaranGrup $grup): bool`, `JenisTagihanSasaranMatcher::siswaMatchesJenisTagihan(Siswa $siswa, JenisTagihan $jenisTagihan): bool`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php

use App\Models\JenisTagihan;
use App\Models\JenisTagihanSasaranGrup;
use App\Models\JenisTagihanSasaranKriteria;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Services\JenisTagihanSasaranMatcher;

it('returns every siswa in the lembaga when there is no sasaran grup at all', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaSatu = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaDua = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    Siswa::factory()->create(); // siswa lembaga lain, tidak boleh ikut

    $result = (new JenisTagihanSasaranMatcher())->resolveTargetSiswa($jenisTagihan);

    expect($result->pluck('id')->sort()->values()->all())
        ->toBe(collect([$siswaSatu->id, $siswaDua->id])->sort()->values()->all());
});

it('matches siswa by AND-ing every kriteria within one grup', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasTujuhA = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);

    $cocok = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasTujuhA->id, 'jenis_kelamin' => 'L']);
    $bedaKelamin = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasTujuhA->id, 'jenis_kelamin' => 'P']);
    $bedaKelas = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'L']);

    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'kelas', 'operator' => 'in', 'value' => [$kelasTujuhA->id]]);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]);

    $result = (new JenisTagihanSasaranMatcher())->resolveTargetSiswa($jenisTagihan);

    expect($result->pluck('id')->all())->toBe([$cocok->id]);
    expect($result->pluck('id')->all())->not->toContain($bedaKelamin->id, $bedaKelas->id);
});

it('OR-s multiple sasaran grup together', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasKhusus = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);

    $siswaLaki = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'L']);
    $siswaPerempuanKelasKhusus = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'P', 'kelas_id' => $kelasKhusus->id]);
    $siswaPerempuanLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_kelamin' => 'P']);

    $grupLaki = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grupLaki->id, 'field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]);

    $grupKelasKhusus = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grupKelasKhusus->id, 'field' => 'kelas', 'operator' => 'in', 'value' => [$kelasKhusus->id]]);

    $result = (new JenisTagihanSasaranMatcher())->resolveTargetSiswa($jenisTagihan);

    // siswaLaki cocok lewat grupLaki; siswaPerempuanKelasKhusus cocok HANYA lewat grupKelasKhusus
    // (gagal di grupLaki karena jenis_kelamin) — ini yang membuktikan OR antar-grup benar-benar
    // berlaku, bukan cuma satu grup yang kebetulan menjawab semuanya. siswaPerempuanLain tidak
    // cocok grup manapun dan harus tidak ikut.
    expect($result->pluck('id')->sort()->values()->all())
        ->toBe(collect([$siswaLaki->id, $siswaPerempuanKelasKhusus->id])->sort()->values()->all());
    expect($result->pluck('id'))->not->toContain($siswaPerempuanLain->id);
});

it('excludes siswa matching a not_in kriteria', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaAktif = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    $siswaLulus = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'lulus']);

    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'status_siswa', 'operator' => 'not_in', 'value' => ['lulus', 'keluar']]);

    $result = (new JenisTagihanSasaranMatcher())->resolveTargetSiswa($jenisTagihan);

    expect($result->pluck('id')->all())->toBe([$siswaAktif->id]);
    expect($result->pluck('id')->all())->not->toContain($siswaLulus->id);
});

it('matches tahun_ajaran and tingkat kriteria through the kelas relation', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasEnam = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tingkat' => '6']);
    $kelasSatu = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tingkat' => '1']);

    $siswaKelasEnam = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasEnam->id]);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasSatu->id]);

    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'tingkat', 'operator' => 'in', 'value' => ['6']]);

    $result = (new JenisTagihanSasaranMatcher())->resolveTargetSiswa($jenisTagihan);

    expect($result->pluck('id')->all())->toBe([$siswaKelasEnam->id]);
});

it('siswaMatchesJenisTagihan is true for an empty sasaran and false for a non-matching lembaga', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaSendiri = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaLain = Siswa::factory()->create();

    $matcher = new JenisTagihanSasaranMatcher();

    expect($matcher->siswaMatchesJenisTagihan($siswaSendiri, $jenisTagihan))->toBeTrue();
    expect($matcher->siswaMatchesJenisTagihan($siswaLain, $jenisTagihan))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=JenisTagihanSasaranMatcherTest`
Expected: FAIL — `Class "App\Services\JenisTagihanSasaranMatcher" not found`

- [ ] **Step 3: Write the service**

```php
<?php
// app/Services/JenisTagihanSasaranMatcher.php

namespace App\Services;

use App\Models\JenisTagihan;
use App\Models\JenisTagihanSasaranGrup;
use App\Models\JenisTagihanSasaranKriteria;
use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class JenisTagihanSasaranMatcher
{
    /**
     * @return Collection<int, Siswa>
     */
    public function resolveTargetSiswa(JenisTagihan $jenisTagihan): Collection
    {
        $sasaranGrups = $jenisTagihan->sasaranGrup()->where('tipe', 'sasaran')->with('kriteria')->get();

        $query = Siswa::withoutGlobalScope(TenantScope::class)
            ->with('kelas')
            ->where('lembaga_id', $jenisTagihan->lembaga_id);

        if ($sasaranGrups->isNotEmpty()) {
            $query->where(function (Builder $outer) use ($sasaranGrups) {
                foreach ($sasaranGrups as $grup) {
                    $outer->orWhere(function (Builder $inner) use ($grup) {
                        foreach ($grup->kriteria as $kriteria) {
                            $this->applyKriteriaToQuery($inner, $kriteria);
                        }
                    });
                }
            });
        }

        return $query->get();
    }

    public function siswaMatchesGrup(Siswa $siswa, JenisTagihanSasaranGrup $grup): bool
    {
        foreach ($grup->kriteria as $kriteria) {
            if (! $this->siswaMatchesKriteria($siswa, $kriteria)) {
                return false;
            }
        }

        return true;
    }

    public function siswaMatchesJenisTagihan(Siswa $siswa, JenisTagihan $jenisTagihan): bool
    {
        if ($siswa->lembaga_id !== $jenisTagihan->lembaga_id) {
            return false;
        }

        $sasaranGrups = $jenisTagihan->sasaranGrup()->where('tipe', 'sasaran')->with('kriteria')->get();

        if ($sasaranGrups->isEmpty()) {
            return true;
        }

        foreach ($sasaranGrups as $grup) {
            if ($this->siswaMatchesGrup($siswa, $grup)) {
                return true;
            }
        }

        return false;
    }

    private function applyKriteriaToQuery(Builder $query, JenisTagihanSasaranKriteria $kriteria): void
    {
        $values = $kriteria->value;
        $isIn = $kriteria->operator === 'in';

        switch ($kriteria->field) {
            case 'lembaga':
                $isIn ? $query->whereIn('lembaga_id', $values) : $query->whereNotIn('lembaga_id', $values);
                break;
            case 'kelas':
                $isIn ? $query->whereIn('kelas_id', $values) : $query->whereNotIn('kelas_id', $values);
                break;
            case 'jenis_kelamin':
                $isIn ? $query->whereIn('jenis_kelamin', $values) : $query->whereNotIn('jenis_kelamin', $values);
                break;
            case 'status_siswa':
                $isIn ? $query->whereIn('status', $values) : $query->whereNotIn('status', $values);
                break;
            case 'tahun_ajaran':
                $isIn
                    ? $query->whereHas('kelas', fn (Builder $k) => $k->whereIn('tahun_ajaran_id', $values))
                    : $query->whereDoesntHave('kelas', fn (Builder $k) => $k->whereIn('tahun_ajaran_id', $values));
                break;
            case 'tingkat':
                $isIn
                    ? $query->whereHas('kelas', fn (Builder $k) => $k->whereIn('tingkat', $values))
                    : $query->whereDoesntHave('kelas', fn (Builder $k) => $k->whereIn('tingkat', $values));
                break;
        }
    }

    private function siswaMatchesKriteria(Siswa $siswa, JenisTagihanSasaranKriteria $kriteria): bool
    {
        $actual = match ($kriteria->field) {
            'lembaga' => $siswa->lembaga_id,
            'kelas' => $siswa->kelas_id,
            'jenis_kelamin' => $siswa->jenis_kelamin,
            'status_siswa' => $siswa->status->value,
            'tahun_ajaran' => $siswa->kelas?->tahun_ajaran_id,
            'tingkat' => $siswa->kelas?->tingkat,
        };

        $inList = in_array($actual, $kriteria->value);

        return $kriteria->operator === 'in' ? $inList : ! $inList;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=JenisTagihanSasaranMatcherTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/JenisTagihanSasaranMatcher.php tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php
git commit -m "feat(keuangan): add JenisTagihanSasaranMatcher for OR-of-AND target evaluation"
```

---

### Task 3: `TagihanNominalResolver` service

**Files:**
- Create: `app/Services/TagihanNominalResolver.php`
- Test: `tests/Feature/Keuangan/TagihanNominalResolverTest.php`

**Interfaces:**
- Consumes: `App\Services\JenisTagihanSasaranMatcher::siswaMatchesGrup()` (Task 2), `App\Models\NominalTagihanSiswa`, `App\Models\SiswaKeringanan`, `App\Models\JenisTagihanKeringanan` (all existing, from Sub-project 1)
- Produces: `TagihanNominalResolver::resolve(Siswa $siswa, JenisTagihan $jenisTagihan): array{nominal: float, discount_amount: float, discount_type: ?string}`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/TagihanNominalResolverTest.php

use App\Models\JenisTagihan;
use App\Models\JenisTagihanKeringanan;
use App\Models\JenisTagihanSasaranGrup;
use App\Models\JenisTagihanSasaranKriteria;
use App\Models\KategoriKeringanan;
use App\Models\NominalTagihanSiswa;
use App\Models\Siswa;
use App\Models\SiswaKeringanan;
use App\Services\JenisTagihanSasaranMatcher;
use App\Services\TagihanNominalResolver;

function buatResolver(): TagihanNominalResolver
{
    return new TagihanNominalResolver(new JenisTagihanSasaranMatcher());
}

it('falls back to default_amount when nothing else matches', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 250000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['nominal'])->toBe(250000.0);
    expect($result['discount_amount'])->toBe(0.0);
    expect($result['discount_type'])->toBeNull();
});

it('uses the first matching tarif grup nominal over default_amount', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 250000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id, 'jenis_kelamin' => 'L']);

    $grupTarif = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'tarif', 'nominal' => 300000]);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grupTarif->id, 'field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['nominal'])->toBe(300000.0);
});

it('uses nominal_tagihan_siswa override above every other source', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 250000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id, 'jenis_kelamin' => 'L']);

    $grupTarif = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'tarif', 'nominal' => 300000]);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grupTarif->id, 'field' => 'jenis_kelamin', 'operator' => 'in', 'value' => ['L']]);

    NominalTagihanSiswa::create(['jenis_tagihan_id' => $jenisTagihan->id, 'siswa_id' => $siswa->id, 'nominal' => 100000]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['nominal'])->toBe(100000.0);
});

it('computes a fixed discount amount directly from nilai', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 500000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $kategori = KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Anak Pegawai']);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 75000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(75000.0);
    expect($result['discount_type'])->toBe('fixed');
});

it('computes a persen discount as a percentage of the resolved nominal', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 500000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $kategori = KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Beasiswa']);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'persen', 'nilai' => 10]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(50000.0);
    expect($result['discount_type'])->toBe('persen');
});

it('picks the largest computed rupiah discount when multiple keringanan rules match', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 500000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $kategoriPersen = KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Beasiswa']);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriPersen->id, 'tipe_potongan' => 'persen', 'nilai' => 10]); // = 50000
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriPersen->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $kategoriFixed = KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Anak Pegawai']);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategoriFixed->id, 'tipe_potongan' => 'fixed', 'nilai' => 100000]);
    SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategoriFixed->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(100000.0);
    expect($result['discount_type'])->toBe('fixed');
});

it('ignores a keringanan whose berlaku_sampai has already passed', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 500000]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $kategori = KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Anak Pegawai']);
    JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 75000]);
    SiswaKeringanan::create([
        'siswa_id' => $siswa->id,
        'kategori_keringanan_id' => $kategori->id,
        'berlaku_dari' => now()->subMonths(2)->toDateString(),
        'berlaku_sampai' => now()->subMonth()->toDateString(),
    ]);

    $result = buatResolver()->resolve($siswa, $jenisTagihan);

    expect($result['discount_amount'])->toBe(0.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TagihanNominalResolverTest`
Expected: FAIL — `Class "App\Services\TagihanNominalResolver" not found`

- [ ] **Step 3: Write the service**

```php
<?php
// app/Services/TagihanNominalResolver.php

namespace App\Services;

use App\Models\JenisTagihan;
use App\Models\JenisTagihanKeringanan;
use App\Models\NominalTagihanSiswa;
use App\Models\Siswa;
use App\Models\SiswaKeringanan;

class TagihanNominalResolver
{
    public function __construct(private readonly JenisTagihanSasaranMatcher $matcher)
    {
    }

    /**
     * @return array{nominal: float, discount_amount: float, discount_type: ?string}
     */
    public function resolve(Siswa $siswa, JenisTagihan $jenisTagihan): array
    {
        $nominal = $this->resolveNominal($siswa, $jenisTagihan);
        [$discountAmount, $discountType] = $this->resolveDiscount($siswa, $jenisTagihan, $nominal);

        return [
            'nominal' => $nominal,
            'discount_amount' => $discountAmount,
            'discount_type' => $discountType,
        ];
    }

    private function resolveNominal(Siswa $siswa, JenisTagihan $jenisTagihan): float
    {
        $override = NominalTagihanSiswa::where('jenis_tagihan_id', $jenisTagihan->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($override) {
            return (float) $override->nominal;
        }

        $tarifGrups = $jenisTagihan->sasaranGrup()->where('tipe', 'tarif')->with('kriteria')->orderBy('id')->get();

        foreach ($tarifGrups as $grup) {
            if ($this->matcher->siswaMatchesGrup($siswa, $grup)) {
                return (float) $grup->nominal;
            }
        }

        return (float) ($jenisTagihan->default_amount ?? 0);
    }

    /**
     * @return array{0: float, 1: ?string}
     */
    private function resolveDiscount(Siswa $siswa, JenisTagihan $jenisTagihan, float $nominal): array
    {
        $today = now()->toDateString();

        $kategoriIds = SiswaKeringanan::where('siswa_id', $siswa->id)
            ->where('berlaku_dari', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>=', $today);
            })
            ->pluck('kategori_keringanan_id');

        if ($kategoriIds->isEmpty()) {
            return [0.0, null];
        }

        $rules = JenisTagihanKeringanan::where('jenis_tagihan_id', $jenisTagihan->id)
            ->whereIn('kategori_keringanan_id', $kategoriIds)
            ->get();

        $bestAmount = 0.0;
        $bestType = null;

        foreach ($rules as $rule) {
            $amount = $rule->tipe_potongan === 'persen'
                ? round($nominal * ((float) $rule->nilai) / 100, 2)
                : (float) $rule->nilai;

            if ($amount > $bestAmount) {
                $bestAmount = $amount;
                $bestType = $rule->tipe_potongan;
            }
        }

        return [$bestAmount, $bestType];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TagihanNominalResolverTest`
Expected: PASS (7 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/TagihanNominalResolver.php tests/Feature/Keuangan/TagihanNominalResolverTest.php
git commit -m "feat(keuangan): add TagihanNominalResolver for nominal + keringanan resolution"
```

---

### Task 4: `TagihanBillingGenerator` service

**Files:**
- Create: `app/Services/TagihanBillingGenerator.php`
- Test: `tests/Feature/Keuangan/TagihanBillingGeneratorTest.php`

**Interfaces:**
- Consumes: `App\Services\JenisTagihanSasaranMatcher::resolveTargetSiswa()` (Task 2), `App\Services\TagihanNominalResolver::resolve()` (Task 3), `App\Models\BillingJobLog` (Task 1), `App\Models\Tagihan` (existing)
- Produces: `TagihanBillingGenerator::generate(JenisTagihan $jenisTagihan, string $triggerType, ?string $triggerEvent = null): BillingJobLog`, `TagihanBillingGenerator::generateForSiswa(Siswa $siswa, JenisTagihan $jenisTagihan, string $triggerType): bool`, `TagihanBillingGenerator::generateForSiswaViaEvent(Siswa $siswa, JenisTagihan $jenisTagihan, string $triggerEvent): BillingJobLog`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/TagihanBillingGeneratorTest.php

use App\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Services\JenisTagihanSasaranMatcher;
use App\Services\TagihanBillingGenerator;
use App\Services\TagihanNominalResolver;

function buatGenerator(): TagihanBillingGenerator
{
    $matcher = new JenisTagihanSasaranMatcher();

    return new TagihanBillingGenerator($matcher, new TagihanNominalResolver($matcher));
}

it('generates a belum_bayar tagihan for every matching siswa and logs a success job', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000, 'mode' => 'otomatis', 'hari_jatuh_tempo' => 10]);
    $siswaSatu = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $siswaDua = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $log = buatGenerator()->generate($jenisTagihan, 'cron');

    expect($log->status)->toBe('success');
    expect($log->bills_generated)->toBe(2);
    expect($log->trigger_type)->toBe('cron');
    expect($log->period)->toBe(now()->format('Y-m'));

    $tagihanSatu = Tagihan::where('tagihable_id', $siswaSatu->id)->where('tagihable_type', Siswa::class)->first();
    expect($tagihanSatu)->not->toBeNull();
    expect((float) $tagihanSatu->net_amount)->toBe(200000.0);
    expect($tagihanSatu->status)->toBe('belum_bayar');
    expect($tagihanSatu->jatuh_tempo->format('Y-m-d'))->toBe(now()->startOfMonth()->addDays(9)->format('Y-m-d'));

    expect(Tagihan::where('tagihable_id', $siswaDua->id)->exists())->toBeTrue();
});

it('does not create a duplicate tagihan for the same siswa and billing_period on a second run', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000, 'mode' => 'otomatis']);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $generator = buatGenerator();
    $generator->generate($jenisTagihan, 'cron');
    $secondLog = $generator->generate($jenisTagihan, 'cron');

    expect($secondLog->bills_generated)->toBe(0);
    expect(Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count())->toBe(1);
});

it('sets billing_period to null for a manual-mode jenis_tagihan regardless of trigger', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 150000, 'mode' => 'manual']);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $log = buatGenerator()->generate($jenisTagihan, 'manual');

    expect($log->period)->toBeNull();
    expect(Tagihan::first()->billing_period)->toBeNull();
});

it('applies the discount from TagihanNominalResolver to net_amount', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 500000, 'mode' => 'otomatis']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $kategori = \App\Models\KategoriKeringanan::create(['lembaga_id' => $jenisTagihan->lembaga_id, 'nama' => 'Anak Pegawai']);
    \App\Models\JenisTagihanKeringanan::create(['jenis_tagihan_id' => $jenisTagihan->id, 'kategori_keringanan_id' => $kategori->id, 'tipe_potongan' => 'fixed', 'nilai' => 100000]);
    \App\Models\SiswaKeringanan::create(['siswa_id' => $siswa->id, 'kategori_keringanan_id' => $kategori->id, 'berlaku_dari' => now()->subDay()->toDateString()]);

    buatGenerator()->generate($jenisTagihan, 'cron');

    $tagihan = Tagihan::where('tagihable_id', $siswa->id)->first();
    expect((float) $tagihan->total_tagihan)->toBe(500000.0);
    expect((float) $tagihan->discount_amount)->toBe(100000.0);
    expect((float) $tagihan->net_amount)->toBe(400000.0);
});

it('generateForSiswa returns false and creates nothing when a tagihan for that period already exists', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000, 'mode' => 'otomatis']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    $generator = buatGenerator();

    expect($generator->generateForSiswa($siswa, $jenisTagihan, 'event'))->toBeTrue();
    expect($generator->generateForSiswa($siswa, $jenisTagihan, 'event'))->toBeFalse();
    expect(Tagihan::where('tagihable_id', $siswa->id)->count())->toBe(1);
});

it('generateForSiswaViaEvent logs a single-siswa job with the given trigger_event', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000, 'mode' => 'otomatis']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $log = buatGenerator()->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentCreated');

    expect($log->trigger_type)->toBe('event');
    expect($log->trigger_event)->toBe('StudentCreated');
    expect($log->bills_generated)->toBe(1);
    expect($log->status)->toBe('success');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TagihanBillingGeneratorTest`
Expected: FAIL — `Class "App\Services\TagihanBillingGenerator" not found`

- [ ] **Step 3: Write the service**

```php
<?php
// app/Services/TagihanBillingGenerator.php

namespace App\Services;

use App\Models\BillingJobLog;
use App\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TagihanBillingGenerator
{
    public function __construct(
        private readonly JenisTagihanSasaranMatcher $matcher,
        private readonly TagihanNominalResolver $nominalResolver,
    ) {
    }

    public function generate(JenisTagihan $jenisTagihan, string $triggerType, ?string $triggerEvent = null): BillingJobLog
    {
        $targetSiswa = $this->matcher->resolveTargetSiswa($jenisTagihan);

        $billsGenerated = 0;
        $errors = [];

        foreach ($targetSiswa as $siswa) {
            try {
                if ($this->generateForSiswa($siswa, $jenisTagihan, $triggerType)) {
                    $billsGenerated++;
                }
            } catch (\Throwable $e) {
                $errors[] = ['siswa_id' => $siswa->id, 'message' => $e->getMessage()];
            }
        }

        return $this->logResult($jenisTagihan, $triggerType, $triggerEvent, $billsGenerated, $errors);
    }

    public function generateForSiswa(Siswa $siswa, JenisTagihan $jenisTagihan, string $triggerType): bool
    {
        return DB::transaction(function () use ($siswa, $jenisTagihan, $triggerType) {
            $billingPeriod = $jenisTagihan->mode === 'otomatis' ? now()->format('Y-m') : null;

            $exists = Tagihan::where('tagihable_type', Siswa::class)
                ->where('tagihable_id', $siswa->id)
                ->where('jenis_tagihan_id', $jenisTagihan->id)
                ->where('billing_period', $billingPeriod)
                ->where('status', '!=', 'dibatalkan')
                ->exists();

            if ($exists) {
                return false;
            }

            $resolved = $this->nominalResolver->resolve($siswa, $jenisTagihan);
            $netAmount = max(0, $resolved['nominal'] - $resolved['discount_amount']);

            Tagihan::create([
                'tagihable_type' => Siswa::class,
                'tagihable_id' => $siswa->id,
                'jenis_tagihan_id' => $jenisTagihan->id,
                'kategori' => $jenisTagihan->kategori,
                'billing_period' => $billingPeriod,
                'source_trigger' => $triggerType,
                'total_tagihan' => $resolved['nominal'],
                'discount_amount' => $resolved['discount_amount'] ?: null,
                'discount_type' => $resolved['discount_type'],
                'net_amount' => $netAmount,
                'jatuh_tempo' => $this->resolveDueDate($jenisTagihan, $billingPeriod),
                'status' => 'belum_bayar',
            ]);

            return true;
        });
    }

    public function generateForSiswaViaEvent(Siswa $siswa, JenisTagihan $jenisTagihan, string $triggerEvent): BillingJobLog
    {
        $billsGenerated = 0;
        $errors = [];

        try {
            if ($this->generateForSiswa($siswa, $jenisTagihan, 'event')) {
                $billsGenerated = 1;
            }
        } catch (\Throwable $e) {
            $errors[] = ['siswa_id' => $siswa->id, 'message' => $e->getMessage()];
        }

        return $this->logResult($jenisTagihan, 'event', $triggerEvent, $billsGenerated, $errors);
    }

    private function resolveDueDate(JenisTagihan $jenisTagihan, ?string $billingPeriod): ?string
    {
        if (! $billingPeriod || ! $jenisTagihan->hari_jatuh_tempo) {
            return null;
        }

        $year = (int) substr($billingPeriod, 0, 4);
        $month = (int) substr($billingPeriod, 5, 2);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $day = min($jenisTagihan->hari_jatuh_tempo, $daysInMonth);

        return Carbon::create($year, $month, $day)->toDateString();
    }

    private function logResult(JenisTagihan $jenisTagihan, string $triggerType, ?string $triggerEvent, int $billsGenerated, array $errors): BillingJobLog
    {
        $status = match (true) {
            empty($errors) => 'success',
            $billsGenerated === 0 => 'failed',
            default => 'partial',
        };

        return BillingJobLog::create([
            'jenis_tagihan_id' => $jenisTagihan->id,
            'trigger_type' => $triggerType,
            'trigger_event' => $triggerEvent,
            'period' => $jenisTagihan->mode === 'otomatis' ? now()->format('Y-m') : null,
            'bills_generated' => $billsGenerated,
            'status' => $status,
            'error_log' => empty($errors) ? null : $errors,
            'executed_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TagihanBillingGeneratorTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/TagihanBillingGenerator.php tests/Feature/Keuangan/TagihanBillingGeneratorTest.php
git commit -m "feat(keuangan): add TagihanBillingGenerator orchestration service"
```

---

### Task 5: `billing:proses` manual-trigger command

**Files:**
- Create: `app/Console/Commands/ProsesTagihan.php`
- Test: `tests/Feature/Keuangan/ProsesTagihanCommandTest.php`

**Interfaces:**
- Consumes: `App\Services\TagihanBillingGenerator::generate()` (Task 4)
- Produces: Artisan command `billing:proses {jenis_tagihan_id}` — foundation for the admin "Proses Tagihan" button built in Sub-project 2b

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/ProsesTagihanCommandTest.php

use App\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;

it('generates tagihan for the given jenis_tagihan_id and reports the count', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000]);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    $this->artisan('billing:proses', ['jenis_tagihan_id' => $jenisTagihan->id])
        ->expectsOutputToContain('1 tagihan dibuat')
        ->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $jenisTagihan->id)->count())->toBe(1);
});

it('fails gracefully when the jenis_tagihan_id does not exist', function () {
    $this->artisan('billing:proses', ['jenis_tagihan_id' => 999999])
        ->expectsOutputToContain('tidak ditemukan')
        ->assertExitCode(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProsesTagihanCommandTest`
Expected: FAIL — `Command "billing:proses" is not defined`

- [ ] **Step 3: Write the command**

```php
<?php
// app/Console/Commands/ProsesTagihan.php

namespace App\Console\Commands;

use App\Models\JenisTagihan;
use App\Services\TagihanBillingGenerator;
use Illuminate\Console\Command;

class ProsesTagihan extends Command
{
    protected $signature = 'billing:proses {jenis_tagihan_id}';

    protected $description = 'Generate tagihan manually for one jenis_tagihan (admin "Proses Tagihan" button, or backfill/testing)';

    public function handle(TagihanBillingGenerator $generator): int
    {
        $jenisTagihan = JenisTagihan::find($this->argument('jenis_tagihan_id'));

        if (! $jenisTagihan) {
            $this->error('Jenis tagihan tidak ditemukan.');

            return self::FAILURE;
        }

        $log = $generator->generate($jenisTagihan, 'manual');

        $this->info("{$log->bills_generated} tagihan dibuat. Status: {$log->status}.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ProsesTagihanCommandTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ProsesTagihan.php tests/Feature/Keuangan/ProsesTagihanCommandTest.php
git commit -m "feat(keuangan): add billing:proses manual-trigger command"
```

---

### Task 6: `billing:generate-harian` cron command + scheduler wiring

**Files:**
- Create: `app/Console/Commands/GenerateTagihanHarian.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php`

**Interfaces:**
- Consumes: `App\Services\TagihanBillingGenerator::generate()` (Task 4)
- Produces: Artisan command `billing:generate-harian`, scheduled daily at 00:01

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php

use App\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use Illuminate\Support\Carbon;

it('processes only jenis_tagihan whose tanggal_generate matches today and is within the active window', function () {
    Carbon::setTestNow('2026-09-15');

    $cocok = JenisTagihan::factory()->create([
        'default_amount' => 200000,
        'mode' => 'otomatis',
        'is_active' => true,
        'tanggal_generate' => 15,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => null,
    ]);
    Siswa::factory()->create(['lembaga_id' => $cocok->lembaga_id]);

    $bedaTanggal = JenisTagihan::factory()->create([
        'default_amount' => 100000,
        'mode' => 'otomatis',
        'is_active' => true,
        'tanggal_generate' => 1,
        'tanggal_mulai' => '2026-01-01',
    ]);
    Siswa::factory()->create(['lembaga_id' => $bedaTanggal->lembaga_id]);

    $sudahSelesai = JenisTagihan::factory()->create([
        'default_amount' => 100000,
        'mode' => 'otomatis',
        'is_active' => true,
        'tanggal_generate' => 15,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-06-30',
    ]);
    Siswa::factory()->create(['lembaga_id' => $sudahSelesai->lembaga_id]);

    $tidakAktif = JenisTagihan::factory()->create([
        'default_amount' => 100000,
        'mode' => 'otomatis',
        'is_active' => false,
        'tanggal_generate' => 15,
        'tanggal_mulai' => '2026-01-01',
    ]);
    Siswa::factory()->create(['lembaga_id' => $tidakAktif->lembaga_id]);

    $this->artisan('billing:generate-harian')
        ->expectsOutputToContain('1 jenis tagihan diproses')
        ->assertExitCode(0);

    expect(Tagihan::where('jenis_tagihan_id', $cocok->id)->count())->toBe(1);
    expect(Tagihan::where('jenis_tagihan_id', $bedaTanggal->id)->count())->toBe(0);
    expect(Tagihan::where('jenis_tagihan_id', $sudahSelesai->id)->count())->toBe(0);
    expect(Tagihan::where('jenis_tagihan_id', $tidakAktif->id)->count())->toBe(0);

    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GenerateTagihanHarianCommandTest`
Expected: FAIL — `Command "billing:generate-harian" is not defined`

- [ ] **Step 3: Write the command**

```php
<?php
// app/Console/Commands/GenerateTagihanHarian.php

namespace App\Console\Commands;

use App\Models\JenisTagihan;
use App\Models\Scopes\TenantScope;
use App\Services\TagihanBillingGenerator;
use Illuminate\Console\Command;

class GenerateTagihanHarian extends Command
{
    protected $signature = 'billing:generate-harian';

    protected $description = 'Cron harian: generate tagihan untuk semua jenis_tagihan mode otomatis yang jatuh tanggal_generate hari ini';

    public function handle(TagihanBillingGenerator $generator): int
    {
        $today = now();

        // withoutGlobalScope: cron runs with no authenticated user so TenantScope would be a
        // no-op here anyway, but stays explicit per this plan's tenant-scope constraint rather
        // than relying on "no session exists right now" to make it safe.
        $kandidat = JenisTagihan::withoutGlobalScope(TenantScope::class)
            ->where('mode', 'otomatis')
            ->where('is_active', true)
            ->where('tanggal_generate', $today->day)
            ->where('tanggal_mulai', '<=', $today->toDateString())
            ->where(function ($q) use ($today) {
                $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $today->toDateString());
            })
            ->get();

        foreach ($kandidat as $jenisTagihan) {
            $generator->generate($jenisTagihan, 'cron');
        }

        $this->info("{$kandidat->count()} jenis tagihan diproses.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Wire the scheduler**

Modify `routes/console.php` — add the import and one scheduled line, keeping the existing two commands untouched:

```php
<?php

use App\Console\Commands\GenerateTagihanHarian;
use App\Console\Commands\KirimReminderSesi;
use App\Console\Commands\TandaiTugasTerlewat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(KirimReminderSesi::class)->dailyAt('07:00');
Schedule::command(TandaiTugasTerlewat::class)->dailyAt('01:00');
Schedule::command(GenerateTagihanHarian::class)->dailyAt('00:01');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=GenerateTagihanHarianCommandTest`
Expected: PASS (1 test)

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/GenerateTagihanHarian.php routes/console.php tests/Feature/Keuangan/GenerateTagihanHarianCommandTest.php
git commit -m "feat(keuangan): add billing:generate-harian cron command"
```

---

### Task 7: `StudentCreated` + `StudentUpdatedClass` events

**Files:**
- Create: `app/Events/StudentCreated.php`
- Create: `app/Events/StudentUpdatedClass.php`
- Create: `app/Listeners/GenerateTagihanForNewStudent.php`
- Create: `app/Listeners/GenerateTagihanForUpdatedClass.php`
- Modify: `app/Models/Siswa.php` (add `booted()`)
- Test: `tests/Feature/Keuangan/StudentBillingEventsTest.php`

**Interfaces:**
- Consumes: `App\Services\JenisTagihanSasaranMatcher::siswaMatchesJenisTagihan()` (Task 2), `App\Services\TagihanBillingGenerator::generateForSiswaViaEvent()` (Task 4)
- Produces: `event(new StudentCreated($siswa))` fires on every `Siswa::create()`; `event(new StudentUpdatedClass($siswa))` fires whenever an existing `Siswa`'s `kelas_id` changes. Laravel 12 auto-discovers the listeners below by their `handle()` type-hint — no manual registration needed.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/StudentBillingEventsTest.php

use App\Models\JenisTagihan;
use App\Models\JenisTagihanSasaranGrup;
use App\Models\JenisTagihanSasaranKriteria;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\Tagihan;

it('generates a tagihan automatically when a new siswa is created and matches an active jenis_tagihan', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000, 'mode' => 'otomatis', 'is_active' => true]);

    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    expect(Tagihan::where('tagihable_type', Siswa::class)->where('tagihable_id', $siswa->id)->where('jenis_tagihan_id', $jenisTagihan->id)->exists())->toBeTrue();
});

it('does not generate a tagihan for a new siswa in a different lembaga', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 200000, 'mode' => 'otomatis', 'is_active' => true]);

    $siswa = Siswa::factory()->create(); // lembaga acak, beda dari $jenisTagihan

    expect(Tagihan::where('tagihable_type', Siswa::class)->where('tagihable_id', $siswa->id)->exists())->toBeFalse();
});

it('generates a tagihan when a siswa moves into a kelas that matches a kelas-scoped jenis_tagihan', function () {
    $kelasBaru = Kelas::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 150000, 'mode' => 'otomatis', 'is_active' => true, 'lembaga_id' => $kelasBaru->lembaga_id]);
    $grup = JenisTagihanSasaranGrup::create(['jenis_tagihan_id' => $jenisTagihan->id, 'tipe' => 'sasaran']);
    JenisTagihanSasaranKriteria::create(['jenis_tagihan_sasaran_grup_id' => $grup->id, 'field' => 'kelas', 'operator' => 'in', 'value' => [$kelasBaru->id]]);

    $siswa = Siswa::factory()->create(['lembaga_id' => $kelasBaru->lembaga_id, 'kelas_id' => null]);
    expect(Tagihan::where('tagihable_id', $siswa->id)->exists())->toBeFalse();

    $siswa->update(['kelas_id' => $kelasBaru->id]);

    expect(Tagihan::where('tagihable_id', $siswa->id)->where('jenis_tagihan_id', $jenisTagihan->id)->exists())->toBeTrue();
});

it('does not fire StudentUpdatedClass when an unrelated field changes', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 150000, 'mode' => 'otomatis', 'is_active' => true]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    Tagihan::query()->delete(); // buang tagihan dari StudentCreated supaya tes ini murni soal update

    $siswa->update(['nama_lengkap' => 'Nama Diperbarui']);

    expect(Tagihan::where('tagihable_id', $siswa->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StudentBillingEventsTest`
Expected: FAIL — no tagihan created (events don't exist yet, nothing fires)

- [ ] **Step 3: Write the events**

```php
<?php
// app/Events/StudentCreated.php

namespace App\Events;

use App\Models\Siswa;
use Illuminate\Foundation\Events\Dispatchable;

class StudentCreated
{
    use Dispatchable;

    public function __construct(public readonly Siswa $siswa)
    {
    }
}
```

```php
<?php
// app/Events/StudentUpdatedClass.php

namespace App\Events;

use App\Models\Siswa;
use Illuminate\Foundation\Events\Dispatchable;

class StudentUpdatedClass
{
    use Dispatchable;

    public function __construct(public readonly Siswa $siswa)
    {
    }
}
```

- [ ] **Step 4: Write the listeners**

```php
<?php
// app/Listeners/GenerateTagihanForNewStudent.php

namespace App\Listeners;

use App\Events\StudentCreated;
use App\Models\JenisTagihan;
use App\Models\Scopes\TenantScope;
use App\Services\JenisTagihanSasaranMatcher;
use App\Services\TagihanBillingGenerator;

class GenerateTagihanForNewStudent
{
    public function __construct(
        private readonly JenisTagihanSasaranMatcher $matcher,
        private readonly TagihanBillingGenerator $generator,
    ) {
    }

    public function handle(StudentCreated $event): void
    {
        $siswa = $event->siswa;

        JenisTagihan::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $siswa->lembaga_id)
            ->where('is_active', true)
            ->get()
            ->each(function (JenisTagihan $jenisTagihan) use ($siswa) {
                if ($this->matcher->siswaMatchesJenisTagihan($siswa, $jenisTagihan)) {
                    $this->generator->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentCreated');
                }
            });
    }
}
```

```php
<?php
// app/Listeners/GenerateTagihanForUpdatedClass.php

namespace App\Listeners;

use App\Events\StudentUpdatedClass;
use App\Models\JenisTagihan;
use App\Models\Scopes\TenantScope;
use App\Services\JenisTagihanSasaranMatcher;
use App\Services\TagihanBillingGenerator;

class GenerateTagihanForUpdatedClass
{
    public function __construct(
        private readonly JenisTagihanSasaranMatcher $matcher,
        private readonly TagihanBillingGenerator $generator,
    ) {
    }

    public function handle(StudentUpdatedClass $event): void
    {
        $siswa = $event->siswa;

        JenisTagihan::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $siswa->lembaga_id)
            ->where('is_active', true)
            ->get()
            ->each(function (JenisTagihan $jenisTagihan) use ($siswa) {
                if ($this->matcher->siswaMatchesJenisTagihan($siswa, $jenisTagihan)) {
                    $this->generator->generateForSiswaViaEvent($siswa, $jenisTagihan, 'StudentUpdatedClass');
                }
            });
    }
}
```

- [ ] **Step 5: Add the model hook**

Modify `app/Models/Siswa.php` — add a `booted()` method. Insert it directly after the `orangTua()` relation method and before `getActivitylogOptions()`:

```php
    protected static function booted(): void
    {
        static::created(fn (Siswa $siswa) => event(new \App\Events\StudentCreated($siswa)));

        static::updated(function (Siswa $siswa) {
            if ($siswa->wasChanged('kelas_id')) {
                event(new \App\Events\StudentUpdatedClass($siswa));
            }
        });
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=StudentBillingEventsTest`
Expected: PASS (4 tests)

- [ ] **Step 7: Run the full existing Siswa-related regression to confirm the new `booted()` hook didn't break anything**

Run: `php artisan test --filter=Siswa`
Expected: PASS, all pre-existing Siswa tests unchanged

- [ ] **Step 8: Commit**

```bash
git add app/Events/StudentCreated.php app/Events/StudentUpdatedClass.php app/Listeners/GenerateTagihanForNewStudent.php app/Listeners/GenerateTagihanForUpdatedClass.php app/Models/Siswa.php tests/Feature/Keuangan/StudentBillingEventsTest.php
git commit -m "feat(keuangan): auto-generate tagihan on StudentCreated/StudentUpdatedClass"
```

---

### Task 8: `BillTypeActivated` event

**Files:**
- Create: `app/Events/BillTypeActivated.php`
- Create: `app/Listeners/GenerateTagihanForActivatedBillType.php`
- Modify: `app/Models/JenisTagihan.php` (add `booted()`)
- Test: `tests/Feature/Keuangan/BillTypeActivatedEventTest.php`

**Interfaces:**
- Consumes: `App\Services\TagihanBillingGenerator::generate()` (Task 4)
- Produces: `event(new BillTypeActivated($jenisTagihan))` fires whenever `jenis_tagihan.is_active` changes from `false` to `true`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Keuangan/BillTypeActivatedEventTest.php

use App\Models\JenisTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;

it('generates tagihan for matching siswa when is_active flips from false to true', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 300000, 'mode' => 'otomatis', 'is_active' => false]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);

    expect(Tagihan::where('tagihable_id', $siswa->id)->exists())->toBeFalse();

    $jenisTagihan->update(['is_active' => true]);

    expect(Tagihan::where('tagihable_id', $siswa->id)->where('jenis_tagihan_id', $jenisTagihan->id)->exists())->toBeTrue();
});

it('does not fire again when is_active is saved as true a second time without changing', function () {
    $jenisTagihan = JenisTagihan::factory()->create(['default_amount' => 300000, 'mode' => 'otomatis', 'is_active' => true]);
    Siswa::factory()->create(['lembaga_id' => $jenisTagihan->lembaga_id]);
    Tagihan::query()->delete();

    $jenisTagihan->update(['nama' => 'Nama Diperbarui']); // is_active tidak berubah

    expect(Tagihan::count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=BillTypeActivatedEventTest`
Expected: FAIL — first test fails, no tagihan created (event doesn't exist yet)

- [ ] **Step 3: Write the event**

```php
<?php
// app/Events/BillTypeActivated.php

namespace App\Events;

use App\Models\JenisTagihan;
use Illuminate\Foundation\Events\Dispatchable;

class BillTypeActivated
{
    use Dispatchable;

    public function __construct(public readonly JenisTagihan $jenisTagihan)
    {
    }
}
```

- [ ] **Step 4: Write the listener**

```php
<?php
// app/Listeners/GenerateTagihanForActivatedBillType.php

namespace App\Listeners;

use App\Events\BillTypeActivated;
use App\Services\TagihanBillingGenerator;

class GenerateTagihanForActivatedBillType
{
    public function __construct(private readonly TagihanBillingGenerator $generator)
    {
    }

    public function handle(BillTypeActivated $event): void
    {
        $this->generator->generate($event->jenisTagihan, 'event', 'BillTypeActivated');
    }
}
```

- [ ] **Step 5: Add the model hook**

Modify `app/Models/JenisTagihan.php` — add a `booted()` method. Insert it directly after the `keringananRules()` relation method (the last method in the class):

```php
    protected static function booted(): void
    {
        static::updated(function (JenisTagihan $jenisTagihan) {
            if ($jenisTagihan->wasChanged('is_active') && $jenisTagihan->is_active) {
                event(new \App\Events\BillTypeActivated($jenisTagihan));
            }
        });
    }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=BillTypeActivatedEventTest`
Expected: PASS (2 tests)

- [ ] **Step 7: Run the existing `JenisTagihanTest.php` and `JenisTagihanBillingColumnsTest.php` to confirm the new `booted()` hook didn't break anything**

Run: `php artisan test tests/Feature/Admin/JenisTagihanTest.php tests/Feature/Keuangan/JenisTagihanBillingColumnsTest.php`
Expected: PASS, all unchanged

- [ ] **Step 8: Commit**

```bash
git add app/Events/BillTypeActivated.php app/Listeners/GenerateTagihanForActivatedBillType.php app/Models/JenisTagihan.php tests/Feature/Keuangan/BillTypeActivatedEventTest.php
git commit -m "feat(keuangan): auto-generate tagihan on BillTypeActivated"
```

---

### Task 9: Full regression suite verification

**Files:** none (verification only)

**Interfaces:** none

- [ ] **Step 1: Run the entire test suite**

Run: `php artisan test`
Expected: PASS — every test green, including all 8 new `tests/Feature/Keuangan/*` files from this plan, all Sub-project 1 tests, and every pre-existing test. The only acceptable failures are the same 6 pre-existing, unrelated ones documented in Sub-project 1's handoff (missing `admin.roles.data` route; `LembagaCrudTest`'s pagination-count test) — verify by name that any failure matches that known list before treating the suite as clean.

- [ ] **Step 2: If anything else fails, fix forward**

Trace any new failure back to the task that introduced it and fix the production code (not the test, unless the test itself was asserting incorrect behavior). Re-run until green.

- [ ] **Step 3: Manually verify the event chain end-to-end via tinker**

```
php artisan tinker
```
```php
$lembaga = \App\Models\Lembaga::first();
$jenisTagihan = \App\Models\JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Test SPP', 'kategori' => 'spp', 'default_amount' => 100000, 'mode' => 'otomatis', 'is_active' => true]);
$siswa = \App\Models\Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
\App\Models\Tagihan::where('tagihable_id', $siswa->id)->where('jenis_tagihan_id', $jenisTagihan->id)->exists(); // expect true (StudentCreated fired, but jenis_tagihan didn't exist yet when siswa was created — see note below)
```
Note: since `$jenisTagihan` is created *before* `$siswa` in this snippet, `StudentCreated` should already pick it up — confirm the tinker output is `true`. Then clean up: `$jenisTagihan->delete(); $siswa->delete();` (cascades via FK).

- [ ] **Step 4: Commit if Step 2 required fixes**

```bash
git add -A
git commit -m "fix(keuangan): address regressions found during sub-project 2a full suite verification"
```
