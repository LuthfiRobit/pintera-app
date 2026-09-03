# Spec: Perbaikan Audit Menyeluruh Modul Akademik

**Tanggal**: 2026-09-03
**Branch**: `akademik-v2`
**Konteks**: Audit menyeluruh (business flow + backend + frontend) terhadap modul Akademik dilakukan lintas 3 sisi (Admin/Data Master, Guru/Pengajaran, Rapor & Kenaikan Kelas) plus temuan langsung terhadap sidebar. 11 temuan dikelompokkan 3 prioritas. Spec ini menutup semuanya kecuali item yang eksplisit ditandai "tidak perlu kode" di §4.

## 1. Keputusan Desain yang Sudah Final (dikonfirmasi user, jangan tanya ulang)

- **Menu stub sidebar** (Nilai & Rapor/Jadwal Pelajaran/Presensi Saya untuk Siswa; Nilai Anak/Jadwal Anak/Riwayat Izin-Sakit Anak untuk Orang Tua) — **DISEMBUNYIKAN dari sidebar**, bukan dibangun/dialihkan. Konsisten dengan preseden pembekuan menu PPDB (24 Agustus 2026 & 2 September 2026) — comment-out, bukan hapus, supaya gampang dikembalikan kalau halaman sungguhan sudah dibangun sebagai proyek fitur terpisah nanti.
- **Rekap Kehadiran untuk guru mapel (bukan wali kelas)** — **diperbaiki dengan filter hak akses**, bukan dibiarkan atau dibuka penuh: guru mapel BISA melihat Rekap Kehadiran, tapi datanya difilter hanya untuk sesi yang dia ajar sendiri (`guru_id` match di `sesi_pembelajaran`). Wali kelas tetap dapat rekap penuh lintas-mapel (perilaku sekarang, tidak berubah).

## 2. Prioritas Tinggi

### 2.1. Guru bisa mereset rapor yang sudah Disetujui/Diverifikasi kembali ke Diajukan

**Masalah**: `SubmitPengajuanRaporAction::execute()` (`app/Domains/Akademik/Actions/Rapor/SubmitPengajuanRaporAction.php:44-63`) tidak mengecek status `PengajuanRapor` saat ini sebelum reset ke `Diajukan` + reset `ApprovalRequest` ke step pertama. `Guru\RaporController::ajukan()` (baris 204-222) juga tidak ada guard status.

**Perbaikan**: tambah guard di awal `execute()` — kalau `PengajuanRapor` sudah ada DAN statusnya `StatusPengajuanRapor::Diverifikasi` atau `StatusPengajuanRapor::Disetujui`, lempar `ValidationException` dengan pesan jelas ("Rapor kelas ini sedang/sudah diverifikasi, tidak bisa diajukan ulang. Hubungi admin kalau perlu revisi."). Status `Draft`, `Diajukan` (belum diproses), dan `Ditolak` tetap boleh submit/resubmit seperti sekarang (itu alur normal).

```php
// Di awal method execute(), setelah $siswaBelumLengkap check:
$existing = PengajuanRapor::where('kelas_id', $kelas->id)->where('semester_id', $semester->id)->first();
if ($existing && in_array($existing->status, [StatusPengajuanRapor::Diverifikasi, StatusPengajuanRapor::Disetujui], true)) {
    throw ValidationException::withMessages([
        'status' => "Rapor kelas ini sudah berstatus \"{$existing->status->label()}\" dan tidak bisa diajukan ulang dari halaman ini.",
    ]);
}
```

**Frontend**: `resources/views/portals/guru/rapor/catatan/index.blade.php` — saat ini cuma menampilkan banner untuk status `Ditolak` (baris 60-67). Tambah banner informatif untuk status `Diverifikasi`/`Disetujui` juga (mis. "Rapor kelas ini sudah [status] pada [diajukan_pada]") dan sembunyikan/disable tombol "Ajukan Rapor" untuk kedua status itu (bukan cuma berdasarkan kelengkapan catatan seperti sekarang).

**Test**: Feature test memanggil `SubmitPengajuanRaporAction` dua kali — pertama submit normal (status jadi Diajukan), lalu manual set status ke `Disetujui`, lalu panggil lagi dan assert `ValidationException` dilempar dengan pesan yang tepat, dan `current_step_id`/`status` di `ApprovalRequest` TIDAK berubah.

### 2.2. Kenaikan Kelas — tidak ada indikator visual kelas yang sudah diproses

**Masalah**: `KenaikanKelasController::index()` (`app/Http/Controllers/Admin/KenaikanKelasController.php:20-41`) sudah eager-load `withCount('siswa')` ke `kelasLamaList`, TAPI view tidak memanfaatkan angka ini untuk memberi sinyal visual kalau sebuah kelas sudah 0 siswa (artinya sudah diproses kenaikan kelasnya / kosong).

