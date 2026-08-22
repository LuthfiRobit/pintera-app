# Kehadiran SDM — Item Tertunda Sub-project 3: Penugasan Shift per Periode — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bangun penugasan shift manual per periode ke pegawai individu (`jenis_shift` template + `penugasan_shift` penugasan), dengan validasi anti-tumpang-tindih, dan `ShiftAwareAttendanceResolver` yang membungkus `AttendancePolicyResolver` (Sub-project 3) TANPA mengubahnya, supaya shift aktif menang atas Policy untuk jam & hari kerja, tapi toleransi tetap dari Policy.

**Architecture:** Domain `App\Domains\Sdm\` diperluas lagi: `Models\JenisShift`, `Models\PenugasanShift`, `Services\ShiftAwareAttendanceResolver`, `Actions\AssignShiftAction`, `Exceptions\ShiftAssignmentOverlapException`. `RecordManualAttendanceAction`, `ScanQrAttendanceAction`, `AttendanceRecordAggregator`, `TandaiAlpaOtomatisSdm` ganti dependency dari `AttendancePolicyResolver` langsung jadi `ShiftAwareAttendanceResolver` (pola swap yang sama 2x sudah dipraktikkan sebelumnya).

**Tech Stack:** Laravel 11, PHP 8.2+, Pest (test), Tailwind + Alpine.js, Tom Select — sama seperti Sub-project 1-3.

## Global Constraints

- Branch kerja: `sdm-v1`. JANGAN buat branch baru, JANGAN buat worktree.
- Baseline: commit `dc7cfc5` di branch `sdm-v1` (spec item ini baru dikomit). Kalau ada commit baru masuk sebelum eksekusi, verifikasi ulang file yang dikutip plan ini sebelum melanjutkan — terutama `app/Domains/Sdm/Actions/RecordManualAttendanceAction.php`, `app/Domains/Sdm/Actions/ScanQrAttendanceAction.php`, `app/Domains/Sdm/Services/AttendanceRecordAggregator.php`, `app/Console/Commands/TandaiAlpaOtomatisSdm.php`, `app/Http/Controllers/Admin/AttendanceConfigurationController.php`, `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`, `resources/js/app.js`.
- Spec lengkap: `.agents/specs/2026-08-22-sdm-03b-shift-bergilir.md` — baca dulu untuk "kenapa", plan ini "bagaimana"-nya.
- **`App\Domains\Sdm\Services\AttendancePolicyResolver.php` dan `App\Domains\Sdm\Services\KalenderKerjaSdmResolver.php` TIDAK BOLEH diubah/disentuh SAMA SEKALI** di plan ini. `ShiftAwareAttendanceResolver` (Task 3) MEMBUNGKUS `AttendancePolicyResolver` lewat constructor injection.
- **`PenugasanShift` model WAJIB `BelongsToTenant` DENGAN `lembaga_id` WAJIB TERISI** (bukan nullable — beda dari `AttendanceMethodConfiguration`/`KalenderKerjaSdm`/`AttendancePolicy` yang punya baris nasional). `JenisShift` TETAP pakai pola nasional+lembaga-override seperti biasa (`lembaga_id` nullable, WAJIB bypass `withoutGlobalScope(TenantScope::class)` untuk listing gabungan).
- Validasi tumpang-tindih tanggal WAJIB di level Action (`AssignShiftAction`), bukan cuma DB constraint — tidak ada unique index yang bisa menangkap overlap rentang tanggal secara native di MySQL untuk kasus ini.
- Ikuti pola UI Tom Select untuk pemilih pegawai (guru/karyawan) — WAJIB reuse pendekatan `resources/js/attendance-manual-form.js` (buat modul JS baru serupa, JANGAN native `<select>` polos untuk daftar pegawai yang bisa puluhan).
- TIDAK ADA hardcode nama role apapun.
- TIDAK membangun rotasi otomatis — di luar cakupan total plan ini.
- Testing policy: test scoped per task, dijalankan SEBELUM commit setiap task. Full suite HANYA di Task 9, dan HANYA setelah izin eksplisit user.
- Satu commit per task, pesan commit sesuai yang ditentukan di tiap task Step terakhir.
- Test framework: Pest, gaya `it('...', function () { ... })`.

---

## Task 1: Migrasi (`jenis_shift` + `penugasan_shift`)

**Files:**
- Create: `database/migrations/2026_08_22_120000_create_jenis_shift_table.php`
- Create: `database/migrations/2026_08_22_120100_create_penugasan_shift_table.php`

**Interfaces:**
- Produces: tabel `jenis_shift`, `penugasan_shift` — dipakai Task 2 dst.

- [ ] **Step 1: Buat migrasi `jenis_shift`**

```php
<?php
// database/migrations/2026_08_22_120000_create_jenis_shift_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yayasan_id')->constrained('yayasan')->cascadeOnDelete();
            $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
            $table->string('nama');
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_shift');
    }
};
```

- [ ] **Step 2: Buat migrasi `penugasan_shift`**

```php
<?php
// database/migrations/2026_08_22_120100_create_penugasan_shift_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penugasan_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->morphs('pegawai');
            $table->foreignId('jenis_shift_id')->constrained('jenis_shift')->cascadeOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->json('hari_kerja')->nullable();
            $table->timestamps();

            $table->index(['pegawai_type', 'pegawai_id', 'tanggal_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_shift');
    }
};
```

- [ ] **Step 3: Jalankan migrasi dan verifikasi**

Run: `php artisan migrate`
Expected: 2 migrasi baru berjalan sukses.

Run: `php artisan migrate:status | grep shift`
Expected: kedua tabel `Ran`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_22_120000_create_jenis_shift_table.php database/migrations/2026_08_22_120100_create_penugasan_shift_table.php
git commit -m "feat(sdm): migrasi tabel jenis_shift + penugasan_shift"
```

---

## Task 2: Model `JenisShift` + `PenugasanShift` + `AssignShiftAction`

**Files:**
- Create: `app/Domains/Sdm/Models/JenisShift.php`
- Create: `app/Domains/Sdm/Models/PenugasanShift.php`
- Create: `app/Domains/Sdm/DataTransferObjects/ShiftAssignmentData.php`
- Create: `app/Domains/Sdm/Exceptions/ShiftAssignmentOverlapException.php`
- Create: `app/Domains/Sdm/Actions/AssignShiftAction.php`
- Test: `tests/Feature/Sdm/AssignShiftActionTest.php`

**Interfaces:**
- Produces: `JenisShift::create([...])`; `AssignShiftAction::execute(Model $pegawai, ShiftAssignmentData $data, ?int $excludingId = null): PenugasanShift` (throws `ShiftAssignmentOverlapException`) — dipakai Task 3 dst (model), Task 7 (Action, controller).

