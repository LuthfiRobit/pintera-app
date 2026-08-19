# Sub-Task 04d: Adaptive E-Rapor Engine — 4 Template PDF Berjenjang Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cetak rapor resmi per-siswa dalam 4 layout DomPDF berbeda sesuai jenjang pendidikan (PAUD/SD/SMP-SMA/SMK), lengkap dengan identitas, nilai, narasi capaian, catatan wali kelas, absensi, tanda tangan, dan logic khusus semester Ganjil vs Genap — di atas backend headless yang sudah selesai dari Sub-Task 04a/04b/04c.

**Architecture:** Satu Service baru (`RaporPdfDataBuilder`) mengumpulkan semua data yang dibutuhkan PDF dari Service/Model yang sudah ada (`RaporCalculationService`, `CapaianKompetensiGenerator`, `PresensiAggregationService`). Dua method `cetak()` ditambahkan ke controller yang SUDAH ADA dari 04c (`Guru\RaporController`, `Lembaga\Rapor\PersetujuanController`) — tidak ada controller baru. Sekaligus memperluas `KomponenPenilaian` dengan kolom `elemen_cp` (untuk kategorisasi PAUD) DAN memperbaiki celah UI `kktp_minimal` yang terlewat sejak 04b.

**Tech Stack:** Laravel 11, `barryvdh/laravel-dompdf` (sudah dipakai, lihat `Admin\RaporController::cetak()`), Blade, Pest tests.

## Global Constraints

- **PDF bisa dicetak kapan saja sebagai draft** — bukan hanya setelah `PengajuanRapor.status === Disetujui`. Watermark "DRAFT" (CSS murni) muncul selama status bukan `Disetujui`.
- **Akses cetak**: `rapor.input-wali` (Guru, guard kepemilikan kelas), `rapor.verify`/`rapor.approve` (Waka/Kepsek, TANPA guard step-matching — berbeda dari `show()`/`decision()`). TIDAK ADA permission baru.
- **Tanda tangan 3 kolom** (Wali Kelas/Kepala Sekolah/Orang Tua) memakai nama AKTOR SISTEM yang benar-benar approve (`PengajuanRapor.diverifikasi_oleh`/`disetujui_oleh`), BUKAN data profil registrasi (`Lembaga.nama_kepala_sekolah`/`Kelas.wali_kelas_guru_id`). Kosong/placeholder kalau kolom itu masih `null`.
- **`elemen_cp`** (3 nilai: `nilai_agama_moral`/`jati_diri`/`literasi_steam`) HANYA relevan/ditampilkan untuk kelas berjenjang PAUD (`bentuk_pendidikan` IN `KB`,`TPA`,`SPS`,`TK`). PKL/UKK numerik untuk SMK di LUAR scope — jangan tambahkan field nilai PKL/UKK apa pun.
- **Ganjil/Genap** (deteksi: `$semester->urutan === 2` = Genap): Keterangan Kenaikan Kelas/Kelulusan, akumulasi absensi tahunan, dan nilai rata-rata tahunan HANYA muncul di rapor Genap dan HANYA untuk template `sd`/`smp-sma`/`smk` (bukan `paud`). Judul dokumen beda per semester di SEMUA 4 template.
- **Tidak ada mode cetak-batch/zip** — satu PDF per satu request per satu siswa.
- Setiap file PHP baru diawali `declare(strict_types=1);`.
- Tests: jalankan HANYA test yang di-scope ke task itu di setiap task, sinkron di shell (jangan di-background lalu menunggu notifikasi). Full suite HANYA sekali di task terakhir, dan HANYA setelah bertanya ke user dulu.

---

## File Map

| File | Task | Keterangan |
|---|---|---|
| `database/migrations/2026_08_19_190000_add_elemen_cp_to_komponen_penilaian_table.php` | 1 | Kolom baru |
| `app/Domains/Akademik/Enums/ElemenCapaianPembelajaran.php` | 1 | Enum baru |
| `app/Domains/Akademik/Models/KomponenPenilaian.php` | 1 | + fillable/cast |
| `app/Domains/Akademik/DataTransferObjects/{KomponenPenilaianData,UpdateKomponenPenilaianData}.php` | 1 | + param `elemenCp` |
| `app/Domains/Akademik/Actions/Penilaian/{Create,Update}KomponenPenilaianAction.php` | 1 | + simpan `elemen_cp` |
| `app/Http/Requests/Akademik/{Store,Update}KomponenPenilaian{,Sendiri}Request.php` (4 file) | 1 | + rule `elemen_cp` |
| `app/Http/Controllers/{Admin,Guru}/KomponenPenilaianController.php` | 2 | + `$bentukPendidikan` ke `create()`/`edit()` |
| `resources/views/{admin,guru}/komponen-penilaian/{create,edit}.blade.php` (4 file) | 2 | + field `kktp_minimal` (selalu) & `elemen_cp` (kondisional PAUD) |
| `app/Domains/Akademik/Services/RaporPdfDataBuilder.php` | 3, 4 | Service baru |
| `resources/views/pdf/rapor/_identitas.blade.php`, `_tanda-tangan.blade.php`, `paud.blade.php`, `sd.blade.php` | 5 | Partial + 2 template |
| `resources/views/pdf/rapor/smp-sma.blade.php`, `smk.blade.php` | 6 | 2 template |
| `app/Http/Controllers/Guru/RaporController.php`, `routes/admin.php`, `resources/views/portals/guru/rapor/catatan/index.blade.php` | 7 | `cetak()` sisi wali kelas |
| `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`, `routes/admin.php`, `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php` | 8 | `cetak()` sisi Waka/Kepsek |
| `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`, `.agents/logs/2026-08-19-1900-akademik-04d-rapor-pdf.md` | 9 | Master plan + handoff |

---

### Task 1: Perluasan `elemen_cp` — Migrasi, Enum, Model, DTO, Action, FormRequest

**Files:**
- Create: `database/migrations/2026_08_19_190000_add_elemen_cp_to_komponen_penilaian_table.php`
- Create: `app/Domains/Akademik/Enums/ElemenCapaianPembelajaran.php`
- Modify: `app/Domains/Akademik/Models/KomponenPenilaian.php`
- Modify: `app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`
- Modify: `app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`
- Modify: `app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php`
- Modify: `app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php`
- Modify: `app/Http/Requests/Akademik/StoreKomponenPenilaianSendiriRequest.php`
- Modify: `app/Http/Requests/Akademik/UpdateKomponenPenilaianSendiriRequest.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php` (append)

**Interfaces:**
- Produces: `App\Domains\Akademik\Enums\ElemenCapaianPembelajaran` (backed string enum, 3 case: `NilaiAgamaMoral='nilai_agama_moral'`, `JatiDiri='jati_diri'`, `LiterasiSteam='literasi_steam'`). `KomponenPenilaian.elemen_cp` (nullable, cast ke enum di atas). `KomponenPenilaianData`/`UpdateKomponenPenilaianData` konstruktor bertambah SATU parameter TERAKHIR: `?ElemenCapaianPembelajaran $elemenCp`.

- [ ] **Step 1: Buat migrasi**

Create `database/migrations/2026_08_19_190000_add_elemen_cp_to_komponen_penilaian_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->string('elemen_cp', 30)->nullable()->after('kktp_minimal');
        });
    }

    public function down(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->dropColumn('elemen_cp');
        });
    }
};
```

- [ ] **Step 2: Buat enum**

Create `app/Domains/Akademik/Enums/ElemenCapaianPembelajaran.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Enums;

enum ElemenCapaianPembelajaran: string
{
    case NilaiAgamaMoral = 'nilai_agama_moral';
    case JatiDiri = 'jati_diri';
    case LiterasiSteam = 'literasi_steam';

    public function label(): string
    {
        return match ($this) {
            self::NilaiAgamaMoral => 'Nilai Agama dan Budi Pekerti',
            self::JatiDiri => 'Jati Diri',
            self::LiterasiSteam => 'Literasi, STEAM, Seni, dan Budaya',
        };
    }
}
```

- [ ] **Step 3: Jalankan migrasi**

Run: `php artisan migrate`
Expected: `2026_08_19_190000_add_elemen_cp_to_komponen_penilaian_table ... DONE`

- [ ] **Step 4: Perbarui model**

Edit `app/Domains/Akademik/Models/KomponenPenilaian.php`. Tambahkan import di bagian atas file (setelah `use App\Models\Concerns\BelongsToTenant;`):

```php
use App\Domains\Akademik\Enums\ElemenCapaianPembelajaran;
```

Ubah baris `protected $fillable = [...]`:

```php
    protected $fillable = ['mata_pelajaran_id', 'semester_id', 'lembaga_id', 'kode', 'deskripsi', 'bobot', 'kktp', 'kktp_minimal', 'elemen_cp'];
```

Tambahkan method `casts()` baru setelah `newFactory()` (SEBELUM `protected static function booted()`):

```php
    protected function casts(): array
    {
        return [
            'elemen_cp' => ElemenCapaianPembelajaran::class,
        ];
    }
```

- [ ] **Step 5: Perluas DTO**

Edit `app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`. Tambahkan import:

```php
use App\Domains\Akademik\Enums\ElemenCapaianPembelajaran;
```

Ubah constructor — tambahkan parameter TERAKHIR:

```php
final readonly class KomponenPenilaianData
{
    public function __construct(
        public int $mataPelajaranId,
        public int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
        public ?ElemenCapaianPembelajaran $elemenCp,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mataPelajaranId: (int) $data['mata_pelajaran_id'],
            semesterId: (int) $data['semester_id'],
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : 10,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
            elemenCp: isset($data['elemen_cp']) && $data['elemen_cp'] !== '' ? ElemenCapaianPembelajaran::from($data['elemen_cp']) : null,
        );
    }
}
```

Edit `app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php` sama persis (tambahkan import yang sama, tambahkan parameter `?ElemenCapaianPembelajaran $elemenCp` terakhir di constructor, tambahkan `elemenCp: isset($data['elemen_cp']) && $data['elemen_cp'] !== '' ? ElemenCapaianPembelajaran::from($data['elemen_cp']) : null,` di `fromArray()`):

```php
final readonly class UpdateKomponenPenilaianData
{
    public function __construct(
        public ?int $mataPelajaranId,
        public ?int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public ?int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
        public ?ElemenCapaianPembelajaran $elemenCp,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mataPelajaranId: isset($data['mata_pelajaran_id']) ? (int) $data['mata_pelajaran_id'] : null,
            semesterId: isset($data['semester_id']) ? (int) $data['semester_id'] : null,
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : null,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
            elemenCp: isset($data['elemen_cp']) && $data['elemen_cp'] !== '' ? ElemenCapaianPembelajaran::from($data['elemen_cp']) : null,
        );
    }
}
```

