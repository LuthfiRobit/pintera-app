# Perbaikan Audit Akademik Putaran 3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup 1 bug mendesak berdampak finansial (billing keliru saat siswa dinonaktifkan) + 7 bug/gap lain yang ditemukan audit putaran 3 modul Akademik.

**Architecture:** 8 perbaikan independen, masing-masing 1 task berdiri sendiri (tidak saling bergantung kecuali Task 6 yang menambah 1 method baru ke trait yang sudah ada). Root fix billing (Task 1) HANYA di `JenisTagihanSasaranMatcher.php`.

**Tech Stack:** Laravel 12, PHP 8.3, Pest.

## Global Constraints

- Root fix billing (Task 1) HANYA di `JenisTagihanSasaranMatcher.php` — JANGAN sentuh `TagihanBillingGenerator.php`, JANGAN tambah guard terpisah di `GenerateTagihanForUpdatedClass.php`.
- Task 6 (session-staleness) CUMA untuk 3 controller: `GuruController`, `KalenderAkademikController`, `PengaturanAkademikController` — `JalurPpdbController`/`GelombangPpdbController` TIDAK disentuh sama sekali di plan ini.
- Activitylog mass-update gap (temuan #9 di spec) TIDAK masuk plan ini sama sekali.
- User sudah menerima risiko konflik merge dengan branch `keuangan-v2` yang sedang berjalan paralel (Task 1 menyentuh file Keuangan) — tidak perlu tanya ulang soal ini.
- Tidak pindah branch, tetap di `akademik-v2`.

---

## Task 1: Root Fix — `JenisTagihanSasaranMatcher` Filter Status Siswa

**Files:**
- Modify: `app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php`
- Test: `tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php`, `tests/Feature/Akademik/UpdateStatusSiswaActionTest.php` (test baru end-to-end)

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal — matcher menolak siswa non-aktif**

Baca `tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php` untuk pola factory/setup yang sudah dipakai (cek 1-2 test lain di file itu). Tambahkan test baru:

```php
it('siswaMatchesJenisTagihan menolak siswa non-aktif meski JenisTagihan tanpa kriteria sasaran', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'is_active' => true]);
    // Sengaja TIDAK buat sasaranGrup apapun -- kriteria kosong berarti "cocok semua siswa aktif".
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => null, 'status' => 'keluar']);

    $matcher = app(JenisTagihanSasaranMatcher::class);

    expect($matcher->siswaMatchesJenisTagihan($siswa, $jenisTagihan))->toBeFalse();
});

it('resolveTargetSiswa dan countTotalSiswaPool mengecualikan siswa non-aktif', function () {
    $lembaga = Lembaga::factory()->create();
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'is_active' => true]);
    $siswaAktif = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'status' => 'aktif']);
    $siswaKeluar = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => null, 'status' => 'keluar']);

    $matcher = app(JenisTagihanSasaranMatcher::class);

    $target = $matcher->resolveTargetSiswa($jenisTagihan);
    expect($target->pluck('id'))->toContain($siswaAktif->id);
    expect($target->pluck('id'))->not->toContain($siswaKeluar->id);
    expect($matcher->countTotalSiswaPool($jenisTagihan))->toBe(1);
});
```

Sesuaikan `use` import (`Siswa`, `Lembaga`, `JenisTagihan`, `JenisTagihanSasaranMatcher`) dengan yang sudah ada di file.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="siswaMatchesJenisTagihan menolak siswa non-aktif"`
Run: `php artisan test --filter="resolveTargetSiswa dan countTotalSiswaPool mengecualikan"`
Expected: FAIL — siswa non-aktif masih ikut match/terhitung.

- [ ] **Step 3: Tulis test end-to-end (reproduksi persis skenario awal)**

Buat file baru `tests/Feature/Akademik/DeaktivasiSiswaTidakMemicuTagihanTest.php`:

```php
<?php

use App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Enums\StatusSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('deaktivasi siswa via UpdateStatusSiswaAction TIDAK memicu tagihan baru meski JenisTagihan tanpa kriteria sasaran spesifik', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => StatusSiswa::Aktif->value]);

    // JenisTagihan generik tanpa sasaranGrup sama sekali -- sebelum fix, ini "cocok semua siswa".
    JenisTagihan::factory()->create([
        'lembaga_id' => $lembaga->id,
        'is_active' => true,
        'kategori' => 'spp',
    ]);

    $tagihanSebelum = Tagihan::whereHasMorph('tagihable', [Siswa::class], fn ($q) => $q->where('id', $siswa->id))->count();

    app(UpdateStatusSiswaAction::class)->execute($siswa, StatusSiswa::Keluar);

    $tagihanSesudah = Tagihan::whereHasMorph('tagihable', [Siswa::class], fn ($q) => $q->where('id', $siswa->id))->count();

    expect($tagihanSesudah)->toBe($tagihanSebelum);
});
```

Sesuaikan nama kolom `kategori`/relasi morph `tagihable` dengan struktur `Tagihan` yang sebenarnya (cek `app/Domains/Keuangan/Models/Tagihan.php` dulu untuk nama relasi & enum kategori yang valid — pakai kategori yang BUKAN `pendaftaran`/`daftar_ulang`, karena `GenerateTagihanForUpdatedClass::handle()` sudah mengecualikan 2 kategori itu).

- [ ] **Step 4: Jalankan test end-to-end, pastikan gagal**

Run: `php artisan test --filter="deaktivasi siswa via UpdateStatusSiswaAction TIDAK memicu tagihan baru"`
Expected: FAIL — `$tagihanSesudah` > `$tagihanSebelum` (bukti bug nyata sebelum fix).

- [ ] **Step 5: Perbaiki `JenisTagihanSasaranMatcher`**

Baca `app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php` baris 1-84 (mungkin sudah bergeser). Tambahkan `use App\Enums\StatusSiswa;` ke import. Ganti `resolveTargetSiswa()`:
```php
    public function resolveTargetSiswa(JenisTagihan $jenisTagihan): Collection
    {
        $sasaranGrups = $jenisTagihan->sasaranGrup()->where('tipe', 'sasaran')->with('kriteria')->get();

        $query = Siswa::withoutGlobalScope(TenantScope::class)
            ->with('kelas')
            ->where('lembaga_id', $jenisTagihan->lembaga_id)
            ->where('status', StatusSiswa::Aktif->value);

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

    public function countTotalSiswaPool(JenisTagihan $jenisTagihan): int
    {
        return Siswa::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $jenisTagihan->lembaga_id)
            ->where('status', StatusSiswa::Aktif->value)
            ->count();
    }
