# Spec: Perbaikan Audit Akademik Lanjutan (IDOR, RPP Verify, Bentrok Jadwal, Resolver Precedence)

**Tanggal**: 2026-09-04
**Branch**: `akademik-v2`
**Konteks**: Ditemukan lewat audit menyeluruh 2 putaran terhadap modul Akademik (dilakukan setelah spec Kelompok A "siklus hidup kelas_id siswa" dan Kelompok B "peringatan tingkat Kenaikan Kelas" selesai). Semua temuan di bawah sudah diverifikasi lewat pembacaan kode langsung (bukan cuma laporan subagent) sebelum masuk spec ini.

## 1. Keputusan yang Sudah Final (dikonfirmasi lewat brainstorming, jangan tanya ulang)

- **Satu spec gabungan** untuk semua temuan di bawah (bukan dipisah per temuan) — semuanya independen secara teknis tapi berasal dari audit yang sama dan cukup kecil untuk dikerjakan berurutan dalam 1 plan.
- **Assignment GLOBAL (`lembaga_id = NULL`) HANYA boleh dibuat/diubah oleh role `platform`.** Role `yayasan` cuma boleh membuat assignment SPESIFIK untuk lembaga di bawah yayasannya sendiri — diterapkan KONSISTEN untuk `KurikulumAssignment` DAN `FaseDefaultMapping`.
- **Perbaikan lewat pola "derive, jangan validate"** (precedent terverifikasi: `CreatePersonAction::resolveYayasanId()`, `GuruController::resolveLembagaId()`) — `lembaga_id` untuk actor non-platform TIDAK PERNAH diterima dari request/form secara langsung, selalu diturunkan dari `session('active_lembaga_id')` (yayasan-scope) atau `$actor->lembaga_id` (lembaga-scope). **BUKAN** pakai Model Observer/`saving()` hook (itu melanggar `.ai/rules/models.md` yang sudah direkam: *"No model Observers or lifecycle-hook closures"*) — pertahanan lapis kedua dicapai dengan TIDAK PERNAH mempercayai input berbahaya sejak titik masuknya, bukan memvalidasinya setelah diterima.
- **Verifikasi ulang `session('active_lembaga_id')` di titik pakai** — tidak percaya mentah-mentah walau sudah diverifikasi saat di-SET (`ResolveTenant.php`). Cek eksplisit `Lembaga::where('id', $lembagaId)->where('yayasan_id', $actor->yayasan_id)->exists()` sebelum dipakai; gagal → `abort(422)` dengan pesan jelas.
- **1 Action per model sebagai satu-satunya pintu tulis** — `AssignKurikulumAction` (baru, menggantikan pemanggilan `CreateKurikulumAssignmentAction`/`UpdateKurikulumAssignmentAction` langsung dari controller tanpa resolusi scope) dan `SetFaseDefaultMappingAction` (baru). `resolveLembagaId()` identik di keduanya — diekstrak jadi 1 trait/helper dipakai bersama, bukan disalin 2x.
- **Perbaikan `index()` listing WAJIB masuk scope** (bukan opsional) — kebocoran BACA lintas-yayasan adalah pelanggaran isolasi tenant sendiri, terlepas dari kemampuan tulis. `platform` tetap lihat SEMUA assignment lintas yayasan (regresi negatif wajib dites); `yayasan` cuma lihat assignment global + milik lembaga-lembaga di bawah yayasannya sendiri.
- **Severity temuan #5 (session staleness) BUKAN "Minor" polos** — likelihood rendah (butuh `yayasan_id` actor berubah di tengah sesi aktif) TAPI impact-nya IDOR juga (actor bisa beroperasi atas nama lembaga yang bukan lagi haknya) — sekelas dengan temuan Critical, cuma trigger-nya jauh lebih jarang. Diberi label **"Important (impact tinggi, likelihood rendah)"**, bukan "Minor", supaya tidak diremehkan pembaca serah-terima nanti.
- **2 temuan Minor (race condition bobot Komponen Penilaian, fallback guru acak RppController) TIDAK masuk scope perbaikan spec ini** — keduanya baru dilaporkan subagent, BELUM diverifikasi independen oleh saya (beda dengan 4 temuan utama yang semuanya sudah diverifikasi manual). Dicatat di §5 sebagai catatan untuk spec terpisah nanti — supaya tidak ambigu antara "didaftar" dan "diperbaiki".
- **Pola "percaya `session('active_lembaga_id')` tanpa verifikasi ulang" di controller LAIN** (`GuruController`, `JalurPpdbController`, `KalenderAkademikController`, `PengaturanAkademikController`, `GelombangPpdbController`, dan kemungkinan lainnya) **TIDAK ditambal di spec ini** — di luar scope (hanya `KurikulumAssignment`/`FaseDefaultMapping` yang diperbaiki), dicatat di §5 sebagai catatan serah-terima terpisah untuk ditindaklanjuti nanti supaya tidak scope-creep ke seluruh aplikasi.