- [ ] **Step 1: Buat model `JenisShift`**

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JenisShift extends Model
{
    use BelongsToTenant;

    protected $table = 'jenis_shift';

    protected $fillable = ['yayasan_id', 'lembaga_id', 'nama', 'jam_masuk', 'jam_pulang'];

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
```

Catatan: `jam_masuk`/`jam_pulang` SENGAJA TIDAK di-cast (tetap string mentah `TIME`) — alasan sama seperti `AttendancePolicy` Sub-project 3.

- [ ] **Step 2: Buat model `PenugasanShift`**

```php
<?php

namespace App\Domains\Sdm\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PenugasanShift extends Model
{
    use BelongsToTenant;

    protected $table = 'penugasan_shift';

    protected $fillable = ['lembaga_id', 'pegawai_type', 'pegawai_id', 'jenis_shift_id', 'tanggal_mulai', 'tanggal_selesai', 'hari_kerja'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'hari_kerja' => 'array',
        ];
    }

    public function pegawai(): MorphTo
    {
        return $this->morphTo();
    }

    public function jenisShift(): BelongsTo
    {
        return $this->belongsTo(JenisShift::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
```

- [ ] **Step 3: Buat DTO `ShiftAssignmentData`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\DataTransferObjects;

final readonly class ShiftAssignmentData
{
    public function __construct(
        public int $lembagaId,
        public int $jenisShiftId,
        public string $tanggalMulai,
        public ?string $tanggalSelesai = null,
        public ?array $hariKerja = null,
    ) {}
}
```

- [ ] **Step 4: Buat `ShiftAssignmentOverlapException`**

```php
<?php

namespace App\Domains\Sdm\Exceptions;

use RuntimeException;

class ShiftAssignmentOverlapException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Pegawai ini sudah punya penugasan shift lain yang tumpang tindih dengan rentang tanggal ini.');
    }
}
```

- [ ] **Step 5: Buat `AssignShiftAction`**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Actions;

use App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData;
use App\Domains\Sdm\Exceptions\ShiftAssignmentOverlapException;
use App\Domains\Sdm\Models\PenugasanShift;
use Illuminate\Database\Eloquent\Model;

final class AssignShiftAction
{
    public function execute(Model $pegawai, ShiftAssignmentData $data, ?int $excludingId = null): PenugasanShift
    {
        $tumpangTindih = PenugasanShift::where('pegawai_type', $pegawai::class)
            ->where('pegawai_id', $pegawai->id)
            ->when($excludingId, fn ($q) => $q->where('id', '!=', $excludingId))
            ->where('tanggal_mulai', '<=', $data->tanggalSelesai ?? '9999-12-31')
            ->where(function ($q) use ($data) {
                $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $data->tanggalMulai);
            })
            ->exists();

        if ($tumpangTindih) {
            throw new ShiftAssignmentOverlapException();
        }

        $payload = [
            'lembaga_id' => $data->lembagaId,
            'jenis_shift_id' => $data->jenisShiftId,
            'tanggal_mulai' => $data->tanggalMulai,
            'tanggal_selesai' => $data->tanggalSelesai,
            'hari_kerja' => $data->hariKerja,
        ];

        if ($excludingId) {
            $penugasan = PenugasanShift::findOrFail($excludingId);
            $penugasan->update($payload);

            return $penugasan->fresh();
        }

        return $pegawai->penugasanShift()->create($payload);
    }
}
```

Catatan: Action ini memanggil `$pegawai->penugasanShift()` — relasi morph BARU yang HARUS ditambahkan ke `Guru`/`Karyawan` (lihat Step 6-7 di bawah), MIRIP pola `attendanceEvents()` dkk dari Sub-project 1.

- [ ] **Step 6: Tambah relasi `penugasanShift()` di `app/Models/Guru.php`**

Cari baris `use` di Guru.php (blok import setelah `use App\Domains\Sdm\Models\EmployeeQrCode;`), tambahkan:

```php
use App\Domains\Sdm\Models\PenugasanShift;
```

Tambahkan method baru setelah `employeeQrCode()`:

```php
    public function penugasanShift(): MorphMany
    {
        return $this->morphMany(PenugasanShift::class, 'pegawai');
    }
```

- [ ] **Step 7: Tambah relasi `penugasanShift()` di `app/Models/Karyawan.php`**

Sama polanya — tambahkan `use App\Domains\Sdm\Models\PenugasanShift;` dan method `penugasanShift(): MorphMany` setelah `employeeQrCode()`.

- [ ] **Step 8: Tulis test**

```php
<?php
// tests/Feature/Sdm/AssignShiftActionTest.php

use App\Domains\Sdm\Actions\AssignShiftAction;
use App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData;
use App\Domains\Sdm\Models\JenisShift;
use App\Domains\Sdm\Models\PenugasanShift;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('creates a shift assignment for a guru with no existing overlap', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);

    $penugasan = app(AssignShiftAction::class)->execute($guru, new ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: '2026-09-07',
    ));

    expect($penugasan->pegawai_type)->toBe(Guru::class);
    expect($penugasan->pegawai_id)->toBe($guru->id);
});

it('rejects a new assignment overlapping an existing one for the same pegawai', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $action = app(AssignShiftAction::class);
    $action->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: '2026-09-07'));

    expect(fn () => $action->execute($guru, new ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-05', tanggalSelesai: '2026-09-10',
    )))->toThrow(\App\Domains\Sdm\Exceptions\ShiftAssignmentOverlapException::class);

    expect(PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);
});

it('rejects an overlap when the existing assignment has no end date (open-ended)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $action = app(AssignShiftAction::class);
    $action->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: null));

    expect(fn () => $action->execute($guru, new ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-12-01', tanggalSelesai: '2026-12-07',
    )))->toThrow(\App\Domains\Sdm\Exceptions\ShiftAssignmentOverlapException::class);
});

it('allows back-to-back assignments that do not actually overlap', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $action = app(AssignShiftAction::class);
    $action->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: '2026-09-07'));

    $kedua = $action->execute($guru, new ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-08', tanggalSelesai: '2026-09-14',
    ));

    expect($kedua)->not->toBeNull();
    expect(PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(2);
});

it('allows overlapping date ranges for two different pegawai', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $guruB = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $action = app(AssignShiftAction::class);
    $action->execute($guruA, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: '2026-09-07'));

    $penugasan = $action->execute($guruB, new ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: '2026-09-07',
    ));

    expect($penugasan)->not->toBeNull();
});

it('excludes itself from the overlap check when updating via excludingId', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $action = app(AssignShiftAction::class);
    $penugasan = $action->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-01', tanggalSelesai: '2026-09-07'));

    $diperbarui = $action->execute($guru, new ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-09-02', tanggalSelesai: '2026-09-08',
    ), excludingId: $penugasan->id);

    expect($diperbarui->id)->toBe($penugasan->id);
    expect($diperbarui->tanggal_selesai->toDateString())->toBe('2026-09-08');
    expect(PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);
});
```

- [ ] **Step 9: Jalankan test**

Run: `php artisan test tests/Feature/Sdm/AssignShiftActionTest.php`
Expected: 6 passed, 0 failed.

- [ ] **Step 10: Commit**

```bash
git add app/Domains/Sdm/Models/JenisShift.php app/Domains/Sdm/Models/PenugasanShift.php app/Domains/Sdm/DataTransferObjects/ShiftAssignmentData.php app/Domains/Sdm/Exceptions/ShiftAssignmentOverlapException.php app/Domains/Sdm/Actions/AssignShiftAction.php app/Models/Guru.php app/Models/Karyawan.php tests/Feature/Sdm/AssignShiftActionTest.php
git commit -m "feat(sdm): tambah model JenisShift/PenugasanShift dan AssignShiftAction dengan validasi anti-tumpang-tindih"
```

---

## Task 3: `ShiftAwareAttendanceResolver`

**Files:**
- Create: `app/Domains/Sdm/Services/ShiftAwareAttendanceResolver.php`
- Test: `tests/Unit/Services/ShiftAwareAttendanceResolverTest.php`

**Interfaces:**
- Consumes: `PenugasanShift` (Task 2), `AttendancePolicyResolver` (Sub-project 3, TIDAK diubah).
- Produces: `ShiftAwareAttendanceResolver::resolveLibur(Model $pegawai, CarbonInterface $tanggal): array{libur, alasan}`; `ShiftAwareAttendanceResolver::resolveJamKerjaEfektif(Model $pegawai, CarbonInterface $tanggal): ?array{jam_masuk, toleransi_menit}` — dipakai Task 4, 5, 6.

- [ ] **Step 1: Buat `ShiftAwareAttendanceResolver`**

```php
<?php

namespace App\Domains\Sdm\Services;

use App\Domains\Sdm\Models\PenugasanShift;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class ShiftAwareAttendanceResolver
{
    public function __construct(private readonly AttendancePolicyResolver $policyResolver) {}

    /**
     * @return array{libur: bool, alasan: string}
     */
    public function resolveLibur(Model $pegawai, CarbonInterface $tanggal): array
    {
        $shift = $this->cariPenugasanAktif($pegawai, $tanggal);

        if (! $shift) {
            return $this->policyResolver->resolveLibur($pegawai, $tanggal);
        }

        $nama = $shift->jenisShift->nama;

        if ($shift->hari_kerja === null) {
            return ['libur' => false, 'alasan' => 'Hari kerja sesuai jadwal shift '.$nama];
        }

        $adalahHariKerja = in_array($tanggal->dayOfWeek, $shift->hari_kerja, true);

        return $adalahHariKerja
            ? ['libur' => false, 'alasan' => 'Hari kerja sesuai jadwal shift '.$nama]
            : ['libur' => true, 'alasan' => 'Libur sesuai jadwal shift '.$nama];
    }

    /**
     * @return array{jam_masuk: string, toleransi_menit: int}|null
     */
    public function resolveJamKerjaEfektif(Model $pegawai, CarbonInterface $tanggal): ?array
    {
        $shift = $this->cariPenugasanAktif($pegawai, $tanggal);

        if ($shift) {
            $toleransi = $this->policyResolver->resolvePolicy($pegawai)?->toleransi_menit ?? 0;

            return ['jam_masuk' => $shift->jenisShift->jam_masuk, 'toleransi_menit' => $toleransi];
        }

        $policy = $this->policyResolver->resolvePolicy($pegawai);

        if (! $policy) {
            return null;
        }

        return ['jam_masuk' => $policy->jam_masuk, 'toleransi_menit' => $policy->toleransi_menit];
    }

    private function cariPenugasanAktif(Model $pegawai, CarbonInterface $tanggal): ?PenugasanShift
    {
        $tgl = $tanggal->toDateString();

        return PenugasanShift::where('pegawai_type', $pegawai::class)
            ->where('pegawai_id', $pegawai->id)
            ->where('tanggal_mulai', '<=', $tgl)
            ->where(fn ($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tgl))
            ->with('jenisShift')
            ->first();
    }
}
```

- [ ] **Step 2: Tulis test**

```php
<?php
// tests/Unit/Services/ShiftAwareAttendanceResolverTest.php

