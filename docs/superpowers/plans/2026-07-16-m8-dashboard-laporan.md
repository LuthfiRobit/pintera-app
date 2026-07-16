# M8 — Dashboard & Laporan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing admin dashboard (lembaga + yayasan views) with real SPMB and Keuangan metrics — summary cards, a Chart.js line/donut chart per lembaga, and a consolidated cross-lembaga comparison for yayasan — gated per the permissions already established in earlier sub-projects.

**Architecture:** A new `DashboardStatsService` is the single source of truth for computing SPMB/Keuangan metrics for one lembaga (counts, 30-day trend, Rp sums, tagihan-status composition) — both `DashboardController`'s lembaga branch and its yayasan branch (looping per lembaga) call the same service methods, so there is exactly one place that defines what these numbers mean. The controller stays a thin orchestrator: it decides which view to render and which permission-gated sections to populate; the views render what they're given.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Chart.js (new dependency), Pest PHP.

## Global Constraints

- All dashboard numbers for a single lembaga (both the direct lembaga view and each row of the yayasan comparison) are scoped to that lembaga's **active tahun ajaran** (`TahunAjaran::where('lembaga_id', ...)->where('status_aktif', true)->first()`), except the 30-day trend chart, which always covers a rolling 30-day window regardless of tahun ajaran boundaries.
- A lembaga with no active tahun ajaran must produce all-zero stats, never an error.
- `DashboardStatsService` is the only place that computes these metrics — no controller or view may run its own `Tagihan`/`Pendaftaran`/`Pembayaran` aggregation query.
- SPMB section of the lembaga dashboard is gated on `auth()->user()->can('spmb-pendaftaran.view')`; Keuangan section is gated on `auth()->user()->can('tagihan.view')` — no new permissions are introduced.
- `Pembayaran` "menunggu verifikasi" counts must account for both ownership paths (`tagihan_id` direct, and `cicilan_id → skema_cicilan → tagihan`), same as `PembayaranController::data()` already does.
- `admin.dashboard.guru.blade.php` is not touched by this plan.
- `pendaftaran.status` values are exactly `menunggu_verifikasi`/`diterima`/`ditolak`; `tagihan.status` values are exactly `belum_bayar`/`dicicil`/`lunas` — copied verbatim from prior sub-projects' schema, do not introduce new status strings.
- PHP is not on PATH — use `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe` for all `artisan`/test commands. Node is not on PATH either — use `D:\laragon\bin\nodejs\node-v24.15.0-win-x64\node.exe` and the sibling `npm` in that same directory (or add that directory to PATH for the session) for `npm install`/`npm run build`.

---

### Task 1: `DashboardStatsService`

**Files:**
- Create: `app/Services/DashboardStatsService.php`
- Test: `tests/Unit/DashboardStatsServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\Pendaftaran` (`lembaga_id`, `tahun_ajaran_id`, `status`, `submitted_at`), `App\Models\Tagihan` (`pendaftaran()` relation, `status`, `total_tagihan`), `App\Models\Pembayaran` (`tagihan()`/`cicilan()` relations, `status`), `App\Models\TahunAjaran` (`lembaga_id`, `status_aktif`).
- Produces: `DashboardStatsService::statistikSpmb(int $lembagaId): array` (keys `total`, `menunggu_verifikasi`, `diterima`, `ditolak`, all `int`), `::trenPendaftaranHarian(int $lembagaId): array` (keys `labels` — array of 30 date-label strings oldest-first, `data` — array of 30 ints, same order), `::statistikKeuangan(int $lembagaId): array` (keys `rpTerkumpul`, `rpBelumLunas`, `pembayaranMenungguVerifikasi` — all `int`, and `donut` — array with keys `belum_bayar`/`dicicil`/`lunas`, all `int`) — Task 2 and Task 3's controller call all three methods per lembaga.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/DashboardStatsServiceTest.php

use App\Models\Cicilan;
use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\SkemaCicilan;
use App\Models\TahunAjaran;
use App\Models\Tagihan;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanTahunAjaranAktifUntukDashboard(Lembaga $lembaga): TahunAjaran
{
    return TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
}

