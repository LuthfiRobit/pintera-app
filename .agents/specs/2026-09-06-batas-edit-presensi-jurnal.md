# Batas Waktu Edit Presensi & Jurnal KBM (Proyek A)

## 1. Latar Belakang

Item backlog Low-severity, dicatat resmi di `PETA_PENGEMBANGAN.md` (2026-09-06), ditemukan sejak audit 27-28 Agustus 2026: `Guru\Akademik\JurnalKbmController::update()` tidak punya batasan waktu apa pun untuk mengedit presensi/jurnal pada `SesiPembelajaran` lama. Guru bisa mengubah data kehadiran dari sesi berbulan-bulan lalu tanpa batasan. Ini BUKAN celah keamanan (kepemilikan sudah divalidasi benar via `authorizeMilikGuru()`), tapi risiko **integritas data historis** — rekap presensi semester yang sudah dipakai sebagai dasar rapor/kelengkapan nilai bisa berubah diam-diam setelah faktanya seharusnya sudah final.

Dibahas juga (dan sengaja DIPISAH ke luar cakupan spec ini, jadi Proyek B & C terpisah, lihat §5):
- **Proyek B**: Waka Kurikulum bisa mengakses & mengedit Jurnal KBM guru LAIN (bukan sekadar override cutoff, tapi akses lintas-guru yang belum ada sama sekali sekarang) — ditunda, layak spec sendiri.
- **Proyek C**: "Guru piket" — siapa yang mengisi presensi HARI ITU JUGA kalau guru pemilik sesi berhalangan hadir (sakit/izin mendadak) — gap operasional nyata yang berbeda akar masalah dari cutoff (ini soal pengisian normal real-time, bukan koreksi setelah kejadian). Dikerjakan SETELAH Proyek A ini (alasan user: "buat feature setupnya dulu" — pola pengaturan per-Lembaga di sini kemungkinan dipakai lagi di desain C).

## 2. Keputusan Desain

1. **Basis cutoff: rolling N hari dari hari ini, dikonfigurasi per-Lembaga** (bukan hardcoded, bukan terikat status rapor/semester). Alasan: platform ini melayani banyak jenjang (PAUD s.d. SMA/SMK) dengan budaya administrasi berbeda — angka yang pas untuk satu jenjang belum tentu pas untuk jenjang lain. Default: **3 hari**.
2. **Pengaturan disimpan di kolom baru `lembaga.batas_edit_absen_hari`** (integer, default `3`, NOT NULL) — mengikuti persis pola `lembaga.hari_libur_mingguan` yang sudah ada, diatur admin lewat halaman **Pengaturan Akademik** (`admin/pengaturan/akademik`) yang sudah ada, bukan halaman baru.
3. **Override: Wali Kelas dikecualikan dari cutoff, HANYA untuk sesi kelas yang dia menjadi wali kelasnya sendiri** (`$sesi->kelas->wali_kelas_guru_id === $guru->id`). Guru mapel biasa (bukan wali kelas kelas itu) tetap kena batas, termasuk kalau dia wali kelas kelas LAIN. Ini BUKAN akses lintas-guru baru — wali kelas kelasnya sendiri sudah otomatis lolos `authorizeMilikGuru()` kalau dia juga guru mapel di situ; yang berubah cuma pengecekan cutoff-nya.
4. **Waka Kurikulum TIDAK termasuk override di spec ini** (itu Proyek B, butuh akses lintas-guru yang belum ada infrastrukturnya).
5. **Lingkup yang dikunci: seluruh form** (materi jurnal + status presensi + keterangan izin/sakit) — bukan sebagian field. Begitu terkunci, seluruh form itu read-only.
6. **UX: `show()` juga menampilkan status terkunci sebelum guru sempat isi form**, bukan baru muncul error setelah klik Simpan. Form tetap tampil (read-only) dengan banner penjelasan.
7. **Validasi nilai setting**: `batas_edit_absen_hari` harus bilangan bulat antara 1–365 (mencegah admin salah input 0 atau angka ekstrem).
8. **Perhitungan cutoff**: `$sesi->tanggal` dibandingkan ke `now()->subDays($lembaga->batas_edit_absen_hari)` — kalau `$sesi->tanggal` LEBIH LAMA dari itu, terkunci (kecuali wali kelas kelasnya sendiri).

## 3. Arsitektur & Komponen

### 3.1 Migrasi & Model
- Migrasi baru: tambah kolom `batas_edit_absen_hari` (integer, default `3`, `->after('hari_libur_mingguan')`) ke tabel `lembaga`.
- `App\Models\Lembaga` sudah memakai `$fillable` (dikonfirmasi langsung ke kode) — tambah `batas_edit_absen_hari` ke array itu.