use App\Domains\Sdm\Actions\AssignShiftAction;
use App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData;
use App\Domains\Sdm\Models\AttendancePolicy;
use App\Domains\Sdm\Models\JenisShift;
use App\Domains\Sdm\Services\ShiftAwareAttendanceResolver;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Carbon\Carbon;

it('resolveLibur delegates fully to AttendancePolicyResolver when no shift assignment is active', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);

    $result = app(ShiftAwareAttendanceResolver::class)->resolveLibur($guru, Carbon::parse('2026-08-19')); // Wednesday

    expect($result)->toBe(['libur' => false, 'alasan' => 'Hari kerja efektif']);
});

it('resolveLibur treats every day in range as a work day when the shift has no hari_kerja override', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Malam', 'jam_masuk' => '22:00', 'jam_pulang' => '06:00']);
    app(AssignShiftAction::class)->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-08-17', tanggalSelesai: '2026-08-23'));

    $result = app(ShiftAwareAttendanceResolver::class)->resolveLibur($guru, Carbon::parse('2026-08-23')); // Sunday, but shift active

    expect($result)->toBe(['libur' => false, 'alasan' => 'Hari kerja sesuai jadwal shift Shift Malam']);
});

it('resolveLibur respects the shift hari_kerja override when set', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Malam', 'jam_masuk' => '22:00', 'jam_pulang' => '06:00']);
    app(AssignShiftAction::class)->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-08-17', tanggalSelesai: '2026-08-23', hariKerja: [1, 2, 3]));

    $result = app(ShiftAwareAttendanceResolver::class)->resolveLibur($guru, Carbon::parse('2026-08-20')); // Thursday, not in [1,2,3]

    expect($result)->toBe(['libur' => true, 'alasan' => 'Libur sesuai jadwal shift Shift Malam']);
});

it('resolveJamKerjaEfektif uses the shift jam_masuk with toleransi 0 when the pegawai has no AttendancePolicy at all', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    app(AssignShiftAction::class)->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-08-17', tanggalSelesai: '2026-08-23'));

    $result = app(ShiftAwareAttendanceResolver::class)->resolveJamKerjaEfektif($guru, Carbon::parse('2026-08-19'));

    expect($result)->toBe(['jam_masuk' => '06:00:00', 'toleransi_menit' => 0]);
});

it('resolveJamKerjaEfektif combines the shift jam_masuk with the pegawai AttendancePolicy toleransi when a Policy exists', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas']);
    AttendancePolicy::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_kelas', 'jam_masuk' => '07:00', 'toleransi_menit' => 20]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    app(AssignShiftAction::class)->execute($guru, new ShiftAssignmentData(lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-08-17', tanggalSelesai: '2026-08-23'));

    $result = app(ShiftAwareAttendanceResolver::class)->resolveJamKerjaEfektif($guru, Carbon::parse('2026-08-19'));

    expect($result)->toBe(['jam_masuk' => '06:00:00', 'toleransi_menit' => 20]);
});

it('resolveJamKerjaEfektif returns null when there is no shift and no policy (fail-safe unchanged)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);

    $result = app(ShiftAwareAttendanceResolver::class)->resolveJamKerjaEfektif($guru, Carbon::parse('2026-08-19'));

    expect($result)->toBeNull();
});
```

- [ ] **Step 3: Jalankan test**

Run: `php artisan test tests/Unit/Services/ShiftAwareAttendanceResolverTest.php`
Expected: 6 passed, 0 failed.

- [ ] **Step 4: Commit**

```bash
git add app/Domains/Sdm/Services/ShiftAwareAttendanceResolver.php tests/Unit/Services/ShiftAwareAttendanceResolverTest.php
git commit -m "feat(sdm): tambah ShiftAwareAttendanceResolver (membungkus AttendancePolicyResolver tanpa mengubahnya)"
```

---

## Task 4: Ganti Dependency di `RecordManualAttendanceAction`/`ScanQrAttendanceAction`

**Files:**
- Modify: `app/Domains/Sdm/Actions/RecordManualAttendanceAction.php`
- Modify: `app/Domains/Sdm/Actions/ScanQrAttendanceAction.php`
- Test: `tests/Feature/Sdm/RecordManualAttendanceActionTest.php`
- Test: `tests/Feature/Sdm/ScanQrAttendanceActionTest.php`

**Interfaces:**
- Consumes: `ShiftAwareAttendanceResolver` (Task 3).

- [ ] **Step 1: Ganti dependency di `RecordManualAttendanceAction`**

Baca dulu isi file saat ini. Cari baris:

```php
use App\Domains\Sdm\Services\AttendancePolicyResolver;
```

Ganti jadi:

```php
use App\Domains\Sdm\Services\ShiftAwareAttendanceResolver;
```

Cari baris:

```php
    public function __construct(
        private readonly AttendanceRecordAggregator $aggregator,
        private readonly AttendancePolicyResolver $policyResolver,
    ) {}

    public function execute(Model $pegawai, RecordManualAttendanceData $data): AttendanceEvent
    {
        $resolusi = $this->policyResolver->resolveLibur($pegawai, $data->waktu);
```

Ganti jadi:

```php
    public function __construct(
        private readonly AttendanceRecordAggregator $aggregator,
        private readonly ShiftAwareAttendanceResolver $resolver,
    ) {}

    public function execute(Model $pegawai, RecordManualAttendanceData $data): AttendanceEvent
    {
        $resolusi = $this->resolver->resolveLibur($pegawai, $data->waktu);
```

- [ ] **Step 2: Ganti dependency di `ScanQrAttendanceAction`**

Pola SAMA — ganti `use App\Domains\Sdm\Services\AttendancePolicyResolver;` jadi `use App\Domains\Sdm\Services\ShiftAwareAttendanceResolver;`, ganti constructor param `AttendancePolicyResolver $policyResolver` jadi `ShiftAwareAttendanceResolver $resolver`, dan ganti baris `$resolusi = $this->policyResolver->resolveLibur($pegawai, now());` jadi `$resolusi = $this->resolver->resolveLibur($pegawai, now());`.

- [ ] **Step 3: Jalankan ulang SEMUA test existing kedua Action untuk pastikan tidak regresi**

Run: `php artisan test tests/Feature/Sdm/RecordManualAttendanceActionTest.php tests/Feature/Sdm/ScanQrAttendanceActionTest.php`
Expected: SEMUA test lama tetap passed (6 di `RecordManualAttendanceActionTest`, 4 di `ScanQrAttendanceActionTest` dari Sub-project 1-3), 0 failed — TIDAK ADA perubahan perilaku untuk pegawai TANPA penugasan shift (delegasi penuh ke `AttendancePolicyResolver` menjamin ini).

- [ ] **Step 4: Tambah 1 test baru di akhir `tests/Feature/Sdm/RecordManualAttendanceActionTest.php` membuktikan shift override memengaruhi hasil**

```php
it('allows a manual attendance record on a lembaga-libur day when the pegawai has an active shift assignment covering that day', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = \App\Domains\Sdm\Models\JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Malam', 'jam_masuk' => '22:00', 'jam_pulang' => '06:00']);
    app(\App\Domains\Sdm\Actions\AssignShiftAction::class)->execute($guru, new \App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-08-17', tanggalSelesai: '2026-08-30',
    ));
    $action = app(\App\Domains\Sdm\Actions\RecordManualAttendanceAction::class);

    $event = $action->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-23 22:05:00'), dicatatOlehUserId: $admin->id, // Sunday, but shift active
    ));

    expect($event->arah)->toBe('masuk');
    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});
