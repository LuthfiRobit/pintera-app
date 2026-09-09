# Perbaikan TP (Komponen Penilaian) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Perbaiki bug kritis Edit TP (Admin) yang gagal total untuk TP belum dipakai, dengan menyelaraskan validasi Admin ke desain "Subjek/Semester terkunci permanen" yang sudah benar di sisi Guru (Opsi A, dikonfirmasi user). Plus 3 item minor: badge scope, hardcode PAUD list, konsistensi default bobot.

**Architecture:** Task 1 (kritis, berdiri sendiri) mengubah 5 file kode + 3 test existing dalam 1 perubahan kohesif (FormRequest, Action, DTO, view, JS semuanya harus berubah BERSAMAAN supaya konsisten — tidak bisa dipecah tanpa periode transisi yang rusak). Task 2-4 independen satu sama lain dan dari Task 1. Task 5 penutup.

**Tech Stack:** Laravel 12, Blade, Alpine.js, Pest (function-style test).

## Global Constraints

- **Backend inti TIDAK diubah** — guard bobot 100% (`lockForUpdate()`), proteksi hapus/ubah TP yang sudah dipakai, cross-tenant check `mata_pelajaran` di Store — SEMUA tetap apa adanya. Task 1 HANYA menghapus kemampuan reassignment subjek/semester yang TIDAK PERNAH punya jalur UI nyata (dibuktikan lewat 2 sesi audit terpisah + perbandingan langsung dengan sisi Guru).
- **Jalur Guru (`Guru\KomponenPenilaianController`, `UpdateKomponenPenilaianSendiriRequest`, `portals/guru/...`) TIDAK disentuh** — sudah benar, jadi RUJUKAN untuk Task 1, bukan target perubahan.
- **Task 1 WAJIB mengubah KETIGA test lama** (`KomponenPenilaianCrudTest.php` baris ±258, ±592, ±632) — SEMUANYA menguji kemampuan reassignment subjek/semester yang sengaja dihapus. Test baris ±674 ("does not touch lembaga_id...") TIDAK diubah (sudah dicek, assertion-nya tetap benar dengan kode baru).
- **`UpdateKomponenPenilaianData` constructor berubah** (3 properti dihapus: `subjekType`, `subjekId`, `semesterId`) — sudah dicek TIDAK ADA pemanggilan `new UpdateKomponenPenilaianData(...)` di luar `fromArray()` di seluruh codebase, aman.
- **`komponenPenilaianEditForm()` (JS) TETAP ADA sebagai fungsi (objek kosong)** — JANGAN dihapus totalnya, `edit.blade.php` masih memanggilnya lewat `x-data="komponenPenilaianEditForm()"`.

---

## Konteks File yang Sudah Ada (baca sebelum mulai)

- `app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php` — `rules()` saat ini mewajibkan `subjek_type`/`subjek_id`/`semester_id`/`assessment_type` saat `!$dipakai`. `edit.blade.php` (Admin) TIDAK PERNAH mengirim 3 field pertama (dikonfirmasi grep, 0 match) — SATU-SATUNYA sebab bug kritis Task 1.
- `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php` — logic update `assessment_type` SAAT INI ada DI DALAM blok kondisi reassignment subjek (baris 23-31), jadi ikut tidak pernah jalan meski field itu punya UI edit yang valid.
- `resources/views/portals/guru/akademik/komponen-penilaian/edit.blade.php` baris 66 — SUDAH punya teks "Subjek Penilaian dan Semester tidak bisa diubah di sini — hapus lalu buat TP baru..." — SALIN teks ini kata-per-kata ke `edit.blade.php` Admin (Task 1 poin 4).
- `resources/js/komponen-penilaian-edit.js` — `initMataPelajaranSelect()`/`initSemesterSelect()` TIDAK PERNAH dipanggil di `edit.blade.php` Admin manapun — kode mati, dibersihkan Task 1.
- `tests/Feature/Admin/KomponenPenilaianCrudTest.php` — helper `actingAsKomponenManager(Lembaga $lembaga)` (lembaga-scope) dan `actingAsYayasanKomponenManager(Yayasan $yayasan)` (yayasan-scope) SUDAH ADA, JANGAN bikin baru.
- `app/Http/Controllers/Admin/KomponenPenilaianController.php` — SAAT INI TIDAK `use ResolveLembagaScopeTrait;` sama sekali (beda dari kebanyakan controller lain yang sudah diaudit sepanjang sesi ini) — Task 2 WAJIB menambahkannya.