### 3.2 Pengaturan Admin
- **DTO baru** `App\Domains\Akademik\DataTransferObjects\BatasEditAbsenLembagaData` — `final readonly class` dengan 1 properti `public int $batasHari`, persis pola `HariAktifLembagaData` (sibling DTO yang sudah ada, dikonfirmasi langsung ke kode).
- **Action baru** `App\Domains\Akademik\Actions\Kalender\UpdateBatasEditAbsenLembagaAction` — namespace `Kalender` dikonfirmasi adalah lokasi asli `UpdateHariAktifLembagaAction` (bukan tebakan). Signature: `execute(Lembaga $lembaga, BatasEditAbsenLembagaData $data): Lembaga` — menerima DTO, BUKAN scalar `int` langsung, supaya konsisten dengan pola Action sejenis di domain ini.
- **Modifikasi** `PengaturanAkademikController`: tambah method baru `updateBatasEditAbsen()` (pola sama persis `updateHariAktif()`), validasi `batas_edit_absen_hari` integer 1-365, permission `pengaturan-akademik.kelola` (reuse, bukan permission baru).
- **Modifikasi view** `portals.lembaga.akademik.pengaturan.akademik` — tambah 1 field baru (input angka) untuk `batas_edit_absen_hari`, di bagian yang sama dengan pengaturan hari aktif.
- **Route baru** di file route Pengaturan Akademik yang sudah ada.

### 3.3 Enforcement di Jurnal KBM
- **Helper/method baru** di `JurnalKbmController` (atau Service kecil kalau logic-nya dipakai di 2 tempat — `show()` dan `update()` sama-sama butuh tahu status terkunci): `private function sesiTerkunci(SesiPembelajaran $sesi, Guru $guru): bool` — hitung berdasarkan §2.8, kecualikan wali kelas kelasnya sendiri sesuai §2.3.
- **`show()`**: hitung `$terkunci = $this->sesiTerkunci($sesi, $guru)`, kirim ke view sebagai variabel baru.
- **`update()`**: sebelum memanggil `$this->recordJurnalDanPresensiAction->execute(...)`, cek `sesiTerkunci()` — kalau `true`, redirect kembali dengan pesan error jelas ("Sesi ini sudah melewati batas waktu edit ({batas} hari). Hubungi Wali Kelas kelas ini untuk koreksi."), TIDAK memanggil Action sama sekali (fail sebelum ada perubahan data apa pun).
- **View `show.blade.php`**: kalau `$terkunci`, tampilkan banner penjelasan di atas form + disable semua input (textarea materi, radio presensi, input keterangan) + sembunyikan/disable tombol Simpan.

## 4. Skenario Test

1. Guru mapel biasa edit sesi DALAM batas N hari → berhasil (perilaku sekarang, tidak berubah).
2. Guru mapel biasa edit sesi DI LUAR batas N hari (bukan wali kelas kelas itu) → ditolak, pesan error jelas, TIDAK ada perubahan data tersimpan.
3. Wali Kelas edit sesi KELASNYA SENDIRI di luar batas N hari → berhasil (override).
4. Wali Kelas edit sesi KELAS LAIN (bukan yang dia walikan) di luar batas N hari → tetap ditolak (override cuma untuk kelasnya sendiri; catatan: `authorizeMilikGuru()` biasanya sudah menolak duluan kalau dia bukan guru mapel di situ juga — pastikan test ini benar-benar menguji kasus dia KEBETULAN guru mapel di kelas lain tapi bukan wali kelasnya).
5. `show()` menampilkan form read-only + banner untuk sesi yang terkunci.
6. `show()` menampilkan form editable normal untuk sesi yang belum terkunci.
7. Admin update `batas_edit_absen_hari` lewat Pengaturan Akademik → tersimpan, tervalidasi rentang 1-365.
8. Lembaga tanpa setting eksplisit (baru dibuat) → default `3` berlaku otomatis (test migrasi/factory default).
9. Regresi — semua test existing `JurnalKbmControllerTest.php`, `JurnalKbmResolveKartuTest.php`, `JurnalKbmTanggalSusulanTest.php` tetap lulus tanpa modifikasi logic-nya (cuma menambah, bukan mengubah perilaku yang sudah ada untuk sesi DALAM batas waktu).

## 5. Di Luar Cakupan

- **Proyek B** — Waka Kurikulum akses/edit Jurnal KBM guru lain untuk koreksi. Backlog terpisah, butuh spec sendiri (halaman baru untuk menjelajahi sesi lintas-guru, guard tenant-safety terpisah).
- **Proyek C** — "Guru piket" / pengisian presensi real-time saat guru asli berhalangan hadir. Backlog terpisah, akan dikerjakan setelah Proyek A, kemungkinan reuse pola pengaturan-per-Lembaga dari spec ini.
- Notifikasi/alert ke admin/wali kelas saat ada percobaan edit yang ditolak karena cutoff — tidak diminta, tidak dibangun.
- Audit log/jejak siapa yang mengedit kapan (di luar Spatie Activitylog yang mungkin sudah otomatis jalan di level model, tidak ada penambahan eksplisit) — tidak dibangun khusus di spec ini.
