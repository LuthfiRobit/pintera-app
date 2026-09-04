# Spec: Perbaikan Audit Akademik Putaran 4 (Root-Fix TenantScope, Session-Staleness Lanjutan, Race Condition Jadwal)

**Tanggal**: 2026-09-04
**Branch**: `akademik-v2`
**Konteks**: Ditemukan lewat audit putaran 4 modul Akademik (dilakukan setelah paket billing-trigger/RPP-guru/race-condition-Komponen-Penilaian/session-staleness-3-controller/dll pada putaran 3 selesai). Fokus: verifikasi ulang perbaikan-perbaikan sebelumnya + scan baru untuk pola yang sama di titik yang belum tersentuh.

## 1. Keputusan yang Sudah Final (dikonfirmasi lewat brainstorming, jangan tanya ulang)

- **Cakupan final**: Bagian 1 (root-fix `TenantScope`) + Bagian 2 (6 controller jalur-tulis/otorisasi + 1 bug kode-mati) + Bagian 3 (race condition Jadwal Pelajaran). Semua digabung jadi satu paket, tidak ditunda.
- **Titik perbaikan session-staleness root**: DI DALAM `TenantScope::apply()` itu sendiri (bukan di middleware `ResolveTenant`) — supaya berlaku di SEMUA jalur (HTTP, command artisan, job antrian, test), bukan cuma request HTTP yang lewat middleware. Middleware `ResolveTenant` TIDAK disentuh sama sekali di paket ini.
- **Perilaku saat session basi terdeteksi**: diperlakukan SAMA seperti "belum pilih lembaga" — jatuh ke cabang existing yang sudah ada dan teruji (batasi ke semua lembaga milik yayasan actor sendiri), BUKAN diblokir total (403/422).
- **6 controller di Bagian 2**: `KelasController`, `TahunAjaranController`, `PolaJamController`, `JenisTesMasterController`, `RppController::verify()`, `JadwalPelajaranController` (3 titik: `store()`, `update()`, `duplicate()`) — semua pakai ulang `ResolveLembagaScopeTrait::resolveActiveLembagaId(User $actor): ?int` yang SUDAH ADA (dari paket putaran 3), TIDAK membuat trait/method baru.
- **Bug kode-mati `JadwalPelajaranController::duplicate()` baris 445** (`$user->active_lembaga_id` — properti yang tidak ada di model `User`, selalu `null`) — ikut diperbaiki di Bagian 2, pakai `resolveActiveLembagaId()` yang sama.
- **Race condition Jadwal Pelajaran**: dikunci lewat `JamPelajaran::where('id', $data->jamPelajaranId)->lockForUpdate()->first()` (BUKAN mengunci `Semester` seperti kasus Komponen Penilaian) — karena semua jenis bentrok (ruangan, kelas+jam, guru+waktu) diperiksa relatif terhadap satu `jam_pelajaran_id`/rentang waktu yang sama.
- **Non-Goals eksplisit**: `JalurPpdbController`/`GelombangPpdbController`, pola serupa di modul SPMB (`SkPpdbController`, `TagihanSusulanController`, `PendaftaranAdminController`), cutoff edit presensi, dan gap Activitylog `ProsesKenaikanKelasAction` — SEMUA TIDAK masuk paket ini, tetap dicatat sebagai utang teknis terpisah.

## 2. Temuan & Perbaikan

### 2.1. [CRITICAL] Root-fix: `TenantScope` tidak verifikasi ulang kepemilikan yayasan atas `active_lembaga_id`

**Masalah**: `app/Models/Scopes/TenantScope.php:52-56` — untuk actor scope 'yayasan', begitu `session('active_lembaga_id')` terisi, langsung `where('lembaga_id', $activeLembagaId)` tanpa pernah memverifikasi ulang bahwa lembaga itu masih milik `yayasan_id` actor SAAT INI. `ResolveTenant` middleware (`app/Http/Middleware/ResolveTenant.php:23-30`) HANYA memverifikasi kepemilikan saat user aktif memakai switcher (`?switch_lembaga=...`), TIDAK pada request-request berikutnya. Ini scope GLOBAL dipakai oleh hampir semua model `BelongsToTenant` di Akademik.

**Skenario kegagalan**: user/lembaga dipindah ke yayasan lain saat session masih aktif → user tetap bisa baca/tulis data lembaga yang sudah bukan wewenangnya, karena tidak ada titik yang mengecek ulang.