```

- [ ] **Step 5: Jalankan lagi seluruh file test Action manual**

Run: `php artisan test tests/Feature/Sdm/RecordManualAttendanceActionTest.php`
Expected: 7 passed, 0 failed.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Sdm/Actions/RecordManualAttendanceAction.php app/Domains/Sdm/Actions/ScanQrAttendanceAction.php tests/Feature/Sdm/RecordManualAttendanceActionTest.php
git commit -m "feat(sdm): ganti sumber resolusi hari libur di RecordManualAttendanceAction/ScanQrAttendanceAction ke ShiftAwareAttendanceResolver"
```

---

## Task 5: Ganti Dependency di `AttendanceRecordAggregator`

**Files:**
- Modify: `app/Domains/Sdm/Services/AttendanceRecordAggregator.php`
- Test: `tests/Feature/Sdm/AttendanceRecordAggregatorLateDetectionTest.php`

**Interfaces:**
- Consumes: `ShiftAwareAttendanceResolver` (Task 3).

- [ ] **Step 1: Ganti dependency di `AttendanceRecordAggregator`**

Baca dulu isi file saat ini. Ganti SELURUH isinya jadi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Sdm\Services;

use App\Domains\Sdm\Enums\AttendanceStatus;
use App\Domains\Sdm\Models\AttendanceRecord;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecordAggregator
{
    public function __construct(private readonly ShiftAwareAttendanceResolver $resolver) {}

    public function sync(Model $pegawai, CarbonImmutable $tanggal): AttendanceRecord
    {
        $events = $pegawai->attendanceEvents()
            ->whereDate('waktu', $tanggal->toDateString())
            ->orderBy('waktu')
            ->get();

        $waktuMasuk = $events->firstWhere('arah', 'masuk')?->waktu;
        $waktuPulang = $events->where('arah', 'pulang')->last()?->waktu;

        $statusOverride = $events->first(fn ($event) => $event->status !== AttendanceStatus::Hadir);
        $status = $statusOverride?->status ?? AttendanceStatus::Hadir;

        [$isLate, $lateMinutes] = $this->hitungKeterlambatan($pegawai, $tanggal, $waktuMasuk);

        return AttendanceRecord::updateOrCreate(
            [
                'pegawai_type' => $pegawai::class,
                'pegawai_id' => $pegawai->id,
                'tanggal' => $tanggal->toDateString(),
            ],
            [
                'lembaga_id' => $pegawai->lembaga_id,
                'status' => $status,
                'waktu_masuk' => $waktuMasuk,
                'waktu_pulang' => $waktuPulang,
                'is_late' => $isLate,
                'late_minutes' => $lateMinutes,
            ]
        );
    }

    /**
     * @return array{0: bool, 1: int|null}
     */
    private function hitungKeterlambatan(Model $pegawai, CarbonInterface $tanggal, ?CarbonInterface $waktuMasuk): array
    {
        if (! $waktuMasuk) {
            return [false, null];
        }

        $jamKerja = $this->resolver->resolveJamKerjaEfektif($pegawai, $tanggal);

        if (! $jamKerja) {
            return [false, null];
        }

        $batasWaktu = CarbonImmutable::parse($tanggal->toDateString().' '.$jamKerja['jam_masuk'])->addMinutes($jamKerja['toleransi_menit']);

        if ($waktuMasuk->lessThanOrEqualTo($batasWaktu)) {
            return [false, 0];
        }

        return [true, $batasWaktu->diffInMinutes($waktuMasuk)];
    }
}
```

- [ ] **Step 2: Jalankan ulang SEMUA test existing yang menyentuh Aggregator untuk pastikan tidak regresi**

Run: `php artisan test tests/Feature/Sdm/RecordManualAttendanceActionTest.php tests/Feature/Sdm/ScanQrAttendanceActionTest.php tests/Feature/Sdm/AttendanceRecordAggregatorLateDetectionTest.php tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`
Expected: semua tetap passed seperti sebelumnya, 0 failed.

- [ ] **Step 3: Tambah 1 test baru di akhir `tests/Feature/Sdm/AttendanceRecordAggregatorLateDetectionTest.php`**

```php
it('marks is_late true with toleransi 0 when a shift-assigned pegawai with no policy arrives after the shift jam_masuk', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'jenis_ptk' => 'guru_bk']);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = \App\Domains\Sdm\Models\JenisShift::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi',
        'jam_masuk' => '06:00', 'jam_pulang' => '14:00',
    ]);
    app(\App\Domains\Sdm\Actions\AssignShiftAction::class)->execute($guru, new \App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-08-24', tanggalSelesai: '2026-08-24',
    ));

    app(RecordManualAttendanceAction::class)->execute($guru, new RecordManualAttendanceData(
        lembagaId: $lembaga->id, arah: 'masuk', status: AttendanceStatus::Hadir,
        waktu: CarbonImmutable::parse('2026-08-24 06:10:00'), dicatatOlehUserId: $admin->id, // Monday
    ));

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record->is_late)->toBeTrue();
    expect($record->late_minutes)->toBe(10);
});
```

- [ ] **Step 4: Jalankan test baru**

Run: `php artisan test tests/Feature/Sdm/AttendanceRecordAggregatorLateDetectionTest.php`
Expected: 4 passed, 0 failed.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Sdm/Services/AttendanceRecordAggregator.php tests/Feature/Sdm/AttendanceRecordAggregatorLateDetectionTest.php
git commit -m "feat(sdm): ganti sumber jam kerja+toleransi di AttendanceRecordAggregator ke ShiftAwareAttendanceResolver"
```

---

## Task 6: Ganti Dependency di `TandaiAlpaOtomatisSdm`

**Files:**
- Modify: `app/Console/Commands/TandaiAlpaOtomatisSdm.php`
- Test: `tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`

**Interfaces:**
- Consumes: `ShiftAwareAttendanceResolver` (Task 3).

- [ ] **Step 1: Ganti dependency di `TandaiAlpaOtomatisSdm`**

Cari baris:

```php
use App\Domains\Sdm\Services\AttendancePolicyResolver;
```

Ganti jadi:

```php
use App\Domains\Sdm\Services\ShiftAwareAttendanceResolver;
```

Cari baris:

```php
    public function __construct(
        private readonly AttendancePolicyResolver $policyResolver,
        private readonly AttendanceRecordAggregator $aggregator,
    ) {
        parent::__construct();
    }
```

Ganti jadi:

```php
    public function __construct(
        private readonly ShiftAwareAttendanceResolver $resolver,
        private readonly AttendanceRecordAggregator $aggregator,
    ) {
        parent::__construct();
    }
```

Cari baris (di dalam `handle()`):

```php
                ->filter(fn ($pegawai) => ! $this->policyResolver->resolveLibur($pegawai, $tanggal)['libur']);
```

Ganti jadi:

```php
                ->filter(fn ($pegawai) => ! $this->resolver->resolveLibur($pegawai, $tanggal)['libur']);
```

- [ ] **Step 2: Jalankan ulang SEMUA 8 test existing untuk pastikan tidak regresi**

Run: `php artisan test tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`
Expected: 8 passed, 0 failed.

- [ ] **Step 3: Tambah 1 test baru di akhir file membuktikan penugasan shift memengaruhi auto-alpa**

```php
it('marks a pegawai with an active shift assignment as Alpa on a lembaga-libur day even without an AttendancePolicy hari_kerja override', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 01:00:00')); // Monday, H-1 = Sunday (lembaga libur)
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan_sdm' => [0]]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => 'aktif']);
    $jenisShift = \App\Domains\Sdm\Models\JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Malam', 'jam_masuk' => '22:00', 'jam_pulang' => '06:00']);
    app(\App\Domains\Sdm\Actions\AssignShiftAction::class)->execute($guru, new \App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData(
        lembagaId: $lembaga->id, jenisShiftId: $jenisShift->id, tanggalMulai: '2026-08-17', tanggalSelesai: '2026-08-30',
    ));

    $this->artisan('sdm:tandai-alpa-otomatis')->assertSuccessful();

    $record = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe(AttendanceStatus::Alpa);

    Carbon::setTestNow();
});
```

- [ ] **Step 4: Jalankan seluruh file test (8 lama + 1 baru)**