---

### Task 1: 🔴 Selaraskan Validasi Edit Admin dengan Desain "Subjek/Semester Terkunci Permanen"

**Files:**
- Modify: `app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`
- Modify: `app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php`
- Modify: `resources/views/portals/lembaga/akademik/komponen-penilaian/edit.blade.php`
- Modify: `resources/js/komponen-penilaian-edit.js`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- `UpdateKomponenPenilaianData` constructor SEKARANG cuma 6 parameter (`kode`, `deskripsi`, `bobot`, `kktp`, `kktpMinimal`, `assessmentType`) — turun dari 9. TIDAK ADA caller lain yang perlu disesuaikan (sudah dicek).

- [ ] **Step 1: Tulis test yang gagal (bukti bug utama)**

Tambahkan ke `tests/Feature/Admin/KomponenPenilaianCrudTest.php` (akhir file):

```php
it('successfully saves kode, deskripsi, bobot, kktp, and assessment_type edits for a TP that is not yet used, using the exact payload the real edit form sends', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $createAction = app(CreateKomponenPenilaianAction::class);
    $komponen = $createAction->execute(new KomponenPenilaianData(
        subjekType: 'mata_pelajaran',
        subjekId: $mapel->id,
        semesterId: $semester->id,
        kode: 'TP 1.1',
        deskripsi: 'Deskripsi Awal',
        bobot: 50,
        kktp: null,
        kktpMinimal: null,
        assessmentType: 'numeric',
    ));

    // Payload PERSIS seperti yang dikirim edit.blade.php Admin SAAT INI --
    // TIDAK ADA subjek_type/subjek_id/semester_id sama sekali.
    $response = $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'assessment_type' => 'narrative',
        'kode' => 'TP 1.1',
        'deskripsi' => 'Deskripsi Diubah',
        'bobot' => 60,
    ]);

    $response->assertRedirect(route('admin.komponen-penilaian.index'));
    $response->assertSessionDoesntHaveErrors();

    $komponen->refresh();
    expect($komponen->deskripsi)->toBe('Deskripsi Diubah');
    expect($komponen->bobot)->toBe(60);
    expect($komponen->assessment_type->value)->toBe('narrative');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="successfully saves kode, deskripsi, bobot, kktp, and assessment_type edits" --compact`
Expected: FAIL — `assertSessionDoesntHaveErrors()` gagal karena `subjek_type`/`subjek_id`/`semester_id` wajib diisi menurut `rules()` saat ini, padahal tidak dikirim.

- [ ] **Step 3: Implementasi — `UpdateKomponenPenilaianRequest`**

Ganti seluruh `rules()`:

```php
public function rules(): array
{
    $komponen = $this->route('komponenPenilaian');
    $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

    $rules = [
        'kode' => ['nullable', 'string', 'max:50'],
        'deskripsi' => ['required', 'string'],
        'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
        'kktp' => ['nullable', 'string'],
        'kktp_minimal' => ['nullable', 'integer', 'min:0', 'max:100'],
    ];

    if (! $dipakai) {
        $rules['subjek_type'] = ['required', Rule::in(['mata_pelajaran', 'elemen_cp'])];
        $rules['subjek_id'] = ['required', 'integer', function ($attribute, $value, $fail) {
            $exists = match ($this->input('subjek_type')) {
                'mata_pelajaran' => MataPelajaran::withoutGlobalScopes()->where('id', $value)->exists(),
                'elemen_cp' => ElemenCp::where('id', $value)->exists(),
                default => false,
            };
            if (! $exists) {
                $fail('Subjek penilaian yang dipilih tidak valid.');
            }
        }];
        $rules['semester_id'] = ['required', 'integer'];
        $rules['assessment_type'] = ['nullable', Rule::enum(AssessmentType::class)];
    }

    return $rules;
}
```