## 2. Temuan & Perbaikan

### 2.1. [CRITICAL] IDOR lintas-yayasan — KurikulumAssignment & FaseDefaultMapping

**Masalah** (`KurikulumAssignmentController.php:156-171`, pola identik di `FaseDefaultMappingController.php`):
```php
private function isPlatformOrYayasan(Request $request): bool
{
    return in_array($request->user()->widestScopeLevel(), ['platform', 'yayasan'], true);
}

private function authorizeAssignmentScope(Request $request, ?int $lembagaIdDiminta): void
{
    $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
    if ($lembagaIdDiminta === null) {
        abort_unless($isPlatformOrYayasan, 403);
        return;
    }
    abort_unless($isPlatformOrYayasan || $lembagaIdDiminta === $request->user()->lembaga_id, 403);
}
```
`'yayasan'` diperlakukan sama seperti `'platform'` — tidak pernah dibatasi ke yayasan miliknya sendiri. Model `KurikulumAssignment`/`FaseDefaultMapping` juga TIDAK pakai `BelongsToTenant` — dua lapis pertahanan gagal sekaligus. Dropdown `lembagaList` (`Lembaga::orderBy('nama')->get()`, tanpa filter `yayasan_id`) membocorkan daftar lembaga lintas-yayasan sebagai sumber ID untuk dieksploitasi.

**Perbaikan**:

1. **`app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php`** (baru, dipakai kedua Action baru):
   ```php
   trait ResolveLembagaScopeTrait
   {
       private function resolveLembagaId(User $actor, ?int $lembagaIdDiminta): ?int
       {
           return match ($actor->widestScopeLevel()) {
               'platform' => $lembagaIdDiminta,
               'yayasan' => $this->resolveLembagaIdUntukYayasan($actor),
               default => $actor->lembaga_id,
           };
       }

       private function resolveLembagaIdUntukYayasan(User $actor): int
       {
           $lembagaId = session('active_lembaga_id');
           abort_if($lembagaId === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum melakukan aksi ini.');

           $milikYayasan = Lembaga::where('id', $lembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();
           abort_unless($milikYayasan, 422, 'Lembaga aktif di sesi Anda tidak valid untuk yayasan Anda saat ini. Pilih ulang lembaga aktif melalui pengalih lembaga.');

           return $lembagaId;
       }
   }
   ```
