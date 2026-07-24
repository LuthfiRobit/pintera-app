# Presensi & Asesmen Siswa — Fondasi Akademik

## Konteks

Modul SPMB (Sistem Penerimaan Murid Baru) sudah selesai dan berjalan di lapangan. Pekerjaan berikutnya masuk ke ranah pembelajaran: presensi dan asesmen siswa, sesuai standar Kemendikdasmen, untuk lembaga yang menaungi jenjang KB, TPA, SPS, TK, SD, SMP, SMA, SMK, dan SLB (`lembaga.bentuk_pendidikan`) sekaligus — satu lembaga selalu satu jenjang, tapi platform ini menaungi banyak lembaga dengan jenjang berbeda-beda.

Sebelum modul ini, fondasi data akademik yang sudah ada hanya `TahunAjaran`, `Semester`, dan `Guru` (profil, belum tentu terhubung ke akun login). Belum ada `Siswa`, `Kelas`, `MataPelajaran`, `Jadwal`, atau `KalenderAkademik` — semuanya dibangun dari nol di dokumen ini, mengikuti pola arsitektur yang sudah ada (Laravel 12, MVC + Service layer tipis, Blade server-rendered, multi-tenant via `BelongsToTenant`/`TenantScope` pada `lembaga_id`, `spatie/laravel-permission`, `spatie/activitylog`).

Karena jenjang pendidikan yang dilayani sangat beragam (PAUD tanpa mata pelajaran formal, SD–SMK dengan mata pelajaran & jam pelajaran, SMK dengan PKL, SLB dengan kebutuhan individual), keputusan desain berulang kali diarahkan ke **model data generik** yang beradaptasi lewat konfigurasi per lembaga, bukan tabel/kode terpisah per jenjang.

## Tujuan

Membangun fondasi data akademik (Siswa, Kelas, Mata Pelajaran), Kalender Akademik dinamis, Jadwal & Jam Pelajaran, Presensi (lewat Sesi Pembelajaran + Jurnal Guru), dan Asesmen siswa berbasis Kurikulum Merdeka — cukup generik untuk dipakai semua jenjang (KB–SLB) tanpa migrasi besar saat jenjang baru mulai aktif dipakai.

## Prinsip Desain

- **Generik lintas jenjang**: satu model `Kelas`, satu model `MataPelajaran` (disebut "Unit Asesmen"), satu model `presensi` dengan konsep *mode* — bukan tabel terpisah per bentuk_pendidikan.
- **PHP native Enum** untuk semua nilai pilihan tetap (status, jenis, tipe) — dipakai konsisten di model/controller/view via cast, bukan string mentah bertebaran.
- **Duplikasi, bukan sinkronisasi live**: fitur "salin dari tahun ajaran sebelumnya" menghasilkan data baru yang independen dan bebas diedit, tidak terikat referensi hidup ke sumbernya (beda konsep dengan `sync()` pivot many-to-many yang sudah dipakai di modul SPMB).
- **2 lapis akses terpisah**: RBAC (fitur apa yang boleh diakses, lewat permission) dan data scoping (baris data mana yang terlihat, lewat query ownership) — keduanya wajib ada, tidak saling menggantikan.
- **Out of scope sengaja ditunda** (lihat Section 10) supaya versi dasar bisa jalan lebih cepat: Projek Penguatan Profil Pelajar Pancasila (P5), dukungan penuh K13/KTSP, Portal Siswa/Wali Murid, kompleksitas PPI penuh untuk SLB.

## Global Constraints

- Multi-tenant: semua tabel baru yang scoped ke satu lembaga pakai `lembaga_id` + trait `BelongsToTenant`/`TenantScope` yang sudah ada, konsisten dengan `tahun_ajaran`, `guru`, dll.
- Audit: model yang datanya sensitif/perlu jejak (Siswa, Kelas, Kalender Akademik, Nilai) pakai `Spatie\Activitylog` seperti model lain di project.
- Migration & model mengikuti gaya yang sudah ada: `Schema::create` sederhana, `foreignId()->constrained()->cascadeOnDelete()`, tanpa Form Request kompleks kecuali memang dibutuhkan validasi berat.
- Tidak ada perubahan pada `CalonMurid`/`Pendaftaran` (data SPMB tetap jadi arsip riwayat pendaftaran, tidak diedit ulang setelah siswa aktif).

