# 🏗️ Spesifikasi Arsitektur: Penyempurnaan Modul Akademik & Fondasi Data Induk

- **Document ID / Slug:** `2026-08-17-1015-penyempurnaan-modul-akademik`
- **Spec File:** `.agents/specs/2026-08-17-1015-penyempurnaan-modul-akademik.md`
- **Plan File:** `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`
- **Handoff Log File:** `.agents/logs/2026-08-17-1015-penyempurnaan-modul-akademik.md`
- **Tanggal & Waktu:** 17 Agustus 2026, 10:15 WIB
- **Target Framework & Standar:** Laravel 12, PHP 8.3+, Tailwind CSS, Alpine.js, DomPDF, Spatie Permissions, `laravel-feature-standard`
- **Status:** APPROVED & LOCKED (Dokumen Rujukan Tunggal Implementasi)

---

## 1. Ringkasan Eksekutif & Tujuan Proyek

Proyek ini bertujuan melakukan perombakan dan penyempurnaan menyeluruh (*comprehensive enhancement*) pada **Modul Akademik** serta penataan ulang **Modul Data Induk** di repositori **Pintera App**. 

Transformasi ini memindahkan logika bisnis dari controller monolitik legacy di `app/Http/Controllers/Admin/` dan `app/Http/Controllers/Guru/` ke dalam arsitektur **Domain-Oriented Monolith** terstandarisasi (`App\Domains\Akademik\` dan `App\Domains\DataInduk\`) dengan prinsip:
1. **Multi-Jenjang Adaptif (*Multi-Level Education Support*):** Menyediakan logika KBM, presensi, dan format rapor yang otomatis beradaptasi dari **PAUD/KB/TK, SD/MI, SMP/MTs, SMA/MA, hingga SMK**.
2. **Dukungan Klien Ganda (*Dual-Deployment Support*):** Satu basis kode (*single codebase*) yang otomatis menyesuaikan diri untuk klien **Yayasan Multi-Unit** maupun **Sekolah Mandiri / Single Tenant**.
3. **Otorisasi Hybrid Presisi:** Memadukan Spatie Permission untuk hak kapabilitas sistem dengan Policy Relasional untuk peran fungsional dinamis (*Wali Kelas*, *Guru Mapel*, *Waka Kurikulum*).
4. **Dynamic Permission Auto-Discovery & Modular Routing:** Pemisahan rute per portal persona (`yayasan`, `lembaga`, `guru`, `siswa`, `orang-tua`) yang tersinkronisasi otomatis dengan Role Builder UI.
5. ***Zero Regression Guarantee*:** Mempertahankan integritas 67 test case eksisting yang telah lulus 100%.

---

## 2. Model Otorisasi, Scope & Multi-Role (Hybrid Architecture)

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                        MODEL OTORISASI & SCOPE HYBRID                                  │
├───────────────────────────────────────────┬────────────────────────────────────────────┤
│ 1. SPATIE ROLES (System Capability)       │ 2. CONTEXTUAL POLICY (Relational Roles)    │
│    • yayasan_super_admin (Scope: yayasan) │    • Guru Mapel  : jadwal_pelajaran        │
│    • kepala_sekolah     (Scope: lembaga)  │    • Guru Kelas  : guru.jenis_ptk          │
│    • admin_akademik     (Scope: lembaga)  │    • Wali Kelas  : kelas.wali_kelas_guru_id│
│    • guru               (Scope: personal) │    • Waka Kur.   : guru_jabatan_tambahan   │
│    • siswa              (Scope: personal) │                                            │
│    • orang_tua          (Scope: personal) │                                            │
└───────────────────────────────────────────┴────────────────────────────────────────────┘
```

### 2.1. Tiga Tingkat Scope Level (`scope_level`)
- **Scope Yayasan (`yayasan`):** Pengguna dapat mengakses data seluruh lembaga di bawah yayasan via session switcher `active_lembaga_id`.
- **Scope Lembaga (`lembaga`):** Pengguna terkunci hanya pada unit lembaga tempatnya ditugaskan (`user.lembaga_id`).
- **Scope Diri Sendiri (`diri_sendiri`):** Pengguna hanya berhak membaca/menulis data miliknya sendiri (data pengajaran guru, nilai siswa, data anak orang tua).
- **Resolusi Scope Otomatis:** `User::widestScopeLevel()` mengekstrak level tertinggi. Jika user memiliki role `guru` + `admin_akademik`, user otomatis memperoleh scope `lembaga` sehingga dapat mengakses portal kurikulum lembaga sekaligus mengajar.

### 2.2. Validasi Relasional Kontekstual
Otorisasi operasional KBM tidak menggunakan role Spatie statis baru, melainkan diverifikasi melalui Policy berbasis relasi database riil:
1. **Guru Mata Pelajaran:** Divalidasi apakah `jadwal_pelajaran` dengan `guru_id = auth()->user()->guru->id`, `kelas_id`, dan `mata_pelajaran_id` eksis.
2. **Wali Kelas:** Divalidasi apakah `kelas.wali_kelas_guru_id === auth()->user()->guru->id`.
3. **Waka Kurikulum / Struktural:** Divalidasi apakah `guru_jabatan_tambahan` mencatat jabatan aktif terhubung ke `JabatanTambahanMaster` terkait.

---

## 3. Dual-Deployment: Multi-Unit Yayasan vs Sekolah Mandiri (Single Tenant)

Aplikasi dibangun dari satu codebase universal yang otomatis mendeteksi deployment context:

| Komponen Sistem | Mode Yayasan Multi-Unit | Mode Sekolah Mandiri (Single Tenant) |
|---|---|---|
| **Struktur Organisasi** | 1 Yayasan $\to$ Banyak Unit (KB, SD, SMP, SMA, SMK) | 1 Lembaga Mandiri |
| **Resolusi `TenantContext`** | Membaca switcher `session('active_lembaga_id')` | Otomatis mengunci ke `lembaga_id` tunggal |
| **UI Switcher di Header** | Aktif dirender di Navbar | Disembunyikan otomatis (`Lembaga::count() <= 1`) |
| **Alur Approval Workflow** | Multi-Tier: Staff $\to$ Kepsek $\to$ Yayasan | Single-Tier: Staff $\to$ Kepsek (Final) |
| **Rute Portal Yayasan** | Aktif `/yayasan/*` untuk pengurus | Auto-redirect ke `/lembaga/dashboard` |

---

## 4. Fondasi Modul Data Induk (`App\Domains\DataInduk\`)

Data Induk bertindak sebagai fondasi (*spine*) yang menopang Modul Akademik:

```text
app/Domains/DataInduk/
├── Actions/
│   ├── Siswa/
│   │   ├── CreateSiswaAction.php
│   │   ├── UpdateSiswaAction.php
│   │   ├── ImportSiswaAction.php
│   │   └── UpdateStatusSiswaAction.php
│   ├── Guru/
│   │   ├── CreateGuruAction.php
│   │   ├── UpdateGuruAction.php
│   │   └── AssignJabatanTambahanAction.php
│   └── Kelembagaan/
│       ├── UpdateLembagaAction.php
│       └── ManageDataPeriodikAction.php
└── DataTransferObjects/
    ├── SiswaData.php
    └── GuruData.php
```

### 4 Cluster Data Induk:
1. **Cluster Kelembagaan:** `Yayasan`, `Lembaga` (`bentuk_pendidikan`, `hari_libur_mingguan`), `Gedung`, `Ruangan` (Sarpras).
2. **Cluster SDM / PTK:** `Guru` (NIK, NUPTK, NIP, Jenis PTK, Akun User), `RiwayatPendidikanGuru`, `SertifikasiGuru`, `GuruJabatanTambahan`, `Karyawan`.
3. **Cluster Siswa & Keluarga:** `Siswa` (NIS/NISN, status: `aktif`/`lulus`/`pindah`/`keluar`), `OrangTua`, `SiswaOrangTua` (`hubungan`, `is_kontak_utama`), `AkunSiswaGenerator`.
4. **Cluster Struktur Kurikulum & Waktu:** `TahunAjaran`, `Semester`, `KalenderAkademik`, `PolaJam`, `JamPelajaran`, `MataPelajaran`, `Kelas`.

---

## 5. Spesifikasi Fungsional Modul Akademik (`App\Domains\Akademik\`)

```text
app/Domains/Akademik/
├── Actions/
│   ├── Jadwal/
│   │   ├── CreateJadwalPelajaranAction.php
│   │   ├── UpdateJadwalPelajaranAction.php
│   │   └── DuplicateJadwalPelajaranAction.php
│   ├── Rpp/
│   │   ├── CreateRppAction.php
│   │   ├── SubmitRppAction.php
│   │   └── VerifyRppAction.php
│   ├── Kbm/
│   │   ├── RecordJurnalKbmAction.php
│   │   └── RecordPresensiSiswaAction.php
│   └── Rapor/
│       ├── SubmitPengajuanRaporAction.php
│       ├── VerifyPengajuanRaporAction.php
│       ├── ApprovePengajuanRaporAction.php
│       └── SimpanCatatanWaliKelasAction.php
├── DataTransferObjects/
│   ├── JadwalPelajaranData.php
│   ├── RppData.php
│   ├── JurnalKbmData.php
│   ├── PresensiSiswaData.php
│   └── PengajuanRaporData.php
├── Enums/
│   ├── StatusRpp.php (Draft, Diajukan, Disetujui, PerluRevisi)
│   └── StatusPengajuanRapor.php (Draft, Diajukan, Diverifikasi, Disetujui, Ditolak)
├── Models/
│   ├── Rpp.php
│   ├── PengajuanRapor.php
│   └── CatatanWaliKelas.php
└── Services/
    ├── RaporCalculationService.php
    ├── CapaianKompetensiGenerator.php
    └── PresensiAggregationService.php
```

---

### 5.1. Pilar 1 — Penjadwalan KBM (Roster) & Sarpras Anti-Bentrok

#### Kebutuhan Fungsional:
1. **Integrasi Ruangan Sarpras:** Form pembuatan/pengubahan jadwal mengintegrasikan dropdown `ruangan_id` (default otomatis mengambil dari `kelas.ruangan_id` / Home Room).
2. **Validasi Pencegahan Bentrok Ganda (*Collision Prevention*):**
   Sebelum data jadwal disimpan ke database, sistem menjalankan dua layer validasi:
   - **Layer 1 (Anti-Bentrok Ruangan):** Memanggil [`ValidateRoomClashAction`](file:///d:/laragon/www/pintera-app/app/Domains/Sarpras/Actions/ValidateRoomClashAction.php) untuk memeriksa apakah `ruangan_id` yang dipilih sudah dipakai kelas lain pada `jam_pelajaran_id` dan `semester_id` yang sama.
   - **Layer 2 (Anti-Bentrok Guru):** Memeriksa apakah `guru_id` yang sama sudah memiliki jadwal mengajar di kelas lain pada `jam_pelajaran_id` dan `semester_id` yang sama.
3. **Duplikasi Jadwal:** Memungkinkan kurikulum menyalin seluruh konfigurasi jadwal dari satu semester/kelas ke semester/kelas tujuan.

---

### 5.2. Pilar 2 — Manajemen Perangkat Mengajar (RPP / Modul Ajar)

#### Kebutuhan Fungsional:
1. **Model Dokumen Unggah:** Guru mengunggah berkas RPP / Modul Ajar (PDF/Docx, maks 10MB) dengan metadata:
   - Mata Pelajaran (`mata_pelajaran_id`)
   - Kelas (`kelas_id`)
   - Semester (`semester_id`)
   - Judul Topik / Lingkup Materi (`judul_topik`)
   - Alokasi Waktu Pertemuan (`alokasi_waktu`)
2. **Siklus Verifikasi Kurikulum:**
   - Guru membuat berkas $\to$ status `Draft`.
   - Guru menekan tombol ajukan $\to$ status `Diajukan` $\to$ tercatat pada inbox verifikasi Waka Kurikulum.
   - Waka Kurikulum meninjau berkas $\to$ memilih `Disetujui` atau `PerluRevisi` (+ catatan revisi wajib diisi jika ditolak/minta perbaikan).

---

### 5.3. Pilar 3 — Jurnal KBM & Presensi Siswa Adaptif Multi-Jenjang

#### Kebutuhan Fungsional:
1. **Mode Adaptif Berbasis `bentuk_pendidikan` Lembaga:**
   - **Mode A (Kelas-Centric / Harian) &mdash; Jenjang KB, TK, SD:**
     - Presensi dicatat **1 kali sehari** di pagi hari oleh Guru Kelas.
     - Jurnal mencatat tema/sentra, aktivitas bermain, atau materi harian kelas.
   - **Mode B (Roster / Sesi-Centric) &mdash; Jenjang SMP, SMA, SMK:**
     - Presensi dicatat per jam pelajaran tatap muka oleh Guru Mata Pelajaran.
     - Jurnal mencatat agenda materi pembelajaran, pencapaian TP, dan kendala kelas.
2. **Universal Attendance Aggregation (`PresensiAggregationService`):**
   - Mengakumulasi data presensi sesi seluruh semester menjadi rekapitulasi kehadiran dalam satuan **Hari**:
     - **Sakit (S)**, **Izin (I)**, dan **Alpa / Tanpa Keterangan (A)**.
   - Angka rekapitulasi hari ini otomatis ditarik saat pembentukan lembar E-Rapor kelas.

---

### 5.4. Pilar 4 — Adaptive E-Rapor Engine & Pengesahan Berjenjang

#### Kebutuhan Fungsional:
1. **Generator Narasi Capaian Kompetensi Otomatis (`CapaianKompetensiGenerator`):**
   - Mengambil data Tujuan Pembelajaran dari [`KomponenPenilaian`](file:///d:/laragon/www/pintera-app/app/Models/KomponenPenilaian.php) dan nilai angka dari [`NilaiSiswa`](file:///d:/laragon/www/pintera-app/app/Models/NilaiSiswa.php).
   - Menghitung rata-rata skor per TP:
     - TP dengan nilai tertinggi $\ge KKTP$ $\to$ diubah menjadi kalimat: *"Menunjukkan penguasaan sangat baik dalam [Deskripsi TP]"*.
     - TP dengan nilai terendah $< KKTP$ $\to$ diubah menjadi kalimat: *"Perlu bimbingan dan pendampingan dalam [Deskripsi TP]"*.
   - Guru / Wali Kelas memiliki keleluasaan untuk menyunting narasi sebelum diajukan.
2. **Alur Persetujuan Berjenjang (*Stateful Approval Workflow*):**
   - **Tahap 1 (Guru Mapel):** Input nilai asesmen formatif/sumatif $\to$ sistem mengalkulasi nilai akhir & narasi TP.
   - **Tahap 2 (Wali Kelas):** Membuka lembar kelas $\to$ melengkapi catatan sikap, ekstrakurikuler, rekap presensi $\to$ menekan tombol **Ajukan Rapor**.
   - **Tahap 3 (Waka Kurikulum):** Memeriksa kelengkapan nilai seluruh mapel di kelas tersebut $\to$ menekan tombol **Verifikasi Rapor**.
   - **Tahap 4 (Kepala Sekolah):** Memberikan **Persetujuan Akhir (Approval & Kunci Nilai)** $\to$ status menjadi `Disetujui` dan nilai terkunci permanen.
3. **4 Template PDF Resmi DomPDF (`resources/views/pdf/rapor/`):**
   - `paud.blade.php`: Naratif kualitatif 3 elemen CP (Nilai Agama & Moral, Jati Diri, Literasi & STEAM) + rekap pertumbuhan fisik (TB/BB/Lingkar Kepala).
   - `sd.blade.php`: Nilai angka mata pelajaran dasar/tematik + narasi capaian TP + ekstrakurikuler + absensi (hari).
   - `smp-sma.blade.php`: Nilai angka mata pelajaran umum & pilihan peminatan (Fase F SMA) + narasi capaian TP + ekstrakurikuler + absensi.
   - `smk.blade.php`: Nilai mapel umum & kejuruan konsentrasi keahlian + lembar nilai PKL Industri (DU/DI) + portofolio UKK.

---

## 6. Skema Database Detail (Tabel Baru & Alterasi)

### 6.1. Migrasi 1: Tabel RPP (`create_rpp_table.php`)
```php
Schema::create('rpp', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
    $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
    $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
    $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
    $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
    $table->string('judul_topik', 255);
    $table->string('alokasi_waktu', 50)->default('2 JP');
    $table->string('file_path')->nullable();
    $table->string('file_name')->nullable();
    $table->enum('status_verifikasi', ['draft', 'diajukan', 'disetujui', 'perlu_revisi'])->default('draft');
    $table->text('catatan_revisi')->nullable();
    $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('diverifikasi_pada')->nullable();
    $table->timestamps();

    $table->index(['lembaga_id', 'guru_id', 'semester_id'], 'idx_rpp_lmbg_guru_smt');
});
```

### 6.2. Migrasi 2: Tabel Pengajuan Rapor (`create_pengajuan_rapor_table.php`)
```php
Schema::create('pengajuan_rapor', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
    $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
    $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
    $table->enum('status', ['draft', 'diajukan', 'diverifikasi', 'disetujui', 'ditolak'])->default('draft');
    $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('diajukan_pada')->nullable();
    $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('diverifikasi_pada')->nullable();
    $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('disetujui_pada')->nullable();
    $table->text('catatan_revisi')->nullable();
    $table->date('tanggal_rapor')->nullable();
    $table->timestamps();

    $table->unique(['kelas_id', 'semester_id']);
    $table->index(['lembaga_id', 'semester_id', 'status'], 'idx_pengajuan_rapor_status');
});
```

### 6.3. Migrasi 3: Tabel Catatan Wali Kelas & Pelengkap Rapor (`create_catatan_wali_kelas_table.php`)
```php
Schema::create('catatan_wali_kelas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
    $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
    $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
    $table->text('catatan_sikap')->nullable();
    $table->text('catatan_perkembangan')->nullable(); // Khusus PAUD
    $table->json('ekstrakurikuler')->nullable();      // [{nama: 'Pramuka', predikat: 'A', keterangan: 'Sangat Aktif'}]
    $table->json('prestasi')->nullable();             // [{jenis: 'Juara 1 OSN', keterangan: 'Tingkat Kabupaten'}]
    $table->json('pkl_info')->nullable();             // Khusus SMK: [{mitra: 'PT Telkom', durasi: '3 Bulan', nilai: 90, catatan: 'Baik'}]
    $table->string('keterangan_kenaikan', 50)->nullable(); // Naik ke Kelas X / Lulus
    $table->timestamps();

    $table->unique(['siswa_id', 'semester_id']);
});
```

---

## 7. Arsitektur Rute Dinamis & Permissions Auto-Discovery

### 7.1. Struktur File Rute Modular (`routes/`)
```text
routes/
├── auth.php
├── yayasan.php       (Scope: yayasan | Prefix: /yayasan | Name: yayasan.)
├── lembaga.php       (Scope: lembaga | Prefix: /admin   | Name: admin.)
├── guru.php          (Scope: guru    | Prefix: /guru    | Name: guru.)
├── siswa.php         (Scope: siswa   | Prefix: /siswa   | Name: siswa.)
└── orang-tua.php     (Scope: ortu    | Prefix: /ortu    | Name: ortu.)
```

### 7.2. Daftar Permission Atomik Baru (Auto-Discovered by SyncPermissions):
```text
• akademik.jadwal.view             • akademik.rpp.upload
• akademik.jadwal.create           • akademik.rpp.verify
• akademik.jadwal.edit             • akademik.kbm.jurnal
• akademik.jadwal.delete           • akademik.kbm.presensi
• akademik.jadwal.duplicate        • akademik.rapor.input-wali
• akademik.rapor.view              • akademik.rapor.verify
• akademik.rapor.ajukan            • akademik.rapor.approve
• akademik.rapor.cetak             • data-induk.siswa.manage
```

---

## 8. Kriteria Penerimaan & Verifikasi Pengujian

1. **Jadwal & Sarpras Collision Prevention:**
   - Uji coba menyimpan jadwal pada ruangan yang sama di jam/semester yang sama $\to$ HTTP 422 / Validation Error `"Ruangan sudah terisi pada jam ini"`.
   - Uji coba menugaskan guru yang sama di kelas berbeda pada jam/semester yang sama $\to$ HTTP 422 / Validation Error `"Guru sudah memiliki jadwal mengajar di kelas lain pada jam ini"`.
2. **RPP Approval Lifecycle:**
   - Guru mengunggah file RPP $\to$ status `Draft` $\to$ klik ajukan $\to$ status `Diajukan`.
   - Waka Kurikulum membuka inbox verifikasi $\to$ beri catatan revisi $\to$ status `PerluRevisi` $\to$ guru re-upload $\to$ disetujui $\to$ status `Disetujui`.
3. **Adaptive Attendance & KBM:**
   - Pada lembaga KB/TK/SD: Form guru menampilkan mode Presensi Harian 1x.
   - Pada lembaga SMP/SMK: Form guru menampilkan mode Presensi Sesi Jam Pelajaran.
   - `PresensiAggregationService` menghitung total hari S/I/A akurat untuk semester aktif.
4. **E-Rapor Workflow & DomPDF Output:**
   - Auto-narasi TP menghasilkan kalimat capaian tertinggi dan terendah secara otomatis.
   - Wali kelas melengkapi catatan sikap & ekskul $\to$ ajukan rapor $\to$ kurikulum verifikasi $\to$ kepsek approval (status `Disetujui` mengunci nilai).
   - Cetak PDF menghasilkan 4 layout resmi yang presisi sesuai `bentuk_pendidikan` (PAUD, SD, SMP/SMA, SMK).
5. **Zero Regression Test Suite:**
   - Menjalankan `php artisan test` $\to$ Seluruh pengujian (67 existing + unit/feature test baru) lulus 100% tanpa kegagalan.