```

Ganti `siswaMatchesJenisTagihan()`:
```php
    public function siswaMatchesJenisTagihan(Siswa $siswa, JenisTagihan $jenisTagihan): bool
    {
        if ($siswa->status !== StatusSiswa::Aktif) {
            return false;
        }

        if ($siswa->lembaga_id !== $jenisTagihan->lembaga_id) {
            return false;
        }

        $sasaranGrups = $jenisTagihan->relationLoaded('sasaranGrup')
            ? $jenisTagihan->sasaranGrup->where('tipe', 'sasaran')
            : $jenisTagihan->sasaranGrup()->where('tipe', 'sasaran')->with('kriteria')->get();

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
```

- [ ] **Step 6: Jalankan semua test, pastikan lolos**

Run: `php artisan test --filter=JenisTagihanSasaranMatcherTest`
Run: `php artisan test --filter="DeaktivasiSiswaTidakMemicuTagihan"`
Expected: semua PASS.

- [ ] **Step 7: Regresi Keuangan billing**

Run: `php artisan test --filter=Keuangan`
Expected: semua PASS — perbaikan ini menyempitkan hasil match (exclude non-aktif), jadi test yang mengasumsikan siswa AKTIF match tidak boleh regresi; test yang secara implisit mengandalkan siswa non-aktif ikut match (kalau ada) perlu ditinjau — kalau ada test yang gagal karena ini, itu artinya test itu SENDIRI mengasumsikan bug lama sebagai "benar", perbaiki assertion test itu sesuai perilaku baru yang benar (jangan lemahkan fix ini untuk membuat test lama lolos).

- [ ] **Step 8: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php tests/Feature/Keuangan/JenisTagihanSasaranMatcherTest.php tests/Feature/Akademik/DeaktivasiSiswaTidakMemicuTagihanTest.php
git commit -m "fix(keuangan): JenisTagihanSasaranMatcher exclude siswa non-aktif dari semua jalur billing"
```

---

## Task 2: RPP — `guru_id` Eksplisit Tervalidasi

**Files:**
- Modify: `app/Http/Requests/Akademik/StoreRppRequest.php`
- Modify: `app/Http/Controllers/Admin/RppController.php`
- Modify: `resources/views/portals/lembaga/akademik/rpp/_modal-form.blade.php`
- Test: `tests/Feature/Akademik/RppWorkflowTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis 4 test yang gagal**

Baca `tests/Feature/Akademik/RppWorkflowTest.php` untuk pola setup actor/kelas/semester yang sudah ada. Tambahkan:

```php
it('menolak actor tanpa profil Guru membuat RPP tanpa guru_id', function () {
    // Setup: $manager tanpa relasi Guru, $kelas, $semester -- sesuaikan dgn helper existing di file ini.
    $response = $this->actingAs($managerTanpaGuru)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'judul_topik' => 'Uji Topik',
        'alokasi_waktu' => '2 x 35 Menit',
        'file' => \Illuminate\Http\UploadedFile::fake()->create('rpp.pdf', 100),
    ]);

    $response->assertSessionHasErrors('guru_id');
});

it('mengizinkan actor tanpa profil Guru membuat RPP dengan guru_id valid', function () {
    $guruTarget = Guru::factory()->create(['lembaga_id' => $kelas->lembaga_id]);

    $response = $this->actingAs($managerTanpaGuru)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'guru_id' => $guruTarget->id,
        'judul_topik' => 'Uji Topik',
        'alokasi_waktu' => '2 x 35 Menit',
        'file' => \Illuminate\Http\UploadedFile::fake()->create('rpp.pdf', 100),
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect(\App\Domains\Akademik\Models\Rpp::where('guru_id', $guruTarget->id)->exists())->toBeTrue();
});

it('menolak guru_id milik lembaga lain', function () {
    $lembagaLain = Lembaga::factory()->create();
    $guruLembagaLain = Guru::factory()->create(['lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($managerTanpaGuru)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'guru_id' => $guruLembagaLain->id,
        'judul_topik' => 'Uji Topik',
        'alokasi_waktu' => '2 x 35 Menit',
        'file' => \Illuminate\Http\UploadedFile::fake()->create('rpp.pdf', 100),
    ]);

    $response->assertSessionHasErrors('guru_id');
});

