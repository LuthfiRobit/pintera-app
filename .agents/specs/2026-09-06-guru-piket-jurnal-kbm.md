# Guru Piket — Pengisian Jurnal KBM Real-Time Pengganti (Proyek C, Fase 1)

## 1. Latar Belakang

Rangkaian ketiga dari diskusi presensi/jurnal KBM sesi ini (setelah Proyek A — batas edit presensi, SELESAI; dan Proyek B — akses Waka Kurikulum lintas-guru untuk koreksi, backlog terpisah).

**Masalah**: `Guru\Akademik\JurnalKbmController` (semua method) digerbangi `authorizeMilikGuru()` — HANYA guru dengan `guru_id` sama persis dengan `$sesi->guru_id` yang bisa akses. Tidak ada jalan resmi bagi orang lain mengisi presensi/jurnal kalau guru pemilik sesi berhalangan hadir mendadak (sakit, izin dadakan). Kelas jadi kosong datanya selamanya, atau seseorang harus login "sebagai" guru itu — jelas salah secara etis/keamanan.

**Beda dari Proyek B**: Proyek B soal KOREKSI SETELAH KEJADIAN (Waka Kurikulum edit data lama). Proyek C ini soal PENGISIAN NORMAL REAL-TIME hari itu juga — akar masalah berbeda total.

**Realita lapangan** (dikonfirmasi lewat diskusi): sekolah Indonesia lazim punya "Guru Piket" — jadwal bergilir per hari (biasanya per hari-dalam-minggu, didokumentasikan sebagai "Program Piket Mingguan" yang ditandatangani Kepala Sekolah tiap semester), terpisah dari Wali Kelas. Guru piket menangani kelas yang gurunya tidak masuk, wewenangnya HARI ITU SAJA (bukan mundur berhari-hari seperti Proyek A). Keputusan "guru X berhalangan" adalah keputusan manusia di lapangan saat itu juga, BUKAN dicek otomatis dari status approval `PengajuanIzinCuti` (proses itu kadang diurus setelah kejadian, terlalu lambat untuk kebutuhan real-time).

## 2. Keputusan Desain

1. **Dibangun 2 fase.** Fase 1 (spec ini) didesain sudah mengantisipasi Fase 2 (pelaporan/Dapodik) supaya tidak ada rework skema data nanti — lihat §5 untuk cakupan Fase 2 yang sengaja TIDAK dikerjakan sekarang.
2. **2 lapis data piket**:
   - `JadwalPiketMingguan` — pola berulang per hari-dalam-minggu, terikat 1 `semester_id` (periode berlaku). Ini dokumen resmi "Program Piket Mingguan".
   - `PiketHarian` — assignment per-tanggal konkret, di-generate otomatis dari `JadwalPiketMingguan` (`sumber = 'dari_jadwal_mingguan'`), TAPI admin tetap bisa override manual per-tanggal individual (`sumber = 'override_manual'`) untuk fleksibilitas lapangan (tukar jadwal dadakan).
3. **Wewenang piket dibatasi HARI INI SAJA** — bukan model N-hari-mundur seperti Proyek A. Guru piket cuma bisa akses sesi dengan `tanggal` = hari ini.
4. **Generate `PiketHarian` skip hari libur akademik** (`KalenderAkademik` yang sudah ada), dijalankan sekali batch mencakup seluruh rentang semester saat `JadwalPiketMingguan` dibuat/diaktifkan (BUKAN cron/job berjalan terus).
5. **Edit `JadwalPiketMingguan` di tengah semester → AUTO-REGENERATE `PiketHarian`** ke depan, dengan 3 pengecualian mutlak (baris "beku", tidak pernah disentuh generate ulang):
   - `sumber = 'override_manual'`.
   - `tanggal < hari ini`.
   - `tanggal >= hari ini` TAPI sudah ada `SesiPembelajaran.diisi_oleh_guru_id` yang cocok guru+tanggal+lembaga baris itu (piket itu sudah benar-benar dipakai).
