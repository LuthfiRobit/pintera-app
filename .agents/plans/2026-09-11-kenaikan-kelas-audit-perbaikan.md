# Perbaikan Audit Menyeluruh Kenaikan Kelas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup 7 kelompok temuan audit (1 Critical, 2 High, 2 Medium, 1 gabungan High+Medium, 1 gabungan Low) di halaman Kenaikan Kelas — akar masalahnya adalah mass-update yang melewati seluruh mekanisme Eloquent model event (deaktivasi akun siswa lulus, generate tagihan otomatis, activity log), plus gap validasi dan UX di sekitar aksi bulk ireversibel ini.

**Architecture:** 8 task berurutan sebagian (dependency eksplisit dicatat per task) di domain Akademik (`app/Domains/Akademik/Actions/KenaikanKelas/*`, `app/Http/Controllers/Admin/KenaikanKelasController.php`, 1 view, 1 file JS baru). TIDAK menyentuh `app/Domains/Workflow/*`.

**Tech Stack:** Laravel 12 (PHP 8.3), Pest (test file modul ini pakai Pest function-style `it(...)`, BUKAN PHPUnit class-based), Blade, Alpine.js.

## Global Constraints

- Task 1 HARUS reuse `App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction` (SUDAH ADA di path itu persis) untuk tindakan `lulus` — JANGAN duplikasi logic `is_active`/`kelas_terakhir_id` secara terpisah di `ProsesKenaikanKelasAction`. Ini sudah dipertimbangkan dan ditolak saat spec ditulis (draft awal spec sempat begitu, dikoreksi sebelum final) — satu sumber kebenaran untuk transisi status siswa.
- Task 1 mengubah `ProsesKenaikanKelasAction::execute()` return type dari `array{jadwalGagal: array}` jadi `array{jadwalGagal: array, siswaNaik: int, siswaLulus: int, kelasDilewati: int}` — key `jadwalGagal` TETAP ADA dengan makna yang sama, cuma menambah 3 key baru. Test existing yang mengakses `$result['jadwalGagal']` TIDAK BOLEH rusak.
- Task 1 TIDAK menambah hard-block validasi tingkat/kurikulum di backend — `tests/Feature/Admin/KenaikanKelasControllerTest.php:286-307` (test existing, JANGAN diubah) secara eksplisit mendokumentasikan keputusan arsitektur "backend tidak pernah menolak kombinasi tingkat apapun, warning hanya di frontend". Menambah validasi keras di sini akan membuat test itu gagal DAN bertentangan dengan keputusan yang sudah didokumentasikan.
- Task 6 file JS baru HARUS diregistrasi di `resources/js/app.js` dengan pola `import { kenaikanKelasForm } from './kenaikan-kelas-form';` + `Alpine.data('kenaikanKelasForm', kenaikanKelasForm);`, mengikuti pola baris `Alpine.data(...)` lain yang sudah ada di file itu persis.
- Task 6 TIDAK BOLEH mengubah getter/state `x-data` per-baris yang SUDAH ADA di `<tr>` (`kurikulumAsal`, `kurikulumTujuan`, `tingkatAsal`, `tingkatTujuan`, `selisihIndexTingkat`, `onKelasTujuanChange`) — HANYA menambah binding/attribute baru di elemen `<tr>` yang sama.
- Task 7 bagian opsi "Lewati" selalu tersedia TIDAK BOLEH mengubah logic `@selected(...)` untuk opsi `naik`/`lulus` yang sudah ada — HANYA melonggarkan render opsi `lewati` dari kondisional (`siswa_count === 0`) jadi selalu dirender.
- 4 item SENGAJA TIDAK masuk scope plan ini, JANGAN dikerjakan: notifikasi WhatsApp/email ke siswa/orang tua, halaman pratinjau (preview) terpisah, hard-block validasi tingkat/kurikulum di backend, row lock (`lockForUpdate()`).
- JANGAN sentuh `app/Domains/Workflow/*` sama sekali.

---

## Task 1: Root Cause — Per-Siswa Update Menggantikan Mass-Update Query Builder

**Files:**
- Modify: `app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php`
- Test: `tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: `App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction::execute(Siswa $siswa, StatusSiswa $statusBaru): Siswa` (Action existing, TIDAK diubah — signature ini sudah final).
- Produces: `ProsesKenaikanKelasAction::execute(KenaikanKelasData $data): array{jadwalGagal: array<int,string>, siswaNaik: int, siswaLulus: int, kelasDilewati: int}` — Task 5 bergantung pada 3 key baru ini (`siswaNaik`, `siswaLulus`, `kelasDilewati`).

- [ ] **Step 1: Tulis test yang gagal — siswa Lulus lewat Kenaikan Kelas harus dinonaktifkan akunnya**

Tambahkan di `tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php`, di akhir file (setelah test terakhir `it('promotes siswa when tahun ajaran tujuan has a later tanggal_mulai than kelas lama', ...)`):

```php
it('deactivates the siswa user account when marking a kelas as lulus', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLulus = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $user = \App\Models\User::factory()->create(['is_active' => true]);
    $siswaLulus = Siswa::factory()->create(['kelas_id' => $kelasLulus->id, 'user_id' => $user->id]);

    buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLulus->id => ['tindakan' => 'lulus', 'kelas_baru_id' => null, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ]));

    expect($user->fresh()->is_active)->toBeFalse();
});

it('returns siswaNaik, siswaLulus, and kelasDilewati counts alongside jadwalGagal', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasNaik = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $kelasLulus = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasKosong = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    Siswa::factory()->count(2)->create(['kelas_id' => $kelasNaik->id]);
    Siswa::factory()->count(3)->create(['kelas_id' => $kelasLulus->id]);

    $result = buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasNaik->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
        $kelasLulus->id => ['tindakan' => 'lulus', 'kelas_baru_id' => null, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
        $kelasKosong->id => ['tindakan' => 'lewati', 'kelas_baru_id' => null, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ]));

    expect($result['siswaNaik'])->toBe(2)
        ->and($result['siswaLulus'])->toBe(3)
        ->and($result['kelasDilewati'])->toBe(1)
        ->and($result['jadwalGagal'])->toBe([]);
});

it('dispatches StudentUpdatedClass event when promoting siswa to a new kelas', function () {
    \Illuminate\Support\Facades\Event::fake([\App\Events\StudentUpdatedClass::class]);

    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelasLama->id]);

    buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ]));

    \Illuminate\Support\Facades\Event::assertDispatched(
        \App\Events\StudentUpdatedClass::class,
        fn ($event) => $event->siswa->id === $siswa->id
    );
});
```

**Catatan**: `buatKenaikanAction()` adalah helper existing di file test ini (baris 21-24) — SETELAH Step 3 di bawah, helper ini WAJIB diupdate karena constructor `ProsesKenaikanKelasAction` bertambah 1 parameter. Update helper-nya di Step 3, bukan di step ini.

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php --filter="deactivates the siswa user account"`
Expected: FAIL — `$user->fresh()->is_active` masih `true` (mass-update tidak pernah menyentuh kolom itu).

- [ ] **Step 3: Ganti mass-update jadi per-siswa update, reuse `UpdateStatusSiswaAction`**

Isi file `app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php` saat ini:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KenaikanKelas;