it('actor dengan profil Guru sendiri tetap bisa membuat RPP tanpa guru_id (regresi)', function () {
    // $managerGuru sudah punya relasi Guru -- pola sudah ada di test lain file ini.
    $response = $this->actingAs($managerGuru)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'judul_topik' => 'Uji Topik',
        'alokasi_waktu' => '2 x 35 Menit',
        'file' => \Illuminate\Http\UploadedFile::fake()->create('rpp.pdf', 100),
    ]);

    $response->assertSessionDoesntHaveErrors();
});
```

Baca test lain di file ini untuk pola PERSIS pembuatan `$managerTanpaGuru`/`$managerGuru`/`$kelas`/`$semester` (harus konsisten dengan permission `rpp.kelola` dan tenant yang sudah dipakai file ini), sesuaikan 4 test di atas memakainya alih-alih variabel placeholder.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="RppWorkflowTest"`
Expected: 3 dari 4 test baru FAIL (fallback lama masih aktif); test #4 kemungkinan PASS kebetulan (actor ber-guru tidak terpengaruh bug).

- [ ] **Step 3: Update `StoreRppRequest`**

Baca `app/Http/Requests/Akademik/StoreRppRequest.php` baris 1-88. Tambahkan `use App\Models\Guru;` ke import. Ganti `rules()` — tambah baris:
```php
'guru_id' => ['nullable', 'integer', 'exists:guru,id'],
```
Ganti `withValidator()`:
```php
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $kelasId = $this->input('kelas_id');
            $semesterId = $this->input('semester_id');
            if (! $kelasId || ! $semesterId) {
                return;
            }

            $kelas = Kelas::find($kelasId);
            $semester = Semester::find($semesterId);
            if ($kelas && $semester && $kelas->tahun_ajaran_id !== $semester->tahun_ajaran_id) {
                $validator->errors()->add('kelas_id', 'Kelas yang dipilih bukan berasal dari tahun ajaran yang sama dengan semester ini.');
            }

            if ($this->user()->guru === null) {
                $guruId = $this->input('guru_id');
                if (! $guruId) {
                    $validator->errors()->add('guru_id', 'Guru pengampu wajib dipilih.');

                    return;
                }

                $guru = Guru::find($guruId);
                if ($kelas && $guru && $guru->lembaga_id !== $kelas->lembaga_id) {
                    $validator->errors()->add('guru_id', 'Guru yang dipilih bukan dari lembaga yang sama dengan kelas ini.');
                }
            }
        });
    }
```

- [ ] **Step 4: Update `RppController::store()`**

Baca baris 139-152 (mungkin bergeser). Ganti baris 147:
```php
        $guruId = $guru ? $guru->id : (int) $request->input('guru_id');
```
(Hapus fallback `Guru::where('lembaga_id', $kelas->lembaga_id)->value('id')` dan `abort(422, ...)` di baris 149-151 — `StoreRppRequest` sudah menolak sebelum sampai ke sini kalau `guru_id` kosong/tidak valid untuk actor tanpa profil guru.)

- [ ] **Step 5: Update `RppController::index()` — tambah `guruList` untuk view**

Baca baris 95-119 (mungkin bergeser). Setelah blok `$mataPelajaranList = ...` (baris 109), tambahkan:
```php
        $guruQuery = Guru::query();
        if ($targetLembagaId) {
            $guruQuery->where('lembaga_id', $targetLembagaId);
        }
        $guruList = $guruQuery->orderBy('nama_lengkap')->get();
```
(Cek nama kolom urut yang benar di model `Guru` — kemungkinan lewat relasi `person->nama_lengkap`, bukan kolom langsung; sesuaikan kalau berbeda, baca `App\Models\Guru` dulu.) Tambahkan `'guruList' => $guruList,` ke array data view di baris `return view('portals.lembaga.akademik.rpp.index', [...])`.

- [ ] **Step 6: Update view `_modal-form.blade.php`**

Baca baris 62-72 (blok Mata Pelajaran). Tepat SEBELUM blok itu, tambahkan (cuma tampil kalau actor login tidak punya profil Guru):
```blade
            @if (! auth()->user()->guru)
                <div>
                    <x-input-label value="Guru Pengampu" />
                    <select name="guru_id" required x-model="formModal.guru_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih Guru Pengampu —</option>
                        @foreach ($guruList as $guruOpsi)
                            <option value="{{ $guruOpsi->id }}">{{ $guruOpsi->nama_lengkap }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
```

- [ ] **Step 7: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter="RppWorkflowTest"`
Expected: semua PASS.

- [ ] **Step 8: Regresi RPP**

Run: `php artisan test --filter=Rpp`
Expected: semua PASS.

- [ ] **Step 9: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Akademik/StoreRppRequest.php app/Http/Controllers/Admin/RppController.php resources/views/portals/lembaga/akademik/rpp/_modal-form.blade.php tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "fix(akademik): guru_id eksplisit tervalidasi di RPP, hapus fallback guru acak"
```

---

## Task 3: Race Condition Bobot Komponen Penilaian