**Perbaikan** — di `app/Models/Scopes/TenantScope.php`:
```php
class TenantScope implements Scope
{
    private static bool $resolvingActingUser = false;
    private static array $lembagaOwnershipCache = [];

    public function apply(Builder $builder, Model $model): void
    {
        // ... (re-entrancy guard existing, tidak berubah, baris 16-40)

        // ... (cek platform existing, baris 48-50, tidak berubah)

        if ($actingUser->widestScopeLevel() === 'yayasan') {
            $activeLembagaId = session('active_lembaga_id');
            if ($activeLembagaId !== null && ! $this->lembagaMasihMilikYayasan((int) $activeLembagaId, $actingUser->yayasan_id)) {
                $activeLembagaId = null;
            }

            if ($activeLembagaId) {
                $builder->where($model->getTable().'.lembaga_id', $activeLembagaId);
            } else {
                // ... cabang existing yang sudah ada (baris 58-82), TIDAK BERUBAH SAMA SEKALI
            }

            return;
        }

        $builder->where($model->getTable().'.lembaga_id', $actingUser->lembaga_id);
    }

    private function lembagaMasihMilikYayasan(int $lembagaId, ?int $yayasanId): bool
    {
        $cacheKey = $lembagaId.':'.($yayasanId ?? 'null');

        return self::$lembagaOwnershipCache[$cacheKey] ??= Lembaga::where('id', $lembagaId)
            ->where('yayasan_id', $yayasanId)
            ->exists();
    }
}
```
**Catatan cache**: `$lembagaOwnershipCache` bersifat `static`, di-reset otomatis tiap proses PHP baru (siklus request PHP-FPM/CLI standar) — MENCEGAH query database berulang di setiap pemanggilan `TenantScope::apply()` (yang bisa terjadi puluhan kali dalam satu request) sambil tetap memverifikasi ulang untuk kombinasi lembaga+yayasan yang berbeda. Ini FAKTA struktur organisasi (lembaga milik yayasan mana) yang jarang berubah dalam rentang 1 request — aman di-cache per-proses.

### 2.2. [Important] Controller jalur-tulis/otorisasi tidak verifikasi ulang `active_lembaga_id`

**Masalah**: 6 titik berikut memakai `session('active_lembaga_id')` LANGSUNG (baik sebagai nilai yang DITULIS ke baris baru, maupun sebagai dasar pengecekan otorisasi `abort_if`) tanpa verifikasi ulang kepemilikan yayasan — root-fix §2.1 TIDAK menutup celah ini karena itu jalur BACA-data (query scope), sedangkan titik-titik ini adalah jalur TULIS-data/keputusan otorisasi eksplisit.

**Perbaikan** — pakai ulang `ResolveLembagaScopeTrait::resolveActiveLembagaId(User $actor): ?int` (sudah ada, dari paket putaran 3) di semua titik berikut. Tambahkan `use App\Domains\Akademik\Support\ResolveLembagaScopeTrait;` + `use ResolveLembagaScopeTrait;` di tiap class (kecuali sudah ada).

**a) `KelasController::store()`** (`app/Http/Controllers/Admin/KelasController.php:99-106`):
```php
        $lembagaIdOverride = null;
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaIdOverride = $this->resolveActiveLembagaId($request->user());

            if ($lembagaIdOverride === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat kelas.'])->withInput();
            }
        }
```

**b) `TahunAjaranController::store()`** (`app/Http/Controllers/Admin/TahunAjaranController.php:42-50`):
```php
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            $lembagaId = $this->resolveActiveLembagaId($request->user());

            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat tahun ajaran.'])->withInput();
            }

            $data['lembaga_id'] = $lembagaId;
        }
```

**c) `PolaJamController::store()`** (`app/Http/Controllers/Admin/PolaJamController.php:50-56`):
```php
        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'])->withInput();
        }
```
**PENTING**: PERTAHANKAN struktur ternary aslinya PERSIS — hanya cabang yayasan (`session('active_lembaga_id')`) yang diganti jadi `resolveActiveLembagaId()`. JANGAN panggil `resolveActiveLembagaId()` unconditional di luar ternary — untuk actor platform (`lembaga_id` biasanya `null`, bukan scope yayasan), kode asli sengaja SELALU mengembalikan `$request->user()->lembaga_id` (null) tanpa pernah membaca session sama sekali; memanggil `resolveActiveLembagaId()` unconditional akan membuat actor platform ikut membaca `session('active_lembaga_id')` (lewat cabang akhir method trait yang mengembalikan nilai session mentah utk actor non-yayasan), yang tidak relevan/tidak dimaksudkan untuknya.