6. **`KalenderAkademik` berubah SETELAH batch generate awal** (libur ditambah belakangan) — DITERIMA sebagai keterbatasan Fase 1, TIDAK dibangun mekanisme otomatis pembersihan. Admin koreksi manual lewat halaman override `PiketHarian` yang sudah ada. Ini keterbatasan yang disadari, bukan celah tak terduga.
7. **Kolom akuntabilitas**: `SesiPembelajaran.diisi_oleh_guru_id` (nullable FK ke Guru) — diisi HANYA kalau guru yang submit ≠ `sesi->guru_id` pemilik asli. Tidak perlu tabel/FK terpisah ke `PiketHarian` — kombinasi unique constraint `(lembaga_id, guru_id, tanggal)` di `piket_harian` membuat pengecekan "PiketHarian ini sudah dipakai" bisa diturunkan (derived) tanpa ambigu dari kolom ini saja.
8. **Permission baru `piket.kelola`** (mengatur jadwal piket) → diberikan ke role `wakasek_kesiswaan` (pemilik domain natural, sudah pegang Kasus/BK) dan `operator_akademik` (sudah pegang hampir semua `.kelola` operasional lain). TIDAK ADA permission baru untuk AKSI mengisi presensi — guru piket tetap pakai `presensi.isi` yang sudah mereka punya sebagai guru; yang berubah cuma sesi MANA yang boleh mereka akses.
9. **Titik akses (kepemilikan) WAJIB pakai 1 helper bersama**, dipanggil dari 2 tempat (`JurnalKbmController::authorizeMilikGuru()` dan `UpdateJurnalPresensiRequest::authorize()`) — supaya logic "pemilik ATAU piket hari ini" tidak pernah drift antar 2 tempat itu.
10. **`RecordJurnalDanPresensiAction` (A2, sudah ada) dapat 1 parameter tambahan opsional** untuk isi `diisi_oleh_guru_id` — perubahan ADITIF, tidak mengubah alur notifikasi WA yang sudah ada.
11. **`index()` tambah seksi "Sesi Piket Hari Ini"** — HANYA muncul (di level HTML, bukan cuma logic) kalau guru yang login memang piket hari ini.

## 3. Arsitektur & Komponen

### 3.1 Data Model

**`jadwal_piket_mingguan`**: `id`, `lembaga_id` (FK), `guru_id` (FK Guru), `hari` (tinyint 0-6), `semester_id` (FK), `dibuat_oleh_user_id` (FK User, jejak siapa input — dipakai Fase 2). Unique constraint `(lembaga_id, guru_id, hari, semester_id)` — 1 guru tidak bisa didaftarkan piket hari yang sama dua kali dalam 1 semester.

**`piket_harian`**: `id`, `lembaga_id` (FK), `guru_id` (FK Guru), `tanggal` (date), `sumber` (native DB enum: `dari_jadwal_mingguan`, `override_manual`), `jadwal_piket_mingguan_id` (nullable FK, jejak asal generate). **Unique constraint `(lembaga_id, guru_id, tanggal)`** — wajib, dasar semua pengecekan "sudah dipakai" di §2.5 dan §3.3.

**`sesi_pembelajaran`**: tambah kolom `diisi_oleh_guru_id` (nullable FK ke Guru).

### 3.2 Actions

**`GenerateJadwalPiketHarianAction`** — dipanggil saat lembaga BELUM punya `PiketHarian` sama sekali untuk semester itu (lihat kriteria pemanggilan eksplisit di §3.4). Untuk tiap tanggal dalam rentang `semester.tanggal_mulai` s.d. `semester.tanggal_selesai` yang cocok `hari`-nya, DAN bukan hari libur (`KalenderAkademik`), buat 1 baris `PiketHarian` (`sumber = 'dari_jadwal_mingguan'`, `jadwal_piket_mingguan_id` = baris asal). **WAJIB idempotent**: pakai `firstOrCreate(['lembaga_id' => ..., 'guru_id' => ..., 'tanggal' => ...], [...])` (dilindungi unique constraint `(lembaga_id, guru_id, tanggal)`), BUKAN asumsi insert bersih — jaring pengaman kedua kalau kriteria pemanggilan di §3.4 suatu saat salah diterapkan di titik panggil lain, atau ada race condition kecil.