- [ ] **Step 6: Perluas Action**

Edit `app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php` — di dalam `KomponenPenilaian::create([...])`, tambahkan baris setelah `'kktp_minimal' => $data->kktpMinimal,`:

```php
            'elemen_cp' => $data->elemenCp,
```

Edit `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php` — setelah baris `$komponen->kktp_minimal = $data->kktpMinimal;`, tambahkan:

```php
        $komponen->elemen_cp = $data->elemenCp;
```

- [ ] **Step 7: Perluas 4 FormRequest**

Untuk SETIAP dari 4 file (`app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php`, `UpdateKomponenPenilaianRequest.php`, `StoreKomponenPenilaianSendiriRequest.php`, `UpdateKomponenPenilaianSendiriRequest.php`) — tambahkan import:

```php
use App\Domains\Akademik\Enums\ElemenCapaianPembelajaran;
use Illuminate\Validation\Rule;
```

Dan tambahkan satu baris di dalam `rules()`, setelah baris `'kktp_minimal' => [...]`:

```php
            'elemen_cp' => ['nullable', Rule::enum(ElemenCapaianPembelajaran::class)],
```

(Untuk `UpdateKomponenPenilaianRequest.php`, baris ini masuk ke `$rules` array yang sudah ada di dalam method, posisi sama — setelah baris `'kktp_minimal'`.)

- [ ] **Step 8: Tulis test regresi backward-compatible**

Append ke `tests/Feature/Admin/KomponenPenilaianCrudTest.php`. File ini SUDAH punya helper `actingAsKomponenManager(Lembaga $lembaga): User` (baris 17-27) yang membuat role `admin_akademik` dengan permission `komponen-penilaian.kelola` dan mengembalikan satu `User` — pakai helper itu, JANGAN membuat helper baru:

```php
it('saves elemen_cp when submitted and leaves it null when omitted', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager = actingAsKomponenManager($lembaga);

    $this->actingAs($manager)->post(route('admin.komponen-penilaian.store'), [
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'TP dengan elemen CP',
        'bobot' => 10,
        'elemen_cp' => 'jati_diri',
    ]);

    $this->assertDatabaseHas('komponen_penilaian', [
        'mata_pelajaran_id' => $mapel->id,
        'deskripsi' => 'TP dengan elemen CP',
        'elemen_cp' => 'jati_diri',
    ]);

    $this->actingAs($manager)->post(route('admin.komponen-penilaian.store'), [
        'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id,
        'deskripsi' => 'TP tanpa elemen CP',
        'bobot' => 10,
    ]);

    $this->assertDatabaseHas('komponen_penilaian', [
        'deskripsi' => 'TP tanpa elemen CP',
        'elemen_cp' => null,
    ]);
});
```

- [ ] **Step 9: Jalankan test**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php`
Expected: PASS — semua test lama TETAP hijau tanpa perubahan assertion, ditambah 1 test baru lulus.

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_08_19_190000_add_elemen_cp_to_komponen_penilaian_table.php app/Domains/Akademik/Enums/ElemenCapaianPembelajaran.php app/Domains/Akademik/Models/KomponenPenilaian.php app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php app/Http/Requests/Akademik/StoreKomponenPenilaianSendiriRequest.php app/Http/Requests/Akademik/UpdateKomponenPenilaianSendiriRequest.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "feat(akademik): tambah kolom elemen_cp ke KomponenPenilaian untuk kategori CP PAUD"
```

---

### Task 2: Perbaikan UI — Field `kktp_minimal` + `elemen_cp` di Form Komponen Penilaian

**Files:**
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php`
- Modify: `app/Http/Controllers/Guru/KomponenPenilaianController.php`
- Modify: `resources/views/admin/komponen-penilaian/create.blade.php`
- Modify: `resources/views/admin/komponen-penilaian/edit.blade.php`
- Modify: `resources/views/guru/komponen-penilaian/create.blade.php`
- Modify: `resources/views/guru/komponen-penilaian/edit.blade.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`, `tests/Feature/Guru/KomponenPenilaianControllerTest.php` (append)

**Interfaces:**
- Consumes: `App\Domains\Akademik\Enums\ElemenCapaianPembelajaran` (Task 1). `$request->user()->lembaga->bentuk_pendidikan` (User model sudah punya relasi `lembaga()`, `Lembaga` model sudah punya kolom `bentuk_pendidikan` — TIDAK ada perubahan skema di task ini).

**Konteks penting**: field `kktp_minimal` ditambahkan ke backend di Sub-Task 04b TAPI TIDAK PERNAH ditambahkan ke UI mana pun — task ini memperbaikinya SEKALIGUS dengan menambah `elemen_cp`, karena keduanya berakhir di lokasi form yang sama.

- [ ] **Step 1: Tambahkan `$bentukPendidikan` ke controller Admin**

Edit `app/Http/Controllers/Admin/KomponenPenilaianController.php`. Di method `create()`, tambahkan key baru ke array view (setelah `'mataPelajaranList' => ...`):

```php
        return view('admin.komponen-penilaian.create', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
            'tahunAjaranId' => $tahunAjaranId,
            'semesterList' => $tahunAjaranId ? Semester::where('tahun_ajaran_id', $tahunAjaranId)->orderByDesc('id')->get() : collect(),
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'bentukPendidikan' => $request->user()->lembaga?->bentuk_pendidikan,
        ]);
```

Di method `edit()`, tambahkan key yang sama ke array view (setelah `'semesterList' => ...`):

```php
        return view('admin.komponen-penilaian.edit', [
            'komponenPenilaian' => $komponenPenilaian->load(['mataPelajaran', 'semester.tahunAjaran']),
            'dipakai' => $dipakai,
            'mataPelajaranList' => MataPelajaran::orderBy('nama')->get(),
            'semesterList' => Semester::with('tahunAjaran')->orderByDesc('id')->get(),
            'bentukPendidikan' => auth()->user()->lembaga?->bentuk_pendidikan,
        ]);
```

- [ ] **Step 2: Tambahkan `$bentukPendidikan` ke controller Guru**

Edit `app/Http/Controllers/Guru/KomponenPenilaianController.php`. Di method `create()`, tambahkan key baru:

```php
        return view('guru.komponen-penilaian.create', [
            'mataPelajaranList' => MataPelajaran::whereIn('id', $mapelIds)->orderBy('nama')->get(),
            'semesterList' => Semester::whereIn('id', $semesterIds)->with('tahunAjaran')->orderByDesc('id')->get(),
            'bentukPendidikan' => $request->user()->lembaga?->bentuk_pendidikan,
        ]);
```

Di method `edit()`, tambahkan key yang sama:

```php
        return view('guru.komponen-penilaian.edit', [
            'komponenPenilaian' => $komponenPenilaian->load(['mataPelajaran', 'semester.tahunAjaran']),
            'dipakai' => $dipakai,
            'bentukPendidikan' => auth()->user()->lembaga?->bentuk_pendidikan,
        ]);
```

- [ ] **Step 3: Tambahkan field ke `admin/komponen-penilaian/create.blade.php`**

Cari blok field "KKTP / Kriteria Ketercapaian (Opsional)" (elemen `<textarea name="kktp"...>`) di `resources/views/admin/komponen-penilaian/create.blade.php`. Tambahkan blok baru PERSIS SETELAH `</div>` penutup blok KKTP tersebut, SEBELUM `<div class="flex items-center gap-3 pt-4 border-t border-gray-100">` (blok tombol submit):

```blade
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Ambang KKTP Minimal (Opsional)" />
                        <input
                            type="number"
                            name="kktp_minimal"
                            value="{{ old('kktp_minimal') }}"
                            min="0"
                            max="100"
                            placeholder="Contoh: 75"
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                        <p class="mt-1 text-xs text-gray-400">Ambang skor numerik untuk narasi capaian otomatis (default 75 jika kosong).</p>
                        <x-input-error :messages="$errors->get('kktp_minimal')" class="mt-1" />
                    </div>

                    @if (in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true))
                        <div>
                            <x-input-label value="Elemen Capaian Pembelajaran (PAUD)" />
                            <select
                                name="elemen_cp"
                                class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                            >
                                <option value="">— Pilih Elemen CP —</option>
                                <option value="nilai_agama_moral" @selected(old('elemen_cp') === 'nilai_agama_moral')>Nilai Agama dan Budi Pekerti</option>
                                <option value="jati_diri" @selected(old('elemen_cp') === 'jati_diri')>Jati Diri</option>
                                <option value="literasi_steam" @selected(old('elemen_cp') === 'literasi_steam')>Literasi, STEAM, Seni, dan Budaya</option>
                            </select>
                            <x-input-error :messages="$errors->get('elemen_cp')" class="mt-1" />
                        </div>
                    @endif
                </div>
```

- [ ] **Step 4: Tambahkan field ke `admin/komponen-penilaian/edit.blade.php`**

Cari blok field "KKTP / Kriteria Ketercapaian (Opsional)" di `resources/views/admin/komponen-penilaian/edit.blade.php`. Tambahkan blok baru PERSIS SETELAH `</div>` penutup blok itu, SEBELUM `<div class="flex items-center gap-3 pt-4 border-t border-gray-100">`:

```blade
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Ambang KKTP Minimal (Opsional)" />
                        <input
                            type="number"
                            name="kktp_minimal"
                            value="{{ old('kktp_minimal', $komponenPenilaian->kktp_minimal) }}"
                            min="0"
                            max="100"
                            placeholder="Contoh: 75"
                            class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                        >
                        <p class="mt-1 text-xs text-gray-400">Ambang skor numerik untuk narasi capaian otomatis (default 75 jika kosong).</p>
                        <x-input-error :messages="$errors->get('kktp_minimal')" class="mt-1" />
                    </div>

                    @if (in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true))
                        <div>
                            <x-input-label value="Elemen Capaian Pembelajaran (PAUD)" />
                            @php($elemenCpSaatIni = old('elemen_cp', $komponenPenilaian->elemen_cp?->value))
                            <select
                                name="elemen_cp"
                                class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500"
                            >
                                <option value="">— Pilih Elemen CP —</option>
                                <option value="nilai_agama_moral" @selected($elemenCpSaatIni === 'nilai_agama_moral')>Nilai Agama dan Budi Pekerti</option>
                                <option value="jati_diri" @selected($elemenCpSaatIni === 'jati_diri')>Jati Diri</option>
                                <option value="literasi_steam" @selected($elemenCpSaatIni === 'literasi_steam')>Literasi, STEAM, Seni, dan Budaya</option>
                            </select>
                            <x-input-error :messages="$errors->get('elemen_cp')" class="mt-1" />
                        </div>
                    @endif
                </div>
