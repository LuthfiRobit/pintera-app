# Guru Piket — Pengisian Jurnal KBM Real-Time Pengganti Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Guru piket (jadwal bergilir per hari, dikonfigurasi lewat "Program Piket Mingguan") bisa mengisi Jurnal KBM & Presensi untuk sesi guru LAIN yang berhalangan hadir — HARI ITU JUGA saja, dalam lembaga yang sama, dengan jejak akuntabilitas siapa yang benar-benar mengisi.

**Architecture:** 2 lapis data (`JadwalPiketMingguan` pola berulang → generate `PiketHarian` per-tanggal, dengan override manual), 1 Service (`PiketAccessChecker`) dipanggil dari 2 titik guard existing (`JurnalKbmController::authorizeMilikGuru()` dan `UpdateJurnalPresensiRequest::authorize()`), 1 kolom akuntabilitas baru di `SesiPembelajaran`, dan 1 halaman admin baru untuk kelola jadwal.

**Tech Stack:** Laravel 12, PHP 8.3, Pest, Blade + Alpine.js.

## Global Constraints

- Wewenang piket HANYA hari ini (`tanggal` sesi = hari ini), BUKAN model N-hari-mundur seperti Proyek A (batas edit presensi).
- 2 lapis data: `JadwalPiketMingguan` (pola berulang per hari-dalam-minggu) men-generate `PiketHarian` (per-tanggal konkret). Override manual pada `PiketHarian` tetap dipertahankan, TIDAK PERNAH tertimpa otomatis.
- 3 aturan baris `PiketHarian` "beku" yang TIDAK PERNAH disentuh regenerate: `sumber = 'override_manual'`, ATAU `tanggal < hari ini`, ATAU sudah ada `SesiPembelajaran.diisi_oleh_guru_id` yang cocok guru+tanggal+lembaga baris itu.
- `RegenerateJadwalPiketHarianAction`: 3 langkah bernomor WAJIB urutan itu (1. ambil kandidat, 2. filter yang sudah dipakai, 3. baru delete+generate ulang), dibungkus 1 `DB::transaction()`.
- `GenerateJadwalPiketHarianAction` WAJIB idempotent (`firstOrCreate`, dilindungi unique constraint `(lembaga_id, guru_id, tanggal)`).
- Kriteria pemilihan Generate vs Regenerate berbasis KONDISI DATA (`PiketHarian::exists()` untuk lembaga+semester itu), BUKAN jenis form action (tambah/ubah/hapus baris).
- `KalenderAkademik` berubah SETELAH batch generate awal — TIDAK dibangun mekanisme otomatis pembersihan, diterima sebagai keterbatasan (JANGAN bangun observer/hook lintas-domain).
- `PiketAccessChecker` WAJIB cek `$sesi->lembaga_id` (BUKAN lembaga_id guru yang login) — cross-tenant safety.
- 1 Service (`PiketAccessChecker`) dipanggil dari `authorizeMilikGuru()` DAN `UpdateJurnalPresensiRequest::authorize()` — logic TIDAK BOLEH ditulis ulang beda di 2 tempat.
- `diisi_oleh_guru_id` cuma terisi kalau guru yang submit BEDA dari pemilik asli sesi — guru pemilik sendiri isi sesinya sendiri → tetap `null`.
- Permission `piket.kelola` HANYA untuk atur jadwal, BUKAN untuk mengisi presensi (itu tetap `presensi.isi` yang sudah ada).
- Fase 2 (`LaporanPiket`, verifikasi Kepala Sekolah, cetak dokumen) TIDAK dikerjakan — di luar cakupan total plan ini.
- JANGAN merusak fitur A2 (notifikasi WA) & A3 (Scan Presensi Kartu Digital Siswa) yang sudah ada.
- Tidak pakai worktree, kerja langsung di branch `akademik-v2`.

---

## File Structure

- **Create**: migrasi `jadwal_piket_mingguan`, `piket_harian`, kolom `diisi_oleh_guru_id` di `sesi_pembelajaran`
- **Create**: `app/Domains/Akademik/Models/JadwalPiketMingguan.php`, `PiketHarian.php`
- **Create**: `app/Domains/Akademik/Actions/Piket/GenerateJadwalPiketHarianAction.php`, `RegenerateJadwalPiketHarianAction.php`
- **Create**: `app/Domains/Akademik/Services/PiketAccessChecker.php`
- **Modify**: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php` (`authorizeMilikGuru()`, `update()`, `index()`)
- **Modify**: `app/Http/Requests/Akademik/UpdateJurnalPresensiRequest.php`
- **Modify**: `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php`
- **Modify**: `resources/views/portals/guru/akademik/jurnal-kbm/index.blade.php`
- **Modify**: `database/seeders/PermissionSeeder.php`, `database/seeders/RoleSeeder.php`
- **Create**: `app/Http/Controllers/Admin/JadwalPiketMingguanController.php`, `app/Http/Controllers/Admin/PiketHarianController.php`
- **Modify**: `routes/admin/akademik-master.php`
- **Create**: `resources/views/portals/lembaga/akademik/piket-guru/index.blade.php`, `create.blade.php`, `edit.blade.php`, dan view override `PiketHarian`
- **Create**: `.agents/logs/2026-09-06-guru-piket-jurnal-kbm.md`
- **Modify**: `PETA_PENGEMBANGAN.md`

---

### Task 1: Migrasi & Model Dasar

**Files:**
- Create: `database/migrations/2026_09_06_000003_create_jadwal_piket_mingguan_table.php`
- Create: `database/migrations/2026_09_06_000004_create_piket_harian_table.php`
- Create: `database/migrations/2026_09_06_000005_add_diisi_oleh_guru_id_to_sesi_pembelajaran_table.php`
- Create: `app/Domains/Akademik/Models/JadwalPiketMingguan.php`
- Create: `app/Domains/Akademik/Models/PiketHarian.php`
- Test: `tests/Unit/Domains/Akademik/PiketModelsTest.php`

**Interfaces:**
- Produces: model `JadwalPiketMingguan` (`$fillable`: `lembaga_id`, `guru_id`, `hari`, `semester_id`, `dibuat_oleh_user_id`), model `PiketHarian` (`$fillable`: `lembaga_id`, `guru_id`, `tanggal`, `sumber`, `jadwal_piket_mingguan_id`) — dipakai semua task berikutnya. `SesiPembelajaran.diisi_oleh_guru_id` (nullable int) — dipakai Task 3, 6.

- [x] **Step 1: Buat migrasi `jadwal_piket_mingguan`**

```bash
php artisan make:migration create_jadwal_piket_mingguan_table --no-interaction
```

Isi (ganti nama file jadi `2026_09_06_000003_create_jadwal_piket_mingguan_table.php` kalau timestamp beda):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_piket_mingguan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->unsignedTinyInteger('hari');
            $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->foreignId('dibuat_oleh_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lembaga_id', 'guru_id', 'hari', 'semester_id'], 'jadwal_piket_mingguan_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_piket_mingguan');
    }
};
```

- [x] **Step 2: Buat migrasi `piket_harian`**

```bash
php artisan make:migration create_piket_harian_table --no-interaction
```

Isi (ganti nama file jadi `2026_09_06_000004_create_piket_harian_table.php`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piket_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->date('tanggal');
            $table->enum('sumber', ['dari_jadwal_mingguan', 'override_manual']);
            $table->foreignId('jadwal_piket_mingguan_id')->nullable()->constrained('jadwal_piket_mingguan')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lembaga_id', 'guru_id', 'tanggal'], 'piket_harian_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piket_harian');
    }
};
```

- [x] **Step 3: Buat migrasi kolom `diisi_oleh_guru_id`**

```bash
php artisan make:migration add_diisi_oleh_guru_id_to_sesi_pembelajaran_table --no-interaction
```

Isi (ganti nama file jadi `2026_09_06_000005_add_diisi_oleh_guru_id_to_sesi_pembelajaran_table.php`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_pembelajaran', function (Blueprint $table) {
            $table->foreignId('diisi_oleh_guru_id')->nullable()->after('guru_id')->constrained('guru')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sesi_pembelajaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diisi_oleh_guru_id');
        });
    }
};
```

