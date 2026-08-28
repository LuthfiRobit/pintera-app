# Fix: ruangan_id Lolos Tanpa Cross-Check Lembaga pada Jadwal Pelajaran — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `JadwalPelajaranController::store()` dan `::update()` harus menolak `ruangan_id` yang bukan milik lembaga kelas terkait (kecuali ruangan `is_shared`), mirror pola cross-check yang sudah ada untuk `guru_id`/`mata_pelajaran_id`.

**Architecture:** Tambah 1 blok pengecekan eksplisit di `store()` (sebelum baris pembentukan `$ruanganId` lama) dan di `update()` (sebelum baris pembentukan `$ruanganId` lama): query `Ruangan::withoutGlobalScope(TenantScope::class)->find(...)`, tolak kalau tidak ditemukan atau `lembaga_id`-nya beda dari `$kelas->lembaga_id` DAN bukan `is_shared`. Tidak ada perubahan skema, tidak ada guard baru di action layer.

**Tech Stack:** Laravel 12, PHP 8.3, Pest v4.

## Global Constraints

- Fix HANYA mengubah `app/Http/Controllers/Admin/JadwalPelajaranController.php` (2 blok baru di `store()` dan `update()`, tidak ada import baru — `Ruangan` dan `TenantScope` sudah di-import di file ini).
- Tidak mengubah `CreateJadwalPelajaranAction.php`, `UpdateJadwalPelajaranAction.php`, `ValidateRoomClashAction.php`, atau dropdown/filter UI di `create()`/`edit()`.
- Ruangan dengan `is_shared = true` HARUS tetap diterima meski `lembaga_id`-nya beda dari kelas — ini bukan bug, itu fitur ruangan bersama antar-lembaga.
- Semua test existing di `tests/Feature/Admin/JadwalPelajaranCrudTest.php` WAJIB tetap PASS tanpa modifikasi assertion apa pun (tidak ada satupun test existing yang mengirim `ruangan_id` di payload-nya, jadi fix ini seharusnya tidak menyentuh jalur test manapun yang sudah ada — validasi ini hanya untuk memastikan).
- Hanya jalankan test scoped: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`. TIDAK PERLU full suite untuk fix sekecil ini.

---

### Task 1: Cross-Check `ruangan_id` di `store()` dan `update()` + Test Reproduksi & Regresi

**Files:**
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php`
- Modify: `tests/Feature/Admin/JadwalPelajaranCrudTest.php`

**Interfaces:**
- Consumes: `App\Domains\Sarpras\Models\Ruangan` (fillable: `yayasan_id`, `lembaga_id`, `gedung_id`, `kode_ruangan`, `nama_ruangan`, `lantai`, `jenis_ruangan`, `kapasitas_siswa`, `luas_m2`, `penanggung_jawab_guru_id`, `is_shared` bool, `is_aktif` bool), pakai trait `BelongsToTenant` (jadi wajib `withoutGlobalScope(TenantScope::class)` untuk query lintas-lembaga secara sengaja). `App\Models\Scopes\TenantScope` — sudah dipakai identik di `edit()` baris 288 untuk query `ruanganList`.
- Produces: `store()`/`update()` tetap mengembalikan `RedirectResponse|JsonResponse` — signature method tidak berubah, hanya menambah 1 jalur early-return error baru di masing-masing.

- [x] **Step 1: Baca baseline `store()` dan `update()` untuk memastikan tidak ada drift**

Baseline `store()` (baris 175-273 di `app/Http/Controllers/Admin/JadwalPelajaranController.php`), bagian yang relevan (baris 209-219 saat ini):

```php
        if ($semester->lembaga_id !== $kelas->lembaga_id) {
            $msg = 'Semester harus berasal dari lembaga yang sama dengan kelas ini.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['semester_id' => [$msg]]], 422);
            }
            return back()->withErrors(['semester_id' => $msg])->withInput();
        }

        $ruanganId = ! empty($data['ruangan_id']) ? (int) $data['ruangan_id'] : $kelas->ruangan_id;

        $jamPelajaranIds = array_unique($data['jam_pelajaran_id']);
```

Baseline `update()` (baris 309-372), bagian yang relevan (baris 331-344 saat ini):

