# Fix Kritis IDOR Lintas-Guru pada RppController Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup celah IDOR lintas-guru pada `RppController` (update/submit/destroy/download tanpa cek kepemilikan, store tanpa cek kombinasi mengajar) dan menambah cross-check lembaga eksplisit di `VerifyRppAction`.

**Architecture:** Semua fix di layer Controller (private method `authorizeMilikGuru()` + guard tambahan) plus 1 parameter baru di `VerifyRppAction`. Tidak ada DTO/Action lain yang berubah, tidak ada migration.

**Tech Stack:** Laravel 12.68, Pest v4, MySQL.

## Global Constraints

- `authorizeMilikGuru()` TIDAK MENGGANTIKAN `$this->authorize('rpp.kelola')` yang sudah ada di `submit()`/`destroy()` — keduanya lapis terpisah (permission generik + kepemilikan spesifik).
- `download()`: guru pemilik ATAU user dengan permission `rpp.verify` — bukan permission `rpp.view` generik lagi.
- `store()`: verifikasi kombinasi mengajar HANYA berlaku kalau aktor adalah guru (`$guru !== null` dari variabel yang SUDAH ADA di method, baris 141) — dilewati sepenuhnya untuk aktor non-guru (admin/staf membuatkan RPP atas nama guru lain via fallback `guru_id`).
- `VerifyRppAction::execute()` bertambah 1 parameter wajib `int $verifierLembagaId` — SEMUA pemanggil method ini harus diperbarui (cek grep dulu, jangan asumsi cuma 1 pemanggil).
- Tidak mengubah `RppData`/`CreateRppAction`/`UpdateRppAction`/`SubmitRppAction`/`DeleteRppAction`.
- Tidak mengubah skema, tidak ada migration.
- Jalankan `vendor/bin/pint --dirty --format agent` di akhir setiap task sebelum commit.
- Test scoped per task. **Task terakhir WAJIB menjalankan full test suite (`php artisan test --compact`, TANPA filter)** sebagai pengaman — fix ini menyentuh otorisasi yang berpotensi berdampak ke test lain yang sudah ada di luar file RPP (siapa pun yang test-nya membuat/memanipulasi `Rpp` langsung tanpa lewat guard baru ini).

---

### Task 1: Perbaiki baseline fixture test existing SEBELUM menulis fix (mencegah regresi tak terduga)

**Files:**
- Modify: `tests/Feature/Akademik/RppWorkflowTest.php:20-48` (beforeEach)

**Interfaces:**
- Tidak ada — murni penyesuaian fixture test.

- [x] **Step 1: Baca file existing untuk konfirmasi baseline**

Baca `tests/Feature/Akademik/RppWorkflowTest.php` baris 1-96 penuh — pastikan `beforeEach` (baris 20-48) dan test "mendukung upload RPP tematik PAUD tanpa mata pelajaran" (baris 80-96) persis seperti kutipan di Step 2. Kalau beda, STOP dan laporkan ke user.

- [x] **Step 2: Tambah `wali_kelas_guru_id` ke fixture `$this->kelas`**

`$this->kelas` dibuat SEBELUM `$this->guru` di `beforeEach` (baris 37, 43), dengan `wali_kelas_guru_id` default `null` dari factory. Test "RPP tematik PAUD tanpa mata pelajaran" (baris 80-96) mengasumsikan guru boleh membuat RPP tanpa `mata_pelajaran_id` untuk kelas itu — begitu Task 3 (§2.3 spec) menambah verifikasi "hanya wali kelas boleh membuat RPP tematik", test ini akan gagal kecuali `$this->kelas->wali_kelas_guru_id` benar-benar diisi guru itu.

Edit `tests/Feature/Akademik/RppWorkflowTest.php`, tambahkan SATU BARIS setelah baris `$this->guru = Guru::factory()->create(['user_id' => $this->userGuru->id, 'lembaga_id' => $this->lembaga->id, 'nama' => 'Ustadzah Maya']);` (baris 43):

```php
    $this->kelas->update(['wali_kelas_guru_id' => $this->guru->id]);
```