use App\Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction;
use App\Domains\Akademik\DataTransferObjects\JadwalPelajaranData;
use App\Domains\Akademik\DataTransferObjects\KenaikanKelasData;
use App\Enums\StatusSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProsesKenaikanKelasAction
{
    public function __construct(
        private readonly CreateJadwalPelajaranAction $createJadwalPelajaranAction
    ) {}

    /**
     * @return array{jadwalGagal: array<int, string>}
     *
     * @throws \DomainException kalau kelas tujuan berada di tahun ajaran yang sama dengan kelas asal
     */
    public function execute(KenaikanKelasData $data): array
    {
        $jadwalGagal = [];

        DB::transaction(function () use ($data, &$jadwalGagal) {
            foreach ($data->mapping as $kelasLamaId => $aksi) {
                if ($aksi['tindakan'] === 'lewati') {
                    continue;
                }

                $kelasLama = Kelas::findOrFail($kelasLamaId);

                if ($aksi['tindakan'] === 'lulus') {
                    Siswa::where('kelas_id', $kelasLama->id)->update([
                        'status' => StatusSiswa::Lulus->value,
                        'kelas_terakhir_id' => DB::raw('kelas_id'),
                        'kelas_id' => null,
                    ]);

                    continue;
                }

                $kelasBaru = Kelas::find($aksi['kelas_baru_id']);
                abort_if($kelasBaru === null || $kelasBaru->lembaga_id !== $kelasLama->lembaga_id, 404);

                if ($kelasBaru->tahun_ajaran_id === $kelasLama->tahun_ajaran_id) {
                    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" masih berada di tahun ajaran yang sama dengan kelas asal \"{$kelasLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                }

                $tahunAjaranLama = TahunAjaran::findOrFail($kelasLama->tahun_ajaran_id);
                $tahunAjaranBaru = TahunAjaran::findOrFail($kelasBaru->tahun_ajaran_id);

                if ($tahunAjaranBaru->tanggal_mulai < $tahunAjaranLama->tanggal_mulai) {
                    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" berada di tahun ajaran \"{$tahunAjaranBaru->nama}\" yang lebih lama dari tahun ajaran kelas asal \"{$tahunAjaranLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                }

                Siswa::where('kelas_id', $kelasLama->id)->update(['kelas_id' => $kelasBaru->id]);

                if (($aksi['salin_jadwal'] ?? false) && ! empty($aksi['semester_tujuan_id'])) {
                    $semesterTujuan = Semester::find($aksi['semester_tujuan_id']);
                    abort_if(
                        $semesterTujuan === null
                        || $semesterTujuan->lembaga_id !== $kelasLama->lembaga_id
                        || $semesterTujuan->tahun_ajaran_id !== $kelasBaru->tahun_ajaran_id,
                        404
                    );

                    $gagalDiBaris = $this->salinJadwal($kelasLama, $kelasBaru, $semesterTujuan->id);
                    $jadwalGagal = array_merge($jadwalGagal, $gagalDiBaris);
                }
            }
        });

        return ['jadwalGagal' => $jadwalGagal];
    }

    /**
     * @return array<int, string> deskripsi baris yang gagal disalin karena bentrok
     */
    private function salinJadwal(Kelas $kelasLama, Kelas $kelasBaru, int $semesterTujuanId): array
    {
        $jadwalLama = JadwalPelajaran::where('kelas_id', $kelasLama->id)->with('jamPelajaran')->get();
        $gagal = [];

        foreach ($jadwalLama as $jadwal) {
            $sudahAda = JadwalPelajaran::where('kelas_id', $kelasBaru->id)
                ->where('jam_pelajaran_id', $jadwal->jam_pelajaran_id)
                ->where('semester_id', $semesterTujuanId)
                ->exists();

            if ($sudahAda) {
                continue;
            }

            try {
                $this->createJadwalPelajaranAction->execute(JadwalPelajaranData::fromArray([
                    'lembaga_id' => $kelasBaru->lembaga_id,
                    'kelas_id' => $kelasBaru->id,
                    'guru_id' => $jadwal->guru_id,
                    'jam_pelajaran_id' => $jadwal->jam_pelajaran_id,
                    'semester_id' => $semesterTujuanId,
                    'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
                    'ruangan_id' => $jadwal->ruangan_id,
                ]));
            } catch (ValidationException $e) {
                $labelJam = $jadwal->jamPelajaran->label ?? "slot #{$jadwal->jam_pelajaran_id}";
                $gagal[] = "{$kelasLama->nama} → {$kelasBaru->nama} ({$labelJam}): ".$e->validator->errors()->first();
            }
        }

        return $gagal;
    }
}
```

Ganti TOTAL isinya jadi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\KenaikanKelas;

use App\Domains\Akademik\Actions\Jadwal\CreateJadwalPelajaranAction;
use App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction;
use App\Domains\Akademik\DataTransferObjects\JadwalPelajaranData;
use App\Domains\Akademik\DataTransferObjects\KenaikanKelasData;
use App\Enums\StatusSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProsesKenaikanKelasAction
{
    public function __construct(
        private readonly CreateJadwalPelajaranAction $createJadwalPelajaranAction,
        private readonly UpdateStatusSiswaAction $updateStatusSiswaAction,
    ) {}

    /**
     * @return array{jadwalGagal: array<int, string>, siswaNaik: int, siswaLulus: int, kelasDilewati: int}
     *
     * @throws \DomainException kalau kelas tujuan berada di tahun ajaran yang sama dengan kelas asal
     */
    public function execute(KenaikanKelasData $data): array
    {
        $jadwalGagal = [];
        $siswaNaik = 0;
        $siswaLulus = 0;
        $kelasDilewati = 0;

        DB::transaction(function () use ($data, &$jadwalGagal, &$siswaNaik, &$siswaLulus, &$kelasDilewati) {
            foreach ($data->mapping as $kelasLamaId => $aksi) {
                if ($aksi['tindakan'] === 'lewati') {
                    $kelasDilewati++;

                    continue;
                }

                $kelasLama = Kelas::findOrFail($kelasLamaId);

                if ($aksi['tindakan'] === 'lulus') {
                    $siswaList = Siswa::where('kelas_id', $kelasLama->id)->get();
                    foreach ($siswaList as $siswa) {
                        $this->updateStatusSiswaAction->execute($siswa, StatusSiswa::Lulus);
                        $siswaLulus++;
                    }

                    continue;
                }

                $kelasBaru = Kelas::find($aksi['kelas_baru_id']);
                abort_if($kelasBaru === null || $kelasBaru->lembaga_id !== $kelasLama->lembaga_id, 404);

                if ($kelasBaru->tahun_ajaran_id === $kelasLama->tahun_ajaran_id) {
                    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" masih berada di tahun ajaran yang sama dengan kelas asal \"{$kelasLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                }

                $tahunAjaranLama = TahunAjaran::findOrFail($kelasLama->tahun_ajaran_id);
                $tahunAjaranBaru = TahunAjaran::findOrFail($kelasBaru->tahun_ajaran_id);

                if ($tahunAjaranBaru->tanggal_mulai < $tahunAjaranLama->tanggal_mulai) {
                    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" berada di tahun ajaran \"{$tahunAjaranBaru->nama}\" yang lebih lama dari tahun ajaran kelas asal \"{$tahunAjaranLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                }

                $siswaList = Siswa::where('kelas_id', $kelasLama->id)->get();
                foreach ($siswaList as $siswa) {
                    $siswa->update(['kelas_id' => $kelasBaru->id]);
                    $siswaNaik++;
                }

                if (($aksi['salin_jadwal'] ?? false) && ! empty($aksi['semester_tujuan_id'])) {
                    $semesterTujuan = Semester::find($aksi['semester_tujuan_id']);
                    abort_if(
                        $semesterTujuan === null
                        || $semesterTujuan->lembaga_id !== $kelasLama->lembaga_id
                        || $semesterTujuan->tahun_ajaran_id !== $kelasBaru->tahun_ajaran_id,
                        404
                    );

                    $gagalDiBaris = $this->salinJadwal($kelasLama, $kelasBaru, $semesterTujuan->id);
                    $jadwalGagal = array_merge($jadwalGagal, $gagalDiBaris);
                }
            }
        });

        return [
            'jadwalGagal' => $jadwalGagal,
            'siswaNaik' => $siswaNaik,
            'siswaLulus' => $siswaLulus,
            'kelasDilewati' => $kelasDilewati,
        ];
    }

    /**
     * @return array<int, string> deskripsi baris yang gagal disalin karena bentrok
     */
    private function salinJadwal(Kelas $kelasLama, Kelas $kelasBaru, int $semesterTujuanId): array
    {
        $jadwalLama = JadwalPelajaran::where('kelas_id', $kelasLama->id)->with('jamPelajaran')->get();
        $gagal = [];

        foreach ($jadwalLama as $jadwal) {
            $sudahAda = JadwalPelajaran::where('kelas_id', $kelasBaru->id)
                ->where('jam_pelajaran_id', $jadwal->jam_pelajaran_id)
                ->where('semester_id', $semesterTujuanId)
                ->exists();

            if ($sudahAda) {
                continue;
            }

            try {
                $this->createJadwalPelajaranAction->execute(JadwalPelajaranData::fromArray([
                    'lembaga_id' => $kelasBaru->lembaga_id,
                    'kelas_id' => $kelasBaru->id,
                    'guru_id' => $jadwal->guru_id,
                    'jam_pelajaran_id' => $jadwal->jam_pelajaran_id,
                    'semester_id' => $semesterTujuanId,
                    'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
                    'ruangan_id' => $jadwal->ruangan_id,
                ]));
            } catch (ValidationException $e) {
                $labelJam = $jadwal->jamPelajaran->label ?? "slot #{$jadwal->jam_pelajaran_id}";
                $gagal[] = "{$kelasLama->nama} → {$kelasBaru->nama} ({$labelJam}): ".$e->validator->errors()->first();
            }
        }

        return $gagal;
    }
}
```

Di `tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php`, helper `buatKenaikanAction()` (baris 21-24) saat ini:

```php
function buatKenaikanAction(): ProsesKenaikanKelasAction
{
    return new ProsesKenaikanKelasAction(new CreateJadwalPelajaranAction(new ValidateRoomClashAction));
}
```

Ubah jadi (tambah parameter kedua):

```php
function buatKenaikanAction(): ProsesKenaikanKelasAction
{
    return new ProsesKenaikanKelasAction(
        new CreateJadwalPelajaranAction(new ValidateRoomClashAction),
        app(\App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction::class),
    );
}
```

- [ ] **Step 4: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php --compact`
Expected: PASS — SEMUA test di file ini (5 test lama + 3 test baru) hijau. Test lama `'promotes siswa to the destination kelas and marks lulus siswa accordingly'` HARUS tetap lolos tanpa perubahan assertion (perilaku `kelas_id`/`status`/`kelas_terakhir_id` tidak berubah, cuma jalur internalnya).

- [ ] **Step 5: Jalankan seluruh test Kenaikan Kelas untuk cek regresi lebih luas**

Run: `vendor/bin/pest tests/Feature/Admin/KenaikanKelasControllerTest.php tests/Feature/Akademik/KenaikanKelasControllerUxTest.php tests/Feature/Akademik/KenaikanKelasIndicatorTest.php --compact`
Expected: semua PASS. Kalau `KenaikanKelasController.php` belum di-inject `UpdateStatusSiswaAction` secara eksplisit (tidak perlu — `ProsesKenaikanKelasAction` di-resolve Laravel service container otomatis, `UpdateStatusSiswaAction` akan ikut ter-resolve otomatis via constructor injection tanpa perubahan controller), test controller ini seharusnya tetap lolos tanpa perubahan apa pun di controller untuk task ini.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php
git commit -m "fix(akademik): kenaikan kelas pakai per-siswa update, reuse UpdateStatusSiswaAction agar akun lulus dinonaktifkan & event/tagihan/audit-log ikut terpicu

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Validasi Tahun Ajaran Sumber vs Tujuan di `index()`

**Files:**
- Modify: `app/Http/Controllers/Admin/KenaikanKelasController.php`
- Test: `tests/Feature/Admin/KenaikanKelasControllerTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: `App\Models\TahunAjaran` (model existing, kolom `tanggal_mulai`, relasi `lembaga()` — dipakai juga Task 3).
- Produces: view `portals.lembaga.akademik.kenaikan-kelas.index` menerima variabel baru `errorTahunAjaran` (`?string`) — Task 7 mengonsumsi variabel ini untuk menampilkan banner.

- [ ] **Step 1: Tulis test yang gagal — sumber sama dengan tujuan, dan tujuan lebih lama dari sumber, harus ditolak**

Tambahkan di `tests/Feature/Admin/KenaikanKelasControllerTest.php`, di akhir file (setelah test terakhir `it('does not block promoting a siswa into a kelas at the same tingkat...', ...)`):

```php
it('rejects rendering the mapping table when tahun ajaran sumber and tujuan are the same', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahun = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahun->id, 'nama' => '5A']);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahun->id,
        'tahun_ajaran_tujuan_id' => $tahun->id,
    ]));

    $response->assertOk();
    $response->assertViewHas('errorTahunAjaran', fn ($error) => $error !== null);
    $response->assertViewHas('kelasLamaList', fn ($list) => $list->isEmpty());
});

it('rejects rendering the mapping table when tahun ajaran tujuan is older than sumber', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01']);
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026', 'tanggal_mulai' => '2025-07-01']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunBaru->id,
        'tahun_ajaran_tujuan_id' => $tahunLama->id,
    ]));

    $response->assertOk();
    $response->assertViewHas('errorTahunAjaran', fn ($error) => $error !== null);
    $response->assertViewHas('kelasLamaList', fn ($list) => $list->isEmpty());
});

it('renders the mapping table normally when tujuan is a genuinely later tahun ajaran', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026', 'tanggal_mulai' => '2025-07-01']);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunLama->id,
        'tahun_ajaran_tujuan_id' => $tahunBaru->id,
    ]));

    $response->assertOk();
    $response->assertViewHas('errorTahunAjaran', fn ($error) => $error === null);
    $response->assertViewHas('kelasLamaList', fn ($list) => $list->isNotEmpty());
});
```

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Admin/KenaikanKelasControllerTest.php --filter="rejects rendering the mapping table"`
Expected: FAIL — `errorTahunAjaran` tidak ada di view data sama sekali (view belum menerima variabel itu), dan `kelasLamaList` tidak kosong (tabel tetap dirender walau kombinasi tahun ajaran salah).

- [ ] **Step 3: Tambah validasi di `index()`**

Isi `app/Http/Controllers/Admin/KenaikanKelasController.php` saat ini:

```php
    public function index(Request $request): View
    {
        $this->authorize('kenaikan-kelas.kelola');

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        $tahunAjaranTujuanId = $request->query('tahun_ajaran_tujuan_id');

        return view('portals.lembaga.akademik.kenaikan-kelas.index', [
            'tahunAjaranList' => TahunAjaran::orderByDesc('tanggal_mulai')->get(),
            'kelasLamaList' => $tahunAjaranId
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->with('lembaga')->withCount('siswa')->orderBy('nama')->get()
                : collect(),
            'kelasTujuanList' => $tahunAjaranTujuanId
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderBy('nama')->get()
                : collect(),
            'semesterList' => $tahunAjaranTujuanId
                ? Semester::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderByDesc('id')->get()
                : collect(),
            'tahunAjaranId' => $tahunAjaranId,
            'tahunAjaranTujuanId' => $tahunAjaranTujuanId,
        ]);
    }
```

Ubah jadi:

```php
    public function index(Request $request): View
    {
        $this->authorize('kenaikan-kelas.kelola');

        $tahunAjaranId = $request->query('tahun_ajaran_id');
        $tahunAjaranTujuanId = $request->query('tahun_ajaran_tujuan_id');

        $errorTahunAjaran = null;
        if ($tahunAjaranId && $tahunAjaranTujuanId) {
            $tahunSumber = TahunAjaran::find($tahunAjaranId);
            $tahunTujuan = TahunAjaran::find($tahunAjaranTujuanId);

            if ($tahunSumber && $tahunTujuan) {
                if ((int) $tahunAjaranId === (int) $tahunAjaranTujuanId) {
                    $errorTahunAjaran = 'Tahun Ajaran Sumber dan Tujuan tidak boleh sama. Pilih Tahun Ajaran Tujuan yang berbeda (biasanya tahun ajaran berikutnya).';
                } elseif ($tahunTujuan->tanggal_mulai < $tahunSumber->tanggal_mulai) {
                    $errorTahunAjaran = "Tahun Ajaran Tujuan (\"{$tahunTujuan->nama}\") lebih lama dari Tahun Ajaran Sumber (\"{$tahunSumber->nama}\"). Pilih Tahun Ajaran Tujuan yang lebih baru.";
                }
            }
        }

        return view('portals.lembaga.akademik.kenaikan-kelas.index', [
            'tahunAjaranList' => TahunAjaran::with('lembaga')->orderByDesc('tanggal_mulai')->get(),
            'kelasLamaList' => ($tahunAjaranId && ! $errorTahunAjaran)
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranId)->with('lembaga')->withCount('siswa')->orderBy('nama')->get()
                : collect(),
            'kelasTujuanList' => ($tahunAjaranTujuanId && ! $errorTahunAjaran)
                ? Kelas::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderBy('nama')->get()
                : collect(),
            'semesterList' => ($tahunAjaranTujuanId && ! $errorTahunAjaran)
                ? Semester::where('tahun_ajaran_id', $tahunAjaranTujuanId)->orderByDesc('id')->get()
                : collect(),
            'tahunAjaranId' => $tahunAjaranId,
            'tahunAjaranTujuanId' => $tahunAjaranTujuanId,
            'errorTahunAjaran' => $errorTahunAjaran,
        ]);
    }
```

Method `store()` TIDAK berubah di task ini (disentuh Task 4 dan Task 5 terpisah).

- [ ] **Step 4: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Admin/KenaikanKelasControllerTest.php --compact`
Expected: PASS — semua test di file ini hijau.

- [ ] **Step 5: Jalankan test UX existing untuk cek regresi**

Run: `vendor/bin/pest tests/Feature/Akademik/KenaikanKelasControllerUxTest.php --compact`
Expected: PASS — semua test existing (yang selalu kirim `tahun_ajaran_id`/`tahun_ajaran_tujuan_id` berbeda & valid) tetap lolos tanpa `errorTahunAjaran`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KenaikanKelasController.php tests/Feature/Admin/KenaikanKelasControllerTest.php
git commit -m "fix(akademik): validasi tahun ajaran sumber vs tujuan di titik masuk kenaikan kelas, berlaku utk semua tindakan

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Dropdown Tahun Ajaran Tampilkan Nama Lembaga + A11y

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`
- Test: `tests/Feature/Admin/KenaikanKelasControllerTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: `$tahunAjaranList` (sekarang eager-load `lembaga`, hasil Task 2 Step 3) — `$tahunAjaran->lembaga->nama`.
- Produces: tidak ada interface baru untuk task lain.

**PRASYARAT: Task 2 WAJIB sudah selesai & di-commit** (Task 3 menyentuh blok picker form yang sama, dan bergantung pada eager-load `lembaga` yang ditambahkan Task 2 Step 3).

- [ ] **Step 1: Tulis test yang gagal — dropdown menampilkan nama lembaga**

Tambahkan di `tests/Feature/Admin/KenaikanKelasControllerTest.php`, di akhir file:

```php
it('shows the lembaga name alongside each tahun ajaran option to disambiguate aggregate mode', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'nama' => 'SD Test Unik']);
    $tahun = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2025/2026']);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index'));

    $response->assertOk();
    $response->assertSee('2025/2026 — SD Test Unik');
});
```

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Admin/KenaikanKelasControllerTest.php --filter="shows the lembaga name alongside"`
Expected: FAIL — teks "2025/2026 — SD Test Unik" tidak ada, dropdown cuma menampilkan "2025/2026".

- [ ] **Step 3: Ubah blok picker form di `index.blade.php`**

Baris 20-43 saat ini:

```blade
        {{-- Source & Target Tahun Ajaran Picker --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
            <form method="GET" action="{{ route('admin.kenaikan-kelas.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Tahun Ajaran Sumber (kelas lama)" />
                    <select name="tahun_ajaran_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[220px]">
                    <x-input-label value="Tahun Ajaran Tujuan (kelas baru)" />
                    <select name="tahun_ajaran_tujuan_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranTujuanId == $tahunAjaran->id)>{{ $tahunAjaran->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button type="submit">Tampilkan</x-primary-button>
            </form>
        </div>
```

Ubah jadi:

```blade
        {{-- Source & Target Tahun Ajaran Picker --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-card">
            <form method="GET" action="{{ route('admin.kenaikan-kelas.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[220px]">
                    <x-input-label for="tahun_ajaran_id" value="Tahun Ajaran Sumber (kelas lama)" />
                    <select id="tahun_ajaran_id" name="tahun_ajaran_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranId == $tahunAjaran->id)>{{ $tahunAjaran->nama }} — {{ $tahunAjaran->lembaga->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[220px]">
                    <x-input-label for="tahun_ajaran_tujuan_id" value="Tahun Ajaran Tujuan (kelas baru)" />
                    <select id="tahun_ajaran_tujuan_id" name="tahun_ajaran_tujuan_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">— Pilih —</option>
                        @foreach ($tahunAjaranList as $tahunAjaran)
                            <option value="{{ $tahunAjaran->id }}" @selected($tahunAjaranTujuanId == $tahunAjaran->id)>{{ $tahunAjaran->nama }} — {{ $tahunAjaran->lembaga->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button type="submit">Tampilkan</x-primary-button>
            </form>
        </div>
```

(Blok "Source & Target Tahun Ajaran Picker" ini SEBELUM blok banner error dari Task 2/Task 7 — banner error dari Task 7 disisipkan SETELAH blok ini, JANGAN disisipkan di sini oleh task ini.)

- [ ] **Step 4: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Admin/KenaikanKelasControllerTest.php --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php tests/Feature/Admin/KenaikanKelasControllerTest.php
git commit -m "fix(akademik): tampilkan nama lembaga di dropdown tahun ajaran kenaikan kelas, perbaiki a11y label for/id

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: Validasi `exists:kelas,id` + `salin_jadwal` Requires `semester_tujuan_id`

**Files:**
- Modify: `app/Http/Controllers/Admin/KenaikanKelasController.php`
- Test: `tests/Feature/Admin/KenaikanKelasControllerTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: tidak ada interface baru.

- [ ] **Step 1: Tulis test yang gagal — kelas_baru_id tidak exists, dan salin_jadwal tanpa semester_tujuan_id, harus ditolak validasi**

Tambahkan di `tests/Feature/Admin/KenaikanKelasControllerTest.php`, di akhir file:

```php
it('rejects a kelas_baru_id that does not exist in the database', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLama->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => 999999],
        ],
    ])->assertSessionHasErrors();

    $siswa->refresh();
    expect($siswa->kelas_id)->toBe($kelasLama->id);
});

it('rejects checking salin_jadwal without selecting a semester_tujuan_id', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => '6A']);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $response = $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasLama->id => [
                'tindakan' => 'naik',
                'kelas_baru_id' => $kelasBaru->id,
                'salin_jadwal' => '1',
            ],
        ],
    ]);

    $response->assertSessionHasErrors();
});
```

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Admin/KenaikanKelasControllerTest.php --filter="rejects a kelas_baru_id that does not exist"`
Expected: FAIL — request untuk ID kelas yang tidak ada menghasilkan HTTP 404 (dari `abort_if` di dalam Action), bukan `assertSessionHasErrors()` (redirect back dengan validation error).

Run: `vendor/bin/pest tests/Feature/Admin/KenaikanKelasControllerTest.php --filter="rejects checking salin_jadwal without selecting"`
Expected: FAIL — tidak ada session error sama sekali, request dianggap sukses (redirect ke `admin.kelas.index`) walau jadwal tidak tersalin.

- [ ] **Step 3: Tambah validasi di `store()`**

Baris `$data = $request->validate([...]);` di `app/Http/Controllers/Admin/KenaikanKelasController.php`, method `store()`, saat ini:

```php
        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*.tindakan' => ['required', 'in:naik,lulus,lewati'],
            'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'integer'],
            'mapping.*.salin_jadwal' => ['nullable', 'boolean'],
            'mapping.*.semester_tujuan_id' => ['nullable', 'integer'],
        ]);
```

Ubah jadi:

```php
        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*.tindakan' => ['required', 'in:naik,lulus,lewati'],
            'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'integer', 'exists:kelas,id'],
            'mapping.*.salin_jadwal' => ['nullable', 'boolean'],
            'mapping.*.semester_tujuan_id' => ['required_if:mapping.*.salin_jadwal,1', 'nullable', 'integer', 'exists:semester,id'],
        ], [
            'mapping.*.kelas_baru_id.exists' => 'Kelas tujuan yang dipilih tidak valid atau sudah tidak tersedia.',
            'mapping.*.semester_tujuan_id.required_if' => 'Anda mencentang "Salin Jadwal" untuk salah satu kelas, tapi belum memilih semester tujuan. Pilih semester tujuan atau batalkan centang tersebut.',
        ]);
```

- [ ] **Step 4: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Admin/KenaikanKelasControllerTest.php --compact`
Expected: PASS — semua test di file ini hijau. Test existing `'rejects promoting siswa into a kelas belonging to a different lembaga'` (yang mengirim `kelas_baru_id` valid TAPI beda lembaga, dan meng-assert `assertNotFound()`) HARUS TETAP LOLOS — `exists:kelas,id` hanya menolak ID yang benar-benar tidak ada di tabel, bukan ID valid milik lembaga lain (itu tetap ditangani `abort_if` di dalam Action seperti sebelumnya, guard lembaga TIDAK berubah).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/KenaikanKelasController.php tests/Feature/Admin/KenaikanKelasControllerTest.php
git commit -m "fix(akademik): validasi exists:kelas,id dan salin_jadwal wajib semester_tujuan_id di kenaikan kelas

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 5: Ringkasan Hasil Flash Message

**Files:**
- Modify: `app/Http/Controllers/Admin/KenaikanKelasController.php`
- Test: `tests/Feature/Admin/KenaikanKelasControllerTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: `ProsesKenaikanKelasAction::execute()` return value `array{jadwalGagal, siswaNaik, siswaLulus, kelasDilewati}` (hasil Task 1).
- Produces: tidak ada interface baru.

**PRASYARAT: Task 1 WAJIB sudah selesai & di-commit** (return value baru dari Task 1 dipakai langsung di task ini).

- [ ] **Step 1: Tulis test yang gagal — flash message berisi ringkasan angka**

Tambahkan di `tests/Feature/Admin/KenaikanKelasControllerTest.php`, di akhir file:

```php
it('includes a summary of siswa naik/lulus/kelas dilewati counts in the success flash message', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasNaik = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => '6A']);
    $kelasLulus = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '6B']);
    Siswa::factory()->count(2)->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasNaik->id, 'status' => StatusSiswa::Aktif->value]);
    Siswa::factory()->count(1)->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasLulus->id, 'status' => StatusSiswa::Aktif->value]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $response = $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasNaik->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id],
            $kelasLulus->id => ['tindakan' => 'lulus'],
        ],
    ]);

    $response->assertRedirect(route('admin.kelas.index'));
    $response->assertSessionHas('status', fn ($status) => str_contains($status, '2 siswa naik kelas') && str_contains($status, '1 siswa diluluskan'));
});
```

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Admin/KenaikanKelasControllerTest.php --filter="includes a summary of siswa naik"`
Expected: FAIL — flash message cuma "Kenaikan kelas berhasil diproses.", tidak mengandung angka apa pun.