- [x] **Step 4: Jalankan migrasi**

Run: `php artisan migrate`
Expected: ketiga migrasi baru `Migrated:` tanpa error.

- [x] **Step 4b: Tambah `diisi_oleh_guru_id` ke `$fillable` model `SesiPembelajaran`**

Baca `app/Domains/Akademik/Models/SesiPembelajaran.php` (isi `$fillable` saat ini: `['jadwal_pelajaran_id', 'kelas_id', 'guru_id', 'mata_pelajaran_id', 'lembaga_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'materi', 'status']`), tambahkan `'diisi_oleh_guru_id'` ke array itu:

```php
    protected $fillable = [
        'jadwal_pelajaran_id', 'kelas_id', 'guru_id', 'mata_pelajaran_id', 'lembaga_id',
        'tanggal', 'jam_mulai', 'jam_selesai', 'materi', 'status', 'diisi_oleh_guru_id',
    ];
```

**WAJIB dikerjakan sekarang (Task 1), bukan ditunda ke Task 6** — Task 3 (Regenerate Action) sudah butuh mass-assign field ini lewat factory di testnya.

- [x] **Step 5: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanLembagaGuruSemester(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create();

    return compact('lembaga', 'semester', 'guru', 'user');
}

it('membuat JadwalPiketMingguan dengan relasi yang benar', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'user' => $user] = siapkanLembagaGuruSemester();

    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => 1,
        'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id,
    ]);

    expect($jadwal->guru->id)->toBe($guru->id);
});

it('menolak JadwalPiketMingguan duplikat (guru+hari+semester sama)', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'user' => $user] = siapkanLembagaGuruSemester();
    JadwalPiketMingguan::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => 1, 'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id]);

    expect(fn () => JadwalPiketMingguan::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => 1, 'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id]))
        ->toThrow(QueryException::class);
});

it('membuat PiketHarian dengan relasi yang benar', function () {
    ['lembaga' => $lembaga, 'guru' => $guru] = siapkanLembagaGuruSemester();

    $piket = PiketHarian::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id,
        'tanggal' => now()->toDateString(), 'sumber' => 'override_manual',
    ]);

    expect($piket->guru->id)->toBe($guru->id);
});

it('menolak PiketHarian duplikat (guru+tanggal+lembaga sama)', function () {
    ['lembaga' => $lembaga, 'guru' => $guru] = siapkanLembagaGuruSemester();
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual']);

    expect(fn () => PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual']))
        ->toThrow(QueryException::class);
});
```

- [x] **Step 6: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Domains/Akademik/PiketModelsTest.php --compact`
Expected: FAIL — `Class "App\Domains\Akademik\Models\JadwalPiketMingguan" not found`.

- [x] **Step 7: Tulis model `JadwalPiketMingguan`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Models;

use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalPiketMingguan extends Model
{
    protected $table = 'jadwal_piket_mingguan';

    protected $fillable = ['lembaga_id', 'guru_id', 'hari', 'semester_id', 'dibuat_oleh_user_id'];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh_user_id');
    }
}
```

- [x] **Step 8: Tulis model `PiketHarian`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Models;

use App\Models\Guru;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PiketHarian extends Model
{
    protected $table = 'piket_harian';

    protected $fillable = ['lembaga_id', 'guru_id', 'tanggal', 'sumber', 'jadwal_piket_mingguan_id'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function jadwalPiketMingguan(): BelongsTo
    {
        return $this->belongsTo(JadwalPiketMingguan::class);
    }
}
```

- [x] **Step 9: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Domains/Akademik/PiketModelsTest.php --compact`
Expected: **4 passed**.

- [x] **Step 10: Commit**

```bash
git add database/migrations/2026_09_06_000003_create_jadwal_piket_mingguan_table.php database/migrations/2026_09_06_000004_create_piket_harian_table.php database/migrations/2026_09_06_000005_add_diisi_oleh_guru_id_to_sesi_pembelajaran_table.php app/Domains/Akademik/Models/JadwalPiketMingguan.php app/Domains/Akademik/Models/PiketHarian.php app/Domains/Akademik/Models/SesiPembelajaran.php tests/Unit/Domains/Akademik/PiketModelsTest.php
git commit -m "feat(akademik): migrasi & model dasar JadwalPiketMingguan, PiketHarian, kolom akuntabilitas

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: `GenerateJadwalPiketHarianAction`

**Files:**
- Create: `app/Domains/Akademik/Actions/Piket/GenerateJadwalPiketHarianAction.php`
- Test: `tests/Unit/Domains/Akademik/GenerateJadwalPiketHarianActionTest.php`

**Interfaces:**
- Consumes: `KalenderAkademikResolver::resolve(Lembaga $lembaga, CarbonInterface $tanggal): array{libur: bool, alasan: string}` (Service SUDAH ADA, `app/Domains/Akademik/Services/KalenderAkademikResolver.php` — PERIKSA LANGSUNG file itu sebelum menulis kode, sudah dipakai `SesiPembelajaranGenerator` untuk kebutuhan sama).
- Produces: `GenerateJadwalPiketHarianAction::execute(JadwalPiketMingguan $jadwal): void` — dipakai Task 9 (controller admin) dan Task 3 (dipanggil ulang oleh Regenerate).

- [x] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction;
use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\KalenderAkademik;
use App\Domains\Akademik\Models\PiketHarian;
use App\Enums\TipeKalenderAkademik;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generate PiketHarian untuk semua tanggal cocok hari dalam rentang semester, mulai dari hari ini', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => []]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mulai = now()->startOfDay();
    $selesai = now()->addWeeks(3)->startOfDay();
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'lembaga_id' => $lembaga->id,
        'tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $selesai->toDateString(),
    ]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create();
    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => $mulai->dayOfWeek,
        'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id,
    ]);

    (new GenerateJadwalPiketHarianAction(new \App\Domains\Akademik\Services\KalenderAkademikResolver))->execute($jadwal);

    $tanggalHasil = PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->orderBy('tanggal')->pluck('tanggal')->map->toDateString()->all();
    $tanggalHarapan = collect(range(0, 3))
        ->map(fn ($i) => $mulai->copy()->addWeeks($i)->toDateString())
        ->filter(fn ($tgl) => $tgl <= $selesai->toDateString())
        ->values()->all();
    expect($tanggalHasil)->toBe($tanggalHarapan);
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->get()->every(fn ($p) => $p->sumber === 'dari_jadwal_mingguan' && $p->jadwal_piket_mingguan_id === $jadwal->id))->toBeTrue();
});

it('skip tanggal yang jatuh di hari libur akademik', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => []]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mulai = now()->startOfDay();
    $selesai = now()->addWeeks(2)->startOfDay();
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'lembaga_id' => $lembaga->id,
        'tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $selesai->toDateString(),
    ]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create();
    $tanggalLiburKandidat = $mulai->copy()->addWeek();
    KalenderAkademik::create([
        'lembaga_id' => $lembaga->id, 'tanggal' => $tanggalLiburKandidat->toDateString(), 'tanggal_selesai' => $tanggalLiburKandidat->toDateString(),
        'nama' => 'Libur Uji Coba', 'tipe' => TipeKalenderAkademik::Libur,
    ]);
    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => $mulai->dayOfWeek,
        'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id,
    ]);

    (new GenerateJadwalPiketHarianAction(new \App\Domains\Akademik\Services\KalenderAkademikResolver))->execute($jadwal);

    $tanggalHasil = PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->pluck('tanggal')->map->toDateString()->all();
    expect($tanggalHasil)->not->toContain($tanggalLiburKandidat->toDateString());
    expect($tanggalHasil)->toContain($mulai->toDateString());
});