menjadi:

```php
public function rules(): array
{
    $komponen = $this->route('komponenPenilaian');
    $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

    $rules = [
        'kode' => ['nullable', 'string', 'max:50'],
        'deskripsi' => ['required', 'string'],
        'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
        'kktp' => ['nullable', 'string'],
        'kktp_minimal' => ['nullable', 'integer', 'min:0', 'max:100'],
    ];

    if (! $dipakai) {
        $rules['assessment_type'] = ['nullable', Rule::enum(AssessmentType::class)];
    }

    return $rules;
}
```

Hapus 2 baris import yang sudah tidak dipakai lagi di file ini:

```php
use App\Domains\Akademik\Models\ElemenCp;
use App\Domains\Akademik\Models\MataPelajaran;
```

(`use Illuminate\Validation\Rule;` TETAP ADA — masih dipakai `Rule::enum`.)

- [ ] **Step 4: Implementasi — `UpdateKomponenPenilaianAction`**

Ganti seluruh method `execute()`:

```php
public function execute(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian
{
    return DB::transaction(function () use ($komponen, $data) {
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

        Semester::where('id', $komponen->semester_id)->lockForUpdate()->first();

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
    });
}
```

menjadi:

```php
public function execute(KomponenPenilaian $komponen, UpdateKomponenPenilaianData $data): KomponenPenilaian
{
    return DB::transaction(function () use ($komponen, $data) {
        $dipakai = $komponen->asesmen()->exists() || $komponen->nilaiSiswa()->exists();

        // Subjek Penilaian dan Semester TIDAK BISA diubah sejak TP dibuat --
        // baik sudah dipakai maupun belum. Satu-satunya cara mengganti
        // Subjek/Semester adalah hapus lalu buat TP baru. Blok reassignment
        // lama SENGAJA dihapus (bukan dilewati) -- subjek_type/subjek_id/
        // semester_id di form manapun (Admin maupun Guru) memang tidak
        // pernah lagi dikirim ke sini.
        if (! $dipakai && $data->assessmentType !== null) {
            $komponen->assessment_type = $data->assessmentType;
        }

        Semester::where('id', $komponen->semester_id)->lockForUpdate()->first();

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
    });
}
```

(Import `use App\Models\Semester;` TETAP ADA — masih dipakai baris `Semester::where('id', $komponen->semester_id)->lockForUpdate()`.)

- [ ] **Step 5: Implementasi — `UpdateKomponenPenilaianData`**

Ganti seluruh isi file:

```php
final readonly class UpdateKomponenPenilaianData
{
    public function __construct(
        public ?string $subjekType,
        public ?int $subjekId,
        public ?int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public ?int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
        public ?string $assessmentType,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            subjekType: $data['subjek_type'] ?? null,
            subjekId: isset($data['subjek_id']) ? (int) $data['subjek_id'] : null,
            semesterId: isset($data['semester_id']) ? (int) $data['semester_id'] : null,
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : null,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
            assessmentType: isset($data['assessment_type']) && $data['assessment_type'] !== ''
                ? (string) $data['assessment_type']
                : null,
        );
    }
}
```

menjadi:

```php
final readonly class UpdateKomponenPenilaianData
{
    public function __construct(
        public ?string $kode,
        public string $deskripsi,
        public ?int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
        public ?string $assessmentType,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : null,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
            assessmentType: isset($data['assessment_type']) && $data['assessment_type'] !== ''
                ? (string) $data['assessment_type']
                : null,
        );
    }
}
```

- [ ] **Step 6: Jalankan test Step 1, pastikan lulus**

Run: `php artisan test --filter="successfully saves kode, deskripsi, bobot, kktp, and assessment_type edits" --compact`
Expected: PASS.

- [ ] **Step 7: Sesuaikan 3 test lama yang sekarang menguji perilaku terbalik**

Ganti isi test *"updates a komponen penilaian including mata pelajaran and semester when not yet used"* (cari via `it('updates a komponen penilaian including mata pelajaran and semester when not yet used'`):