---

## 1. Fondasi Data Akademik

### 1.1 `Siswa`

Satu model generik untuk semua jenjang. Punya 3 kemungkinan jalur asal, dilacak lewat kolom `sumber_data`:

```
siswa
├── lembaga_id, kelas_id (nullable saat baru dibuat sebelum ditempatkan)
├── calon_murid_id (nullable — hanya terisi jalur SPMB)
├── pendaftaran_asal_id (nullable — hanya terisi jalur SPMB)
├── sumber_data (Enum: spmb, import, manual)
├── nis (nomor induk siswa, spesifik per lembaga)
├── nisn
├── nama_lengkap, jenis_kelamin, tempat_lahir, tanggal_lahir, agama
├── status (Enum: aktif, lulus, pindah, keluar)
└── timestamps
```

Field identitas (`nama_lengkap`, `tanggal_lahir`, dst) adalah **snapshot independen**, bukan relasi baca ke `CalonMurid` — supaya data siswa bisa dikoreksi bertahun-tahun ke depan (alamat pindah, dsb) tanpa mengubah arsip data pendaftaran SPMB asli. `calon_murid_id`/`pendaftaran_asal_id` disimpan murni untuk jejak audit/telusur balik.

**3 jalur pembuatan Siswa:**

1. **Dari SPMB** — halaman "Pendaftaran Siap Didaftarkan": tabel berisi `Pendaftaran` berstatus `aktif` (diterima **dan** tagihan daftar ulang lunas — lihat `Pendaftaran::isAktif` yang sudah ada) yang belum punya `Siswa` terkait. Admin centang beberapa baris (checkbox, biasanya 1 gelombang/jalur sekaligus), pilih **satu Kelas tujuan**, klik "Daftarkan sebagai Siswa" → sistem bikin `Siswa` per baris tercentang sekaligus set `kelas_id`. Kolom NIS di form ini **pre-filled otomatis** (urut per lembaga per tahun ajaran) tapi bisa ditimpa manual per baris sebelum submit.

2. **Import massal (Excel)** — untuk siswa lama/pindahan yang tidak melalui SPMB Pintera:
   - Template kolom: NISN, NIS, nama_lengkap, jenis_kelamin, tempat_lahir, tanggal_lahir, agama, **nama kelas**.
   - Upload → validasi per baris (NISN wajib unik, nama kelas harus persis cocok dengan `Kelas` yang sudah ada di lembaga — tidak auto-membuat kelas baru dari typo).
   - Preview: baris valid vs error ditampilkan terpisah sebelum commit; admin bisa commit yang valid dulu, perbaiki sisanya, upload ulang.
   - `calon_murid_id`/`pendaftaran_asal_id` = NULL.

3. **Input manual (form satu-satu)** — untuk kasus susulan/kecil. Field sama seperti kolom import, tapi Kelas dipilih dari dropdown (bukan ketik bebas) supaya tidak mismatch.

### 1.2 `Kelas`

Satu model generik untuk rombongan belajar di semua jenjang (bukan tabel terpisah untuk "Kelompok" PAUD vs "Kelas+Tingkat" SD–SMK):

```
kelas
├── lembaga_id, tahun_ajaran_id
├── nama (bebas: "Kelompok A", "6A", "XI IPA 2")
├── tingkat (nullable — PAUD boleh kosong/pakai kelompok usia sbg nama, SD-SMK diisi numerik/romawi)
├── pola_jam_id (lihat Section 3)
├── wali_kelas_guru_id (nullable, guru)
└── timestamps
```

### 1.3 `MataPelajaran` (Unit Asesmen)