```php
        if (! empty($data['mata_pelajaran_id'])) {
            $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
            if (! $mataPelajaran || $mataPelajaran->lembaga_id !== $kelas->lembaga_id) {
                $msg = 'Mata pelajaran harus berasal dari lembaga yang sama dengan kelas ini.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['mata_pelajaran_id' => [$msg]]], 422);
                }
                return back()->withErrors(['mata_pelajaran_id' => $msg])->withInput();
            }
        }

        $ruanganId = ! empty($data['ruangan_id']) ? (int) $data['ruangan_id'] : ($jadwalPelajaran->ruangan_id ?? $kelas->ruangan_id);

        $dto = new JadwalPelajaranData(
```

Jika file di repo berbeda dari baseline ini, STOP dan laporkan sebelum melanjutkan.

- [x] **Step 2: Tulis test yang gagal (reproduksi bug pada store dan update, plus regresi is_shared)**

**PENTING — `Ruangan` TIDAK punya factory** (`database/factories/RuanganFactory.php` tidak ada, meski model pakai trait `HasFactory`). Ruangan test dibuat lewat `Ruangan::create()` langsung, dengan `Gedung::create()` dulu sebagai parent wajib — pola ini persis diambil dari `tests/Feature/Akademik/JadwalSarprasCollisionTest.php:32-57,184-209` yang sudah teruji jalan.

Tambahkan `use App\Domains\Sarpras\Models\Gedung;` dan `use App\Domains\Sarpras\Models\Ruangan;` ke bagian `use` di puncak `tests/Feature/Admin/JadwalPelajaranCrudTest.php` (cek dulu belum ada, baru tambahkan — jangan duplikat import).

Tambahkan helper berikut dan 4 test baru di akhir file (setelah test terakhir):

```php
function buatRuanganUntukLembaga(Yayasan $yayasan, Lembaga $lembaga, string $kode, bool $isShared = false): Ruangan
{
    $gedung = Gedung::create([
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga->id,
        'nama_gedung' => "Gedung {$kode}",
        'kode_gedung' => "GD-{$kode}",
    ]);

    return Ruangan::create([
        'yayasan_id' => $yayasan->id,
        'lembaga_id' => $lembaga->id,
        'gedung_id' => $gedung->id,
        'nama_ruangan' => "Ruangan {$kode}",
        'kode_ruangan' => "RG-{$kode}",
        'is_shared' => $isShared,
        'is_aktif' => true,
    ]);
}

it('rejects a ruangan_id belonging to another lembaga on store', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id, 'pola_jam_id' => $polaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $ruanganB = buatRuanganUntukLembaga($yayasan, $lembagaB, 'B-TIDAK-SHARED', false);
    $manager = actingAsJadwalManager($lembagaA);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => [$jamA->id],
        'guru_id' => $guruA->id,
        'semester_id' => $semesterA->id,
        'ruangan_id' => $ruanganB->id,
    ])->assertSessionHasErrors('ruangan_id');

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->exists())->toBeFalse();
});

it('accepts a shared ruangan_id from another lembaga on store', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranA = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semesterA = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranA->id]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id, 'pola_jam_id' => $polaA->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true]);
    $guruA = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $ruanganBersama = buatRuanganUntukLembaga($yayasan, $lembagaB, 'B-SHARED', true);
    $manager = actingAsJadwalManager($lembagaA);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasA->id,
        'jam_pelajaran_id' => [$jamA->id],
        'guru_id' => $guruA->id,
        'semester_id' => $semesterA->id,
        'ruangan_id' => $ruanganBersama->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelasA->id, 'semester_id' => $semesterA->id]));

    expect(JadwalPelajaran::where('kelas_id', $kelasA->id)->where('ruangan_id', $ruanganBersama->id)->exists())->toBeTrue();
});

it('accepts a ruangan_id from the same lembaga on store', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $ruangan = buatRuanganUntukLembaga($yayasan, $lembaga, 'SAMA', false);
    $manager = actingAsJadwalManager($lembaga);

    $this->actingAs($manager)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelas->id,
        'jam_pelajaran_id' => [$jam->id],
        'guru_id' => $guru->id,
        'semester_id' => $semester->id,
        'ruangan_id' => $ruangan->id,
    ])->assertRedirect(route('admin.jadwal-pelajaran.index', ['kelas_id' => $kelas->id, 'semester_id' => $semester->id]));

    expect(JadwalPelajaran::where('kelas_id', $kelas->id)->where('ruangan_id', $ruangan->id)->exists())->toBeTrue();
});

it('rejects updating ruangan_id to a ruangan from another lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaA = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaB = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsJadwalManager($lembagaA);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaA->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembagaA->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembagaA->id]);
    $jadwal = JadwalPelajaran::factory()->create(['kelas_id' => $kelas->id, 'jam_pelajaran_id' => $jam->id, 'guru_id' => $guru->id, 'semester_id' => $semester->id, 'ruangan_id' => null]);
    $ruanganB = buatRuanganUntukLembaga($yayasan, $lembagaB, 'UPDATE-B', false);

    $this->actingAs($manager)->put(route('admin.jadwal-pelajaran.update', $jadwal), [
        'jam_pelajaran_id' => $jam->id,
        'guru_id' => $guru->id,
        'ruangan_id' => $ruanganB->id,
    ])->assertSessionHasErrors('ruangan_id');

    expect($jadwal->fresh()->ruangan_id)->toBeNull();
});
```

