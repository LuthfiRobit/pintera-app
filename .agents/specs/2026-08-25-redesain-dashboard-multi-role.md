# Spec: Penyempurnaan Data 7 Dashboard Multi-Role (Platform/Yayasan/Lembaga/Karyawan/Guru/Orang Tua/Siswa)

**Tanggal**: 2026-08-25 (revisi dari draft awal)
**Branch**: `rbac-v2`
**Status**: Revisi setelah review — draft awal ditolak karena (a) mengusulkan sistem visual baru tanpa mengecek yang sudah ada, (b) mengusulkan komponen Blade baru yang DUPLIKAT dengan yang sudah ada, (c) tidak ada verifikasi struktur model nyata.

## 1. Latar Belakang

`DashboardController` sudah punya 7 varian dashboard (Platform — baru ditambahkan sub-project sebelumnya, Yayasan, Lembaga, Karyawan — baru diperkaya sub-project sebelumnya, Guru, Orang Tua, Siswa). Sebagian besar (Guru/Orang Tua/Siswa) HANYA menampilkan data modul Kasus/Pendampingan — padahal data akademik (nilai, presensi, jadwal), keuangan (tagihan), dan SDM (kuota cuti, shift) sudah ADA sebagai modul terpisah di sistem, cuma belum disambungkan ke dashboard masing-masing peran. Siswa masih stub kosong total.

## 2. Koreksi dari Draft Awal — WAJIB Dipatuhi

1. **JANGAN buat komponen Blade baru** (`partials/hero-banner.blade.php`, `partials/stat-card.blade.php`, dst). Komponen `<x-hero-banner>`, `<x-stat-tile>`, `<x-panel>`, `<x-badge>`, `<x-icon>` **SUDAH ADA** (`resources/views/components/*.blade.php`) dan sudah dipakai di ketujuh dashboard existing. Kalau butuh prop tambahan (mis. `trend` di `stat-tile`), PERLUAS komponen yang sudah ada, JANGAN buat versi baru.
2. **JANGAN perkenalkan token visual baru** ("Kanvas Soft Pastel `bg-slate-50`", dst). Token yang sudah dipakai: gradient `ink`→`#123363` untuk hero banner, `border-gray-200 bg-white shadow-card` untuk card/panel/stat-tile (konsisten dengan admin panel TailAdmin-style), `font-display` untuk heading. PERTAHANKAN token ini di semua dashboard yang direvisi.
3. **Chart.js SUDAH terinstall** (`chart.js: ^4.5.1` di `package.json`) dan SUDAH dipakai lewat pola Alpine data component di `resources/js/dashboard-charts.js` (`trenPendaftaranChart`, `donutTagihanChart`, `perLembagaBarChart`, didaftarkan di `resources/js/app.js`). Chart baru WAJIB mengikuti pola yang SAMA PERSIS (fungsi baru di file yang sama, didaftarkan sebagai `Alpine.data(...)` baru) — JANGAN import Chart.js dengan cara lain.
4. **Hapus Global Constraints yang tidak relevan** dari plan sebelumnya ("kolom aksi di kolom 1", "no native confirm") — dashboard adalah halaman read-only ringkasan, tidak ada tabel CRUD dengan aksi hapus/edit.
5. **Semua struktur model di bawah ini SUDAH DIVERIFIKASI langsung dari kode** (bukan tebakan) — kutip persis, jangan diasumsikan berbeda.

## 3. Struktur Data Terverifikasi (Rujukan Wajib untuk Plan)