**Files:**
- Modify: `app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal (behavioral, bukan concurrency asli)**

Baca `tests/Feature/Admin/KomponenPenilaianCrudTest.php` untuk pola setup `subjekType`/`subjekId`/`semesterId` yang sudah ada. Tambahkan:

```php
it('tetap konsisten menolak bobot melebihi 100% setelah dibungkus lock (regresi, bukan tes concurrency asli)', function () {
    // Setup subjek+semester yang sama dengan pola existing di file ini.
    app(CreateKomponenPenilaianAction::class)->execute(new KomponenPenilaianData(
        subjekType: $subjekType, subjekId: $subjekId, semesterId: $semesterId,
        kode: 'A', deskripsi: 'Komponen A', bobot: 60, kktp: null, kktpMinimal: null, assessmentType: null,
    ));

    expect(fn () => app(CreateKomponenPenilaianAction::class)->execute(new KomponenPenilaianData(
        subjekType: $subjekType, subjekId: $subjekId, semesterId: $semesterId,
        kode: 'B', deskripsi: 'Komponen B', bobot: 50, kktp: null, kktpMinimal: null, assessmentType: null,
    )))->toThrow(\Illuminate\Validation\ValidationException::class);
});
```

Sesuaikan constructor `KomponenPenilaianData` dengan signature aktual (baca DTO-nya dulu).

- [ ] **Step 2: Jalankan test, pastikan LOLOS di percobaan pertama**

Run: `php artisan test --filter="tetap konsisten menolak bobot melebihi 100"`
Expected: **PASS** — ini BUKAN pola TDD merah-dulu, karena validasi "≤100%" itu sendiri sudah benar SEBELUM fix ini (yang belum benar adalah PROTEKSI SAAT PARALEL, bukan logic single-request). Test ini jadi regression-guard untuk Step 3-4 (pastikan pembungkusan transaction tidak merusak logic yang sudah benar).

- [ ] **Step 3: Perbaiki `CreateKomponenPenilaianAction`**

Baca file penuh (baris 1-54 versi awal, cek pergeseran). Tambahkan `use Illuminate\Support\Facades\DB;` ke import. Bungkus SELURUH isi `execute()` dengan transaction + lock:
```php
final class CreateKomponenPenilaianAction
{
    /**
     * @throws ValidationException
     */
    public function execute(KomponenPenilaianData $data): KomponenPenilaian
    {
        return DB::transaction(function () use ($data) {
            Semester::where('id', $data->semesterId)->lockForUpdate()->first();

            $existingSum = KomponenPenilaian::where('subjek_type', $data->subjekType)
                ->where('subjek_id', $data->subjekId)
                ->where('semester_id', $data->semesterId)
                ->sum('bobot');

            if (($existingSum + $data->bobot) > 100) {
                $remaining = max(0, 100 - $existingSum);
                throw ValidationException::withMessages([
                    'bobot' => "Total bobot melebihi 100%. Sisa bobot yang tersedia untuk subjek ini adalah {$remaining}%.",
                ]);
            }

            $assessmentType = $data->assessmentType ?? match ($data->subjekType) {
                'elemen_cp' => AssessmentType::Narrative->value,
                'mata_pelajaran' => AssessmentType::Numeric->value,
            };

            return KomponenPenilaian::create([
                'subjek_type' => $data->subjekType,
                'subjek_id' => $data->subjekId,
                'semester_id' => $data->semesterId,
                'lembaga_id' => Semester::findOrFail($data->semesterId)->lembaga_id,
                'kode' => $data->kode,
                'deskripsi' => $data->deskripsi,
                'bobot' => $data->bobot,
                'kktp' => $data->kktp,
                'kktp_minimal' => $data->kktpMinimal,
                'assessment_type' => $assessmentType,
            ]);
        });
    }
}
```

- [ ] **Step 4: Perbaiki `UpdateKomponenPenilaianAction`** — pola identik, bungkus SELURUH isi `execute()` (baris 17-53 versi awal) dengan `DB::transaction()`, tambah `Semester::where('id', $komponen->semester_id)->lockForUpdate()->first();` di awal closure SEBELUM `sum('bobot')` dihitung. **WAJIB pertahankan `->where('id', '!=', $komponen->id)`** pada query `$existingSum` (baris yang SUDAH BENAR, jangan dihapus) dan seluruh logic lain (blok `if (! $dipakai && ...)`, update field, `$komponen->save()`) TETAP UTUH di dalam closure yang sama.

- [ ] **Step 5: Jalankan test lagi + regresi**

Run: `php artisan test --filter=KomponenPenilaian`
Expected: semua PASS, termasuk test baru dan seluruh test existing (`KomponenPenilaianCrudTest`, `KomponenPenilaianControllerTest`, dll).

- [ ] **Step 6: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php tests/Feature/Admin/KomponenPenilaianCrudTest.php
git commit -m "fix(akademik): cegah race condition validasi total bobot Komponen Penilaian via lockForUpdate pada Semester"
```

---

## Task 4: Re-check Tenant `mata_pelajaran_id` di RPP Jalur Admin

**Files:**
- Modify: `app/Http/Controllers/Admin/RppController.php`
- Test: `tests/Feature/Akademik/RppWorkflowTest.php`

**Interfaces:** Konsumsi: Task 2 (mengubah blok `store()` yang sama) — kerjakan Task 4 SETELAH Task 2 selesai supaya tidak konflik edit pada method yang sama.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan di `tests/Feature/Akademik/RppWorkflowTest.php`:

```php
it('menolak mata_pelajaran_id milik lembaga lain pada jalur admin (guru null)', function () {
    $guruTarget = Guru::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $lembagaLain = Lembaga::factory()->create();
    $mapelLembagaLain = \App\Domains\Akademik\Models\MataPelajaran::factory()->create(['lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($managerTanpaGuru)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'guru_id' => $guruTarget->id,
        'mata_pelajaran_id' => $mapelLembagaLain->id,
        'judul_topik' => 'Uji Topik',
        'alokasi_waktu' => '2 x 35 Menit',
        'file' => \Illuminate\Http\UploadedFile::fake()->create('rpp.pdf', 100),
    ]);

    $response->assertNotFound();
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menolak mata_pelajaran_id milik lembaga lain"`
Expected: FAIL — request lolos (tidak ada 404), RPP kemungkinan tetap dibuat dengan `mata_pelajaran_id` lintas-lembaga.

- [ ] **Step 3: Perbaiki `RppController::store()`**

Baca baris 139-165 (mungkin bergeser setelah Task 2). Cari blok `if ($guru !== null) { ... }` (baris ~153+). Tepat SEBELUM blok itu (jalur `$guru === null`), tambahkan:
```php
        if ($guru === null && $request->filled('mata_pelajaran_id')) {
            $mapel = MataPelajaran::find($request->input('mata_pelajaran_id'));
            abort_if($mapel === null || $mapel->lembaga_id !== $kelas->lembaga_id, 404);
        }
```
Tambahkan `use App\Domains\Akademik\Models\MataPelajaran;` ke import kalau belum ada.

- [ ] **Step 4: Jalankan test lagi + regresi**

Run: `php artisan test --filter="RppWorkflowTest"`
Expected: semua PASS.

- [ ] **Step 5: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/RppController.php tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "fix(akademik): re-check tenant mata_pelajaran_id di jalur admin RppController::store()"
```

---

## Task 5: Dropdown Kelas `SiswaController` — Filter TA Aktif + Preservasi Kelas Existing

**Files:**
- Modify: `app/Http/Controllers/Admin/SiswaController.php`
- Test: `tests/Feature/Admin/SiswaCrudTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal — filter TA aktif**

Tambahkan di `tests/Feature/Admin/SiswaCrudTest.php`:

```php
it('kelasList di create() dan edit() cuma berisi kelas dari tahun ajaran aktif', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->givePermissionTo(['siswa.create', 'siswa.edit']);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    $kelasAktif = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taAktif->id, 'nama' => 'Kelas Aktif']);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taLama->id, 'nama' => 'Kelas Lama']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasAktif->id, 'status' => 'aktif']);

    $responseCreate = $this->actingAs($manager)->get(route('admin.siswa.create'));
    $responseCreate->assertViewHas('kelasList', fn ($list) => $list->contains('id', $kelasAktif->id) && ! $list->contains('id', $kelasLama->id));

    $responseEdit = $this->actingAs($manager)->get(route('admin.siswa.edit', $siswa));
    $responseEdit->assertViewHas('kelasList', fn ($list) => $list->contains('id', $kelasAktif->id) && ! $list->contains('id', $kelasLama->id));
});
```