- [x] **Step 3: Jalankan test existing, pastikan masih PASS (baseline belum berubah perilaku)**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php --compact`
Expected: PASS, semua test existing tetap lulus — perubahan ini hanya melengkapi data fixture, belum ada logic baru yang bergantung padanya sampai Task 3.

- [x] **Step 4: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/Akademik/RppWorkflowTest.php
git commit -m "test(akademik): lengkapi fixture wali_kelas_guru_id di RppWorkflowTest"
```

---

### Task 2: Ownership check untuk `update()`, `submit()`, `destroy()`, `download()`

**Files:**
- Modify: `app/Http/Controllers/Admin/RppController.php`
- Test: `tests/Feature/Akademik/RppControllerIdorTest.php` (BARU)

**Interfaces:**
- Produces: `RppController::authorizeMilikGuru(Rpp $rpp): void` (private) — dipakai internal saja.

- [x] **Step 1: Baca file existing untuk konfirmasi baseline**

Baca `app/Http/Controllers/Admin/RppController.php` penuh — pastikan method `update()` (baris 192-214), `submit()` (216-236), `destroy()` (269-289), `download()` (172-190) persis seperti kutipan di plan ini (lihat Step 3-5). Kalau beda, STOP dan laporkan.

- [x] **Step 2: Tulis test yang gagal**

Buat `tests/Feature/Akademik/RppControllerIdorTest.php`:

```php
<?php

use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\Rpp;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function siapkanRppIdorFixture(): array
{
    Storage::fake('public');
    Permission::firstOrCreate(['name' => 'rpp.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rpp.kelola', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rpp.verify', 'guard_name' => 'web']);

    $roleGuru = Role::firstOrCreate(['name' => 'guru_idor_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $roleGuru->givePermissionTo(['rpp.view', 'rpp.kelola']);

    $roleKurikulum = Role::firstOrCreate(['name' => 'wakasek_idor_test', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $roleKurikulum->givePermissionTo(['rpp.view', 'rpp.kelola', 'rpp.verify']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $userGuruA = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userGuruA->assignRole($roleGuru);
    $guruA = Guru::factory()->create(['user_id' => $userGuruA->id, 'lembaga_id' => $lembaga->id]);

    $userGuruB = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userGuruB->assignRole($roleGuru);
    $guruB = Guru::factory()->create(['user_id' => $userGuruB->id, 'lembaga_id' => $lembaga->id]);

    $userKurikulum = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKurikulum->assignRole($roleKurikulum);

    $rppMilikA = Rpp::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'guru_id' => $guruA->id,
        'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id, 'kelas_id' => $kelas->id,
        'mata_pelajaran_id' => $mapel->id, 'judul_topik' => 'RPP Milik Guru A',
        'alokasi_waktu' => '2 JP', 'file_path' => 'rpp/milik-a.pdf', 'file_name' => 'milik-a.pdf',
        'file_size_bytes' => 1024, 'mime_type' => 'application/pdf', 'status' => StatusRpp::Draft,
    ]);
    Storage::disk('public')->put('rpp/milik-a.pdf', 'dummy-content');

    return [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA];
}

it('rejects Guru B updating an RPP owned by Guru A', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userGuruB)->put(route('admin.rpp.update', $rppMilikA), [
        'kelas_id' => $rppMilikA->kelas_id,
        'judul_topik' => 'Diubah Paksa Oleh Guru B',
        'alokasi_waktu' => '2 JP',
    ])->assertForbidden();

    expect($rppMilikA->fresh()->judul_topik)->toBe('RPP Milik Guru A');
});

it('rejects Guru B submitting an RPP owned by Guru A', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userGuruB)->post(route('admin.rpp.submit', $rppMilikA))->assertForbidden();

    expect($rppMilikA->fresh()->status)->toBe(StatusRpp::Draft);
});

it('rejects Guru B destroying an RPP owned by Guru A', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userGuruB)->delete(route('admin.rpp.destroy', $rppMilikA))->assertForbidden();

    expect(Rpp::find($rppMilikA->id))->not->toBeNull();
});

it('allows Guru A to update, submit their own RPP as before', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userGuruA)->put(route('admin.rpp.update', $rppMilikA), [
        'kelas_id' => $rppMilikA->kelas_id,
        'judul_topik' => 'Diubah Oleh Pemilik Sah',
        'alokasi_waktu' => '2 JP',
    ])->assertRedirect();

    expect($rppMilikA->fresh()->judul_topik)->toBe('Diubah Oleh Pemilik Sah');

    $this->actingAs($userGuruA)->post(route('admin.rpp.submit', $rppMilikA))->assertRedirect();
    expect($rppMilikA->fresh()->status)->toBe(StatusRpp::Diajukan);
});

it('rejects Guru B downloading an RPP owned by Guru A without rpp.verify', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userGuruB)->get(route('admin.rpp.download', $rppMilikA))->assertForbidden();
});

it('allows a user with rpp.verify to download any RPP in the same lembaga', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userKurikulum)->get(route('admin.rpp.download', $rppMilikA))->assertOk();
});

it('allows Guru A to download their own RPP as before', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $this->actingAs($userGuruA)->get(route('admin.rpp.download', $rppMilikA))->assertOk();
});
```