- [x] **Step 3: Jalankan test untuk memastikan reproduksi bug GAGAL (bug masih ada)**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --filter="rejects a ruangan_id belonging to another lembaga on store" --compact`
Expected: FAIL — `assertSessionHasErrors('ruangan_id')` gagal karena request tersimpan sukses (redirect 302 tanpa errors) alih-alih ditolak.

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --filter="rejects updating ruangan_id to a ruangan from another lembaga" --compact`
Expected: FAIL — sama, karena update tersimpan sukses.

Run 2 test lain (`accepts a shared ruangan_id...`, `accepts a ruangan_id from the same lembaga...`) dan pastikan itu PASS dari awal (baseline aman, bukan bukti bug):
Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --filter="accepts a" --compact`
Expected: PASS untuk keduanya.

- [x] **Step 4: Implementasi minimal fix di `store()`**

Edit `app/Http/Controllers/Admin/JadwalPelajaranController.php`, tepat setelah blok pengecekan `semester_id` (baris 209-215 lama) dan sebelum baris pembentukan `$ruanganId` (baris 217 lama):

```php
        if ($semester->lembaga_id !== $kelas->lembaga_id) {
            $msg = 'Semester harus berasal dari lembaga yang sama dengan kelas ini.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['semester_id' => [$msg]]], 422);
            }
            return back()->withErrors(['semester_id' => $msg])->withInput();
        }

        if (! empty($data['ruangan_id'])) {
            $ruangan = Ruangan::withoutGlobalScope(TenantScope::class)->find((int) $data['ruangan_id']);
            if (! $ruangan || (! $ruangan->is_shared && $ruangan->lembaga_id !== $kelas->lembaga_id)) {
                $msg = 'Ruangan harus berasal dari lembaga yang sama dengan kelas ini, atau berupa ruangan bersama.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['ruangan_id' => [$msg]]], 422);
                }
                return back()->withErrors(['ruangan_id' => $msg])->withInput();
            }
        }

        $ruanganId = ! empty($data['ruangan_id']) ? (int) $data['ruangan_id'] : $kelas->ruangan_id;

        $jamPelajaranIds = array_unique($data['jam_pelajaran_id']);
