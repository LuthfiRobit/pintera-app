# Spec: Kuota/Saldo Cuti Tahunan

**Tanggal:** 23 Agustus 2026
**Branch:** `sdm-v1`
**Modul terkait:** Kehadiran SDM (memperluas Sub-project 4 — Izin/Cuti Berjenjang)

## 1. Latar Belakang

Sub-project 4 (`.agents/specs/2026-08-22-sdm-04-izin-cuti-berjenjang.md`) membangun alur pengajuan izin/cuti berjenjang lengkap (Sakit/Izin/Cuti → approval berjenjang → tercatat otomatis ke kehadiran), tapi secara eksplisit menyatakan kuota/saldo cuti tahunan **di luar cakupan** (baris 26 & 125 spec tersebut). Saat ini pegawai bisa mengajukan Cuti berapa hari pun tanpa batasan apapun selama disetujui approver — tidak ada pengecekan sisa jatah.

Spec ini membangun fitur itu: batasan jatah Cuti tahunan per pegawai.

## 2. Tujuan

Menambahkan validasi kuota Cuti tahunan ke alur pengajuan izin/cuti yang sudah ada, TANPA mengubah domain `App\Domains\Workflow` (approval generik) dan TANPA menambah tabel saldo/ledger — sisa kuota dihitung ulang dari data pengajuan yang sudah ada.

## 3. Keputusan Desain (hasil brainstorming dengan user)

### 3.1 Cakupan kategori
Kuota **hanya berlaku untuk kategori `Cuti`**. Kategori `Izin` dan `Sakit` (`App\Domains\Sdm\Enums\KategoriPengajuanIzin`) sama sekali tidak terpengaruh — perilakunya identik seperti sekarang.

### 3.2 Besaran jatah
**Flat** — satu angka `jatah_hari_per_tahun` berlaku untuk semua pegawai, dikonfigurasi Admin SDM. Disimpan di tabel dengan kolom `jenis_ptk`/`jenis_karyawan_id` **nullable** (pola identik `attendance_policies`) supaya bisa diperluas jadi per-jenis-pegawai di masa depan TANPA migrasi skema baru — untuk sekarang cukup 1 baris dengan kedua kolom itu `NULL` (berlaku untuk semua pegawai di scope-nya).

### 3.3 Siklus reset
**Tahun kalender**, berdasarkan `tanggal_mulai` pengajuan (bukan tanggal submit). Tidak ada job terjadwal untuk reset — karena sisa kuota dihitung ulang (lihat 3.4), reset terjadi otomatis lewat filter tahun di query.

### 3.4 Arsitektur perhitungan sisa kuota — **dihitung ulang, BUKAN saldo tersimpan**
Tidak ada kolom/tabel "saldo" yang didekrementasi. Sisa kuota pegawai untuk tahun `Y`:

```
sisaKuota(pegawai, Y) = jatah(pegawai) − SUM(hari) dari semua PengajuanIzinCuti milik pegawai
                         WHERE kategori = Cuti
                         AND YEAR(tanggal_mulai) = Y
                         AND status approval IN (Pending, InReview, Approved)
```

Pengajuan yang Cancelled/Rejected TIDAK dihitung — otomatis "mengembalikan" kuota secara konseptual tanpa logic refund manual, karena memang tidak pernah ikut dijumlahkan begitu statusnya bukan salah satu dari 3 status aktif itu.

### 3.5 Kapan kuota ditahan
Kuota otomatis "ditahan" begitu pengajuan Cuti **dibuat** (status Pending) — bukan menunggu approval final. Ini konsisten dengan formula 3.4 (Pending sudah ikut dihitung).

### 3.6 Concurrency — WAJIB, bukan opsional
Tanpa pengaman, dua pengajuan Cuti yang submit hampir bersamaan bisa sama-sama lolos validasi "sisa kuota cukup" padahal gabungannya melebihi jatah (race condition klasik read-then-write). **Wajib** dibungkus `Cache::lock()` per `(pegawai_type, pegawai_id, tahun)` mengelilingi langkah hitung-sisa + buat-pengajuan di `AjukanIzinCutiAction`, HANYA untuk kategori Cuti. `CACHE_STORE` di environment ini adalah `database` (mendukung atomic lock lewat tabel `cache_locks` bawaan Laravel) — mekanisme ini portable dan tidak bergantung pada semantik locking spesifik-engine database (MySQL vs SQLite test env berbeda).

**Keterbatasan test yang harus diakui secara eksplisit**: `phpunit.xml` mengatur `CACHE_STORE=array` untuk test — cukup untuk membuktikan mekanisme lock bekerja secara isolasi (lock diambil, percobaan kedua di proses yang sama gagal/diblokir), TAPI TIDAK bisa membuktikan skenario 2 HTTP request paralel sungguhan (keterbatasan tooling Pest, bukan keterbatasan desain). Test race-condition sungguhan (kalau nanti dibutuhkan) harus lewat uji beban manual di luar cakupan spec ini.