```php
it('updates a komponen penilaian including mata pelajaran and semester when not yet used', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap']);
    $mapelLama = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelBaru = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelLama->id, 'semester_id' => $semesterLama->id, 'kode' => 'TP LAMA']);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapelBaru->id,
        'semester_id' => $semesterBaru->id,
        'kode' => 'TP BARU',
        'deskripsi' => 'Deskripsi baru',
        'kktp' => 'KKTP baru',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->subjek_id)->toBe($mapelBaru->id);
    expect($komponen->semester_id)->toBe($semesterBaru->id);
    expect($komponen->kode)->toBe('TP BARU');
    expect($komponen->deskripsi)->toBe('Deskripsi baru');
});
```

menjadi:

```php
it('updates a komponen penilaian including mata pelajaran and semester when not yet used', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLama = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Ganjil']);
    $semesterBaru = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Genap']);
    $mapelLama = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $mapelBaru = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapelLama->id, 'semester_id' => $semesterLama->id, 'kode' => 'TP LAMA']);

    $this->actingAs($manager)->put(route('admin.komponen-penilaian.update', $komponen), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapelBaru->id,
        'semester_id' => $semesterBaru->id,
        'kode' => 'TP BARU',
        'deskripsi' => 'Deskripsi baru',
        'kktp' => 'KKTP baru',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->subjek_id)->toBe($mapelLama->id);
    expect($komponen->semester_id)->toBe($semesterLama->id);
    expect($komponen->kode)->toBe('TP BARU');
    expect($komponen->deskripsi)->toBe('Deskripsi baru');
});
```

Ganti isi test *"recomputes lembaga_id to follow the new semester for elemen_cp when a yayasan actor moves it across lembaga"*:

```php
it('recomputes lembaga_id to follow the new semester for elemen_cp when a yayasan actor moves it across lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $elemenCp = ElemenCp::factory()->create();

    $createAction = app(CreateKomponenPenilaianAction::class);
    $komponen = $createAction->execute(new KomponenPenilaianData(
        subjekType: 'elemen_cp',
        subjekId: $elemenCp->id,
        semesterId: $semesterA->id,
        kode: 'ECP-1',
        deskripsi: 'Deskripsi awal',
        bobot: 100,
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
```

menjadi (NAMA test JUGA diganti):

```php
it('ignores subjek/semester reassignment payload for elemen_cp even from a yayasan actor (lembaga_id stays put)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $elemenCp = ElemenCp::factory()->create();

    $createAction = app(CreateKomponenPenilaianAction::class);
    $komponen = $createAction->execute(new KomponenPenilaianData(
        subjekType: 'elemen_cp',
        subjekId: $elemenCp->id,
        semesterId: $semesterA->id,
        kode: 'ECP-1',
        deskripsi: 'Deskripsi awal',
        bobot: 100,
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
        'deskripsi' => 'Deskripsi diubah',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->semester_id)->toBe($semesterA->id);
    expect($komponen->lembaga_id)->toBe($lembagaA->id);
    expect($komponen->deskripsi)->toBe('Deskripsi diubah');
});
```

Ganti isi test *"recomputes lembaga_id to follow the new semester for mata_pelajaran when a yayasan actor moves it across lembaga"* (nama JUGA diganti):

```php
it('ignores subjek/semester reassignment payload for mata_pelajaran even from a yayasan actor (lembaga_id stays put)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $tahunAjaranB = TahunAjaran::factory()->create(['lembaga_id' => $lembagaB->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $semesterB = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranB->id]);
    $mapelA = MataPelajaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $mapelB = MataPelajaran::factory()->create(['lembaga_id' => $lembagaB->id]);

    $createAction = app(CreateKomponenPenilaianAction::class);
    $komponen = $createAction->execute(new KomponenPenilaianData(
        subjekType: 'mata_pelajaran',
        subjekId: $mapelA->id,
        semesterId: $semesterA->id,
        kode: 'MP-1',
        deskripsi: 'Deskripsi awal',
        bobot: 100,
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
        'deskripsi' => 'Deskripsi diubah',
        'bobot' => 100,
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen->refresh();
    expect($komponen->subjek_id)->toBe($mapelA->id);
    expect($komponen->semester_id)->toBe($semesterA->id);
    expect($komponen->lembaga_id)->toBe($lembagaA->id);
    expect($komponen->deskripsi)->toBe('Deskripsi diubah');
});
```