**d) `JenisTesMasterController::store()`** (`app/Http/Controllers/Admin/JenisTesMasterController.php:32-46`):
```php
        $isYayasanScope = $request->user()->widestScopeLevel() === 'yayasan';
        if ($isYayasanScope) {
            $lembagaId = $this->resolveActiveLembagaId($request->user());
            if ($lembagaId === null) {
                $message = 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah jenis tes.';

                if ($request->wantsJson()) {
                    return response()->json(['message' => $message, 'errors' => ['lembaga_id' => [$message]]], 422);
                }

                return back()->withErrors(['lembaga_id' => $message])->withInput();
            }
        } else {
            $lembagaId = $request->user()->lembaga_id;
        }
```
**PENTING**: `resolveActiveLembagaId()` HANYA dipanggil di DALAM cabang `if ($isYayasanScope)`, PERSIS seperti struktur kode asli — JANGAN dipanggil unconditional di luar percabangan. Untuk actor platform (`$isYayasanScope === false`, `lembaga_id` biasanya `null`), kode asli sengaja TIDAK pernah membaca `session('active_lembaga_id')` sama sekali; kalau `resolveActiveLembagaId()` dipanggil unconditional, actor platform bisa kebagian nilai `session('active_lembaga_id')` sisa dari konteks lain (yang tidak relevan untuknya) — regresi baru yang tidak diinginkan. Sisa logic di bawahnya — `$data = $request->validate([...])` yang memakai `$lembagaId` untuk unique-check, dan `if ($isYayasanScope) { $data['lembaga_id'] = $lembagaId; }` — TIDAK BERUBAH.

**e) `RppController::verify()`** (`app/Http/Controllers/Admin/RppController.php:264-268`):
```php
        $effectiveLembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;

        abort_if($effectiveLembagaId === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum memverifikasi RPP.');
```
(PERTAHANKAN struktur ternary aslinya PERSIS, sama seperti catatan di poin c — hanya cabang yayasan yang diganti. Sisa method — pemanggilan `verifyRppAction->execute(...)` dengan `verifierLembagaId: (int) $effectiveLembagaId` — TIDAK BERUBAH.)

**f) `JadwalPelajaranController::store()`** (`app/Http/Controllers/Admin/JadwalPelajaranController.php:182`):
```php
        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;
```
(PERTAHANKAN struktur ternary aslinya PERSIS. Baris `if ($lembagaId) { abort_if($kelas->lembaga_id !== $lembagaId, 404); }` di bawahnya TIDAK BERUBAH — hanya baris resolve-nya yang diganti.)

**g) `JadwalPelajaranController::update()`** (`app/Http/Controllers/Admin/JadwalPelajaranController.php:335`):
```php
        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? $this->resolveActiveLembagaId($request->user())
            : $request->user()->lembaga_id;
```
(Pola identik dengan f, sisa method tidak berubah.)

**h) `JadwalPelajaranController::duplicate()`** (`app/Http/Controllers/Admin/JadwalPelajaranController.php:444-445`) — **bug kode-mati**, bukan cuma session-staleness:
```php
        $user = $request->user();
        $lembagaId = $user->widestScopeLevel() === 'yayasan' ? $this->resolveActiveLembagaId($user) : $user->lembaga_id;
```
(Menggantikan `$lembagaId = $user->active_lembaga_id ?: ($user->lembaga_id ?: null);` — properti `active_lembaga_id` TIDAK PERNAH ADA di model `User`, selalu resolve ke `null` lewat magic `__get()`, sehingga actor yayasan-scope sebelumnya SELALU jatuh ke cabang `else` yang cuma cek `sourceKelas->lembaga_id === targetKelas->lembaga_id` tanpa pernah memverifikasi lembaga aktif actor sama sekali. Sisa method — blok `if ($lembagaId) { abort_if(...) } else { abort_if(...) }` — TIDAK BERUBAH.)

**Catatan `JadwalPelajaranController::index()` baris 71** (`$targetLembagaId` dari session, dipakai HANYA untuk filter dropdown `mataPelajaranList`/`guruList`/`ruanganList`) — **TIDAK termasuk perbaikan ini**, karena murni filter UI (bukan otorisasi/tulis-data); kalaupun stale, hasilnya cuma dropdown menampilkan pilihan lebih luas/sempit dari yang seharusnya, bukan kebocoran data lintas-tenant (data hasil pilihan tetap divalidasi ulang di `store()`/`update()` yang sudah diperbaiki di atas).

### 2.3. [Important] Race condition `CreateJadwalPelajaranAction`/`UpdateJadwalPelajaranAction`