- [ ] **Step 2: Tulis test yang gagal — preservasi kelas existing (regresi negatif #10b)**

Tambahkan di file yang sama:

```php
it('mempertahankan kelas siswa saat ini di kelasList meski dari TA tidak aktif, dan submit tanpa ubah field tidak memindahkan siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->givePermissionTo(['siswa.edit']);
    $taAktif = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $taLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => false]);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taAktif->id, 'nama' => 'Kelas Aktif']);
    $kelasLamaSiswa = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $taLama->id, 'nama' => 'Kelas Lama Siswa']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLamaSiswa->id, 'status' => 'aktif', 'nis' => '99999']);

    $responseEdit = $this->actingAs($manager)->get(route('admin.siswa.edit', $siswa));
    $responseEdit->assertViewHas('kelasList', fn ($list) => $list->contains('id', $kelasLamaSiswa->id));

    // Submit form TANPA mengubah kelas_id -- kirim value kelas lama itu (form asli akan
    // mengirim value yang sedang ter-selected, yaitu kelas lama siswa itu sendiri).
    $this->actingAs($manager)->put(route('admin.siswa.update', $siswa), [
        'kelas_id' => $kelasLamaSiswa->id,
        'nis' => $siswa->nis,
        'nisn' => $siswa->nisn,
        'nama_lengkap' => $siswa->nama_lengkap,
        'jenis_kelamin' => 'L',
    ]);

    expect($siswa->fresh()->kelas_id)->toBe($kelasLamaSiswa->id);
});
```

- [ ] **Step 3: Jalankan kedua test, pastikan gagal**

Run: `php artisan test --filter="kelasList di create"`
Run: `php artisan test --filter="mempertahankan kelas siswa saat ini"`
Expected: FAIL — `kelasList` masih berisi SEMUA kelas tanpa filter TA.

- [ ] **Step 4: Perbaiki `SiswaController::create()`**

Baca baris 76-83 (mungkin bergeser). Ganti:
```php
    public function create(): View
    {
        $this->authorize('siswa.create');

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->first();
        $kelasList = $tahunAjaranAktif
            ? Kelas::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
            : collect();

        return view('admin.siswa.create', [
            'kelasList' => $kelasList,
        ]);
    }
```

- [ ] **Step 5: Perbaiki `SiswaController::edit()`**

Baca baris 134-145 (mungkin bergeser). Ganti:
```php
    public function edit(Siswa $siswa): View
    {
        $this->authorize('siswa.edit');

        $siswa->load(['orangTua', 'person', 'siswaKeringanan.kategoriKeringanan']);

        $tahunAjaranAktif = TahunAjaran::where('status_aktif', true)->first();
        $kelasList = $tahunAjaranAktif
            ? Kelas::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
            : collect();

        if ($siswa->kelas_id !== null && ! $kelasList->contains('id', $siswa->kelas_id)) {
            $kelasSaatIni = Kelas::find($siswa->kelas_id);
            if ($kelasSaatIni !== null) {
                $kelasList = $kelasList->push($kelasSaatIni)->sortBy('nama')->values();
            }
        }

        return view('admin.siswa.edit', [
            'siswa' => $siswa,
            'kelasList' => $kelasList,
            'keringanan' => $siswa->siswaKeringanan->sortByDesc('berlaku_dari')->values(),
        ]);
    }
```

- [ ] **Step 6: Jalankan test lagi + regresi**

Run: `php artisan test --filter=SiswaCrudTest`
Expected: semua PASS.

- [ ] **Step 7: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/SiswaController.php tests/Feature/Admin/SiswaCrudTest.php
git commit -m "fix(akademik): filter dropdown kelas ke TA aktif di create/edit siswa, tetap preservasi kelas existing"
```

---

## Task 6: `resolveActiveLembagaId()` — 3 Controller Non-PPDB

**Files:**
- Modify: `app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php` (file SUDAH ADA dari paket sebelumnya — TAMBAH method baru, jangan buat file baru)
- Modify: `app/Http/Controllers/Admin/GuruController.php`
- Modify: `app/Http/Controllers/Admin/KalenderAkademikController.php`
- Modify: `app/Http/Controllers/Admin/PengaturanAkademikController.php`
- Test: `tests/Feature/Admin/GuruControllerTest.php`, `tests/Feature/Admin/KalenderAkademikControllerTest.php` (atau nama file test existing yang sesuai — cek dulu), `tests/Feature/Admin/PengaturanAkademikControllerTest.php`

**Interfaces:**
- Produksi: `ResolveLembagaScopeTrait::resolveActiveLembagaId(User $actor): ?int` — method BARU, TERPISAH dari `resolveLembagaId(User, ?int)` yang sudah ada dari paket sebelumnya (beda semantik: yang ini untuk READ/filter, bukan CREATE).

- [ ] **Step 1: Tulis test yang gagal — trait**

Baca `tests/Unit/Support/ResolveLembagaScopeTraitTest.php` (file sudah ada dari paket sebelumnya) untuk pola helper `objekPakaiResolveLembagaScope()`. Perluas helper itu (tambah method `panggilResolveActive`) atau buat anonymous class kedua, lalu tambahkan test:

```php
it('resolveActiveLembagaId: lembaga-scope selalu pakai lembaga_id sendiri', function () {
    $actor = User::factory()->create(['lembaga_id' => 55]);
    $obj = new class { use ResolveLembagaScopeTrait; public function panggil(User $a): ?int { return $this->resolveActiveLembagaId($a); } };

    expect($obj->panggil($actor))->toBe(55);
});

it('resolveActiveLembagaId: yayasan dengan session valid dikembalikan, session stale dikembalikan null', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasanSaya->id]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $actor = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $obj = new class { use ResolveLembagaScopeTrait; public function panggil(User $a): ?int { return $this->resolveActiveLembagaId($a); } };

    session(['active_lembaga_id' => $lembagaSaya->id]);
    expect($obj->panggil($actor))->toBe($lembagaSaya->id);

    session(['active_lembaga_id' => $lembagaLain->id]);
    expect($obj->panggil($actor))->toBeNull();

    session()->forget('active_lembaga_id');
    expect($obj->panggil($actor))->toBeNull();
});
```

(`$actor->widestScopeLevel()` untuk kedua test di atas perlu role yang sesuai — baca `ResolveLembagaScopeTraitTest.php` existing untuk pola `assignRole`/`Role::firstOrCreate` yang benar, terapkan sama di sini.)

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="resolveActiveLembagaId"`
Expected: FAIL — method belum ada.

- [ ] **Step 3: Tambah method ke trait**

Baca `app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php` (file sudah ada). Tambahkan method baru SETELAH `resolveLembagaId()`/`resolveLembagaIdUntukYayasan()` yang sudah ada (jangan hapus/ubah yang sudah ada):
```php
    private function resolveActiveLembagaId(User $actor): ?int
    {
        if ($actor->lembaga_id !== null) {
            return $actor->lembaga_id;
        }

        $lembagaId = session('active_lembaga_id');
        if ($lembagaId === null) {
            return null;
        }

        if ($actor->widestScopeLevel() === 'yayasan') {
            $milikYayasan = Lembaga::where('id', $lembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();

            return $milikYayasan ? $lembagaId : null;
        }

        return $lembagaId;
    }
```

- [ ] **Step 4: Jalankan test trait lagi, pastikan lolos**

Run: `php artisan test --filter="resolveActiveLembagaId"`
Expected: PASS.

- [ ] **Step 5: Tulis test yang gagal — `GuruController`**

Cek nama file test existing untuk `GuruController` (`find tests -iname "*Guru*Controller*"`). Tambahkan:

```php
it('menolak actor yayasan dengan active_lembaga_id stale (lembaga di luar yayasannya) saat membuat data guru', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->givePermissionTo('guru.create');
    $role = Role::firstOrCreate(['name' => 'yayasan_uji_guru', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->post(route('admin.guru.store'), [
        'nama_lengkap' => 'Guru Uji', 'jenis_kelamin' => 'L', 'jenis_ptk' => 'guru_kelas',
    ]);

    $response->assertSessionHasErrors('lembaga_id');
    expect(Guru::where('nama', 'like', '%Guru Uji%')->exists())->toBeFalse();
});
```

Sesuaikan field wajib `admin.guru.store` dan pesan error dengan `StoreGuruRequest`/validasi yang sebenarnya (baca dulu). Baca juga apakah `resolveLembagaId(Request)` yang sudah ada mengembalikan `null` lalu ditangani gimana di `store()` — pastikan test ini menguji jalur error yang sama seperti kondisi "session kosong" yang sudah ada (baris 98-99).

- [ ] **Step 6: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menolak actor yayasan dengan active_lembaga_id stale"`
Expected: FAIL — request lolos memakai lembaga milik yayasan lain.

- [ ] **Step 7: Perbaiki `GuruController`**

Baca baris 257-263 (mungkin bergeser). Tambahkan `use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;` ke import dan `use ResolveLembagaScopeTrait;` di dalam class body (setelah `use AuthorizesRequests;`). Ganti ISI method (JANGAN ganti nama method — `resolveLembagaId(Request)` TETAP dipakai nama itu di GuruController, TIDAK bentrok dengan `resolveLembagaId(User, ?int)` milik trait karena PHP membedakan method trait vs method class sendiri: method class sendiri MENANG/override otomatis atas method trait dengan nama sama tanpa error, tapi supaya TIDAK MEMBINGUNGKAN, method GuruController ini akan memanggil method BARU `resolveActiveLembagaId` dari trait, bukan `resolveLembagaId` milik trait):
```php
    private function resolveLembagaId(Request $request): ?int
    {
        return $this->resolveActiveLembagaId($request->user());
    }
```

- [ ] **Step 8: Jalankan test lagi + regresi Guru**

Run: `php artisan test --filter=Guru`
Expected: semua PASS.

- [ ] **Step 9: Tulis test yang gagal — `KalenderAkademikController`**

Cek nama file test existing. Tambahkan pola serupa Step 5 tapi untuk `admin.kalender-akademik.store` (baca route/field wajib dulu):

```php
it('menolak actor yayasan dengan active_lembaga_id stale saat menambah entri kalender', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->givePermissionTo('kalender-akademik.kelola');
    $role = Role::firstOrCreate(['name' => 'yayasan_uji_kalender', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->post(route('admin.kalender-akademik.store'), [
        'tanggal' => now()->addDays(5)->toDateString(),
        'nama' => 'Uji Libur', 'tipe' => 'libur',
    ]);

    $response->assertSessionHasErrors('lembaga_id');
    expect(KalenderAkademik::where('nama', 'Uji Libur')->exists())->toBeFalse();
});
```

- [ ] **Step 10: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menolak actor yayasan dengan active_lembaga_id stale saat menambah entri kalender"`
Expected: FAIL.

- [ ] **Step 11: Perbaiki `KalenderAkademikController::store()`**

Baca baris 21-52 (mungkin bergeser). Tambahkan `use ResolveLembagaScopeTrait;` (dan importnya). Ganti baris 40-44:
```php
        $lembagaId = null;
        if (! $nasional) {
            $lembagaId = $this->resolveActiveLembagaId($request->user());
            if ($lembagaId === null) {
                return $this->errorResponse($request, 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah entri kalender.', 'lembaga_id');
            }
        }
```
(Hapus baris lama `if (! $nasional && $request->user()->widestScopeLevel() === 'yayasan' && session('active_lembaga_id') === null) {...}` dan `$lembagaId = $nasional ? null : (...)` — digantikan blok di atas.) Baca juga apakah ada method lain di controller ini (baris ~73, ~111 disebut di audit) yang pakai pola sama — kalau ada method `update()`/`destroy()` dengan pola identik, terapkan perbaikan yang sama di situ.

- [ ] **Step 12: Jalankan test lagi + regresi**

Run: `php artisan test --filter=KalenderAkademik`
Expected: semua PASS.

- [ ] **Step 13: Tulis test yang gagal — `PengaturanAkademikController`**

Pola serupa untuk `admin.pengaturan-akademik.index` atau `updateHariAktif` (baca route yang ada dulu):

```php
it('menolak actor yayasan dengan active_lembaga_id stale mengakses Pengaturan Akademik', function () {
    $yayasanSaya = Yayasan::factory()->create();
    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    $manager = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasanSaya->id]);
    $manager->givePermissionTo('kalender-akademik.view');
    $role = Role::firstOrCreate(['name' => 'yayasan_uji_pengaturan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $manager->assignRole($role);
    session(['active_lembaga_id' => $lembagaLain->id]);

    $response = $this->actingAs($manager)->get(route('admin.pengaturan-akademik.index'));

    $response->assertRedirect();
    $response->assertSessionHasErrors('lembaga_id');
});
```

- [ ] **Step 14: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menolak actor yayasan dengan active_lembaga_id stale mengakses Pengaturan Akademik"`
Expected: FAIL — request lolos (halaman ter-render memakai lembaga asing).

- [ ] **Step 15: Perbaiki `PengaturanAkademikController`**