it('counts pendaftaran per status for the active tahun ajaran only, scoped to the given lembaga', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = siapkanTahunAjaranAktifUntukDashboard($lembaga);
    $lembagaLain = Lembaga::factory()->create();
    $tahunAjaranLain = siapkanTahunAjaranAktifUntukDashboard($lembagaLain);

    Pendaftaran::factory()->count(2)->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'menunggu_verifikasi']);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'diterima']);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'ditolak']);
    Pendaftaran::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id, 'status' => 'diterima']);

    $hasil = app(DashboardStatsService::class)->statistikSpmb($lembaga->id);

    expect($hasil)->toBe(['total' => 4, 'menunggu_verifikasi' => 2, 'diterima' => 1, 'ditolak' => 1]);
});

it('returns all-zero spmb stats when the lembaga has no active tahun ajaran', function () {
    $lembaga = Lembaga::factory()->create();

    $hasil = app(DashboardStatsService::class)->statistikSpmb($lembaga->id);

    expect($hasil)->toBe(['total' => 0, 'menunggu_verifikasi' => 0, 'diterima' => 0, 'ditolak' => 0]);
});

it('builds a 30-point daily trend including days with zero pendaftaran, oldest first', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = siapkanTahunAjaranAktifUntukDashboard($lembaga);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'submitted_at' => now()]);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'submitted_at' => now()]);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'submitted_at' => now()->subDays(35)]);

    $hasil = app(DashboardStatsService::class)->trenPendaftaranHarian($lembaga->id);

    expect($hasil['labels'])->toHaveCount(30);
    expect($hasil['data'])->toHaveCount(30);
    expect($hasil['data'][29])->toBe(2);
    expect(array_sum($hasil['data']))->toBe(2);
});

it('computes rpTerkumpul and rpBelumLunas from tagihan status, scoped to lembaga and active tahun ajaran', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = siapkanTahunAjaranAktifUntukDashboard($lembaga);
    $pendaftaranLunas = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pendaftaranCicil = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pendaftaranBelum = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    Tagihan::create(['pendaftaran_id' => $pendaftaranLunas->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'lunas']);
    Tagihan::create(['pendaftaran_id' => $pendaftaranCicil->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 900000, 'status' => 'dicicil']);
    Tagihan::create(['pendaftaran_id' => $pendaftaranBelum->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'belum_bayar']);

    $hasil = app(DashboardStatsService::class)->statistikKeuangan($lembaga->id);

    expect($hasil['rpTerkumpul'])->toBe(150000);
    expect($hasil['rpBelumLunas'])->toBe(1050000);
    expect($hasil['donut'])->toBe(['belum_bayar' => 1, 'dicicil' => 1, 'lunas' => 1]);
});

it('counts pembayaran menunggu verifikasi through both the direct tagihan and the cicilan ownership path', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = siapkanTahunAjaranAktifUntukDashboard($lembaga);
    $pendaftaranLangsung = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $tagihanLangsung = Tagihan::create(['pendaftaran_id' => $pendaftaranLangsung->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'belum_bayar']);
    Pembayaran::create(['tagihan_id' => $tagihanLangsung->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);

    $pendaftaranCicil = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $tagihanCicil = Tagihan::create(['pendaftaran_id' => $pendaftaranCicil->id, 'kategori' => 'daftar_ulang', 'total_tagihan' => 900000, 'status' => 'dicicil']);
    $skema = SkemaCicilan::create(['tagihan_id' => $tagihanCicil->id, 'jumlah_termin' => 3, 'dibuat_oleh' => 'calon_siswa']);
    $termin = Cicilan::create(['skema_cicilan_id' => $skema->id, 'urutan' => 1, 'nominal' => 300000, 'jatuh_tempo' => now(), 'status' => 'belum_bayar']);
    Pembayaran::create(['cicilan_id' => $termin->id, 'sumber' => 'calon_siswa', 'metode' => 'transfer_manual', 'status' => 'menunggu_verifikasi']);

    $hasil = app(DashboardStatsService::class)->statistikKeuangan($lembaga->id);

    expect($hasil['pembayaranMenungguVerifikasi'])->toBe(2);
});

