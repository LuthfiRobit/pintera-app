# Penyempurnaan Data 7 Dashboard Multi-Role Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyambungkan data akademik/keuangan/SDM nyata ke 7 dashboard role (Platform, Yayasan, Lembaga, Karyawan, Guru, Orang Tua, Siswa) yang saat ini sebagian besar hanya menampilkan data modul Kasus, memakai komponen Blade dan pola chart yang SUDAH ADA (bukan sistem baru).

**Architecture:** `DashboardStatsService` diperluas dengan method agregasi baru per kebutuhan dashboard, `DashboardController::index()` memanggilnya per cabang role, Blade view existing (`admin/dashboard/*.blade.php`) ditambah section baru memakai `<x-stat-tile>`/`<x-panel>`/`<x-badge>` yang sudah ada, chart baru mengikuti pola Alpine di `resources/js/dashboard-charts.js`.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Chart.js 4.5 (sudah terinstall), Pest.

## Global Constraints

- Baseline kode: commit `6ac08e6` di branch `rbac-v2`. Kalau isi file berbeda signifikan dari baseline, STOP, laporkan ke user.
- **JANGAN buat komponen Blade baru** — reuse `<x-hero-banner>`, `<x-stat-tile>`, `<x-panel>`, `<x-badge>`, `<x-icon>` yang sudah ada di `resources/views/components/`.
- **JANGAN perkenalkan token visual baru** — pertahankan `border-gray-200 bg-white shadow-card` (card), gradient `ink`→`#123363` (hero banner), `font-display` (heading) yang sudah dipakai di semua dashboard existing.
- Chart baru WAJIB mengikuti pola `resources/js/dashboard-charts.js` (fungsi baru diekspor dari file yang sama, didaftarkan `Alpine.data(...)` di `resources/js/app.js`) — JANGAN cara lain.
- Semua query baru WAJIB scope ke tenant yang benar (`lembaga_id`/`yayasan_id` eksplisit atau lewat `TenantScope` yang sudah otomatis untuk model `BelongsToTenant`).
- Data kosong (kelas belum ada nilai, karyawan belum ada config kuota, dst) WAJIB ditampilkan sebagai state kosong yang jelas, JANGAN crash/exception.
- Test scoped SEBELUM commit. Full suite HANYA di task terakhir, izin eksplisit user dulu.

---

## Task 1: Perluas `DashboardStatsService` — Method Agregasi Baru

**Files:**
- Modify: `app/Services/DashboardStatsService.php`
- Test: `tests/Unit/DashboardStatsServiceTest.php`

**Interfaces:**
- Produces: `statistikPresensiSdm(array $lembagaIds): array`, `statistikProgressRaporKelas(\App\Models\Kelas $kelas): array`, `statistikSisaKuotaCuti(\App\Models\Karyawan $karyawan): ?array`, `trenPertumbuhanYayasan(): array` — dipakai Task 3-8.

- [ ] **Step 1: Baca ulang file existing, konfirmasi 3 method sama dengan baseline**

```bash
cat app/Services/DashboardStatsService.php
```

- [ ] **Step 2: Verifikasi kolom `lembaga_id` di `MataPelajaran` (sudah dikonfirmasi ada, cek ulang untuk memastikan baseline belum berubah)**

```bash
grep -n "lembaga_id" app/Domains/Akademik/Models/MataPelajaran.php
```
Expected: ADA di `$fillable`.

- [ ] **Step 3: Tulis test yang gagal dulu**

Tambahkan ke `tests/Unit/DashboardStatsServiceTest.php` (baca dulu isi file existing untuk konfirmasi pola `uses()`/import yang dipakai, sesuaikan kalau beda):

```php
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Domains\Sdm\Models\KuotaCutiConfig;
use App\Models\Karyawan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use App\Services\DashboardStatsService;

it('aggregates SDM attendance counts across the given lembaga ids for today', function () {
    $lembaga = Lembaga::factory()->create();
    $guru = \App\Models\Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    AttendanceRecord::create([
        'lembaga_id' => $lembaga->id, 'pegawai_type' => \App\Models\Guru::class, 'pegawai_id' => $guru->id,
        'tanggal' => now()->toDateString(), 'status' => 'hadir',
    ]);

    $service = new DashboardStatsService();
    $hasil = $service->statistikPresensiSdm([$lembaga->id]);

    expect($hasil['hadir'])->toBe(1);
    expect($hasil['izin'])->toBe(0);
});

it('computes rapor fill progress as zero when there is no active semester', function () {
    $kelas = Kelas::factory()->create();

    $service = new DashboardStatsService();
    $hasil = $service->statistikProgressRaporKelas($kelas);

    expect($hasil['persen'])->toBe(0.0);
    expect($hasil['total'])->toBe(0);
});

it('returns null sisa kuota cuti when no KuotaCutiConfig matches the karyawan', function () {
    $karyawan = Karyawan::factory()->create();

    $service = new DashboardStatsService();
    $hasil = $service->statistikSisaKuotaCuti($karyawan);

    expect($hasil)->toBeNull();
});

it('computes sisa kuota cuti as jatah minus approved days this year', function () {
    $lembaga = Lembaga::factory()->create();
    $jenis = JenisKaryawanMaster::factory()->create();
    $karyawan = Karyawan::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenis->id]);
    KuotaCutiConfig::create(['lembaga_id' => $lembaga->id, 'jenis_karyawan_id' => $jenis->id, 'jenis_ptk' => 'karyawan', 'jatah_hari_per_tahun' => 12]);

    $service = new DashboardStatsService();
    $hasil = $service->statistikSisaKuotaCuti($karyawan);

    expect($hasil['jatah'])->toBe(12);
    expect($hasil['sisa'])->toBe(12);
});

it('returns 6 months of yayasan growth trend labels and data', function () {
    Yayasan::factory()->create();

    $service = new DashboardStatsService();
    $hasil = $service->trenPertumbuhanYayasan();

    expect($hasil['labels'])->toHaveCount(6);
    expect($hasil['data'])->toHaveCount(6);
    expect(array_sum($hasil['data']))->toBeGreaterThanOrEqual(1);
});
```

**PENTING**: kalau `KuotaCutiConfig::create()` gagal karena field `jenis_ptk` wajib punya nilai enum tertentu (bukan string bebas), baca dulu migration `create_kuota_cuti_config_table` untuk tahu nilai valid, sesuaikan test.

- [ ] **Step 4: Jalankan test, konfirmasi gagal**

```bash
php artisan test tests/Unit/DashboardStatsServiceTest.php
```
Expected: FAIL (method belum ada).

- [ ] **Step 5: Tambah 4 method baru di `DashboardStatsService.php`**

Tambahkan import di bagian atas file:
```php
use App\Domains\Sdm\Models\AttendanceRecord;
use App\Domains\Sdm\Models\KuotaCutiConfig;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\Karyawan;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Yayasan;
```

Tambahkan 4 method PERSIS SEBELUM penutup class (setelah `tahunAjaranAktif()`):