Baca baris 20-64 (mungkin bergeser). Tambahkan `use ResolveLembagaScopeTrait;`. Ganti `index()` baris 24-29:
```php
        $lembagaId = $this->resolveActiveLembagaId($request->user());
        if ($lembagaId === null) {
            return redirect()->route('dashboard')
                ->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga untuk mengakses Pengaturan Akademik.']);
        }
```
Ganti `updateHariAktif()` baris 46-58 dengan pola sama (guard null di awal, `resolveActiveLembagaId` menggantikan `$request->user()->lembaga_id ?? session('active_lembaga_id')`).

- [ ] **Step 16: Jalankan test lagi + regresi**

Run: `php artisan test --filter=PengaturanAkademik`
Expected: semua PASS.

- [ ] **Step 17: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php app/Http/Controllers/Admin/GuruController.php app/Http/Controllers/Admin/KalenderAkademikController.php app/Http/Controllers/Admin/PengaturanAkademikController.php tests/Unit/Support/ResolveLembagaScopeTraitTest.php
git commit -m "fix(akademik): verifikasi ulang session active_lembaga_id di titik pakai -- GuruController, KalenderAkademikController, PengaturanAkademikController"
```

(Sesuaikan daftar file test yang di-`git add` dengan nama file test aktual yang ditemukan/dipakai di Step 5, 9, 13.)

---

## Task 7: `kelas_terakhir_id` untuk Lulus Massal

**Files:**
- Modify: `app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php`
- Test: `tests/Feature/Admin/KenaikanKelasControllerTest.php`

**Interfaces:** Tidak ada — perbaikan berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan di `tests/Feature/Admin/KenaikanKelasControllerTest.php`:

```php
it('mengisi kelas_terakhir_id saat siswa lulus lewat Kenaikan Kelas massal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'tingkat' => '6']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => ['tindakan' => 'lulus'],
        ],
    ])->assertRedirect(route('admin.kelas.index'));

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Lulus);
    expect($siswa->kelas_id)->toBeNull();
    expect($siswa->kelas_terakhir_id)->toBe($kelasLama->id);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="mengisi kelas_terakhir_id saat siswa lulus"`
Expected: FAIL — `kelas_terakhir_id` masih `null`.

- [ ] **Step 3: Perbaiki `ProsesKenaikanKelasAction`**

Baca baris 42-49 (mungkin bergeser). Ganti:
```php
                if ($aksi['tindakan'] === 'lulus') {
                    Siswa::where('kelas_id', $kelasLama->id)->update([
                        'status' => StatusSiswa::Lulus->value,
                        'kelas_terakhir_id' => DB::raw('kelas_id'),
                        'kelas_id' => null,
                    ]);

                    continue;
                }
```
**PENTING**: urutan array HARUS `kelas_terakhir_id` SEBELUM `kelas_id => null` persis seperti di atas — MySQL mengevaluasi klausa `SET` berurutan kiri-ke-kanan, kalau dibalik `kelas_terakhir_id` akan ikut ter-null-kan.

- [ ] **Step 4: Jalankan test lagi + regresi**

Run: `php artisan test --filter=KenaikanKelasControllerTest`
Expected: semua PASS.

- [ ] **Step 5: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php tests/Feature/Admin/KenaikanKelasControllerTest.php
git commit -m "fix(akademik): isi kelas_terakhir_id saat siswa lulus massal lewat Kenaikan Kelas"
```

---

## Task 8: Full Test Suite Final

**Files:** Tidak ada file diubah — verifikasi akhir.

- [ ] **Step 1: Pastikan tidak ada proses test lain berjalan**

Run: `ps aux | grep artisan | grep -v grep`
Expected: kosong.

- [ ] **Step 2: Jalankan full suite sendirian**

Run: `php artisan test --compact`
Expected: SEMUA test PASS, 0 failures (kecuali test SPMB flaky yang sudah diketahui tidak berkaitan — data Faker acak mengandung apostrof; kalau muncul, jalankan ulang sendirian untuk konfirmasi flaky, bukan regresi paket ini).

- [ ] **Step 3: Pint final**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` atau auto-fix tanpa error.

---

## Self-Review

**1. Spec coverage**: §2.1→Task 1, §2.2→Task 2, §2.3→Task 3, §2.4→Task 4, §2.5→Task 5, §2.6→Task 6, §2.7→Task 7. §3 Non-Goals (TagihanBillingGenerator tidak disentuh, PPDB controller tidak disentuh, Activitylog tidak masuk) — tidak ada task yang melanggarnya.

**2. Placeholder scan**: tidak ada TBD — setiap step berisi kode lengkap. Beberapa step (Task 2 Step 5, Task 6 Step 5/9/13) minta implementer baca struktur aktual dulu (nama route/field) sebelum finalisasi test — ini instruksi eksplisit, bukan placeholder kosong.

**3. Type consistency**: `resolveActiveLembagaId(User $actor): ?int` dipakai identik nama/parameter di Task 6 Step 3 (definisi) dan Step 7/11/15 (pemakaian di 3 controller). `DB::raw('kelas_id')` dan urutan array di Task 7 konsisten dengan penjelasan teknis di spec.

**4. Dependency antar-task**: Task 4 WAJIB dikerjakan SETELAH Task 2 (sama-sama edit `RppController::store()`) — sudah dicatat eksplisit di Interfaces Task 4. Task lain independen, urutan sisanya bebas tapi disarankan ikuti urutan 1→3→5→6→7 sesuai prioritas severity di spec.