- [ ] **Step 3: Ubah `store()` untuk memakai return value baru**

Isi method `store()` di `app/Http/Controllers/Admin/KenaikanKelasController.php` saat ini (SETELAH Task 4 diterapkan):

```php
    public function store(Request $request, ProsesKenaikanKelasAction $action): RedirectResponse
    {
        $this->authorize('kenaikan-kelas.kelola');

        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*.tindakan' => ['required', 'in:naik,lulus,lewati'],
            'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'integer', 'exists:kelas,id'],
            'mapping.*.salin_jadwal' => ['nullable', 'boolean'],
            'mapping.*.semester_tujuan_id' => ['required_if:mapping.*.salin_jadwal,1', 'nullable', 'integer', 'exists:semester,id'],
        ], [
            'mapping.*.kelas_baru_id.exists' => 'Kelas tujuan yang dipilih tidak valid atau sudah tidak tersedia.',
            'mapping.*.semester_tujuan_id.required_if' => 'Anda mencentang "Salin Jadwal" untuk salah satu kelas, tapi belum memilih semester tujuan. Pilih semester tujuan atau batalkan centang tersebut.',
        ]);

        try {
            $result = $action->execute(new KenaikanKelasData(mapping: $data['mapping']));
        } catch (\DomainException $e) {
            return back()->withErrors(['mapping' => $e->getMessage()]);
        }

        $status = 'Kenaikan kelas berhasil diproses.';
        if (! empty($result['jadwalGagal'])) {
            $status .= ' '.count($result['jadwalGagal']).' jadwal tidak tersalin karena bentrok: '.implode('; ', $result['jadwalGagal']).'.';
        }

        return redirect()->route('admin.kelas.index')->with('status', $status);
    }
```