Run: `php artisan test tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php`
Expected: 9 passed, 0 failed.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/TandaiAlpaOtomatisSdm.php tests/Feature/Sdm/TandaiAlpaOtomatisSdmTest.php
git commit -m "feat(sdm): ganti sumber resolusi TandaiAlpaOtomatisSdm ke ShiftAwareAttendanceResolver"
```

---

## Task 7: CRUD `JenisShift` + `PenugasanShift` di `AttendanceConfigurationController` + Routes + JS

**Files:**
- Modify: `app/Http/Controllers/Admin/AttendanceConfigurationController.php`
- Modify: `routes/admin/kehadiran-sdm.php`
- Create: `resources/js/shift-penugasan-form.js`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/Admin/ShiftControllerTest.php`

**Interfaces:**
- Consumes: `AssignShiftAction` (Task 2).
- Produces: route `admin.kehadiran-sdm.jenis-shift.store/update/destroy`, `admin.kehadiran-sdm.penugasan-shift.store/update/destroy`; `index()` diperluas dengan `jenisShiftList`, `penugasanShiftList`, `guruList`, `karyawanList` — dipakai Task 8 (view).

- [ ] **Step 1: Perluas `index()` dan tambah 6 method baru**

Baca dulu isi file saat ini (harus sudah punya `storePolicy`/`updatePolicy`/`destroyPolicy` dari Sub-project 3). Tambahkan `use` statement baru:

```php
use App\Domains\Sdm\Actions\AssignShiftAction;
use App\Domains\Sdm\DataTransferObjects\ShiftAssignmentData;
use App\Domains\Sdm\Exceptions\ShiftAssignmentOverlapException;
use App\Domains\Sdm\Models\JenisShift;
use App\Domains\Sdm\Models\PenugasanShift;
use App\Models\Guru;
use App\Models\Karyawan;
```

Cari baris akhir `return view('admin.kehadiran-sdm.konfigurasi', [ ... 'jenisKaryawanList' => JenisKaryawanMaster::orderBy('nama')->get(), ]);` — tambahkan query baru SEBELUM `return view(...)`, dan 4 key baru ke array data:

```php
        $jenisShiftList = $yayasanId ? JenisShift::withoutGlobalScope(TenantScope::class)
            ->where('yayasan_id', $yayasanId)
            ->where(function ($query) use ($lembagaId) {
                $query->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderBy('nama')
            ->get() : collect();

        $penugasanShiftList = $lembagaId ? PenugasanShift::where('lembaga_id', $lembagaId)
            ->with(['pegawai', 'jenisShift'])
            ->orderByDesc('tanggal_mulai')
            ->get() : collect();

        $guruList = $lembagaId ? Guru::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']) : collect();
        $karyawanList = $lembagaId ? Karyawan::where('lembaga_id', $lembagaId)->orderBy('nama')->get(['id', 'nama']) : collect();
```

Lalu tambahkan 4 key ini ke array `return view(...)` yang sudah ada:

```php
            'jenisShiftList' => $jenisShiftList,
            'penugasanShiftList' => $penugasanShiftList,
            'guruList' => $guruList,
            'karyawanList' => $karyawanList,
```

Tambahkan 6 method baru SETELAH `destroyPolicy()`, SEBELUM `resolveLembagaId()`:

```php
    public function storeJenisShift(Request $request): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['required', 'date_format:H:i'],
            'is_nasional' => ['nullable', 'boolean'],
        ]);

        $isNasional = (bool) ($data['is_nasional'] ?? false);

        if ($isNasional && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh membuat jenis shift nasional.');
        }

        $lembagaId = $isNasional ? null : $this->resolveLembagaId($request);

        if (! $isNasional && $lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis shift.']);
        }

        $yayasanId = $this->resolveYayasanId($request, $lembagaId);

        JenisShift::create([
            'yayasan_id' => $yayasanId,
            'lembaga_id' => $lembagaId,
            'nama' => $data['nama'],
            'jam_masuk' => $data['jam_masuk'],
            'jam_pulang' => $data['jam_pulang'],
        ]);

        return back()->with('status', 'Jenis shift berhasil ditambahkan.');
    }

    public function updateJenisShift(Request $request, JenisShift $jenisShift): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($jenisShift->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh mengubah jenis shift nasional.');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['required', 'date_format:H:i'],
        ]);

        $jenisShift->update($data);

        return back()->with('status', 'Jenis shift berhasil diperbarui.');
    }

    public function destroyJenisShift(Request $request, JenisShift $jenisShift): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        if ($jenisShift->lembaga_id === null && $request->user()->widestScopeLevel() !== 'yayasan') {
            abort(403, 'Hanya aktor berscope yayasan yang boleh menghapus jenis shift nasional.');
        }

        $jenisShift->delete();

        return back()->with('status', 'Jenis shift berhasil dihapus.');
    }

    public function storePenugasanShift(Request $request, AssignShiftAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'pegawai_tipe' => ['required', 'in:guru,karyawan'],
            'pegawai_id' => ['required', 'integer'],
            'jenis_shift_id' => ['required', 'integer', 'exists:jenis_shift,id'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'hari_kerja' => ['nullable', 'array'],
            'hari_kerja.*' => ['integer', 'between:0,6'],
        ]);

        $lembagaId = $this->resolveLembagaId($request);

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.']);
        }

        $pegawaiModel = $data['pegawai_tipe'] === 'guru' ? Guru::class : Karyawan::class;
        $pegawai = $pegawaiModel::find($data['pegawai_id']);

        abort_if($pegawai === null || (int) $pegawai->lembaga_id !== $lembagaId, 404, 'Pegawai tidak ditemukan di lembaga aktif Anda.');

        try {
            $action->execute($pegawai, new ShiftAssignmentData(
                lembagaId: $lembagaId,
                jenisShiftId: $data['jenis_shift_id'],
                tanggalMulai: $data['tanggal_mulai'],
                tanggalSelesai: $data['tanggal_selesai'] ?? null,
                hariKerja: $data['hari_kerja'] ?? null,
            ));
        } catch (ShiftAssignmentOverlapException $exception) {
            return back()->withErrors(['tanggal_mulai' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Penugasan shift berhasil ditambahkan.');
    }

    public function updatePenugasanShift(Request $request, PenugasanShift $penugasanShift, AssignShiftAction $action): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $data = $request->validate([
            'jenis_shift_id' => ['required', 'integer', 'exists:jenis_shift,id'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'hari_kerja' => ['nullable', 'array'],
            'hari_kerja.*' => ['integer', 'between:0,6'],
        ]);

        try {
            $action->execute($penugasanShift->pegawai, new ShiftAssignmentData(
                lembagaId: $penugasanShift->lembaga_id,
                jenisShiftId: $data['jenis_shift_id'],
                tanggalMulai: $data['tanggal_mulai'],
                tanggalSelesai: $data['tanggal_selesai'] ?? null,
                hariKerja: $data['hari_kerja'] ?? null,
            ), excludingId: $penugasanShift->id);
        } catch (ShiftAssignmentOverlapException $exception) {
            return back()->withErrors(['tanggal_mulai' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Penugasan shift berhasil diperbarui.');
    }

    public function destroyPenugasanShift(PenugasanShift $penugasanShift): RedirectResponse
    {
        $this->authorize('kehadiran-sdm.kelola-konfigurasi');

        $penugasanShift->delete();

        return back()->with('status', 'Penugasan shift berhasil dihapus.');
    }
```

- [ ] **Step 2: Tambah 6 route baru ke `routes/admin/kehadiran-sdm.php`**

Tambahkan di AKHIR file, setelah route `kehadiran-sdm.policy.destroy`:

```php
Route::post('kehadiran-sdm/konfigurasi/jenis-shift', [AttendanceConfigurationController::class, 'storeJenisShift'])->name('kehadiran-sdm.jenis-shift.store');
Route::put('kehadiran-sdm/konfigurasi/jenis-shift/{jenisShift}', [AttendanceConfigurationController::class, 'updateJenisShift'])->name('kehadiran-sdm.jenis-shift.update');
Route::delete('kehadiran-sdm/konfigurasi/jenis-shift/{jenisShift}', [AttendanceConfigurationController::class, 'destroyJenisShift'])->name('kehadiran-sdm.jenis-shift.destroy');

Route::post('kehadiran-sdm/konfigurasi/penugasan-shift', [AttendanceConfigurationController::class, 'storePenugasanShift'])->name('kehadiran-sdm.penugasan-shift.store');
Route::put('kehadiran-sdm/konfigurasi/penugasan-shift/{penugasanShift}', [AttendanceConfigurationController::class, 'updatePenugasanShift'])->name('kehadiran-sdm.penugasan-shift.update');
Route::delete('kehadiran-sdm/konfigurasi/penugasan-shift/{penugasanShift}', [AttendanceConfigurationController::class, 'destroyPenugasanShift'])->name('kehadiran-sdm.penugasan-shift.destroy');
```