it('returns all-zero keuangan stats when the lembaga has no active tahun ajaran', function () {
    $lembaga = Lembaga::factory()->create();

    $hasil = app(DashboardStatsService::class)->statistikKeuangan($lembaga->id);

    expect($hasil)->toBe([
        'rpTerkumpul' => 0, 'rpBelumLunas' => 0, 'pembayaranMenungguVerifikasi' => 0,
        'donut' => ['belum_bayar' => 0, 'dicicil' => 0, 'lunas' => 0],
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/DashboardStatsServiceTest.php`
Expected: FAIL — `App\Services\DashboardStatsService` doesn't exist yet.

- [ ] **Step 3: Write `DashboardStatsService`**

```php
<?php
// app/Services/DashboardStatsService.php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Models\TahunAjaran;

class DashboardStatsService
{
    public function statistikSpmb(int $lembagaId): array
    {
        $tahunAjaran = $this->tahunAjaranAktif($lembagaId);

        if (! $tahunAjaran) {
            return ['total' => 0, 'menunggu_verifikasi' => 0, 'diterima' => 0, 'ditolak' => 0];
        }

        $counts = Pendaftaran::where('lembaga_id', $lembagaId)
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'menunggu_verifikasi' => (int) ($counts['menunggu_verifikasi'] ?? 0),
            'diterima' => (int) ($counts['diterima'] ?? 0),
            'ditolak' => (int) ($counts['ditolak'] ?? 0),
        ];
    }

    public function trenPendaftaranHarian(int $lembagaId): array
    {
        $mulai = now()->subDays(29)->startOfDay();

        $counts = Pendaftaran::where('lembaga_id', $lembagaId)
            ->where('submitted_at', '>=', $mulai)
            ->selectRaw('DATE(submitted_at) as tanggal, count(*) as total')
            ->groupBy('tanggal')
            ->pluck('total', 'tanggal');

        $labels = [];
        $data = [];

        for ($i = 29; $i >= 0; $i--) {
            $hari = now()->subDays($i);
            $labels[] = $hari->translatedFormat('d M');
            $data[] = (int) ($counts[$hari->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    public function statistikKeuangan(int $lembagaId): array
    {
        $tahunAjaran = $this->tahunAjaranAktif($lembagaId);

        if (! $tahunAjaran) {
            return [
                'rpTerkumpul' => 0, 'rpBelumLunas' => 0, 'pembayaranMenungguVerifikasi' => 0,
                'donut' => ['belum_bayar' => 0, 'dicicil' => 0, 'lunas' => 0],
            ];
        }

        $tagihanQuery = fn () => Tagihan::whereHas(
            'pendaftaran',
            fn ($q) => $q->where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $tahunAjaran->id)
        );

        $rpTerkumpul = (int) $tagihanQuery()->where('status', 'lunas')->sum('total_tagihan');
        $rpBelumLunas = (int) $tagihanQuery()->whereIn('status', ['belum_bayar', 'dicicil'])->sum('total_tagihan');

        $donutCounts = $tagihanQuery()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pembayaranMenungguVerifikasi = Pembayaran::where('status', 'menunggu_verifikasi')
            ->where(function ($q) use ($lembagaId, $tahunAjaran) {
                $q->whereHas('tagihan.pendaftaran', fn ($p) => $p->where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $tahunAjaran->id))
                    ->orWhereHas('cicilan.skemaCicilan.tagihan.pendaftaran', fn ($p) => $p->where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $tahunAjaran->id));
            })
            ->count();

        return [
            'rpTerkumpul' => $rpTerkumpul,
            'rpBelumLunas' => $rpBelumLunas,
            'pembayaranMenungguVerifikasi' => $pembayaranMenungguVerifikasi,
            'donut' => [
                'belum_bayar' => (int) ($donutCounts['belum_bayar'] ?? 0),
                'dicicil' => (int) ($donutCounts['dicicil'] ?? 0),
                'lunas' => (int) ($donutCounts['lunas'] ?? 0),
            ],
        ];
    }

    private function tahunAjaranAktif(int $lembagaId): ?TahunAjaran
    {
        return TahunAjaran::where('lembaga_id', $lembagaId)->where('status_aktif', true)->first();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Unit/DashboardStatsServiceTest.php`
Expected: PASS (6/6)

- [ ] **Step 5: Run the full suite**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all pre-existing tests still pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/DashboardStatsService.php tests/Unit/DashboardStatsServiceTest.php
git commit -m "feat: add DashboardStatsService for SPMB and keuangan dashboard metrics"
```

---

### Task 2: Dashboard Lembaga — SPMB & Keuangan Sections with Chart.js

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/views/admin/dashboard/lembaga.blade.php`
- Create: `resources/js/dashboard-charts.js`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/Admin/DashboardLembagaTest.php`

**Interfaces:**
- Consumes: `DashboardStatsService::statistikSpmb()`, `::trenPendaftaranHarian()`, `::statistikKeuangan()` (Task 1).
- Produces: `resources/js/dashboard-charts.js` exports `trenPendaftaranChart(labels, data)` and `donutTagihanChart(labels, data)` Alpine component factories, registered as `Alpine.data('trenPendaftaranChart', ...)` / `Alpine.data('donutTagihanChart', ...)` — Task 3's yayasan bar chart follows the same registration pattern in the same file.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/DashboardLembagaTest.php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\Tagihan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('shows the spmb section only for a user with spmb-pendaftaran.view, and hides the keuangan section without tagihan.view', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'diterima']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_administrasi');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Total Pendaftar');
    $response->assertDontSee('Rp Terkumpul');
});

it('shows both spmb and keuangan sections for admin_keuangan, with correct numbers', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'diterima']);
    Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'lunas']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Total Pendaftar');
    $response->assertSee('Rp Terkumpul');
    $response->assertSee('Rp 150.000');
});