```php
    public function statistikPresensiSdm(array $lembagaIds): array
    {
        $counts = AttendanceRecord::whereIn('lembaga_id', $lembagaIds)
            ->whereDate('tanggal', now()->toDateString())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'hadir' => (int) ($counts['hadir'] ?? 0),
            'izin' => (int) ($counts['izin'] ?? 0),
            'sakit' => (int) ($counts['sakit'] ?? 0),
            'alpa' => (int) ($counts['alpa'] ?? 0),
            'cuti' => (int) ($counts['cuti'] ?? 0),
        ];
    }

    public function statistikProgressRaporKelas(Kelas $kelas): array
    {
        $semester = Semester::where('lembaga_id', $kelas->lembaga_id)->where('status_aktif', true)->first();

        if (! $semester) {
            return ['persen' => 0.0, 'terisi' => 0, 'total' => 0];
        }

        $totalSiswa = Siswa::where('kelas_id', $kelas->id)->count();
        $totalKomponen = KomponenPenilaian::where('semester_id', $semester->id)
            ->whereHas('mataPelajaran', fn ($q) => $q->where('lembaga_id', $kelas->lembaga_id))
            ->count();

        $totalTerisi = NilaiSiswa::whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelas->id))
            ->whereHas('komponenPenilaian', fn ($q) => $q->where('semester_id', $semester->id))
            ->whereNotNull('nilai_angka')
            ->count();

        $totalSlot = $totalSiswa * $totalKomponen;

        return [
            'persen' => $totalSlot > 0 ? round($totalTerisi / $totalSlot * 100, 1) : 0.0,
            'terisi' => $totalTerisi,
            'total' => $totalSlot,
        ];
    }

    public function statistikSisaKuotaCuti(Karyawan $karyawan): ?array
    {
        $config = KuotaCutiConfig::where('jenis_karyawan_id', $karyawan->jenis_karyawan_id)
            ->where(fn ($q) => $q->where('lembaga_id', $karyawan->lembaga_id)->orWhere('yayasan_id', $karyawan->yayasan_id))
            ->first();

        if (! $config) {
            return null;
        }

        $terpakai = $karyawan->pengajuanIzinCuti()
            ->whereHas('approvalRequest', fn ($q) => $q->where('status', ApprovalStatus::Approved))
            ->whereYear('tanggal_mulai', now()->year)
            ->get()
            ->sum(fn ($p) => $p->tanggal_mulai->diffInDays($p->tanggal_selesai) + 1);

        return [
            'jatah' => $config->jatah_hari_per_tahun,
            'terpakai' => $terpakai,
            'sisa' => max(0, $config->jatah_hari_per_tahun - $terpakai),
        ];
    }

    public function trenPertumbuhanYayasan(): array
    {
        $mulai = now()->subMonths(5)->startOfMonth();

        $counts = Yayasan::where('created_at', '>=', $mulai)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as bulan, count(*) as total")
            ->groupBy('bulan')
            ->pluck('total', 'bulan');

        $labels = [];
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $bulan = now()->subMonths($i);
            $labels[] = $bulan->translatedFormat('M Y');
            $data[] = (int) ($counts[$bulan->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }
```

- [ ] **Step 6: Verifikasi syntax**

```bash
php -l app/Services/DashboardStatsService.php
```

- [ ] **Step 7: Jalankan test, konfirmasi lulus**

```bash
php artisan test tests/Unit/DashboardStatsServiceTest.php
```
Expected: semua PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/DashboardStatsService.php tests/Unit/DashboardStatsServiceTest.php
git commit -m "feat(dashboard): tambah method agregasi presensi SDM, progress rapor, kuota cuti, tren yayasan"
```

---

## Task 2: Chart Baru di `dashboard-charts.js`

**Files:**
- Modify: `resources/js/dashboard-charts.js`
- Modify: `resources/js/app.js`

**Interfaces:**
- Produces: `Alpine.data('trenTenantChart', ...)`, `Alpine.data('presensiBulananChart', ...)` — dipakai Task 3 (Platform) dan Task 6 (Karyawan).

- [ ] **Step 1: Baca ulang `dashboard-charts.js` dan bagian import di `app.js`**

```bash
cat resources/js/dashboard-charts.js
grep -n "dashboard-charts" resources/js/app.js
```

- [ ] **Step 2: Tambah 2 fungsi baru di `dashboard-charts.js`**

Tambahkan PERSIS SETELAH fungsi `perLembagaBarChart`:

```js
export function trenTenantChart(labels, data) {
    return {
        init() {
            new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{ label: 'Yayasan Baru', data, borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,0.1)', fill: true, tension: 0.3 }],
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
            });
        },
    };
}

export function presensiBulananChart(labels, hadir, izin, sakit, alpa) {
    return {
        init() {
            new Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Hadir', data: hadir, backgroundColor: '#22c55e' },
                        { label: 'Izin', data: izin, backgroundColor: '#3b82f6' },
                        { label: 'Sakit', data: sakit, backgroundColor: '#f59e0b' },
                        { label: 'Alpa', data: alpa, backgroundColor: '#ef4444' },
                    ],
                },
                options: { responsive: true, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } },
            });
        },
    };
}
```

- [ ] **Step 3: Daftarkan di `app.js`**

Baca dulu baris import+daftar existing:
```bash
grep -n "dashboard-charts\|Alpine.data('trenPendaftaranChart'" resources/js/app.js
```
Ganti baris import (baseline: `import { trenPendaftaranChart, donutTagihanChart, perLembagaBarChart } from './dashboard-charts';`) menjadi:
```js
import { trenPendaftaranChart, donutTagihanChart, perLembagaBarChart, trenTenantChart, presensiBulananChart } from './dashboard-charts';
```
Tambahkan PERSIS SETELAH baris `Alpine.data('perLembagaBarChart', perLembagaBarChart);`:
```js
Alpine.data('trenTenantChart', trenTenantChart);
Alpine.data('presensiBulananChart', presensiBulananChart);
```

- [ ] **Step 4: Build frontend**

```bash
npm run build
```
Expected: sukses tanpa error.

- [ ] **Step 5: Commit**

```bash
git add resources/js/dashboard-charts.js resources/js/app.js
git commit -m "feat(dashboard): tambah chart tren tenant & presensi bulanan (Chart.js, pola existing)"
```

---

## Task 3: Dashboard Platform — Tren Pertumbuhan Tenant & Health Check

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/views/admin/dashboard/platform.blade.php`
- Test: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: `DashboardStatsService::trenPertumbuhanYayasan()` (Task 1), `trenTenantChart` Alpine (Task 2).

- [ ] **Step 1: Baca ulang cabang `platform` di `DashboardController::index()` (baseline dari sub-project sebelumnya)**

```bash
grep -n "widestScopeLevel() === 'platform'" -A 25 app/Http/Controllers/Admin/DashboardController.php
```

- [ ] **Step 2: Tambah data tren + health check per yayasan**

Di dalam blok `if ($user->widestScopeLevel() === 'platform') { ... }`, ganti `$ringkasanPerYayasan = $yayasanList->map(...)` supaya juga menghitung TA aktif & akun nonaktif per yayasan:

```php
            $ringkasanPerYayasan = $yayasanList->map(function (Yayasan $yayasan) {
                $lembagaIds = Lembaga::where('yayasan_id', $yayasan->id)->pluck('id');

                return [
                    'yayasan' => $yayasan,
                    'lembaga' => $yayasan->lembaga_count,
                    'guru' => Guru::whereIn('lembaga_id', $lembagaIds)->count(),
                    'pengguna' => User::withoutGlobalScope(TenantScope::class)
                        ->where(fn ($q) => $q->whereIn('lembaga_id', $lembagaIds)->orWhere('yayasan_id', $yayasan->id))
                        ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))
                        ->count(),
                    'adaTahunAjaranAktif' => TahunAjaran::whereIn('lembaga_id', $lembagaIds)->where('status_aktif', true)->exists(),
                    'akunNonaktif' => User::withoutGlobalScope(TenantScope::class)
                        ->where(fn ($q) => $q->whereIn('lembaga_id', $lembagaIds)->orWhere('yayasan_id', $yayasan->id))
                        ->where('is_active', false)
                        ->count(),
                ];
            });

            return view('admin.dashboard.platform', [
                'ringkasanPerYayasan' => $ringkasanPerYayasan,
                'trenTenant' => $this->dashboardStats->trenPertumbuhanYayasan(),
                'stats' => [
                    'yayasan' => $yayasanList->count(),
                    'lembaga' => Lembaga::count(),
                    'guru' => Guru::count(),
                    'pengguna' => User::withoutGlobalScope(TenantScope::class)
                        ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'siswa'))
                        ->count(),
                ],
            ]);
```

- [ ] **Step 3: Perbarui `platform.blade.php`**

Baca dulu isi file existing (`cat resources/views/admin/dashboard/platform.blade.php`), lalu tambahkan chart PERSIS SETELAH blok stat card (`<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">...</div>`) dan SEBELUM `<x-panel>` tabel ringkasan:

```blade
        <x-panel class="p-6">
            <p class="mb-3 text-sm font-medium text-ink">Tren Pertumbuhan Yayasan (6 Bulan Terakhir)</p>
            <div x-data="trenTenantChart(@js($trenTenant['labels']), @js($trenTenant['data']))">
                <canvas x-ref="canvas" height="90"></canvas>
            </div>
        </x-panel>
```