Ubah jadi:

```php
    public function store(Request $request, ProsesKenaikanKelasAction $action): RedirectResponse
    {
        $this->authorize('kenaikan-kelas.kelola');

        $data = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*.tindakan' => ['required', 'in:naik,lulus,lewati'],
            'mapping.*.kelas_baru_id' => ['required_if:mapping.*.tindakan,naik', 'nullable', 'integer', 'exists:kelas,id'],
            'mapping.*.salin_jadwal' => ['nullable', 'boolean'],
            'mapping.*.semester_tujuan_id' => ['required_if:mapping.*.salin_jadwal,1', 'nullable', 'integer', 'exists:semester,id'],
        ], [
            'mapping.*.kelas_baru_id.exists' => 'Kelas tujuan yang dipilih tidak valid atau sudah tidak tersedia.',
            'mapping.*.semester_tujuan_id.required_if' => 'Anda mencentang "Salin Jadwal" untuk salah satu kelas, tapi belum memilih semester tujuan. Pilih semester tujuan atau batalkan centang tersebut.',
        ]);

        try {
            $result = $action->execute(new KenaikanKelasData(mapping: $data['mapping']));
        } catch (\DomainException $e) {
            return back()->withErrors(['mapping' => $e->getMessage()]);
        }

        $status = "Kenaikan kelas berhasil diproses: {$result['siswaNaik']} siswa naik kelas, {$result['siswaLulus']} siswa diluluskan, {$result['kelasDilewati']} kelas dilewati.";
        if (! empty($result['jadwalGagal'])) {
            $status .= ' '.count($result['jadwalGagal']).' jadwal tidak tersalin karena bentrok: '.implode('; ', $result['jadwalGagal']).'.';
        }

        return redirect()->route('admin.kelas.index')->with('status', $status);
    }
```