Test *"does not touch lembaga_id when updating a komponen without changing semester_id"* TIDAK DIUBAH sama sekali.

- [ ] **Step 8: Jalankan ketiga test yang direvisi, pastikan lulus**

Run: `php artisan test --filter="updates a komponen penilaian including mata pelajaran and semester when not yet used|ignores subjek.semester reassignment payload" --compact`
Expected: PASS ketiganya.

- [ ] **Step 9: Implementasi — wording view Admin**

Di `resources/views/portals/lembaga/akademik/komponen-penilaian/edit.blade.php`, setelah blok `<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">...</div>` yang berisi Subjek Penilaian & Semester (baris ±42-52), tambahkan SATU baris baru persis setelah `</div>` penutup grid itu:

```blade
<p class="text-xs text-gray-400 -mt-3">Subjek Penilaian dan Semester tidak bisa diubah di sini — hapus lalu buat TP baru kalau butuh Subjek/Semester yang berbeda.</p>
```

(Teks PERSIS sama dengan `resources/views/portals/guru/akademik/komponen-penilaian/edit.blade.php` baris 66.)

- [ ] **Step 10: Bersihkan kode JS mati**

Di `resources/js/komponen-penilaian-edit.js`, ganti seluruh isi file:

```js
import TomSelect from 'tom-select';

export function komponenPenilaianEditForm() {
    return {
        initMataPelajaranSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari mata pelajaran...',
            });
        },

        initSemesterSelect(el) {
            new TomSelect(el, {
                maxItems: 1,
                create: false,
                placeholder: 'Cari semester...',
            });
        },
    };
}
```

menjadi:

```js
export function komponenPenilaianEditForm() {
    return {};
}
```

- [ ] **Step 11: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`
Expected: PASS semua — termasuk 2 test "locks mata pelajaran and semester when the komponen is already used..." (dipakai=true, TIDAK disentuh task ini, harus tetap lulus) dan "does not touch lembaga_id when updating a komponen without changing semester_id" (juga tidak disentuh, harus tetap lulus).

- [ ] **Step 12: Jalankan regresi lintas modul (Guru)**

Run: `php artisan test tests/Feature/Guru/KomponenPenilaianControllerTest.php --compact`
Expected: PASS semua — jalur Guru TIDAK disentuh sama sekali di task ini, murni regresi memastikan tidak ada efek samping tak terduga.

- [ ] **Step 13: Commit**

```bash
git add app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php resources/views/portals/lembaga/akademik/komponen-penilaian/edit.blade.php resources/js/komponen-penilaian-edit.js tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "fix(komponen-penilaian): selaraskan validasi Edit Admin dengan desain Subjek/Semester terkunci permanen (Opsi A)"
```

---

### Task 2: Badge Scope (isYayasan/activeLembaga) + Label "(Aktif)"

**Files:**
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php`
- Modify: `resources/views/portals/lembaga/akademik/komponen-penilaian/index.blade.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Produces: view `index` (halaman penuh SAJA, BUKAN cabang ajax) menerima `isYayasan`/`activeLembaga`.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('shows the active lembaga badge for a yayasan-scoped actor who has switched into a lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Cempaka Raya']);
    $manager = actingAsYayasanKomponenManager($yayasan);
    session(['active_lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'))
        ->assertSee('SD Cempaka Raya')
        ->assertSee('border-brand-200 bg-brand-50 text-brand-700', false);
});

it('shows the "Semua Lembaga" badge in aggregate mode for a yayasan-scoped actor', function () {
    $yayasan = Yayasan::factory()->create();
    $manager = actingAsYayasanKomponenManager($yayasan);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'))
        ->assertSee('Semua Lembaga')
        ->assertSee('border-purple-200 bg-purple-50 text-purple-700', false);
});

it('does not show the scope badge for a lembaga-scoped actor', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'))
        ->assertDontSee('Semua Lembaga');
});

it('shows "(Aktif)" on the tahun ajaran dropdown for the active tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026', 'status_aktif' => true]);

    $this->actingAs($manager)->get(route('admin.komponen-penilaian.index'))
        ->assertSee('2025/2026 (Aktif)', false);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="shows the active lembaga badge|shows the .Semua Lembaga. badge|does not show the scope badge|shows .\(Aktif\). on the tahun ajaran dropdown" --compact`
