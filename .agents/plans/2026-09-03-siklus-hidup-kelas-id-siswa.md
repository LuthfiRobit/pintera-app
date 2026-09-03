# Siklus Hidup `kelas_id` Siswa Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Menutup akar masalah `kelas_id` siswa yang tidak pernah dibersihkan saat status berubah ke Lulus/Pindah/Keluar, dengan invariant terjamin di level database (CHECK constraint), snapshot historis (`kelas_terakhir_id`), dan accessor terpusat untuk tampilan.

**Architecture:** 1 migration (kolom + backfill + CHECK constraint) → 1 Action baru (`UpdateStatusSiswaAction`) menggantikan logic inline di controller → 1 accessor model (`kelas_efektif`) dipakai di 3 titik tampilan → 2 perbaikan pencegahan (validasi form, fix test lama) yang WAJIB mendahului/mengiringi migration supaya tidak ada jendela waktu di mana test suite merah atau form produksi crash.

**Tech Stack:** Laravel 12, PHP 8.3, Pest, MySQL 8.0.30.

## Global Constraints

- Ketiga status non-aktif (`Lulus`, `Pindah`, `Keluar`) diperlakukan SAMA — semuanya null-kan `kelas_id` via snapshot ke `kelas_terakhir_id` saat transisi keluar dari `Aktif`.
- Reversal otomatis: transisi balik ke `Aktif` memulihkan `kelas_id` dari `kelas_terakhir_id`, lalu `kelas_terakhir_id` di-null-kan lagi.
- Idempotency wajib: kalau status target sama dengan status sekarang, TIDAK ADA perubahan `kelas_id`/`kelas_terakhir_id` sama sekali.
- Urutan WAJIB di dalam migration: backfill data existing SEBELUM `ADD CONSTRAINT` — MySQL memvalidasi seluruh baris existing terhadap CHECK constraint baru saat `ALTER TABLE`; kalau dibalik, migration gagal begitu ada baris yang melanggar.
- Backfill sinkron, 1 statement SQL — TIDAK pakai queued job/chunking (dikonfirmasi cukup: 0 dari 336 siswa non-aktif di database dev saat spec ditulis).
- Accessor `kelas_efektif` dipakai di SEMUA 3 tempat yang menampilkan kelas siswa non-aktif (`RaporPdfDataBuilder`, `_daftar.blade.php`, `profil.blade.php`) — JANGAN bikin 3 logic fallback terpisah yang bisa menyimpang.
- `JenisTagihanSasaranMatcher` dan `TagihanBillingGenerator` (`app/Domains/Keuangan/Services/`) TIDAK BOLEH disentuh sama sekali di plan ini — di luar scope, sedang aktif digarap paralel di branch `keuangan-v2`.
- Tidak pindah branch — semua kerja tetap di `akademik-v2`.
- Tidak ada perubahan pada validasi tingkat Kenaikan Kelas atau konsep "siswa tinggal kelas" — topik terpisah (Kelompok B), tidak digarap di plan ini.
- Setiap task WAJIB diakhiri test suite hijau (regresi terkait dijalankan sendirian) — TIDAK BOLEH ada jendela antar-task di mana test lain jadi merah.

---

## Task 1: Guard Validasi `kelas_id` untuk Siswa Non-Aktif di `SiswaController`

**Files:**
- Modify: `app/Http/Controllers/Admin/SiswaController.php` (method `validateSiswa()`, sekitar baris 279-303)
- Test: `tests/Feature/Admin/SiswaCrudTest.php`

**Interfaces:**
- Konsumsi: `App\Enums\StatusSiswa` (enum existing, sudah diimport di file ini).
- Produksi: `validateSiswa()` sekarang melempar `ValidationException` dengan key `kelas_id` kalau siswa yang diedit berstatus non-`Aktif` dan `kelas_id` yang disubmit tidak kosong. Task lain tidak bergantung pada perubahan ini.

- [x] **Step 1: Baca method `validateSiswa()` saat ini**

Baca `app/Http/Controllers/Admin/SiswaController.php` baris 279-310 untuk memastikan baris yang akan diedit masih persis sama sebelum melangkah (nomor baris bisa bergeser sedikit kalau ada perubahan lain sebelumnya).

- [x] **Step 2: Tulis test yang gagal**

Buka `tests/Feature/Admin/SiswaCrudTest.php`, cek pola `actingAs`/factory yang sudah dipakai test lain di file itu untuk membuat `$manager` (user dengan permission `siswa.edit`) dan `Lembaga`. Tambahkan test baru di akhir file:

```php
it('rejects setting kelas_id on update for a siswa with non-aktif status', function () {
    $yayasan = \App\Models\Yayasan::factory()->create();
    $lembaga = \App\Models\Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->givePermissionTo('siswa.edit');
    $tahunAjaran = \App\Models\TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswaKeluar = \App\Models\Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => null,
        'status' => \App\Enums\StatusSiswa::Keluar->value,
        'nis' => '9990001',
    ]);

    $response = $this->actingAs($manager)->put(route('admin.siswa.update', $siswaKeluar), [
        'kelas_id' => $kelas->id,
        'nis' => $siswaKeluar->nis,
        'nisn' => $siswaKeluar->nisn,
        'nama_lengkap' => $siswaKeluar->nama_lengkap,
        'jenis_kelamin' => 'L',
    ]);

    $response->assertSessionHasErrors('kelas_id');
    expect($siswaKeluar->fresh()->kelas_id)->toBeNull();
});
```

Sesuaikan cara `givePermissionTo`/pembuatan `$manager` persis dengan pola yang sudah ada di file test ini (cek 1-2 test lain di file yang sama untuk memastikan format yang benar dipakai proyek ini, mis. apakah permission perlu `Permission::firstOrCreate()` dulu).

- [x] **Step 3: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="rejects setting kelas_id on update for a siswa with non-aktif status"`
Expected: FAIL — `kelas_id` benar-benar ter-update (tidak ada error validasi), assertion `assertSessionHasErrors('kelas_id')` gagal.

- [x] **Step 4: Tambahkan guard di `validateSiswa()`**

Di `app/Http/Controllers/Admin/SiswaController.php`, tambahkan `use Illuminate\Validation\ValidationException;` ke daftar `use` di atas (setelah `use Illuminate\Support\Facades\Log;`). Lalu di method `validateSiswa()`, setelah blok `$data = $request->validate([...]);` selesai (baris kira-kira 302, sebelum `return $data;` atau blok berikutnya), tambahkan:

```php
if ($current && $current->status !== StatusSiswa::Aktif && ! empty($data['kelas_id'])) {
    throw ValidationException::withMessages([
        'kelas_id' => 'Tidak bisa menempatkan kelas untuk siswa berstatus non-aktif. Ubah status ke Aktif terlebih dahulu.',
    ]);
}
```

- [x] **Step 5: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter="rejects setting kelas_id on update for a siswa with non-aktif status"`
Expected: PASS