**Masalah**: `app/Domains/Akademik/Actions/Jadwal/CreateJadwalPelajaranAction.php:26-71` dan `UpdateJadwalPelajaranAction.php:26-73` — 3 pengecekan agregat (bentrok ruangan via `ValidateRoomClashAction`, slot jam kelas via `$isSlotTaken`, bentrok guru via `$isGuruClash`) dilakukan lewat `exists()` di LUAR `DB::transaction`, sebelum baris `DB::transaction(fn () => JadwalPelajaran::create(...))`/`update(...)` di akhir method. Dua request paralel bisa lolos ketiga pengecekan bersamaan dan membuat/mengubah jadwal yang saling bentrok.

**Perbaikan** — `CreateJadwalPelajaranAction::execute()`, bungkus SELURUH isi method (bukan cuma bagian akhir) ke dalam satu `DB::transaction()`, kunci baris `JamPelajaran` di awal:
```php
    public function execute(JadwalPelajaranData $data): JadwalPelajaran
    {
        return DB::transaction(function () use ($data) {
            $jamPelajaranBaru = JamPelajaran::where('id', $data->jamPelajaranId)->lockForUpdate()->first();

            // 1. Validasi Bentrok Ruangan Sarpras
            if ($data->ruanganId !== null) {
                $isRoomClash = $this->validateRoomClashAction->execute(
                    ruanganId: $data->ruanganId,
                    semesterId: $data->semesterId,
                    jamPelajaranId: $data->jamPelajaranId
                );

                if ($isRoomClash) {
                    throw ValidationException::withMessages([
                        'ruangan_id' => 'Ruangan yang dipilih sudah digunakan oleh kelas lain pada jam pelajaran ini.',
                    ]);
                }
            }

            // 2. Validasi Slot Jam Kelas
            $isSlotTaken = JadwalPelajaran::query()
                ->where('kelas_id', $data->kelasId)
                ->where('semester_id', $data->semesterId)
                ->where('jam_pelajaran_id', $data->jamPelajaranId)
                ->exists();

            if ($isSlotTaken) {
                throw ValidationException::withMessages([
                    'jam_pelajaran_id' => 'Kelas ini sudah punya jadwal pada slot ini di semester yang sama.',
                ]);
            }

            // 3. Validasi Bentrok Guru Pengampu (berbasis waktu wall-clock, bukan ID slot --
            // 2 Pola Jam berbeda bisa punya jam_pelajaran_id berbeda untuk jam yang sama persis).
            $isGuruClash = JadwalPelajaran::query()
                ->where('guru_id', $data->guruId)
                ->where('semester_id', $data->semesterId)
                ->whereHas('jamPelajaran', function ($q) use ($jamPelajaranBaru) {
                    $q->where('hari', $jamPelajaranBaru->hari)
                        ->where('jam_mulai', '<', $jamPelajaranBaru->jam_selesai)
                        ->where('jam_selesai', '>', $jamPelajaranBaru->jam_mulai);
                })
                ->exists();

            if ($isGuruClash) {
                throw ValidationException::withMessages([
                    'guru_id' => 'Guru ini sudah mengajar kelas lain pada jam dan semester yang sama.',
                ]);
            }

            return JadwalPelajaran::create($data->toArray());
        });
    }
```
**PENTING**: `$jamPelajaranBaru = JamPelajaran::where('id', $data->jamPelajaranId)->lockForUpdate()->first();` MENGGANTIKAN baris lama `$jamPelajaranBaru = JamPelajaran::findOrFail($data->jamPelajaranId);` — pindah ke AWAL closure (sebelum pengecekan #1), bukan di antara pengecekan #2 dan #3 seperti kode asli, supaya lock diambil SEBELUM pengecekan manapun mulai membaca data yang bisa berubah.

**Perbaikan identik untuk `UpdateJadwalPelajaranAction::execute()`** — bungkus SELURUH isi method ke `DB::transaction()`, `$jamPelajaranBaru = JamPelajaran::where('id', $data->jamPelajaranId)->lockForUpdate()->first();` di awal closure, WAJIB pertahankan SEMUA klausa `->where('id', '!=', $jadwal->id)` (baris yang sudah benar di 2 dari 3 pengecekan) dan `ignoreJadwalId: $jadwal->id` (di pengecekan #1) tetap utuh, dan `$jadwal->update($data->toArray()); return $jadwal->fresh();` di akhir closure.

## 3. Non-Goals

- `JalurPpdbController`/`GelombangPpdbController` (session-staleness PPDB) — SKIP, tetap catatan serah-terima dari putaran 3.
- Pola serupa di modul SPMB (`SkPpdbController`, `TagihanSusulanController`, `PendaftaranAdminController`) — SKIP, temuan baru dari audit ini tapi di LUAR modul Akademik, dicatat terpisah.
- Cutoff edit presensi — SKIP, item lama tanpa keputusan desain, ditunda.
- Gap Activitylog `ProsesKenaikanKelasAction` — SKIP, sudah dicatat ditunda dari putaran 3.
- `JadwalPelajaranController::index()` baris 71 (filter dropdown) — SKIP, murni UI, bukan otorisasi/tulis-data (lihat catatan di §2.2).
- `ResolveTenant` middleware — TIDAK disentuh sama sekali, root-fix cukup di `TenantScope`.

## 4. Test Plan

| # | Area | Skenario |
|---|---|---|
| 1 | `TenantScope` | Actor yayasan dengan `active_lembaga_id` di session menunjuk ke lembaga yang **sudah bukan** milik yayasannya saat ini → hasil query model tenant-scoped (mis. `Siswa`) tetap ter-scope ke lembaga miliknya sendiri (fallback ke cabang "semua lembaga milik yayasan sendiri"), BUKAN bocor ke lembaga asing, BUKAN juga kosong total. |
| 2 | `TenantScope` | Regresi: actor yayasan dengan `active_lembaga_id` session VALID (masih milik yayasannya) tetap ter-scope hanya ke lembaga itu seperti sebelumnya — pastikan fix tidak melonggarkan perilaku yang sudah benar. |
| 3 | `KelasController::store()` | Actor yayasan dengan session stale → ditolak (`assertSessionHasErrors('lembaga_id')`), kelas tidak dibuat dengan lembaga_id yang salah. |
| 4 | `TahunAjaranController::store()` | Sama pola #3. |
| 5 | `PolaJamController::store()` | Sama pola #3. |
| 6 | `JenisTesMasterController::store()` | Sama pola #3. |
| 7 | `RppController::verify()` | Actor yayasan dengan session stale → ditolak 422, RPP tidak berubah status. |
| 8 | `JadwalPelajaranController::store()` | Regresi: actor yayasan dengan `active_lembaga_id` session VALID (menunjuk lembaga L1 miliknya) tetap bisa membuat jadwal untuk kelas di L1, dan tetap ditolak 404 untuk kelas di lembaga lain L2 — pastikan penggantian `session('active_lembaga_id')` dengan `resolveActiveLembagaId()` tidak mengubah perilaku otorisasi normal yang sudah benar. (Catatan: skenario "session stale" untuk method ini sebagian besar sudah tertutup oleh root-fix §2.1 sendiri — `Kelas::find()` di baris 179 tidak lagi bisa mengembalikan kelas lintas-yayasan begitu `TenantScope` diperbaiki, jadi `abort_if(! $kelas, 404)` di baris 180 sudah menangkapnya duluan. Fix di titik ini murni defense-in-depth + konsistensi kode.) |
| 9 | `JadwalPelajaranController::update()` | Sama pola #8, untuk update — regresi kasus normal, bukan penutup celah independen. |
| 10 | `JadwalPelajaranController::duplicate()` | Actor yayasan dengan `active_lembaga_id` VALID menunjuk ke lembaga L1 (di bawah yayasannya sendiri), mencoba duplicate jadwal antar 2 kelas yang SAMA-SAMA di lembaga L2 (lembaga lain, MASIH di bawah yayasan yang sama, jadi tetap lolos `TenantScope`) → harus 404. Ini skenario yang SEBELUMNYA lolos (bug kode-mati membuat `$lembagaId` selalu `null` untuk actor yayasan, jatuh ke cabang `else` yang cuma cek `sourceKelas->lembaga_id === targetKelas->lembaga_id` — di sini keduanya sama-sama L2 jadi lolos meski actor sedang "aktif" bekerja di L1). Setelah fix, `resolveActiveLembagaId()` mengembalikan L1, memicu cabang `if ($lembagaId)` yang mewajibkan source DAN target sama-sama L1 — L2 harus ditolak. |
| 11 | `CreateJadwalPelajaranAction`/`UpdateJadwalPelajaranAction` | Behavioral: 2 pemanggilan sekuensial yang salah satunya seharusnya bentrok (slot/guru/ruangan sama) tetap konsisten ditolak pada pemanggilan kedua setelah dibungkus lock — regresi memastikan pembungkusan transaction tidak merusak logic yang sudah benar. |