Expected: 3 test pertama FAIL (badge belum ada). Test ke-4 ("does not show...") SUDAH PASS (baseline). Test ke-5 (label Aktif) FAIL.

- [ ] **Step 3: Implementasi**

Di `app/Http/Controllers/Admin/KomponenPenilaianController.php`, tambahkan import:

```php
use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;
use App\Models\Lembaga;
```

Tambahkan `use ResolveLembagaScopeTrait;` di dalam class (sejajar dengan `use AuthorizesRequests;` yang sudah ada).

Tambahkan method private baru sebelum `index()`:

```php
private function scopeHeaderData(Request $request): array
{
    $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
    $lembagaId = $this->resolveActiveLembagaId($request->user());

    return [
        'isYayasan' => $isYayasan,
        'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
    ];
}
```

Di method `index()`, ganti:

```php
'tahunAjaranList' => TahunAjaran::orderByDesc('id')->get(),
```

menjadi (HANYA di return `view()` HALAMAN PENUH, BUKAN di cabang `if ($request->ajax())` yang return `_daftar`):

```php
'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('id')->get(),
```

Dan tambahkan `...$this->scopeHeaderData($request),` sebagai elemen terakhir array itu (SEBELUM `]);` penutup).

Di `resources/views/portals/lembaga/akademik/komponen-penilaian/index.blade.php`, ganti:

```blade
<h1 class="font-display text-lg font-bold text-gray-900">Komponen Penilaian (TP)</h1>
```

menjadi:

```blade
<div class="flex flex-wrap items-center gap-2.5">
    <h1 class="font-display text-lg font-bold text-gray-900">Komponen Penilaian (TP)</h1>
    @if ($isYayasan ?? false)
        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
            <x-icon name="apartment" class="h-3.5 w-3.5" />
            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
        </span>
    @endif
</div>
```

Dan ganti:

```blade
<option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
```

menjadi:

```blade
<option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}{{ $tahunAjaran->status_aktif ? ' (Aktif)' : '' }}{{ ($isYayasan ?? false) && ! ($activeLembaga ?? null) ? ' — '.($tahunAjaran->lembaga->nama ?? '-') : '' }}</option>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="shows the active lembaga badge|shows the .Semua Lembaga. badge|does not show the scope badge|shows .\(Aktif\). on the tahun ajaran dropdown" --compact`
Expected: PASS semua 4 test.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KomponenPenilaianController.php resources/views/portals/lembaga/akademik/komponen-penilaian/index.blade.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "feat(komponen-penilaian): badge scope isYayasan/activeLembaga + label (Aktif) di dropdown Tahun Ajaran"
```

---

### Task 3: Pakai `BentukPendidikan::isPaud()`, Hapus Hardcode

**Files:**
- Modify: `app/Http/Controllers/Admin/KomponenPenilaianController.php`
- Modify: `resources/views/portals/lembaga/akademik/komponen-penilaian/create.blade.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Produces: view `create` menerima `isPaud` (bool) menggantikan `bentukPendidikan` (string mentah).

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('defaults to elemen_cp and narrative for a PAUD lembaga on the create form', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'TK']);
    $manager = actingAsKomponenManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.create'));

    $response->assertOk()->assertSee("subjekType: 'elemen_cp'", false);
});