it('idempotent -- dipanggil 2x tidak membuat baris duplikat', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => []]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mulai = now()->startOfDay();
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'lembaga_id' => $lembaga->id,
        'tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $mulai->copy()->addWeeks(2)->toDateString(),
    ]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create();
    $jadwal = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => $mulai->dayOfWeek,
        'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id,
    ]);

    $action = new GenerateJadwalPiketHarianAction(new \App\Domains\Akademik\Services\KalenderAkademikResolver);
    $action->execute($jadwal);
    $jumlahPertama = PiketHarian::count();
    $action->execute($jadwal);
    $jumlahKedua = PiketHarian::count();

    expect($jumlahKedua)->toBe($jumlahPertama);
    expect($jumlahPertama)->toBeGreaterThan(0);
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Domains/Akademik/GenerateJadwalPiketHarianActionTest.php --compact`
Expected: FAIL — `Class "App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction" not found`.

- [x] **Step 3: Tulis implementasi**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Piket;

use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Services\KalenderAkademikResolver;
use Carbon\CarbonPeriod;

final class GenerateJadwalPiketHarianAction
{
    public function __construct(
        private readonly KalenderAkademikResolver $kalenderResolver,
    ) {}

    public function execute(JadwalPiketMingguan $jadwal): void
    {
        $jadwal->loadMissing('semester', 'lembaga');
        $semester = $jadwal->semester;
        $lembaga = $jadwal->lembaga;

        if ($semester->tanggal_mulai === null || $semester->tanggal_selesai === null) {
            return;
        }

        // Mulai dari HARI INI (bukan semester->tanggal_mulai) -- kalau JadwalPiketMingguan
        // dibuat di tengah semester, jangan generate PiketHarian utk tanggal LAMPAU. Wewenang
        // piket sengaja hanya berlaku hari ini/ke depan (spec §2 poin 3); men-generate baris
        // utk tanggal lampau akan diam-diam memberi akses piket ke sesi lama lewat
        // PiketAccessChecker (yang sengaja cuma cek kecocokan tabel, bukan cek tanggal=hari ini).
        $tanggalMulai = $semester->tanggal_mulai->isPast() ? now()->startOfDay() : $semester->tanggal_mulai;
        $periode = CarbonPeriod::create($tanggalMulai, $semester->tanggal_selesai);

        foreach ($periode as $tanggal) {
            if ((int) $tanggal->dayOfWeek !== (int) $jadwal->hari) {
                continue;
            }

            $resolusi = $this->kalenderResolver->resolve($lembaga, $tanggal);
            if ($resolusi['libur']) {
                continue;
            }

            PiketHarian::firstOrCreate(
                [
                    'lembaga_id' => $jadwal->lembaga_id,
                    'guru_id' => $jadwal->guru_id,
                    'tanggal' => $tanggal->toDateString(),
                ],
                [
                    'sumber' => 'dari_jadwal_mingguan',
                    'jadwal_piket_mingguan_id' => $jadwal->id,
                ]
            );
        }
    }
}
```

**PENTING**: kode di atas ditulis berdasarkan pembacaan `KalenderAkademikResolver::resolve()` saat spec/plan ditulis (signature `resolve(Lembaga $lembaga, CarbonInterface $tanggal): array{libur: bool, alasan: string}`, tanpa dependency constructor lain). Implementer WAJIB baca ulang file `app/Domains/Akademik/Services/KalenderAkademikResolver.php` sebelum implementasi — kalau ternyata berubah, sesuaikan.

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Domains/Akademik/GenerateJadwalPiketHarianActionTest.php --compact`
Expected: **3 passed**.

- [x] **Step 5: Commit**

```bash
git add app/Domains/Akademik/Actions/Piket/GenerateJadwalPiketHarianAction.php tests/Unit/Domains/Akademik/GenerateJadwalPiketHarianActionTest.php
git commit -m "feat(akademik): GenerateJadwalPiketHarianAction -- idempotent, skip hari libur akademik

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: `RegenerateJadwalPiketHarianAction`

**Files:**
- Create: `app/Domains/Akademik/Actions/Piket/RegenerateJadwalPiketHarianAction.php`
- Test: `tests/Unit/Domains/Akademik/RegenerateJadwalPiketHarianActionTest.php`

**Interfaces:**
- Consumes: `GenerateJadwalPiketHarianAction` (Task 2, dipakai ulang untuk langkah generate-ulang), `PiketHarian`, `SesiPembelajaran` (kolom `diisi_oleh_guru_id`, Task 1).
- Produces: `RegenerateJadwalPiketHarianAction::execute(int $lembagaId, int $semesterId): void` — dipakai Task 9 (controller admin).

- [x] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Actions\Piket\RegenerateJadwalPiketHarianAction;
use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanPiketHarianRegenerateTest(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => []]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'lembaga_id' => $lembaga->id,
        'tanggal_mulai' => now()->subDays(10)->toDateString(), 'tanggal_selesai' => now()->addDays(30)->toDateString(),
    ]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create();
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    return compact('lembaga', 'semester', 'guru', 'user', 'kelas');
}

it('baris dari_jadwal_mingguan tanggal depan tanpa akuntabilitas -- dihapus & digenerate ulang', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'user' => $user] = siapkanPiketHarianRegenerateTest();
    $jadwalLama = JadwalPiketMingguan::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'hari' => now()->addDay()->dayOfWeek, 'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $user->id]);
    $tanggalLama = now()->addDay()->toDateString();
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => $tanggalLama, 'sumber' => 'dari_jadwal_mingguan', 'jadwal_piket_mingguan_id' => $jadwalLama->id]);

    $guruBaru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwalLama->update(['guru_id' => $guruBaru->id]);

    (new RegenerateJadwalPiketHarianAction(new \App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction(new \App\Domains\Akademik\Services\KalenderAkademikResolver)))
        ->execute($lembaga->id, $semester->id);

    expect(PiketHarian::where('tanggal', $tanggalLama)->where('guru_id', $guru->id)->exists())->toBeFalse();
    expect(PiketHarian::where('tanggal', $tanggalLama)->where('guru_id', $guruBaru->id)->exists())->toBeTrue();
});

it('baris override_manual TIDAK disentuh regenerate', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'user' => $user] = siapkanPiketHarianRegenerateTest();
    $tanggalDepan = now()->addDay()->toDateString();
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => $tanggalDepan, 'sumber' => 'override_manual']);

    (new RegenerateJadwalPiketHarianAction(new \App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction(new \App\Domains\Akademik\Services\KalenderAkademikResolver)))
        ->execute($lembaga->id, $semester->id);

    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->where('tanggal', $tanggalDepan)->where('sumber', 'override_manual')->exists())->toBeTrue();
});

it('baris tanggal LAMPAU (tanggal < hari ini) TIDAK disentuh regenerate', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'user' => $user] = siapkanPiketHarianRegenerateTest();
    $tanggalLampau = now()->subDay()->toDateString();
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => $tanggalLampau, 'sumber' => 'dari_jadwal_mingguan']);

    (new RegenerateJadwalPiketHarianAction(new \App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction(new \App\Domains\Akademik\Services\KalenderAkademikResolver)))
        ->execute($lembaga->id, $semester->id);

    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->where('tanggal', $tanggalLampau)->exists())->toBeTrue();
});