Satu model generik menampung baik mata pelajaran formal (SD–SMK) maupun aspek perkembangan STPPA (PAUD: Nilai Agama & Budi Pekerti, Jati Diri, Dasar Literasi-Matematika-Sains-Teknologi-Rekayasa-Seni):

```
mata_pelajaran
├── lembaga_id
├── nama
├── tipe (Enum: mapel, aspek_perkembangan)
└── timestamps
```

Field `tipe` membedakan istilah/konteks tampilan saja — struktur relasi ke Jadwal, Sesi Pembelajaran, dan Asesmen tetap satu jalur untuk keduanya.

---

## 2. Kalender Akademik & Hari Libur Dinamis

### 2.1 Layered Calendar Resolution

Status suatu tanggal untuk suatu lembaga ditentukan lewat 3 lapis, dari yang paling spesifik menang lebih dulu:

```
1. Entri kalender_akademik milik lembaga ini untuk tanggal tsb? → final (bisa tipe 'libur' atau 'kerja')
2. Entri kalender_akademik nasional (lembaga_id NULL) untuk tanggal tsb? → ikuti itu
3. Tanggal tsb match hari_libur_mingguan milik lembaga? → libur rutin
4. Default → hari efektif belajar
```

`tipe = 'kerja'` pada entri milik lembaga berfungsi sebagai **override** yang membatalkan status libur nasional untuk lembaga itu saja (contoh: 1 Januari tetap masuk sekolah untuk lembaga tertentu).

### 2.2 Skema

```php
// Migration baru: tambah kolom ke tabel lembaga
Schema::table('lembaga', function (Blueprint $table) {
    $table->json('hari_libur_mingguan')->default(json_encode([0])); // 0=Minggu..6=Sabtu
});
```

```php
// Migration baru: create kalender_akademik
Schema::create('kalender_akademik', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete(); // NULL = nasional
    $table->date('tanggal');
    $table->string('nama');
    $table->enum('tipe', ['libur', 'kerja']);
    $table->text('keterangan')->nullable();
    $table->timestamps();

    $table->index(['lembaga_id', 'tanggal']);
});
```

Catatan: constraint uniqueness "1 tanggal 1 entri per lembaga" **tidak** dipaksakan lewat DB unique index (MySQL menganggap tiap NULL berbeda, sehingga tidak efektif untuk entri nasional) — divalidasi di level Form Request saat create/update.

`App\Enums\TipeKalenderAkademik` (backed enum: `Libur = 'libur'`, `Kerja = 'kerja'`) dipakai lewat cast di model `KalenderAkademik`.

### 2.3 Query per Tahun Ajaran (tanpa FK `tahun_ajaran_id`)

Kalender **sengaja tidak** punya kolom `tahun_ajaran_id` — kalender berjalan berdasar tanggal kalender terus-menerus (seperti kalender dinding), bukan dikotak-kotakkan per tahun ajaran. Selain itu satu entri nasional harus berlaku ke semua lembaga meski tiap lembaga punya `TahunAjaran` dengan rentang tanggal yang bisa berbeda — menambah FK justru memaksa satu entri nasional dikaitkan ke banyak `tahun_ajaran_id` sekaligus, bertentangan dengan desain "1 entri nasional berlaku semua lembaga".

Untuk kebutuhan cetak/filter per tahun ajaran, query cukup pakai rentang tanggal dari `TahunAjaran` yang dipilih:

```php
$entriKalender = KalenderAkademik::whereBetween('tanggal', [
        $tahunAjaran->tanggal_mulai, $tahunAjaran->tanggal_selesai,
    ])
    ->where(fn ($q) => $q->whereNull('lembaga_id')->orWhere('lembaga_id', $tahunAjaran->lembaga_id))
    ->get();
```

Index `(lembaga_id, tanggal)` yang sudah didefinisikan di atas cukup untuk performa query ini — tidak ada baris "kedaluwarsa" yang perlu disaring lewat FK terpisah.

---

## 3. Jadwal & Jam Pelajaran

### 3.1 Struktur berlapis