```

- [ ] **Step 5: Ulangi Step 3 dan 4 untuk `guru/komponen-penilaian/create.blade.php` dan `guru/komponen-penilaian/edit.blade.php`**

Buka kedua file (`resources/views/guru/komponen-penilaian/create.blade.php`, `resources/views/guru/komponen-penilaian/edit.blade.php`), cari blok field KKTP yang sama (kemungkinan besar strukturnya identik dengan versi Admin karena keduanya form yang sama untuk model yang sama — verifikasi dengan membaca file dulu sebelum edit, JANGAN asumsikan markup identik 100% tanpa cek), lalu tambahkan blok yang SAMA PERSIS seperti Step 3 (untuk `create.blade.php`) dan Step 4 (untuk `edit.blade.php`) di lokasi yang setara.

- [ ] **Step 6: Tulis test membuktikan rendering kondisional**

Append ke `tests/Feature/Admin/KomponenPenilaianCrudTest.php` (gunakan helper setup yang SUDAH ADA di file, sesuaikan cara membuat `Lembaga` dengan `bentuk_pendidikan` custom):

```php
it('shows the elemen_cp select on the create form for a PAUD lembaga but not for an SD lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaPaud = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'TK']);
    $userPaud = actingAsKomponenManager($lembagaPaud);

    $responsePaud = $this->actingAs($userPaud)->get(route('admin.komponen-penilaian.create'));
    $responsePaud->assertOk();
    $responsePaud->assertSee('name="elemen_cp"', false);
    $responsePaud->assertSee('name="kktp_minimal"', false);

    $lembagaSd = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $userSd = actingAsKomponenManager($lembagaSd);

    $responseSd = $this->actingAs($userSd)->get(route('admin.komponen-penilaian.create'));
    $responseSd->assertOk();
    $responseSd->assertDontSee('name="elemen_cp"', false);
    $responseSd->assertSee('name="kktp_minimal"', false);
});
```

(Reuse helper `actingAsKomponenManager(Lembaga $lembaga): User` yang sudah ada di file ini — lihat Task 1 Step 8. Karena helper itu memakai `Role::firstOrCreate(['name' => 'admin_akademik', ...])`, memanggilnya dua kali dengan `$lembaga` berbeda AMAN — `firstOrCreate` tidak membuat role duplikat, hanya `User` baru per panggilan.)

- [ ] **Step 7: Jalankan test**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php tests/Feature/Guru/KomponenPenilaianControllerTest.php`
Expected: PASS — semua test lama tetap hijau, ditambah 1 test baru.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/KomponenPenilaianController.php app/Http/Controllers/Guru/KomponenPenilaianController.php resources/views/admin/komponen-penilaian/create.blade.php resources/views/admin/komponen-penilaian/edit.blade.php resources/views/guru/komponen-penilaian/create.blade.php resources/views/guru/komponen-penilaian/edit.blade.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "fix(akademik): tambah field kktp_minimal (celah sejak 04b) dan elemen_cp ke form Komponen Penilaian"
```

---

### Task 3: `RaporPdfDataBuilder` — Data Inti (tanpa Ganjil/Genap)

**Files:**
- Create: `app/Domains/Akademik/Services/RaporPdfDataBuilder.php`
- Test: `tests/Feature/Akademik/RaporPdfDataBuilderTest.php`

**Interfaces:**
- Consumes: `RaporCalculationService::hitungRekapKelas(Kelas, Semester): array{siswaList, mapelList, rekapNilai, classAvg, highestScore}` (04a). `CapaianKompetensiGenerator::generateNarasi(Siswa, MataPelajaran, Semester): array{tertinggi, terendah}` (04b). `PresensiAggregationService::agregasiPerKelas(int $kelasId, ?Semester $semester): Collection<array{siswa_id,nis,nama,hadir,izin,sakit,alpa,terlambat}>` (03c) — CATATAN: method ini HANYA mengembalikan siswa dengan `status='aktif'`, jadi siswa non-aktif TIDAK akan ada di hasilnya sama sekali.
- Produces: `RaporPdfDataBuilder::build(Siswa $siswa, Semester $semester): array` (struktur lengkap di Step 3 di bawah — task ini BELUM mengisi 6 key terakhir yang berhubungan Ganjil/Genap, itu Task 4). `RaporPdfDataBuilder::templateUntukJenjang(string $bentukPendidikan): string` — ditulis penuh di task ini juga (independen dari Ganjil/Genap).

- [ ] **Step 1: Tulis test yang gagal untuk `templateUntukJenjang()`**

Create `tests/Feature/Akademik/RaporPdfDataBuilderTest.php`:

```php
<?php

use App\Domains\Akademik\Services\RaporPdfDataBuilder;

it('maps bentuk_pendidikan to the correct template, defaulting unknown values to sd', function () {
    $builder = app(RaporPdfDataBuilder::class);

    expect($builder->templateUntukJenjang('KB'))->toBe('pdf.rapor.paud');
    expect($builder->templateUntukJenjang('TPA'))->toBe('pdf.rapor.paud');
    expect($builder->templateUntukJenjang('SPS'))->toBe('pdf.rapor.paud');
    expect($builder->templateUntukJenjang('TK'))->toBe('pdf.rapor.paud');
    expect($builder->templateUntukJenjang('SMK'))->toBe('pdf.rapor.smk');
    expect($builder->templateUntukJenjang('SMP'))->toBe('pdf.rapor.smp-sma');
    expect($builder->templateUntukJenjang('SMA'))->toBe('pdf.rapor.smp-sma');
    expect($builder->templateUntukJenjang('SD'))->toBe('pdf.rapor.sd');
    expect($builder->templateUntukJenjang('SLB'))->toBe('pdf.rapor.sd');
    expect($builder->templateUntukJenjang('NILAI_TAK_DIKENAL'))->toBe('pdf.rapor.sd');
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test tests/Feature/Akademik/RaporPdfDataBuilderTest.php`
Expected: FAIL — `Class "App\Domains\Akademik\Services\RaporPdfDataBuilder" not found`

- [ ] **Step 3: Tulis `RaporPdfDataBuilder` (fondasi + `templateUntukJenjang()` + `build()` TANPA field Ganjil/Genap dulu)**

Create `app/Domains/Akademik/Services/RaporPdfDataBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;

final class RaporPdfDataBuilder
{
    public function __construct(
        private readonly RaporCalculationService $raporCalculationService,
        private readonly CapaianKompetensiGenerator $capaianKompetensiGenerator,
        private readonly PresensiAggregationService $presensiAggregationService,
    ) {
    }

    /**
     * @return array{
     *   siswa: Siswa, kelas: \App\Models\Kelas, semester: Semester, lembaga: \App\Models\Lembaga,
     *   rekapNilai: array<int, float|null>, mapelList: \Illuminate\Support\Collection,
     *   narasiPerMapel: array<int, array{tertinggi: ?string, terendah: ?string}>,
     *   catatan: ?CatatanWaliKelas,
     *   absensi: array{hadir:int, izin:int, sakit:int, alpa:int, terlambat:int},
     *   pengajuanRapor: ?PengajuanRapor, isDraft: bool,
     *   namaWaliKelas: ?string, namaKepalaSekolah: ?string, namaOrangTua: ?string,
     * }
     */
    public function build(Siswa $siswa, Semester $semester): array
    {
        $kelas = $siswa->kelas;
        $lembaga = $kelas->lembaga;

        $rekap = $this->raporCalculationService->hitungRekapKelas($kelas, $semester);
        $mapelList = $rekap['mapelList'];
        $rekapNilaiSiswa = $rekap['rekapNilai'][$siswa->id] ?? [];

        $narasiPerMapel = [];
        foreach ($mapelList as $mapel) {
            $narasiPerMapel[$mapel->id] = $this->capaianKompetensiGenerator->generateNarasi($siswa, $mapel, $semester);
        }

        $catatan = CatatanWaliKelas::where('siswa_id', $siswa->id)->where('semester_id', $semester->id)->first();

        $absensiSemua = $this->presensiAggregationService->agregasiPerKelas($kelas->id, $semester);
        $absensiSiswa = $absensiSemua->firstWhere('siswa_id', $siswa->id) ?? [
            'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'terlambat' => 0,
        ];

        $pengajuanRapor = PengajuanRapor::where('kelas_id', $kelas->id)->where('semester_id', $semester->id)->first();
        $isDraft = $pengajuanRapor?->status !== StatusPengajuanRapor::Disetujui;

        $namaWaliKelas = $pengajuanRapor?->diverifikasi_oleh
            ? User::find($pengajuanRapor->diverifikasi_oleh)?->guru?->nama
            : null;
        $namaKepalaSekolah = $pengajuanRapor?->disetujui_oleh
            ? User::find($pengajuanRapor->disetujui_oleh)?->guru?->nama
            : null;
        $namaOrangTua = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first()?->nama_lengkap;

        return [
            'siswa' => $siswa,
            'kelas' => $kelas,
            'semester' => $semester,
            'lembaga' => $lembaga,
            'rekapNilai' => $rekapNilaiSiswa,
            'mapelList' => $mapelList,
            'narasiPerMapel' => $narasiPerMapel,
            'catatan' => $catatan,
            'absensi' => $absensiSiswa,
            'pengajuanRapor' => $pengajuanRapor,
            'isDraft' => $isDraft,
            'namaWaliKelas' => $namaWaliKelas,
            'namaKepalaSekolah' => $namaKepalaSekolah,
            'namaOrangTua' => $namaOrangTua,
        ];
    }

    /** Whitelist sama seperti field kondisional 04c — literal duplikasi disengaja (YAGNI). */
    public function templateUntukJenjang(string $bentukPendidikan): string
    {
        if (in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true)) {
            return 'pdf.rapor.paud';
        }

        if ($bentukPendidikan === 'SMK') {
            return 'pdf.rapor.smk';
        }

        if (in_array($bentukPendidikan, ['SMP', 'SMA'], true)) {
            return 'pdf.rapor.smp-sma';
        }

        return 'pdf.rapor.sd';
    }
}
```

- [ ] **Step 4: Jalankan test `templateUntukJenjang()`**

Run: `php artisan test tests/Feature/Akademik/RaporPdfDataBuilderTest.php`
Expected: PASS — 1 test lulus.

- [ ] **Step 5: Tulis test untuk `build()`**

Append ke `tests/Feature/Akademik/RaporPdfDataBuilderTest.php`:

```php
use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\ApprovePengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\WorkflowDefinitionSeeder;

function siapkanSiswaLengkapUntukPdf(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'urutan' => 1, 'nama' => 'Ganjil']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 90]);

    $orangTua = OrangTua::factory()->create(['nama_lengkap' => 'Budi Orang Tua']);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    return compact('yayasan', 'lembaga', 'tahunAjaran', 'semester', 'kelas', 'siswa', 'mapel');
}