it('baris yang SUDAH DIPAKAI (ada SesiPembelajaran.diisi_oleh_guru_id cocok) TIDAK disentuh regenerate walau tanggal depan', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'user' => $user, 'kelas' => $kelas] = siapkanPiketHarianRegenerateTest();
    $tanggalHariIni = now()->toDateString();
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => $tanggalHariIni, 'sumber' => 'dari_jadwal_mingguan']);

    $guruAsli = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruAsli->id,
        'diisi_oleh_guru_id' => $guru->id, 'tanggal' => $tanggalHariIni,
    ]);

    (new RegenerateJadwalPiketHarianAction(new \App\Domains\Akademik\Actions\Piket\GenerateJadwalPiketHarianAction(new \App\Domains\Akademik\Services\KalenderAkademikResolver)))
        ->execute($lembaga->id, $semester->id);

    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->where('tanggal', $tanggalHariIni)->exists())->toBeTrue();
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Domains/Akademik/RegenerateJadwalPiketHarianActionTest.php --compact`
Expected: FAIL — `Class "App\Domains\Akademik\Actions\Piket\RegenerateJadwalPiketHarianAction" not found`.

- [x] **Step 3: Tulis implementasi — PERSIS 3 langkah bernomor di spec §3.2**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Piket;

use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use Illuminate\Support\Facades\DB;

final class RegenerateJadwalPiketHarianAction
{
    public function __construct(
        private readonly GenerateJadwalPiketHarianAction $generateAction,
    ) {}

    public function execute(int $lembagaId, int $semesterId): void
    {
        DB::transaction(function () use ($lembagaId, $semesterId) {
            // Langkah 1: ambil kandidat
            $kandidat = PiketHarian::where('lembaga_id', $lembagaId)
                ->where('tanggal', '>=', now()->toDateString())
                ->where('sumber', 'dari_jadwal_mingguan')
                ->get();

            // Langkah 2: filter buang yang sudah dipakai
            $bolehDihapus = $kandidat->reject(function (PiketHarian $baris) {
                return SesiPembelajaran::where('diisi_oleh_guru_id', $baris->guru_id)
                    ->where('tanggal', $baris->tanggal->toDateString())
                    ->where('lembaga_id', $baris->lembaga_id)
                    ->exists();
            });

            // Langkah 3: delete + generate ulang
            PiketHarian::whereIn('id', $bolehDihapus->pluck('id'))->delete();

            $jadwalList = JadwalPiketMingguan::where('lembaga_id', $lembagaId)
                ->where('semester_id', $semesterId)
                ->get();

            foreach ($jadwalList as $jadwal) {
                $this->generateAction->execute($jadwal);
            }
        });
    }
}
```

**Catatan urutan (WAJIB, jangan diubah)**: langkah 1 (query kandidat) dan langkah 2 (filter) HARUS selesai SEBELUM baris `delete()` di langkah 3 dijalankan — kode di atas sudah urut begitu (variabel `$bolehDihapus` dihitung penuh dulu, baru dipakai untuk delete). `GenerateJadwalPiketHarianAction::execute()` (Task 2) sendiri idempotent (`firstOrCreate`), jadi generate ulang di langkah 3 otomatis SKIP tanggal yang masih ada barisnya (baik karena `override_manual`, tanggal lampau, atau sudah dipakai — semuanya tidak ikut terhapus, jadi `firstOrCreate` di generate ulang menemukan baris itu sudah ada dan tidak menimpanya).

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Domains/Akademik/RegenerateJadwalPiketHarianActionTest.php --compact`
Expected: **4 passed**.

- [x] **Step 5: Commit**

```bash
git add app/Domains/Akademik/Actions/Piket/RegenerateJadwalPiketHarianAction.php tests/Unit/Domains/Akademik/RegenerateJadwalPiketHarianActionTest.php
git commit -m "feat(akademik): RegenerateJadwalPiketHarianAction -- 3 langkah bernomor, lindungi baris beku

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: `PiketAccessChecker`

**Files:**
- Create: `app/Domains/Akademik/Services/PiketAccessChecker.php`
- Test: `tests/Unit/Domains/Akademik/PiketAccessCheckerTest.php`

**Interfaces:**
- Consumes: `PiketHarian` (Task 1), `SesiPembelajaran`, `Guru`.
- Produces: `PiketAccessChecker::bisaAkses(SesiPembelajaran $sesi, Guru $guru): bool` — dipakai Task 5.

- [x] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\PiketAccessChecker;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanSesiUntukAccessCheckerTest(int $lembagaId): SesiPembelajaran
{
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaId]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaId, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $guruPemilik = Guru::factory()->create(['lembaga_id' => $lembagaId]);

    return SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembagaId, 'kelas_id' => $kelas->id, 'guru_id' => $guruPemilik->id, 'tanggal' => now()->toDateString(),
    ]);
}

it('guru pemilik sesi selalu bisa akses', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $sesi = siapkanSesiUntukAccessCheckerTest($lembaga->id);
    $guruPemilik = Guru::find($sesi->guru_id);

    expect((new PiketAccessChecker)->bisaAkses($sesi, $guruPemilik))->toBeTrue();
});

it('guru piket lembaga SAMA, tanggal SAMA bisa akses sesi guru lain', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $sesi = siapkanSesiUntukAccessCheckerTest($lembaga->id);
    $guruPiket = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guruPiket->id, 'tanggal' => $sesi->tanggal->toDateString(), 'sumber' => 'override_manual']);

    expect((new PiketAccessChecker)->bisaAkses($sesi, $guruPiket))->toBeTrue();
});

it('guru piket lembaga LAIN TIDAK bisa akses walau tanggal sama', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $sesi = siapkanSesiUntukAccessCheckerTest($lembagaA->id);
    $guruPiketLembagaB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    // Guru ini piket di lembaga B, BUKAN di lembaga A tempat $sesi berada.
    PiketHarian::create(['lembaga_id' => $lembagaB->id, 'guru_id' => $guruPiketLembagaB->id, 'tanggal' => $sesi->tanggal->toDateString(), 'sumber' => 'override_manual']);

    expect((new PiketAccessChecker)->bisaAkses($sesi, $guruPiketLembagaB))->toBeFalse();
});