Lalu ganti header tabel dan baris data untuk menambah 2 kolom baru:
```blade
                        <th class="px-6 py-3 font-display font-semibold">Pengguna</th>
```
Menjadi:
```blade
                        <th class="px-6 py-3 font-display font-semibold">Pengguna</th>
                        <th class="px-6 py-3 font-display font-semibold">TA Aktif?</th>
                        <th class="px-6 py-3 font-display font-semibold">Akun Nonaktif</th>
```
Dan:
```blade
                            <td class="px-6 py-3.5">{{ $ringkasan['pengguna'] }}</td>
                        </tr>
```
Menjadi:
```blade
                            <td class="px-6 py-3.5">{{ $ringkasan['pengguna'] }}</td>
                            <td class="px-6 py-3.5">
                                <x-badge tone="{{ $ringkasan['adaTahunAjaranAktif'] ? 'green' : 'amber' }}">{{ $ringkasan['adaTahunAjaranAktif'] ? 'Ya' : 'Tidak' }}</x-badge>
                            </td>
                            <td class="px-6 py-3.5">{{ $ringkasan['akunNonaktif'] }}</td>
                        </tr>
```
Dan `colspan="4"` pada baris kosong jadi `colspan="6"`.

- [ ] **Step 4: Tambah test**

Tambahkan ke `tests/Feature/DashboardTest.php` (setelah test platform yang sudah ada):

```php
it('shows the yayasan growth trend chart and health columns on the platform dashboard', function () {
    Role::firstOrCreate(['name' => 'platform_super_admin', 'guard_name' => 'web'], ['scope_level' => 'platform', 'is_protected' => true]);
    $yayasan = Yayasan::factory()->create(['nama' => 'Yayasan Sehat']);
    Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    $admin = User::factory()->create();
    $admin->assignRole('platform_super_admin');

    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('trenTenantChart(', false);
    $response->assertViewHas('ringkasanPerYayasan', function ($ringkasan) {
        return $ringkasan->first()['akunNonaktif'] === 0;
    });
});
```

- [ ] **Step 5: Verifikasi syntax & jalankan test**

```bash
php -l app/Http/Controllers/Admin/DashboardController.php
php artisan test tests/Feature/DashboardTest.php
```
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php resources/views/admin/dashboard/platform.blade.php tests/Feature/DashboardTest.php
git commit -m "feat(dashboard): tambah tren pertumbuhan yayasan & health check ke dashboard Platform"
```

---

## Task 4: Dashboard Yayasan — Presensi SDM & Kasus Eskalasi Lintas Lembaga

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/views/admin/dashboard/yayasan.blade.php`
- Test: `tests/Feature/Admin/DashboardYayasanTest.php`

**Interfaces:**
- Consumes: `DashboardStatsService::statistikPresensiSdm()` (Task 1), `StatusKasus::Eskalasi` (`App\Domains\Kasus\Enums\StatusKasus`).

- [ ] **Step 1: Baca ulang cabang `yayasan` (tanpa lembaga aktif) di `DashboardController::index()`**

```bash
grep -n "widestScopeLevel() === 'yayasan'" -A 45 app/Http/Controllers/Admin/DashboardController.php
```

- [ ] **Step 2: Tambah import `Kasus` (sudah ada) dan `StatusKasus`**

Tambahkan di bagian `use` paling atas file:
```php
use App\Domains\Kasus\Enums\StatusKasus;
```

- [ ] **Step 3: Tambah data presensi SDM + kasus eskalasi**

Di dalam blok `if ($lembagaAktifId !== null) { ... } else { ... }` (cabang yayasan tanpa lembaga aktif), SETELAH baris `$lembagaList = Lembaga::where('yayasan_id', $user->yayasan_id)->get();`, tambahkan:

```php
            $lembagaIds = $lembagaList->pluck('id')->all();
            $presensiSdmHariIni = $this->dashboardStats->statistikPresensiSdm($lembagaIds);
            $kasusEskalasiUnassigned = Kasus::withoutGlobalScope(TenantScope::class)
                ->whereIn('lembaga_id', $lembagaIds)
                ->where('status', StatusKasus::Eskalasi)
                ->whereNull('konselor_guru_id')
                ->whereNull('konselor_karyawan_id')
                ->count();
```

Lalu tambahkan 2 key baru ke array `return view('admin.dashboard.yayasan', [...])`:
```php
                'presensiSdmHariIni' => $presensiSdmHariIni,
                'kasusEskalasiUnassigned' => $kasusEskalasiUnassigned,
```

- [ ] **Step 4: Tambah section baru di `yayasan.blade.php`**

Baca dulu isi file (`cat resources/views/admin/dashboard/yayasan.blade.php`), lalu tambahkan PERSIS SETELAH blok `@if (isset($ringkasanPerLembaga)) ... @endif` (sebelum penutup `</div>` terakhir sebelum `</x-app-layout>`):

```blade
        @if (isset($presensiSdmHariIni))
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-panel class="p-6">
                    <p class="mb-3 text-sm font-medium text-ink">Kehadiran SDM Hari Ini (Semua Lembaga)</p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <x-stat-tile label="Hadir" :value="$presensiSdmHariIni['hadir']" icon="check_circle" />
                        <x-stat-tile label="Izin" :value="$presensiSdmHariIni['izin']" icon="hourglass_empty" />
                        <x-stat-tile label="Sakit" :value="$presensiSdmHariIni['sakit']" icon="local_hospital" />
                        <x-stat-tile label="Alpa" :value="$presensiSdmHariIni['alpa']" icon="cancel" />
                        <x-stat-tile label="Cuti" :value="$presensiSdmHariIni['cuti']" icon="beach_access" />
                    </div>
                </x-panel>
                <x-panel class="p-6">
                    <p class="mb-3 text-sm font-medium text-ink">Kasus Eskalasi Belum Ditangani</p>
                    <x-stat-tile label="Menunggu Konselor" :value="$kasusEskalasiUnassigned" icon="priority_high" hint="Lintas semua lembaga di yayasan ini" />
                </x-panel>
            </div>
        @endif
```

- [ ] **Step 5: Tambah test**

Tambahkan ke `tests/Feature/Admin/DashboardYayasanTest.php` (baca dulu helper `actingAs...()` yang sudah ada di file itu untuk konsistensi setup):

```php
it('shows SDM attendance summary and unassigned eskalasi count across every lembaga in the yayasan', function () {
    $manager = actingAsYayasanManagerForDashboardTest();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $manager->yayasan_id]);
    $guru = \App\Models\Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Sdm\Models\AttendanceRecord::create([
        'lembaga_id' => $lembaga->id, 'pegawai_type' => \App\Models\Guru::class, 'pegawai_id' => $guru->id,
        'tanggal' => now()->toDateString(), 'status' => 'hadir',
    ]);

    $response = $this->actingAs($manager)->get('/dashboard');

    $response->assertOk();
    $response->assertViewHas('presensiSdmHariIni', fn ($p) => $p['hadir'] === 1);
});
```

**PENTING**: nama helper `actingAsYayasanManagerForDashboardTest()` adalah TEBAKAN — baca dulu `tests/Feature/Admin/DashboardYayasanTest.php` untuk nama helper setup yang SEBENARNYA dipakai di file itu, sesuaikan pemanggilannya.

- [ ] **Step 6: Verifikasi syntax & jalankan test**

```bash
php -l app/Http/Controllers/Admin/DashboardController.php
php artisan test tests/Feature/Admin/DashboardYayasanTest.php
```
Expected: semua PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php resources/views/admin/dashboard/yayasan.blade.php tests/Feature/Admin/DashboardYayasanTest.php
git commit -m "feat(dashboard): tambah ringkasan presensi SDM & kasus eskalasi lintas lembaga ke dashboard Yayasan"
```

---

## Task 5: Dashboard Lembaga — Presensi SDM, Progress Rapor, Izin Cuti Pending

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/views/admin/dashboard/lembaga.blade.php`
- Test: `tests/Feature/Admin/DashboardLembagaTest.php`

**Interfaces:**
- Consumes: `DashboardStatsService::statistikPresensiSdm()`, `statistikProgressRaporKelas()` (Task 1).

- [ ] **Step 1: Baca ulang method `lembagaViewData()` di `DashboardController.php`**

```bash
grep -n "private function lembagaViewData" -A 45 app/Http/Controllers/Admin/DashboardController.php
```

- [ ] **Step 2: Tambah import `PengajuanIzinCuti`, `ApprovalStatus`, `Kelas`**

```php
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Kelas;
```

- [ ] **Step 3: Perluas `lembagaViewData()`**

Tambahkan PERSIS SETELAH baris `'keuanganStats' => null,` di dalam array `$data`:
```php
            'presensiSdmHariIni' => $this->dashboardStats->statistikPresensiSdm([$lembagaId]),
            'izinCutiPendingCount' => PengajuanIzinCuti::where('lembaga_id', $lembagaId)
                ->whereHas('approvalRequest', fn ($q) => $q->where('status', ApprovalStatus::Pending))
                ->count(),
            'progressRaporPerKelas' => null,
```