- [ ] **Step 3: Buat `resources/js/shift-penugasan-form.js`**

Reuse pola persis `resources/js/attendance-manual-form.js` (Sub-project 1) — pemilih pegawai (bisa puluhan) WAJIB Tom Select.

```js
import TomSelect from 'tom-select';

export function shiftPenugasanForm() {
    return {
        pegawaiTipe: 'guru',

        initSelect(el) {
            new TomSelect(el, { maxItems: 1, create: false, allowEmptyOption: true, controlInput: null });
        },
    };
}
```

- [ ] **Step 4: Registrasikan di `resources/js/app.js`**

Cari baris:

```js
import { attendanceManualForm } from './attendance-manual-form';
```

Tambahkan setelahnya:

```js
import { shiftPenugasanForm } from './shift-penugasan-form';
```

Cari baris:

```js
Alpine.data('attendanceManualForm', attendanceManualForm);
```

Tambahkan setelahnya:

```js
Alpine.data('shiftPenugasanForm', shiftPenugasanForm);
```

- [ ] **Step 5: Tulis test**

```php
<?php
// tests/Feature/Admin/ShiftControllerTest.php

use App\Domains\Sdm\Models\JenisShift;
use App\Domains\Sdm\Models\PenugasanShift;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

if (! function_exists('actingAsAdminSdmShift')) {
    function actingAsAdminSdmShift(Lembaga $lembaga): User
    {
        foreach (['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $role->givePermissionTo(['kehadiran-sdm.view', 'kehadiran-sdm.kelola-konfigurasi']);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->assignRole($role);

        return $user;
    }
}

it('lets an admin_sdm create a jenis_shift for their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = actingAsAdminSdmShift($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.jenis-shift.store'), [
        'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00',
    ])->assertRedirect();

    expect(JenisShift::where('lembaga_id', $lembaga->id)->where('nama', 'Shift Pagi')->exists())->toBeTrue();
});

it('lets an admin_sdm assign a shift to a guru in their own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $admin = actingAsAdminSdmShift($lembaga);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.penugasan-shift.store'), [
        'pegawai_tipe' => 'guru', 'pegawai_id' => $guru->id, 'jenis_shift_id' => $jenisShift->id,
        'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-07',
    ])->assertRedirect();

    expect(PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->exists())->toBeTrue();
});

it('404s when assigning a shift to a guru from a different lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guruLembagaB = Guru::factory()->create(['lembaga_id' => $lembagaB->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembagaA->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $adminLembagaA = actingAsAdminSdmShift($lembagaA);

    $this->actingAs($adminLembagaA)->post(route('admin.kehadiran-sdm.penugasan-shift.store'), [
        'pegawai_tipe' => 'guru', 'pegawai_id' => $guruLembagaB->id, 'jenis_shift_id' => $jenisShift->id,
        'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-07',
    ])->assertNotFound();

    expect(PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guruLembagaB->id)->exists())->toBeFalse();
});

it('returns a session error (not a 500) when creating an overlapping shift assignment via the endpoint', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisShift = JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00']);
    $admin = actingAsAdminSdmShift($lembaga);
    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.penugasan-shift.store'), [
        'pegawai_tipe' => 'guru', 'pegawai_id' => $guru->id, 'jenis_shift_id' => $jenisShift->id,
        'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-07',
    ]);

    $this->actingAs($admin)->post(route('admin.kehadiran-sdm.penugasan-shift.store'), [
        'pegawai_tipe' => 'guru', 'pegawai_id' => $guru->id, 'jenis_shift_id' => $jenisShift->id,
        'tanggal_mulai' => '2026-09-05', 'tanggal_selesai' => '2026-09-10',
    ])->assertSessionHasErrors('tanggal_mulai');

    expect(PenugasanShift::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe(1);
});

it('rejects an admin without kehadiran-sdm.kelola-konfigurasi permission', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $noPermissionUser = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($noPermissionUser)->post(route('admin.kehadiran-sdm.jenis-shift.store'), [
        'nama' => 'Shift Pagi', 'jam_masuk' => '06:00', 'jam_pulang' => '14:00',
    ])->assertForbidden();
});
```

- [ ] **Step 6: Jalankan test**

Run: `php artisan test tests/Feature/Admin/ShiftControllerTest.php`
Expected: 5 passed, 0 failed.

- [ ] **Step 7: Jalankan ulang test controller Sub-project 1-3 yang berpotensi tersentuh perubahan `index()`**

Run: `php artisan test tests/Feature/Admin/AttendanceConfigurationControllerTest.php tests/Feature/Admin/AttendanceConfigurationKalenderControllerTest.php tests/Feature/Admin/AttendancePolicyControllerTest.php`
Expected: semua tetap passed seperti sebelumnya, tidak ada regresi.

- [ ] **Step 8: Build asset frontend**

Run: `npm run build`
Expected: build sukses tanpa error.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin/AttendanceConfigurationController.php routes/admin/kehadiran-sdm.php resources/js/shift-penugasan-form.js resources/js/app.js tests/Feature/Admin/ShiftControllerTest.php
git commit -m "feat(sdm): tambah endpoint CRUD JenisShift dan PenugasanShift di AttendanceConfigurationController"
```

---

## Task 8: View — Tab "Shift Bergilir"

**Files:**
- Modify: `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`
- Test: `tests/Feature/Admin/ShiftViewTest.php`

**Interfaces:**
- Consumes: semua endpoint Task 7.

- [ ] **Step 1: Tambah tab ke-4**

Baca dulu isi file saat ini (harus sudah punya 3 tab: `metode`, `kalender`, `policy`). Cari baris tombol tab:

```blade
            <button type="button" @click="tab = 'policy'" :class="tab === 'policy' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Attendance Policy</button>
        </div>
```

Ganti jadi:

```blade
            <button type="button" @click="tab = 'policy'" :class="tab === 'policy' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Attendance Policy</button>
            <button type="button" @click="tab = 'shift'" :class="tab === 'shift' ? 'border-b-2 border-brand-500 text-brand-600' : 'text-gray-500 hover:text-gray-700'" class="rounded-t-lg px-4 py-2.5 text-sm font-semibold transition">Shift Bergilir</button>
        </div>
```

Tambah state Alpine baru — cari baris akhir `x-data` (`togglePolicyHari(day) { ... }` diikuti `}">`, tepat sebelum penutup objek):

```blade
        togglePolicyHari(day) {
            this.formPolicy.hari_kerja = this.formPolicy.hari_kerja.includes(day) ? this.formPolicy.hari_kerja.filter((d) => d !== day) : [...this.formPolicy.hari_kerja, day];
        }
    }">
```

Ganti jadi:

```blade
        togglePolicyHari(day) {
            this.formPolicy.hari_kerja = this.formPolicy.hari_kerja.includes(day) ? this.formPolicy.hari_kerja.filter((d) => d !== day) : [...this.formPolicy.hari_kerja, day];
        },
        showJenisShiftModal: false,
        editingJenisShift: null,
        formJenisShift: { nama: '', jam_masuk: '06:00', jam_pulang: '14:00', is_nasional: false },
        openJenisShiftModal(jenisShift = null, nasional = false) {
            this.editingJenisShift = jenisShift;
            this.formJenisShift = jenisShift
                ? { nama: jenisShift.nama, jam_masuk: jenisShift.jam_masuk.slice(0, 5), jam_pulang: jenisShift.jam_pulang.slice(0, 5), is_nasional: jenisShift.lembaga_id === null }
                : { nama: '', jam_masuk: '06:00', jam_pulang: '14:00', is_nasional: nasional };
            this.showJenisShiftModal = true;
        },
        showPenugasanModal: false,
        editingPenugasan: null,
        formPenugasan: { jenis_shift_id: '', tanggal_mulai: '', tanggal_selesai: '', hari_kerja: [], overrideHariKerja: false },
        openPenugasanModal(penugasan = null) {
            this.editingPenugasan = penugasan;
            this.formPenugasan = penugasan
                ? { jenis_shift_id: penugasan.jenis_shift_id, tanggal_mulai: penugasan.tanggal_mulai.split('T')[0], tanggal_selesai: penugasan.tanggal_selesai ? penugasan.tanggal_selesai.split('T')[0] : '', hari_kerja: penugasan.hari_kerja ?? [], overrideHariKerja: penugasan.hari_kerja !== null }
                : { jenis_shift_id: '', tanggal_mulai: '', tanggal_selesai: '', hari_kerja: [], overrideHariKerja: false };
            this.showPenugasanModal = true;
        },
        togglePenugasanHari(day) {
            this.formPenugasan.hari_kerja = this.formPenugasan.hari_kerja.includes(day) ? this.formPenugasan.hari_kerja.filter((d) => d !== day) : [...this.formPenugasan.hari_kerja, day];
        }
    }">
```