it('guru bukan pemilik & bukan piket TIDAK bisa akses', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $sesi = siapkanSesiUntukAccessCheckerTest($lembaga->id);
    $guruLain = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    expect((new PiketAccessChecker)->bisaAkses($sesi, $guruLain))->toBeFalse();
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Domains/Akademik/PiketAccessCheckerTest.php --compact`
Expected: FAIL — `Class "App\Domains\Akademik\Services\PiketAccessChecker" not found`.

- [x] **Step 3: Tulis implementasi**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Guru;

final class PiketAccessChecker
{
    public function bisaAkses(SesiPembelajaran $sesi, Guru $guru): bool
    {
        if ($sesi->guru_id === $guru->id) {
            return true;
        }

        return PiketHarian::where('lembaga_id', $sesi->lembaga_id)
            ->where('guru_id', $guru->id)
            ->where('tanggal', $sesi->tanggal->toDateString())
            ->exists();
    }
}
```

**PENTING**: baris `->where('lembaga_id', $sesi->lembaga_id)` WAJIB ada persis seperti ini — scoping ke lembaga milik SESI, BUKAN lembaga milik guru yang login. Ini nama class TIDAK mengikuti konvensi suffix Resolver/Generator/Aggregator/Engine di `.ai/rules/services.md` — SENGAJA, nama ini sudah ditentukan eksplisit di spec yang disetujui user, JANGAN diganti nama unilateral.

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Domains/Akademik/PiketAccessCheckerTest.php --compact`
Expected: **4 passed**.

- [x] **Step 5: Commit**

```bash
git add app/Domains/Akademik/Services/PiketAccessChecker.php tests/Unit/Domains/Akademik/PiketAccessCheckerTest.php
git commit -m "feat(akademik): PiketAccessChecker -- validasi akses guru piket dgn scoping lembaga sesi

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Wiring Guard — `authorizeMilikGuru()` & `UpdateJurnalPresensiRequest`

**Files:**
- Modify: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`
- Modify: `app/Http/Requests/Akademik/UpdateJurnalPresensiRequest.php`
- Test: `tests/Feature/Guru/JurnalKbmPiketAksesTest.php`

**Interfaces:**
- Consumes: `PiketAccessChecker::bisaAkses()` (Task 4).
- Produces: `authorizeMilikGuru()` dan `UpdateJurnalPresensiRequest::authorize()` sekarang menerima guru piket, dipakai transparan oleh `show()`, `update()`, `resolveKartu()` yang sudah ada.

**PENTING**: baca dulu isi KEDUA file ini SEKARANG (sudah berubah beberapa kali sesi-sesi sebelumnya untuk fitur Kartu Digital Siswa & Batas Edit Presensi) — JANGAN asumsi dari plan-plan sebelumnya, verifikasi state terkini.

- [x] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanSesiDanGuruPiketUntukAksesTest(): array
{
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_piket_akses_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $guruPemilik = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $sesi = SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruPemilik->id, 'tanggal' => now()->toDateString(),
    ]);

    $guruPiket = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userPiket = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Person::where('id', $guruPiket->person_id)->update(['user_id' => $userPiket->id]);
    $userPiket->assignRole($role);
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guruPiket->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual']);

    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruPiketLembagaLain = Guru::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $userPiketLembagaLain = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
    Person::where('id', $guruPiketLembagaLain->person_id)->update(['user_id' => $userPiketLembagaLain->id]);
    $userPiketLembagaLain->assignRole($role);
    PiketHarian::create(['lembaga_id' => $lembagaLain->id, 'guru_id' => $guruPiketLembagaLain->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual']);

    return compact('sesi', 'userPiket', 'userPiketLembagaLain');
}

it('guru piket hari ini BISA akses show() sesi guru lain lembaga sama', function () {
    ['sesi' => $sesi, 'userPiket' => $userPiket] = siapkanSesiDanGuruPiketUntukAksesTest();

    $response = $this->actingAs($userPiket)->get(route('guru.jurnal-kbm.show', $sesi));

    $response->assertOk();
});

it('guru piket hari ini BISA update() sesi guru lain lembaga sama', function () {
    ['sesi' => $sesi, 'userPiket' => $userPiket] = siapkanSesiDanGuruPiketUntukAksesTest();

    $response = $this->actingAs($userPiket)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Diisi guru piket', 'presensi' => [],
    ]);

    $response->assertSessionHas('status');
});

it('guru piket lembaga LAIN TIDAK BISA akses sesi', function () {
    ['sesi' => $sesi, 'userPiketLembagaLain' => $userPiketLembagaLain] = siapkanSesiDanGuruPiketUntukAksesTest();

    $response = $this->actingAs($userPiketLembagaLain)->get(route('guru.jurnal-kbm.show', $sesi));

    $response->assertForbidden();
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Guru/JurnalKbmPiketAksesTest.php --compact`
Expected: FAIL — semua test gagal (guru piket ditolak 403 karena guard lama cuma cek `guru_id === $guru->id`).

- [x] **Step 3: Modifikasi `authorizeMilikGuru()`**

Ganti method `authorizeMilikGuru()` di `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`:

```php
    private function authorizeMilikGuru(SesiPembelajaran $sesi): void
    {
        $guru = auth()->user()->guru;

        abort_if($guru === null || ! app(\App\Domains\Akademik\Services\PiketAccessChecker::class)->bisaAkses($sesi, $guru), 403);
    }
```

Tambahkan `use App\Domains\Akademik\Services\PiketAccessChecker;` ke bagian atas file, lalu sederhanakan referensi FQCN di atas jadi `PiketAccessChecker::class` (ikuti gaya file ini yang sudah pakai `use` untuk class lain).

- [x] **Step 4: Modifikasi `UpdateJurnalPresensiRequest::authorize()`**

Baca dulu isi file `app/Http/Requests/Akademik/UpdateJurnalPresensiRequest.php` (isi saat ini: `authorize()` cuma cek `$guru !== null && $sesi instanceof SesiPembelajaran && $sesi->guru_id === $guru->id`). Ganti jadi:

```php
<?php

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\JurnalPresensiData;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\PiketAccessChecker;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateJurnalPresensiRequest extends FormRequest
{
    // Sole ownership enforcement point for the update route (mirrors, but is not
    // called by, JurnalKbmController::authorizeMilikGuru(), which still guards show()).
    // Kedua titik ini WAJIB memakai PiketAccessChecker yang sama -- jangan tulis ulang logic beda.
    public function authorize(): bool
    {
        $sesi = $this->route('sesi');
        $guru = $this->user()?->guru;

        if ($guru === null || ! $sesi instanceof SesiPembelajaran) {
            return false;
        }

        return app(PiketAccessChecker::class)->bisaAkses($sesi, $guru);
    }

    public function rules(): array
    {
        return [
            'materi' => ['nullable', 'string'],
            'presensi' => ['required', 'array'],
            'presensi.*' => ['required', 'in:hadir,izin,sakit,alpa,terlambat'],
            'keterangan' => ['nullable', 'array'],
            'keterangan.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toDTO(): JurnalPresensiData
    {
        return JurnalPresensiData::fromArray($this->validated());
    }
}
```

- [x] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Guru/JurnalKbmPiketAksesTest.php --compact`
Expected: **3 passed**.

- [x] **Step 6: Jalankan ulang SEMUA test existing yang bergantung ke `authorizeMilikGuru()`/`UpdateJurnalPresensiRequest`, pastikan tidak regresi**

Run: `php artisan test tests/Feature/Guru/JurnalKbmControllerTest.php tests/Feature/Guru/JurnalKbmResolveKartuTest.php tests/Feature/Guru/JurnalKbmBatasEditTest.php tests/Feature/Akademik/JurnalKbmTanggalSusulanTest.php --compact`
Expected: semua test lama tetap **passed** — kasus non-piket (guru biasa akses sesinya sendiri, guru lain ditolak) harus berperilaku identik seperti sebelumnya.

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/Guru/Akademik/JurnalKbmController.php app/Http/Requests/Akademik/UpdateJurnalPresensiRequest.php tests/Feature/Guru/JurnalKbmPiketAksesTest.php
git commit -m "feat(akademik): wiring PiketAccessChecker ke authorizeMilikGuru() & UpdateJurnalPresensiRequest

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Kolom Akuntabilitas — `diisi_oleh_guru_id`

**Files:**
- Modify: `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php`
- Modify: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`
- Test: `tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php`

**Interfaces:**
- Consumes: `SesiPembelajaran.diisi_oleh_guru_id` (Task 1).
- Produces: `RecordJurnalDanPresensiAction::execute(SesiPembelajaran $sesi, JurnalPresensiData $data, ?int $diisiOlehGuruId = null): SesiPembelajaran` — signature baru, parameter ke-3 opsional (backward compatible).

**PENTING**: baca dulu isi `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php` SEKARANG — file ini sudah dimodifikasi berkali-kali sesi-sesi sebelumnya untuk fitur notifikasi WA (Opsi A2). Alur notifikasi (`PresensiNotificationService::kirimJikaPerluAtasPerubahan()`, dipanggil SETELAH `DB::transaction()` selesai) TIDAK BOLEH berubah sama sekali — perubahan Task ini HANYA menambah 1 field ke `$sesi->update([...])` yang sudah ada, tidak menyentuh apa pun soal notifikasi.

- [x] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanGuruPiketDanSesiUntukAkuntabilitasTest(): array
{
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_akuntabilitas_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $guruPemilik = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userPemilik = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Person::where('id', $guruPemilik->person_id)->update(['user_id' => $userPemilik->id]);
    $userPemilik->assignRole($role);

    $sesi = SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruPemilik->id, 'tanggal' => now()->toDateString(),
    ]);

    $guruPiket = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userPiket = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Person::where('id', $guruPiket->person_id)->update(['user_id' => $userPiket->id]);
    $userPiket->assignRole($role);
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guruPiket->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual']);

    return compact('sesi', 'guruPemilik', 'userPemilik', 'guruPiket', 'userPiket');
}

it('guru piket submit jurnal -- diisi_oleh_guru_id terisi ID guru piket', function () {
    ['sesi' => $sesi, 'userPiket' => $userPiket, 'guruPiket' => $guruPiket] = siapkanGuruPiketDanSesiUntukAkuntabilitasTest();

    $this->actingAs($userPiket)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Diisi guru piket', 'presensi' => [],
    ]);

    expect($sesi->fresh()->diisi_oleh_guru_id)->toBe($guruPiket->id);
});