- [ ] **Step 4: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Admin/KenaikanKelasControllerTest.php --compact`
Expected: PASS — semua test di file ini hijau.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/KenaikanKelasController.php tests/Feature/Admin/KenaikanKelasControllerTest.php
git commit -m "fix(akademik): flash message kenaikan kelas sertakan ringkasan jumlah siswa naik/lulus/kelas dilewati

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 6: Konfirmasi + Ringkasan Real-Time + Highlight Baris Peringatan

**Files:**
- Create: `resources/js/kenaikan-kelas-form.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`

**Interfaces:**
- Consumes: `window.confirmDialog(title, message, options): Promise<boolean>` (global function, sudah terdaftar via `registerConfirmDialogStore` di `app.js`, TIDAK diubah task ini).
- Produces: `Alpine.data('kenaikanKelasForm', ...)` — dipakai di `<form x-data="kenaikanKelasForm()">`.

**PRASYARAT: Task 3 WAJIB sudah selesai & di-commit** (task ini menyentuh file `index.blade.php` yang sama).

- [ ] **Step 1: Buat file JS baru**

Buat `resources/js/kenaikan-kelas-form.js`:

```js
export function kenaikanKelasForm() {
    return {
        submitting: false,

        async konfirmasiDanKirim(event) {
            const form = event.target;
            const rows = form.querySelectorAll('tbody tr[data-kelas-lama]');
            let naik = 0;
            let lulus = 0;
            let lewati = 0;
            let peringatan = 0;

            rows.forEach((row) => {
                const tindakan = row.querySelector('select[name$="[tindakan]"]')?.value;
                if (tindakan === 'naik') naik++;
                else if (tindakan === 'lulus') lulus++;
                else lewati++;

                if (row.dataset.warning === '1') peringatan++;
            });

            let message = `${naik} kelas akan dinaikkan, ${lulus} kelas akan diluluskan, ${lewati} kelas dilewati.`;
            if (lulus > 0) {
                message += ' Siswa yang diluluskan akan dinonaktifkan akunnya secara otomatis.';
            }
            if (peringatan > 0) {
                message += ` Perhatian: ${peringatan} baris punya peringatan kurikulum/tingkat tidak wajar — periksa kembali kolom "Kelas Tujuan" sebelum lanjut.`;
            }
            message += ' Tindakan ini memindahkan/meluluskan siswa secara langsung dan tidak bisa dibatalkan otomatis.';

            const confirmed = await window.confirmDialog('Proses Kenaikan Kelas?', message, { confirmLabel: 'Ya, Proses Sekarang' });
            if (confirmed) {
                this.submitting = true;
                form.submit();
            }
        },
    };
}
```

- [ ] **Step 2: Registrasi di `app.js`**

Buka `resources/js/app.js`. Cari blok import lain yang mengikuti pola `import { xxx } from './xxx';` (mis. baris `import { registerConfirmDialogStore } from './confirm-dialog-store';` di sekitar baris 8, atau blok import fitur Akademik lain). Tambahkan baris baru:

```js
import { kenaikanKelasForm } from './kenaikan-kelas-form';
```

Cari blok registrasi `Alpine.data(...)` lain (mis. `Alpine.data('raporWaliKelasFilter', raporWaliKelasFilter);` di sekitar baris 81). Tambahkan baris baru SETELAH baris registrasi yang sudah ada terakhir:

```js
Alpine.data('kenaikanKelasForm', kenaikanKelasForm);
```

- [ ] **Step 3: Ubah `<form>`, `<tr>`, dan tombol submit di `index.blade.php`**

Tag `<form>` (baris 58 saat ini):

```blade
                <form method="POST" action="{{ route('admin.kenaikan-kelas.store') }}">
```

Ubah jadi:

```blade
                <form method="POST" action="{{ route('admin.kenaikan-kelas.store') }}" x-data="kenaikanKelasForm()" @submit.prevent="konfirmasiDanKirim($event)">
```

Baris pembuka `<tr>` per-baris kelas (baris 74-92 saat ini):

```blade
                                    <tr class="transition hover:bg-gray-50/60" x-data="{
                                        kurikulumAsal: {{ Js::from($kelasLama->kurikulum?->value) }},
                                        kurikulumTujuan: null,
                                        tingkatTujuan: null,
                                        tingkatAsal: {{ Js::from($kelasLama->tingkat) }},
                                        daftarTingkat: {{ Js::from($kelasLama->lembaga ? BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->validTingkatValues() : []) }},
                                        onKelasTujuanChange(event) {
                                            const opt = event.target.selectedOptions[0];
                                            this.kurikulumTujuan = opt?.dataset.kurikulum || null;
                                            this.tingkatTujuan = opt?.dataset.tingkat || null;
                                        },
                                        get selisihIndexTingkat() {
                                            if (this.tingkatTujuan === null || this.tingkatAsal === null) return null;
                                            const indexAsal = this.daftarTingkat.indexOf(this.tingkatAsal);
                                            const indexTujuan = this.daftarTingkat.indexOf(this.tingkatTujuan);
                                            if (indexAsal === -1 || indexTujuan === -1) return null;
                                            return indexTujuan - indexAsal;
                                        },
                                    }">