it('builds a complete data array for a siswa with nilai, catatan, and approval', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanSiswaLengkapUntukPdf();

    $roleWaka = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web']);
    $userWaka = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $userWaka->assignRole($roleWaka);
    $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
    $userKepsek = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $userKepsek->assignRole($roleKepsek);
    \App\Models\Guru::factory()->create(['user_id' => $userWaka->id, 'lembaga_id' => $kelas->lembaga_id, 'nama' => 'Bu Waka']);
    \App\Models\Guru::factory()->create(['user_id' => $userKepsek->id, 'lembaga_id' => $kelas->lembaga_id, 'nama' => 'Pak Kepsek']);

    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id, 'catatan_sikap' => 'Baik']));
    $pengajuan = (new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $userWaka);
    (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWaka, ApprovalAction::Approve);
    (new ApprovePengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan->fresh(), $userKepsek, ApprovalAction::Approve);

    $data = app(\App\Domains\Akademik\Services\RaporPdfDataBuilder::class)->build($siswa->fresh(), $semester);

    expect($data['siswa']->id)->toBe($siswa->id);
    expect($data['catatan']->catatan_sikap)->toBe('Baik');
    expect($data['isDraft'])->toBeFalse();
    expect($data['namaWaliKelas'])->toBe('Bu Waka');
    expect($data['namaKepalaSekolah'])->toBe('Pak Kepsek');
    expect($data['namaOrangTua'])->toBe('Budi Orang Tua');
});

it('marks isDraft true and leaves signature names null when nothing has been submitted yet', function () {
    ['siswa' => $siswa, 'semester' => $semester] = siapkanSiswaLengkapUntukPdf();

    $data = app(\App\Domains\Akademik\Services\RaporPdfDataBuilder::class)->build($siswa, $semester);

    expect($data['isDraft'])->toBeTrue();
    expect($data['namaWaliKelas'])->toBeNull();
    expect($data['namaKepalaSekolah'])->toBeNull();
    expect($data['pengajuanRapor'])->toBeNull();
});
```

- [ ] **Step 6: Jalankan test**

Run: `php artisan test tests/Feature/Akademik/RaporPdfDataBuilderTest.php`
Expected: PASS — 3 test lulus.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Akademik/Services/RaporPdfDataBuilder.php tests/Feature/Akademik/RaporPdfDataBuilderTest.php
git commit -m "feat(akademik): tambah RaporPdfDataBuilder (data inti PDF rapor)"
```

---

### Task 4: `RaporPdfDataBuilder` — Perluasan Ganjil/Genap

**Files:**
- Modify: `app/Domains/Akademik/Services/RaporPdfDataBuilder.php`
- Test: `tests/Feature/Akademik/RaporPdfDataBuilderTest.php` (append)

**Interfaces:**
- Produces: `build()` sekarang mengembalikan 6 key TAMBAHAN: `isGenap: bool`, `isTingkatAkhir: bool`, `labelKenaikan: string`, `judulDokumen: string`, `absensiTahunan: ?array`, `nilaiRataRataTahunan: ?array`.

- [ ] **Step 1: Tulis test yang gagal**

Append ke `tests/Feature/Akademik/RaporPdfDataBuilderTest.php`:

```php
it('marks isGenap false and leaves tahunan fields null for a Ganjil semester', function () {
    ['siswa' => $siswa, 'semester' => $semester] = siapkanSiswaLengkapUntukPdf();

    $data = app(\App\Domains\Akademik\Services\RaporPdfDataBuilder::class)->build($siswa, $semester);

    expect($data['isGenap'])->toBeFalse();
    expect($data['absensiTahunan'])->toBeNull();
    expect($data['nilaiRataRataTahunan'])->toBeNull();
    expect($data['labelKenaikan'])->toBe('Keterangan Kenaikan Kelas');
});

it('sums absensi and averages nilai across Ganjil+Genap when the pair exists', function () {
    ['lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran, 'kelas' => $kelas, 'siswa' => $siswa, 'mapel' => $mapel, 'semester' => $semesterGanjil] = siapkanSiswaLengkapUntukPdf();

    // PENTING: SemesterFactory default tanggal_mulai/tanggal_selesai SAMA untuk semua instance
    // (keduanya now()..now()+6bulan) - kalau tidak di-override eksplisit dengan rentang yang
    // TIDAK TUMPANG TINDIH, PresensiAggregationService::agregasiPerKelas() (yang memfilter
    // berdasarkan rentang tanggal semester) tidak akan bisa membedakan sesi milik semester mana.
    $semesterGanjil->update(['tanggal_mulai' => now()->subMonths(6), 'tanggal_selesai' => now()->subDay()]);
    $semesterGenap = Semester::factory()->create([
        'tahun_ajaran_id' => $tahunAjaran->id, 'urutan' => 2, 'nama' => 'Genap',
        'tanggal_mulai' => now(), 'tanggal_selesai' => now()->addMonths(6),
    ]);
    $asesmenGenap = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semesterGenap->id]);
    $komponenGenap = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semesterGenap->id]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmenGenap->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenGenap->id, 'nilai_angka' => 80]);

    $sesiGanjil = \App\Domains\Akademik\Models\SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->subMonths(3)]);
    \App\Domains\Akademik\Models\Presensi::create(['sesi_pembelajaran_id' => $sesiGanjil->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);
    $sesiGenap = \App\Domains\Akademik\Models\SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()]);
    \App\Domains\Akademik\Models\Presensi::create(['sesi_pembelajaran_id' => $sesiGenap->id, 'siswa_id' => $siswa->id, 'status' => 'izin']);

    $data = app(\App\Domains\Akademik\Services\RaporPdfDataBuilder::class)->build($siswa, $semesterGenap);

    expect($data['isGenap'])->toBeTrue();
    expect($data['nilaiRataRataTahunan'][$mapel->id])->toBe(85.0);
    expect($data['absensi']['izin'])->toBe(1);
    expect($data['absensiTahunan']['hadir'])->toBe(1);
    expect($data['absensiTahunan']['izin'])->toBe(1);
});

it('keeps tahunan fields null in Genap when the Ganjil pair does not exist', function () {
    ['lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran, 'kelas' => $kelas, 'siswa' => $siswa] = siapkanSiswaLengkapUntukPdf();
    $semesterGenapTanpaPasangan = Semester::factory()->create(['tahun_ajaran_id' => TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]), 'urutan' => 2]);

    $data = app(\App\Domains\Akademik\Services\RaporPdfDataBuilder::class)->build($siswa, $semesterGenapTanpaPasangan);

    expect($data['isGenap'])->toBeTrue();
    expect($data['absensiTahunan'])->toBeNull();
    expect($data['nilaiRataRataTahunan'])->toBeNull();
});

it('labels kelulusan for a Genap semester at the final tingkat of SD, not for a non-final tingkat', function () {
    ['lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran, 'siswa' => $siswa] = siapkanSiswaLengkapUntukPdf();

    $kelasAkhir = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'tingkat' => '6']);
    $siswaAkhir = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasAkhir->id, 'status' => 'aktif']);
    $semesterGenap = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'urutan' => 2]);

    $dataAkhir = app(\App\Domains\Akademik\Services\RaporPdfDataBuilder::class)->build($siswaAkhir, $semesterGenap);
    expect($dataAkhir['isTingkatAkhir'])->toBeTrue();
    expect($dataAkhir['labelKenaikan'])->toBe('Keterangan Kelulusan');

    $kelasBukanAkhir = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'tingkat' => '3']);
    $siswaBukanAkhir = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasBukanAkhir->id, 'status' => 'aktif']);

    $dataBukanAkhir = app(\App\Domains\Akademik\Services\RaporPdfDataBuilder::class)->build($siswaBukanAkhir, $semesterGenap);
    expect($dataBukanAkhir['isTingkatAkhir'])->toBeFalse();
    expect($dataBukanAkhir['labelKenaikan'])->toBe('Keterangan Kenaikan Kelas');
});

it('builds a judulDokumen mentioning the semester name and tahun ajaran', function () {
    ['siswa' => $siswa, 'semester' => $semester, 'tahunAjaran' => $tahunAjaran] = siapkanSiswaLengkapUntukPdf();

    $data = app(\App\Domains\Akademik\Services\RaporPdfDataBuilder::class)->build($siswa, $semester);

    expect($data['judulDokumen'])->toContain($semester->nama);
    expect($data['judulDokumen'])->toContain($tahunAjaran->nama);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test tests/Feature/Akademik/RaporPdfDataBuilderTest.php`
Expected: FAIL — key `isGenap` dll tidak ada di array yang dikembalikan.

- [ ] **Step 3: Perluas `RaporPdfDataBuilder::build()`**

Edit `app/Domains/Akademik/Services/RaporPdfDataBuilder.php`. Tambahkan import setelah `use App\Models\User;`:

```php
use App\Models\Kelas;
```

Ganti isi method `build()` — tambahkan blok baru SEBELUM `return [...]`, dan tambahkan 6 key baru ke `return [...]`:

```php
    public function build(Siswa $siswa, Semester $semester): array
    {
        $kelas = $siswa->kelas;
        $lembaga = $kelas->lembaga;

        $rekap = $this->raporCalculationService->hitungRekapKelas($kelas, $semester);
        $mapelList = $rekap['mapelList'];
        $rekapNilaiSiswa = $rekap['rekapNilai'][$siswa->id] ?? [];

        $narasiPerMapel = [];
        foreach ($mapelList as $mapel) {
            $narasiPerMapel[$mapel->id] = $this->capaianKompetensiGenerator->generateNarasi($siswa, $mapel, $semester);
        }

        $catatan = CatatanWaliKelas::where('siswa_id', $siswa->id)->where('semester_id', $semester->id)->first();

        $absensiSemua = $this->presensiAggregationService->agregasiPerKelas($kelas->id, $semester);
        $absensiSiswa = $absensiSemua->firstWhere('siswa_id', $siswa->id) ?? [
            'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'terlambat' => 0,
        ];

        $pengajuanRapor = PengajuanRapor::where('kelas_id', $kelas->id)->where('semester_id', $semester->id)->first();
        $isDraft = $pengajuanRapor?->status !== StatusPengajuanRapor::Disetujui;

        $namaWaliKelas = $pengajuanRapor?->diverifikasi_oleh
            ? User::find($pengajuanRapor->diverifikasi_oleh)?->guru?->nama
            : null;
        $namaKepalaSekolah = $pengajuanRapor?->disetujui_oleh
            ? User::find($pengajuanRapor->disetujui_oleh)?->guru?->nama
            : null;
        $namaOrangTua = $siswa->orangTua()->wherePivot('is_kontak_utama', true)->first()?->nama_lengkap;

        $isGenap = $semester->urutan === 2;
        $isTingkatAkhir = $this->isTingkatAkhir($lembaga->bentuk_pendidikan, $kelas->tingkat);
        $labelKenaikan = ($isGenap && $isTingkatAkhir) ? 'Keterangan Kelulusan' : 'Keterangan Kenaikan Kelas';
        $judulDokumen = "Laporan Hasil Belajar Semester {$semester->nama} — {$kelas->tahunAjaran->nama}";

        $absensiTahunan = null;
        $nilaiRataRataTahunan = null;

        if ($isGenap) {
            $semesterGanjil = Semester::where('tahun_ajaran_id', $semester->tahun_ajaran_id)->where('urutan', 1)->first();

            if ($semesterGanjil) {
                $absensiGanjilSemua = $this->presensiAggregationService->agregasiPerKelas($kelas->id, $semesterGanjil);
                $absensiGanjilSiswa = $absensiGanjilSemua->firstWhere('siswa_id', $siswa->id) ?? [
                    'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'terlambat' => 0,
                ];

                $absensiTahunan = [
                    'hadir' => $absensiSiswa['hadir'] + $absensiGanjilSiswa['hadir'],
                    'izin' => $absensiSiswa['izin'] + $absensiGanjilSiswa['izin'],
                    'sakit' => $absensiSiswa['sakit'] + $absensiGanjilSiswa['sakit'],
                    'alpa' => $absensiSiswa['alpa'] + $absensiGanjilSiswa['alpa'],
                    'terlambat' => $absensiSiswa['terlambat'] + $absensiGanjilSiswa['terlambat'],
                ];

                $rekapGanjil = $this->raporCalculationService->hitungRekapKelas($kelas, $semesterGanjil);
                $rekapNilaiGanjilSiswa = $rekapGanjil['rekapNilai'][$siswa->id] ?? [];

                $nilaiRataRataTahunan = [];
                foreach ($mapelList as $mapel) {
                    $nilaiGenap = $rekapNilaiSiswa[$mapel->id] ?? null;
                    $nilaiGanjil = $rekapNilaiGanjilSiswa[$mapel->id] ?? null;

                    $nilaiRataRataTahunan[$mapel->id] = match (true) {
                        $nilaiGenap !== null && $nilaiGanjil !== null => round(($nilaiGenap + $nilaiGanjil) / 2, 1),
                        $nilaiGenap !== null => $nilaiGenap,
                        $nilaiGanjil !== null => $nilaiGanjil,
                        default => null,
                    };
                }
            }
        }

        return [
            'siswa' => $siswa,
            'kelas' => $kelas,
            'semester' => $semester,
            'lembaga' => $lembaga,
            'rekapNilai' => $rekapNilaiSiswa,
            'mapelList' => $mapelList,
            'narasiPerMapel' => $narasiPerMapel,
            'catatan' => $catatan,
            'absensi' => $absensiSiswa,
            'pengajuanRapor' => $pengajuanRapor,
            'isDraft' => $isDraft,
            'namaWaliKelas' => $namaWaliKelas,
            'namaKepalaSekolah' => $namaKepalaSekolah,
            'namaOrangTua' => $namaOrangTua,
            'isGenap' => $isGenap,
            'isTingkatAkhir' => $isTingkatAkhir,
            'labelKenaikan' => $labelKenaikan,
            'judulDokumen' => $judulDokumen,
            'absensiTahunan' => $absensiTahunan,
            'nilaiRataRataTahunan' => $nilaiRataRataTahunan,
        ];
    }

    private function isTingkatAkhir(?string $bentukPendidikan, ?string $tingkat): bool
    {
        $tingkatAkhirPerJenjang = [
            'SD' => '6',
            'SLB' => '6',
            'SMP' => '9',
            'SMA' => '12',
            'SMK' => '12',
        ];

        return isset($tingkatAkhirPerJenjang[$bentukPendidikan]) && $tingkatAkhirPerJenjang[$bentukPendidikan] === $tingkat;
    }
```

- [ ] **Step 4: Jalankan test**