Tambahkan blok tab ke-4, SETELAH `</div>` penutup blok "Tab: Attendance Policy" dan SEBELUM komentar `{{-- Modal Titik Absen (sudah ada dari Sub-project 1) --}}`:

```blade
        {{-- Tab: Shift Bergilir --}}
        <div x-show="tab === 'shift'" x-cloak class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-sm font-bold text-gray-900">Jenis Shift</h2>
                    @can('kehadiran-sdm.kelola-konfigurasi')
                        <x-primary-button type="button" @click="openJenisShiftModal(null, false)">+ Tambah Jenis Shift</x-primary-button>
                    @endcan
                </div>
                <div class="mt-4 divide-y divide-gray-100">
                    @forelse ($jenisShiftList as $js)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">
                                    {{ $js->nama }}
                                    @if ($js->lembaga_id === null)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Nasional</span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-gray-400">{{ substr($js->jam_masuk, 0, 5) }} — {{ substr($js->jam_pulang, 0, 5) }}</p>
                            </div>
                            @can('kehadiran-sdm.kelola-konfigurasi')
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="openJenisShiftModal({{ $js->toJson() }})" class="text-xs font-semibold text-brand-600 hover:text-brand-700">Edit</button>
                                    <form method="POST" action="{{ route('admin.kehadiran-sdm.jenis-shift.destroy', $js) }}" onsubmit="return confirm('Hapus jenis shift ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-400">Belum ada jenis shift.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-sm font-bold text-gray-900">Penugasan Shift</h2>
                    @can('kehadiran-sdm.kelola-konfigurasi')
                        <x-primary-button type="button" @click="openPenugasanModal()">+ Tambah Penugasan</x-primary-button>
                    @endcan
                </div>
                <div class="mt-4 divide-y divide-gray-100">
                    @forelse ($penugasanShiftList as $p)
                        <div class="flex items-center justify-between py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ $p->pegawai->nama ?? '—' }} — {{ $p->jenisShift->nama }}</p>
                                <p class="text-[11px] text-gray-400">{{ $p->tanggal_mulai->format('d M Y') }}{{ $p->tanggal_selesai ? ' — '.$p->tanggal_selesai->format('d M Y') : ' (tanpa batas)' }}</p>
                            </div>
                            @can('kehadiran-sdm.kelola-konfigurasi')
                                <form method="POST" action="{{ route('admin.kehadiran-sdm.penugasan-shift.destroy', $p) }}" onsubmit="return confirm('Hapus penugasan shift ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Hapus</button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-400">Belum ada penugasan shift untuk lembaga ini.</p>
                    @endforelse
                </div>
            </div>
        </div>

```

- [ ] **Step 2: Tambah 2 modal — SETELAH modal Attendance Policy, SEBELUM `</div>` penutup terakhir dan `</x-app-layout>`**

```blade
        {{-- Modal Jenis Shift --}}
        <div x-show="showJenisShiftModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none;">
            <div class="fixed inset-0 bg-gray-900/60" @click="showJenisShiftModal = false"></div>
            <div class="relative z-10 w-full max-w-sm rounded-2xl bg-white p-6 shadow-elevated">
                <h3 class="font-display text-base font-bold text-gray-900" x-text="editingJenisShift ? 'Edit Jenis Shift' : 'Tambah Jenis Shift'"></h3>
                <form method="POST" :action="editingJenisShift ? `/admin/kehadiran-sdm/konfigurasi/jenis-shift/${editingJenisShift.id}` : '{{ route('admin.kehadiran-sdm.jenis-shift.store') }}'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingJenisShift"><input type="hidden" name="_method" value="PUT"></template>
                    <template x-if="!editingJenisShift && formJenisShift.is_nasional"><input type="hidden" name="is_nasional" value="1"></template>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Shift</label>
                        <input x-model="formJenisShift.nama" name="nama" type="text" required placeholder="Contoh: Shift Malam" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Masuk</label>
                            <input x-model="formJenisShift.jam_masuk" name="jam_masuk" type="time" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jam Pulang</label>
                            <input x-model="formJenisShift.jam_pulang" name="jam_pulang" type="time" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-secondary-button type="button" @click="showJenisShiftModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Penugasan Shift --}}
        <div x-show="showPenugasanModal" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display: none;">
            <div class="fixed inset-0 bg-gray-900/60" @click="showPenugasanModal = false"></div>
            <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-elevated" x-data="shiftPenugasanForm()">
                <h3 class="font-display text-base font-bold text-gray-900" x-text="editingPenugasan ? 'Edit Penugasan Shift' : 'Tambah Penugasan Shift'"></h3>
                <form method="POST" :action="editingPenugasan ? `/admin/kehadiran-sdm/konfigurasi/penugasan-shift/${editingPenugasan.id}` : '{{ route('admin.kehadiran-sdm.penugasan-shift.store') }}'" class="mt-4 space-y-4">
                    @csrf
                    <template x-if="editingPenugasan"><input type="hidden" name="_method" value="PUT"></template>

                    <template x-if="!editingPenugasan">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Pegawai</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-sm"><input type="radio" name="pegawai_tipe" value="guru" x-model="pegawaiTipe"> Guru</label>
                                <label class="flex items-center gap-2 text-sm"><input type="radio" name="pegawai_tipe" value="karyawan" x-model="pegawaiTipe"> Karyawan</label>
                            </div>
                        </div>
                    </template>

                    <template x-if="!editingPenugasan && pegawaiTipe === 'guru'">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Guru</label>
                            <select name="pegawai_id" x-ref="guruSelectShift" x-init="initSelect($refs.guruSelectShift)" class="w-full rounded-lg border-gray-200 text-sm">
                                <option value="">Pilih guru...</option>
                                @foreach ($guruList as $g)
                                    <option value="{{ $g->id }}">{{ $g->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    <template x-if="!editingPenugasan && pegawaiTipe === 'karyawan'">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Nama Karyawan</label>
                            <select name="pegawai_id" x-ref="karyawanSelectShift" x-init="initSelect($refs.karyawanSelectShift)" class="w-full rounded-lg border-gray-200 text-sm">
                                <option value="">Pilih karyawan...</option>
                                @foreach ($karyawanList as $k)
                                    <option value="{{ $k->id }}">{{ $k->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-gray-700">Jenis Shift</label>
                        <select x-model="formPenugasan.jenis_shift_id" name="jenis_shift_id" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                            <option value="">Pilih jenis shift...</option>
                            @foreach ($jenisShiftList as $js)
                                <option value="{{ $js->id }}">{{ $js->nama }} ({{ substr($js->jam_masuk, 0, 5) }}-{{ substr($js->jam_pulang, 0, 5) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Mulai</label>
                            <input x-model="formPenugasan.tanggal_mulai" name="tanggal_mulai" type="date" required class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">Tanggal Selesai (opsional)</label>
                            <input x-model="formPenugasan.tanggal_selesai" name="tanggal_selesai" type="date" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm">
                        </div>
                    </div>

                    <div class="rounded-lg bg-amber-50 border border-amber-100 px-3.5 py-3">
                        <label class="flex items-center gap-2 text-xs font-semibold text-amber-800">
                            <input type="checkbox" x-model="formPenugasan.overrideHariKerja" class="rounded border-gray-300">
                            Batasi ke hari tertentu dalam rentang (opsional — kosongkan berarti semua hari dalam rentang dianggap kerja)
                        </label>
                        <template x-if="formPenugasan.overrideHariKerja">
                            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <template x-for="[hari, label] in Object.entries({1: 'Senin', 2: 'Selasa', 3: 'Rabu', 4: 'Kamis', 5: 'Jumat', 6: 'Sabtu', 0: 'Minggu'})" :key="hari">
                                    <label class="flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-2 py-1.5 text-xs">
                                        <input type="checkbox" :checked="formPenugasan.hari_kerja.includes(Number(hari))" @change="togglePenugasanHari(Number(hari))">
                                        <span x-text="label"></span>
                                    </label>
                                </template>
                            </div>
                        </template>
                        <template x-for="day in (formPenugasan.overrideHariKerja ? formPenugasan.hari_kerja : [])" :key="day">
                            <input type="hidden" name="hari_kerja[]" :value="day">
                        </template>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <x-secondary-button type="button" @click="showPenugasanModal = false">Batal</x-secondary-button>
                        <x-primary-button type="submit">Simpan</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

```