it('guru pemilik asli submit jurnal untuk sesinya sendiri -- diisi_oleh_guru_id TETAP null', function () {
    ['sesi' => $sesi, 'userPemilik' => $userPemilik] = siapkanGuruPiketDanSesiUntukAkuntabilitasTest();

    $this->actingAs($userPemilik)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Diisi guru pemilik', 'presensi' => [],
    ]);

    expect($sesi->fresh()->diisi_oleh_guru_id)->toBeNull();
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php --compact`
Expected: FAIL — `diisi_oleh_guru_id` tetap `null` untuk kedua kasus (belum ada logic mengisinya).

- [x] **Step 3: Modifikasi `RecordJurnalDanPresensiAction`**

Tambahkan parameter opsional ke-3, dan sisipkan `diisi_oleh_guru_id` ke `$sesi->update()` yang SUDAH ADA di dalam `DB::transaction()` — JANGAN ubah baris lain:

```php
    public function execute(SesiPembelajaran $sesi, JurnalPresensiData $data, ?int $diisiOlehGuruId = null): SesiPembelajaran
    {
        $perluDicek = [];

        $sesiTerbaru = DB::transaction(function () use ($sesi, $data, $diisiOlehGuruId, &$perluDicek) {
            $sesi->update([
                'materi' => $data->materi,
                'diisi_oleh_guru_id' => $diisiOlehGuruId,
            ]);
```

(Baris lain di dalam `execute()` — loop `$data->presensi`, `return $sesi->fresh();`, dan seluruh blok pengiriman notifikasi setelah `DB::transaction()` — TETAP PERSIS SAMA, tidak diubah sama sekali.)

- [x] **Step 4: Modifikasi `JurnalKbmController::update()`**

Tambahkan penghitungan `$diisiOlehGuruId` sebelum memanggil Action:

```php
    public function update(UpdateJurnalPresensiRequest $request, SesiPembelajaran $sesi): RedirectResponse
    {
        $this->authorize('presensi.isi');
        // Ownership check is already enforced by UpdateJurnalPresensiRequest::authorize(),
        // which runs before this method body — no need to call authorizeMilikGuru() again here.

        $guru = $request->user()->guru;

        if ($this->sesiTerkunci($sesi, $guru)) {
            $batasHari = $guru->lembaga->batas_edit_absen_hari ?? 3;

            return redirect()->route('guru.jurnal-kbm.index')
                ->with('error', "Sesi ini sudah melewati batas waktu edit ({$batasHari} hari). Hubungi Wali Kelas kelas ini untuk koreksi.");
        }

        $diisiOlehGuruId = $guru->id !== $sesi->guru_id ? $guru->id : null;

        $this->recordJurnalDanPresensiAction->execute($sesi, $request->toDTO(), $diisiOlehGuruId);

        return redirect()->route('guru.jurnal-kbm.index')->with('status', 'Jurnal dan presensi berhasil disimpan.');
    }
```

- [x] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php --compact`
Expected: **2 passed**.

- [x] **Step 6: Jalankan ulang test A2 (notifikasi WA) yang sudah ada, WAJIB pastikan tidak regresi**

Run: `php artisan test tests/Feature/Guru/JurnalKbmControllerTest.php --compact`
Expected: semua test lama tetap **passed**, termasuk 2 test notifikasi WA ("mengirim notifikasi presensi ke kontak utama..." dan "tidak mengirim notifikasi presensi kalau siswa disimpan tetap hadir") — pemanggilan `execute()` tanpa parameter ke-3 (default `null`) di jalur lama TIDAK mengubah perilaku notifikasi sama sekali.

- [x] **Step 7: Commit**

```bash
git add app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php app/Http/Controllers/Guru/Akademik/JurnalKbmController.php tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php
git commit -m "feat(akademik): kolom akuntabilitas diisi_oleh_guru_id -- terisi hanya kalau bukan guru pemilik asli

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 7: Seksi "Sesi Piket Hari Ini" di `index()`

**Files:**
- Modify: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`
- Modify: `resources/views/portals/guru/akademik/jurnal-kbm/index.blade.php`
- Test: `tests/Feature/Guru/JurnalKbmSesiPiketTest.php`

**Interfaces:**
- Consumes: `PiketHarian` (Task 1).
- Produces: variabel view `$sesiPiket` (Collection, cuma dikirim kalau guru piket hari ini) — dipakai view saja, tidak dipakai task lain.

**PENTING**: baca dulu isi KEDUA file ini SEKARANG sebelum edit (sudah dibaca sebelumnya saat plan ditulis, tapi implementer WAJIB verifikasi ulang state terkini sendiri).

- [x] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\PiketHarian;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanGuruUntukSesiPiketTest(bool $piket): array
{
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_sesi_piket_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $guruLain = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruLain->id, 'tanggal' => now()->toDateString(),
    ]);

    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Person::where('id', $guru->person_id)->update(['user_id' => $user->id]);
    $user->assignRole($role);

    if ($piket) {
        PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual']);
    }

    return compact('user');
}

it('guru YANG PIKET hari ini melihat seksi Sesi Piket Hari Ini', function () {
    ['user' => $user] = siapkanGuruUntukSesiPiketTest(piket: true);

    $response = $this->actingAs($user)->get(route('guru.jurnal-kbm.index'));

    $response->assertOk();
    $response->assertSee('Sesi Piket Hari Ini');
});

it('guru YANG BUKAN piket hari ini TIDAK melihat seksi Sesi Piket Hari Ini sama sekali', function () {
    ['user' => $user] = siapkanGuruUntukSesiPiketTest(piket: false);

    $response = $this->actingAs($user)->get(route('guru.jurnal-kbm.index'));

    $response->assertOk();
    $response->assertDontSee('Sesi Piket Hari Ini');
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Guru/JurnalKbmSesiPiketTest.php --compact`
Expected: FAIL — teks "Sesi Piket Hari Ini" belum ada di mana pun.

- [x] **Step 3: Modifikasi `index()`**

Tambahkan `use App\Domains\Akademik\Models\PiketHarian;` di bagian atas file, lalu sisipkan logic ini SEBELUM `return view(...)` di method `index()` (setelah baris `$sesiList = $guru ? ... : collect();` yang sudah ada):

```php
        $sesiPiket = null;
        if ($guru) {
            $piketHariIni = PiketHarian::where('lembaga_id', $guru->lembaga_id)
                ->where('guru_id', $guru->id)
                ->where('tanggal', now()->toDateString())
                ->exists();

            if ($piketHariIni) {
                $sesiPiket = SesiPembelajaran::where('lembaga_id', $guru->lembaga_id)
                    ->where('guru_id', '!=', $guru->id)
                    ->whereDate('tanggal', $hariIni)
                    ->with('kelas.tahunAjaran', 'mataPelajaran', 'guru')
                    ->get();
            }
        }

        return view('portals.guru.akademik.jurnal-kbm.index', [
            'sesiList' => $sesiList,
            'mapelTerjadwal' => $this->mapelTerjadwalUntukSesiTematik($sesiList, $hariIni),
            'tanggalDipilih' => $hariIni->toDateString(),
            'sesiPiket' => $sesiPiket,
        ]);
```

(Ganti `return view(...)` yang sudah ada persis dengan versi di atas — cuma tambah 1 key baru `'sesiPiket' => $sesiPiket` ke array yang sudah ada, tidak mengubah key lain.)

- [x] **Step 4: Modifikasi view — tambah seksi baru**

Di `resources/views/portals/guru/akademik/jurnal-kbm/index.blade.php`, tambahkan blok baru SETELAH blok `{{-- Sesi List Section --}}` yang sudah ada (sebelum `</div>` penutup terakhir sebelum `</x-app-layout>`):

```blade
        {{-- Sesi Piket Hari Ini — HANYA render kalau variabel ini benar2 ada & tidak kosong --}}
        @if (($sesiPiket ?? null) && $sesiPiket->isNotEmpty())
            <div class="space-y-4 mt-6">
                <h2 class="font-display text-sm font-bold text-gray-700 flex items-center gap-2">
                    <x-icon name="shield" class="h-4 w-4 text-amber-500" />
                    Sesi Piket Hari Ini
                </h2>
                <p class="text-xs text-gray-500 -mt-2">Anda sedang piket hari ini. Sesi di bawah ini milik guru lain yang bisa Anda bantu isi kalau gurunya berhalangan hadir.</p>

                @foreach ($sesiPiket as $sesi)
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/40 p-5 shadow-card flex flex-col md:flex-row md:items-center justify-between gap-5">
                        <div class="space-y-2 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge tone="brass" class="gap-1">
                                    <x-icon name="school" class="h-3 w-3" />
                                    Kelas {{ $sesi->kelas->nama }}
                                </x-badge>
                                <x-badge tone="amber" class="gap-1">
                                    <x-icon name="person" class="h-3 w-3" />
                                    Guru: {{ $sesi->guru->nama_lengkap ?? '-' }}
                                </x-badge>
                            </div>
                        </div>
                        <div class="shrink-0 w-full md:w-auto">
                            <x-link-button href="{{ route('guru.jurnal-kbm.show', $sesi) }}" class="w-full md:w-auto justify-center shadow-sm">
                                <x-icon name="edit" class="h-4 w-4" />
                                Isi Sebagai Piket
                            </x-link-button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
```

- [x] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Guru/JurnalKbmSesiPiketTest.php --compact`
Expected: **2 passed**.

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Guru/Akademik/JurnalKbmController.php resources/views/portals/guru/akademik/jurnal-kbm/index.blade.php tests/Feature/Guru/JurnalKbmSesiPiketTest.php
git commit -m "feat(akademik): seksi Sesi Piket Hari Ini di halaman index Jurnal KBM

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 8: Permission & Role

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`
- Modify: `database/seeders/RoleSeeder.php`

**Interfaces:**
- Produces: permission `piket.kelola` → dimiliki role `wakasek_kesiswaan` dan `operator_akademik` — dipakai Task 9 (`$this->authorize('piket.kelola')`).

- [x] **Step 1: Tambah permission ke `PermissionSeeder.php`**

Baca `database/seeders/PermissionSeeder.php`, cari baris `'pengaturan-akademik.kelola',` (sekitar baris 68), tambahkan `'piket.kelola',` persis setelahnya:

```php
            'pengaturan-akademik.kelola',
            'piket.kelola',
```

- [x] **Step 2: Berikan permission ke `wakasek_kesiswaan` di `RoleSeeder.php`**

Baca `database/seeders/RoleSeeder.php`, cari blok:

```php
            if ($name === 'wakasek_kesiswaan') {
                $role->givePermissionTo([
                    'kasus.view', 'kasus.triase', 'kasus.lihat-log-akses',
                ]);
            }
```

Ganti jadi:

```php
            if ($name === 'wakasek_kesiswaan') {
                $role->givePermissionTo([
                    'kasus.view', 'kasus.triase', 'kasus.lihat-log-akses',
                    'piket.kelola',
                ]);
            }
```

- [x] **Step 3: Berikan permission ke `operator_akademik` di `RoleSeeder.php`**

Di blok `if ($name === 'operator_akademik')` yang sudah ada (array `givePermissionTo` panjang), tambahkan `'piket.kelola',` setelah baris `'pengaturan-akademik.kelola',` yang sudah ada di array itu — JANGAN buat blok `if` baru, cuma tambah 1 baris ke array yang sudah ada.

- [x] **Step 4: Jalankan seeder, verifikasi**

Run: `php artisan db:seed --class=PermissionSeeder`
Run: `php artisan db:seed --class=RoleSeeder`
Expected: tidak ada error.

Run:
```
php artisan tinker --execute '
$r = Spatie\Permission\Models\Role::where("name", "wakasek_kesiswaan")->first();
echo $r ? ($r->hasPermissionTo("piket.kelola") ? "wakasek_kesiswaan: OK" : "wakasek_kesiswaan: GAGAL") : "role tidak ditemukan";
$r2 = Spatie\Permission\Models\Role::where("name", "operator_akademik")->first();
echo PHP_EOL . ($r2 ? ($r2->hasPermissionTo("piket.kelola") ? "operator_akademik: OK" : "operator_akademik: GAGAL") : "role tidak ditemukan");
'
```
Expected: `wakasek_kesiswaan: OK` dan `operator_akademik: OK`.

- [x] **Step 5: Commit**

```bash
git add database/seeders/PermissionSeeder.php database/seeders/RoleSeeder.php
git commit -m "feat(akademik): permission piket.kelola -- diberikan ke wakasek_kesiswaan & operator_akademik

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 9: Admin CRUD `JadwalPiketMingguan` + Kriteria Pemilihan Action

**Files:**
- Create: `app/Http/Controllers/Admin/JadwalPiketMingguanController.php`
- Create: `resources/views/portals/lembaga/akademik/piket-guru/index.blade.php`
- Create: `resources/views/portals/lembaga/akademik/piket-guru/create.blade.php`
- Create: `resources/views/portals/lembaga/akademik/piket-guru/edit.blade.php`
- Modify: `routes/admin/akademik-master.php`
- Test: `tests/Feature/Admin/JadwalPiketMingguanControllerTest.php`

**Interfaces:**
- Consumes: `GenerateJadwalPiketHarianAction` (Task 2), `RegenerateJadwalPiketHarianAction` (Task 3), permission `piket.kelola` (Task 8).

**PENTING**: baca dulu `app/Http/Controllers/Admin/PolaJamController.php` sebagai referensi pola CRUD admin lembaga yang sudah ada di project ini (`ResolveLembagaScopeTrait`, `resolveActiveLembagaId()`, pola `$lembagaId = $request->user()->widestScopeLevel() === 'yayasan' ? $this->resolveActiveLembagaId(...) : $request->user()->lembaga_id`).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\JadwalPiketMingguan;
use App\Domains\Akademik\Models\PiketHarian;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function siapkanAdminPiketKelola(): array
{
    Permission::firstOrCreate(['name' => 'piket.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'wakasek_kesiswaan_piket_test', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('piket.kelola');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => []]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'lembaga_id' => $lembaga->id, 'status_aktif' => true,
        'tanggal_mulai' => now()->toDateString(), 'tanggal_selesai' => now()->addWeeks(4)->toDateString(),
    ]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    return compact('lembaga', 'semester', 'guru', 'admin');
}

it('lembaga BELUM punya PiketHarian -- store() memicu Generate (sinkron, tanpa job)', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketKelola();

    $response = $this->actingAs($admin)->post(route('admin.piket-guru.store'), [
        'guru_id' => $guru->id, 'hari' => now()->dayOfWeek, 'semester_id' => $semester->id,
    ]);

    $response->assertRedirect();
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->count())->toBeGreaterThan(0);
});

it('lembaga SUDAH punya PiketHarian -- tambah baris baru tetap memicu Regenerate, bukan Generate ulang dari nol', function () {
    ['lembaga' => $lembaga, 'semester' => $semester, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketKelola();
    $guruLama = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jadwalLama = JadwalPiketMingguan::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guruLama->id, 'hari' => now()->dayOfWeek,
        'semester_id' => $semester->id, 'dibuat_oleh_user_id' => $admin->id,
    ]);
    $tanggalOverride = now()->addWeek()->toDateString();
    PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guruLama->id, 'tanggal' => $tanggalOverride, 'sumber' => 'override_manual']);

    $guruBaru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $response = $this->actingAs($admin)->post(route('admin.piket-guru.store'), [
        'guru_id' => $guruBaru->id, 'hari' => now()->addDay()->dayOfWeek, 'semester_id' => $semester->id,
    ]);

    $response->assertRedirect();
    // Baris override_manual yang sudah ada TIDAK boleh hilang -- bukti bahwa jalur yg dipanggil adalah
    // Regenerate (yang melindungi baris beku), bukan Generate murni dari nol yg mengabaikan data lama.
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guruLama->id)->where('tanggal', $tanggalOverride)->where('sumber', 'override_manual')->exists())->toBeTrue();
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guruBaru->id)->exists())->toBeTrue();
});