- [x] **Step 6: Regresi file test terkait**

Run: `php artisan test --filter=SiswaCrudTest`
Expected: semua test di file itu PASS (tidak ada regresi ke test edit siswa aktif biasa).

- [x] **Step 7: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/SiswaController.php tests/Feature/Admin/SiswaCrudTest.php
git commit -m "fix(akademik): tolak set kelas_id lewat form edit untuk siswa berstatus non-aktif"
```

---

## Task 2: Migration `kelas_terakhir_id` + Backfill + CHECK Constraint

**Files:**
- Create: `database/migrations/2026_09_03_000001_add_kelas_terakhir_id_to_siswa_table.php`
- Modify: `tests/Unit/Services/SesiPembelajaranGeneratorTest.php:187` (interim fix — lihat Step 6, akan disempurnakan di Task 4)
- Test: `tests/Feature/Akademik/KelasTerakhirIdMigrationTest.php`

**Interfaces:**
- Produksi: kolom `siswa.kelas_terakhir_id` (`bigint unsigned`, nullable, FK ke `kelas.id` `ON DELETE SET NULL`); CHECK constraint `chk_siswa_kelas_id_null_saat_nonaktif` aktif di tabel `siswa`. Task 3 (Action) dan Task 5 (accessor) bergantung pada kolom ini ada.
- **PENTING**: begitu migration ini jalan, RefreshDatabase di SEMUA test suite akan menerapkan CHECK constraint baru. Test manapun yang membuat `Siswa` dengan `kelas_id` terisi DAN status non-`aktif` dalam 1 factory call akan gagal dengan DB exception. Step 6 di task ini menambal SATU test yang diketahui melanggar (`SesiPembelajaranGeneratorTest.php:187`) dengan perbaikan interim minimal (hapus `kelas_id` dari factory call) — perbaikan versi lengkap (pakai `UpdateStatusSiswaAction`) menyusul di Task 4 setelah Action itu ada.

- [x] **Step 1: Tulis test migration yang gagal (backfill)**

Buat file baru `tests/Feature/Akademik/KelasTerakhirIdMigrationTest.php`:

```php
<?php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Support\Facades\DB;

it('membackfill kelas_terakhir_id dan mengosongkan kelas_id untuk siswa non-aktif yang sudah ada sebelum migration', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    // Migration backfill sudah jalan (RefreshDatabase menjalankan SEMUA migration termasuk
    // migration baru ini). Untuk menguji efek backfill-nya sendiri, kita simulasikan kondisi
    // "data lama sebelum migration" dengan menonaktifkan constraint sementara, insert row
    // yang melanggar invariant, lalu jalankan ULANG isi backfill UPDATE-nya secara langsung.
    DB::statement('ALTER TABLE siswa DROP CONSTRAINT chk_siswa_kelas_id_null_saat_nonaktif');
    $siswaLama = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'status' => 'keluar',
    ]);
    DB::statement('UPDATE siswa SET kelas_terakhir_id = kelas_id, kelas_id = NULL WHERE status != \'aktif\' AND kelas_id IS NOT NULL');
    DB::statement('ALTER TABLE siswa ADD CONSTRAINT chk_siswa_kelas_id_null_saat_nonaktif CHECK (status = \'aktif\' OR kelas_id IS NULL)');

    $siswaLama->refresh();
    expect($siswaLama->kelas_id)->toBeNull();
    expect($siswaLama->kelas_terakhir_id)->toBe($kelas->id);
});

it('menolak insert siswa non-aktif dengan kelas_id terisi lewat query mentah (CHECK constraint aktif)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    expect(fn () => Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'status' => 'keluar',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=KelasTerakhirIdMigrationTest`
Expected: FAIL — kolom `kelas_terakhir_id` belum ada (`Column not found` error), dan constraint belum ada sehingga test kedua tidak melempar exception apapun.

- [x] **Step 3: Buat migration**

```bash
php artisan make:migration add_kelas_terakhir_id_to_siswa_table --no-interaction
```

Ganti isi file migration yang dihasilkan (rename filenya kalau timestamp tidak sama dengan `2026_09_03_000001_add_kelas_terakhir_id_to_siswa_table.php`, atau biarkan timestamp asli hasil generate — yang penting jalan SETELAH migration terakhir yang ada) jadi:

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
        Schema::table('siswa', function (Blueprint $table) {
            $table->foreignId('kelas_terakhir_id')->nullable()->after('kelas_id')
                ->constrained('kelas')->nullOnDelete();
        });

        DB::statement("
            UPDATE siswa
            SET kelas_terakhir_id = kelas_id, kelas_id = NULL
            WHERE status != 'aktif' AND kelas_id IS NOT NULL
        ");

        DB::statement('
            ALTER TABLE siswa ADD CONSTRAINT chk_siswa_kelas_id_null_saat_nonaktif
                CHECK (status = \'aktif\' OR kelas_id IS NULL)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE siswa DROP CONSTRAINT chk_siswa_kelas_id_null_saat_nonaktif');

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kelas_terakhir_id');
        });
    }
};
```

- [x] **Step 4: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=KelasTerakhirIdMigrationTest`
Expected: PASS (2 test)

- [x] **Step 5: Jalankan full regresi cepat untuk menemukan test yang pecah**

Run: `php artisan test --compact 2>&1 | grep -B2 "FAILED\|Error"`

Expected: kemungkinan besar HANYA `tests/Unit/Services/SesiPembelajaranGeneratorTest.php` (test `'excludes non-aktif siswa from auto-generated presensi'`) yang gagal, dengan error `QueryException` soal CHECK constraint. Kalau ada test LAIN yang gagal karena alasan yang sama (siswa non-aktif dibuat dengan `kelas_id` terisi dalam 1 factory call), catat nama file dan baris persis, lalu tambal dengan pola yang sama seperti Step 6 di bawah (hapus `kelas_id` dari factory call yang melanggar) — jangan lanjut ke Task 3 sebelum SEMUA kegagalan akibat constraint ini ditambal.