**Perbaikan**: di `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`, tampilkan badge "Sudah diproses / kosong" (warna netral/abu) di baris kelas manapun yang `siswa_count === 0`, dan secara default JANGAN centang/include kelas itu di form submit massal (biarkan admin sadar dan tetap bisa pilih manual kalau memang mau, tapi bukan default). Tidak perlu tabel/log baru — data `siswa_count` sudah tersedia dari controller, ini murni perbaikan tampilan.

**Test**: Feature test hit halaman index setelah 1 kelas diproses kenaikan kelasnya (siswa sudah pindah semua), assert response mengandung indikator/badge tsb untuk kelas itu (`assertSee`).

### 2.3. Kurikulum Assignment — hapus/ubah tanpa peringatan dampak ke Kelas existing

**Masalah**: `KurikulumAssignmentController::destroy()` (`app/Http/Controllers/Admin/KurikulumAssignmentController.php:130-138`) dan `UpdateKurikulumAssignmentAction` tidak mengecek apakah ada `Kelas` yang `kurikulum`-nya sudah snapshot dari assignment ini. Tool resync (`admin.kurikulum-assignment.resync`) sudah ada tapi tidak ditautkan dari halaman terkait.

**Perbaikan**:
1. `KurikulumAssignmentController::destroy()` — SEBELUM delete, hitung `Kelas::where('lembaga_id', $assignment->lembaga_id)->where('tahun_ajaran_id', $assignment->tahun_ajaran_id)->where('tingkat', $assignment->tingkat)->where('kurikulum', $assignment->kurikulum)->count()`. Kalau > 0, **tolak** hapus dengan pesan jelas ("Assignment ini masih dipakai N Kelas. Reassign kelas-kelas itu dulu atau gunakan tool Resync.") — konsisten dengan pola `DeletePolaJamAction` yang sudah benar (blokir, bukan cuma warning).
2. `resources/views/admin/kurikulum-assignment/index.blade.php` dan `edit.blade.php` — tambah link "Cek & Perbaiki Kurikulum/Fase" (`route('admin.kurikulum-assignment.resync')`) yang sudah ada tool-nya, supaya admin tahu jalan keluarnya kalau memang perlu ganti assignment yang sudah dipakai.

**Test**: Feature test buat KurikulumAssignment + 1 Kelas yang snapshot dari situ, coba `destroy()`, assert redirect back dengan error (bukan 200/deleted), assert row assignment masih ada di DB.

### 2.4. Sembunyikan 6 menu sidebar stub (Ruang Siswa & Ruang Orang Tua)

**Perbaikan**: comment-out 6 item di `resources/views/layouts/sidebar.blade.php` (baris 29-31 untuk Ruang Siswa: Nilai & Rapor/Jadwal Pelajaran/Presensi Saya; baris 39-41 untuk Ruang Orang Tua: Nilai Anak/Jadwal Anak/Riwayat Izin-Sakit Anak), dengan komentar developer menjelaskan alasan (halaman belum dibangun, data ringkas sudah ada di Dashboard) — pola identik dengan comment-out menu PPDB yang sudah ada di file yang sama.

**Test**: Feature test login sebagai siswa/orang tua, assert sidebar TIDAK mengandung teks "Nilai & Rapor"/"Jadwal Anak"/dst (assertDontSee), dan route `dalam-pengembangan` sendiri boleh tetap ada (tidak perlu dihapus, cuma link ke sana yang hilang).

## 3. Prioritas Sedang

### 3.1. Guru mapel bisa lihat Rekap Kehadiran (difilter ke sesinya sendiri)

**Perbaikan**:
1. `PresensiAggregationService::agregasiPerKelas()` — tambah parameter opsional `?int $guruId = null`. Kalau diisi, tambah `->where('sesi_pembelajaran.guru_id', $guruId)` ke query count. Signature baru: `agregasiPerKelas(int $kelasId, ?Semester $semester = null, ?int $guruId = null): Collection`.
2. `RekapKehadiranController::index()` — ganti `Kelas::where('wali_kelas_guru_id', $guru->id)` (baris 61) jadi mencakup JUGA kelas yang guru ajar (via `JadwalPelajaran::where('guru_id', $guru->id)->distinct()->pluck('kelas_id')`), digabung dengan kelas wali. Tandai per-kelas di `$kelasList` apakah guru ini wali-nya atau bukan (`is_wali` flag per item). Saat memanggil `agregasiPerKelas()`, pass `$guruId = $guru->id` HANYA kalau `$kelas->wali_kelas_guru_id !== $guru->id` (guru mapel → difilter); kalau dia wali kelas, pass `null` (rekap penuh, perilaku sekarang).
3. View `rekap.blade.php` — tampilkan indikator kecil ("Rekap disaring untuk mapel Anda" vs "Rekap penuh kelas") sesuai `is_wali`.