Struktur jam bisa berbeda per hari (misal Senin ada Upacara) **dan** per tingkat/kelompok kelas dalam satu lembaga (misal kelas rendah SD pulang lebih awal dari kelas tinggi):

```
pola_jam                              (template jam yang reusable, dipakai banyak kelas)
├── lembaga_id
└── nama ("Kelas Rendah 1-3", "Kelas Tinggi 4-6", "Kelompok Bermain")

jam_pelajaran                         (slot waktu di dalam satu pola, per hari)
├── pola_jam_id
├── hari (Enum: senin..minggu)
├── urutan
├── label ("Upacara", "Jam ke-1", "Istirahat", "Sholat Dzuhur", ...)
├── jam_mulai, jam_selesai
└── is_pelajaran (boolean — slot belajar vs istirahat/upacara/sholat)

kelas.pola_jam_id                     (tiap kelas "berlangganan" ke satu pola jam)

jadwal_pelajaran                      (menghubungkan jam ke mapel & guru)
├── kelas_id
├── jam_pelajaran_id                  (sudah tersirat hari & pola-nya, tidak perlu kolom hari terpisah)
├── mata_pelajaran_id (nullable — PAUD bisa kosong/pakai aspek umum)
├── guru_id
└── semester_id                       (jadwal berlaku per semester)
```

Alur admin: buat Pola Jam sekali → assign ke tiap Kelas → isi Jadwal Pelajaran per slot per kelas per semester. `jam_pelajaran` dengan `is_pelajaran = false` tidak muncul sebagai pilihan saat mengisi `jadwal_pelajaran`.

---

## 4. Sesi Pembelajaran, Presensi & Jurnal Guru

### 4.1 Kenapa perlu entitas perantara

Jurnal mengajar guru (materi yang diajarkan, catatan) adalah **1 entri per sesi kelas**, bukan per siswa. Kalau disatukan ke tabel presensi, tiap baris presensi siswa akan menyimpan data materi yang sama berulang — redundan dan membebani query rekap kehadiran (yang harus tetap ringan/cepat). Solusinya: entitas perantara `sesi_pembelajaran`.

```
sesi_pembelajaran                     (1 baris per kelas per hari/jam pelajaran)
├── jadwal_pelajaran_id (nullable — kosong utk mode di luar jadwal formal, misal PKL)
├── kelas_id, guru_id, mata_pelajaran_id (nullable)
├── tanggal, jam_mulai, jam_selesai
├── materi (jurnal mengajar guru)
├── status (Enum: terlaksana, diganti, kosong)
└── timestamps

presensi                              (1 baris per siswa per sesi_pembelajaran)
├── sesi_pembelajaran_id
├── siswa_id
├── status (Enum: hadir, izin, sakit, alpa, terlambat)
├── keterangan (nullable)
└── timestamps
```

### 4.2 Mode presensi — satu model, bukan tabel terpisah

- **Harian** (PAUD/SD): 1 `sesi_pembelajaran` per hari per kelas.
- **Per jam pelajaran** (SMP/SMA/SMK Kurikulum Merdeka): 1 `sesi_pembelajaran` per jam pelajaran per kelas — tiap guru mapel isi presensi & jurnal sendiri.
- **PKL** (SMK): `sesi_pembelajaran` dengan guru = pembimbing PKL, tanpa `jadwal_pelajaran_id` formal, dicatat harian di luar sekolah.

Semua mode tetap menghasilkan baris di tabel `presensi` yang sama sempit/seragam — rekap kehadiran lintas mode tetap satu query, tidak perlu union beberapa tabel.

---

## 5. Duplikasi Awal Tahun Ajaran

Wizard yang muncul saat admin mengaktifkan `TahunAjaran` baru, dengan 4 langkah opsional (bisa dicentang/lewati per bagian). **Semua hasil salinan adalah starting point yang independen dan bebas diedit manual** setelahnya — bukan sinkronisasi live yang tetap terhubung ke sumbernya (istilah fitur ini sengaja **"Duplikasi"**, bukan "Sinkron", supaya tidak rancu dengan `sync()` pivot many-to-many yang sudah dipakai di modul SPMB/Gelombang-Jalur).