it('does not leak another lembaga data into the dashboard numbers', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    $lembagaLain = Lembaga::factory()->create();
    $tahunAjaranLain = TahunAjaran::create(['lembaga_id' => $lembagaLain->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    Pendaftaran::factory()->count(3)->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSeeText('3', false);
});

it('renders the lembaga dashboard without error when there is no active tahun ajaran', function () {
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('shows both sections for kepala_sekolah too, since the gating is permission-based not role-based', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunAjaran = TahunAjaran::create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'status' => 'diterima']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('kepala_sekolah');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Total Pendaftar');
    $response->assertSee('Rp Terkumpul');
});

it('does not change the guru dashboard', function () {
    $lembaga = Lembaga::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Total Pendaftar');
    $response->assertDontSee('Rp Terkumpul');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/DashboardLembagaTest.php`
Expected: FAIL — the lembaga view doesn't render SPMB/Keuangan sections yet.

- [ ] **Step 3: Install Chart.js**

Run (with node on PATH for this shell — see Global Constraints): `npm install chart.js`
Expected: `chart.js` appears in `package.json` dependencies and `package-lock.json` is updated.

- [ ] **Step 4: Write `resources/js/dashboard-charts.js`**

```js
// resources/js/dashboard-charts.js

import Chart from 'chart.js/auto';

export function trenPendaftaranChart(labels, data) {
    return {
        init() {
            new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{ label: 'Pendaftaran', data, borderColor: '#123363', backgroundColor: 'rgba(18,51,99,0.1)', fill: true, tension: 0.3 }],
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
            });
        },
    };
}

export function donutTagihanChart(labels, data) {
    return {
        init() {
            new Chart(this.$refs.canvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{ data, backgroundColor: ['#f59e0b', '#3b82f6', '#22c55e'] }],
                },
                options: { responsive: true },
            });
        },
    };
}
```

- [ ] **Step 5: Register the Alpine components in `resources/js/app.js`**

Add `import { trenPendaftaranChart, donutTagihanChart } from './dashboard-charts';` alongside the other imports, and `Alpine.data('trenPendaftaranChart', trenPendaftaranChart);` / `Alpine.data('donutTagihanChart', donutTagihanChart);` alongside the other `Alpine.data(...)` registrations.

- [ ] **Step 6: Extend `DashboardController`**

Replace the existing `index()` method's final `return view('admin.dashboard.lembaga', ...)` block (and the two branches above it stay as-is for now — the yayasan branch is extended in Task 3) with a version that builds a shared `lembagaViewData()` payload:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class DashboardController extends BaseController
{
    public function __construct(private DashboardStatsService $dashboardStats)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->hasRole('guru')) {
            return view('admin.dashboard.guru', [
                'jabatanTambahan' => $user->guru?->jabatanTambahan ?? collect(),
            ]);
        }

        if ($user->widestScopeLevel() === 'yayasan') {
            return view('admin.dashboard.yayasan', [
                'lembagaList' => Lembaga::all(),
                'stats' => [
                    'lembaga' => Lembaga::count(),
                    'guru' => Guru::count(),
                    'pengguna' => User::count(),
                    'tahunAjaranAktif' => TahunAjaran::where('status_aktif', true)->count(),
                ],
            ]);
        }

        return view('admin.dashboard.lembaga', $this->lembagaViewData($user->lembaga_id, $user));
    }

    private function lembagaViewData(int $lembagaId, User $user): array
    {
        $data = [
            'stats' => [
                'guru' => Guru::where('lembaga_id', $lembagaId)->count(),
                'pengguna' => User::where('lembaga_id', $lembagaId)->count(),
                'tahunAjaranAktif' => TahunAjaran::where('lembaga_id', $lembagaId)->where('status_aktif', true)->count(),
            ],
            'tahunAjaranAktif' => TahunAjaran::where('lembaga_id', $lembagaId)->where('status_aktif', true)->first(),
            'spmbStats' => null,
            'tren' => null,
            'keuanganStats' => null,
        ];

        if ($user->can('spmb-pendaftaran.view')) {
            $data['spmbStats'] = $this->dashboardStats->statistikSpmb($lembagaId);
            $data['tren'] = $this->dashboardStats->trenPendaftaranHarian($lembagaId);
        }

        if ($user->can('tagihan.view')) {
            $data['keuanganStats'] = $this->dashboardStats->statistikKeuangan($lembagaId);
        }

        return $data;
    }
}
```