**`RegenerateJadwalPiketHarianAction`** — dipanggil SETIAP KALI `JadwalPiketMingguan` untuk 1 semester diedit (tambah/ubah/hapus baris pola mingguan). 3 langkah bernomor, WAJIB urutan ini, di dalam **1 `DB::transaction()`**:
1. **Ambil kandidat**: query `PiketHarian::where('lembaga_id', $lembagaId)->where('tanggal', '>=', now()->toDateString())->where('sumber', 'dari_jadwal_mingguan')->get()`.
2. **Filter buang yang sudah dipakai**: dari kandidat itu, buang (jangan masuk daftar hapus) baris mana pun yang punya `SesiPembelajaran` dengan `diisi_oleh_guru_id` = `PiketHarian.guru_id`, `tanggal` = `PiketHarian.tanggal`, `lembaga_id` = `PiketHarian.lembaga_id`.
3. **Baru delete + generate ulang**: hapus baris yang lolos filter langkah 2, lalu panggil ulang logic `GenerateJadwalPiketHarianAction` (atau logic yang sama) untuk pola `JadwalPiketMingguan` TERBARU, rentang tanggal `>= hari ini` s.d. akhir semester, skip tanggal yang MASIH punya baris `PiketHarian` (baris yang tidak ikut terhapus di langkah 3 — baik karena `override_manual`, `tanggal < hari ini`, atau "sudah dipakai").

```php
final class RegenerateJadwalPiketHarianAction
{
    public function execute(int $lembagaId, int $semesterId): void
    {
        DB::transaction(function () use ($lembagaId, $semesterId) {
            // Langkah 1: ambil kandidat
            $kandidat = PiketHarian::where('lembaga_id', $lembagaId)
                ->where('tanggal', '>=', now()->toDateString())
                ->where('sumber', 'dari_jadwal_mingguan')
                ->get();

            // Langkah 2: filter buang yang sudah dipakai
            $bolehDihapus = $kandidat->reject(function (PiketHarian $baris) {
                return SesiPembelajaran::where('diisi_oleh_guru_id', $baris->guru_id)
                    ->where('tanggal', $baris->tanggal)
                    ->where('lembaga_id', $baris->lembaga_id)
                    ->exists();
            });

            // Langkah 3: delete + generate ulang
            PiketHarian::whereIn('id', $bolehDihapus->pluck('id'))->delete();
            // ... generate ulang pola JadwalPiketMingguan terbaru untuk semester ini,
            // skip tanggal yang masih ada baris PiketHarian (query ulang existing dulu),
            // skip hari libur akademik (KalenderAkademik).
        });
    }
}
```

(Detail lengkap logic generate ulang di langkah 3 — termasuk query "skip tanggal yang masih ada baris" — dirinci penuh di task implementasi, bukan di spec ini; poin di sini adalah URUTAN 3 LANGKAH dan pembungkusan transaksi yang WAJIB, bukan implementasi baris demi baris.)

**`PiketAccessChecker`** (Service, bukan Action — dipanggil dari 2 tempat berbeda, bukan use-case tunggal):

```php
final class PiketAccessChecker
{
    public function bisaAkses(SesiPembelajaran $sesi, Guru $guru): bool
    {
        if ($sesi->guru_id === $guru->id) {
            return true;
        }

        return PiketHarian::where('lembaga_id', $sesi->lembaga_id)
            ->where('guru_id', $guru->id)
            ->where('tanggal', $sesi->tanggal->toDateString())
            ->exists();
    }
}
```

**Baris `->where('lembaga_id', $sesi->lembaga_id)` WAJIB ada persis seperti ini** — scoping ke `lembaga_id` milik SESI yang diakses, BUKAN `lembaga_id` milik guru yang login. Ini yang mencegah guru piket lembaga A membuka sesi lembaga B walau by some kesalahan data guru itu py `guru_id` yang somehow match di 2 lembaga berbeda.

### 3.3 Titik Pemanggilan `PiketAccessChecker`