### 3.7 Cuti lintas pergantian tahun — DITOLAK
Pengajuan Cuti dengan `tanggal_mulai` dan `tanggal_selesai` di tahun kalender yang berbeda (mis. 30 Desember → 2 Januari) **ditolak** lewat validasi (`ValidationException`), dengan pesan: "Pengajuan Cuti tidak boleh melewati pergantian tahun kalender. Silakan ajukan terpisah untuk setiap tahun." Alasan: menghindari kompleksitas membagi kuota lintas 2 baris config tahun yang berbeda (yang bisa saja beda nilai `jatah_hari_per_tahun`-nya) dan pesan error yang membingungkan. Pegawai tinggal submit 2 pengajuan terpisah kalau memang perlu.

### 3.8 Kuota tidak cukup saat submit
**Ditolak otomatis** (`ValidationException`) sebelum pengajuan sempat dibuat dan sebelum masuk alur approval — bukan warning visual ke approver. Pesan jelas menyebut sisa kuota aktual, mis. "Sisa kuota Cuti Anda tahun ini tinggal 2 hari, tidak cukup untuk 5 hari yang diajukan."

### 3.9 Visibilitas ke pegawai
Halaman form pengajuan (`sdm/izin-cuti/create.blade.php`) menampilkan info sisa kuota Cuti tahun berjalan pegawai yang login — hanya relevan/tampil saat kategori Cuti dipilih.

### 3.10 Unique constraint konfigurasi
Kolom `yayasan_id, lembaga_id, jenis_ptk, jenis_karyawan_id` diberi unique constraint gabungan — mencegah admin membuat 2 baris konfigurasi yang scope-nya sama persis. Nama constraint mengikuti pola `attendance_policies` (`attendance_policy_unique`) → `kuota_cuti_config_unique`.

## 4. Skema Database

**1 tabel baru**, mengikuti pola `attendance_policies` persis (`database/migrations/2026_08_22_110000_create_attendance_policies_table.php` sebagai referensi struktur):

```
kuota_cuti_config
- id
- yayasan_id (foreign, not null)
- lembaga_id (foreign, nullable — NULL = berlaku nasional/semua lembaga di yayasan itu)
- jenis_ptk (string, nullable — NULL = berlaku semua jenis PTK)
- jenis_karyawan_id (foreign, nullable — NULL = berlaku semua jenis karyawan)
- jatah_hari_per_tahun (integer)
- timestamps
- UNIQUE(yayasan_id, lembaga_id, jenis_ptk, jenis_karyawan_id) sebagai `kuota_cuti_config_unique`
```

**Tidak ada tabel saldo/ledger/riwayat pemakaian kuota** — sesuai keputusan 3.4, dihapus dari rencana.

## 5. Komponen Baru & yang Dimodifikasi

- **Baru** — Migration `create_kuota_cuti_config_table.php`.
- **Baru** — `App\Domains\Sdm\Models\KuotaCutiConfig` (Eloquent model, `BelongsToTenant`).
- **Baru** — `App\Domains\Sdm\Services\KuotaCutiResolver` — service (pola sama seperti `AttendancePolicyResolver`), 2 method publik:
  - `jatahTahunan(Model $pegawai): int` — resolve config yang berlaku (lembaga → nasional/yayasan fallback, sama pola resolusi `AttendancePolicyResolver`), default `0` kalau tidak ada config sama sekali (berarti belum dikonfigurasi = tidak ada batasan yang ditegakkan — TAPI ini keputusan implementasi yang perlu eksplisit di plan: kalau `jatahTahunan` mengembalikan config kosong, `AjukanIzinCutiAction` TIDAK menegakkan validasi kuota sama sekali, supaya lembaga yang belum sempat setting kuota tidak tiba-tiba semua pengajuan Cuti pegawainya ditolak).
  - `sisaKuota(Model $pegawai, int $tahun): int` — hitung sesuai formula 3.4.
- **Modifikasi** — `App\Domains\Sdm\Actions\AjukanIzinCutiAction`:
  - Kalau `kategori === Cuti` DAN `tanggal_mulai`/`tanggal_selesai` beda tahun kalender → `ValidationException` (larangan ini KHUSUS kategori Cuti, sesuai 3.7 — murni soal kompleksitas kuota lintas config tahun berbeda. Izin/Sakit TIDAK dibatasi ini, tetap boleh lintas tahun seperti sekarang, mis. sakit yang kebetulan melewati malam tahun baru).
  - Kalau `kategori === Cuti` DAN `KuotaCutiResolver::jatahTahunan()` untuk pegawai ini `> 0` (ada config): bungkus pengecekan sisa kuota + pembuatan pengajuan dengan `Cache::lock()` per `(pegawai_type, pegawai_id, tahun)`; kalau hari yang diajukan melebihi sisa kuota → `ValidationException`.
  - Signature method `execute()` yang sudah ada TIDAK berubah.