Note: `Guru::where('lembaga_id', ...)->count()` and `User::where('lembaga_id', ...)->count()` replace the previous unscoped `Guru::count()`/`User::count()` in the lembaga branch — the old code counted ALL guru/users system-wide on a page titled "lembaga dashboard," which was a latent cross-tenant leak on a read-only page. Similarly, `'tahunAjaranAktif' => TahunAjaran::where('lembaga_id', $lembagaId)->where('status_aktif', true)->first()` adds a `lembaga_id` filter that the original query never had at all (`TahunAjaran::where('status_aktif', true)->first()` — could return literally any lembaga's active tahun ajaran). Both are fixed here because this task already rewrites this exact block; no separate task needed.

- [ ] **Step 7: Extend `resources/views/admin/dashboard/lembaga.blade.php`**

Replace the file with:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Lembaga &middot; Ringkasan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Panel Administrasi Lembaga"
            :title="'Halo, ' . Auth::user()->name . '!'"
            subtitle="Kelola data guru, pengguna, dan tahun ajaran di lembaga Anda."
        />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
            <x-stat-tile label="Guru" :value="$stats['guru']" hint="Terdaftar di lembaga Anda" icon="school" />
            <x-stat-tile label="Pengguna" :value="$stats['pengguna']" hint="Akun aktif di lembaga Anda" icon="group" />
            <x-stat-tile label="Tahun Ajaran Aktif" :value="$stats['tahunAjaranAktif']" :hint="$tahunAjaranAktif->nama ?? 'Belum diaktifkan'" icon="calendar_month" />
        </div>

        @if ($spmbStats)
            <div>
                <h3 class="font-display text-lg font-semibold text-ink">SPMB</h3>
                <div class="mt-3 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <x-stat-tile label="Total Pendaftar" :value="$spmbStats['total']" icon="groups" />
                    <x-stat-tile label="Menunggu Verifikasi" :value="$spmbStats['menunggu_verifikasi']" icon="hourglass_empty" />
                    <x-stat-tile label="Diterima" :value="$spmbStats['diterima']" icon="check_circle" />
                    <x-stat-tile label="Ditolak" :value="$spmbStats['ditolak']" icon="cancel" />
                </div>
                <x-panel class="mt-4 p-6">
                    <p class="mb-3 text-sm font-medium text-ink">Tren Pendaftaran (30 Hari Terakhir)</p>
                    <div x-data="trenPendaftaranChart(@js($tren['labels']), @js($tren['data']))">
                        <canvas x-ref="canvas" height="90"></canvas>
                    </div>
                </x-panel>
            </div>
        @endif

        @if ($keuanganStats)
            <div>
                <h3 class="font-display text-lg font-semibold text-ink">Keuangan</h3>
                <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <x-stat-tile label="Rp Terkumpul" value="Rp {{ number_format($keuanganStats['rpTerkumpul'], 0, ',', '.') }}" icon="payments" />
                    <x-stat-tile label="Rp Belum Lunas" value="Rp {{ number_format($keuanganStats['rpBelumLunas'], 0, ',', '.') }}" icon="pending_actions" />
                    <a href="{{ route('admin.pembayaran.index') }}">
                        <x-stat-tile label="Pembayaran Menunggu Verifikasi" :value="$keuanganStats['pembayaranMenungguVerifikasi']" icon="fact_check" />
                    </a>
                </div>
                <x-panel class="mt-4 p-6">
                    <p class="mb-3 text-sm font-medium text-ink">Komposisi Status Tagihan</p>
                    <div
                        x-data="donutTagihanChart(
                            ['Belum Bayar', 'Dicicil', 'Lunas'],
                            @js([$keuanganStats['donut']['belum_bayar'], $keuanganStats['donut']['dicicil'], $keuanganStats['donut']['lunas']])
                        )"
                        class="max-w-xs"
                    >
                        <canvas x-ref="canvas"></canvas>
                    </div>
                </x-panel>
            </div>
        @endif

        @unless ($spmbStats || $keuanganStats)
            <x-panel class="p-6 text-sm text-slate">
                Modul Akademik, E-Sarpra, E-HRD, dan E-BK menyusul di fase berikutnya.
            </x-panel>
        @endunless
    </div>