Tambahkan blok baru SETELAH blok `if ($user->can('kasus.triase')) { ... } else { ... }` (sebelum `return $data;`):
```php
        if ($user->can('komponen-penilaian.kelola')) {
            $data['progressRaporPerKelas'] = Kelas::where('lembaga_id', $lembagaId)->get()
                ->map(fn (Kelas $kelas) => [
                    'kelas' => $kelas,
                    'progress' => $this->dashboardStats->statistikProgressRaporKelas($kelas),
                ]);
        }
```

- [ ] **Step 4: Tambah section baru di `lembaga.blade.php`**

Baca dulu isi file (`cat resources/views/admin/dashboard/lembaga.blade.php`), tambahkan PERSIS SEBELUM penutup `</div>` terakhir sebelum `</x-app-layout>`:

```blade
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-panel class="p-6">
                <p class="mb-3 text-sm font-medium text-ink">Kehadiran SDM Hari Ini</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <x-stat-tile label="Hadir" :value="$presensiSdmHariIni['hadir']" icon="check_circle" />
                    <x-stat-tile label="Izin" :value="$presensiSdmHariIni['izin']" icon="hourglass_empty" />
                    <x-stat-tile label="Sakit" :value="$presensiSdmHariIni['sakit']" icon="local_hospital" />
                    <x-stat-tile label="Alpa" :value="$presensiSdmHariIni['alpa']" icon="cancel" />
                    <x-stat-tile label="Cuti" :value="$presensiSdmHariIni['cuti']" icon="beach_access" />
                </div>
            </x-panel>
            <x-panel class="p-6">
                <p class="mb-3 text-sm font-medium text-ink">Pengajuan Izin/Cuti Menunggu Persetujuan</p>
                <x-stat-tile label="Pending" :value="$izinCutiPendingCount" icon="pending_actions" />
            </x-panel>
        </div>

        @if ($progressRaporPerKelas !== null)
            <x-panel>
                <div class="border-b border-ink/10 px-6 py-4">
                    <h3 class="font-display font-semibold text-ink">Progress Pengumpulan Nilai per Kelas</h3>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink/10 bg-paper/60 text-left text-xs uppercase tracking-wide text-slate">
                            <th class="px-6 py-3 font-display font-semibold">Kelas</th>
                            <th class="px-6 py-3 font-display font-semibold">Terisi</th>
                            <th class="px-6 py-3 font-display font-semibold">Progress</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink/10">
                        @foreach ($progressRaporPerKelas as $item)
                            <tr>
                                <td class="px-6 py-3.5 font-display font-medium text-ink">{{ $item['kelas']->nama }}</td>
                                <td class="px-6 py-3.5">{{ $item['progress']['terisi'] }} / {{ $item['progress']['total'] }}</td>
                                <td class="px-6 py-3.5">{{ $item['progress']['persen'] }}%</td>
                            </tr>
                        @endforeach
                        @if ($progressRaporPerKelas->isEmpty())
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-slate">Belum ada kelas di lembaga ini.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </x-panel>
        @endif
```

- [ ] **Step 5: Tambah test**

Tambahkan ke `tests/Feature/Admin/DashboardLembagaTest.php` (baca dulu helper setup existing di file itu untuk konsistensi):

```php
it('shows the rapor fill progress table only for a user with komponen-penilaian.kelola permission', function () {
    // Sesuaikan setup actor & permission dengan pola helper yang sudah ada di file ini.
    // Buat 1 Kelas di lembaga actor, assert response berisi nama kelas itu kalau actor
    // punya permission 'komponen-penilaian.kelola', dan TIDAK berisi tabel progress
    // (assertDontSee 'Progress Pengumpulan Nilai per Kelas') kalau tidak punya permission itu.
})->todo('Lengkapi dengan pola helper actingAs yang sudah ada di file ini setelah dibaca langsung.');
```

**PENTING**: test di atas SENGAJA ditulis sebagai kerangka (`->todo()`) karena pola helper setup di `DashboardLembagaTest.php` belum dibaca penuh saat plan ini ditulis — WAJIB baca isi file itu dulu, lengkapi test sungguhan mengikuti pola yang sama (bukan menebak), lalu hapus `->todo()`.

- [ ] **Step 6: Verifikasi syntax & jalankan test**

```bash
php -l app/Http/Controllers/Admin/DashboardController.php
php artisan test tests/Feature/Admin/DashboardLembagaTest.php
```
Expected: semua PASS (termasuk test lama yang sudah ada, TIDAK regresi).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php resources/views/admin/dashboard/lembaga.blade.php tests/Feature/Admin/DashboardLembagaTest.php
git commit -m "feat(dashboard): tambah presensi SDM, progress rapor per kelas, izin cuti pending ke dashboard Lembaga"
```

---

## Task 6: Dashboard Karyawan — Riwayat Presensi 30 Hari, Sisa Kuota Cuti, Shift Mendatang

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/views/admin/dashboard/karyawan.blade.php`
- Test: `tests/Feature/KaryawanDashboardTest.php`

**Interfaces:**
- Consumes: `DashboardStatsService::statistikSisaKuotaCuti()` (Task 1), `presensiBulananChart` Alpine (Task 2).

- [ ] **Step 1: Baca ulang cabang `pegawai_yayasan`/`pegawai_lembaga` (baseline dari sub-project sebelumnya)**

```bash
grep -n "hasRole('pegawai_yayasan')" -A 25 app/Http/Controllers/Admin/DashboardController.php
```

- [ ] **Step 2: Tambah data riwayat presensi + kuota cuti + shift**

Ganti blok existing:
```php
            $izinCutiPending = $karyawanId === null
                ? 0
                : $karyawan->pengajuanIzinCuti()->withoutGlobalScope(TenantScope::class)
                    ->whereHas('approvalRequest', fn ($q) => $q->where('status', ApprovalStatus::Pending->value))
                    ->count();

            return view('admin.dashboard.karyawan', [
                'karyawan' => $karyawan,
                'presensiHariIni' => $presensiHariIni,
                'izinCutiPending' => $izinCutiPending,
                'kasusDitangani' => $kasusDitangani,
                'kasusDitanganiStats' => $this->kasusStatusCounts($kasusDitangani),
            ]);
```
Menjadi:
```php
            $izinCutiPending = $karyawanId === null
                ? 0
                : $karyawan->pengajuanIzinCuti()->withoutGlobalScope(TenantScope::class)
                    ->whereHas('approvalRequest', fn ($q) => $q->where('status', ApprovalStatus::Pending->value))
                    ->count();

            $riwayatPresensi30Hari = ['labels' => [], 'hadir' => [], 'izin' => [], 'sakit' => [], 'alpa' => []];
            $sisaKuotaCuti = null;
            $shiftMendatang = collect();

            if ($karyawan !== null) {
                $records = $karyawan->attendanceRecords()->withoutGlobalScope(TenantScope::class)
                    ->where('tanggal', '>=', now()->subDays(29)->toDateString())
                    ->get()
                    ->keyBy(fn ($r) => $r->tanggal->toDateString());

                for ($i = 29; $i >= 0; $i--) {
                    $tanggal = now()->subDays($i);
                    $record = $records->get($tanggal->toDateString());
                    $riwayatPresensi30Hari['labels'][] = $tanggal->translatedFormat('d M');
                    $riwayatPresensi30Hari['hadir'][] = $record?->status?->value === 'hadir' ? 1 : 0;
                    $riwayatPresensi30Hari['izin'][] = $record?->status?->value === 'izin' ? 1 : 0;
                    $riwayatPresensi30Hari['sakit'][] = $record?->status?->value === 'sakit' ? 1 : 0;
                    $riwayatPresensi30Hari['alpa'][] = $record?->status?->value === 'alpa' ? 1 : 0;
                }

                $sisaKuotaCuti = $this->dashboardStats->statistikSisaKuotaCuti($karyawan);

                $shiftMendatang = $karyawan->penugasanShift()->withoutGlobalScope(TenantScope::class)
                    ->where('tanggal_selesai', '>=', now()->toDateString())
                    ->with('jenisShift')
                    ->orderBy('tanggal_mulai')
                    ->limit(3)
                    ->get();
            }

            return view('admin.dashboard.karyawan', [
                'karyawan' => $karyawan,
                'presensiHariIni' => $presensiHariIni,
                'izinCutiPending' => $izinCutiPending,
                'riwayatPresensi30Hari' => $riwayatPresensi30Hari,
                'sisaKuotaCuti' => $sisaKuotaCuti,
                'shiftMendatang' => $shiftMendatang,
                'kasusDitangani' => $kasusDitangani,
                'kasusDitanganiStats' => $this->kasusStatusCounts($kasusDitangani),
            ]);
```