it('user tanpa permission piket.kelola ditolak akses', function () {
    ['admin' => $admin] = siapkanAdminPiketKelola();
    $userBiasa = User::factory()->create(['lembaga_id' => $admin->lembaga_id]);

    $response = $this->actingAs($userBiasa)->get(route('admin.piket-guru.index'));

    $response->assertForbidden();
});
```

- [x] **Step 1: Tulis test**
- [x] **Step 2: Jalankan test, pastikan gagal**
- [x] **Step 3: Tambah route**
- [x] **Step 4: Tulis controller**
- [x] **Step 5: Tulis view minimal (index, create, edit)**
- [x] **Step 6: Jalankan test, pastikan lulus**
- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/JadwalPiketMingguanController.php resources/views/portals/lembaga/akademik/piket-guru/ routes/admin/akademik-master.php tests/Feature/Admin/JadwalPiketMingguanControllerTest.php
git commit -m "feat(akademik): admin CRUD JadwalPiketMingguan -- kriteria pasti generate vs regenerate, sinkron

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 10: Admin Override Manual `PiketHarian` Per-Tanggal

**Files:**
- Create: `app/Http/Controllers/Admin/PiketHarianController.php`
- Create: `resources/views/portals/lembaga/akademik/piket-guru/harian.blade.php`
- Modify: `routes/admin/akademik-master.php`
- Test: `tests/Feature/Admin/PiketHarianControllerTest.php`

**Interfaces:**
- Consumes: `PiketHarian` (Task 1).
- Produces: endpoint create/update/delete override manual — dipakai langsung dari view, tidak dipakai task lain.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\PiketHarian;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function siapkanAdminPiketHarianTest(): array
{
    Permission::firstOrCreate(['name' => 'piket.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_piket_harian_test', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('piket.kelola');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruLembagaLain = Guru::factory()->create(['lembaga_id' => $lembagaLain->id]);

    return compact('lembaga', 'guru', 'admin', 'lembagaLain', 'guruLembagaLain');
}

it('admin bisa buat override manual PiketHarian', function () {
    ['lembaga' => $lembaga, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketHarianTest();

    $response = $this->actingAs($admin)->post(route('admin.piket-harian.store'), [
        'guru_id' => $guru->id, 'tanggal' => now()->addDays(3)->toDateString(),
    ]);

    $response->assertRedirect();
    expect(PiketHarian::where('lembaga_id', $lembaga->id)->where('guru_id', $guru->id)->where('sumber', 'override_manual')->exists())->toBeTrue();
});

it('admin lembaga A TIDAK BISA buat override manual utk guru lembaga B', function () {
    ['admin' => $admin, 'guruLembagaLain' => $guruLembagaLain] = siapkanAdminPiketHarianTest();

    $response = $this->actingAs($admin)->post(route('admin.piket-harian.store'), [
        'guru_id' => $guruLembagaLain->id, 'tanggal' => now()->addDays(3)->toDateString(),
    ]);

    $response->assertSessionHasErrors();
    expect(PiketHarian::where('guru_id', $guruLembagaLain->id)->exists())->toBeFalse();
});

it('admin bisa hapus baris PiketHarian', function () {
    ['lembaga' => $lembaga, 'guru' => $guru, 'admin' => $admin] = siapkanAdminPiketHarianTest();
    $piket = PiketHarian::create(['lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->addDays(3)->toDateString(), 'sumber' => 'override_manual']);

    $response = $this->actingAs($admin)->delete(route('admin.piket-harian.destroy', $piket));

    $response->assertRedirect();
    expect(PiketHarian::find($piket->id))->toBeNull();
});
```