- **`NilaiSiswa`** (`app/Domains/Akademik/Models/NilaiSiswa.php`): fillable `asesmen_id, siswa_id, komponen_penilaian_id, lembaga_id, nilai_angka, predikat, catatan`. Relasi: `siswa()`, `asesmen()` (→ `Asesmen`, punya `kelas_id`+`mataPelajaran()`), `komponenPenilaian()` (→ punya `mataPelajaran()`). TIDAK ADA relasi balik `Siswa::nilaiSiswa()` — selalu query `NilaiSiswa::where('siswa_id', ...)` langsung.
- **`Asesmen`** (`app/Domains/Akademik/Models/Asesmen.php`): punya `kelas()` dan `mataPelajaran()` BelongsTo — ini jalur untuk tahu mata pelajaran & kelas dari sebuah nilai.
- **`KomponenPenilaian`**: fillable termasuk `bobot` (tanpa cast, treated as int). Relasi `mataPelajaran()`, `nilaiSiswa(): HasMany`. TIDAK ADA `kelas_id` langsung.
- **`PengajuanRapor`**: enum `StatusPengajuanRapor` (`Draft`, `Diajukan`, `Diverifikasi`, `Disetujui`, `Ditolak`). TIDAK ADA field persentase progress — dihitung manual (lihat §4.C).
- **`RaporCalculationService::hitungRekapKelas(Kelas $kelas, Semester $semester): array`** — SATU-SATUNYA method publik, return `['siswaList', 'mapelList', 'rekapNilai' => [siswa_id][mapel_id] => nilai, 'classAvg', 'highestScore']`. TIDAK ADA method single-student.
- **`Tagihan`** (`app/Domains/Keuangan/Models/Tagihan.php`): keterkaitan ke siswa lewat **`tagihable` MorphTo** (`tagihable_type`/`tagihable_id`), BUKAN kolom `siswa_id`. `Siswa::tagihan(): MorphMany` sudah ada. Field: `total_tagihan`, `paid_amount`, `status`, `jatuh_tempo`.
- **`Siswa`** (`app/Models/Siswa.php`): relasi `kelas()`, `orangTua(): BelongsToMany` (pivot `siswa_orang_tua`), `tagihan(): MorphMany`, `user()`. TIDAK ADA relasi ke `NilaiSiswa`/`Presensi` — query manual pakai `siswa_id`.
- **`OrangTua`** (`app/Models/OrangTua.php`): `siswa(): BelongsToMany` (inverse pivot, terverifikasi ada).
- **`Presensi`** (`app/Domains/Akademik/Models/Presensi.php`, BUKAN `AttendanceRecord`): fillable `sesi_pembelajaran_id, siswa_id, status, keterangan`, cast `status => StatusPresensi::class`. TIDAK ADA kolom tanggal langsung — tanggal ada di `SesiPembelajaran::tanggal` (relasi `sesiPembelajaran()`... perlu verifikasi nama relasi balik saat implementasi, kalau tidak ada pakai `whereHas('sesiPembelajaran', fn($q) => $q->whereDate('tanggal', ...))`).
- **`SesiPembelajaran`**: fillable termasuk `tanggal` (cast date), relasi `kelas()`, `guru()`, `mataPelajaran()`, `jadwalPelajaran()`, `presensi(): HasMany`.
- **`JadwalPelajaran`** (`app/Models/JadwalPelajaran.php`): fillable `kelas_id, jam_pelajaran_id, mata_pelajaran_id, guru_id, semester_id, lembaga_id, ruangan_id`. TIDAK ADA kolom `hari` langsung — hari ada di **`JamPelajaran::hari`** (enum `App\Enums\Hari`, punya `Hari::fromCarbonDayOfWeek(int $dayOfWeek): self`).
- **`Kelas`**: fillable termasuk `wali_kelas_guru_id` (FK ke `Guru`).
- **`Rpp`** (`app/Domains/Akademik/Models/Rpp.php`): cast `status => StatusRpp::class` (`Draft`, `Diajukan`, `Disetujui`, `PerluRevisi`). Method `isEditable()` = `Draft`/`PerluRevisi`.
- **`Guru`** DAN **`Karyawan`** SAMA-SAMA punya relasi polymorphic identik: `attendanceRecords(): MorphMany`, `pengajuanIzinCuti(): MorphMany`, `penugasanShift(): MorphMany` — keduanya adalah target sah `pegawai_type` di tabel `attendance_records`/`pengajuan_izin_cuti`/`penugasan_shift`.
- **`AttendanceRecord`**: fillable `lembaga_id, pegawai_type, pegawai_id, tanggal, status, waktu_masuk, waktu_pulang, is_late, late_minutes`, cast `status => AttendanceStatus::class` (`Hadir`, `Izin`, `Sakit`, `Alpa`, `Cuti`). Query ringkasan lintas Guru+Karyawan dalam SATU query (`pegawai_type`/`pegawai_id` sama-sama kolom di tabel yang sama, tidak perlu union).
- **`PengajuanIzinCuti`**: fillable `lembaga_id, pegawai_type, pegawai_id, kategori, tanggal_mulai, tanggal_selesai, alasan`. Status persetujuan ADA DI `approvalRequest(): MorphOne` → `ApprovalRequest::status` (enum `App\Domains\Workflow\Enums\ApprovalStatus`, cases: `Pending`, `InReview`, `Approved`, `Rejected`, `RevisionRequired`, `Cancelled`).
- **`KuotaCutiConfig`**: fillable `yayasan_id, lembaga_id, jenis_ptk, jenis_karyawan_id, jatah_hari_per_tahun`. Template kuota (bukan per-karyawan), dicocokkan via `lembaga_id`+`jenis_karyawan_id`.
- **`PenugasanShift`**: fillable `lembaga_id, pegawai_type, pegawai_id, jenis_shift_id, tanggal_mulai, tanggal_selesai, hari_kerja` (array). Range tanggal, BUKAN 1 baris per hari.

