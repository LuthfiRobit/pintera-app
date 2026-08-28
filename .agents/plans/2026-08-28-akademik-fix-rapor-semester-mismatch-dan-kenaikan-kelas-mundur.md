# Fix: Rapor Semester-TahunAjaran Mismatch & Kenaikan Kelas Mundur — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** (1) `Guru\RaporController` harus menolak `semester_id` yang bukan milik tahun ajaran kelas siswa/kelas yang bersangkutan. (2) `ProsesKenaikanKelasAction` harus menolak kenaikan kelas ke tahun ajaran yang tanggal mulainya lebih awal (mundur) dari tahun ajaran kelas asal.

**Architecture:** 2 task independen (beda file, beda area, tidak saling bergantung) dalam 1 plan karena digabung dalam satu siklus fix atas persetujuan user. Task 1: tambah 4 `abort_if` di `Guru\RaporController`, mirror pola `Admin\RaporController::cetak()`. Task 2: tambah 1 pengecekan `tanggal_mulai` di `ProsesKenaikanKelasAction::execute()`, memakai operator `<` (strict) — bukan `<=` — karena `TahunAjaranFactory` default ke `now()` tanpa variasi untuk semua baris, dan kolom `tanggal_mulai` bertipe `date`.

**Tech Stack:** Laravel 12, PHP 8.3, Pest v4.

## Global Constraints

- Task 1 HANYA mengubah `app/Http/Controllers/Guru/RaporController.php` (4 baris `abort_if` baru, tidak ada import baru — `Semester` sudah di-import).
- Task 2 HANYA mengubah `app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php` (+1 blok pengecekan, +1 import `App\Models\TahunAjaran`).
- Guard existing di kedua file (cross-lembaga di `ProsesKenaikanKelasAction` baris 47/57, guard `wali_kelas_guru_id` di `RaporController`) TIDAK BOLEH diubah/dihapus.
- Task 2 WAJIB pakai operator `<` (strict), BUKAN `<=` — lihat penjelasan di Task 2 Step 1.
- Semua test existing di kedua file test yang disebut di bawah WAJIB tetap PASS tanpa modifikasi assertion apa pun.
- Hanya jalankan test scoped per task: `php artisan test tests/Feature/Guru/RaporControllerTest.php --compact` untuk Task 1, `php artisan test tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php --compact` untuk Task 2. TIDAK PERLU full suite untuk fix sekecil ini.

---

### Task 1: Cross-Check Semester vs Tahun Ajaran Kelas di `Guru\RaporController`

**Files:**
- Modify: `app/Http/Controllers/Guru/RaporController.php`
- Modify: `tests/Feature/Guru/RaporControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Semester` (properti `tahun_ajaran_id`), `App\Models\Kelas` (properti `tahun_ajaran_id`) — keduanya sudah dipakai di file ini, tidak ada import baru.
- Produces: `edit()`, `generateNarasi()`, `ajukan()`, `cetak()` tetap return type yang sama — hanya menambah 1 jalur `abort_if` baru (404) di masing-masing.

- [ ] **Step 1: Baca baseline 4 method untuk memastikan tidak ada drift**

Baseline `edit()` (baris 121-154), bagian relevan (baris 129-133 saat ini):
```php
        $semesterId = (int) $request->query('semester_id');
        abort_if($semesterId === 0, 404, 'Konteks semester wajib disertakan untuk membuka form catatan wali kelas.');
        $semester = Semester::find($semesterId);
        abort_if($semester === null, 404);

        $catatan = CatatanWaliKelas::where('siswa_id', $siswa->id)->where('semester_id', $semester->id)->first()
```

Baseline `generateNarasi()` (baris 178-192), bagian relevan (baris 186-189 saat ini):
```php
        $semester = Semester::find((int) $request->query('semester_id'));
        abort_if($semester === null, 404);

        $narasi = $this->generateNarasiPerkembanganAction->execute($siswa, $siswa->kelas, $semester);
```