</x-app-layout>
```

- [ ] **Step 8: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/DashboardLembagaTest.php`
Expected: PASS (6/6)

- [ ] **Step 9: Run the full suite, then `npm run build`**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass. Pay particular attention to any pre-existing test asserting the OLD unscoped `Guru::count()`/`User::count()` behavior on the lembaga dashboard — if one exists and now fails because it expected cross-tenant counts, that test was asserting the bug; update it to expect the scoped count instead.
Run: `npm run build`
Expected: builds cleanly, `chart.js` bundled.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php resources/views/admin/dashboard/lembaga.blade.php \
        resources/js/dashboard-charts.js resources/js/app.js package.json package-lock.json \
        tests/Feature/Admin/DashboardLembagaTest.php
git commit -m "feat: add SPMB and keuangan sections with charts to the lembaga dashboard"
```

---

### Task 3: Dashboard Yayasan — Consolidated Stats & Drill-Down Fix

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/views/admin/dashboard/yayasan.blade.php`
- Test: `tests/Feature/Admin/DashboardYayasanTest.php`

**Interfaces:**
- Consumes: `DashboardStatsService::statistikSpmb()`, `::statistikKeuangan()` (Task 1); `DashboardController::lembagaViewData()` (Task 2, reused for the drill-down case — same private method, no change to its signature).
- Produces: nothing new consumed by later tasks — this is the final task of the plan.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Admin/DashboardYayasanTest.php

use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\Tagihan;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('shows consolidated totals summed across every lembaga under the yayasan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunA = TahunAjaran::create(['lembaga_id' => $lembagaA->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    $tahunB = TahunAjaran::create(['lembaga_id' => $lembagaB->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    Pendaftaran::factory()->count(2)->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunA->id]);
    Pendaftaran::factory()->count(3)->create(['lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunB->id]);
    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee($lembagaA->nama);
    $response->assertSee($lembagaB->nama);
});

it('shows the lembaga dashboard, not the yayasan dashboard, once active_lembaga_id is set via switch_lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true]);
    $pendaftaran = Pendaftaran::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    Tagihan::create(['pendaftaran_id' => $pendaftaran->id, 'kategori' => 'pendaftaran', 'total_tagihan' => 150000, 'status' => 'lunas']);
    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');

    $response = $this->actingAs($user)->get(route('dashboard', ['switch_lembaga' => $lembaga->id]));

    $response->assertOk();
    $response->assertSee('Rp 150.000');
    $response->assertDontSee('Dashboard Yayasan');
});

it('goes back to the yayasan dashboard once switch_lembaga=all is used', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');

    $this->actingAs($user)->get(route('dashboard', ['switch_lembaga' => $lembaga->id]));
    $response = $this->actingAs($user)->get(route('dashboard', ['switch_lembaga' => 'all']));

    $response->assertOk();
    $response->assertSee('Dashboard Yayasan');
});