Catatan: `x-data="shiftPenugasanForm()"` DISENGAJA ditaruh nested di `<div>` modal (BUKAN di root), supaya `pegawaiTipe`/`initSelect()` datang dari komponen JS yang di-reuse (Task 7), sementara `showPenugasanModal`/`editingPenugasan`/`formPenugasan` TETAP terbaca dari scope root x-data di luar (perilaku standar Alpine.js — child scope bisa membaca DAN menulis properti parent scope yang tidak didefinisikan ulang di child).

- [ ] **Step 3: Tulis test**

```php
<?php
// tests/Feature/Admin/ShiftViewTest.php

use App\Domains\Sdm\Models\JenisShift;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

it('renders the konfigurasi page with the Shift Bergilir tab and existing jenis_shift rows', function () {
    Permission::firstOrCreate(['name' => 'kehadiran-sdm.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_sdm', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo('kehadiran-sdm.view');

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    JenisShift::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'nama' => 'Shift Malam', 'jam_masuk' => '22:00', 'jam_pulang' => '06:00']);

    $this->actingAs($admin)->get(route('admin.kehadiran-sdm.konfigurasi.index'))
        ->assertOk()
        ->assertSee('Shift Bergilir')
        ->assertSee('Shift Malam');
});
```

- [ ] **Step 4: Jalankan test baru**

Run: `php artisan test tests/Feature/Admin/ShiftViewTest.php`
Expected: 1 passed, 0 failed.

- [ ] **Step 5: Jalankan ulang test view Sub-project 1-3 yang berpotensi tersentuh**

Run: `php artisan test tests/Feature/Admin/AttendanceKonfigurasiKalenderViewTest.php tests/Feature/Admin/AttendanceHolidayOverrideViewTest.php tests/Feature/Admin/AttendancePolicyViewTest.php`
Expected: semua tetap passed seperti sebelumnya.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/kehadiran-sdm/konfigurasi.blade.php tests/Feature/Admin/ShiftViewTest.php
git commit -m "feat(sdm): tambah tab Shift Bergilir (Jenis Shift + Penugasan Shift) di halaman konfigurasi"
```

---

## Task 9: Verifikasi Akhir + Full Test Suite (Butuh Izin User)

**Files:** Tidak ada file baru — task ini murni verifikasi.

- [ ] **Step 1: Grep ulang untuk memastikan tidak ada hardcode role**

Run: `grep -rn "hasRole(" app/Domains/Sdm app/Http/Controllers/Admin/AttendanceConfigurationController.php app/Console/Commands/TandaiAlpaOtomatisSdm.php`
Expected: kosong.

- [ ] **Step 2: Grep untuk memastikan `AttendancePolicyResolver.php` dan `KalenderKerjaSdmResolver.php` tidak diubah isinya**

Run: `git diff dc7cfc5..HEAD -- app/Domains/Sdm/Services/AttendancePolicyResolver.php app/Domains/Sdm/Services/KalenderKerjaSdmResolver.php`
Expected: output KOSONG (tidak ada perubahan sama sekali pada kedua file ini sejak baseline plan).

- [ ] **Step 3: Grep untuk memastikan tidak ada hardcode kategori pegawai (mis. "satpam") di kode manapun**

Run: `grep -rin "satpam" app/`
Expected: kosong — "satpam" HANYA boleh muncul di komentar/dokumentasi spec/plan (bukan bagian dari kode aplikasi), TIDAK PERNAH di string/kondisi kode manapun.

- [ ] **Step 4: Jalankan seluruh test scoped item ini bersama-sama**

Run: `php artisan test tests/Feature/Sdm tests/Unit/Services/ShiftAwareAttendanceResolverTest.php tests/Feature/Admin/ShiftControllerTest.php tests/Feature/Admin/ShiftViewTest.php`
Expected: semua test dari Task 2-8 hijau bersama-sama (total ≥ 25 test baru), 0 failed.

- [ ] **Step 5: Jalankan ulang SELURUH test domain Sdm dari Sub-project 1, 2, 3, dan item ini sekaligus, untuk pastikan tidak ada regresi silang**

Run: `php artisan test tests/Feature/Sdm tests/Feature/Admin/Attendance*.php tests/Feature/Admin/Shift*.php tests/Unit/Services/KalenderKerjaSdmResolverTest.php tests/Unit/Services/AttendancePolicyResolverTest.php tests/Unit/Services/ShiftAwareAttendanceResolverTest.php`
Expected: 0 failed.

- [ ] **Step 6: MINTA IZIN EKSPLISIT USER sebelum lanjut ke Step 7**

Tampilkan pesan ke user: "Semua test scoped Penugasan Shift per Periode sudah hijau. Boleh saya jalankan full test suite sekarang?" — TUNGGU jawaban eksplisit sebelum menjalankan Step 7.

- [ ] **Step 7: (Setelah izin diberikan) Jalankan full test suite**

Run: `php artisan test`
Expected: 0 failed, 0 error. Total test harus ≥ 1986 (baseline Sub-project 3) + jumlah test baru item ini (kurang lebih 25).

Catatan: kalau ada test GAGAL yang TIDAK terkait item ini sama sekali, ada riwayat flaky test pre-existing di branch ini (`KomponenPenilaianCrudTest`, `RaporPdfDataBuilderTest`) — jalankan ulang test yang gagal SENDIRIAN dulu untuk konfirmasi sebelum melaporkan sebagai regresi.

- [ ] **Step 8: Tulis handoff log**

Buat file `.agents/logs/2026-08-22-sdm-03b-shift-bergilir.md` berisi: ringkasan per task (1-9), commit hash tiap task, hasil verifikasi akhir dengan angka pasti, dan daftar deviasi (kalau ada) dari plan ini.

- [ ] **Step 9: Commit handoff log**

```bash
git add .agents/logs/2026-08-22-sdm-03b-shift-bergilir.md
git commit -m "docs(sdm): handoff log Penugasan Shift per Periode (item tertunda Sub-project 3)"
```

---

## Self-Review (dilakukan penulis plan, bukan executor)

**Spec coverage**: §3 struktur data → Task 1-2. §4 resolver → Task 3. §5 integrasi ke 4 file existing → Task 4-6. §6 RBAC → Task 7. §7 UI → Task 7-8. §8 batasan (rotasi otomatis tidak dibangun, kedua resolver Sub-project 2/3 tidak disentuh) → diverifikasi eksplisit di Task 9 Step 2-3. Semua requirement spec punya task yang mengimplementasikannya.

**Placeholder scan**: tidak ada TBD/TODO, semua kode lengkap per step.

**Type consistency**: `ShiftAwareAttendanceResolver::resolveLibur()` signature (`array{libur, alasan}`) identik dipakai di `RecordManualAttendanceAction`, `ScanQrAttendanceAction`, `TandaiAlpaOtomatisSdm` — sama seperti pendahulunya. `resolveJamKerjaEfektif()` dipakai konsisten di `AttendanceRecordAggregator::hitungKeterlambatan()`. `AssignShiftAction::execute()` dipakai identik di `storePenugasanShift` (tanpa `excludingId`) dan `updatePenugasanShift` (dengan `excludingId`).

**Regresi Sub-project 1-3**: Task 4 Step 3, Task 5 Step 2, Task 6 Step 2, Task 7 Step 7, dan Task 8 Step 5 masing-masing eksplisit menjalankan ulang test Sub-project 1-3 yang tersentuh perubahan. Task 9 Step 2 secara eksplisit memverifikasi `AttendancePolicyResolver.php` DAN `KalenderKerjaSdmResolver.php` benar-benar 0 byte berubah — jaminan konkret independensi antar-lapisan (Kalender → Policy → Shift) yang jadi prinsip arsitektur seluruh modul ini.