Baseline `ajukan()` (baris 194-211), bagian relevan (baris 199-206 saat ini):
```php
        $kelas = Kelas::find($request->validated('kelas_id'));
        abort_if($kelas === null, 404);
        abort_unless($kelas->wali_kelas_guru_id === $guru->id, 403);

        $semester = Semester::find($request->validated('semester_id'));
        abort_if($semester === null, 404);

        $this->submitPengajuanRaporAction->execute($kelas, $semester, $request->user());
```

Baseline `cetak()` (baris 213-230), bagian relevan (baris 221-225 saat ini):
```php
        $semester = Semester::find((int) $request->query('semester_id'));
        abort_if($semester === null, 404);

        $data = $this->raporPdfDataBuilder->build($siswa, $semester);
```

Jika file di repo berbeda dari baseline ini, STOP dan laporkan sebelum melanjutkan.

- [ ] **Step 2: Tulis test yang gagal (reproduksi bug di 4 method + regresi negatif)**

Tambahkan di akhir `tests/Feature/Guru/RaporControllerTest.php` (setelah test terakhir di file):

```php
it('rejects opening the edit form when semester_id belongs to a different tahun ajaran than the siswa kelas', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'lembaga' => $lembaga] = siapkanWaliKelasUntukRapor();
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);

    $this->actingAs($guruUser)
        ->get(route('guru.rapor.catatan.edit', ['siswa' => $siswa->id, 'semester_id' => $semesterLain->id]))
        ->assertNotFound();
});

it('rejects generating narasi when semester_id belongs to a different tahun ajaran than the siswa kelas', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'lembaga' => $lembaga] = siapkanWaliKelasUntukRapor();
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);

    $this->actingAs($guruUser)
        ->post(route('guru.rapor.catatan.generate-narasi', ['siswa' => $siswa->id, 'semester_id' => $semesterLain->id]))
        ->assertNotFound();
});

it('rejects submitting a pengajuan when semester_id belongs to a different tahun ajaran than the kelas', function () {
    ['guruUser' => $guruUser, 'kelas' => $kelas, 'lembaga' => $lembaga] = siapkanWaliKelasUntukRapor();
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);

    $this->actingAs($guruUser)
        ->post(route('guru.rapor.pengajuan.submit'), ['kelas_id' => $kelas->id, 'semester_id' => $semesterLain->id])
        ->assertNotFound();
});

it('rejects printing a pdf when semester_id belongs to a different tahun ajaran than the siswa kelas', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa, 'lembaga' => $lembaga] = siapkanWaliKelasUntukRapor();
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);

    $this->actingAs($guruUser)
        ->get(route('guru.rapor.cetak', ['siswa' => $siswa->id, 'semester_id' => $semesterLain->id]))
        ->assertNotFound();
});
```

`siapkanWaliKelasUntukRapor()` sudah mengembalikan `lembaga` di array-nya (lihat baris 44 file existing: `return compact('guruUser', 'guru', 'kelas', 'siswa', 'lembaga', 'yayasan', 'tahunAjaran', 'semester');`), jadi tidak perlu setup tambahan.

- [ ] **Step 3: Jalankan test untuk memastikan reproduksi bug GAGAL (bug masih ada)**

Run: `php artisan test tests/Feature/Guru/RaporControllerTest.php --filter="rejects opening the edit form when semester_id belongs to a different tahun ajaran" --compact`
Expected: FAIL — `assertNotFound()` gagal karena response sukses (200) alih-alih 404.

Run juga untuk 3 test lain (`rejects generating narasi...`, `rejects submitting a pengajuan...`, `rejects printing a pdf...`) — semua harus FAIL di titik ini.

- [ ] **Step 4: Implementasi minimal fix**

Edit `app/Http/Controllers/Guru/RaporController.php`:

`edit()` — tepat setelah `abort_if($semester === null, 404);` (baris 132 lama):
```php
        $semester = Semester::find($semesterId);
        abort_if($semester === null, 404);
        abort_if($semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404);

        $catatan = CatatanWaliKelas::where('siswa_id', $siswa->id)->where('semester_id', $semester->id)->first()
```