- **`JurnalKbmController::authorizeMilikGuru()`**: `abort_if($guru === null || ! $piketAccessChecker->bisaAkses($sesi, $guru), 403);` — menggantikan pengecekan lama yang cuma `$sesi->guru_id !== $guru->id`.
- **`UpdateJurnalPresensiRequest::authorize()`**: `return $guru !== null && $sesi instanceof SesiPembelajaran && app(PiketAccessChecker::class)->bisaAkses($sesi, $guru);` — logic SAMA PERSIS, method yang sama, tidak ditulis ulang beda.
- **`RecordJurnalDanPresensiAction::execute()`**: tambah parameter `?int $diisiOlehGuruId = null` (nullable, default null — backward compatible dgn pemanggilan lain kalau ada). Kalau tidak null, `$sesi->update(['materi' => ..., 'diisi_oleh_guru_id' => $diisiOlehGuruId])` di dalam transaksi yang sudah ada. Controller `update()` menghitung ini: `$diisiOlehGuruId = $guru->id !== $sesi->guru_id ? $guru->id : null;`.
- **`JurnalKbmController::index()`**: tambah query terpisah — cek dulu `PiketHarian::where('lembaga_id', $guru->lembaga_id)->where('guru_id', $guru->id)->where('tanggal', now()->toDateString())->exists()`. Kalau `true`, tambah variabel `$sesiPiket` (query `SesiPembelajaran::where('lembaga_id', $guru->lembaga_id)->where('guru_id', '!=', $guru->id)->whereDate('tanggal', now())->get()`) ke view. View HANYA merender blok "Sesi Piket Hari Ini" kalau variabel itu ADA dan tidak kosong (`@if($sesiPiket->isNotEmpty())`) — bukan render blok kosong.

### 3.4 Admin — Setup Jadwal Piket

- Halaman admin baru (rute `admin/piket-guru`, permission `piket.kelola`): kelola `JadwalPiketMingguan` (pilih guru + hari + semester) — submit memicu `GenerateJadwalPiketHarianAction` atau `RegenerateJadwalPiketHarianAction`, **DIPANGGIL SINKRON di controller yang sama, dalam request/response cycle yang sama** — BUKAN `dispatch()`, BUKAN job/queue, BUKAN `ShouldQueue`. Controller memanggil Action itu langsung sebelum `return redirect()`, sama seperti pola Action lain di seluruh project ini (`UpdateHariAktifLembagaAction`, dst — tidak ada satu pun Action akademik di project ini yang di-queue).
- **Kriteria PASTI pemilihan Action** (berbasis kondisi data, BUKAN jenis form action "tambah baris" vs "pertama dibuat" — itu ambigu): sebelum memanggil, controller cek `PiketHarian::where('lembaga_id', $lembagaId)->where('tanggal', '>=', $semester->tanggal_mulai)->exists()`. Kalau `false` (lembaga ini belum PERNAH punya `PiketHarian` untuk semester itu sama sekali) → panggil `GenerateJadwalPiketHarianAction`. Kalau `true` (sudah ada, apa pun jumlahnya) → SELALU panggil `RegenerateJadwalPiketHarianAction`, TIDAK PEDULI jenis perubahan yang terjadi di form `JadwalPiketMingguan` (tambah baris baru, ubah baris existing, atau hapus baris) — semuanya lewat jalur Regenerate begitu sudah pernah ada data, supaya baris "beku" (§2.5) selalu terlindungi konsisten.
- Halaman terpisah/tab untuk override manual `PiketHarian` per-tanggal (edit/hapus baris individual).

## 4. Skenario Test