```

Ubah jadi (getter/state x-data TIDAK berubah, hanya menambah `data-kelas-lama`, `:data-warning`, `:class` di elemen `<tr>` yang sama):

```blade
                                    <tr data-kelas-lama="{{ $kelasLama->id }}"
                                        :data-warning="((kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal) || (selisihIndexTingkat !== null && selisihIndexTingkat !== 0 && selisihIndexTingkat !== 1)) ? '1' : '0'"
                                        :class="{ 'border-l-4 border-amber-400 bg-amber-50/30': ((kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal) || (selisihIndexTingkat !== null && selisihIndexTingkat !== 0 && selisihIndexTingkat !== 1)) }"
                                        class="transition hover:bg-gray-50/60"
                                        x-data="{
                                        kurikulumAsal: {{ Js::from($kelasLama->kurikulum?->value) }},
                                        kurikulumTujuan: null,
                                        tingkatTujuan: null,
                                        tingkatAsal: {{ Js::from($kelasLama->tingkat) }},
                                        daftarTingkat: {{ Js::from($kelasLama->lembaga ? BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->validTingkatValues() : []) }},
                                        onKelasTujuanChange(event) {
                                            const opt = event.target.selectedOptions[0];
                                            this.kurikulumTujuan = opt?.dataset.kurikulum || null;
                                            this.tingkatTujuan = opt?.dataset.tingkat || null;
                                        },
                                        get selisihIndexTingkat() {
                                            if (this.tingkatTujuan === null || this.tingkatAsal === null) return null;
                                            const indexAsal = this.daftarTingkat.indexOf(this.tingkatAsal);
                                            const indexTujuan = this.daftarTingkat.indexOf(this.tingkatTujuan);
                                            if (indexAsal === -1 || indexTujuan === -1) return null;
                                            return indexTujuan - indexAsal;
                                        },
                                    }">
```

Tombol submit (baris 153 saat ini):

```blade
                        <x-primary-button type="submit">Proses Kenaikan Kelas</x-primary-button>
```

Ubah jadi:

```blade
                        <x-primary-button type="submit" x-bind:disabled="submitting">Proses Kenaikan Kelas</x-primary-button>
```

- [ ] **Step 4: Build asset frontend**

Run: `npm run build`
Expected: build sukses tanpa error.

- [ ] **Step 5: Verifikasi manual dev-server — tidak ada automated test untuk perilaku JS Alpine murni**

Login sebagai admin dengan permission `kenaikan-kelas.kelola`, buka halaman Kenaikan Kelas dengan tahun ajaran sumber & tujuan valid berisi minimal 1 kelas dengan kurikulum/tingkat tujuan berbeda dari asal (untuk memicu warning). Konfirmasi lewat browser:
1. Klik "Proses Kenaikan Kelas" — muncul dialog konfirmasi dengan ringkasan jumlah naik/lulus/lewati yang benar sesuai isian tabel.
2. Kalau ada baris dengan kurikulum/tingkat tidak wajar, dialog menyebutkan jumlah baris berperingatan, DAN baris itu di tabel punya border kiri amber.
3. Klik "Ya, Proses Sekarang" — form benar-benar submit, tombol berubah `disabled` (mencegah double-klik).
4. Klik "Batal" — form TIDAK submit, halaman tetap di tempat.

- [ ] **Step 6: Jalankan test existing untuk memastikan tidak ada regresi**

Run: `vendor/bin/pest tests/Feature/Akademik/KenaikanKelasControllerUxTest.php --compact`
Expected: PASS — SEMUA test existing yang meng-assert isi `x-data` per-baris (`kurikulumAsal`, `get selisihIndexTingkat()`, dst) TETAP LOLOS karena teks itu masih ada persis sama di HTML (cuma ada tambahan attribute baru di sekitarnya, bukan pengganti).

- [ ] **Step 7: Commit**

```bash
git add resources/js/kenaikan-kelas-form.js resources/js/app.js resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php
git commit -m "feat(akademik): tambah konfirmasi + ringkasan real-time + highlight baris peringatan sebelum proses kenaikan kelas

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 7: Polish — Banner Error, Penjelasan Salin Jadwal, Opsi Lewati, Wording Kurikulum, Style Tabel

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`
- Test: `tests/Feature/Admin/KenaikanKelasControllerTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: `$errorTahunAjaran` (dari Task 2).
- Produces: tidak ada interface baru.

**PRASYARAT: Task 2, Task 3, Task 6 WAJIB sudah selesai & di-commit** (task ini adalah polish terakhir di file yang sama, dikerjakan setelah semuanya settle).

- [ ] **Step 1: Tulis test yang gagal — opsi "Lewati" tetap tersedia untuk kelas berisi siswa**

Tambahkan di `tests/Feature/Admin/KenaikanKelasControllerTest.php`, di akhir file:

```php
it('still offers the Lewati option for a kelas that has siswa, not just empty ones', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasBerisi = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => '5A']);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasBerisi->id]);
    $manager = actingAsKenaikanKelasManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunLalu->id,
        'tahun_ajaran_tujuan_id' => $tahunBaru->id,
    ]));

    $response->assertOk();
    $response->assertSee('value="lewati"', false);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Admin/KenaikanKelasControllerTest.php --filter="still offers the Lewati option"`
Expected: FAIL — opsi `value="lewati"` tidak dirender sama sekali untuk kelas yang punya siswa (`siswa_count > 0`).

- [ ] **Step 3: Tambah banner error validasi tahun ajaran**

SETELAH blok "Source & Target Tahun Ajaran Picker" (penutup `</div>` dari blok itu, hasil Task 3), SEBELUM blok `@if ($kelasLamaList->isNotEmpty() && $tahunAjaranTujuanId === null)` (baris 45 saat ini), sisipkan:

```blade
        @if ($errorTahunAjaran)
            <div class="rounded-2xl border border-error-200 bg-error-50 p-4 text-sm text-error-700">
                {{ $errorTahunAjaran }}
            </div>
        @endif
```

- [ ] **Step 4: Tambah penjelasan fungsi "Salin Jadwal ke Semester"**

Header kolom (baris 69 saat ini):

```blade
                                    <th class="px-4 py-3.5">Salin Jadwal ke Semester</th>
```

Ubah jadi:

```blade
                                    <th class="px-4 py-3.5">
                                        Salin Jadwal ke Semester
                                        <span class="block text-[10px] font-normal normal-case text-gray-400 mt-0.5">Menyalin struktur jadwal pelajaran kelas lama ke kelas tujuan, di semester yang dipilih</span>
                                    </th>
```

- [ ] **Step 5: Opsi "Lewati" selalu tersedia**

Dropdown Tindakan (baris 108-114 saat ini, SETELAH Task 6 baris ini bergeser tapi ISINYA sama):

```blade
                                        <td class="px-4 py-4">
                                            <select name="mapping[{{ $kelasLama->id }}][tindakan]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                @if ($kelasLama->siswa_count === 0)
                                                    <option value="lewati" selected>Lewati (sudah kosong)</option>
                                                @endif
                                                <option value="naik" @selected(! $isTingkatAkhir && $kelasLama->siswa_count > 0)>Naik Kelas</option>
                                                <option value="lulus" @selected($isTingkatAkhir && $kelasLama->siswa_count > 0)>Lulus</option>
                                            </select>
                                            @if ($isTingkatAkhir)
                                                <p class="mt-1 text-xs text-amber-600">Disarankan: tingkat akhir jenjang</p>
                                            @endif
                                        </td>
```

Ubah jadi (opsi `naik`/`lulus` dan logic `@selected(...)`-nya TIDAK BERUBAH — hanya opsi `lewati` yang sekarang SELALU dirender):

```blade
                                        <td class="px-4 py-4">
                                            <select name="mapping[{{ $kelasLama->id }}][tindakan]" class="rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                                <option value="lewati" @selected($kelasLama->siswa_count === 0)>Lewati{{ $kelasLama->siswa_count === 0 ? ' (sudah kosong)' : '' }}</option>
                                                <option value="naik" @selected(! $isTingkatAkhir && $kelasLama->siswa_count > 0)>Naik Kelas</option>
                                                <option value="lulus" @selected($isTingkatAkhir && $kelasLama->siswa_count > 0)>Lulus</option>
                                            </select>
                                            @if ($isTingkatAkhir)
                                                <p class="mt-1 text-xs text-amber-600">Disarankan: tingkat akhir jenjang</p>
                                            @endif
                                        </td>
```