2. **`AssignKurikulumAction`** (baru, `app/Domains/Akademik/Actions/KurikulumAssignment/AssignKurikulumAction.php`) — satu-satunya pintu masuk create/update `KurikulumAssignment`. Memakai `ResolveLembagaScopeTrait::resolveLembagaId()` sebelum memanggil logic existing (`CreateKurikulumAssignmentAction`/`UpdateKurikulumAssignmentAction` bisa tetap jadi collaborator internal, atau logic-nya dipindah masuk — detail struktur file ditentukan saat writing-plans). `KurikulumAssignmentController::store()`/`update()` memanggil Action ini, TIDAK memproses `lembaga_id` dari request sama sekali untuk actor non-platform.
3. **`SetFaseDefaultMappingAction`** (baru, pola identik) untuk `FaseDefaultMapping`.
4. **Form create/edit untuk actor non-platform TIDAK lagi punya dropdown pilih lembaga** (mengikuti pola `GuruController`/`admin/guru/create.blade.php`) — cukup tampilkan nama lembaga aktif sebagai teks info read-only. Dropdown lembaga (`Lembaga::orderBy('nama')->get()`) HANYA dirender untuk `platform`, dengan tambahan opsi eksplisit "Global — berlaku semua yayasan" (value kosong/null).
5. **`index()` listing**: `platform` tetap lihat semua; `yayasan` di-filter ke `whereNull('lembaga_id')->orWhereIn('lembaga_id', Lembaga::where('yayasan_id', $actor->yayasan_id)->pluck('id'))`. **Konfirmasi actor `lembaga`-scope biasa**: filter existing (`! $isPlatformOrYayasan` → `whereNull('lembaga_id')->orWhere('lembaga_id', $request->user()->lembaga_id)`) SUDAH BENAR dan tidak disentuh — bug IDOR cuma ada di cabang `isPlatformOrYayasan`, cabang `lembaga`-scope ini sudah ter-filter dengan tepat sejak awal.
6. **[GAP TERPISAH, dikonfirmasi lewat pembacaan kode langsung] `edit()`/`update()`/`destroy()` punya celah IDOR SENDIRI, beda pintu dari create.** `update()` (baris 125 di kode existing) bahkan tidak pernah mengubah `lembaga_id` sama sekali (`lembagaId: $kurikulumAssignment->lembaga_id`, dikembalikan apa adanya) — jadi `resolveLembagaId()` di atas TIDAK relevan untuk 3 method ini. Proteksi ke-3 method ini 100% bertumpu pada `authorizeAssignmentScope($request, $kurikulumAssignment->lembaga_id)`, yang rusak dengan cara SAMA (`isPlatformOrYayasan` menyamakan yayasan dengan platform) — seorang yayasan_super_admin Yayasan A bisa `PUT`/`DELETE` assignment dengan `{id}` yang menunjuk ke baris milik Yayasan B, lolos otorisasi, walau tidak ada nilai `lembaga_id` baru yang salah ditulis.

   **Perbaikan**: ganti `authorizeAssignmentScope()` dengan pemeriksaan yang memverifikasi kepemilikan baris EXISTING (bukan nilai yang akan ditulis):
   ```php
   private function authorizeExistingAssignmentScope(User $actor, ?int $existingLembagaId): void
   {
       if ($actor->widestScopeLevel() === 'platform') {
           return;
       }

       if ($existingLembagaId === null) {
           abort(403, 'Assignment global hanya bisa diubah/dihapus oleh Platform Admin.');
       }

       if ($actor->widestScopeLevel() === 'yayasan') {
           $milikYayasan = Lembaga::where('id', $existingLembagaId)->where('yayasan_id', $actor->yayasan_id)->exists();
           abort_unless($milikYayasan, 403, 'Assignment ini bukan milik yayasan Anda.');

           return;
       }

       abort_unless($existingLembagaId === $actor->lembaga_id, 403);
   }
   ```
   Dipanggil di `edit()`/`update()`/`destroy()` dengan `$kurikulumAssignment->lembaga_id` (baris EXISTING dari route-model-binding) SEBELUM logic apapun lain dijalankan (termasuk sebelum cek duplikat di `update()`). Pola identik untuk `FaseDefaultMappingController`.

### 2.2. [IMPORTANT] Verifikasi RPP gagal total untuk role yayasan

**Masalah** (`RppController.php:269`, `VerifyRppAction.php:18`): `verifierLembagaId: (int) $request->user()->lembaga_id` — untuk `yayasan_super_admin` (`lembaga_id = null`), jadi `0`. `VerifyRppAction` membandingkan `$rpp->lembaga_id !== 0` yang selalu `true` → verifikasi SELALU ditolak untuk role yayasan, walau permission `rpp.verify` sah.

**Perbaikan**: `RppController::verify()` memakai pola `effectiveLembagaId` yang SUDAH BENAR di `VerifyPengajuanRaporAction.php:27-29`/`PersetujuanController.php:44-46`:
```php
$effectiveLembagaId = $request->user()->widestScopeLevel() === 'yayasan'
    ? session('active_lembaga_id')
    : $request->user()->lembaga_id;
```
Diteruskan ke `VerifyRppAction::execute()` sebagai `verifierLembagaId`. Guard tambahan: kalau `$effectiveLembagaId === null` (yayasan belum pilih lembaga aktif), tolak dengan pesan jelas SEBELUM memanggil Action (bukan biarkan `VerifyRppAction` menolak dengan pesan generik "tidak berwenang" yang membingungkan).

### 2.3. [IMPORTANT] Deteksi bentrok jadwal berbasis ID slot, bukan waktu nyata