1. `PiketAccessChecker::bisaAkses()` — guru pemilik sesi → `true`. Guru piket lembaga SAMA, tanggal SAMA → `true`. Guru piket lembaga LAIN (`PiketHarian.lembaga_id` beda dari `sesi.lembaga_id`) → `false` (**test eksplisit wajib**: skenario cross-lembaga, assert ditolak). Guru bukan pemilik & bukan piket → `false`.
2. `RegenerateJadwalPiketHarianAction` — baris `dari_jadwal_mingguan` tanggal depan tanpa akuntabilitas terisi → dihapus & digenerate ulang sesuai pola baru. Baris `override_manual` → TIDAK disentuh. Baris `tanggal < hari ini` → TIDAK disentuh. Baris tanggal depan yang SUDAH punya `SesiPembelajaran.diisi_oleh_guru_id` cocok → TIDAK disentuh (test eksplisit skenario ini).
3. `GenerateJadwalPiketHarianAction` — tanggal yang jatuh di `KalenderAkademik` libur → TIDAK dibuat baris `PiketHarian`.
4. Guru piket hari ini submit jurnal untuk sesi guru lain → berhasil, `SesiPembelajaran.diisi_oleh_guru_id` terisi dengan ID guru piket (BUKAN null, BUKAN ID guru pemilik asli).
5. Guru pemilik asli submit jurnal untuk sesinya sendiri → `diisi_oleh_guru_id` TETAP `null` (bukan diisi ID guru itu sendiri — kolom ini cuma terisi kalau BEDA orang).
6. `index()` — guru YANG PIKET hari ini → seksi "Sesi Piket Hari Ini" muncul di HTML (assert `assertSee`), berisi sesi guru lain yang relevan.
7. `index()` — guru YANG BUKAN piket hari ini → seksi "Sesi Piket Hari Ini" **TIDAK TERENDER SAMA SEKALI** di level HTML (**test eksplisit wajib**: `assertDontSee('Sesi Piket Hari Ini')`, bukan cuma cek variabel controller kosong).
8. `JadwalPiketMingguanController` (admin) — submit create/update memicu Generate/Regenerate Action secara SINKRON dalam request yang sama (test: assert response balik SETELAH baris `PiketHarian` sudah benar-benar ada di DB, tanpa perlu `Bus::fake()`/queue assertion — kalau ada `Bus::fake()` dipakai dan test masih lolos, itu tanda salah, karena action ini TIDAK di-dispatch sebagai job).
9. Kriteria pemilihan Action (§3.4) — lembaga BELUM punya `PiketHarian` sama sekali utk semester itu → `GenerateJadwalPiketHarianAction` yang terpanggil. Lembaga SUDAH punya `PiketHarian` (walau cuma 1 baris) utk semester itu, lalu admin TAMBAH baris baru (bukan edit baris lama) → tetap `RegenerateJadwalPiketHarianAction` yang terpanggil, BUKAN Generate (test eksplisit skenario "tambah baris di semester yang sudah berjalan" ini, pastikan baris lama yang sudah dipakai/`override_manual` tetap tidak tersentuh).
10. `GenerateJadwalPiketHarianAction` dipanggil 2x berturut-turut dengan input identik (simulasi race condition/kesalahan titik panggil) → panggilan kedua TIDAK membuat baris duplikat (idempotent via `firstOrCreate`), jumlah baris `PiketHarian` di DB tetap sama setelah kedua panggilan.
11. Regresi — `RecordJurnalDanPresensiAction` dipanggil TANPA parameter `diisiOlehGuruId` (pemanggilan lama, guru pemilik asli) tetap berjalan identik seperti sebelumnya, notifikasi WA (A2) tidak terpengaruh.
12. Regresi — fitur Scan Presensi Kartu Digital Siswa (A3, `resolveKartu()`) tetap berfungsi untuk guru pemilik MAUPUN guru piket yang sedang mengisi sesi pengganti (karena `authorizeMilikGuru()` dipakai `resolveKartu()` juga, otomatis ikut mendukung piket).
13. Permission `piket.kelola` — user tanpa permission ini ditolak akses halaman admin `admin/piket-guru`.

## 5. Di Luar Cakupan (Fase 2 — Backlog Terpisah, TIDAK Dikerjakan Sekarang)

- **`LaporanPiket`** — ringkasan aktivitas piket (siswa terlambat, tamu, catatan, link ke Kasus existing) diisi guru piket per hari piket.
- **Alur verifikasi Kepala Sekolah** untuk `JadwalPiketMingguan` — reuse pola `VerifyRppAction`/`VerifyPengajuanRaporAction` yang sudah ada di codebase. Fase 1 cuma simpan `dibuat_oleh_user_id`, tidak ada status verifikasi.
- **Cetak dokumen** "Program Piket Mingguan" (dari `JadwalPiketMingguan`) dan "Buku Laporan Hasil Piket" untuk keperluan Dapodik.
- **Mekanisme otomatis pembersihan `PiketHarian`** saat `KalenderAkademik` berubah setelah batch generate awal — diterima sebagai keterbatasan Fase 1 (§2.6), koreksi manual admin.
- **Proyek B** (Waka Kurikulum akses lintas-guru untuk koreksi setelah kejadian) — backlog terpisah total, tidak disentuh spec ini.