- [ ] **Step 6: Wording peringatan kurikulum pakai label, bukan raw value**

Opsi kelas tujuan (baris 122-124 saat ini):

```blade
                                                @foreach ($kelasTujuanList as $kelasBaru)
                                                    <option value="{{ $kelasBaru->id }}" data-kurikulum="{{ $kelasBaru->kurikulum?->value }}" data-tingkat="{{ $kelasBaru->tingkat }}">{{ $kelasBaru->nama }}</option>
                                                @endforeach
```

Ubah jadi:

```blade
                                                @foreach ($kelasTujuanList as $kelasBaru)
                                                    <option value="{{ $kelasBaru->id }}" data-kurikulum="{{ $kelasBaru->kurikulum?->value }}" data-kurikulum-label="{{ $kelasBaru->kurikulum?->label() }}" data-tingkat="{{ $kelasBaru->tingkat }}">{{ $kelasBaru->nama }}</option>
                                                @endforeach
```

Blok `x-data` per-baris (hasil Task 6, di dalam `<tr ...>`) — tambah 2 field baru dan update `onKelasTujuanChange`. Bagian ini SAAT INI (setelah Task 6):

```js
                                        kurikulumAsal: {{ Js::from($kelasLama->kurikulum?->value) }},
                                        kurikulumTujuan: null,
                                        tingkatTujuan: null,
                                        tingkatAsal: {{ Js::from($kelasLama->tingkat) }},
                                        daftarTingkat: {{ Js::from($kelasLama->lembaga ? BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->validTingkatValues() : []) }},
                                        onKelasTujuanChange(event) {
                                            const opt = event.target.selectedOptions[0];
                                            this.kurikulumTujuan = opt?.dataset.kurikulum || null;
                                            this.tingkatTujuan = opt?.dataset.tingkat || null;
                                        },
```

Ubah jadi:

```js
                                        kurikulumAsal: {{ Js::from($kelasLama->kurikulum?->value) }},
                                        kurikulumAsalLabel: {{ Js::from($kelasLama->kurikulum?->label()) }},
                                        kurikulumTujuan: null,
                                        kurikulumTujuanLabel: null,
                                        tingkatTujuan: null,
                                        tingkatAsal: {{ Js::from($kelasLama->tingkat) }},
                                        daftarTingkat: {{ Js::from($kelasLama->lembaga ? BentukPendidikan::from($kelasLama->lembaga->bentuk_pendidikan)->validTingkatValues() : []) }},
                                        onKelasTujuanChange(event) {
                                            const opt = event.target.selectedOptions[0];
                                            this.kurikulumTujuan = opt?.dataset.kurikulum || null;
                                            this.kurikulumTujuanLabel = opt?.dataset.kurikulumLabel || null;
                                            this.tingkatTujuan = opt?.dataset.tingkat || null;
                                        },
```

(`get selisihIndexTingkat() {...}` dan sisa `x-data` TIDAK berubah — hanya blok ini yang diubah, `:data-warning`/`:class` dari Task 6 TETAP memakai `kurikulumAsal`/`kurikulumTujuan` raw value untuk LOGIC perbandingan, JANGAN diganti ke variabel label.)

Teks peringatan kurikulum (baris 127-129 saat ini):

```blade
                                            <p x-show="kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal"
                                               class="mt-1 text-xs font-medium text-amber-600"
                                               x-text="'⚠ Kurikulum berbeda: kelas asal ' + kurikulumAsal + ', kelas tujuan ' + kurikulumTujuan"></p>
```

Ubah jadi:

```blade
                                            <p x-show="kurikulumTujuan !== null && kurikulumAsal !== null && kurikulumTujuan !== kurikulumAsal"
                                               class="mt-1 text-xs font-medium text-amber-600"
                                               x-text="'⚠ Kurikulum berbeda: kelas asal ' + kurikulumAsalLabel + ', kelas tujuan ' + kurikulumTujuanLabel"></p>
```

- [ ] **Step 7: Samakan shade warna & padding tabel**

Header tabel (baris 64 saat ini):

```blade
                                <tr class="border-b border-gray-200 bg-gray-100 text-xs uppercase font-bold tracking-wider text-gray-600">
```

Ubah jadi:

```blade
                                <tr class="border-b border-gray-200 bg-gray-50/50 text-xs uppercase font-bold tracking-wider text-gray-600">
```

`<th>` pertama (baris 65 saat ini, `px-6 py-3.5`):

```blade
                                    <th class="px-6 py-3.5">Kelas Lama</th>
```

Ubah jadi:

```blade
                                    <th class="px-5 py-3">Kelas Lama</th>
```

Sisa `<th>` yang pakai `px-4 py-3.5` (baris 66-68, 69 hasil Step 4) — ubah jadi `px-4 py-3` (samakan skala vertikal saja, horizontal tetap `px-4` karena kolom itu lebih sempit dari kolom pertama, konsisten dengan pola proyek yang membedakan padding horizontal kolom pertama vs kolom lain).

Sel `<td>` yang pakai `px-6 py-4` (baris 93, 101, 107, 119, 135 saat ini) — ubah SEMUA jadi `px-5 py-3.5`. Sel `<td>` yang pakai `px-4 py-4` — ubah jadi `px-4 py-3.5`.

- [ ] **Step 8: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Admin/KenaikanKelasControllerTest.php tests/Feature/Akademik/KenaikanKelasControllerUxTest.php --compact`
Expected: PASS — semua test hijau, termasuk test existing `'pre-selects Lewati for a kelas that is already empty'` (opsi `lewati` tetap ter-`selected` untuk kelas kosong, sekarang JUGA ada untuk kelas berisi tanpa ter-`selected`).

- [ ] **Step 9: Verifikasi manual dev-server**

`npm run build` (kalau belum dari Task 6). Buka halaman, konfirmasi: banner error muncul kalau URL diisi manual dengan tahun ajaran sumber=tujuan sama, kolom "Salin Jadwal" punya teks bantuan kecil, dropdown "Tindakan" untuk kelas berisi siswa sekarang PUNYA opsi "Lewati" (walau tidak ter-pilih otomatis), peringatan kurikulum menampilkan label manusiawi (bukan `k13`/`merdeka` mentah), warna header tabel & padding konsisten dengan halaman admin lain.

- [ ] **Step 10: Commit**

```bash
git add resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php tests/Feature/Admin/KenaikanKelasControllerTest.php
git commit -m "style(akademik): polish halaman kenaikan kelas -- banner error, penjelasan salin jadwal, opsi lewati selalu ada, label kurikulum, samakan style tabel

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 8: Regresi Penutup

**Files:**
- Tidak ada file yang dimodifikasi — task ini murni verifikasi.

**Interfaces:**
- Consumes: seluruh perubahan dari Task 1-7.
- Produces: tidak ada.

- [ ] **Step 1: Jalankan seluruh test Kenaikan Kelas**

Run: `vendor/bin/pest tests/Unit/Domains/Akademik/Actions/KenaikanKelas tests/Feature/Admin/KenaikanKelasControllerTest.php tests/Feature/Akademik/KenaikanKelasControllerUxTest.php tests/Feature/Akademik/KenaikanKelasIndicatorTest.php --compact`
Expected: semua PASS, tidak ada yang gagal.

- [ ] **Step 2: Jalankan Pint pada semua file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` (jalankan ulang sampai `passed` kalau ada auto-fix diterapkan).

- [ ] **Step 3: Jalankan full test suite proyek**

Run: `php artisan test --compact`
Expected: HANYA 3 kegagalan pre-existing yang sudah dikenal (`Tests\Unit\M3DemoDataSeederTest` x2, `Tests\Feature\Akademik\SubjekTenantValidationTest`) yang muncul. KALAU ADA kegagalan lain — STOP, jangan lanjut, laporkan detail (nama test, pesan error, stack trace) alih-alih mengasumsikan pre-existing. Perhatikan KHUSUS: jalankan test ini SENDIRIAN (bukan bersamaan dengan proses `pest`/`artisan test` lain yang sedang berjalan) — menjalankan 2 test suite bersamaan ke database test yang sama pernah menghasilkan kegagalan palsu massal akibat rebutan koneksi database di sesi-sesi sebelumnya.

- [ ] **Step 4: Verifikasi manual dev-server — rekap seluruh checklist UI dari Task 6 & 7**

Checklist ulang (boleh screenshot untuk laporan handoff): dialog konfirmasi + ringkasan real-time berfungsi (Task 6), highlight baris peringatan (Task 6), banner error tahun ajaran sumber=tujuan (Task 7), opsi "Lewati" tersedia untuk kelas berisi siswa (Task 7), label kurikulum manusiawi (Task 7), style tabel konsisten (Task 7).

- [ ] **Step 5: Verifikasi dampak data lama (informasional, BUKAN tindakan wajib task ini)**

Jalankan query informasional untuk mengetahui apakah ada siswa berstatus Lulus yang akunnya MASIH aktif dari SEBELUM perbaikan Task 1 (data lama tidak otomatis diperbaiki spec ini, lihat spec §4):
```
php artisan tinker --execute 'echo App\Models\Siswa::where("status", "lulus")->whereHas("user", fn($q) => $q->where("is_active", true))->count();'
```
Catat angkanya di laporan akhir — kalau > 0, ini informasi yang WAJIB disampaikan ke user sebagai catatan tindak lanjut manual (bukan blocker, bukan tugas yang harus dikerjakan otomatis di task ini).

- [ ] **Step 6: Commit penutup (kalau ada sisa perubahan dari Pint)**

```bash
git status
```

Kalau ada perubahan tersisa dari auto-fix Pint yang belum ter-commit:

```bash
git add -u
git commit -m "style(akademik): rapikan format Pint hasil perbaikan audit kenaikan kelas

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