it('defaults to mata_pelajaran and numeric for a non-PAUD lembaga on the create form', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $manager = actingAsKomponenManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.komponen-penilaian.create'));

    $response->assertOk()->assertSee("subjekType: 'mata_pelajaran'", false);
});
```

- [ ] **Step 2: Jalankan test, pastikan lulus (baseline sebelum refactor)**

Run: `php artisan test --filter="defaults to elemen_cp and narrative for a PAUD lembaga|defaults to mata_pelajaran and numeric for a non-PAUD lembaga" --compact`
Expected: PASS keduanya — ini test REGRESI untuk memastikan refactor Task ini TIDAK mengubah perilaku (murni ganti cara komputasi dari hardcode ke enum), bukan menambah fitur baru. Kalau SALAH SATU gagal di titik ini (sebelum ada perubahan kode), STOP dan laporkan ke user.

- [ ] **Step 3: Implementasi**

Di `app/Http/Controllers/Admin/KomponenPenilaianController.php`, method `create()`, ganti:

```php
'bentukPendidikan' => $request->user()->lembaga?->bentuk_pendidikan,
```

menjadi:

```php
'isPaud' => \App\Domains\Akademik\Enums\BentukPendidikan::tryFrom($request->user()->lembaga?->bentuk_pendidikan ?? '')?->isPaud() ?? false,
```

Di `resources/views/portals/lembaga/akademik/komponen-penilaian/create.blade.php` baris 31, ganti:

```blade
{{ old('subjek_type', in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true) ? 'elemen_cp' : 'mata_pelajaran') }}', assessmentType: '{{ old('assessment_type', in_array($bentukPendidikan, ['KB', 'TPA', 'SPS', 'TK'], true) ? 'narrative' : 'numeric') }}'
```

menjadi:

```blade
{{ old('subjek_type', $isPaud ? 'elemen_cp' : 'mata_pelajaran') }}', assessmentType: '{{ old('assessment_type', $isPaud ? 'narrative' : 'numeric') }}'
```

- [ ] **Step 4: Jalankan test Step 1 lagi, pastikan TETAP lulus setelah refactor**

Run: `php artisan test --filter="defaults to elemen_cp and narrative for a PAUD lembaga|defaults to mata_pelajaran and numeric for a non-PAUD lembaga" --compact`
Expected: PASS keduanya — perilaku observable SAMA, cuma cara komputasi berubah.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KomponenPenilaianController.php resources/views/portals/lembaga/akademik/komponen-penilaian/create.blade.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "refactor(komponen-penilaian): pakai BentukPendidikan::isPaud() di create.blade.php, hapus hardcode array PAUD"
```

---

### Task 4: Konsistenkan Default "Bobot" DTO dengan UI