**Test**: Feature test dengan guru mapel (bukan wali) yang mengajar 1 mapel di sebuah kelas — buat sesi/presensi untuk mapel itu DAN mapel lain (guru berbeda) di kelas yang sama — assert rekap yang dikembalikan cuma menghitung presensi dari sesi guru tsb, bukan total kelas.

### 3.2. Jurnal & Presensi hardcode ke "hari ini", tidak bisa isi susulan

**Perbaikan**: `JurnalKbmController::index()` — terima `?tanggal` dari query string (`$request->query('tanggal')`), default ke `now()` kalau kosong. Validasi: tanggal tidak boleh di masa depan (`Carbon::parse($tanggal)->isFuture()` → abort/redirect dengan pesan), dan tidak boleh lebih dari N hari ke belakang (batasi ke rentang semester aktif siswa, pakai `tanggal >= semester->tanggal_mulai`). Tambah navigasi tanggal sederhana (tombol "Hari Sebelumnya"/"Hari Ini"/date picker) di `jurnal-kbm/index.blade.php`.

**Test**: Feature test akses `guru.jurnal-kbm.index` dengan `?tanggal=` kemarin, assert sesi kemarin (bukan hari ini) yang ditampilkan; assert `?tanggal=` besok ditolak/redirect.

### 3.3. Tidak ada halaman Riwayat Persetujuan Rapor

**Perbaikan**: `PersetujuanController::index()` — tambah dukungan `?tab=riwayat` (default tetap tab aktif seperti sekarang). Kalau `riwayat`, query berubah jadi `PengajuanRapor::whereIn('status', [StatusPengajuanRapor::Disetujui, StatusPengajuanRapor::Ditolak])` di-scope ke lembaga aktor (lewat relasi `kelas.lembaga_id`), bukan `statusUntukAktor()`. Tambah tab UI di `persetujuan/index.blade.php` ("Menunggu Keputusan Saya" / "Riwayat").

**Test**: Feature test approve 1 pengajuan, lalu akses `?tab=riwayat`, assert pengajuan itu muncul; assert TIDAK muncul di tab default (menunggu).

### 3.4. Race condition approve/reject rapor ganda

**Perbaikan**: `ApprovePengajuanRaporAction::execute()` dan `VerifyPengajuanRaporAction::execute()` — bungkus isi method dengan `DB::transaction()` (kalau belum) dan panggil `PengajuanRapor::lockForUpdate()->findOrFail($pengajuanRapor->id)` di awal transaksi sebelum baca/tulis status, ganti argumen yang dipakai selanjutnya ke instance yang di-lock ini bukan yang dari controller.

**Test**: Test unit/feature yang mensimulasikan 2 pemanggilan `execute()` berurutan cepat (tidak perlu concurrency asli) dan assert status akhir konsisten (bukan double-processed) — cek juga `ApprovalLog` tidak double.

## 4. Prioritas Rendah (dicatat, TIDAK perlu kode di plan ini)

- **`irfan.hakim@demo.test` idle** (13 guru_kelas untuk 12 slot wali) — data seed, bukan bug. Tidak ada tindakan.
- **`JadwalPelajaranController::store/update` tidak eksplisit validasi tenant `$kelas`** — sudah aman lewat `TenantScope` global, cuma inkonsistensi pola vs `duplicate()`. Opsional: tambah `abort_if($kelas->lembaga_id !== $lembagaId, 404)` eksplisit di awal `store()`/`update()` sebagai defense-in-depth. **Dimasukkan sebagai 1 task kecil opsional di plan (boleh di-skip kalau plan sudah panjang).**
- **Inkonsistensi pola dialog konfirmasi** (native `confirm()` vs Alpine `confirmDialog()`) — kosmetik murni, di luar scope plan bug-fix ini.

## 5. Non-Goals

- Tidak membangun halaman Nilai/Jadwal/Presensi Siswa/Ortu yang sungguhan — itu proyek fitur terpisah nanti (lihat §1).
- Tidak menambah tabel/model audit-log baru untuk Kenaikan Kelas — perbaikan §2.2 murni UI dari data yang sudah ada.
- Tidak menyentuh modul di luar Akademik (Keuangan, SPMB, Kehadiran SDM, Kasus) kecuali dependency langsung yang sudah disebut di atas (tidak ada).