**Langkah 1 — Salin Pola Jam & Jam Pelajaran.** Toggle per pola.

**Langkah 2 — Salin Mata Pelajaran/Unit Asesmen.**

**Langkah 3 — Kenaikan Kelas (pemetaan, bukan salin polos).** Admin memetakan kelas lama → kelas baru, siswa ikut berpindah otomatis:
```
Kelas 5A (tahun lalu) → Kelas 6A (tahun ini)   [siswa ikut pindah otomatis]
Kelas 6A (tahun lalu) → LULUS (tidak dipetakan)  [siswa.status = lulus]
```

**Langkah 4 — Salin Jadwal Pelajaran** (opsional, berdasar pemetaan Langkah 3) — struktur (jam & mapel) disalin sebagai starting point; guru mapel biasanya tetap perlu direview manual.

Siswa baru dari SPMB (Section 1.1) masuk terpisah di luar wizard ini, ditempatkan ke kelas hasil Langkah 3.

---

## 6. Asesmen Siswa (Kurikulum Merdeka)

Fokus tahap ini: **Asesmen Sumatif saja** (Kurikulum Merdeka), dengan penamaan model **generik** supaya K13/KTSP bisa menyusul tanpa migrasi besar (lihat Section 10). Asesmen Diagnostik dan Formatif ditunda — lihat Section 10.

```
komponen_penilaian                    (generik — representasi Tujuan Pembelajaran/TP;
                                        wadah utk KD kalau K13 ditambahkan nanti)
├── mata_pelajaran_id, semester_id
├── kode (opsional, "TP 3.1")
├── deskripsi ("Siswa mampu menjelaskan siklus air")
└── kktp (teks kriteria ketuntasan — bisa kualitatif)

asesmen                               (1 event penilaian sumatif)
├── kelas_id, mata_pelajaran_id, guru_id, semester_id
├── nama
├── jenis (Enum: sumatif_lingkup_materi, sumatif_akhir_semester, sumatif_akhir_jenjang)
└── tanggal

asesmen_komponen_penilaian            (pivot many-to-many — 1 asesmen bisa ukur beberapa TP)

nilai_siswa                           (hasil per siswa per asesmen per komponen)
├── siswa_id, asesmen_id, komponen_penilaian_id
├── nilai_angka (nullable)
├── predikat (nullable — "BSH"/"Sangat Berkembang")
└── catatan (nullable)
```

**3 sub-tipe `jenis` (semua sumatif):**
- **`sumatif_lingkup_materi`** — setelah satu bab/topik pembelajaran selesai (ulangan harian, per-TP).
- **`sumatif_akhir_semester`** — akhir semester ganjil/genap, dasar nilai rapor. Scoped ke `semester_id` yang sudah ada di tabel `asesmen`.
- **`sumatif_akhir_jenjang`** — akhir kelas/tahun ajaran, terkait pertimbangan kelulusan/kenaikan ke jenjang berikutnya. **Keputusan lulus/tidak tetap manual** oleh admin/dewan guru di wizard Kenaikan Kelas (Section 5) — hasil asesmen ini ditampilkan sebagai **rujukan/rekap** saat wizard berjalan, bukan pemicu otomatis, karena kelulusan di lapangan sering mempertimbangkan hal kualitatif (sikap, kehadiran) di luar angka semata.

Adaptasi per jenjang:
- **PAUD**: `mata_pelajaran_id` = salah satu dari 6 aspek STPPA, `nilai_angka` dikosongkan, cukup `predikat` + `catatan` naratif.
- **SD–SMA/SMK**: `nilai_angka` diisi per TP, diagregasi jadi nilai akhir mapel di rapor; `kktp` dari `komponen_penilaian` jadi acuan tuntas/belum tuntas.

**Rapor** (query, bukan tabel baru): kumpulkan `nilai_siswa` milik siswa X di semester Y dengan `asesmen.jenis IN (sumatif_lingkup_materi, sumatif_akhir_semester)`, dikelompokkan per mapel, digabung dengan deskripsi capaian per TP.