`generateNarasi()` — tepat setelah `abort_if($semester === null, 404);` (baris 187 lama):
```php
        $semester = Semester::find((int) $request->query('semester_id'));
        abort_if($semester === null, 404);
        abort_if($semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404);

        $narasi = $this->generateNarasiPerkembanganAction->execute($siswa, $siswa->kelas, $semester);
```

`ajukan()` — tepat setelah `abort_if($semester === null, 404);` (baris 204 lama):
```php
        $semester = Semester::find($request->validated('semester_id'));
        abort_if($semester === null, 404);
        abort_if($semester->tahun_ajaran_id !== $kelas->tahun_ajaran_id, 404);

        $this->submitPengajuanRaporAction->execute($kelas, $semester, $request->user());
```

`cetak()` — tepat setelah `abort_if($semester === null, 404);` (baris 222 lama):
```php
        $semester = Semester::find((int) $request->query('semester_id'));
        abort_if($semester === null, 404);
        abort_if($semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404);

        $data = $this->raporPdfDataBuilder->build($siswa, $semester);
```

- [ ] **Step 5: Jalankan seluruh file test dan pastikan semua PASS**

Run: `php artisan test tests/Feature/Guru/RaporControllerTest.php --compact`
Expected: PASS untuk seluruh test di file ini (baseline + 4 test baru).

Jika ada test lain yang FAIL, laporkan sebagai temuan BLOCKED — jangan diam-diam mengubah assertion existing.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Guru/RaporController.php tests/Feature/Guru/RaporControllerTest.php
git commit -m "fix(akademik): cross-check semester vs tahun ajaran kelas pada Guru RaporController"
```

---

### Task 2: Validasi Arah Waktu Tahun Ajaran pada Kenaikan Kelas

**Files:**
- Modify: `app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php`
- Modify: `tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php`

**Interfaces:**
- Consumes: `App\Models\TahunAjaran::findOrFail(int $id): TahunAjaran` (properti `tanggal_mulai`, cast `date`).
- Produces: `ProsesKenaikanKelasAction::execute()` tetap `(KenaikanKelasData $data): array{jadwalGagal: array<int,string>}` — signature tidak berubah, hanya menambah 1 kondisi baru yang bisa throw `\DomainException` (exception class yang sama dengan guard existing, tidak ada exception baru).

- [ ] **Step 1: Baca baseline `execute()` untuk memastikan tidak ada drift**

Baseline (baris 29-66 di `app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php`), bagian relevan (baris 46-53 saat ini):

```php
                $kelasBaru = Kelas::find($aksi['kelas_baru_id']);
                abort_if($kelasBaru === null || $kelasBaru->lembaga_id !== $kelasLama->lembaga_id, 404);

                if ($kelasBaru->tahun_ajaran_id === $kelasLama->tahun_ajaran_id) {
                    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" masih berada di tahun ajaran yang sama dengan kelas asal \"{$kelasLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                }

                Siswa::where('kelas_id', $kelasLama->id)->update(['kelas_id' => $kelasBaru->id]);
```

Jika file di repo berbeda dari baseline ini, STOP dan laporkan sebelum melanjutkan.

**PENTING — kenapa pakai `<` bukan `<=`**: `tahun_ajaran.tanggal_mulai` adalah kolom `date` (`database/migrations/2026_07_12_100820_create_tahun_ajaran_table.php:18`). `TahunAjaranFactory` (`database/factories/TahunAjaranFactory.php:20`) SELALU default `tanggal_mulai` ke `now()` untuk SEMUA baris tanpa variasi. Test existing `promotes siswa to the destination kelas...` dan `skips a jadwal row that clashes...` di `ProsesKenaikanKelasActionTest.php` membuat `$tahunLama`/`$tahunBaru` (dan `$semesterTujuan`'s parent tahun ajaran) lewat factory default — tanggal-nya akan SAMA PERSIS (hari yang sama). Kalau pengecekan baru pakai `<=`, kedua test itu akan mulai gagal (dianggap "tidak lebih baru" padahal cuma kebetulan sama hari saat test dijalankan). WAJIB pakai `<` (strict) supaya hanya kasus BENAR-BENAR mundur (tanggal lebih awal) yang ditolak, dan kasus "kebetulan sama hari" tetap lolos seperti perilaku existing.

- [ ] **Step 2: Tulis test yang gagal (reproduksi bug mundur + regresi negatif "tanggal sama")**

Tambahkan di akhir `tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php` (setelah test terakhir di file):

```php
it('throws a DomainException when kelas tujuan is in a tahun ajaran with an earlier tanggal_mulai than kelas lama', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'tanggal_mulai' => '2026-07-01']);
    $tahunMundur = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'tanggal_mulai' => '2025-07-01']);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasMundur = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunMundur->id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelasLama->id]);

    expect(fn () => buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasMundur->id, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ])))->toThrow(\DomainException::class);

    expect($siswa->fresh()->kelas_id)->toBe($kelasLama->id);
});