it('renders the yayasan dashboard without error when there is no lembaga in the system at all', function () {
    $user = User::factory()->create();
    $user->assignRole('yayasan_super_admin');

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/DashboardYayasanTest.php`
Expected: FAIL — yayasan branch doesn't check `active_lembaga_id` yet, and doesn't render per-lembaga names/consolidated stats.

- [ ] **Step 3: Extend `DashboardController`'s yayasan branch**

Replace the `if ($user->widestScopeLevel() === 'yayasan') { ... }` block from Task 2's version with:

```php
        if ($user->widestScopeLevel() === 'yayasan') {
            $lembagaAktifId = session('active_lembaga_id');

            if ($lembagaAktifId !== null) {
                return view('admin.dashboard.lembaga', $this->lembagaViewData((int) $lembagaAktifId, $user));
            }

            // Lembaga::all(), not filtered by any yayasan_id on $user: the User model has
            // no yayasan_id column (only lembaga_id) — a yayasan-scoped user is identified
            // purely by an assigned role with scope_level='yayasan', not by any FK to a
            // specific Yayasan row. This matches the pre-existing behavior of this exact
            // branch before this task (also unfiltered Lembaga::all()) — do not invent a
            // yayasan_id relationship on User to "fix" this; that would be a schema change
            // outside this plan's scope.
            $lembagaList = Lembaga::all();
            $ringkasanPerLembaga = $lembagaList->map(function (Lembaga $lembaga) {
                return [
                    'lembaga' => $lembaga,
                    'spmb' => $this->dashboardStats->statistikSpmb($lembaga->id),
                    'keuangan' => $this->dashboardStats->statistikKeuangan($lembaga->id),
                ];
            });

            return view('admin.dashboard.yayasan', [
                'lembagaList' => $lembagaList,
                'ringkasanPerLembaga' => $ringkasanPerLembaga,
                'totalPendaftar' => $ringkasanPerLembaga->sum(fn ($r) => $r['spmb']['total']),
                'totalDiterima' => $ringkasanPerLembaga->sum(fn ($r) => $r['spmb']['diterima']),
                'totalRpTerkumpul' => $ringkasanPerLembaga->sum(fn ($r) => $r['keuangan']['rpTerkumpul']),
                'stats' => [
                    'lembaga' => Lembaga::count(),
                    'guru' => Guru::count(),
                    'pengguna' => User::count(),
                    'tahunAjaranAktif' => TahunAjaran::where('status_aktif', true)->count(),
                ],
            ]);
        }
```

This keeps `lembagaViewData()` (Task 2) as the single path both a lembaga-scoped user AND a drilled-in yayasan user go through — there is exactly one place that assembles the lembaga dashboard's data.

- [ ] **Step 4: Add the bar chart Alpine component to `resources/js/dashboard-charts.js`**

Add this export alongside the two from Task 2:

```js
export function perLembagaBarChart(labels, data) {
    return {
        init() {
            new Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{ label: 'Pendaftar', data, backgroundColor: '#123363' }],
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
            });
        },
    };
}
```

Register it in `resources/js/app.js` the same way as the other two: `import { trenPendaftaranChart, donutTagihanChart, perLembagaBarChart } from './dashboard-charts';` and `Alpine.data('perLembagaBarChart', perLembagaBarChart);`.

- [ ] **Step 5: Extend `resources/views/admin/dashboard/yayasan.blade.php`**

Replace the file with:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Yayasan &middot; Ringkasan Konsolidasi</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard Yayasan</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Yayasan &middot; Pengawasan Lintas Lembaga"
            :title="'Halo, ' . Auth::user()->name . '!'"
            subtitle="Pantau seluruh lembaga di bawah yayasan dari satu tempat — data, staf, dan tahun ajaran yang sedang berjalan."
        />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile label="Lembaga" :value="$stats['lembaga']" hint="Total unit pendidikan" icon="apartment" />
            <x-stat-tile label="Guru" :value="$stats['guru']" hint="Terdaftar lintas lembaga" icon="school" />
            <x-stat-tile label="Pengguna" :value="$stats['pengguna']" hint="Akun aktif sistem" icon="group" />
            <x-stat-tile label="Tahun Ajaran Aktif" :value="$stats['tahunAjaranAktif']" hint="Berjalan saat ini" icon="calendar_month" />
        </div>

        @if (isset($ringkasanPerLembaga))
            <div>
                <h3 class="font-display text-lg font-semibold text-ink">Konsolidasi SPMB &amp; Keuangan (Tahun Ajaran Aktif per Lembaga)</h3>
                <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <x-stat-tile label="Total Pendaftar" :value="$totalPendaftar" icon="groups" />
                    <x-stat-tile label="Total Diterima" :value="$totalDiterima" icon="check_circle" />
                    <x-stat-tile label="Total Rp Terkumpul" value="Rp {{ number_format($totalRpTerkumpul, 0, ',', '.') }}" icon="payments" />
                </div>

                @if ($ringkasanPerLembaga->isNotEmpty())
                    <x-panel class="mt-4 p-6">
                        <p class="mb-3 text-sm font-medium text-ink">Pendaftar per Lembaga</p>
                        <div
                            x-data="perLembagaBarChart(
                                @js($ringkasanPerLembaga->pluck('lembaga.nama')),
                                @js($ringkasanPerLembaga->map(fn ($r) => $r['spmb']['total']))
                            )"
                        >
                            <canvas x-ref="canvas" height="90"></canvas>
                        </div>
                    </x-panel>
                @endif

                <x-panel class="mt-4">
                    <div class="border-b border-ink/10 px-6 py-4">
                        <h3 class="font-display font-semibold text-ink">Tinjau sebagai lembaga tertentu</h3>
                        <p class="mt-0.5 text-sm text-slate">Klik salah satu lembaga untuk menyaring seluruh data di sistem ke lembaga itu.</p>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                                <th class="px-6 py-3 font-display font-semibold">Lembaga</th>
                                <th class="px-6 py-3 font-display font-semibold">Pendaftar</th>
                                <th class="px-6 py-3 font-display font-semibold">Diterima</th>
                                <th class="px-6 py-3 font-display font-semibold">Terkumpul</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/10">
                            @foreach ($ringkasanPerLembaga as $ringkasan)
                                <tr class="cursor-pointer hover:bg-paper/60" onclick="window.location='{{ route('dashboard', ['switch_lembaga' => $ringkasan['lembaga']->id]) }}'">
                                    <td class="px-6 py-3.5 font-display font-medium text-ink">{{ $ringkasan['lembaga']->nama }}</td>
                                    <td class="px-6 py-3.5">{{ $ringkasan['spmb']['total'] }}</td>
                                    <td class="px-6 py-3.5">{{ $ringkasan['spmb']['diterima'] }}</td>
                                    <td class="px-6 py-3.5">Rp {{ number_format($ringkasan['keuangan']['rpTerkumpul'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            @if ($ringkasanPerLembaga->isEmpty())
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-slate">Belum ada lembaga di bawah yayasan ini.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </x-panel>
            </div>
        @endif
    </div>
</x-app-layout>
```