**Masalah** (`CreateJadwalPelajaranAction.php:52-63`, `UpdateJadwalPelajaranAction.php` pola sama): bentrok guru dicek via `jam_pelajaran_id` (ID baris), bukan `jam_mulai`/`jam_selesai`. Karena `jam_pelajaran` di-scope per `pola_jam_id` dan 1 lembaga bisa punya banyak Pola Jam berbeda, guru bisa double-booking kalau 2 kelasnya pakai Pola Jam berbeda dengan jam wall-clock yang sebenarnya sama.

**Perbaikan**: ganti query bentrok jadi join ke `jam_pelajaran` dan bandingkan `hari` + rentang waktu overlap (`jam_mulai < ? AND jam_selesai > ?`), bukan `jam_pelajaran_id` mentah:
```php
$isGuruClash = JadwalPelajaran::query()
    ->where('guru_id', $data->guruId)
    ->where('semester_id', $data->semesterId)
    ->whereHas('jamPelajaran', function ($q) use ($jamPelajaranBaru) {
        $q->where('hari', $jamPelajaranBaru->hari)
            ->where('jam_mulai', '<', $jamPelajaranBaru->jam_selesai)
            ->where('jam_selesai', '>', $jamPelajaranBaru->jam_mulai);
    })
    ->exists();
```
Berlaku sama untuk `ValidateRoomClashAction` (bentrok ruangan) — detail final struktur query ditentukan saat writing-plans (perlu load `$jamPelajaranBaru` dulu dari `$data->jamPelajaranId` sebelum query).

### 2.4. [IMPORTANT] Precedence resolver Fase/Kurikulum salah

**Masalah** (`FaseDefaultResolver.php:16-24`, `KurikulumAssignmentResolver.php:23-32`, pola identik): WHERE clause tidak pernah filter `tingkat` — hanya `ORDER BY` yang menentukan pemenang. Baris dengan `tingkat` SPESIFIK APAPUN (termasuk yang TIDAK cocok dengan tingkat yang diminta) selalu menang atas baris catch-all (`tingkat = NULL`) dalam tier lembaga yang sama, karena `ORDER BY tingkat IS NULL` (key ke-2) sudah memutuskan pemenang sebelum `ORDER BY tingkat = ? DESC` (key ke-3) sempat dicek.

**Perbaikan**: tambah filter WHERE eksplisit SEBELUM `orderBy`, supaya baris dengan tingkat yang tidak cocok tidak pernah jadi kandidat sama sekali:
```php
$query = FaseDefaultMapping::where('bentuk_pendidikan', $bentukPendidikan)
    ->where(function ($q) use ($lembagaId) {
        $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
    })
    ->where(function ($q) use ($tingkat) {
        $q->where('tingkat', $tingkat)->orWhereNull('tingkat');
    })
    ->orderByRaw('lembaga_id IS NULL')
    ->orderByRaw('tingkat IS NULL');
```
(Baris `orderByRaw('tingkat = ? DESC', [$tingkat])` yang lama jadi tidak diperlukan lagi — filter WHERE baru sudah menjamin cuma baris `tingkat = $tingkat` ATAU `tingkat = NULL` yang lolos, dan `orderByRaw('tingkat IS NULL')` sudah cukup memilih yang paling spesifik di antara keduanya). Berlaku sama untuk `KurikulumAssignmentResolver`.

## 3. Non-Goals

- Model Observer/`saving()` hook — sengaja TIDAK dipakai (melanggar `.ai/rules/models.md`), diganti pola "derive, jangan validate".
- 2 temuan Minor (race condition bobot Komponen Penilaian, fallback guru acak RppController) — TIDAK diverifikasi/ditambal di spec ini, dicatat di §5 untuk spec terpisah.
- Pola "percaya `session('active_lembaga_id')` tanpa verifikasi ulang" di `GuruController`, `JalurPpdbController`, `KalenderAkademikController`, `PengaturanAkademikController`, `GelombangPpdbController` — di luar scope, dicatat di §5.
- Tidak ada perubahan pada modul di luar Akademik.

## 4. Test Plan