```

- [x] **Step 5: Implementasi minimal fix di `update()`**

Edit `app/Http/Controllers/Admin/JadwalPelajaranController.php`, tepat setelah blok pengecekan `mata_pelajaran_id` (baris 331-340 lama) dan sebelum baris pembentukan `$ruanganId` (baris 342 lama):

```php
        if (! empty($data['mata_pelajaran_id'])) {
            $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);
            if (! $mataPelajaran || $mataPelajaran->lembaga_id !== $kelas->lembaga_id) {
                $msg = 'Mata pelajaran harus berasal dari lembaga yang sama dengan kelas ini.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['mata_pelajaran_id' => [$msg]]], 422);
                }
                return back()->withErrors(['mata_pelajaran_id' => $msg])->withInput();
            }
        }

        if (! empty($data['ruangan_id'])) {
            $ruangan = Ruangan::withoutGlobalScope(TenantScope::class)->find((int) $data['ruangan_id']);
            if (! $ruangan || (! $ruangan->is_shared && $ruangan->lembaga_id !== $kelas->lembaga_id)) {
                $msg = 'Ruangan harus berasal dari lembaga yang sama dengan kelas ini, atau berupa ruangan bersama.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['ruangan_id' => [$msg]]], 422);
                }
                return back()->withErrors(['ruangan_id' => $msg])->withInput();
            }
        }

        $ruanganId = ! empty($data['ruangan_id']) ? (int) $data['ruangan_id'] : ($jadwalPelajaran->ruangan_id ?? $kelas->ruangan_id);

        $dto = new JadwalPelajaranData(
```

`Ruangan` (`App\Domains\Sarpras\Models\Ruangan`) dan `TenantScope` (`App\Models\Scopes\TenantScope`) sudah di-import di puncak file (baris 11 dan 21) — tidak perlu import baru.

- [x] **Step 6: Jalankan seluruh file test dan pastikan semua PASS**

Run: `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`
Expected: PASS untuk seluruh test di file ini (baseline + 4 test baru).

Jika ada test lain di file ini yang FAIL, cek dulu apakah kegagalan itu terkait fix sebelum melanjutkan — laporkan sebagai temuan BLOCKED jika ditemukan, jangan diam-diam mengubah assertion test existing.

- [x] **Step 7: Jalankan Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [x] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/JadwalPelajaranController.php tests/Feature/Admin/JadwalPelajaranCrudTest.php
git commit -m "fix(akademik): cross-check ruangan_id vs lembaga kelas pada jadwal pelajaran"
```

---

## Self-Review

**1. Spec coverage:**
- §2 Keputusan Desain (blok cross-check di store() dan update(), pengecualian is_shared, gunakan withoutGlobalScope) → Task 1 Step 4 dan Step 5. ✅
- §3 Non-Goals (tidak mengubah action layer/ValidateRoomClashAction/dropdown UI, `$kelas->ruangan_id` fallback tidak divalidasi ulang) → tidak ada step yang menyentuh file-file itu; fallback `$kelas->ruangan_id` tetap dipakai apa adanya tanpa validasi tambahan di kedua step fix. ✅
- §4.1 Regresi wajib → Global Constraints + Task 1 Step 6 eksplisit melarang modifikasi assertion existing. ✅
- §4.2 Bug reproduction (store dan update, plus pengecualian is_shared) → Task 1 Step 2, 4 test baru mencakup keduanya. ✅
- §4.3 Kasus tidak berubah (ruangan sama lembaga tetap sukses) → test `accepts a ruangan_id from the same lembaga on store`. ✅
- §5 Ringkasan file → cocok dengan Task 1 Files. ✅

**2. Placeholder scan:** Tidak ada TBD/TODO. Semua kode test dan implementasi lengkap.

**3. Type consistency:** `store()`/`update()` signature tidak berubah. `Ruangan::withoutGlobalScope(TenantScope::class)->find(...)` dipakai identik di kedua step, konsisten dengan pola yang sudah ada di `edit()` baris 288. Test baru memakai helper `buatRuanganUntukLembaga()` berbasis `Ruangan::create()`/`Gedung::create()` (bukan factory, karena `RuanganFactory` tidak ada) — sudah diverifikasi cocok dengan kolom yang dipakai di `JadwalSarprasCollisionTest.php` yang sudah teruji jalan di codebase ini.

---

## Konteks Tambahan untuk Kickoff

- Route `admin.jadwal-pelajaran.store`/`.update` mengarah ke `JadwalPelajaranController` — `guru_id`/`mata_pelajaran_id`/`semester_id` SUDAH divalidasi cross-lembaga sejak awal (lihat baris 188-215 untuk store, 323-340 untuk update) — fix ini murni melengkapi pola yang sama untuk `ruangan_id` yang tertinggal.
- Bug ini reachable oleh admin LEMBAGA BIASA (bukan cuma aktor yayasan) — beda dari 2 bug sebelumnya (KurikulumAssignment, UpdateKomponenPenilaianAction) yang hanya reachable aktor yayasan mode "Semua Lembaga". Jangan salah asumsi bahwa semua test reproduksi harus pakai aktor yayasan — di sini `actingAsJadwalManager()` (aktor lembaga biasa) sudah cukup untuk membuktikan bug.