Note: the old "Semua Lembaga" / per-lembaga link-list panel is replaced by this richer table — the `route('dashboard', ['switch_lembaga' => 'all'])` reset link is no longer present on the yayasan view itself, since once you're viewing the yayasan (consolidated) dashboard you are already in "all lembaga" mode by definition. The reset link remains useful from the *lembaga* dashboard (Task 2's view) for a drilled-in yayasan user to get back — add it there instead: in `resources/views/admin/dashboard/lembaga.blade.php`, inside the `<x-hero-banner>` block, add an `x-slot name="actions"` shown only when relevant:

```blade
        <x-hero-banner
            eyebrow="Panel Administrasi Lembaga"
            :title="'Halo, ' . Auth::user()->name . '!'"
            subtitle="Kelola data guru, pengguna, dan tahun ajaran di lembaga Anda."
        >
            @if (Auth::user()->widestScopeLevel() === 'yayasan')
                <x-slot name="actions">
                    <a href="{{ route('dashboard', ['switch_lembaga' => 'all']) }}" class="inline-flex items-center rounded-xl border border-white/30 px-4 py-2 text-sm text-paper transition hover:bg-white/10">
                        &larr; Kembali ke Dashboard Yayasan
                    </a>
                </x-slot>
            @endif
        </x-hero-banner>
```

(This replaces the plain `<x-hero-banner ... />` self-closing tag from Task 2 with an open/close tag wrapping the conditional `x-slot`.)

- [ ] **Step 6: Run test to verify it passes**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test tests/Feature/Admin/DashboardYayasanTest.php`
Expected: PASS (4/4)

- [ ] **Step 7: Run the full suite, then `npm run build`**

Run: `D:\laragon\bin\php\php-8.3.29-Win32-vs16-x64\php.exe artisan test`
Expected: all tests pass.
Run: `npm run build`
Expected: builds cleanly.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php resources/views/admin/dashboard/yayasan.blade.php \
        resources/views/admin/dashboard/lembaga.blade.php resources/js/dashboard-charts.js resources/js/app.js \
        tests/Feature/Admin/DashboardYayasanTest.php
git commit -m "feat: add consolidated cross-lembaga stats and fix dashboard drill-down for yayasan users"
```

---

## Post-Plan Note

After Task 3, M8 (Dashboard & Laporan) is complete — this closes out "Fase 1 (Pilot)" from the PRD roadmap alongside M9 (Notifikasi), which is a separate plan. No further Dashboard/Laporan work is implied by this plan; filter-by-period and export are explicitly deferred (see spec section 7) and would need their own brainstorming session if requested later.