**Sengaja di luar scope tahap ini**: Asesmen Diagnostik (kognitif & non-kognitif), Asesmen Formatif, dan Projek Penguatan Profil Pelajar Pancasila (P5) — lihat Section 10.

---

## 7. Peran & Hak Akses (RBAC)

### 7.1 Matriks akses

| Peran | Akses |
|---|---|
| **Admin/Operator Sekolah** | Full CRUD: kalender, pola jam, jadwal, kelas, mapel, siswa (SPMB/import/manual), duplikasi tahun ajaran, komponen penilaian. Lihat semua rekap presensi & nilai lintas kelas. |
| **Guru Mata Pelajaran** | Hanya kelas/jam yang ada di `jadwal_pelajaran` miliknya sendiri (`guru_id` = akun login guru): isi sesi pembelajaran + jurnal + presensi, input nilai asesmen mapel yang diampu. |
| **Wali Kelas** (Guru dengan jabatan tambahan "Wali Kelas" — model `GuruJabatanTambahan` sudah ada) | Semua akses Guru Mapel + rekap presensi **lintas mapel** untuk kelas yang diampu (`kelas.wali_kelas_guru_id`), approve/edit izin-sakit siswa, isi catatan/nilai sikap, cetak rapor kelasnya. |

Peran ini **tidak hardcode** — admin bebas membuat Role apa saja lewat halaman Role yang sudah ada dan mencentang kombinasi permission sesuai kebutuhan sekolah masing-masing (lihat 7.2).

### 7.2 Dua lapis akses yang saling melengkapi

1. **RBAC (fitur apa yang boleh diakses)** — dikontrol lewat permission Spatie (`presensi.isi`, `presensi.lihat-rekap-kelas`, `asesmen.input-nilai`, `asesmen.kelola-komponen`, `kalender-akademik.kelola`, `jadwal-pelajaran.kelola`, `kelas.kelola`, `mata-pelajaran.kelola`, `siswa.kelola`). Admin sekolah menentukan sendiri kombinasi permission per Role lewat UI yang sudah ada — tidak perlu perubahan kode untuk membuat Role baru.
2. **Data scoping (baris data mana yang terlihat)** — dicek di level query/controller, bukan lewat permission. Guru dengan izin `presensi.isi` tetap hanya bisa mengisi presensi untuk kelas/jam yang ada di `jadwal_pelajaran` miliknya sendiri (`guru_id`); Wali Kelas hanya melihat rekap kelas yang menjadi tanggung jawabnya (`kelas.wali_kelas_guru_id`).

### 7.3 Perbaikan pendamping: `permissions:sync`

`PermissionSeeder.php` yang ada saat ini berisi daftar permission yang **diketik manual**, terpisah dari `$this->authorize('modul.aksi')` yang ditulis manual juga di tiap controller — dua tempat yang harus selalu disinkronkan tangan, rawan drift (lupa tambah, atau permission "hantu" yang sudah tidak dipakai kode manapun).

Perbaikan: Artisan command `permissions:sync` yang men-scan seluruh `app/Http/Controllers/**/*.php`, mencari pola `$this->authorize('modul.aksi')` yang benar-benar dipakai, lalu `Permission::firstOrCreate()` untuk tiap yang ditemukan. Permission yang ada di database tapi tidak lagi ditemukan di kode **dilaporkan**, tidak otomatis dihapus (supaya tidak diam-diam menghapus assignment Role yang sudah dikonfigurasi admin). `PermissionSeeder.php` dipensiunkan dan digantikan command ini — berlaku juga untuk modul SPMB/Keuangan yang sudah ada, tidak hanya modul baru ini. `RolePermissionSeeder.php` (mapping default Role → permission untuk instalasi baru) tetap terpisah, karena itu keputusan bisnis, bukan soal "permission apa saja yang exist".

Permission baru untuk modul ini otomatis terdaftar begitu ditulis di controller (`$this->authorize('presensi.isi')`, dst) — tidak perlu diketik ulang manual di seeder terpisah.