## 4. Cakupan Per Dashboard (Detail Final)

### A. Platform (`platform_super_admin`) — SUDAH ADA sebagian, DIPERLUAS

Sudah ada (sub-project sebelumnya): stat Yayasan/Lembaga/Guru/Pengguna + tabel ringkasan per yayasan.
**Ditambahkan**: chart tren pertumbuhan tenant (jumlah `Yayasan` baru per bulan, 6 bulan terakhir, berbasis `created_at` timestamp standar), kolom "TA Aktif?" dan "Akun Nonaktif" di tabel ringkasan per yayasan.

### B. Yayasan (`yayasan_super_admin`, semua lembaga)

Sudah ada: stat Lembaga/Guru/Pengguna/TA Aktif, konsolidasi SPMB+Keuangan per lembaga (snapshot, bukan tren), grafik bar pendaftar per lembaga.
**Ditambahkan**: ringkasan kehadiran SDM (Guru+Karyawan) HARI INI lintas semua lembaga di yayasan (groupBy status), jumlah kasus berstatus eskalasi lintas lembaga yang belum ada konselor (`konselor_karyawan_id` DAN `konselor_guru_id` null).

**Non-goal**: tren keuangan BULANAN konsolidasi (grafik line multi-bulan) — `statistikKeuangan()` existing hanya snapshot per tahun ajaran aktif, butuh redesain data model billing_period untuk agregasi bulanan yang akurat; DI LUAR cakupan spec ini (draft awal terlalu ambisius di sini tanpa dasar data yang sesuai).

### C. Lembaga (kepala sekolah/admin lembaga)

Sudah ada: stat Guru/Pengguna/TA Aktif, SPMB+tren, Keuangan, daftar kasus (kondisional per permission).
**Ditambahkan**:
1. Ringkasan kehadiran SDM hari ini (1 lembaga), groupBy status.
2. Progress pengumpulan nilai per kelas untuk semester aktif — dihitung manual: `(jumlah NilaiSiswa terisi) / (jumlah siswa kelas × jumlah komponen penilaian mapel di kelas itu) × 100`, dibatasi ke kelas-kelas di lembaga tsb dan HANYA ditampilkan kalau `$user->can('komponen-penilaian.kelola')` (permission existing).
3. Jumlah pengajuan izin/cuti dengan status `Pending` menunggu approval di lembaga tsb.

**Non-goal**: jadwal pelajaran hari ini per SEMUA kelas (terlalu berat untuk 1 dashboard ringkasan, lebih cocok di halaman Jadwal Pelajaran yang sudah ada) — DI LUAR cakupan.

### D. Karyawan (`pegawai_yayasan`/`pegawai_lembaga`) — SUDAH ADA sebagian, DIPERLUAS

Sudah ada: presensi hari ini, izin/cuti pending, unit/lembaga, kasus ditangani.
**Ditambahkan**: chart bar riwayat presensi 30 hari terakhir (Hadir/Izin/Sakit/Alpa), sisa kuota cuti tahun ini (`KuotaCutiConfig.jatah_hari_per_tahun` dikurangi total hari `PengajuanIzinCuti` yang `Approved` tahun berjalan — tampilkan "Tidak ada kuota terdaftar" kalau config tidak ditemukan, JANGAN error), 3 shift mendatang (`PenugasanShift` dengan `tanggal_selesai >= today`).

### E. Guru (role `guru`)