- [x] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Akademik/RppControllerIdorTest.php --compact`
Expected: FAIL — Guru B saat ini masih BISA update/submit/destroy/download RPP milik Guru A (bug yang sedang diperbaiki).

- [x] **Step 4: Tambah `authorizeMilikGuru()` dan pasang di `update()`/`submit()`/`destroy()`**

Edit `app/Http/Controllers/Admin/RppController.php`. Ubah `update()` (baris 192-196) dari:

```php
    public function update(UpdateRppRequest $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $kelas = Kelas::findOrFail($request->input('kelas_id'));

        $dto = $request->toDTO($rpp, $kelas);
```

menjadi:

```php
    public function update(UpdateRppRequest $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $this->authorizeMilikGuru($rpp);

        $kelas = Kelas::findOrFail($request->input('kelas_id'));

        $dto = $request->toDTO($rpp, $kelas);
```

Ubah `submit()` (baris 216-220) dari:

```php
    public function submit(Request $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $this->authorize('rpp.kelola');

        try {
```

menjadi:

```php
    public function submit(Request $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $this->authorize('rpp.kelola');
        $this->authorizeMilikGuru($rpp);

        try {
```

Ubah `destroy()` (baris 269-273) dari:

```php
    public function destroy(Request $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $this->authorize('rpp.kelola');

        try {
```

menjadi:

```php
    public function destroy(Request $request, Rpp $rpp): RedirectResponse|JsonResponse
    {
        $this->authorize('rpp.kelola');
        $this->authorizeMilikGuru($rpp);

        try {
```

Tambah private method baru di akhir class (sebelum `}` penutup):

```php
    private function authorizeMilikGuru(Rpp $rpp): void
    {
        $guru = auth()->user()->guru;
        abort_if($guru === null || $rpp->guru_id !== $guru->id, 403);
    }
```

- [x] **Step 5: Ubah `download()` — guard guru pemilik ATAU `rpp.verify`**

Ubah `download()` (baris 172-175) dari:

```php
    public function download(Request $request, Rpp $rpp): Response
    {
        $this->authorize('rpp.view');

        if (! Storage::disk('public')->exists($rpp->file_path)) {
```

menjadi:

```php
    public function download(Request $request, Rpp $rpp): Response
    {
        $guru = auth()->user()->guru;
        $isPemilik = $guru !== null && $rpp->guru_id === $guru->id;
        abort_unless($isPemilik || auth()->user()->can('rpp.verify'), 403);

        if (! Storage::disk('public')->exists($rpp->file_path)) {
```

- [x] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/RppControllerIdorTest.php --compact`
Expected: PASS, 7/7 test.

- [x] **Step 7: Jalankan test RPP existing supaya tidak regresi**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php tests/Feature/Akademik/RppKurikulumReportingTest.php --compact`
Expected: PASS semua — kalau ada yang gagal, cek apakah test itu memanipulasi RPP milik guru lain tanpa acting sbg pemiliknya (kalau ya, itu test lama yang kebetulan lolos karena bug ini, WAJIB diperbaiki fixture-nya bukan kode Task 2 — laporkan temuan ini di report akhir).

- [x] **Step 8: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/RppController.php tests/Feature/Akademik/RppControllerIdorTest.php
git commit -m "fix(akademik): cegah IDOR lintas-guru pada update/submit/destroy/download RPP"
```

---

### Task 3: `store()` — verifikasi kombinasi mengajar

**Files:**
- Modify: `app/Http/Controllers/Admin/RppController.php:138-170`
- Test: `tests/Feature/Akademik/RppControllerIdorTest.php` (tambah test, file sudah dibuat di Task 2)

**Interfaces:**
- Consumes: variabel `$guru` yang SUDAH ADA di `store()` (baris 141, `$guru = Guru::where('user_id', $user->id)->first();`) — TIDAK membuat pemanggilan baru.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan di akhir `tests/Feature/Akademik/RppControllerIdorTest.php`:

```php
it('rejects store when the guru does not teach the selected kelas+mapel+semester combination', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();
    $kelas = Kelas::find($rppMilikA->kelas_id);
    $mapel = MataPelajaran::find($rppMilikA->mata_pelajaran_id);
    $semester = Semester::find($rppMilikA->semester_id);
    $file = \Illuminate\Http\UploadedFile::fake()->create('rpp-baru.pdf', 100, 'application/pdf');

    // Guru B tidak punya JadwalPelajaran untuk kombinasi kelas+mapel+semester ini.
    $this->actingAs($userGuruB)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'mata_pelajaran_id' => $mapel->id,
        'judul_topik' => 'RPP Tidak Sah',
        'alokasi_waktu' => '2 JP',
        'file' => $file,
    ])->assertForbidden();

    expect(Rpp::where('judul_topik', 'RPP Tidak Sah')->exists())->toBeFalse();
});

it('allows store when the guru actually teaches the selected combination (via JadwalPelajaran)', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();
    $kelas = Kelas::find($rppMilikA->kelas_id);
    $mapel = MataPelajaran::find($rppMilikA->mata_pelajaran_id);
    $semester = Semester::find($rppMilikA->semester_id);
    $guruB = Guru::where('user_id', $userGuruB->id)->first();
    $jamPelajaran = \App\Domains\Akademik\Models\JamPelajaran::factory()->create();
    \App\Models\JadwalPelajaran::create([
        'kelas_id' => $kelas->id, 'guru_id' => $guruB->id, 'mata_pelajaran_id' => $mapel->id,
        'semester_id' => $semester->id, 'jam_pelajaran_id' => $jamPelajaran->id,
    ]);
    $file = \Illuminate\Http\UploadedFile::fake()->create('rpp-sah.pdf', 100, 'application/pdf');

    $this->actingAs($userGuruB)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'mata_pelajaran_id' => $mapel->id,
        'judul_topik' => 'RPP Sah Guru B',
        'alokasi_waktu' => '2 JP',
        'file' => $file,
    ])->assertRedirect();

    expect(Rpp::where('judul_topik', 'RPP Sah Guru B')->exists())->toBeTrue();
});

it('rejects store of a tematik RPP (no mata_pelajaran_id) when the guru is not the wali kelas', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();
    $kelas = Kelas::find($rppMilikA->kelas_id);
    $semester = Semester::find($rppMilikA->semester_id);
    // $kelas belum punya wali_kelas_guru_id (default null dari factory) -- guru mana pun BUKAN wali kelasnya.
    $file = \Illuminate\Http\UploadedFile::fake()->create('rpp-tematik.pdf', 100, 'application/pdf');

    $this->actingAs($userGuruB)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'mata_pelajaran_id' => null,
        'judul_topik' => 'RPP Tematik Tidak Sah',
        'alokasi_waktu' => '1 Pekan',
        'file' => $file,
    ])->assertForbidden();

    expect(Rpp::where('judul_topik', 'RPP Tematik Tidak Sah')->exists())->toBeFalse();
});

it('allows store of a tematik RPP when the guru IS the wali kelas', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();
    $kelas = Kelas::find($rppMilikA->kelas_id);
    $semester = Semester::find($rppMilikA->semester_id);
    $guruB = Guru::where('user_id', $userGuruB->id)->first();
    $kelas->update(['wali_kelas_guru_id' => $guruB->id]);
    $file = \Illuminate\Http\UploadedFile::fake()->create('rpp-tematik-sah.pdf', 100, 'application/pdf');

    $this->actingAs($userGuruB)->post(route('admin.rpp.store'), [
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'mata_pelajaran_id' => null,
        'judul_topik' => 'RPP Tematik Sah Wali Kelas',
        'alokasi_waktu' => '1 Pekan',
        'file' => $file,
    ])->assertRedirect();

    expect(Rpp::where('judul_topik', 'RPP Tematik Sah Wali Kelas')->exists())->toBeTrue();
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="teach the selected|actually teaches|wali kelas" --compact`
Expected: FAIL — `store()` belum memverifikasi kombinasi mengajar, semua kombinasi (sah maupun tidak) sama-sama diterima saat ini.

- [x] **Step 3: Tambah verifikasi kombinasi mengajar di `store()`**

Edit `app/Http/Controllers/Admin/RppController.php`. Tambah import:

```php
use App\Models\JadwalPelajaran;
```

Ubah `store()` (baris 138-152), sisipkan blok verifikasi SETELAH resolusi `$guruId` (setelah baris `if (! $guruId) { abort(422, ...); }`), SEBELUM `$dto = $request->toDTO(...)`:

```php
    public function store(StoreRppRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $guru = Guru::where('user_id', $user->id)->first();

        $kelas = Kelas::findOrFail($request->input('kelas_id'));
        $semester = Semester::findOrFail($request->input('semester_id'));

        $guruId = $guru ? $guru->id : ($request->input('guru_id') ?: Guru::where('lembaga_id', $kelas->lembaga_id)->value('id'));

        if (! $guruId) {
            abort(422, 'Profil guru pengampu tidak ditemukan.');
        }

        if ($guru !== null) {
            if ($request->filled('mata_pelajaran_id')) {
                $mengajarKombinasiIni = JadwalPelajaran::where('guru_id', $guru->id)
                    ->where('kelas_id', $kelas->id)
                    ->where('mata_pelajaran_id', $request->input('mata_pelajaran_id'))
                    ->where('semester_id', $semester->id)
                    ->exists();

                abort_unless($mengajarKombinasiIni, 403, 'Anda tidak mengajar kombinasi kelas dan mata pelajaran ini.');
            } else {
                abort_unless((int) $kelas->wali_kelas_guru_id === $guru->id, 403, 'RPP tematik tanpa mata pelajaran hanya dapat dibuat oleh wali kelas.');
            }
        }

        $dto = $request->toDTO((int) $guruId, $kelas, $semester);
```

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/RppControllerIdorTest.php --compact`
Expected: PASS, 11/11 test (7 dari Task 2 + 4 baru).

- [x] **Step 5: Jalankan test RPP existing supaya tidak regresi**

Run: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php tests/Feature/Akademik/RppKurikulumReportingTest.php --compact`
Expected: sebelum diperbaiki, 2 test di `StoreRppRequestKelasSemesterTest.php` akan GAGAL — sudah diketahui sebelumnya, perbaiki SEKARANG (bukan sekadar "cek"):

**`tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php`** — fungsi `siapkanRppRequestFixture()` (baris 17-38) membuat `$kelasTahunA`/`$kelasTahunB` TANPA `wali_kelas_guru_id`, dan kedua test POST ke `store()` TANPA `mata_pelajaran_id` di payload (baris 44-50, dan test "allows store..." yang serupa) — artinya request itu akan jatuh ke cabang "tematik tanpa mapel" di fix Task 3, yang menolak karena guru bukan wali kelas. Test "rejects store when kelas belongs to a different tahun ajaran..." (baris 40-53) TETAP AMAN karena request itu sudah ditolak oleh `withValidator()` (422) SEBELUM mencapai kode Task 3 — FormRequest validasi jalan duluan sebelum method controller dieksekusi. Tapi test **"allows store when kelas and semester share the same tahun ajaran"** (kombinasi valid) akan menembus validasi dan kena guard baru Task 3 secara salah.

Edit `tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php`, ubah `siapkanRppRequestFixture()` (baris 17-38) — assign `$guru` sbg wali kelas `$kelasTahunA` (kelas yang dipakai skenario "allows"):

```php
function siapkanRppRequestFixture(): array
{
    Storage::fake('public');
    Permission::firstOrCreate(['name' => 'rpp.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rpp.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_rpp_validasi', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['rpp.view', 'rpp.kelola']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunA = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026']);
    $tahunB = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027']);
    $semesterTahunA = Semester::factory()->create(['tahun_ajaran_id' => $tahunA->id]);
    $kelasTahunA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunA->id]);
    $kelasTahunB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunB->id]);

    $userGuru = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userGuru->assignRole($role);
    $guru = Guru::factory()->create(['user_id' => $userGuru->id, 'lembaga_id' => $lembaga->id]);
    $kelasTahunA->update(['wali_kelas_guru_id' => $guru->id]);

    return [$userGuru, $semesterTahunA, $kelasTahunA, $kelasTahunB];
}
```

(Cukup 1 baris baru `$kelasTahunA->update(...)` sebelum `return` — sisanya tidak berubah. `$kelasTahunB` sengaja TIDAK diberi wali kelas karena tidak pernah dipakai sbg target `store()` sukses di file test ini.)

Setelah itu jalankan ulang: `php artisan test tests/Feature/Akademik/RppWorkflowTest.php tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php tests/Feature/Akademik/RppKurikulumReportingTest.php --compact`
Expected: PASS semua. `RppKurikulumReportingTest.php` tidak terpengaruh sama sekali karena fixture-nya membuat `Rpp::create()` langsung (bypass endpoint `store()`), tidak pernah memanggil `RppController::store()`.

- [x] **Step 6: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/RppController.php tests/Feature/Akademik/RppControllerIdorTest.php tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php
git commit -m "fix(akademik): verifikasi kombinasi mengajar guru pada store RPP"
```

---

### Task 4: `VerifyRppAction` — cross-check `lembaga_id` verifier

**Files:**
- Modify: `app/Domains/Akademik/Actions/Rpp/VerifyRppAction.php`
- Modify: `app/Http/Controllers/Admin/RppController.php:238-267` (method `verify()`)
- Test: `tests/Feature/Akademik/RppControllerIdorTest.php` (tambah test, file sudah dibuat)

**Interfaces:**
- Produces: `VerifyRppAction::execute(Rpp $rpp, StatusRpp $targetStatus, int $verifierUserId, int $verifierLembagaId, ?string $catatanRevisi = null): Rpp` — signature BERTAMBAH 1 parameter wajib `int $verifierLembagaId` (disisipkan SEBELUM `$catatanRevisi` yang punya default value, supaya parameter tanpa default tidak berada setelah parameter dgn default).

- [x] **Step 1: Cek SEMUA pemanggil `VerifyRppAction::execute()` sebelum ubah signature**

Run: `grep -rn "verifyRppAction->execute\|VerifyRppAction::execute" app/ tests/`
Expected: hanya ditemukan di `RppController::verify()` (produksi) dan mungkin di test lain yang memanggil Action ini langsung (bukan lewat HTTP). Kalau ditemukan pemanggil lain di luar yang disebut plan ini, STOP dan laporkan ke user sebelum lanjut.

- [x] **Step 2: Tulis test yang gagal**

Tambahkan di akhir `tests/Feature/Akademik/RppControllerIdorTest.php`:

```php
it('rejects verify from a verifier belonging to a different lembaga than the RPP', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();

    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $userKurikulumLembagaLain = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $userKurikulumLembagaLain->assignRole('wakasek_idor_test');

    $rppMilikA->update(['status' => StatusRpp::Diajukan]);

    $this->actingAs($userKurikulumLembagaLain)->post(route('admin.rpp.verify', $rppMilikA), [
        'status' => StatusRpp::Disetujui->value,
    ])->assertSessionHasErrors(['lembaga']);

    expect($rppMilikA->fresh()->status)->toBe(StatusRpp::Diajukan);
});

it('allows verify from a verifier in the same lembaga as before', function () {
    [$userGuruA, $userGuruB, $userKurikulum, $rppMilikA] = siapkanRppIdorFixture();
    $rppMilikA->update(['status' => StatusRpp::Diajukan]);

    $this->actingAs($userKurikulum)->post(route('admin.rpp.verify', $rppMilikA), [
        'status' => StatusRpp::Disetujui->value,
    ])->assertRedirect();

    expect($rppMilikA->fresh()->status)->toBe(StatusRpp::Disetujui);
});
```

- [x] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="different lembaga than the RPP|verifier in the same lembaga" --compact`
Expected: test pertama FAIL (verifier lembaga lain saat ini masih bisa verify), test kedua kemungkinan PASS (regresi, boleh sudah lulus di titik ini).

- [x] **Step 4: Tambah parameter & cross-check di `VerifyRppAction`**

Edit `app/Domains/Akademik/Actions/Rpp/VerifyRppAction.php`, ubah dari:

```php
    public function execute(Rpp $rpp, StatusRpp $targetStatus, int $verifierUserId, ?string $catatanRevisi = null): Rpp
    {
        if ($rpp->status !== StatusRpp::Diajukan) {
```

menjadi:

```php
    public function execute(Rpp $rpp, StatusRpp $targetStatus, int $verifierUserId, int $verifierLembagaId, ?string $catatanRevisi = null): Rpp
    {
        if ((int) $rpp->lembaga_id !== $verifierLembagaId) {
            throw ValidationException::withMessages([
                'lembaga' => 'Anda tidak berwenang memverifikasi dokumen RPP lembaga lain.',
            ]);
        }

        if ($rpp->status !== StatusRpp::Diajukan) {
```

- [x] **Step 5: Update pemanggil di `RppController::verify()`**

Edit `app/Http/Controllers/Admin/RppController.php`, ubah pemanggilan (baris 244-250) dari:

```php
            $this->verifyRppAction->execute(
                rpp: $rpp,
                targetStatus: $targetStatus,
                verifierUserId: (int) $request->user()->id,
                catatanRevisi: $catatanRevisi
            );
```

menjadi:

```php
            $this->verifyRppAction->execute(
                rpp: $rpp,
                targetStatus: $targetStatus,
                verifierUserId: (int) $request->user()->id,
                verifierLembagaId: (int) $request->user()->lembaga_id,
                catatanRevisi: $catatanRevisi
            );
```

- [x] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Akademik/RppControllerIdorTest.php --compact`
Expected: PASS, 13/13 test (11 dari Task 2-3 + 2 baru).

- [x] **Step 7: Format & commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Actions/Rpp/VerifyRppAction.php app/Http/Controllers/Admin/RppController.php tests/Feature/Akademik/RppControllerIdorTest.php
git commit -m "fix(akademik): tambah cross-check lembaga eksplisit di VerifyRppAction"
```

---

### Task 5: Checkpoint full test suite (WAJIB) + dokumentasi

**Files:**
- Tidak ada file produksi baru — murni verifikasi + docs.

- [x] **Step 1: Jalankan full test suite TANPA filter**

Run: `php artisan test --compact`
Expected: 0 failed. Fix ini menyentuh otorisasi yang dipakai lintas fitur — WAJIB dijalankan penuh sbg pengaman terakhir, bukan cuma test scoped.

**Kalau ada test GAGAL di luar file yang sudah disebutkan plan ini**: itu tanda ada test lain (di luar domain RPP) yang membuat/memanipulasi `Rpp` model langsung tanpa acting sbg pemilik yang benar, atau memanggil `VerifyRppAction::execute()` langsung tanpa parameter baru. STOP, investigasi test yang gagal, JANGAN melonggarkan guard baru untuk "meloloskan" test — perbaiki fixture test tsb (tambah kepemilikan yang benar / parameter yang hilang), laporkan di report akhir sbg penyesuaian yang diperlukan.

- [x] **Step 2: Catat fix ini di `PETA_PENGEMBANGAN.md`**

Tambahkan entri baru (bukan sub-item dari "Audit Sistematis Akademik Tahap 2" yang sudah ditutup — ini temuan dari audit ulang total terpisah) dengan judul singkat "Fix Kritis: IDOR Lintas-Guru RppController (27 Agustus 2026)", ringkasan 2-3 kalimat (celah yang ditemukan, 4 titik yang diperbaiki: update/submit/destroy/download + store + VerifyRppAction), dan link ke spec/plan/log.

```bash
git add PETA_PENGEMBANGAN.md
git commit -m "docs: catat fix kritis IDOR RppController"
```

- [ ] **Step 3: Laporkan angka pasti**

Di report akhir ke user, cantumkan angka pasti full suite (`passed`/`skipped`/`failed`/`assertions`/durasi) dari output nyata Step 1 — jangan diasumsikan atau dibulatkan dari klaim task sebelumnya.