---

## 8. Alur Operasional Tahun Ajaran Baru

1. Aktifkan `TahunAjaran` & `Semester` baru.
2. Cek Kalender Akademik (libur nasional biasanya sudah diinput superadmin platform; cek libur khusus lembaga & hari libur mingguan).
3. Jalankan wizard **Duplikasi Awal Tahun Ajaran** (Section 5) — termasuk Kenaikan Kelas.
4. Daftarkan siswa baru dari SPMB (Section 1.1) ke kelas hasil langkah 3, atau import/input manual siswa lama yang belum tercatat.
5. Pastikan wali kelas ter-assign ke tiap kelas baru.
6. Cek Mata Pelajaran/Unit Asesmen (biasanya reusable dari duplikasi).
7. Cek/revisi Pola Jam & Jam Pelajaran, assign ke kelas-kelas baru.
8. Input/lengkapi Jadwal Pelajaran per kelas per semester.
9. Operasional harian dimulai: `Sesi Pembelajaran` mengikuti kombinasi Jadwal + Kalender (hari libur otomatis tidak memicu sesi), guru isi presensi & jurnal, asesmen berjalan sepanjang semester.

---

## 9. Pertimbangan Lintas Jenjang (Ringkasan)

| Aspek | PAUD (KB/TPA/SPS/TK) | SD–SMA | SMK | SLB |
|---|---|---|---|---|
| Kelas | `tingkat` kosong, nama = kelompok usia | `tingkat` numerik/romawi | sama seperti SMA + kejuruan | sesuai kebutuhan, `tingkat` fleksibel |
| Mata Pelajaran | `tipe = aspek_perkembangan` (6 aspek STPPA) | `tipe = mapel` | `tipe = mapel` + mapel kejuruan | `tipe = mapel`, disederhanakan |
| Presensi | mode harian | mode per jam pelajaran | + mode PKL (harian, di luar sekolah) | mengikuti pola SD/SMP terdekat |
| Asesmen | `nilai_angka` kosong, `predikat`+`catatan` naratif | `nilai_angka` per TP | sama seperti SD–SMA | disederhanakan; PPI penuh di luar scope (Section 10) |

---

## 10. Out of Scope / Next Steps

Ditunda secara sengaja supaya versi dasar bisa segera dipakai lembaga nyata:

- **Asesmen Diagnostik** (kognitif & non-kognitif) — asesmen kesiapan siswa sebelum pembelajaran dimulai. Diagnostik Non-Kognitif khususnya butuh perlakuan struktural berbeda (tidak terikat mata pelajaran — soal gaya belajar, kondisi psikologis/keluarga — sementara `asesmen.mata_pelajaran_id` di desain ini tetap wajib diisi karena scope v1 murni Sumatif).
- **Asesmen Formatif** — pemantauan berkala selama proses pembelajaran (kuis singkat, diskusi, refleksi) untuk umpan balik, bukan nilai akhir.
- **Projek Penguatan Profil Pelajar Pancasila (P5)** — struktur penilaian per Dimensi P5, lintas mapel/kolaboratif, berbeda dari asesmen mapel reguler.
- **Dukungan penuh K13/KTSP** — `komponen_penilaian` sudah dinamai generik supaya siap menampung KD, tapi alur/agregasi nilai KD belum dirancang detail.
- **Portal Siswa & Wali Murid** (read-only lihat presensi/nilai) — butuh guard/auth baru yang belum ada (`User` untuk staf, `AkunPendaftar` khusus PPDB — belum ada guard untuk Murid/Wali Murid). PRD `PRD_Sistem_Administrasi_Yayasan.md` sudah mengantisipasi role ini ("Portal siswa (fase akademik menyusul)") sebagai fase terpisah.
- **PPI (Program Pembelajaran Individual) penuh untuk SLB** — asesmen SLB di tahap ini disederhanakan mengikuti pola jenjang terdekat, belum menangani kebutuhan individual per Peserta Didik Berkebutuhan Khusus secara mendalam.