| # | Area | Skenario |
|---|---|---|
| 1 | `AssignKurikulumAction` | Yayasan kirim `lembaga_id=null` → 422. |
| 2 | `AssignKurikulumAction` | Yayasan kirim `lembaga_id` milik yayasan LAIN di body request → assert baris tersimpan pakai `session('active_lembaga_id')` milik yayasan sendiri, BUKAN nilai yang dikirim (membuktikan input diabaikan total, bukan cuma divalidasi). |
| 3 | `AssignKurikulumAction` | `active_lembaga_id` di sesi menunjuk lembaga DI LUAR yayasan actor (simulasi stale) → 422 pesan jelas. |
| 4 | `AssignKurikulumAction` | Platform BISA buat assignment global (`lembaga_id=null`). |
| 5 | `AssignKurikulumAction` | Platform BISA buat assignment untuk lembaga manapun lintas yayasan. |
| 6-10 | `SetFaseDefaultMappingAction` | Cerminan persis test #1-5 di atas untuk `FaseDefaultMapping`. |
| 11 | `KurikulumAssignmentController::index()` | Yayasan cuma lihat assignment global + milik yayasannya sendiri; TIDAK lihat milik yayasan lain. |
| 12 | `KurikulumAssignmentController::index()` | **Platform TETAP lihat SEMUA assignment lintas yayasan** (regresi negatif — pastikan filter yayasan tidak ikut membatasi visibilitas platform). |
| 13-14 | `FaseDefaultMappingController::index()` | Cerminan test #11-12 untuk `FaseDefaultMapping`. |
| 15a | `edit()`/`update()`/`destroy()` — gap akses baris existing | Yayasan A coba `edit`/`update`/`destroy` assignment yang **benar-benar milik lembaga di Yayasan B** (bukan soal nilai yang ditulis, soal `{id}` yang diakses) → 403 ditolak di titik paling awal, SEBELUM logic lain (cek duplikat, dsb) sempat jalan. Beda dari test #1-10 (yang semuanya soal create/nilai ditulis) — ini pertama kali menguji akses ke baris existing milik pihak lain. |
| 15b | Sama, untuk `FaseDefaultMapping` | Cerminan test #15a. |
| 15c | `edit()`/`update()`/`destroy()` — assignment global | Yayasan coba `edit`/`update`/`destroy` assignment GLOBAL (`lembaga_id = null`) → 403 (cuma platform yang boleh sentuh baris global). |
| 15 | `RppController::verify()` | `yayasan_super_admin` yang sebelumnya SELALU gagal (bug) sekarang BERHASIL verifikasi RPP milik lembaga di bawah yayasannya. |
| 16 | `CreateJadwalPelajaranAction` | 2 kelas beda Pola Jam, `jam_mulai`/`jam_selesai` overlap, guru sama → DITOLAK (test ini gagal di kode lama, membuktikan bug nyata sebelum fix). |
| 17 | `FaseDefaultResolver` | Reproduksi persis skenario bug: Row tingkat spesifik TIDAK cocok vs Row catch-all dalam tier lembaga sama → catch-all menang untuk tingkat yang diminta. |
| 18 | `KurikulumAssignmentResolver` | Cerminan test #17. |

## 5. Catatan Serah-Terima (di luar scope spec ini, untuk ditindaklanjuti terpisah)

1. **2 temuan Minor belum diverifikasi**: race condition validasi total bobot di `CreateKomponenPenilaianAction`/`UpdateKomponenPenilaianAction` (tanpa `lockForUpdate()`, pola sama dengan yang sudah ditambal di Approve/VerifyPengajuanRaporAction); fallback guru acak diam-diam di `RppController.php:147` saat `guru_id` tidak dikirim eksplisit.
2. **[Important — impact tinggi, likelihood rendah] Pola "percaya `session('active_lembaga_id')` tanpa verifikasi ulang" di controller lain**: `GuruController`, `JalurPpdbController`, `KalenderAkademikController`, `PengaturanAkademikController`, `GelombangPpdbController` (dan kemungkinan lainnya, belum disisir lengkap) — semuanya membaca `session('active_lembaga_id')` langsung tanpa re-verifikasi kepemilikan yayasan di titik pakai, berbeda dengan pendekatan yang diambil spec ini untuk `KurikulumAssignment`/`FaseDefaultMapping`. Trigger-nya jarang (butuh `yayasan_id` actor berubah di tengah sesi aktif tanpa invalidasi sesi), TAPI dampaknya IDOR kalau terjadi — jangan diremehkan sebagai "cuma Minor" saat spec perbaikannya ditulis nanti.
3. Area yang masih belum diaudit sama sekali dari audit 2 putaran sebelumnya (lihat riwayat sesi): sebagian besar sudah tercakup, kecuali detail implementasi internal beberapa Service yang cuma dicek dari sisi pemanggilnya.