- [ ] **Step 3: Tambah section baru di `karyawan.blade.php`**

Baca dulu isi file (sudah pernah dibaca sub-project sebelumnya, konfirmasi ulang belum berubah), tambahkan PERSIS SETELAH blok `@if ($karyawan) ... @endif` yang sudah ada:

```blade
        @if ($karyawan)
            <x-panel class="p-6">
                <p class="mb-3 text-sm font-medium text-ink">Riwayat Presensi 30 Hari Terakhir</p>
                <div x-data="presensiBulananChart(
                    @js($riwayatPresensi30Hari['labels']),
                    @js($riwayatPresensi30Hari['hadir']),
                    @js($riwayatPresensi30Hari['izin']),
                    @js($riwayatPresensi30Hari['sakit']),
                    @js($riwayatPresensi30Hari['alpa'])
                )">
                    <canvas x-ref="canvas" height="80"></canvas>
                </div>
            </x-panel>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-panel class="p-6">
                    <p class="mb-2 text-sm font-medium text-ink">Kuota Cuti Tahun Ini</p>
                    @if ($sisaKuotaCuti)
                        <x-stat-tile label="Sisa" :value="$sisaKuotaCuti['sisa'] . ' hari'" :hint="'Terpakai ' . $sisaKuotaCuti['terpakai'] . ' dari ' . $sisaKuotaCuti['jatah'] . ' hari'" icon="event_available" />
                    @else
                        <p class="text-sm text-slate">Belum ada konfigurasi kuota cuti untuk kategori kepegawaian Anda.</p>
                    @endif
                </x-panel>
                <x-panel class="p-6">
                    <p class="mb-2 text-sm font-medium text-ink">Shift Mendatang</p>
                    @if ($shiftMendatang->isEmpty())
                        <p class="text-sm text-slate">Tidak ada shift terjadwal.</p>
                    @else
                        <ul class="divide-y divide-ink/10">
                            @foreach ($shiftMendatang as $shift)
                                <li class="flex items-center justify-between py-2 text-sm">
                                    <span class="text-ink">{{ $shift->jenisShift?->nama ?? 'Shift' }}</span>
                                    <span class="text-slate">{{ $shift->tanggal_mulai->translatedFormat('d M Y') }} – {{ $shift->tanggal_selesai->translatedFormat('d M Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-panel>
            </div>
        @endif
```

- [ ] **Step 4: Tambah test**

Tambahkan ke `tests/Feature/KaryawanDashboardTest.php`:

```php
it('shows a null kuota cuti message when no KuotaCutiConfig matches the karyawan category', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $jenis = JenisKaryawanMaster::factory()->create();

    $karyawan = app(AkunKaryawanGenerator::class)->buat('Karyawan Tanpa Kuota', '3201234567893333', $yayasan->id, $lembaga->id, $jenis->id);
    $karyawan->user()->update(['must_change_password' => false, 'email_verified_at' => now()]);

    $response = $this->actingAs($karyawan->user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertViewHas('sisaKuotaCuti', null);
});
```

- [ ] **Step 5: Verifikasi syntax & jalankan test**

```bash
php -l app/Http/Controllers/Admin/DashboardController.php
php artisan test tests/Feature/KaryawanDashboardTest.php
```
Expected: semua PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php resources/views/admin/dashboard/karyawan.blade.php tests/Feature/KaryawanDashboardTest.php
git commit -m "feat(dashboard): tambah riwayat presensi 30 hari, sisa kuota cuti, shift mendatang ke dashboard Karyawan"
```

---

## Task 7: Dashboard Guru — Jadwal Hari Ini, Wali Kelas, Presensi Diri, RPP, Rekap Presensi Siswa

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/views/admin/dashboard/guru.blade.php`
- Test: `tests/Feature/DashboardKasusTest.php` atau file baru `tests/Feature/DashboardGuruTest.php`

**Interfaces:**
- Consumes: `App\Enums\Hari`, `App\Domains\Akademik\Models\Rpp` + `StatusRpp`, `App\Models\JadwalPelajaran`, `App\Domains\Akademik\Models\SesiPembelajaran`, `App\Domains\Akademik\Models\Presensi`.

- [ ] **Step 1: Baca ulang cabang `guru` di `DashboardController::index()`**

```bash
grep -n "hasRole('guru')" -A 15 app/Http/Controllers/Admin/DashboardController.php
```

- [ ] **Step 2: Tambah import**

```php
use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\Rpp;
use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Enums\Hari;
```

- [ ] **Step 3: Perluas blok `guru`**

Ganti:
```php
        if ($user->hasRole('guru')) {
            $kasusDiajukan = $user->guru === null
                ? collect()
                : Kasus::with('siswa')->where('diajukan_oleh_guru_id', $user->guru->id)->latest()->get();
            $kasusDitangani = $user->guru === null
                ? collect()
                : Kasus::with('siswa')->where('konselor_guru_id', $user->guru->id)->latest()->get();

            return view('admin.dashboard.guru', [
                'jabatanTambahan' => $user->guru?->jabatanTambahan ?? collect(),
                'kasusDiajukan' => $kasusDiajukan,
                'kasusDitangani' => $kasusDitangani,
                'kasusDiajukanStats' => $this->kasusStatusCounts($kasusDiajukan),
                'kasusDitanganiStats' => $this->kasusStatusCounts($kasusDitangani),
            ]);
        }
```
Menjadi:
```php
        if ($user->hasRole('guru')) {
            $guru = $user->guru;
            $kasusDiajukan = $guru === null
                ? collect()
                : Kasus::with('siswa')->where('diajukan_oleh_guru_id', $guru->id)->latest()->get();
            $kasusDitangani = $guru === null
                ? collect()
                : Kasus::with('siswa')->where('konselor_guru_id', $guru->id)->latest()->get();

            $jadwalHariIni = collect();
            $kelasWali = null;
            $progressRaporWali = null;
            $presensiDiriHariIni = null;
            $rppPerluTindakan = 0;
            $rekapPresensiSiswaHariIni = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'terlambat' => 0];

            if ($guru !== null) {
                $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);

                $jadwalHariIni = JadwalPelajaran::where('guru_id', $guru->id)
                    ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hariIni))
                    ->with(['jamPelajaran', 'mataPelajaran', 'kelas'])
                    ->get()
                    ->sortBy(fn ($j) => $j->jamPelajaran->urutan);

                $kelasWali = Kelas::where('wali_kelas_guru_id', $guru->id)->first();
                if ($kelasWali !== null) {
                    $progressRaporWali = $this->dashboardStats->statistikProgressRaporKelas($kelasWali);
                }

                $presensiDiriHariIni = $guru->attendanceRecords()->withoutGlobalScope(TenantScope::class)
                    ->where('tanggal', now()->toDateString())->first();

                $rppPerluTindakan = Rpp::where('guru_id', $guru->id)
                    ->whereIn('status', [StatusRpp::Draft, StatusRpp::PerluRevisi])
                    ->count();

                $sesiHariIni = SesiPembelajaran::where('guru_id', $guru->id)
                    ->whereDate('tanggal', now()->toDateString())
                    ->pluck('id');

                if ($sesiHariIni->isNotEmpty()) {
                    $counts = Presensi::whereIn('sesi_pembelajaran_id', $sesiHariIni)
                        ->selectRaw('status, count(*) as total')
                        ->groupBy('status')
                        ->pluck('total', 'status');

                    $rekapPresensiSiswaHariIni = [
                        'hadir' => (int) ($counts['hadir'] ?? 0),
                        'izin' => (int) ($counts['izin'] ?? 0),
                        'sakit' => (int) ($counts['sakit'] ?? 0),
                        'alpa' => (int) ($counts['alpa'] ?? 0),
                        'terlambat' => (int) ($counts['terlambat'] ?? 0),
                    ];
                }
            }

            return view('admin.dashboard.guru', [
                'jabatanTambahan' => $guru?->jabatanTambahan ?? collect(),
                'jadwalHariIni' => $jadwalHariIni,
                'kelasWali' => $kelasWali,
                'progressRaporWali' => $progressRaporWali,
                'presensiDiriHariIni' => $presensiDiriHariIni,
                'rppPerluTindakan' => $rppPerluTindakan,
                'rekapPresensiSiswaHariIni' => $rekapPresensiSiswaHariIni,
                'kasusDiajukan' => $kasusDiajukan,
                'kasusDitangani' => $kasusDitangani,
                'kasusDiajukanStats' => $this->kasusStatusCounts($kasusDiajukan),
                'kasusDitanganiStats' => $this->kasusStatusCounts($kasusDitangani),
            ]);
        }
```