- **Modifikasi** — `resources/views/admin/kehadiran-sdm/konfigurasi.blade.php`: tab ke-5 "Kuota Cuti" — form set `jatah_hari_per_tahun` untuk scope aktif (lembaga saat ini atau nasional untuk aktor yayasan), mengikuti pola tab-tab lain di halaman yang sama persis (termasuk modal `confirmDialog` kalau ada aksi hapus config nasional oleh aktor yayasan, konsisten dengan tab Kebijakan Jam Kerja/Jenis Shift yang sudah ada).
- **Modifikasi** — `resources/views/sdm/izin-cuti/create.blade.php`: tambah info card sisa kuota Cuti (server-rendered dari controller, hanya tampil terkait kategori Cuti).
- **Modifikasi** — `App\Http\Controllers\PengajuanIzinCutiController::create()`: kirim `sisaKuotaCuti` ke view (hasil `KuotaCutiResolver::sisaKuota()` untuk pegawai yang login, tahun berjalan).
- **Modifikasi** — `App\Http\Controllers\Admin\AttendanceConfigurationController`: tambah handler untuk tab Kuota Cuti (index + update), mengikuti pola handler tab-tab lain yang sudah ada di controller yang sama.

## 6. Yang TIDAK Berubah (hard constraint)

- `App\Domains\Workflow` (semua Model/Service/Action generik approval) — TIDAK disentuh sama sekali.
- `App\Domains\Sdm\Actions\ProsesApprovalIzinCutiAction` — TIDAK perlu diubah (tidak ada saldo untuk didekrementasi/dikembalikan di titik approval, karena dihitung ulang dari status pengajuan).
- Kategori `Izin` dan `Sakit` — perilaku identik seperti sekarang, sama sekali tidak tersentuh logic kuota.
- `KalenderKerjaSdmResolver`, `AttendancePolicyResolver`, `ShiftAwareAttendanceResolver` — tidak disentuh.

## 7. Testing

- Unit/feature test untuk `KuotaCutiResolver::sisaKuota()`: menghitung benar untuk kombinasi status (Pending/InReview/Approved dihitung, Rejected/Cancelled tidak), filter tahun benar, fallback resolusi lembaga→nasional.
- Feature test `AjukanIzinCutiAction`: pengajuan Cuti ditolak kalau sisa kuota tidak cukup (pesan jelas); pengajuan Cuti lolos kalau sisa cukup; pengajuan Izin/Sakit TIDAK PERNAH kena validasi kuota meski "melebihi" angka yang sama; pengajuan Cuti lintas tahun kalender ditolak, pengajuan Izin/Sakit lintas tahun kalender TETAP DIIZINKAN (tidak kena validasi ini sama sekali); lembaga tanpa config kuota sama sekali tidak menegakkan validasi apapun (regresi-aman untuk lembaga yang belum setting).
- Test mekanisme lock: verifikasi `Cache::lock()` benar-benar dipanggil dengan key yang tepat dan pengajuan kedua yang mencoba mengambil lock yang sama akan gagal/diblokir selama lock pertama masih dipegang (test dalam 1 proses, BUKAN true concurrent HTTP — keterbatasan sudah dijelaskan di 3.6, harus tetap ditulis apa adanya di plan/handoff, jangan diklaim sebagai bukti race-condition-proof penuh).
- Test regresi: seluruh test Sub-project 4 yang sudah ada (`AjukanIzinCutiAction`, `ProsesApprovalIzinCutiAction`, dst.) tetap hijau tanpa perubahan jumlah.

## 8. Di Luar Cakupan (Tidak Dikerjakan di Spec Ini)

- Kuota berbeda per jenis pegawai (skema sudah siap lewat kolom nullable, tapi UI/logic pembeda per-jenis TIDAK dibangun sekarang — admin cuma bisa isi 1 baris flat).
- Kuota untuk kategori Izin/Sakit.
- Prorata kuota berdasarkan masa kerja/tanggal masuk pegawai baru.
- Job terjadwal apapun (tidak dibutuhkan karena arsitektur dihitung-ulang).
- Uji beban/concurrency testing sungguhan dengan request paralel nyata (di luar kapasitas Pest test suite ini).
- Rotasi otomatis shift (item terpisah lain, tidak terkait spec ini).