Run: `php artisan test tests/Feature/Akademik/RaporPdfDataBuilderTest.php`
Expected: PASS — 8 test lulus total.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Akademik/Services/RaporPdfDataBuilder.php tests/Feature/Akademik/RaporPdfDataBuilderTest.php
git commit -m "feat(akademik): tambah logic Ganjil/Genap ke RaporPdfDataBuilder"
```

---

### Task 5: Partial + Template `paud.blade.php` + `sd.blade.php`

**Files:**
- Create: `resources/views/pdf/rapor/_identitas.blade.php`
- Create: `resources/views/pdf/rapor/_tanda-tangan.blade.php`
- Create: `resources/views/pdf/rapor/paud.blade.php`
- Create: `resources/views/pdf/rapor/sd.blade.php`

**Interfaces:**
- Consumes: array data dari `RaporPdfDataBuilder::build()` (Task 3+4) — semua key sudah final, tidak berubah lagi setelah task ini.

Referensi gaya CSS DomPDF yang SUDAH TERBUKTI JALAN di codebase ini (pakai gaya yang SAMA, jangan improvisasi CSS baru — DomPDF tidak mendukung semua CSS modern):

```blade
<style>
    body { font-family: sans-serif; font-size: 11px; color: #111827; }
    h1 { font-size: 15px; margin-bottom: 2px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #E5E7EB; padding: 5px 6px; text-align: center; }
    th { background-color: #F3F4F6; font-size: 10px; text-transform: uppercase; }
</style>
```

- [ ] **Step 1: Buat `_identitas.blade.php`**

Create `resources/views/pdf/rapor/_identitas.blade.php`:

```blade
<div style="margin-bottom: 16px;">
    <h1>{{ $judulDokumen }}</h1>
    <table style="border: none; margin-top: 8px;">
        <tr style="border: none;">
            <td style="border: none; text-align: left; padding: 2px 0; width: 50%;">
                <strong>{{ $lembaga->nama }}</strong><br>
                NPSN: {{ $lembaga->npsn ?: '-' }}<br>
                {{ $lembaga->alamat_jalan }}
            </td>
            <td style="border: none; text-align: left; padding: 2px 0;">
                Nama: <strong>{{ $siswa->nama_lengkap }}</strong><br>
                NIS/NISN: {{ $siswa->nis ?: '-' }} / {{ $siswa->nisn ?: '-' }}<br>
                Kelas: {{ $kelas->nama }}
            </td>
        </tr>
    </table>
</div>
```

- [ ] **Step 2: Buat `_tanda-tangan.blade.php`**

Create `resources/views/pdf/rapor/_tanda-tangan.blade.php`:

```blade
<table style="border: none; margin-top: 30px;">
    <tr style="border: none;">
        <td style="border: none; text-align: center; width: 33%;">
            Orang Tua/Wali
            <div style="height: 50px;"></div>
            <strong>{{ $namaOrangTua ?? '.....................' }}</strong>
        </td>
        <td style="border: none; text-align: center; width: 33%;">
            Wali Kelas
            <div style="height: 50px;"></div>
            <strong>{{ $namaWaliKelas ?? '(Menunggu Verifikasi)' }}</strong>
        </td>
        <td style="border: none; text-align: center; width: 33%;">
            Kepala Sekolah
            <div style="height: 50px;"></div>
            <strong>{{ $namaKepalaSekolah ?? '(Menunggu Persetujuan)' }}</strong>
        </td>
    </tr>
</table>
```

- [ ] **Step 3: Buat `paud.blade.php`**

Create `resources/views/pdf/rapor/paud.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 15px; margin-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #E5E7EB; padding: 5px 6px; text-align: left; }
        th { background-color: #F3F4F6; font-size: 10px; text-transform: uppercase; }
        .watermark { position: fixed; top: 40%; left: 15%; font-size: 60px; color: #EF4444; opacity: 0.15; transform: rotate(-30deg); z-index: -1; }
        .elemen-cp { margin-bottom: 14px; }
        .elemen-cp h3 { font-size: 12px; margin-bottom: 4px; }
    </style>
</head>
<body>
    @if($isDraft)
        <div class="watermark">DRAFT</div>
    @endif

    @include('pdf.rapor._identitas')

    @php
        $keranjangElemenCp = [
            'nilai_agama_moral' => ['label' => 'Nilai Agama dan Budi Pekerti', 'kalimat' => []],
            'jati_diri' => ['label' => 'Jati Diri', 'kalimat' => []],
            'literasi_steam' => ['label' => 'Literasi, STEAM, Seni, dan Budaya', 'kalimat' => []],
        ];
        foreach ($mapelList as $mapel) {
            $narasi = $narasiPerMapel[$mapel->id] ?? ['tertinggi' => null, 'terendah' => null];
            $komponenTerkait = \App\Domains\Akademik\Models\KomponenPenilaian::where('mata_pelajaran_id', $mapel->id)
                ->where('semester_id', $semester->id)
                ->whereNotNull('elemen_cp')
                ->first();
            $elemen = $komponenTerkait?->elemen_cp?->value;
            if ($elemen && isset($keranjangElemenCp[$elemen])) {
                if ($narasi['tertinggi']) { $keranjangElemenCp[$elemen]['kalimat'][] = $narasi['tertinggi']; }
                if ($narasi['terendah']) { $keranjangElemenCp[$elemen]['kalimat'][] = $narasi['terendah']; }
            }
        }
    @endphp

    <h2 style="font-size: 13px;">Capaian Pembelajaran</h2>
    @foreach ($keranjangElemenCp as $elemen)
        <div class="elemen-cp">
            <h3>{{ $elemen['label'] }}</h3>
            <p>{{ $elemen['kalimat'] ? implode(' ', $elemen['kalimat']) : 'Belum ada data capaian.' }}</p>
        </div>
    @endforeach

    <h2 style="font-size: 13px;">Pertumbuhan Fisik</h2>
    <table>
        <tr><th>Tinggi Badan</th><th>Berat Badan</th><th>Lingkar Kepala</th></tr>
        <tr>
            <td>{{ $catatan?->tinggi_badan_cm ? $catatan->tinggi_badan_cm.' cm' : '-' }}</td>
            <td>{{ $catatan?->berat_badan_kg ? $catatan->berat_badan_kg.' kg' : '-' }}</td>
            <td>{{ $catatan?->lingkar_kepala_cm ? $catatan->lingkar_kepala_cm.' cm' : '-' }}</td>
        </tr>
    </table>

    <h2 style="font-size: 13px; margin-top: 14px;">Catatan Wali Kelas</h2>
    <p>{{ $catatan?->catatan_sikap ?: '-' }}</p>
    <p>{{ $catatan?->catatan_perkembangan ?: '-' }}</p>

    @include('pdf.rapor._tanda-tangan')
</body>
</html>
```

- [ ] **Step 4: Buat `sd.blade.php`**

Create `resources/views/pdf/rapor/sd.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 15px; margin-bottom: 2px; }
        h2 { font-size: 13px; margin-top: 14px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #E5E7EB; padding: 5px 6px; text-align: center; }
        th { background-color: #F3F4F6; font-size: 10px; text-transform: uppercase; }
        td.nama, td.narasi { text-align: left; }
        .watermark { position: fixed; top: 40%; left: 15%; font-size: 60px; color: #EF4444; opacity: 0.15; transform: rotate(-30deg); z-index: -1; }
    </style>
</head>
<body>
    @if($isDraft)
        <div class="watermark">DRAFT</div>
    @endif

    @include('pdf.rapor._identitas')

    <h2>Nilai Akademik</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 30%;">Mata Pelajaran</th>
                <th style="width: 12%;">Nilai</th>
                @if ($isGenap && $nilaiRataRataTahunan !== null)
                    <th style="width: 12%;">Rata-Rata Tahunan</th>
                @endif
                <th>Capaian Kompetensi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($mapelList as $mapel)
                @php($narasi = $narasiPerMapel[$mapel->id] ?? ['tertinggi' => null, 'terendah' => null])
                <tr>
                    <td class="nama">{{ $mapel->nama }}</td>
                    <td>{{ $rekapNilai[$mapel->id] ?? '-' }}</td>
                    @if ($isGenap && $nilaiRataRataTahunan !== null)
                        <td>{{ $nilaiRataRataTahunan[$mapel->id] ?? '-' }}</td>
                    @endif
                    <td class="narasi">{{ trim(($narasi['tertinggi'] ?? '').' '.($narasi['terendah'] ?? '')) ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Belum ada data nilai.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Ekstrakurikuler</h2>
    <table>
        <thead><tr><th>Kegiatan</th><th>Peran</th></tr></thead>
        <tbody>
            @forelse (($catatan?->ekstrakurikuler ?? []) as $row)
                <tr><td class="nama">{{ $row['nama'] ?? '-' }}</td><td>{{ $row['peran'] ?? '-' }}</td></tr>
            @empty
                <tr><td colspan="2">Tidak mengikuti ekstrakurikuler.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Kehadiran Semester {{ $semester->nama }}</h2>
    <table>
        <thead><tr><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Terlambat</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $absensi['hadir'] }}</td><td>{{ $absensi['izin'] }}</td><td>{{ $absensi['sakit'] }}</td><td>{{ $absensi['alpa'] }}</td><td>{{ $absensi['terlambat'] }}</td>
            </tr>
        </tbody>
    </table>

    @if ($isGenap && $absensiTahunan !== null)
        <h2>Akumulasi Kehadiran Semester Ganjil + Genap</h2>
        <table>
            <thead><tr><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Terlambat</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $absensiTahunan['hadir'] }}</td><td>{{ $absensiTahunan['izin'] }}</td><td>{{ $absensiTahunan['sakit'] }}</td><td>{{ $absensiTahunan['alpa'] }}</td><td>{{ $absensiTahunan['terlambat'] }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <h2>Catatan Wali Kelas</h2>
    <p>{{ $catatan?->catatan_sikap ?: '-' }}</p>

    @if ($isGenap)
        <h2>{{ $labelKenaikan }}</h2>
        <p>{{ $catatan?->keterangan_kenaikan ?: '-' }}</p>
    @endif

    @include('pdf.rapor._tanda-tangan')
</body>
</html>
```

- [ ] **Step 5: Verifikasi manual dengan test integrasi controller (dulu belum bisa dites end-to-end — cek dulu bahwa view tidak error saat di-compile)**

Karena controller `cetak()` belum ada (Task 7/8), verifikasi view compile lewat tinker atau test langsung merender view dengan data dari `RaporPdfDataBuilder`:

Buat file test sementara HANYA untuk verifikasi compile — TIDAK perlu di-commit sebagai test permanen, cukup jalankan lalu hapus:

```bash
php artisan tinker --execute="
\$b = app(\App\Domains\Akademik\Services\RaporPdfDataBuilder::class);
echo 'OK - builder resolved';
"
```

Expected: `OK - builder resolved` tanpa error. (Verifikasi PENUH bahwa Blade view benar-benar compile dengan data asli akan terjadi otomatis di Task 7/8's test lewat HTTP request sungguhan — langkah ini hanya memastikan tidak ada typo fatal PHP di file baru.)

- [ ] **Step 6: Commit**

```bash
git add resources/views/pdf/rapor/_identitas.blade.php resources/views/pdf/rapor/_tanda-tangan.blade.php resources/views/pdf/rapor/paud.blade.php resources/views/pdf/rapor/sd.blade.php
git commit -m "feat(akademik): tambah template PDF rapor PAUD dan SD"
```

---

### Task 6: Template `smp-sma.blade.php` + `smk.blade.php`

**Files:**
- Create: `resources/views/pdf/rapor/smp-sma.blade.php`
- Create: `resources/views/pdf/rapor/smk.blade.php`

**Interfaces:**
- Consumes: sama seperti Task 5, TAMBAH `App\Enums\KelompokMataPelajaran` (enum yang SUDAH ADA sejak sebelum sub-task ini, dipakai `MataPelajaran.kelompok`) untuk pengelompokan sub-header tabel nilai.

- [ ] **Step 1: Buat `smp-sma.blade.php`**

Create `resources/views/pdf/rapor/smp-sma.blade.php` — identik `sd.blade.php` DITAMBAH pengelompokan mapel by `kelompok`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 15px; margin-bottom: 2px; }
        h2 { font-size: 13px; margin-top: 14px; margin-bottom: 4px; }
        h3.kelompok { font-size: 11px; background-color: #F3F4F6; padding: 4px 6px; margin: 8px 0 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #E5E7EB; padding: 5px 6px; text-align: center; }
        th { background-color: #F3F4F6; font-size: 10px; text-transform: uppercase; }
        td.nama, td.narasi { text-align: left; }
        .watermark { position: fixed; top: 40%; left: 15%; font-size: 60px; color: #EF4444; opacity: 0.15; transform: rotate(-30deg); z-index: -1; }
    </style>
</head>
<body>
    @if($isDraft)
        <div class="watermark">DRAFT</div>
    @endif

    @include('pdf.rapor._identitas')

    <h2>Nilai Akademik</h2>
    @php($mapelPerKelompok = $mapelList->groupBy(fn ($mapel) => $mapel->kelompok?->label() ?? 'Lainnya'))
    @foreach ($mapelPerKelompok as $namaKelompok => $mapelDalamKelompok)
        <h3 class="kelompok">{{ $namaKelompok }}</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 30%;">Mata Pelajaran</th>
                    <th style="width: 12%;">Nilai</th>
                    @if ($isGenap && $nilaiRataRataTahunan !== null)
                        <th style="width: 12%;">Rata-Rata Tahunan</th>
                    @endif
                    <th>Capaian Kompetensi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($mapelDalamKelompok as $mapel)
                    @php($narasi = $narasiPerMapel[$mapel->id] ?? ['tertinggi' => null, 'terendah' => null])
                    <tr>
                        <td class="nama">{{ $mapel->nama }}</td>
                        <td>{{ $rekapNilai[$mapel->id] ?? '-' }}</td>
                        @if ($isGenap && $nilaiRataRataTahunan !== null)
                            <td>{{ $nilaiRataRataTahunan[$mapel->id] ?? '-' }}</td>
                        @endif
                        <td class="narasi">{{ trim(($narasi['tertinggi'] ?? '').' '.($narasi['terendah'] ?? '')) ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <h2>Ekstrakurikuler</h2>
    <table>
        <thead><tr><th>Kegiatan</th><th>Peran</th></tr></thead>
        <tbody>
            @forelse (($catatan?->ekstrakurikuler ?? []) as $row)
                <tr><td class="nama">{{ $row['nama'] ?? '-' }}</td><td>{{ $row['peran'] ?? '-' }}</td></tr>
            @empty
                <tr><td colspan="2">Tidak mengikuti ekstrakurikuler.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Kehadiran Semester {{ $semester->nama }}</h2>
    <table>
        <thead><tr><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Terlambat</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $absensi['hadir'] }}</td><td>{{ $absensi['izin'] }}</td><td>{{ $absensi['sakit'] }}</td><td>{{ $absensi['alpa'] }}</td><td>{{ $absensi['terlambat'] }}</td>
            </tr>
        </tbody>
    </table>

    @if ($isGenap && $absensiTahunan !== null)
        <h2>Akumulasi Kehadiran Semester Ganjil + Genap</h2>
        <table>
            <thead><tr><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Terlambat</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $absensiTahunan['hadir'] }}</td><td>{{ $absensiTahunan['izin'] }}</td><td>{{ $absensiTahunan['sakit'] }}</td><td>{{ $absensiTahunan['alpa'] }}</td><td>{{ $absensiTahunan['terlambat'] }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <h2>Catatan Wali Kelas</h2>
    <p>{{ $catatan?->catatan_sikap ?: '-' }}</p>

    @if ($isGenap)
        <h2>{{ $labelKenaikan }}</h2>
        <p>{{ $catatan?->keterangan_kenaikan ?: '-' }}</p>
    @endif

    @include('pdf.rapor._tanda-tangan')
</body>
</html>
```

- [ ] **Step 2: Buat `smk.blade.php`**

Create `resources/views/pdf/rapor/smk.blade.php` — identik `smp-sma.blade.php` DITAMBAH tabel Keterangan PKL sebelum blok tanda tangan:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 15px; margin-bottom: 2px; }
        h2 { font-size: 13px; margin-top: 14px; margin-bottom: 4px; }
        h3.kelompok { font-size: 11px; background-color: #F3F4F6; padding: 4px 6px; margin: 8px 0 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #E5E7EB; padding: 5px 6px; text-align: center; }
        th { background-color: #F3F4F6; font-size: 10px; text-transform: uppercase; }
        td.nama, td.narasi { text-align: left; }
        .watermark { position: fixed; top: 40%; left: 15%; font-size: 60px; color: #EF4444; opacity: 0.15; transform: rotate(-30deg); z-index: -1; }
    </style>
</head>
<body>
    @if($isDraft)
        <div class="watermark">DRAFT</div>
    @endif

    @include('pdf.rapor._identitas')

    <h2>Nilai Akademik</h2>
    @php($mapelPerKelompok = $mapelList->groupBy(fn ($mapel) => $mapel->kelompok?->label() ?? 'Lainnya'))
    @foreach ($mapelPerKelompok as $namaKelompok => $mapelDalamKelompok)
        <h3 class="kelompok">{{ $namaKelompok }}</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 30%;">Mata Pelajaran</th>
                    <th style="width: 12%;">Nilai</th>
                    @if ($isGenap && $nilaiRataRataTahunan !== null)
                        <th style="width: 12%;">Rata-Rata Tahunan</th>
                    @endif
                    <th>Capaian Kompetensi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($mapelDalamKelompok as $mapel)
                    @php($narasi = $narasiPerMapel[$mapel->id] ?? ['tertinggi' => null, 'terendah' => null])
                    <tr>
                        <td class="nama">{{ $mapel->nama }}</td>
                        <td>{{ $rekapNilai[$mapel->id] ?? '-' }}</td>
                        @if ($isGenap && $nilaiRataRataTahunan !== null)
                            <td>{{ $nilaiRataRataTahunan[$mapel->id] ?? '-' }}</td>
                        @endif
                        <td class="narasi">{{ trim(($narasi['tertinggi'] ?? '').' '.($narasi['terendah'] ?? '')) ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <h2>Keterangan PKL</h2>
    <table>
        <thead><tr><th>Perusahaan</th><th>Posisi</th><th>Durasi</th></tr></thead>
        <tbody>
            @forelse (($catatan?->pkl_info ?? []) as $row)
                <tr>
                    <td class="nama">{{ $row['perusahaan'] ?? '-' }}</td>
                    <td>{{ $row['posisi'] ?? '-' }}</td>
                    <td>{{ $row['durasi'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Belum ada data PKL.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Ekstrakurikuler</h2>
    <table>
        <thead><tr><th>Kegiatan</th><th>Peran</th></tr></thead>
        <tbody>
            @forelse (($catatan?->ekstrakurikuler ?? []) as $row)
                <tr><td class="nama">{{ $row['nama'] ?? '-' }}</td><td>{{ $row['peran'] ?? '-' }}</td></tr>
            @empty
                <tr><td colspan="2">Tidak mengikuti ekstrakurikuler.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Kehadiran Semester {{ $semester->nama }}</h2>
    <table>
        <thead><tr><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Terlambat</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $absensi['hadir'] }}</td><td>{{ $absensi['izin'] }}</td><td>{{ $absensi['sakit'] }}</td><td>{{ $absensi['alpa'] }}</td><td>{{ $absensi['terlambat'] }}</td>
            </tr>
        </tbody>
    </table>

    @if ($isGenap && $absensiTahunan !== null)
        <h2>Akumulasi Kehadiran Semester Ganjil + Genap</h2>
        <table>
            <thead><tr><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Terlambat</th></tr></thead>
            <tbody>
                <tr>
                    <td>{{ $absensiTahunan['hadir'] }}</td><td>{{ $absensiTahunan['izin'] }}</td><td>{{ $absensiTahunan['sakit'] }}</td><td>{{ $absensiTahunan['alpa'] }}</td><td>{{ $absensiTahunan['terlambat'] }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <h2>Catatan Wali Kelas</h2>
    <p>{{ $catatan?->catatan_sikap ?: '-' }}</p>

    @if ($isGenap)
        <h2>{{ $labelKenaikan }}</h2>
        <p>{{ $catatan?->keterangan_kenaikan ?: '-' }}</p>
    @endif

    @include('pdf.rapor._tanda-tangan')
</body>
</html>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/pdf/rapor/smp-sma.blade.php resources/views/pdf/rapor/smk.blade.php
git commit -m "feat(akademik): tambah template PDF rapor SMP/SMA dan SMK"
```

---

### Task 7: `Guru\RaporController::cetak()`

**Files:**
- Modify: `app/Http/Controllers/Guru/RaporController.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/portals/guru/rapor/catatan/index.blade.php`
- Test: `tests/Feature/Guru/RaporControllerTest.php` (append)

**Interfaces:**
- Consumes: `RaporPdfDataBuilder::build()`/`templateUntukJenjang()` (Task 3+4). `Guru\RaporController` konstruktor SUDAH ADA dari 04c — task ini menambah SATU dependency baru.

- [ ] **Step 1: Tambahkan dependency & method `cetak()`**

Edit `app/Http/Controllers/Guru/RaporController.php`. Tambahkan import:

```php
use App\Domains\Akademik\Services\RaporPdfDataBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
```

Ubah constructor — tambahkan parameter baru:

```php
    public function __construct(
        private readonly SimpanCatatanWaliKelasAction $simpanCatatanWaliKelasAction,
        private readonly SubmitPengajuanRaporAction $submitPengajuanRaporAction,
        private readonly GenerateNarasiPerkembanganAction $generateNarasiPerkembanganAction,
        private readonly RaporPdfDataBuilder $raporPdfDataBuilder,
    ) {
    }
```

Tambahkan method baru SETELAH `ajukan()` (method terakhir di file):

```php
    public function cetak(Siswa $siswa, Request $request): Response
    {
        $this->authorize('rapor.input-wali');

        $guru = $request->user()->guru;
        abort_if($guru === null, 403);
        abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403);

        $semester = Semester::find((int) $request->query('semester_id'));
        abort_if($semester === null, 404);

        $data = $this->raporPdfDataBuilder->build($siswa, $semester);
        $template = $this->raporPdfDataBuilder->templateUntukJenjang($siswa->kelas->lembaga->bentuk_pendidikan);

        $pdf = Pdf::loadView($template, $data);

        return $pdf->stream('rapor-'.Str::slug($siswa->nama_lengkap).'.pdf');
    }
```

- [ ] **Step 2: Tambahkan route**

Edit `routes/admin.php`. Di dalam grup `guru.` (cari baris `Route::post('rapor/ajukan', ...)`), tambahkan SETELAH baris itu (baris terakhir di grup, sebelum `});` penutup):

```php
    Route::get('rapor/cetak/{siswa}', [GuruRaporController::class, 'cetak'])->name('rapor.cetak');
```

- [ ] **Step 3: Tambahkan tombol cetak di view**

Edit `resources/views/portals/guru/rapor/catatan/index.blade.php`. Cari baris `<a href="{{ route('guru.rapor.catatan.edit', ...` di dalam `<td class="px-5 py-3.5 text-right">`. Tambahkan link baru TEPAT SETELAH link "Isi Catatan" itu, di dalam `<td>` yang sama:

```blade
                                        <a href="{{ route('guru.rapor.catatan.edit', ['siswa' => $siswa->id, 'semester_id' => $semester->id]) }}" class="font-semibold text-brand-600 hover:underline">
                                            Isi Catatan
                                        </a>
                                        <span class="text-gray-300 mx-1">|</span>
                                        <a href="{{ route('guru.rapor.cetak', ['siswa' => $siswa->id, 'semester_id' => $semester->id]) }}" target="_blank" class="font-semibold text-gray-500 hover:underline">
                                            Cetak PDF
                                        </a>
```

- [ ] **Step 4: Tulis test**

Append ke `tests/Feature/Guru/RaporControllerTest.php`:

```php
it('streams a pdf for a siswa the guru is wali kelas of', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.cetak', ['siswa' => $siswa->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('rejects printing a pdf for a siswa the guru is not wali kelas of', function () {
    ['guruUser' => $guruUser, 'lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLain->id]);

    $this->actingAs($guruUser)
        ->get(route('guru.rapor.cetak', ['siswa' => $siswaLain->id, 'semester_id' => $semester->id]))
        ->assertForbidden();
});
```

- [ ] **Step 5: Jalankan test**

Run: `php artisan test tests/Feature/Guru/RaporControllerTest.php`
Expected: PASS — semua test lama + 2 test baru lulus.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Guru/RaporController.php routes/admin.php resources/views/portals/guru/rapor/catatan/index.blade.php tests/Feature/Guru/RaporControllerTest.php
git commit -m "feat(akademik): cetak() Guru\\RaporController - PDF rapor sisi wali kelas"
```

---

### Task 8: `Lembaga\Rapor\PersetujuanController::cetak()`

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`
- Modify: `routes/admin.php`
- Modify: `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php`
- Test: `tests/Feature/Rapor/RaporPersetujuanControllerTest.php` (append)

**Interfaces:**
- Consumes: `RaporPdfDataBuilder::build()`/`templateUntukJenjang()` (Task 3+4). `Lembaga\Rapor\PersetujuanController` konstruktor SUDAH ADA dari 04c — task ini menambah SATU dependency baru.

- [ ] **Step 1: Tambahkan dependency & method `cetak()`**

Edit `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`. Tambahkan import:

```php
use App\Domains\Akademik\Services\RaporPdfDataBuilder;
use App\Models\Siswa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
```

Ubah constructor — tambahkan parameter baru:

```php
    public function __construct(
        private readonly RaporCalculationService $raporCalculationService,
        private readonly VerifyPengajuanRaporAction $verifyPengajuanRaporAction,
        private readonly ApprovePengajuanRaporAction $approvePengajuanRaporAction,
        private readonly RaporPdfDataBuilder $raporPdfDataBuilder,
    ) {
    }
```

Tambahkan method baru SETELAH `show()` (SEBELUM `decision()`):

```php
    public function cetak(PengajuanRapor $pengajuanRapor, Siswa $siswa, Request $request): \Illuminate\Http\Response
    {
        abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);
        abort_unless($siswa->kelas_id === $pengajuanRapor->kelas_id, 404);

        $data = $this->raporPdfDataBuilder->build($siswa, $pengajuanRapor->semester);
        $template = $this->raporPdfDataBuilder->templateUntukJenjang($pengajuanRapor->kelas->lembaga->bentuk_pendidikan);

        $pdf = Pdf::loadView($template, $data);

        return $pdf->stream('rapor-'.Str::slug($siswa->nama_lengkap).'.pdf');
    }
```

- [ ] **Step 2: Tambahkan route**

Edit `routes/admin.php`. Cari baris `Route::post('rapor/persetujuan/{pengajuanRapor}/keputusan', ...)`, tambahkan SETELAH baris itu:

```php
    Route::get('rapor/persetujuan/{pengajuanRapor}/cetak/{siswa}', [\App\Http\Controllers\Lembaga\Rapor\PersetujuanController::class, 'cetak'])->name('rapor.persetujuan.cetak');
```

- [ ] **Step 3: Tambahkan tombol cetak di view**

Edit `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php`. Cari blok `@foreach ($siswaList as $siswa)` di bagian "Catatan Wali Kelas Per Siswa" — di dalam `<p class="font-semibold text-gray-900 mb-2">{{ $siswa->nama_lengkap }}</p>`, tambahkan link cetak TEPAT SETELAH baris itu (masih di dalam `<div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">` yang sama):

```blade
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-card">
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-semibold text-gray-900">{{ $siswa->nama_lengkap }}</p>
                        <a href="{{ route('admin.rapor.persetujuan.cetak', ['pengajuanRapor' => $pengajuanRapor->id, 'siswa' => $siswa->id]) }}" target="_blank" class="text-xs font-semibold text-brand-600 hover:underline">
                            Cetak PDF
                        </a>
                    </div>
```

(Baris `<p class="font-semibold text-gray-900 mb-2">{{ $siswa->nama_lengkap }}</p>` yang lama DIHAPUS, diganti blok `<div class="flex items-center justify-between mb-2">...` di atas.)

- [ ] **Step 4: Tulis test**

Append ke `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`:

```php
it('streams a pdf for Waka without requiring the step-matching guard', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'pengajuan' => $pengajuan, 'siswa' => $siswa] = siapkanAktorPersetujuan();

    $response = $this->actingAs($userWaka)->get(route('admin.rapor.persetujuan.cetak', ['pengajuanRapor' => $pengajuan->id, 'siswa' => $siswa->id]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('streams a pdf for Kepsek even before the pengajuan reaches their step', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userKepsek' => $userKepsek, 'pengajuan' => $pengajuan, 'siswa' => $siswa] = siapkanAktorPersetujuan();

    $response = $this->actingAs($userKepsek)->get(route('admin.rapor.persetujuan.cetak', ['pengajuanRapor' => $pengajuan->id, 'siswa' => $siswa->id]));

    $response->assertOk();
});

it('rejects printing a siswa that does not belong to the pengajuan kelas', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'pengajuan' => $pengajuan, 'lembaga' => $lembaga] = siapkanAktorPersetujuan();
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id])]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLain->id]);

    $this->actingAs($userWaka)
        ->get(route('admin.rapor.persetujuan.cetak', ['pengajuanRapor' => $pengajuan->id, 'siswa' => $siswaLain->id]))
        ->assertNotFound();
});
```

- [ ] **Step 5: Jalankan test**

Run: `php artisan test tests/Feature/Rapor/RaporPersetujuanControllerTest.php`
Expected: PASS — semua test lama + 3 test baru lulus.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php routes/admin.php resources/views/portals/lembaga/rapor/persetujuan/show.blade.php tests/Feature/Rapor/RaporPersetujuanControllerTest.php
git commit -m "feat(akademik): cetak() Lembaga\\Rapor\\PersetujuanController - PDF rapor sisi Waka/Kepsek"
```

---

### Task 9: Verifikasi Akhir, Master Plan, Handoff Log

**Files:**
- Modify: `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`
- Create: `.agents/logs/2026-08-19-1900-akademik-04d-rapor-pdf.md`

- [ ] **Step 1: Jalankan seluruh test scoped sub-task ini sekali lagi sebagai regression bundle**

Run: `php artisan test tests/Feature/Akademik/RaporPdfDataBuilderTest.php tests/Feature/Admin/KomponenPenilaianCrudTest.php tests/Feature/Guru/KomponenPenilaianControllerTest.php tests/Feature/Guru/RaporControllerTest.php tests/Feature/Rapor/RaporPersetujuanControllerTest.php`
Expected: semua PASS, 0 gagal.

- [ ] **Step 2: Minta izin user, lalu jalankan full test suite**

Tanya: "Sub-Task 04d selesai diimplementasikan. Jalankan full test suite `php artisan test` sekarang untuk verifikasi akhir?"

Kalau disetujui, jalankan (sinkron, JANGAN di-background):

Run: `php artisan test`
Expected: semua lulus, 0 gagal. (Baseline sebelum sub-task ini ~1824 test dari 04c — sub-task ini menambah beberapa lagi. Angka pasti bisa beda tipis, yang penting **0 gagal**.)

Kalau ada yang gagal, perbaiki dulu sebelum lanjut ke Step 3 — jangan menulis handoff log dengan kegagalan yang diketahui.

- [ ] **Step 3: Update master plan**

Edit `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`. Cari baris:

```
| **04d** | **4 Template PDF Berjenjang** | `.agents/specs/akademik-04d-rapor-pdf.md` | `.agents/plans/akademik-04d-rapor-pdf.md` | `.agents/logs/akademik-04d-rapor-pdf.md` | ⚪ PENDING |
```

Ganti dengan:

```
| **04d** | **4 Template PDF Berjenjang** | [`.agents/specs/2026-08-19-1900-akademik-04d-rapor-pdf.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-19-1900-akademik-04d-rapor-pdf.md) | [`.agents/plans/2026-08-19-1900-akademik-04d-rapor-pdf.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-19-1900-akademik-04d-rapor-pdf.md) | [`.agents/logs/2026-08-19-1900-akademik-04d-rapor-pdf.md`](file:///d:/laragon/www/pintera-app/.agents/logs/2026-08-19-1900-akademik-04d-rapor-pdf.md) | 🟢 **SELESAI (COMPLETED)** |
```

Cari baris:

```
- **Status Master:** 🟡 IN PROGRESS (Sub-Task 01, 02, 03a, 03b, 03c, 04a, 04b, 04c SELESAI — Sub-Task 04d belum dimulai)
```

Ganti dengan:

```
- **Status Master:** 🟢 SELESAI SEMUA SUB-TASK (01, 02, 03a, 03b, 03c, 04a, 04b, 04c, 04d) — modul Adaptive E-Rapor Engine lengkap end-to-end.
```

- [ ] **Step 4: Tulis handoff log**

Create `.agents/logs/2026-08-19-1900-akademik-04d-rapor-pdf.md`:

```markdown
# 📋 Handoff Log: Sub-Task 04d — Adaptive E-Rapor Engine: 4 Template PDF Berjenjang

- **Spec:** [`.agents/specs/2026-08-19-1900-akademik-04d-rapor-pdf.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-19-1900-akademik-04d-rapor-pdf.md)
- **Plan:** [`.agents/plans/2026-08-19-1900-akademik-04d-rapor-pdf.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-19-1900-akademik-04d-rapor-pdf.md)
- **Status:** 🟢 SELESAI

## Ringkasan

4 template PDF rapor resmi per-siswa (`paud`/`sd`/`smp-sma`/`smk`) via `RaporPdfDataBuilder` baru, dikonsumsi lewat method `cetak()` yang ditambahkan ke controller 04c yang sudah ada (`Guru\RaporController`, `Lembaga\Rapor\PersetujuanController`) — tidak ada controller baru. Sekaligus memperluas `KomponenPenilaian` dengan `elemen_cp` (kategori CP PAUD resmi Kurikulum Merdeka) DAN memperbaiki celah UI `kktp_minimal` yang terlewat sejak 04b (backend ada sejak 04b, form pengisian baru ada sekarang). PDF bisa dicetak kapan saja sebagai draft (watermark otomatis), rapor Genap menambahkan Keterangan Kenaikan Kelas/Kelulusan + akumulasi absensi tahunan + nilai rata-rata tahunan dibanding Ganjil.

## Item Terbuka

1. Gap arsitektur `TenantScope.php` tanpa filter yayasan (ditemukan di 04a) masih terbuka, belum diputuskan — di luar scope seluruh rangkaian Adaptive E-Rapor Engine (04a-04d).
2. Nilai numerik PKL Industri dan portofolio UKK untuk SMK sengaja di luar scope 04d (lihat spec §1) — butuh modul PKL/UKK tersendiri kalau dibutuhkan di masa depan.

**Modul Adaptive E-Rapor Engine (Sub-Task 04a-04d) SELESAI SEPENUHNYA.**
```

- [ ] **Step 5: Commit**

```bash
git add .agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md .agents/logs/2026-08-19-1900-akademik-04d-rapor-pdf.md
git commit -m "docs(akademik): tutup Sub-Task 04d, update master plan & handoff log - modul E-Rapor selesai"
```

---

## Self-Review Notes

- **Spec coverage**: §2.1-2.5 (draft/watermark, akses, elemen_cp, PKL out-of-scope, tanda tangan) → Task 1, 3, 4, 7, 8. §2.6 (Ganjil/Genap) → Task 4, 5, 6. §3 (elemen_cp + kktp_minimal UI fix) → Task 1, 2. §4 (RaporPdfDataBuilder) → Task 3, 4. §5 (controller/route) → Task 7, 8. §6 (4 template) → Task 5, 6. §7 (testing) → tiap task. Tidak ada gap.
- **Placeholder scan**: bersih — semua step berisi kode lengkap. Test baru di Task 1/2 (`tests/Feature/Admin/KomponenPenilaianCrudTest.php`) sudah diverifikasi memakai helper NYATA yang benar-benar ada di file itu (`actingAsKomponenManager(Lembaga $lembaga): User`, baris 17-27), bukan nama karangan. Ditemukan pula bug tersembunyi saat self-review: `SemesterFactory` memberi tanggal default yang SAMA untuk semua instance (`now()`..`now()+6bulan`), yang akan merusak filter tanggal `PresensiAggregationService` kalau test Task 4 membuat 2 semester tanpa override tanggal eksplisit — sudah diperbaiki di test Task 4 Step 1 (`$semesterGanjil->update([...])` + `tanggal_mulai`/`tanggal_selesai` eksplisit saat membuat `$semesterGenap`).
- **Konsistensi tipe/nama**: `RaporPdfDataBuilder::build()` return shape SAMA PERSIS antara Task 3 (draft awal) dan Task 4 (final, +6 key) — Task 4 me-replace method itu utuh, bukan menambah terpisah, supaya tidak ada drift. `templateUntukJenjang()` tidak berubah sejak Task 3. Nama route (`guru.rapor.cetak`, `admin.rapor.persetujuan.cetak`) konsisten dipakai di controller (Task 7/8), routes (Task 7/8), dan view (Task 7/8).