- [ ] **Step 4: Tambah section baru di `guru.blade.php`**

Baca dulu isi file existing, tambahkan PERSIS SETELAH blok `@if ($jabatanTambahan->isNotEmpty()) ... @endif`:

```blade
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-tile label="Jam Mengajar Hari Ini" :value="$jadwalHariIni->count()" icon="school" />
            <x-stat-tile label="Wali Kelas" :value="$kelasWali?->nama ?? 'Bukan Wali Kelas'" icon="groups" />
            <x-stat-tile label="Presensi Diri" :value="$presensiDiriHariIni?->status?->label() ?? 'Belum Absen'" icon="badge" />
            <x-stat-tile label="RPP Perlu Tindakan" :value="$rppPerluTindakan" icon="assignment_late" />
        </div>

        @if ($jadwalHariIni->isNotEmpty())
            <x-panel>
                <div class="border-b border-ink/10 px-6 py-4">
                    <h3 class="font-display font-semibold text-ink">Jadwal Mengajar Hari Ini</h3>
                </div>
                <ul class="divide-y divide-ink/10">
                    @foreach ($jadwalHariIni as $jadwal)
                        <li class="flex items-center justify-between px-6 py-3 text-sm">
                            <div>
                                <p class="font-semibold text-ink">{{ $jadwal->mataPelajaran->nama }} &middot; {{ $jadwal->kelas->nama }}</p>
                                <p class="text-xs text-slate">{{ $jadwal->jamPelajaran->jam_mulai }} - {{ $jadwal->jamPelajaran->jam_selesai }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-panel>
        @endif

        @if ($kelasWali && $progressRaporWali)
            <x-panel class="p-6">
                <p class="mb-2 text-sm font-medium text-ink">Progress Rapor Kelas Binaan ({{ $kelasWali->nama }})</p>
                <x-stat-tile label="Terisi" :value="$progressRaporWali['persen'] . '%'" :hint="$progressRaporWali['terisi'] . ' dari ' . $progressRaporWali['total'] . ' slot nilai'" icon="fact_check" />
            </x-panel>
        @endif

        @if ($jadwalHariIni->isNotEmpty())
            <x-panel class="p-6">
                <p class="mb-3 text-sm font-medium text-ink">Rekap Presensi Siswa di Kelas yang Diajar Hari Ini</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <x-stat-tile label="Hadir" :value="$rekapPresensiSiswaHariIni['hadir']" icon="check_circle" />
                    <x-stat-tile label="Izin" :value="$rekapPresensiSiswaHariIni['izin']" icon="hourglass_empty" />
                    <x-stat-tile label="Sakit" :value="$rekapPresensiSiswaHariIni['sakit']" icon="local_hospital" />
                    <x-stat-tile label="Alpa" :value="$rekapPresensiSiswaHariIni['alpa']" icon="cancel" />
                    <x-stat-tile label="Terlambat" :value="$rekapPresensiSiswaHariIni['terlambat']" icon="schedule" />
                </div>
            </x-panel>
        @endif
```

- [ ] **Step 5: Tulis test baru**

Buat file `tests/Feature/Admin/DashboardGuruTest.php`:

```php
<?php

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Enums\Hari;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;

it('shows today\'s teaching schedule for a guru based on the JamPelajaran hari field', function () {
    Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('guru');
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $user->id]);

    $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
    $jamPelajaran = JamPelajaran::factory()->create(['hari' => $hariIni, 'urutan' => 1]);

    JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jamPelajaran->id, 'mata_pelajaran_id' => $mapel->id,
        'guru_id' => $guru->id, 'lembaga_id' => $lembaga->id,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Jadwal Mengajar Hari Ini');
    $response->assertSee($kelas->nama);
});
```

**PENTING**: `JamPelajaran::factory()` mungkin butuh field wajib lain (`pola_jam_id`) — baca dulu `database/factories/*JamPelajaranFactory.php` (kalau ada) sebelum menjalankan test ini, sesuaikan kalau field wajib tidak ter-generate otomatis.

- [ ] **Step 6: Verifikasi syntax & jalankan test**

```bash
php -l app/Http/Controllers/Admin/DashboardController.php
php artisan test tests/Feature/Admin/DashboardGuruTest.php tests/Feature/DashboardKasusTest.php
```
Expected: semua PASS (termasuk test lama guru yang sudah ada di `DashboardKasusTest.php`/`DashboardTest.php`).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php resources/views/admin/dashboard/guru.blade.php tests/Feature/Admin/DashboardGuruTest.php
git commit -m "feat(dashboard): tambah jadwal mengajar, status wali kelas, RPP, rekap presensi siswa ke dashboard Guru"
```

---

## Task 8: Dashboard Orang Tua — Tagihan, Nilai, Jadwal, Riwayat Izin/Sakit Anak

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/views/admin/dashboard/orang-tua.blade.php`
- Test: `tests/Feature/Admin/DashboardOrangTuaAkademikTest.php` (baru)

**Interfaces:**
- Consumes: `App\Domains\Keuangan\Models\Tagihan`, `App\Domains\Akademik\Models\NilaiSiswa`, `App\Domains\Akademik\Models\Presensi`, `App\Models\JadwalPelajaran`, `App\Enums\Hari`.

- [ ] **Step 1: Baca ulang cabang `orang_tua` di `DashboardController::index()`**

```bash
grep -n "hasRole('orang_tua')" -A 25 app/Http/Controllers/Admin/DashboardController.php
```

- [ ] **Step 2: Tambah import**

```php
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Siswa;
```

- [ ] **Step 3: Perluas blok `orang_tua`**

Tambahkan SETELAH baris `$kontakUtamaKasusIds = ...->pluck('id')->all();` (masih di dalam `if ($orangTua !== null) { ... }`):

```php
            $siswaIds = [];
            $tagihanBelumLunas = collect();
            $nilaiTerbaru = collect();
            $jadwalAnakHariIni = collect();
            $riwayatIzinSakit = collect();

            if ($orangTua !== null) {
                $siswaIds = $orangTua->siswa()->pluck('siswa.id')->all();

                $tagihanBelumLunas = Tagihan::where('tagihable_type', Siswa::class)
                    ->whereIn('tagihable_id', $siswaIds)
                    ->whereIn('status', ['belum_bayar', 'dicicil'])
                    ->orderBy('jatuh_tempo')
                    ->get();

                $nilaiTerbaru = NilaiSiswa::whereIn('siswa_id', $siswaIds)
                    ->whereNotNull('nilai_angka')
                    ->with(['komponenPenilaian.mataPelajaran', 'asesmen.mataPelajaran', 'siswa'])
                    ->latest('id')
                    ->limit(5)
                    ->get();

                $hariIni = \App\Enums\Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
                $kelasIds = Siswa::whereIn('id', $siswaIds)->pluck('kelas_id')->filter();
                $jadwalAnakHariIni = JadwalPelajaran::whereIn('kelas_id', $kelasIds)
                    ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hariIni))
                    ->with(['jamPelajaran', 'mataPelajaran', 'kelas'])
                    ->get()
                    ->sortBy(fn ($j) => $j->jamPelajaran->urutan);

                $riwayatIzinSakit = Presensi::whereIn('siswa_id', $siswaIds)
                    ->whereIn('status', ['izin', 'sakit'])
                    ->with('siswa')
                    ->latest('id')
                    ->limit(5)
                    ->get();
            }
```

Tambahkan 4 key baru ke `return view('admin.dashboard.orang-tua', [...])`:
```php
                'tagihanBelumLunas' => $tagihanBelumLunas,
                'nilaiTerbaru' => $nilaiTerbaru,
                'jadwalAnakHariIni' => $jadwalAnakHariIni,
                'riwayatIzinSakit' => $riwayatIzinSakit,
```