**Files:**
- Modify: `app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:**
- Tidak ada interface baru.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

```php
it('defaults bobot to 100 (not 10) when a raw store request omits the bobot field entirely', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsKomponenManager($lembaga);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($manager)->post(route('admin.komponen-penilaian.store'), [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => $mapel->id,
        'semester_id' => $semester->id,
        'kode' => 'TP-NO-BOBOT',
        'deskripsi' => 'Tanpa bobot dikirim sama sekali',
    ])->assertRedirect(route('admin.komponen-penilaian.index'));

    $komponen = KomponenPenilaian::where('kode', 'TP-NO-BOBOT')->first();
    expect($komponen)->not->toBeNull();
    expect($komponen->bobot)->toBe(100);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="defaults bobot to 100 .not 10. when a raw store request omits" --compact`
Expected: FAIL — `$komponen->bobot` saat ini `10`, bukan `100`.

- [ ] **Step 3: Implementasi**

Di `app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`, ganti:

```php
bobot: isset($data['bobot']) ? (int) $data['bobot'] : 10,
```

menjadi:

```php
bobot: isset($data['bobot']) ? (int) $data['bobot'] : 100,
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter="defaults bobot to 100 .not 10. when a raw store request omits" --compact`
Expected: PASS.

- [ ] **Step 5: Jalankan regresi test file ini secara penuh**

Run: `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`
Expected: PASS semua.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "fix(komponen-penilaian): samakan fallback bobot DTO (100) dengan default UI, bukan 10"
```

---

### Task 5: Penutup — Regresi Penuh, Pint, Verifikasi Manual

**Files:**
- Tidak ada file baru — task verifikasi murni.

- [ ] **Step 1: Jalankan seluruh test domain Komponen Penilaian**

Run: `php artisan test --compact --filter="KomponenPenilaianCrudTest|KomponenPenilaianControllerTest"`
Expected: PASS semua, 0 gagal. (Filter kedua meng-cover `tests/Feature/Guru/KomponenPenilaianControllerTest.php`.)

- [ ] **Step 2: Jalankan Pint pada file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [ ] **Step 3: Verifikasi manual via browser (WAJIB, topik sensitif — data nilai)**

Login sebagai `operator_akademik` (lembaga-scope): buka Komponen Penilaian, buat 1 TP baru (belum dipakai). Klik Edit — pastikan ada teks "Subjek Penilaian dan Semester tidak bisa diubah di sini — hapus lalu buat TP baru...". Ubah deskripsi, bobot, dan Tipe Penilaian, klik "Simpan Perubahan" — pastikan BERHASIL tersimpan (TIDAK ADA error validasi "wajib diisi"), dan perubahan benar-benar terlihat di daftar setelahnya.

Buat 1 TP lagi, tautkan ke asesmen (lewat menu Asesmen) supaya `$dipakai=true`. Klik Edit TP itu — pastikan banner peringatan "sudah dipakai" muncul, field Tipe Penilaian ter-disable, TAPI kode/deskripsi/bobot/kktp TETAP bisa diubah dan tersimpan.

Login sebagai yayasan-scope, mode "Semua Lembaga": badge ungu "Semua Lembaga" di header, dropdown Tahun Ajaran menampilkan suffix nama lembaga. Switch ke 1 lembaga: badge berubah nama lembaga.

Login sebagai lembaga PAUD (TK/KB/dst): buka "Tambah TP Baru" — pastikan default radio "Elemen CP (PAUD)" dan Tipe Penilaian "Naratif/Deskriptif" terpilih otomatis.

- [ ] **Step 4: Laporkan hasil**

TIDAK perlu menulis file handoff log baru di task ini — kalau user menghendaki log terpisah, itu permintaan tambahan setelah plan ini selesai.

---

## Self-Review

**1. Spec coverage** — SEMUA 4 item spec `.agents/specs/2026-09-09-tp-komponen-penilaian-perbaikan.md` tercakup: Item A → Task 1, Item B → Task 2, Item C → Task 3, Item D → Task 4. Task 5 menutup dengan regresi + Pint + verifikasi manual.

**2. Placeholder scan** — tidak ada "TBD"/dst. Semua step berisi kode lengkap, ditranskripsi persis dari spec yang sudah 2x direview (termasuk koreksi PENTING: cakupan Task 1 diperluas dari 1 jadi 3 test yang harus disesuaikan, ditemukan lewat penyisiran ulang seluruh file test sebelum plan ini ditulis).

**3. Type consistency** — `UpdateKomponenPenilaianData` (Task 1) konstruktor baru (6 parameter) dipakai konsisten di `UpdateKomponenPenilaianAction` dan `UpdateKomponenPenilaianRequest::toDTO()` (tidak berubah, tetap `fromArray($this->validated())`). `isYayasan`/`activeLembaga` (Task 2) nama variabel sama persis dengan pola menu lain.

**Catatan tambahan hasil self-review**:
- Task 1 SENGAJA jadi 1 task besar (bukan dipecah per file) — FormRequest, Action, dan DTO harus berubah BERSAMAAN dalam 1 commit, karena masing-masing SENDIRIAN akan membuat kode tidak konsisten/rusak di tengah jalan (mis. kalau DTO diubah duluan tanpa Action, `UpdateKomponenPenilaianAction` akan fatal error karena mengakses properti yang sudah tidak ada).
- Task 1 Step 12 (regresi Guru) SENGAJA ditambahkan meski jalur Guru tidak disentuh — murni untuk membuktikan tidak ada efek samping tak terduga, mengingat topik ini sensitif (data nilai siswa).
- Task 3 Step 1-2 pakai pola "test regresi dulu, verifikasi baseline lulus SEBELUM refactor" (sama seperti pola Task 9 di plan RPP sebelumnya) — karena ini murni refactor cara komputasi, bukan fitur baru.
- Urutan Task 2-4 TIDAK saling bergantung dan boleh dikerjakan dalam urutan berbeda kalau perlu, TAPI Task 1 HARUS paling awal (prioritas kritis, topik sensitif).