it('promotes siswa when tahun ajaran tujuan has a later tanggal_mulai than kelas lama', function () {
    $lembaga = Lembaga::factory()->create();
    $tahunLama = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'tanggal_mulai' => '2025-07-01']);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'tanggal_mulai' => '2026-07-01']);
    $kelasLama = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLama->id]);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelasLama->id]);

    buatKenaikanAction()->execute(new KenaikanKelasData(mapping: [
        $kelasLama->id => ['tindakan' => 'naik', 'kelas_baru_id' => $kelasBaru->id, 'salin_jadwal' => false, 'semester_tujuan_id' => null],
    ]));

    expect($siswa->fresh()->kelas_id)->toBe($kelasBaru->id);
});
```

- [ ] **Step 3: Jalankan test untuk memastikan reproduksi bug GAGAL (bug masih ada)**

Run: `php artisan test tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php --filter="throws a DomainException when kelas tujuan is in a tahun ajaran with an earlier tanggal_mulai" --compact`
Expected: FAIL — `toThrow(\DomainException::class)` gagal karena tidak ada exception yang dilempar (siswa berhasil pindah kelas ke tahun ajaran yang lebih mundur).

Run juga test `promotes siswa when tahun ajaran tujuan has a later tanggal_mulai...` dan pastikan itu PASS dari awal (baseline aman, bukan bukti bug).

- [ ] **Step 4: Implementasi minimal fix**

Edit `app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php`:

Tambah import setelah `use App\Models\Siswa;` (baris 14 lama):
```php
use App\Models\Siswa;
use App\Models\TahunAjaran;
```

Tambah blok pengecekan tepat setelah guard "tahun ajaran sama" (baris 49-51 lama) dan sebelum `Siswa::where(...)->update(...)` (baris 53 lama):
```php
                if ($kelasBaru->tahun_ajaran_id === $kelasLama->tahun_ajaran_id) {
                    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" masih berada di tahun ajaran yang sama dengan kelas asal \"{$kelasLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                }

                $tahunAjaranLama = TahunAjaran::findOrFail($kelasLama->tahun_ajaran_id);
                $tahunAjaranBaru = TahunAjaran::findOrFail($kelasBaru->tahun_ajaran_id);

                if ($tahunAjaranBaru->tanggal_mulai < $tahunAjaranLama->tanggal_mulai) {
                    throw new \DomainException("Kelas tujuan \"{$kelasBaru->nama}\" berada di tahun ajaran \"{$tahunAjaranBaru->nama}\" yang lebih lama dari tahun ajaran kelas asal \"{$tahunAjaranLama->nama}\". Pilih kelas tujuan dari tahun ajaran berikutnya.");
                }

                Siswa::where('kelas_id', $kelasLama->id)->update(['kelas_id' => $kelasBaru->id]);
```

- [ ] **Step 5: Jalankan seluruh file test dan pastikan semua PASS**

Run: `php artisan test tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php --compact`
Expected: PASS untuk seluruh test di file ini (3 test baseline existing + 2 test baru).

Jika ada test lain yang FAIL, laporkan sebagai temuan BLOCKED — jangan diam-diam mengubah assertion existing.

- [ ] **Step 6: Jalankan regresi di 2 file test terkait lain (disebut di spec §4.1)**

Run: `php artisan test tests/Feature/Admin/KenaikanKelasControllerTest.php tests/Feature/Akademik/KenaikanKelasControllerUxTest.php --compact`
Expected: PASS semua — file-file ini tidak dimodifikasi tapi memakai `ProsesKenaikanKelasAction` secara tidak langsung lewat controller, jadi perlu diverifikasi tidak ada regresi tersembunyi.

- [ ] **Step 7: Jalankan Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasAction.php tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php
git commit -m "fix(akademik): validasi tahun ajaran tujuan kenaikan kelas tidak boleh mundur"
```

---

## Self-Review

**1. Spec coverage:**
- Temuan 1 (§2 Fix Temuan 1: 4 abort_if) → Task 1 Step 4, keempat method tercover. ✅
- Temuan 2 (§2 Fix Temuan 2: validasi tanggal_mulai, pakai `<` bukan `<=`, guard existing dipertahankan) → Task 2 Step 4. ✅
- §3 Non-Goals (tidak ubah Admin\RaporController, tidak ubah guard cross-lembaga existing, tidak ada lock/idempotency baru, tidak ubah action/service lain, tidak ada window cut-off presensi) → tidak ada task yang menyentuh area-area itu. ✅
- §4.1 Regresi wajib → Task 1 Step 5, Task 2 Step 5 + Step 6 (regresi di 2 file test terkait lain). ✅
- §4.2/§4.3 (Temuan 1 reproduksi + regresi negatif) → Task 1 Step 2 (4 test baru) + test existing yang sudah pakai semester sama tahun ajaran (misal `streams a pdf for a siswa the guru is wali kelas of`, baris 216-223 existing) sebagai regresi negatif implisit. ✅
- §4.4/§4.5 (Temuan 2 reproduksi + 2 varian regresi negatif: tahun sama ID, tahun beda ID tapi tanggal sama) → Task 2 Step 2, mencakup 2 test baru (mundur → throw, lebih baru → sukses) + test existing `throws...same tahun ajaran` (ID sama, tidak diubah) + test existing `promotes siswa...`/`skips a jadwal row...` (tanggal sama, ID beda, harus tetap sukses karena `<` bukan `<=`). ✅
- §5 Ringkasan file → cocok dengan Task 1 & 2 Files. ✅

**2. Placeholder scan:** Tidak ada TBD/TODO. Semua kode test dan implementasi lengkap.

**3. Type consistency:** `Guru\RaporController` method signatures tidak berubah. `ProsesKenaikanKelasAction::execute()` signature tidak berubah (tetap `array{jadwalGagal: array<int,string>}`), exception class tetap `\DomainException` yang sama dipakai guard existing — tidak ada inkonsistensi.

---

## Konteks Tambahan untuk Kickoff

- Task 1 dan Task 2 independen sepenuhnya (file berbeda, tidak saling bergantung) — bisa dikerjakan dalam urutan apapun, tapi plan ini menulisnya berurutan (Task 1 dulu) untuk kejelasan commit history.
- Task 2 punya jebakan halus yang HARUS diperhatikan: kolom `date` + factory default `now()` tanpa variasi bisa membuat implementer salah pakai `<=` kalau tidak membaca catatan "PENTING" di Task 2 Step 1. Ini BUKAN kesalahan pemahaman bisnis, murni detail teknis fixture yang mudah terlewat.
- Referensi pola yang di-mirror di Task 1: `app/Http/Controllers/Admin/RaporController.php:107` — `abort_if($selectedSemester->tahun_ajaran_id !== $selectedKelas->tahun_ajaran_id, 404);`.