- [ ] **Step 4: Tambah import `Presensi` dan `JadwalPelajaran`**

```php
use App\Domains\Akademik\Models\Presensi;
use App\Models\JadwalPelajaran;
```

- [ ] **Step 5: Tambah section baru di `orang-tua.blade.php`**

Baca dulu isi file existing, tambahkan PERSIS SEBELUM blok `<x-panel>` "Kasus Pendampingan" yang sudah ada:

```blade
        @if ($tagihanBelumLunas->isNotEmpty())
            <x-panel class="p-6">
                <p class="mb-3 text-sm font-medium text-ink">Tagihan Belum Lunas</p>
                <ul class="divide-y divide-ink/10">
                    @foreach ($tagihanBelumLunas as $tagihan)
                        <li class="flex items-center justify-between py-2.5 text-sm">
                            <span class="text-ink">Rp {{ number_format($tagihan->net_amount ?? $tagihan->total_tagihan, 0, ',', '.') }}</span>
                            <span class="text-slate">Jatuh tempo {{ $tagihan->jatuh_tempo?->translatedFormat('d M Y') ?? '-' }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-panel>
        @endif

        @if ($nilaiTerbaru->isNotEmpty())
            <x-panel class="p-6">
                <p class="mb-3 text-sm font-medium text-ink">Nilai Terbaru</p>
                <ul class="divide-y divide-ink/10">
                    @foreach ($nilaiTerbaru as $nilai)
                        <li class="flex items-center justify-between py-2.5 text-sm">
                            <span class="text-ink">{{ $nilai->siswa->nama_lengkap }} &middot; {{ $nilai->komponenPenilaian?->mataPelajaran?->nama ?? $nilai->asesmen?->mataPelajaran?->nama ?? '-' }}</span>
                            <x-badge tone="brass">{{ $nilai->nilai_angka }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            </x-panel>
        @endif

        @if ($jadwalAnakHariIni->isNotEmpty())
            <x-panel class="p-6">
                <p class="mb-3 text-sm font-medium text-ink">Jadwal Pelajaran Hari Ini</p>
                <ul class="divide-y divide-ink/10">
                    @foreach ($jadwalAnakHariIni as $jadwal)
                        <li class="flex items-center justify-between py-2.5 text-sm">
                            <span class="text-ink">{{ $jadwal->mataPelajaran->nama }} &middot; {{ $jadwal->kelas->nama }}</span>
                            <span class="text-slate">{{ $jadwal->jamPelajaran->jam_mulai }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-panel>
        @endif

        @if ($riwayatIzinSakit->isNotEmpty())
            <x-panel class="p-6">
                <p class="mb-3 text-sm font-medium text-ink">Riwayat Izin / Sakit</p>
                <ul class="divide-y divide-ink/10">
                    @foreach ($riwayatIzinSakit as $presensi)
                        <li class="flex items-center justify-between py-2.5 text-sm">
                            <span class="text-ink">{{ $presensi->siswa->nama_lengkap }}</span>
                            <x-badge tone="amber">{{ $presensi->status->label() }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            </x-panel>
        @endif
```

- [ ] **Step 6: Tulis test baru**

Buat file `tests/Feature/Admin/DashboardOrangTuaAkademikTest.php`:

```php
<?php

use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\Semester as AkademikSemester;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;

it('shows an orang tua the latest grade recorded for their linked child', function () {
    Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'nama_lengkap' => 'Anak Uji Coba']);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = \App\Models\Semester::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'lembaga_id' => $lembaga->id]);

    NilaiSiswa::create([
        'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'lembaga_id' => $lembaga->id, 'nilai_angka' => 88,
    ]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null, 'name' => 'Ortu Nilai']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::factory()->create(['user_id' => $orangTuaUser->id]);
    $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $response = $this->actingAs($orangTuaUser)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Anak Uji Coba');
    $response->assertSee('88');
});
```

**PENTING**: `KomponenPenilaian::factory()`/`NilaiSiswa` field wajib lain (mis. `asesmen_id` nullable atau tidak) — baca dulu migration tabel `nilai_siswa` dan factory `KomponenPenilaianFactory` sebelum menjalankan test ini; kalau `komponen_penilaian_id` tanpa `asesmen_id` ternyata melanggar constraint DB, sesuaikan (isi `asesmen_id` juga via factory `Asesmen`).

- [ ] **Step 7: Verifikasi syntax & jalankan test**

```bash
php -l app/Http/Controllers/Admin/DashboardController.php
php artisan test tests/Feature/Admin/DashboardOrangTuaAkademikTest.php
```
Expected: semua PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php resources/views/admin/dashboard/orang-tua.blade.php tests/Feature/Admin/DashboardOrangTuaAkademikTest.php
git commit -m "feat(dashboard): tambah tagihan, nilai terbaru, jadwal, riwayat izin/sakit anak ke dashboard Orang Tua"
```

---

## Task 9: Dashboard Siswa — Bangun dari Nol

**Files:**
- Modify: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `resources/views/admin/dashboard/siswa.blade.php`
- Test: `tests/Feature/Admin/DashboardSiswaTest.php` (baru)

**Interfaces:**
- Consumes: sama seperti Task 8 (query serupa, scoped ke 1 siswa).

- [ ] **Step 1: Baca ulang cabang `siswa` di `DashboardController::index()` (saat ini return view kosong tanpa data)**

```bash
grep -n "hasRole('siswa')" -A 3 app/Http/Controllers/Admin/DashboardController.php
```

- [ ] **Step 2: Ganti blok `siswa`**

Ganti:
```php
        if ($user->hasRole('siswa')) {
            return view('admin.dashboard.siswa');
        }
```
Menjadi:
```php
        if ($user->hasRole('siswa')) {
            $siswa = $user->siswa;

            $jadwalHariIni = collect();
            $presensiBulanIni = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'terlambat' => 0];
            $nilaiTerbaru = collect();
            $tagihanBelumLunas = collect();

            if ($siswa !== null) {
                $hariIni = Hari::fromCarbonDayOfWeek(now()->dayOfWeek);
                $jadwalHariIni = JadwalPelajaran::where('kelas_id', $siswa->kelas_id)
                    ->whereHas('jamPelajaran', fn ($q) => $q->where('hari', $hariIni))
                    ->with(['jamPelajaran', 'mataPelajaran'])
                    ->get()
                    ->sortBy(fn ($j) => $j->jamPelajaran->urutan);

                $counts = Presensi::where('siswa_id', $siswa->id)
                    ->whereHas('sesiPembelajaran', fn ($q) => $q->whereMonth('tanggal', now()->month)->whereYear('tanggal', now()->year))
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status');

                $presensiBulanIni = [
                    'hadir' => (int) ($counts['hadir'] ?? 0),
                    'izin' => (int) ($counts['izin'] ?? 0),
                    'sakit' => (int) ($counts['sakit'] ?? 0),
                    'alpa' => (int) ($counts['alpa'] ?? 0),
                    'terlambat' => (int) ($counts['terlambat'] ?? 0),
                ];

                $nilaiTerbaru = NilaiSiswa::where('siswa_id', $siswa->id)
                    ->whereNotNull('nilai_angka')
                    ->with(['komponenPenilaian.mataPelajaran', 'asesmen.mataPelajaran'])
                    ->latest('id')
                    ->limit(5)
                    ->get();

                $tagihanBelumLunas = Tagihan::where('tagihable_type', Siswa::class)
                    ->where('tagihable_id', $siswa->id)
                    ->whereIn('status', ['belum_bayar', 'dicicil'])
                    ->orderBy('jatuh_tempo')
                    ->get();
            }

            return view('admin.dashboard.siswa', [
                'siswa' => $siswa,
                'jadwalHariIni' => $jadwalHariIni,
                'presensiBulanIni' => $presensiBulanIni,
                'nilaiTerbaru' => $nilaiTerbaru,
                'tagihanBelumLunas' => $tagihanBelumLunas,
            ]);
        }
