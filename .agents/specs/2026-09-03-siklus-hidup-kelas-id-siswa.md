# Spec: Siklus Hidup `kelas_id` Siswa Saat Status Berubah

**Tanggal**: 2026-09-03
**Branch**: `akademik-v2` (lanjut di branch yang sama, tidak pindah branch)
**Konteks**: Ditemukan saat audit bisnis lanjutan fitur Kenaikan Kelas (lihat `.agents/logs/2026-09-03-audit-akademik-perbaikan.md`, bagian "Audit Bisnis Lanjutan"). `SiswaController::updateStatus()` tidak pernah membersihkan `kelas_id` siswa saat status berubah ke `Pindah`/`Keluar`/`Lulus`, menyebabkan siswa yang sudah tidak aktif tetap "menempel" di kelas lama dan ikut terhitung di berbagai fitur yang query berdasarkan `kelas_id`. Salah satu titiknya (`SubmitPengajuanRaporAction`) sudah terbukti jadi **bug produksi aktif** (memblokir submit rapor wali kelas) dan sudah ditambal terpisah, minimal dan terisolasi, sebelum spec ini ditulis (lihat commit terkait di handoff log Kenaikan Kelas). Spec ini menutup akar masalahnya secara permanen.

## 1. Keputusan yang Sudah Final (dikonfirmasi lewat brainstorming, jangan tanya ulang)

- **Cakupan**: spec ini HANYA menutup siklus hidup `kelas_id` (Kelompok A). Validasi tingkat Kenaikan Kelas + konsep "siswa tinggal kelas" (Kelompok B) adalah spec terpisah, tidak digabung — independen secara teknis dan butuh keputusan bisnis sendiri yang bisa memakan waktu.
- **Ketiga status non-aktif diperlakukan SAMA**: `Lulus`, `Pindah`, `Keluar` semuanya membersihkan `kelas_id` (snapshot dulu ke `kelas_terakhir_id`) — ini juga menyeragamkan jalur Lulus-manual (sebelumnya tidak null-kan `kelas_id`) dengan jalur Lulus-via-Kenaikan-Kelas (sebelumnya sudah null-kan).
- **Reversal otomatis**: kalau status dikembalikan ke `Aktif`, `kelas_id` OTOMATIS dipulihkan dari `kelas_terakhir_id`, lalu `kelas_terakhir_id` di-null-kan lagi. Alasan: kondisi "Aktif tanpa kelas" adalah kegagalan SENYAP (siswa hilang dari semua fitur berbasis kelas tanpa tanda visual), sedangkan "kelas yang dipulihkan mungkin sudah usang" adalah kegagalan KELIHATAN (gampang dikoreksi manual lewat halaman edit Siswa yang sudah ada).
- **Idempotency**: kalau status target sama dengan status sekarang, tidak ada snapshot/restore yang dijalankan sama sekali.
- **CHECK constraint di database**: `CHECK (status = 'aktif' OR kelas_id IS NULL)` pada tabel `siswa` — dikonfirmasi didukung MySQL 8.0.30 (versi project ini). Ini membuat invariant terjamin di level data, bukan cuma konvensi kode yang bisa dilanggar kode masa depan.
- **Backfill wajib, sinkron (bukan queued/chunked)**: dicek langsung ke database dev — 0 dari 336 siswa berstatus non-aktif hari ini, jadi skala backfill kecil. 1 statement SQL sinkron cukup, tidak perlu job/queue seperti pola di modul Keuangan (yang menangani ratusan/ribuan baris tagihan).
- **Urutan WAJIB di dalam migration**: backfill (bersihkan data existing) HARUS jalan SEBELUM `ADD CONSTRAINT` — MySQL memvalidasi seluruh baris existing terhadap CHECK constraint baru saat `ALTER TABLE`, kalau dibalik migration akan gagal begitu ada baris yang melanggar.
- **Arsitektur**: logic dipindah dari inline `SiswaController::updateStatus()` ke Action baru `App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction`, sesuai konvensi proyek yang sudah direkam (`.ai/rules/controllers.md`: *state-transition endpoints inject a Domain Action*) — sekaligus membetulkan inkonsistensi arsitektur yang sudah ada sebelum spec ini.
- **`kelas_terakhir_id`**: kolom baru `BIGINT UNSIGNED NULL` di tabel `siswa`, dengan FK ke `kelas.id` `ON DELETE SET NULL` — menyamai definisi `kelas_id` yang sudah ada (dikonfirmasi lewat schema: `kelas_id` sudah punya FK asli dengan `ON DELETE SET NULL`, bukan kolom polos seperti dugaan awal).
- **Branch**: tetap di `akademik-v2`, tidak dipindah ke branch baru.

## 2. Perubahan Database (1 Migration)

Isi migration, urutan HARUS seperti ini:

1. **Tambah kolom**: `kelas_terakhir_id` (`bigint unsigned`, nullable) + foreign key ke `kelas.id`, `onDelete('set null')`.
2. **Backfill data existing** (raw SQL, 1 statement):
   ```sql
   UPDATE siswa
   SET kelas_terakhir_id = kelas_id, kelas_id = NULL
   WHERE status != 'aktif' AND kelas_id IS NOT NULL;
   ```
   Backfill ini murni snapshot ID — TIDAK memvalidasi keberadaan Kelas (dikonfirmasi: `KelasController` tidak punya `destroy()`, Kelas tidak pernah bisa dihapus lewat UI aplikasi ini hari ini; 0 baris orphan `kelas_id` ditemukan saat audit).
3. **Tambah CHECK constraint** (via `DB::statement()`, Laravel Schema Builder tidak punya method fluent untuk CHECK):
   ```sql
   ALTER TABLE siswa ADD CONSTRAINT chk_siswa_kelas_id_null_saat_nonaktif
       CHECK (status = 'aktif' OR kelas_id IS NULL);
   ```

**Down migration**: drop constraint, drop FK, drop kolom — tidak perlu me-restore `kelas_id` dari `kelas_terakhir_id` saat rollback (rollback migration ini secara alami berarti fitur ini dibatalkan sepenuhnya, bukan operasi yang butuh dipertahankan datanya).

## 3. `UpdateStatusSiswaAction`

**File baru**: `app/Domains/Akademik/Actions/Siswa/UpdateStatusSiswaAction.php`