Kalau working tree bersih, tidak perlu commit apa pun di step ini.

---

## Self-Review — Putaran 1 (cakupan spec + placeholder + konsistensi tipe)

**Cakupan spec**: §2.1→Task 1, §2.2→Task 2, §2.3→Task 3, §2.4→Task 4, §2.5→Task 5, §2.6→Task 6, §2.7→Task 7, §5 (pengujian)→tersebar ke tiap task + Task 8, §6 (struktur task)→diikuti persis. §3 (item di luar scope) dikonfirmasi TIDAK ADA task yang menyentuh notifikasi, halaman preview terpisah, hard-block validasi tingkat, atau `lockForUpdate()`.

**Placeholder scan**: tidak ditemukan "TBD"/"TODO" — setiap step code block berisi kode lengkap siap salin.

**Konsistensi tipe**: return value `ProsesKenaikanKelasAction::execute()` (`array{jadwalGagal, siswaNaik, siswaLulus, kelasDilewati}`) dipakai KONSISTEN — didefinisikan Task 1, dikonsumsi Task 5 dengan nama key yang sama persis. Variabel `errorTahunAjaran` didefinisikan Task 2, dikonsumsi Task 7 dengan nama sama.

## Self-Review — Putaran 2 (verifikasi terhadap kode aktual & test existing)

- Dikonfirmasi ulang `UpdateStatusSiswaAction::execute(Siswa $siswa, StatusSiswa $statusBaru): Siswa` — signature ini dipakai PERSIS sama di Task 1 (`$this->updateStatusSiswaAction->execute($siswa, StatusSiswa::Lulus)`), dibaca langsung dari file `app/Domains/Akademik/Actions/Siswa/UpdateStatusSiswaAction.php` sebelum plan ditulis.
- Dikonfirmasi ulang test existing `KenaikanKelasControllerTest.php:255-276` (`'does not carry a siswa Keluar along...'`) — Task 1 TIDAK mengubah query `Siswa::where('kelas_id', $kelasLama->id)` (hanya cara memprosesnya, dari mass-update jadi loop), jadi perilaku "siswa Keluar tidak ikut ter-query karena `kelas_id`-nya sudah null" TETAP terjaga otomatis, tidak perlu penanganan khusus tambahan.
- Dikonfirmasi ulang `tests/Feature/Admin/KenaikanKelasControllerTest.php:286-307` — Task 1 TIDAK menyentuh baris ini maupun logic yang diujinya (tidak ada validasi tingkat ditambahkan di Action manapun).
- Ditambahkan instruksi eksplisit di Task 1 Step 5 untuk menjalankan ulang SEMUA test terkait modul ini (bukan cuma file Action) sebagai jaring pengaman tambahan atas perubahan constructor signature.

## Self-Review — Putaran 3 (dependency antar-task & urutan risiko)

- **Task 1 → Task 5**: dikonfirmasi ulang Task 5 Step 3 SECARA LITERAL mengutip `$result['siswaNaik']`/`$result['siswaLulus']`/`$result['kelasDilewati']` yang baru ada setelah Task 1 — kalau dikerjakan terbalik, kode Task 5 akan merujuk key yang undefined (PHP akan warning/null, bukan error fatal, TAPI hasilnya salah senyap). Prasyarat sudah dicatat eksplisit di Task 5.
- **Task 2 → Task 3 → Task 6 → Task 7**: dikonfirmasi baris yang disentuh MASING-MASING task di `index.blade.php` (Task 2: tidak menyentuh view sama sekali, cuma controller; Task 3: 2 blok `<select>` picker tahun ajaran; Task 6: tag `<form>` + `<tr>` + tombol submit; Task 7: banner error baru + header kolom + dropdown tindakan + opsi kelas tujuan + teks peringatan + shade warna) — TIDAK saling tumpang tindih baris PERSIS, tapi Task 7 Step 6 MENULIS ULANG blok `x-data` yang sudah ditambah binding baru oleh Task 6 — task 7 HARUS baca kondisi "saat ini" versi HASIL Task 6, bukan versi asli sebelum Task 6. Sudah dicatat eksplisit di Task 7 Step 6 ("Bagian ini SAAT INI (setelah Task 6)").
- **Task 4 independen**: dikonfirmasi tidak overlap baris dengan Task 2 (beda method, `index()` vs `store()`) maupun Task 5 (Task 5 mengutip HASIL Task 4 sebagai starting point di Step 3, dicatat eksplisit "SETELAH Task 4 diterapkan").

## Self-Review — Putaran 4 (baca ulang dengan mata segar)

- Task 6 Step 3: dicek ulang binding `:data-warning` dan `:class` memakai EKSPRESI YANG SAMA PERSIS (disalin dua kali, satu untuk atribut data satu untuk class) — ini SENGAJA (Alpine tidak punya cara native membaca hasil ekspresi lain di binding berbeda tanpa memperkenalkan getter baru, dan spec eksplisit melarang mengubah getter yang sudah ada) — bukan duplikasi tidak sengaja.
- Task 7 Step 6: dicek ulang variabel baru (`kurikulumAsalLabel`, `kurikulumTujuanLabel`) TIDAK menggantikan variabel lama (`kurikulumAsal`, `kurikulumTujuan`) yang masih dipakai Task 6 punya binding `:data-warning`/`:class` — kedua pasang variabel hidup berdampingan, dicatat eksplisit sebagai catatan di akhir Step 6 ("JANGAN diganti ke variabel label").
- Dicek ulang Task 1 Step 3: import baru `use App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction;` ditambahkan di posisi alfabetis yang wajar di antara `use` statement lain — tidak mengganggu import lain yang sudah ada.
- Dicek ulang Task 8 Step 3: instruksi eksplisit "jalankan SENDIRIAN, bukan bersamaan dengan proses lain" — pelajaran dari insiden nyata di sesi ini sebelumnya (Pengadaan review) di mana 2 proses test paralel menghasilkan kegagalan palsu masif. Dicantumkan supaya tidak terulang.

## Self-Review — Putaran 5 (baca ulang final, cek referensi silang & kelengkapan test plan)

- Dicek ulang §5 spec (Pengujian yang Dibutuhkan) baris per baris terhadap task yang mengimplementasikannya: test `StudentUpdatedClass` event (Task 1 Step 1, test ketiga) — spec menyarankan `Event::fake()`+`Event::assertDispatched()` sebagai salah satu opsi, plan MEMILIH opsi itu secara eksplisit (bukan assert efek tagihan langsung, yang lebih mahal setup-nya) — keputusan implementasi yang konsisten dengan preferensi spec ("pilih pendekatan mana yang lebih murah/stabil").
- Dicek ulang Task 8 Step 5 (query dampak data lama) — SESUAI §4 spec yang eksplisit menyebutkan ini sebagai catatan informasional, BUKAN tindakan perbaikan data yang harus dilakukan otomatis — plan sudah benar menandainya "informasional, BUKAN tindakan wajib".
- Dicek ulang total 8 task vs 8 di §6 spec — jumlah dan urutan cocok persis, tidak ada yang tertukar posisi atau hilang.
- Dicek ulang SEMUA nama file test yang dirujuk (`ProsesKenaikanKelasActionTest.php`, `KenaikanKelasControllerTest.php`, `KenaikanKelasControllerUxTest.php`, `KenaikanKelasIndicatorTest.php`) — keempatnya DIKONFIRMASI ADA lewat pembacaan langsung sebelum plan ditulis (3 dari 4 dibaca penuh isinya, `KenaikanKelasIndicatorTest.php` dikonfirmasi ada lewat listing file tapi tidak dibaca detail — TIDAK ada task yang menambah test ke file itu, jadi aman, hanya disertakan di baris "jalankan test" sebagai bagian regresi).