- [x] **Step 1: Tulis test yang gagal**
- [x] **Step 2: Jalankan test, pastikan gagal**
- [x] **Step 3: Tambah route**
- [x] **Step 4: Tulis controller**
- [x] **Step 5: Tambah link ke halaman override manual di view `piket-guru/index.blade.php`**
- [x] **Step 6: Jalankan test, pastikan lulus**
- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/PiketHarianController.php routes/admin/akademik-master.php tests/Feature/Admin/PiketHarianControllerTest.php
git commit -m "feat(akademik): admin override manual PiketHarian per-tanggal -- validasi tenant-safety

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 11: Penutup — Full Test Suite & Dokumentasi

**Files:**
- Create: `.agents/logs/2026-09-06-guru-piket-jurnal-kbm.md`
- Modify: `PETA_PENGEMBANGAN.md`

- [x] **Step 1: Pastikan tidak ada proses test lain berjalan**
- [x] **Step 2: Full test suite**
- [x] **Step 3: Pint**
- [x] **Step 4: Tulis handoff log**
- [x] **Step 5: Update `PETA_PENGEMBANGAN.md`**
- [x] **Step 6: Commit**

```bash
git add .agents/logs/2026-09-06-guru-piket-jurnal-kbm.md PETA_PENGEMBANGAN.md
git commit -m "docs(akademik): handoff log & update roadmap -- guru piket fase 1 selesai

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Cakupan spec**: §2 (11 poin keputusan) semua tercermin — poin 1 (2 fase, Fase 2 di §5 setiap task), poin 2 (Task 1, 2 lapis data), poin 3 (Task 4 PiketAccessChecker + Task 2 fix tanggal mulai), poin 4 (Task 2, skip libur via KalenderAkademikResolver + batch sekali), poin 5 (Task 3, 3 langkah bernomor + 3 aturan beku), poin 6 (TIDAK ada task untuk observer KalenderAkademik, sesuai — diterima sbg keterbatasan), poin 7 (Task 1 + Task 6, kolom akuntabilitas), poin 8 (Task 8, permission piket.kelola), poin 9 (Task 5, 1 helper 2 titik panggil), poin 10 (Task 6, parameter opsional RecordJurnalDanPresensiAction), poin 11 (Task 7, seksi index()). §3.1-3.4 masing-masing dapat task sendiri (Task 1 / Task 2-3 / Task 4-6 / Task 9-10). Ke-13 skenario §4 tercakup: #1 di Task 4, #2 di Task 3, #3 di Task 2, #4-5 di Task 6, #6-7 di Task 7, #8-9-10 di Task 9 & 2, #11 di Task 6, #12 di Task 5 (implisit lewat resolveKartu tetap pakai authorizeMilikGuru yg sudah di-wire), #13 di Task 9.

**2. Placeholder scan**: semua step berisi kode lengkap. Beberapa catatan "PERIKSA LANGSUNG"/"WAJIB baca dulu" adalah instruksi verifikasi eksplisit terhadap file yang sudah berkali-kali berubah sesi-sesi sebelumnya (JurnalKbmController, RecordJurnalDanPresensiAction) — bukan placeholder, konsisten dengan disiplin "verifikasi sebelum menulis" yang dipakai di semua plan sesi ini.

**3. Konsistensi tipe**: `GenerateJadwalPiketHarianAction::execute(JadwalPiketMingguan $jadwal): void` (Task 2) dipakai identik di Task 3 (dependency constructor) dan Task 9 (`store()`). `RegenerateJadwalPiketHarianAction::execute(int $lembagaId, int $semesterId): void` (Task 3) dipakai identik di Task 9 (`store()`, `update()`, `destroy()`). `PiketAccessChecker::bisaAkses(SesiPembelajaran $sesi, Guru $guru): bool` (Task 4) dipakai identik di Task 5 (2 titik). `RecordJurnalDanPresensiAction::execute(SesiPembelajaran $sesi, JurnalPresensiData $data, ?int $diisiOlehGuruId = null)` (Task 6) — signature baru konsisten dgn satu-satunya titik panggil (`JurnalKbmController::update()`).

**Koreksi yang ditemukan & diperbaiki saat plan ditulis** (dicatat eksplisit, bukan disembunyikan): Task 2 awalnya men-generate `PiketHarian` dari `semester->tanggal_mulai` — kalau `JadwalPiketMingguan` dibuat di tengah semester, ini akan membuat baris untuk tanggal LAMPAU, bertentangan dengan prinsip "wewenang piket hanya hari ini" (spec §2 poin 3) dan berisiko diam-diam memberi akses piket ke sesi lama lewat `PiketAccessChecker` (yang sengaja cuma cek kecocokan tabel, tidak cek `tanggal = hari ini`, sesuai kode yang disetujui di spec). Diperbaiki: generate mulai dari `max(hari ini, semester->tanggal_mulai)`. Test Task 2 juga disesuaikan dari tanggal hardcoded (Januari 2026, sudah lewat) jadi tanggal relatif ke `now()`.