```php
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

Catatan desain: transisi non-aktif → non-aktif lain (mis. `Pindah` → `Keluar`) tidak menyentuh `kelas_id`/`kelas_terakhir_id` sama sekali (keduanya sudah dalam keadaan benar dari transisi sebelumnya) — hanya kolom `status` yang berubah.

**`SiswaController::updateStatus()`** diubah untuk memanggil action ini (method-injected), validasi input (`in:aktif,lulus,pindah,keluar`) tetap di controller seperti sekarang.

## 4. `RaporPdfDataBuilder` — Fallback ke Kelas Terakhir

**File**: `app/Domains/Akademik/Services/RaporPdfDataBuilder.php:45`

Ubah:
```php
$kelas = $siswa->kelas;
```
Menjadi:
```php
$kelas = $siswa->kelas ?? Kelas::find($siswa->kelas_terakhir_id);
abort_if($kelas === null, 404);
```

**Known-limitation, didokumentasikan (bukan diperbaiki di spec ini)**: `kelas_terakhir_id` cuma menyimpan 1 kelas "terakhir diketahui". Kalau siswa pernah pindah kelas beberapa kali sebelum status berubah jadi non-aktif, cetak ulang rapor untuk semester LAMA (bukan semester terakhir) akan tetap menampilkan kelas TERAKHIR, bukan kelas yang benar untuk semester itu. Ini bukan regresi baru — `$siswa->kelas` hari ini pun sudah selalu mengambil kelas TERKINI siswa untuk semester manapun yang dicetak (tidak historis per-semester).

## 5. Known-Limitation Tambahan (didokumentasikan, tidak diperbaiki)

FK `kelas_terakhir_id` menggunakan `ON DELETE SET NULL` ke `kelas.id`. Kalau nanti ada fitur hapus-Kelas ditambahkan (hari ini tidak mungkin — `KelasController` tidak punya `destroy()`), snapshot di `kelas_terakhir_id` akan hilang diam-diam tanpa constraint yang mendeteksi (CHECK constraint yang ada cuma mengunci `kelas_id`, tidak menyentuh `kelas_terakhir_id`). Tidak perlu solusi sekarang — dicatat supaya tidak jadi kejutan kalau fitur hapus-Kelas dibangun di masa depan.

## 6. Titik Konsumen — Perlakuan per Kelompok

**5 titik "gratis"** (tidak butuh perubahan kode — begitu `kelas_id` null, query `where('kelas_id', ...)` otomatis tidak match):
- `ProsesKenaikanKelasAction`
- `RaporCalculationService.php:27`
- `Guru\RaporController.php:94` (listing biasa)
- `Guru\RaporController.php:143` (navigasi next/previous siswa)
- `DashboardStatsService.php:138`

**2 titik yang SUDAH ditambal sebelumnya** (di luar spec ini, referensi saja):
- `SubmitPengajuanRaporAction.php:37` — sudah ditambal minimal+terisolasi sebelum spec ini ditulis.
- `PresensiAggregationService.php:20` — sudah benar sejak awal (pola rujukan).

**1 titik butuh kode eksplisit**: `RaporPdfDataBuilder.php:45` (lihat §4).

**1 titik di luar scope, catatan serah-terima** (lihat §7 Non-Goals): `JenisTagihanSasaranMatcher` + `TagihanBillingGenerator`.

## 7. Non-Goals

- **Validasi tingkat Kenaikan Kelas + konsep "siswa tinggal kelas"** — spec/topik terpisah (Kelompok B), tidak digarap di sini.
- **`JenisTagihanSasaranMatcher::resolveTargetSiswa()`/`countTotalSiswaPool()` dan `TagihanBillingGenerator`** — base query-nya tidak filter status SAMA SEKALI (independen dari masalah `kelas_id`; siswa non-aktif dengan kriteria sasaran non-kelas, mis. "semua siswa", tetap akan kena tagihan baru walau `kelas_id` sudah null). **TIDAK disentuh di spec ini** karena file yang sama sedang aktif digarap di spec Keuangan lain (`keuangan-v2`, dikonfirmasi lewat `git log` — ada histori kerja baru-baru ini di area billing dan referensi eksplisit ke file ini di brainstorming redesain form Jenis Tagihan). **Catatan serah-terima untuk direlay ke spec/sesi Keuangan yang berjalan paralel itu**: base query `resolveTargetSiswa()`/`countTotalSiswaPool()` perlu ditambah `->where('status', StatusSiswa::Aktif->value)`, terpisah dan tidak bergantung pada mekanisme `kelas_terakhir_id` di spec ini.
- **Akurasi kelas historis per-semester** di `RaporPdfDataBuilder` — batasan lama (lihat §4), tidak diperbaiki.
- **Migrasi/pindah branch** — tetap di `akademik-v2`.
- **Tidak ada perubahan pada halaman/endpoint lain** di luar yang disebutkan eksplisit di atas.

## 8. Test Plan

| # | Area | Skenario |
|---|---|---|
| 1 | `UpdateStatusSiswaAction` | Aktif → Keluar: `kelas_terakhir_id` = `kelas_id` lama, `kelas_id` jadi null. |
| 2 | `UpdateStatusSiswaAction` | Keluar → Aktif: `kelas_id` pulih dari `kelas_terakhir_id`, `kelas_terakhir_id` jadi null lagi. |
| 3 | `UpdateStatusSiswaAction` | **Siklus ganda**: Aktif→Keluar→Aktif→Keluar lagi (dengan `kelas_id` yang BERBEDA di siklus kedua, mis. siswa sempat pindah kelas saat aktif kembali) — assert `kelas_terakhir_id` di akhir siklus kedua adalah snapshot dari kelas SAAT ITU, bukan sisa data basi dari siklus pertama. |
| 4 | `UpdateStatusSiswaAction` | **Idempotency**: panggil dengan status target = status sekarang (mis. sudah Keluar, dipanggil Keluar lagi) — assert `kelas_id`/`kelas_terakhir_id` tidak berubah. |
| 5 | CHECK constraint | Percobaan insert/update mentah lewat query builder yang melanggar invariant (`status != 'aktif'` dengan `kelas_id` terisi) — assert exception database dilempar (bukti constraint aktif di level DB). |
| 6 | Migration | Seed siswa non-aktif dengan `kelas_id` terisi sebelum migration backfill dijalankan (test migration terpisah, atau assert langsung lewat `RefreshDatabase` + query pasca-migrate) — assert `kelas_terakhir_id` terisi benar, `kelas_id` null setelahnya. |
| 7 | `RaporPdfDataBuilder` | Cetak rapor untuk siswa berstatus Keluar (`kelas_id` null, `kelas_terakhir_id` terisi) — assert tidak crash, data kelas terbaca dari `kelas_terakhir_id`. |
| 8 | Kelompok "gratis" — 1 representatif | `ProsesKenaikanKelasAction`: kelas dengan siswa aktif+keluar campur — assert siswa keluar tidak ikut naik/lulus. **Sertakan komentar eksplisit di kode test**: menjelaskan bahwa `RaporCalculationService` dan `DashboardStatsService` punya pola query identik (`where('kelas_id', ...)`) dan dianggap terbukti benar oleh test ini + invariant CHECK constraint di database, sengaja tidak diduplikasi per file. |
| 9 | `Guru\RaporController` (listing) | Kelas dengan siswa aktif+keluar campur — assert siswa keluar tidak muncul di listing. |
| 10 | `Guru\RaporController` (navigasi next/previous) | Kelas dengan siswa aktif+keluar campur dalam urutan tertentu — assert navigasi "siswa berikutnya/sebelumnya" cuma menghitung siswa aktif, tidak meleset index karena siswa keluar seharusnya sudah tidak ada di daftar sama sekali. |

Test #9 dan #10 WAJIB terpisah dan eksplisit (bukan diringkas jadi 1) karena navigasi next/previous punya logic tambahan (index-based) di luar sekadar filter WHERE — tidak otomatis terbukti benar hanya dari fakta "kelas_id null tidak match where clause".