- [x] **Step 6: Tambal interim `SesiPembelajaranGeneratorTest.php:187`**

Baca `tests/Unit/Services/SesiPembelajaranGeneratorTest.php` baris 185-193. Ganti baris 187 dari:

```php
$siswaLulus = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'lulus']);
```

Menjadi (interim — TIDAK mengubah assertion, cuma menghapus kombinasi yang sekarang melanggar constraint; versi final memakai `UpdateStatusSiswaAction` disempurnakan di Task 4):

```php
$siswaLulus = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => null, 'status' => 'lulus']);
```

- [x] **Step 7: Jalankan test itu + full suite lagi**

Run: `php artisan test --filter=SesiPembelajaranGeneratorTest`
Expected: PASS

Run: `php artisan test --compact`
Expected: SEMUA test PASS, 0 failures — kalau masih ada yang gagal karena constraint, ulangi Step 5-6 untuk test itu sebelum lanjut.

- [x] **Step 8: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/*_add_kelas_terakhir_id_to_siswa_table.php tests/Feature/Akademik/KelasTerakhirIdMigrationTest.php tests/Unit/Services/SesiPembelajaranGeneratorTest.php
git commit -m "feat(akademik): tambah kolom kelas_terakhir_id + backfill + CHECK constraint kelas_id siswa non-aktif"
```

---

## Task 3: `UpdateStatusSiswaAction`

**Files:**
- Create: `app/Domains/Akademik/Actions/Siswa/UpdateStatusSiswaAction.php`
- Modify: `app/Http/Controllers/Admin/SiswaController.php` (method `updateStatus()`, sekitar baris 186-203)
- Test: `tests/Feature/Akademik/UpdateStatusSiswaActionTest.php`

**Interfaces:**
- Konsumsi: `App\Models\Siswa`, `App\Enums\StatusSiswa` (sudah ada).
- Produksi: `App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction::execute(Siswa $siswa, StatusSiswa $statusBaru): Siswa`. Task 4 (fix test) dan Task 9 (test navigasi) memanggil Action ini persis dengan signature ini.

- [x] **Step 1: Tulis test yang gagal (siklus dasar + reversal)**

Buat file baru `tests/Feature/Akademik/UpdateStatusSiswaActionTest.php`:

```php
<?php

use App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction;
use App\Enums\StatusSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function siapkanSiswaAktifDiKelas(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => StatusSiswa::Aktif->value]);

    return compact('yayasan', 'lembaga', 'tahunAjaran', 'kelas', 'siswa');
}

it('snapshot kelas_id ke kelas_terakhir_id dan null-kan kelas_id saat transisi Aktif ke Keluar', function () {
    ['siswa' => $siswa, 'kelas' => $kelas] = siapkanSiswaAktifDiKelas();

    (new UpdateStatusSiswaAction)->execute($siswa, StatusSiswa::Keluar);

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Keluar);
    expect($siswa->kelas_id)->toBeNull();
    expect($siswa->kelas_terakhir_id)->toBe($kelas->id);
});

it('memulihkan kelas_id dari kelas_terakhir_id dan mengosongkan kelas_terakhir_id saat kembali ke Aktif', function () {
    ['siswa' => $siswa, 'kelas' => $kelas] = siapkanSiswaAktifDiKelas();
    $action = new UpdateStatusSiswaAction;
    $action->execute($siswa, StatusSiswa::Keluar);

    $action->execute($siswa->fresh(), StatusSiswa::Aktif);

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Aktif);
    expect($siswa->kelas_id)->toBe($kelas->id);
    expect($siswa->kelas_terakhir_id)->toBeNull();
});

it('siklus ganda Aktif->Keluar->Aktif->Keluar lagi mengambil snapshot kelas yang benar di siklus kedua', function () {
    ['siswa' => $siswa, 'kelas' => $kelasPertama, 'lembaga' => $lembaga, 'tahunAjaran' => $tahunAjaran] = siapkanSiswaAktifDiKelas();
    $action = new UpdateStatusSiswaAction;

    // Siklus 1: keluar dari kelas pertama, lalu aktif lagi (otomatis kembali ke kelas pertama).
    $action->execute($siswa, StatusSiswa::Keluar);
    $action->execute($siswa->fresh(), StatusSiswa::Aktif);

    // Siswa pindah ke kelas KEDUA saat aktif kembali (skenario realistis: admin assign ulang).
    $kelasKedua = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa->fresh()->update(['kelas_id' => $kelasKedua->id]);

    // Siklus 2: keluar lagi -- snapshot HARUS kelas kedua, bukan sisa data basi dari siklus pertama.
    $action->execute($siswa->fresh(), StatusSiswa::Keluar);

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Keluar);
    expect($siswa->kelas_id)->toBeNull();
    expect($siswa->kelas_terakhir_id)->toBe($kelasKedua->id);
});

it('idempotent: memanggil dengan status target sama dengan status sekarang tidak mengubah kelas_id/kelas_terakhir_id', function () {
    ['siswa' => $siswa, 'kelas' => $kelas] = siapkanSiswaAktifDiKelas();
    $action = new UpdateStatusSiswaAction;
    $action->execute($siswa, StatusSiswa::Keluar);
    $siswaSetelahKeluar = $siswa->fresh();

    $action->execute($siswaSetelahKeluar, StatusSiswa::Keluar);

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Keluar);
    expect($siswa->kelas_id)->toBeNull();
    expect($siswa->kelas_terakhir_id)->toBe($kelas->id);
});

it('transisi Aktif langsung memanggil execute dengan status Aktif tanpa perubahan sebelumnya juga idempotent', function () {
    ['siswa' => $siswa, 'kelas' => $kelas] = siapkanSiswaAktifDiKelas();

    (new UpdateStatusSiswaAction)->execute($siswa, StatusSiswa::Aktif);

    $siswa->refresh();
    expect($siswa->status)->toBe(StatusSiswa::Aktif);
    expect($siswa->kelas_id)->toBe($kelas->id);
    expect($siswa->kelas_terakhir_id)->toBeNull();
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=UpdateStatusSiswaActionTest`
Expected: FAIL — `Class "App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction" not found`

- [x] **Step 3: Buat Action**

Buat direktori `app/Domains/Akademik/Actions/Siswa/` kalau belum ada, lalu buat file `app/Domains/Akademik/Actions/Siswa/UpdateStatusSiswaAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Siswa;

use App\Enums\StatusSiswa;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;

final class UpdateStatusSiswaAction
{
    public function execute(Siswa $siswa, StatusSiswa $statusBaru): Siswa
    {
        return DB::transaction(function () use ($siswa, $statusBaru) {
            if ($siswa->status === $statusBaru) {
                return $siswa;
            }

            if ($statusBaru === StatusSiswa::Aktif) {
                $siswa->kelas_id = $siswa->kelas_terakhir_id;
                $siswa->kelas_terakhir_id = null;
            } elseif ($siswa->status === StatusSiswa::Aktif) {
                $siswa->kelas_terakhir_id = $siswa->kelas_id;
                $siswa->kelas_id = null;
            }

            $siswa->status = $statusBaru;
            $siswa->save();

            if ($siswa->user_id) {
                $siswa->user()->update(['is_active' => $statusBaru === StatusSiswa::Aktif]);
            }

            return $siswa;
        });
    }
}
```

- [x] **Step 4: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=UpdateStatusSiswaActionTest`
Expected: PASS (5 test)

- [x] **Step 5: Wire ke `SiswaController::updateStatus()`**

Di `app/Http/Controllers/Admin/SiswaController.php`, tambahkan import `use App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction;` ke daftar `use` di atas. Ganti method `updateStatus()` (baris 186-203) dari:

```php
    public function updateStatus(Request $request, Siswa $siswa): RedirectResponse
    {
        $this->authorize('siswa.edit');

        $data = $request->validate([
            'status' => ['required', 'in:aktif,lulus,pindah,keluar'],
        ]);

        DB::transaction(function () use ($data, $siswa) {
            $siswa->update(['status' => $data['status']]);

            if ($siswa->user_id) {
                $siswa->user()->update(['is_active' => $data['status'] === StatusSiswa::Aktif->value]);
            }
        });

        return redirect()->route('admin.siswa.index')->with('status', 'Status siswa berhasil diperbarui.');
    }
```

Menjadi:

```php
    public function updateStatus(Request $request, Siswa $siswa, UpdateStatusSiswaAction $action): RedirectResponse
    {
        $this->authorize('siswa.edit');

        $data = $request->validate([
            'status' => ['required', 'in:aktif,lulus,pindah,keluar'],
        ]);

        $action->execute($siswa, StatusSiswa::from($data['status']));

        return redirect()->route('admin.siswa.index')->with('status', 'Status siswa berhasil diperbarui.');
    }
```

- [x] **Step 6: Regresi test controller siswa**

Run: `php artisan test --filter=SiswaCrudTest`
Expected: PASS, tidak ada regresi (termasuk test dari Task 1 dan test existing `it('...update-status...')` di baris 215 file itu kalau ada).

- [x] **Step 7: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Actions/Siswa/UpdateStatusSiswaAction.php app/Http/Controllers/Admin/SiswaController.php tests/Feature/Akademik/UpdateStatusSiswaActionTest.php
git commit -m "feat(akademik): tambah UpdateStatusSiswaAction, pindahkan logic transisi status dari controller"
```

---

## Task 4: Sempurnakan Fix `SesiPembelajaranGeneratorTest.php` Pakai `UpdateStatusSiswaAction`

**Files:**
- Modify: `tests/Unit/Services/SesiPembelajaranGeneratorTest.php:185-193`

**Interfaces:**
- Konsumsi: `App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction::execute()` dari Task 3.

- [x] **Step 1: Baca kondisi test saat ini (hasil interim Task 2)**

Baca `tests/Unit/Services/SesiPembelajaranGeneratorTest.php` baris 185-193 — pastikan masih versi interim (`'kelas_id' => null`) dari Task 2 Step 6.

- [x] **Step 2: Ganti jadi versi final memakai Action**

Tambahkan import di atas file (kalau belum ada blok `use` untuk file Pest ini, tambahkan di baris paling atas setelah `<?php`):

```php
use App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction;
use App\Enums\StatusSiswa;
```

Ganti isi test (baris 185-193) dari:

```php
it('excludes non-aktif siswa from auto-generated presensi', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();
    $siswaLulus = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => null, 'status' => 'lulus']);

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($hasil->first()->presensi()->count())->toBe(3); // only the 3 aktif siswa from siapkanKelasDenganJadwal()
    expect($hasil->first()->presensi()->where('siswa_id', $siswaLulus->id)->exists())->toBeFalse();
});
```

Menjadi:

```php
it('excludes non-aktif siswa from auto-generated presensi', function () {
    ['kelas' => $kelas, 'semester' => $semester] = siapkanKelasDenganJadwal();
    $siswaLulus = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);
    app(UpdateStatusSiswaAction::class)->execute($siswaLulus, StatusSiswa::Lulus);

    $hasil = (new SesiPembelajaranGenerator)->generateUntukTanggal($kelas, Carbon::parse('2026-08-19'), $semester->id);

    expect($hasil->first()->presensi()->count())->toBe(3); // only the 3 aktif siswa from siapkanKelasDenganJadwal()
    expect($hasil->first()->presensi()->where('siswa_id', $siswaLulus->id)->exists())->toBeFalse();
});
```

- [x] **Step 3: Jalankan test, pastikan lolos**

Run: `php artisan test --filter=SesiPembelajaranGeneratorTest`
Expected: PASS

- [x] **Step 4: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Unit/Services/SesiPembelajaranGeneratorTest.php
git commit -m "test(akademik): sempurnakan test presensi non-aktif pakai UpdateStatusSiswaAction (bukan factory langsung)"
```

---

## Task 5: Accessor `kelas_efektif` di Model `Siswa` + Terapkan ke `RaporPdfDataBuilder`

**Files:**
- Modify: `app/Models/Siswa.php`
- Modify: `app/Domains/Akademik/Services/RaporPdfDataBuilder.php:45`
- Test: `tests/Unit/Models/SiswaKelasEfektifTest.php`

**Interfaces:**
- Produksi: `Siswa::kelasTerakhir(): BelongsTo` (relasi baru), `$siswa->kelas_efektif` (accessor, magic property, return `?Kelas`). Task 6 (blade views) memakai accessor dan relasi `kelasTerakhir` yang sama persis ini.

- [x] **Step 1: Tulis test yang gagal**

Buat file baru `tests/Unit/Models/SiswaKelasEfektifTest.php`:

```php
<?php

use App\Enums\StatusSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

it('mengembalikan kelas() untuk siswa aktif', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => StatusSiswa::Aktif->value]);

    expect($siswa->kelas_efektif?->id)->toBe($kelas->id);
});

it('mengembalikan kelasTerakhir() untuk siswa non-aktif dengan kelas_terakhir_id terisi', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => null,
        'kelas_terakhir_id' => $kelas->id,
        'status' => StatusSiswa::Keluar->value,
    ]);

    expect($siswa->kelas_efektif?->id)->toBe($kelas->id);
});

it('mengembalikan null kalau kelas_id maupun kelas_terakhir_id kosong', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => null, 'status' => StatusSiswa::Aktif->value]);

    expect($siswa->kelas_efektif)->toBeNull();
});
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=SiswaKelasEfektifTest`
Expected: FAIL — `kelas_efektif` accessor belum ada, dan factory belum mendukung field `kelas_terakhir_id` (error "Unknown column" atau accessor return null/error tergantung urutan).

- [x] **Step 3: Tambah relasi dan accessor di model**

Baca `app/Models/Siswa.php` baris 85-90 untuk menemukan method `kelas(): BelongsTo` yang sudah ada. Tepat setelah method itu, tambahkan:

```php
    public function kelasTerakhir(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_terakhir_id');
    }

    public function getKelasEfektifAttribute(): ?Kelas
    {
        return $this->kelas ?? $this->kelasTerakhir;
    }
```

- [x] **Step 4: Tambah `kelas_terakhir_id` ke `$fillable`**

Di `app/Models/Siswa.php` baris 33-36, tambahkan `'kelas_terakhir_id'` ke array `$fillable` (setelah `'kelas_id'`):

```php
    protected $fillable = [
        'person_id', 'lembaga_id', 'kelas_id', 'kelas_terakhir_id', 'calon_murid_id', 'pendaftaran_asal_id',
        'sumber_data', 'nis', 'nisn', 'status',
    ];
```

- [x] **Step 5: Tambah `kelas_terakhir_id` ke `SiswaFactory`**

Di `database/factories/SiswaFactory.php`, tambahkan `'kelas_terakhir_id' => null` ke `definition()` (setelah baris `'kelas_id' => null,`):

```php
            'kelas_id' => null,
            'kelas_terakhir_id' => null,
```

- [x] **Step 6: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=SiswaKelasEfektifTest`
Expected: PASS (3 test)

- [x] **Step 7: Terapkan ke `RaporPdfDataBuilder`**

Di `app/Domains/Akademik/Services/RaporPdfDataBuilder.php` baris 45, ganti:

```php
        $kelas = $siswa->kelas;
```

Menjadi:

```php
        $kelas = $siswa->kelas_efektif;
        abort_if($kelas === null, 404);
```

- [x] **Step 8: Tulis test regresi untuk `RaporPdfDataBuilder`**

Cari test file existing untuk `RaporPdfDataBuilder` atau endpoint `cetak` (cek `tests/Feature/Guru/RaporControllerTest.php`, sudah ada test `'streams a pdf for a siswa the guru is wali kelas of'`). Tambahkan test baru di file yang sama:

```php
it('streams a pdf for a siswa who has since left the kelas (kelas_id null, kelas_terakhir_id terisi)', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    app(\App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction::class)->execute($siswa, \App\Enums\StatusSiswa::Keluar);

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.cetak', ['siswa' => $siswa->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});
```

**Catatan**: test ini kemungkinan akan tabrakan dengan guard otorisasi `abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403)` di `RaporController::cetak()` (baris 230) — guard itu memakai `$siswa->kelas` (bukan `kelas_efektif`), jadi setelah siswa jadi Keluar, `$siswa->kelas` null dan guard ini akan 403 SEBELUM sempat mencapai `RaporPdfDataBuilder`. Ini DI LUAR SCOPE task ini untuk diperbaiki (guard otorisasi cetak rapor untuk mantan siswa adalah keputusan bisnis terpisah: siapa yang berwenang cetak rapor siswa yang sudah keluar). **Sesuaikan test ini** jadi memanggil `RaporPdfDataBuilder::build()` LANGSUNG (bukan lewat endpoint HTTP penuh) untuk menguji accessor-nya saja, bukan alur otorisasi controller:

```php
it('builds rapor data for a siswa who has since left the kelas (kelas_id null, kelas_terakhir_id terisi)', function () {
    ['kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    app(\App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction::class)->execute($siswa, \App\Enums\StatusSiswa::Keluar);

    $data = app(\App\Domains\Akademik\Services\RaporPdfDataBuilder::class)->build($siswa->fresh(), $semester);

    expect($data)->toBeArray();
});
```

- [x] **Step 9: Jalankan test, pastikan lolos**

Run: `php artisan test --filter=RaporControllerTest`
Expected: PASS, termasuk test baru.

- [x] **Step 10: Regresi luas model Siswa dan Rapor**

Run: `php artisan test --filter=Siswa`
Run: `php artisan test --filter=Rapor`
Expected: semua PASS.

- [x] **Step 11: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Siswa.php app/Domains/Akademik/Services/RaporPdfDataBuilder.php database/factories/SiswaFactory.php tests/Unit/Models/SiswaKelasEfektifTest.php tests/Feature/Guru/RaporControllerTest.php
git commit -m "feat(akademik): accessor kelas_efektif di model Siswa, dipakai RaporPdfDataBuilder"
```

---

## Task 6: Terapkan `kelas_efektif` ke `_daftar.blade.php` dan `profil.blade.php` + Label "(kelas terakhir)"

**Files:**
- Modify: `resources/views/admin/siswa/_daftar.blade.php:91-97`
- Modify: `resources/views/admin/siswa/tabs/profil.blade.php:78-81`
- Modify: `app/Http/Controllers/Admin/SiswaController.php:33` (eager load)
- Test: `tests/Feature/Admin/SiswaCrudTest.php`

**Interfaces:**
- Konsumsi: `$siswa->kelas_efektif`, `$siswa->kelasTerakhir` dari Task 5.

- [x] **Step 1: Tulis test yang gagal**

Tambahkan test baru di `tests/Feature/Admin/SiswaCrudTest.php`:

```php
it('shows the last known kelas with a "(kelas terakhir)" label for a non-aktif siswa in the daftar list', function () {
    $yayasan = \App\Models\Yayasan::factory()->create();
    $lembaga = \App\Models\Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->givePermissionTo('siswa.view');
    $tahunAjaran = \App\Models\TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas 9C']);
    $siswa = \App\Models\Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => 'aktif', 'nama_lengkap' => 'Siswa Keluar Uji']);
    app(\App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction::class)->execute($siswa, \App\Enums\StatusSiswa::Keluar);

    $response = $this->actingAs($manager)->get(route('admin.siswa.index'));

    $response->assertOk();
    $response->assertSee('Kelas 9C');
    $response->assertSee('(kelas terakhir)');
});
```

Sesuaikan permission `siswa.view` dan pola pembuatan `$manager` dengan yang sudah dipakai test lain di file ini.

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="shows the last known kelas"`
Expected: FAIL — `_daftar.blade.php` masih menampilkan badge "Belum ditempatkan" untuk siswa ini (kelas_id sudah null), teks "Kelas 9C" dan "(kelas terakhir)" tidak muncul.

- [x] **Step 3: Update `_daftar.blade.php`**

Baca `resources/views/admin/siswa/_daftar.blade.php` baris 91-97. Ganti:

```blade
                    <td class="px-5 py-3.5 text-gray-600">
                        @if ($siswa->kelas)
                            {{ $siswa->kelas->nama }}
                        @else
                            <x-badge tone="amber">Belum ditempatkan</x-badge>
                        @endif
                    </td>
```

Menjadi:

```blade
                    <td class="px-5 py-3.5 text-gray-600">
                        @if ($siswa->kelas_efektif)
                            {{ $siswa->kelas_efektif->nama }}
                            @if (! $siswa->kelas && $siswa->kelasTerakhir)
                                <span class="text-xs text-gray-400">(kelas terakhir)</span>
                            @endif
                        @else
                            <x-badge tone="amber">Belum ditempatkan</x-badge>
                        @endif
                    </td>
```

- [x] **Step 4: Update eager loading di controller (cegah N+1)**

Di `app/Http/Controllers/Admin/SiswaController.php` baris 33, ganti:

```php
        $query = Siswa::with(['kelas', 'person'])
```

Menjadi:

```php
        $query = Siswa::with(['kelas', 'kelasTerakhir', 'person'])
```

- [x] **Step 5: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter="shows the last known kelas"`
Expected: PASS

- [x] **Step 6: Update `profil.blade.php` dan tulis test-nya**

Tambahkan test baru di `tests/Feature/Admin/SiswaCrudTest.php` (cek route `show`/`profil` yang benar dengan membaca `routes/admin.php` atau controller `show()` kalau ada — sesuaikan nama route persis):

```php
it('shows the last known kelas with a "(kelas terakhir)" label on the siswa profil tab', function () {
    $yayasan = \App\Models\Yayasan::factory()->create();
    $lembaga = \App\Models\Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->givePermissionTo('siswa.view');
    $tahunAjaran = \App\Models\TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas 9D']);
    $siswa = \App\Models\Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);
    app(\App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction::class)->execute($siswa, \App\Enums\StatusSiswa::Keluar);

    $response = $this->actingAs($manager)->get(route('admin.siswa.show', $siswa));

    $response->assertOk();
    $response->assertSee('Kelas 9D');
    $response->assertSee('(kelas terakhir)');
});
```

Kalau route `admin.siswa.show` tidak ada (cek dengan `php artisan route:list --name=siswa`), sesuaikan ke route yang benar dipakai untuk membuka halaman profil siswa (kemungkinan `admin.siswa.edit` yang merender tab profil sebagai bagian dari halaman edit — baca `SiswaController` untuk method mana yang me-load `tabs/profil.blade.php`).

Jalankan dulu untuk pastikan gagal: `php artisan test --filter="shows the last known kelas with a \"(kelas terakhir)\" label on the siswa profil tab"` → Expected: FAIL.

Lalu update `resources/views/admin/siswa/tabs/profil.blade.php` baris 78-81, ganti:

```blade
                        <div class="flex justify-between py-2.5">
                            <dt class="text-gray-500">Rombel / Kelas Aktif</dt>
                            <dd class="font-medium text-brand-600">{{ $siswa->kelas?->nama ?? 'Belum ada kelas' }}</dd>
                        </div>
```

Menjadi:

```blade
                        <div class="flex justify-between py-2.5">
                            <dt class="text-gray-500">Rombel / Kelas Aktif</dt>
                            <dd class="font-medium text-brand-600">
                                {{ $siswa->kelas_efektif?->nama ?? 'Belum ada kelas' }}
                                @if (! $siswa->kelas && $siswa->kelasTerakhir)
                                    <span class="text-xs text-gray-400">(kelas terakhir)</span>
                                @endif
                            </dd>
                        </div>
```

- [x] **Step 7: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=SiswaCrudTest`
Expected: PASS, semua test termasuk 2 test baru.

- [x] **Step 8: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/admin/siswa/_daftar.blade.php resources/views/admin/siswa/tabs/profil.blade.php app/Http/Controllers/Admin/SiswaController.php tests/Feature/Admin/SiswaCrudTest.php
git commit -m "feat(akademik): tampilkan kelas terakhir siswa non-aktif di daftar dan profil siswa"
```

---

## Task 7: Frontend Guard — Disable Field `kelas_id` di Form Edit Siswa Non-Aktif

**Files:**
- Modify: `resources/views/admin/siswa/_form.blade.php:71-81`
- Test: `tests/Feature/Admin/SiswaCrudTest.php`

**Interfaces:**
- Konsumsi: `App\Enums\StatusSiswa` (di Blade, dipanggil fully-qualified).

- [x] **Step 1: Tulis test yang gagal**

Tambahkan test baru di `tests/Feature/Admin/SiswaCrudTest.php`:

```php
it('disables the kelas_id select on the edit form for a non-aktif siswa', function () {
    $yayasan = \App\Models\Yayasan::factory()->create();
    $lembaga = \App\Models\Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->givePermissionTo('siswa.edit');
    $siswaKeluar = \App\Models\Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => null, 'status' => 'keluar']);

    $response = $this->actingAs($manager)->get(route('admin.siswa.edit', $siswaKeluar));

    $response->assertOk();
    $html = $response->getContent();
    $selectPos = strpos($html, 'name="kelas_id"');
    expect($selectPos)->not->toBeFalse();
    $selectTagEnd = strpos($html, '>', $selectPos);
    $selectOpenTag = substr($html, strrpos(substr($html, 0, $selectPos), '<select'), $selectTagEnd - strrpos(substr($html, 0, $selectPos), '<select') + 1);
    expect($selectOpenTag)->toContain('disabled');
    $response->assertSee('Siswa berstatus non-aktif');
});
```

Sesuaikan nama route `admin.siswa.edit` dan permission `siswa.edit` dengan yang sudah dipakai test lain di file ini.

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="disables the kelas_id select"`
Expected: FAIL — field `kelas_id` tidak punya atribut `disabled`, teks "Siswa berstatus non-aktif" tidak muncul.

- [x] **Step 3: Update `_form.blade.php`**

Baca `resources/views/admin/siswa/_form.blade.php` baris 71-81. Ganti:

```blade
            <div class="sm:col-span-12">
                <x-input-label value="Penempatan Kelas" />
                <x-select name="kelas_id" class="mt-1.5 block w-full transition duration-150" :error="$errors->has('kelas_id')">
                    <option value="">— Belum ditempatkan —</option>
                    @foreach ($kelasList as $kelas)
                        <option value="{{ $kelas->id }}" @selected($val('kelas_id') == $kelas->id)>{{ $kelas->nama }}</option>
                    @endforeach
                </x-select>
                <x-input-hint>(Opsional)</x-input-hint>
                <x-input-error :messages="$errors->get('kelas_id')" class="mt-1.5" />
            </div>
```

Menjadi:

```blade
            @php $siswaNonAktif = $siswa && $siswa->status !== \App\Enums\StatusSiswa::Aktif; @endphp
            <div class="sm:col-span-12">
                <x-input-label value="Penempatan Kelas" />
                <x-select name="kelas_id" :disabled="$siswaNonAktif" class="mt-1.5 block w-full transition duration-150" :error="$errors->has('kelas_id')">
                    <option value="">— Belum ditempatkan —</option>
                    @foreach ($kelasList as $kelas)
                        <option value="{{ $kelas->id }}" @selected($val('kelas_id') == $kelas->id)>{{ $kelas->nama }}</option>
                    @endforeach
                </x-select>
                @if ($siswaNonAktif)
                    <x-input-hint>Siswa berstatus non-aktif — ubah status ke Aktif dulu untuk menempatkan kelas.</x-input-hint>
                @else
                    <x-input-hint>(Opsional)</x-input-hint>
                @endif
                <x-input-error :messages="$errors->get('kelas_id')" class="mt-1.5" />
            </div>
```

- [x] **Step 4: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter="disables the kelas_id select"`
Expected: PASS

- [x] **Step 5: Regresi form create siswa (pastikan `$siswa` null tidak error)**

Run: `php artisan test --filter=SiswaCrudTest`
Expected: semua PASS — perhatikan khusus test yang meng-hit halaman `create` (di mana `$siswa` bernilai `null`, jadi `$siswaNonAktif` harus `false`, bukan error).

- [x] **Step 6: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/admin/siswa/_form.blade.php tests/Feature/Admin/SiswaCrudTest.php
git commit -m "feat(akademik): disable field kelas_id di form edit untuk siswa berstatus non-aktif"
```

---

## Task 8: Test Regresi Titik "Gratis" — `ProsesKenaikanKelasAction` (Representatif)

**Files:**
- Test: `tests/Feature/Admin/KenaikanKelasControllerTest.php`

**Interfaces:**
- Konsumsi: `App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction` dari Task 3.

- [x] **Step 1: Tulis test**

Tambahkan test baru di `tests/Feature/Admin/KenaikanKelasControllerTest.php` (mengikuti pola helper `actingAsKenaikanKelasManager` yang sudah ada di file ini):

```php
it('does not carry a siswa Keluar along when promoting the rest of the kelas (kelas_id already null via UpdateStatusSiswaAction)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => '6A']);
    $siswaAktif = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $siswaKeluar = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    app(\App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction::class)->execute($siswaKeluar, StatusSiswa::Keluar);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id],
        ],
    ])->assertRedirect(route('admin.kelas.index'));

    expect($siswaAktif->fresh()->kelas_id)->toBe($kelasBaru->id);
    expect($siswaKeluar->fresh()->kelas_id)->toBeNull(); // tidak ikut naik, dan tetap null (bukan ke kelasBaru)
    expect($siswaKeluar->fresh()->kelas_terakhir_id)->toBe($kelasLama->id); // snapshot tetap ke kelas lama, tidak berubah
});

// Catatan: RaporCalculationService.php:27 dan DashboardStatsService.php:138 punya pola query
// IDENTIK (Siswa::where('kelas_id', $kelasId), tanpa filter status) -- setelah kelas_id
// dijamin null untuk siswa non-aktif oleh CHECK constraint (lihat migration
// 2026_09_03_000001_add_kelas_terakhir_id_to_siswa_table), kedua titik itu otomatis benar
// dengan cara yang SAMA PERSIS seperti dibuktikan test di atas. Sengaja TIDAK diduplikasi
// per file -- keputusan sadar, bukan celah yang terlewat (lihat spec
// .agents/specs/2026-09-03-siklus-hidup-kelas-id-siswa.md §10 test #11).
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="does not carry a siswa Keluar along"`
Expected: FAIL kalau dijalankan sebelum Task 2-3 selesai (kolom/Action belum ada) — tapi karena task ini dieksekusi TERAKHIR dalam plan, seharusnya justru langsung PASS begitu ditulis (semua dependency sudah ada). Kalau langsung PASS di percobaan pertama, itu MEMBUKTIKAN klaim "gratis" di spec — bukan tanda test salah. Jalankan tetap untuk konfirmasi, dan verifikasi manual dengan membaca assertion bahwa test ini benar-benar menguji hal yang benar (bukan tautologi).

- [x] **Step 3: Jalankan test, pastikan lolos**

Run: `php artisan test --filter=KenaikanKelasControllerTest`
Expected: semua PASS.

- [x] **Step 4: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/Admin/KenaikanKelasControllerTest.php
git commit -m "test(akademik): buktikan siswa Keluar tidak ikut Kenaikan Kelas, representatif untuk titik query serupa"
```

---

## Task 9: Test Regresi `Guru\RaporController` — Listing dan Navigasi Next/Previous

**Files:**
- Test: `tests/Feature/Guru/RaporControllerTest.php`

**Interfaces:**
- Konsumsi: `App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction` dari Task 3.

- [x] **Step 1: Tulis test listing (index) yang gagal-jika-salah**

Tambahkan test baru di `tests/Feature/Guru/RaporControllerTest.php`:

```php
it('excludes a siswa Keluar from the wali kelas rapor catatan listing', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'siswa' => $siswaAktif, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    $siswaKeluar = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'aktif', 'nama_lengkap' => 'Zainal Keluar']);
    app(\App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction::class)->execute($siswaKeluar, \App\Enums\StatusSiswa::Keluar);

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertDontSee('Zainal Keluar');
    $response->assertViewHas('siswaList', function ($list) use ($siswaAktif, $siswaKeluar) {
        return $list->contains('id', $siswaAktif->id) && ! $list->contains('id', $siswaKeluar->id);
    });
});
```

- [x] **Step 2: Jalankan test**

Run: `php artisan test --filter="excludes a siswa Keluar from the wali kelas rapor catatan listing"`
Expected: PASS (sudah "gratis" sejak Task 2, ini murni test pembuktian).

- [x] **Step 3: Tulis test navigasi next/previous**

Tambahkan test baru di file yang sama, mendesain urutan nama supaya siswa Keluar SEHARUSNYA berada di TENGAH kalau tidak dikecualikan (membuktikan tidak ada pergeseran index):

```php
it('skips a siswa Keluar entirely when computing siswaSebelumnya/siswaBerikutnya, without shifting the index of the remaining aktif siswa', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'semester' => $semester] = siapkanWaliKelasUntukRapor();
    // siapkanWaliKelasUntukRapor() sudah membuat 1 siswa "Ahmad Fauzi" -- tambahkan 2 lagi
    // supaya urutan alfabetis: Ahmad Fauzi, Budi Keluar (akan dikeluarkan), Citra Wulandari.
    $budiKeluar = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'aktif', 'nama_lengkap' => 'Budi Keluar']);
    app(\App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction::class)->execute($budiKeluar, \App\Enums\StatusSiswa::Keluar);
    $citra = Siswa::factory()->create(['lembaga_id' => $kelas->lembaga_id, 'kelas_id' => $kelas->id, 'status' => 'aktif', 'nama_lengkap' => 'Citra Wulandari']);
    $ahmad = Siswa::where('kelas_id', $kelas->id)->where('nama_lengkap', 'like', '%Ahmad%')->first();

    $response = $this->actingAs($guruUser)->get(route('guru.rapor.catatan.edit', ['siswa' => $ahmad->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('siswaSebelumnya', null);
    $response->assertViewHas('siswaBerikutnya', function ($siswa) use ($citra) {
        return $siswa !== null && $siswa->id === $citra->id;
    });
});
```

- [x] **Step 4: Jalankan test**

Run: `php artisan test --filter="skips a siswa Keluar entirely"`
Expected: PASS. Kalau FAIL, periksa apakah `$ahmad` berhasil ditemukan (factory `siapkanWaliKelasUntukRapor()` mungkin memberi nama berbeda dari "Ahmad Fauzi" — baca definisinya di file test yang sama untuk memastikan nama persis, sesuaikan query `where('nama_lengkap', 'like', ...)` kalau perlu).

- [x] **Step 5: Regresi penuh file test**

Run: `php artisan test --filter=RaporControllerTest`
Expected: semua PASS.

- [x] **Step 6: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/Guru/RaporControllerTest.php
git commit -m "test(akademik): buktikan siswa Keluar dikecualikan dari listing dan navigasi next/previous rapor wali kelas"
```

---

## Task 10: Full Test Suite Final

**Files:** Tidak ada file diubah — verifikasi akhir.

- [x] **Step 1: Pastikan tidak ada proses test lain berjalan**

Run: `ps aux | grep artisan | grep -v grep`
Expected: kosong (tidak ada proses `php artisan test` lain).

- [x] **Step 2: Jalankan full suite sendirian**

Run: `php artisan test --compact`
Expected: SEMUA test PASS, 0 failures.

- [x] **Step 3: Pint final**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` atau auto-fix tanpa error — commit lagi kalau ada file yang diformat ulang.

---

## Self-Review (dilakukan penulis plan, bukan reviewer terpisah)

**1. Spec coverage** — setiap bagian `.agents/specs/2026-09-03-siklus-hidup-kelas-id-siswa.md` sudah dipetakan:
- §3.1 (guard validasi) → Task 1.
- §3.2 (fix test) → Task 2 Step 6 (interim) + Task 4 (final).
- §4 (migration) → Task 2.
- §5 (`UpdateStatusSiswaAction`) → Task 3.
- §6 (accessor + `RaporPdfDataBuilder`) → Task 5.
- §7 (frontend: `_daftar`/`profil` + guard `_form`) → Task 6 (tampilan) + Task 7 (guard).
- §10 test #1-13 → dipetakan ke Task 1 (#1), Task 4 (#2), Task 3 (#3-6), Task 2 (#7-8), Task 5 (#9-10), Task 8 (#11), Task 9 (#12-13).
- §9 Non-Goals (`JenisTagihanSasaranMatcher`, Kelompok B, branch) — tidak ada task yang menyentuhnya, sesuai.

**2. Placeholder scan** — tidak ada "TBD"/"handle appropriately"; setiap step berisi kode lengkap.

**3. Type consistency** — `UpdateStatusSiswaAction::execute(Siswa $siswa, StatusSiswa $statusBaru): Siswa` dipakai identik (nama method, urutan parameter, tipe) di Task 3, 4, 5, 8, 9. `kelas_efektif` (accessor) dan `kelasTerakhir` (relasi) dipakai identik di Task 5, 6.

**Catatan urutan dependency penting** (bukan urutan literal di spec §2, tapi urutan REAL yang menghormati dependency kode): spec menyebut "fix test SesiPembelajaranGeneratorTest" sebagai bagian pra-migration, tapi versi FINAL fix itu butuh `UpdateStatusSiswaAction` yang baru ada di Task 3 — plan ini menyelesaikannya dengan 2 langkah (interim di Task 2 Step 6 yang membuat suite tetap hijau segera setelah migration, final version di Task 4 setelah Action ada), sesuai instruksi eksplisit yang diberikan saat brainstorming.