```

- [ ] **Step 3: Bangun `siswa.blade.php` dari nol**

Ganti isi file `resources/views/admin/dashboard/siswa.blade.php` menjadi:

```blade
<x-app-layout>
    <x-slot name="header">
        <p class="font-display text-[11px] font-semibold uppercase tracking-[0.16em] text-brass">Siswa &middot; Ringkasan</p>
        <h2 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard Siswa</h2>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8">
        <x-hero-banner
            eyebrow="Portal Siswa"
            :title="'Halo, ' . Auth::user()->name . '!'"
            :subtitle="$siswa ? ('Kelas ' . ($siswa->kelas?->nama ?? '-') . ' &middot; NIS ' . $siswa->nis) : 'Profil siswa Anda belum tertaut. Hubungi admin sekolah.'"
        />

        @if ($siswa)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-stat-tile label="Jadwal Hari Ini" :value="$jadwalHariIni->count()" icon="calendar_today" />
                <x-stat-tile label="Hadir Bulan Ini" :value="$presensiBulanIni['hadir']" icon="check_circle" />
                <x-stat-tile label="Izin/Sakit Bulan Ini" :value="$presensiBulanIni['izin'] + $presensiBulanIni['sakit']" icon="hourglass_empty" />
                <x-stat-tile label="Tagihan Belum Lunas" :value="$tagihanBelumLunas->count()" icon="payments" />
            </div>

            @if ($jadwalHariIni->isNotEmpty())
                <x-panel>
                    <div class="border-b border-ink/10 px-6 py-4">
                        <h3 class="font-display font-semibold text-ink">Jadwal Pelajaran Hari Ini</h3>
                    </div>
                    <ul class="divide-y divide-ink/10">
                        @foreach ($jadwalHariIni as $jadwal)
                            <li class="flex items-center justify-between px-6 py-3 text-sm">
                                <span class="text-ink">{{ $jadwal->mataPelajaran->nama }}</span>
                                <span class="text-slate">{{ $jadwal->jamPelajaran->jam_mulai }} - {{ $jadwal->jamPelajaran->jam_selesai }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-panel>
            @endif

            @if ($nilaiTerbaru->isNotEmpty())
                <x-panel class="p-6">
                    <p class="mb-3 text-sm font-medium text-ink">Nilai Terbaru</p>
                    <ul class="divide-y divide-ink/10">
                        @foreach ($nilaiTerbaru as $nilai)
                            <li class="flex items-center justify-between py-2.5 text-sm">
                                <span class="text-ink">{{ $nilai->komponenPenilaian?->mataPelajaran?->nama ?? $nilai->asesmen?->mataPelajaran?->nama ?? '-' }}</span>
                                <x-badge tone="brass">{{ $nilai->nilai_angka }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-panel>
            @endif

            @if ($tagihanBelumLunas->isNotEmpty())
                <x-panel class="p-6">
                    <p class="mb-3 text-sm font-medium text-ink">Tagihan Belum Lunas</p>
                    <ul class="divide-y divide-ink/10">
                        @foreach ($tagihanBelumLunas as $tagihan)
                            <li class="flex items-center justify-between py-2.5 text-sm">
                                <span class="text-ink">Rp {{ number_format($tagihan->net_amount ?? $tagihan->total_tagihan, 0, ',', '.') }}</span>
                                <span class="text-slate">Jatuh tempo {{ $tagihan->jatuh_tempo?->translatedFormat('d M Y') ?? '-' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-panel>
            @endif
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 4: Tambah import `NilaiSiswa`, `Presensi`, `Tagihan`, `JadwalPelajaran`, `Hari`, `Siswa` (kalau belum ada dari task sebelumnya)**

```bash
grep -n "^use App" app/Http/Controllers/Admin/DashboardController.php
```
Pastikan `App\Domains\Akademik\Models\NilaiSiswa`, `App\Domains\Akademik\Models\Presensi`, `App\Domains\Keuangan\Models\Tagihan`, `App\Models\JadwalPelajaran`, `App\Enums\Hari`, `App\Models\Siswa` semua sudah di-import (kemungkinan besar sudah dari Task 7-8, tinggal cek).

- [ ] **Step 5: Tulis test baru**

Buat file `tests/Feature/Admin/DashboardSiswaTest.php`:

```php
<?php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;

it('shows a siswa their own kelas name and NIS instead of the old empty stub', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

    $lembaga = Lembaga::factory()->create();
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => 'Kelas 5A']);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('siswa');
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'user_id' => $user->id, 'nis' => '2026999']);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Kelas 5A');
    $response->assertSee('2026999');
    $response->assertDontSee('Portal siswa (nilai, presensi, jadwal) belum tersedia');
});

it('shows an empty state message when a siswa account has no linked Siswa profile yet', function () {
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $user = User::factory()->create();
    $user->assignRole('siswa');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertSee('Profil siswa Anda belum tertaut');
});
```

- [ ] **Step 6: Verifikasi syntax & jalankan test**

```bash
php -l app/Http/Controllers/Admin/DashboardController.php
php artisan test tests/Feature/Admin/DashboardSiswaTest.php tests/Feature/DashboardTest.php
```
Expected: semua PASS (termasuk test lama "shows the siswa placeholder dashboard..." di `DashboardTest.php` — kalau test itu assert `assertSee('Dashboard Siswa')` doang, tetap PASS karena header masih sama; kalau assert teks stub lama, PERBAIKI test itu karena stub-nya memang sengaja dihapus).

- [ ] **Step 7: Update test lama yang mengasumsikan stub kosong**

Baca `tests/Feature/DashboardTest.php`, cari test `'shows the siswa placeholder dashboard to a user with only the siswa role'` — kalau assertion-nya cuma `assertSee('Dashboard Siswa')` (header, bukan isi stub), TIDAK perlu diubah. Kalau ada assertion lain yang bergantung ke teks stub lama, sesuaikan ke perilaku baru.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/DashboardController.php resources/views/admin/dashboard/siswa.blade.php tests/Feature/Admin/DashboardSiswaTest.php
git commit -m "feat(dashboard): bangun dashboard Siswa dari nol - jadwal, presensi, nilai, tagihan"
```

---

## Task 10: Verifikasi Akhir & Handoff Log

**Files:**
- Create: `.agents/logs/2026-08-25-redesain-dashboard-multi-role.md`

- [ ] **Step 1: Grep verifikasi tidak ada komponen/token baru yang tidak sesuai constraint**

```bash
ls resources/views/admin/dashboard/partials/ 2>/dev/null
```
Expected: direktori TIDAK ADA (tidak ada partial baru dibuat, sesuai Global Constraints).

```bash
grep -rn "bg-slate-50" resources/views/admin/dashboard/*.blade.php
```
Expected: KOSONG (token visual baru tidak dipakai).

- [ ] **Step 2: Jalankan seluruh test yang disentuh plan ini sekaligus**

```bash
php artisan test tests/Unit/DashboardStatsServiceTest.php tests/Feature/DashboardTest.php tests/Feature/KaryawanDashboardTest.php tests/Feature/Admin/DashboardYayasanTest.php tests/Feature/Admin/DashboardLembagaTest.php tests/Feature/Admin/DashboardGuruTest.php tests/Feature/Admin/DashboardOrangTuaAkademikTest.php tests/Feature/Admin/DashboardSiswaTest.php tests/Feature/DashboardKasusTest.php
```
Expected: 0 failed. Catat angka pasti.

- [ ] **Step 3: Minta izin user untuk full test suite**

Tanya ke user: "Task 1-9 selesai, test yang disentuh plan ini hijau, grep verifikasi kosong (tidak ada komponen/token baru yang melanggar constraint). Boleh saya jalankan full test suite untuk verifikasi akhir?" — TUNGGU jawaban eksplisit.

- [ ] **Step 4: Jalankan full suite SOLO**

```bash
php artisan test
```
Catat angka PASTI passed/failed/duration.

- [ ] **Step 5: Tulis handoff log**

Buat `.agents/logs/2026-08-25-redesain-dashboard-multi-role.md` (Bahasa Indonesia): ringkasan Task 1-9 dengan commit hash, hasil grep Step 1 (kosong), hasil test Step 2 dan Step 4 (angka pasti, jangan dicampur), daftar keputusan penting (reuse komponen existing bukan bikin baru, non-goals yang di-skip sesuai spec §4 seperti tren keuangan bulanan konsolidasi & jadwal semua kelas, dan kalau ada penyesuaian dari `PENTING` note manapun di plan ini yang ternyata perlu diverifikasi ulang saat eksekusi — catat hasilnya).

- [ ] **Step 6: Commit**

```bash
git add .agents/logs/2026-08-25-redesain-dashboard-multi-role.md
git commit -m "docs(dashboard): handoff log penyempurnaan data 7 dashboard multi-role"
```