Sudah ada: jabatan tambahan, 6 stat kasus diajukan+ditangani, daftar kasus.
**Ditambahkan**:
1. Jadwal mengajar hari ini: `JadwalPelajaran::where('guru_id', $guru->id)->whereHas('jamPelajaran', fn($q) => $q->where('hari', Hari::fromCarbonDayOfWeek(now()->dayOfWeek)))->with(['jamPelajaran','mataPelajaran','kelas'])->get()`, diurutkan per `jamPelajaran.urutan`.
2. Kalau `$guru->id === $kelas->wali_kelas_guru_id` untuk suatu kelas (cek `Kelas::where('wali_kelas_guru_id', $guru->id)->first()`): tampilkan progress rapor kelas itu (reuse logic §4.C poin 2, scoped ke 1 kelas).
3. Presensi diri hari ini: `$guru->attendanceRecords()->where('tanggal', today())->first()`.
4. RPP milik guru dengan status `Draft`/`PerluRevisi` (pakai `Rpp::isEditable()`), tampilkan jumlahnya sebagai stat + link ke halaman RPP.
5. Rekap presensi siswa di kelas yang diajar HARI INI: dari jadwal poin 1, ambil `SesiPembelajaran` hari ini milik guru tsb (`where('guru_id', $guru->id)->whereDate('tanggal', today())`), groupBy status `Presensi` terkait.

### F. Orang Tua (role `orang_tua`)

Sudah ada: 6 stat kasus anak + daftar kasus.
**Ditambahkan** (untuk SEMUA anak yang terhubung, `$orangTua->siswa`):
1. Tagihan: `Tagihan::where('tagihable_type', Siswa::class)->whereIn('tagihable_id', $siswaIds)->whereIn('status', ['belum_bayar','dicicil'])->orderBy('jatuh_tempo')`. Tampilkan total belum lunas + tagihan jatuh tempo terdekat.
2. Nilai terbaru per anak: `NilaiSiswa::whereIn('siswa_id', $siswaIds)->whereNotNull('nilai_angka')->with(['komponenPenilaian.mataPelajaran','asesmen.mataPelajaran'])->latest('id')->limit(5)->get()`.
3. Jadwal pelajaran anak hari ini (per anak, kalau lebih dari 1 anak tampilkan per anak): `JadwalPelajaran::where('kelas_id', $siswa->kelas_id)->whereHas('jamPelajaran', ...)`.
4. Riwayat izin/sakit anak: `Presensi::where('siswa_id', $siswa->id)->whereIn('status', ['izin','sakit'])->latest()->limit(5)->get()` (nama enum `StatusPresensi` case value diverifikasi di step implementasi).

### G. Siswa (role `siswa`) — dibangun dari nol

1. Jadwal pelajaran hari ini (query sama seperti §4.F poin 3, `kelas_id` milik diri sendiri).
2. Presensi rekap bulan ini: `Presensi::where('siswa_id', $siswa->id)->whereMonth(...)->groupBy('status')`.
3. Nilai terbaru per mapel (query sama seperti §4.F poin 2, scoped 1 siswa).
4. Info tagihan (query sama seperti §4.F poin 1, scoped 1 siswa).

**Non-goal**: pengumuman kelas — TIDAK ADA modul "Pengumuman" di sistem saat ini (grep akan mengonfirmasi ini di task terkait; kalau ternyata ada, baru dimasukkan; kalau tidak, dihilangkan dari scope tanpa mengganti dengan fitur fiktif).

## 5. Kriteria Penerimaan

1. Setiap dashboard baru/diperluas TIDAK memunculkan komponen Blade baru selain yang sudah ada (`x-hero-banner`, `x-stat-tile`, `x-panel`, `x-badge`, `x-icon`) — kecuali chart Alpine baru di `dashboard-charts.js` mengikuti pola existing.
2. Semua data berasal dari query nyata ke database, tidak ada data dummy/hardcoded.
3. Setiap dashboard yang datanya bisa kosong (kelas belum ada nilai, karyawan belum ada config kuota, dst) WAJIB menampilkan state kosong yang jelas, bukan error/crash.
4. Test existing (`tests/Feature/DashboardTest.php`, `tests/Feature/KaryawanDashboardTest.php`, `tests/Feature/Admin/DashboardYayasanTest.php`, `tests/Feature/Admin/DashboardLembagaTest.php`) tetap PASS, plus test baru untuk tiap penambahan data.
5. TIDAK ADA regresi terhadap isolasi tenant (`TenantScope`/`BelongsToTenant`) — semua query baru discope ke lembaga/yayasan yang benar.
