# Spec: Sub-Task 04d — Adaptive E-Rapor Engine: 4 Template PDF Berjenjang

- **Document ID / Slug:** `2026-08-19-1900-akademik-04d-rapor-pdf`
- **Bagian dari Master Plan:** [`.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md) (FASE 4d — sub-task terakhir dari rangkaian Adaptive E-Rapor Engine)
- **Tanggal:** 19 Agustus 2026
- **Bergantung pada:** Sub-Task 04a (`RaporCalculationService`, `KomponenPenilaian` domain), 04b (`PengajuanRapor`, `CatatanWaliKelas`, `CapaianKompetensiGenerator`, approval workflow), 04c (`Guru\RaporController`, `Lembaga\Rapor\PersetujuanController`) — semua **SELESAI**, direview independen, tanpa temuan terbuka.

---

## 1. Tujuan

Mencetak rapor resmi per-siswa dalam 4 layout berbeda sesuai jenjang pendidikan (`Lembaga::bentuk_pendidikan`), menggunakan DomPDF (sudah dipakai project ini — lihat `Admin\RaporController::cetak()` untuk rekap matriks nilai sederhana yang SUDAH ADA dan TIDAK diubah oleh sub-task ini). Ini adalah dokumen "resmi" per-siswa (identitas + nilai + narasi capaian + catatan wali kelas + absensi + tanda tangan) — berbeda dari `admin.rapor.cetak` yang ada sekarang (rekap matriks nilai satu kelas, tanpa identitas detail/tanda tangan/catatan wali kelas).

**Di luar scope 04d** (per keputusan brainstorming): nilai numerik PKL Industri dan portofolio UKK untuk SMK — keduanya bukan data yang pernah diminta/dikumpulkan sistem manapun sampai saat ini; menambahkannya butuh modul PKL/UKK tersendiri. Template SMK 04d hanya menampilkan `pkl_info` (perusahaan/posisi/durasi) yang sudah ada dari 04c, sebagai "Keterangan PKL", tanpa kolom nilai.

---

## 2. Keputusan Desain Kunci (dari brainstorming)

1. **PDF bisa dicetak kapan saja sebagai draft/pratinjau** — bukan hanya setelah `PengajuanRapor.status === Disetujui`. Watermark diagonal "DRAFT" (CSS murni, tanpa gambar/font eksternal) muncul otomatis pada PDF selama status BUKAN `Disetujui`; watermark hilang otomatis begitu status `Disetujui`. Ini berbeda dari nilai (`NilaiSiswa`) yang tetap terkunci sesuai aturan 04b — mencetak draft PDF tidak mengubah kuncian nilai apa pun, murni tampilan dokumen.
2. **Akses cetak**: Wali Kelas (permission `rapor.input-wali`, guard kepemilikan kelas sama seperti method lain di `Guru\RaporController`), Waka Kurikulum (`rapor.verify`), dan Kepala Sekolah (`rapor.approve`) — reuse permission yang sudah ada dari 04b, TIDAK ada permission baru. Waka/Kepsek boleh cetak draft kapan saja selama meninjau (tidak digerbang oleh step-matching seperti `show()`/`decision()` — mencetak draft bukan aksi approval, jadi tidak perlu menunggu giliran step).
3. **Kategori Capaian Pembelajaran (CP) PAUD**: kolom baru `elemen_cp` (nullable) pada `komponen_penilaian`, enum 3 nilai (`nilai_agama_moral`, `jati_diri`, `literasi_steam`) sesuai istilah resmi Kurikulum Merdeka PAUD. Diisi guru lewat form Komponen Penilaian yang sudah ada (04a), field baru ditampilkan HANYA untuk kelas berjenjang PAUD (whitelist sama seperti field kondisional lain: `KB`, `TPA`, `SPS`, `TK`). Perluasan ini backward-compatible mengikuti pola `kktp_minimal` (04b) persis — DTO, Action, 4 FormRequest diperluas, bukan dibuat ulang.
4. **PKL/UKK SMK**: lihat §1 "Di luar scope".
5. **Tanda tangan (3 kolom)**:
   - **Wali Kelas**: nama diambil dari `PengajuanRapor.diverifikasi_oleh` (User yang benar-benar menekan Verifikasi) — BUKAN dari `Kelas.wali_kelas_guru_id`. Kosong/placeholder `(Menunggu Verifikasi)` kalau `diverifikasi_oleh` masih null.
   - **Kepala Sekolah**: nama diambil dari `PengajuanRapor.disetujui_oleh` — kosong/placeholder `(Menunggu Persetujuan)` kalau masih null.
   - **Orang Tua/Wali**: nama diambil dari `Siswa->orangTua()->wherePivot('is_kontak_utama', true)->first()->nama_lengkap` — baris kosong tanpa nama kalau tidak ada data kontak utama tercatat.
   - Ketiganya diambil dari nama AKTOR SISTEM (audit-accurate), BUKAN dari data profil registrasi (`Lembaga.nama_kepala_sekolah` atau `Kelas.wali_kelas_guru_id`) — keputusan ini sengaja berbeda dari intuisi "selalu terisi", karena tujuannya mencerminkan siapa yang BENAR-BENAR melakukan verifikasi/approval di sistem untuk draft yang sedang dilihat.
6. **Perbedaan Rapor Semester Ganjil vs Genap** (berlaku untuk template `sd`/`smp-sma`/`smk` — TIDAK berlaku untuk `paud` yang memang tidak punya section nilai/kenaikan-kelas sama sekali sesuai desain §6, KECUALI judul dokumen yang tetap berbeda di semua 4 template). Deteksi Ganjil/Genap: `$semester->urutan === 2` → Genap (konvensi seeder: `urutan=1` selalu "Ganjil", `urutan=2` selalu "Genap", satu `TahunAjaran` selalu punya tepat 2 `Semester`).
   - **Judul dokumen**: header PDF menampilkan "Laporan Hasil Belajar Semester {Ganjil|Genap}" + nama `TahunAjaran` — berlaku di SEMUA 4 template termasuk PAUD.
   - **Keterangan Kenaikan Kelas/Kelulusan**: section ini (dari `catatan->keterangan_kenaikan`) HANYA muncul di rapor Genap — di rapor Ganjil section ini disembunyikan sepenuhnya (`@if($isGenap)`). Labelnya sendiri berubah jadi "Keterangan Kelulusan" (bukan "Keterangan Kenaikan Kelas") kalau `$kelas->tingkat` adalah tingkat akhir jenjangnya: SD tingkat `'6'`, SMP tingkat `'9'`, SMA/SMK tingkat `'12'` (lihat §4 `isTingkatAkhir`).
   - **Akumulasi absensi tahunan**: rapor Genap menambahkan baris/tabel kedua "Akumulasi Semester Ganjil + Genap" (total hadir/izin/sakit/alpa/terlambat gabungan dari kedua semester dalam `TahunAjaran` yang sama) DI SAMPING data absensi semester berjalan yang sudah selalu tampil — rapor Ganjil hanya menampilkan data semester berjalan, tanpa baris akumulasi.
   - **Nilai rata-rata tahunan**: rapor Genap menambahkan satu kolom tambahan "Rata-Rata Tahunan" di tabel nilai (rata-rata numerik dari nilai Semester Ganjil + Semester Genap per mapel yang sama) — rapor Ganjil tidak punya kolom ini.

---

## 3. Perluasan `elemen_cp` (mengikuti pola `kktp_minimal` 04b persis)

Referensi cepat, semua ini **backward-compatible extension** dari kode yang sudah ada — bukan file/behavior baru bagi konsumen yang tidak mengirim `elemen_cp`:

```php
// Migrasi baru (setelah komponen_penilaian.kktp_minimal)
Schema::table('komponen_penilaian', function (Blueprint $table) {
    $table->string('elemen_cp', 30)->nullable()->after('kktp_minimal');
});

// app/Domains/Akademik/Enums/ElemenCapaianPembelajaran.php (BARU)
enum ElemenCapaianPembelajaran: string {
    case NilaiAgamaMoral = 'nilai_agama_moral';
    case JatiDiri = 'jati_diri';
    case LiterasiSteam = 'literasi_steam';

    public function label(): string {
        return match ($this) {
            self::NilaiAgamaMoral => 'Nilai Agama dan Budi Pekerti',
            self::JatiDiri => 'Jati Diri',
            self::LiterasiSteam => 'Literasi, STEAM, Seni, dan Budaya',
        };
    }
}
```

- `KomponenPenilaian.$fillable` += `elemen_cp`, cast `elemen_cp` → `ElemenCapaianPembelajaran::class` (nullable enum cast — Laravel native, sama seperti cast `status`/`jenis` enum lain di codebase ini).
- `KomponenPenilaianData`/`UpdateKomponenPenilaianData` DTO += `?ElemenCapaianPembelajaran $elemenCp` (parameter TERAKHIR, seperti `kktpMinimal` ditambahkan terakhir di 04b — urutan constructor param existing TIDAK berubah).
- `CreateKomponenPenilaianAction`/`UpdateKomponenPenilaianAction` menyimpan `elemen_cp` di create/save.
- 4 FormRequest (`Store/UpdateKomponenPenilaianRequest`, `Store/UpdateKomponenPenilaianSendiriRequest`) += rule `'elemen_cp' => ['nullable', Rule::enum(ElemenCapaianPembelajaran::class)]`.
- Form create/edit Komponen Penilaian (Admin & Guru) += `<select name="elemen_cp">` 3 opsi, ditampilkan lewat `@if` kondisional jenjang PAUD (whitelist sama seperti field kondisional 04c: cek `bentuk_pendidikan` dari mata pelajaran → lembaga; TIDAK perlu enum/abstraksi baru untuk whitelist ini, duplikasi literal `in_array` sederhana per keputusan YAGNI 04c).

---

## 4. `RaporPdfDataBuilder` — Service Baru

```php
// app/Domains/Akademik/Services/RaporPdfDataBuilder.php
final class RaporPdfDataBuilder
{
    public function __construct(
        private readonly RaporCalculationService $raporCalculationService,
        private readonly CapaianKompetensiGenerator $capaianKompetensiGenerator,
        private readonly PresensiAggregationService $presensiAggregationService,
    ) {}

    /**
     * @return array{
     *   siswa: Siswa, kelas: Kelas, semester: Semester, lembaga: Lembaga,
     *   rekapNilai: array<int, float|null> keyed by mata_pelajaran_id,
     *   mapelList: Collection<MataPelajaran>,
     *   narasiPerMapel: array<int, array{tertinggi: ?string, terendah: ?string}> keyed by mata_pelajaran_id,
     *   catatan: ?CatatanWaliKelas,
     *   absensi: array{hadir:int, izin:int, sakit:int, alpa:int, terlambat:int},
     *   pengajuanRapor: ?PengajuanRapor,
     *   isDraft: bool,
     *   namaWaliKelas: ?string, namaKepalaSekolah: ?string, namaOrangTua: ?string,
     *   isGenap: bool, isTingkatAkhir: bool, labelKenaikan: string, judulDokumen: string,
     *   absensiTahunan: ?array{hadir:int, izin:int, sakit:int, alpa:int, terlambat:int},
     *   nilaiRataRataTahunan: ?array<int, float|null> keyed by mata_pelajaran_id,
     * }
     */
    public function build(Siswa $siswa, Semester $semester): array;

    /** Whitelist sama seperti field kondisional 04c — literal duplikasi disengaja (YAGNI). */
    public function templateUntukJenjang(string $bentukPendidikan): string; // 'pdf.rapor.paud'|'sd'|'smp-sma'|'smk'
}
```

Detail method `build()`:
- `rekapNilai`/`mapelList` dari `RaporCalculationService::hitungRekapKelas($kelas, $semester)`, DIAMBIL HANYA baris milik `$siswa` (bukan semua siswa kelas — service existing mengembalikan array untuk seluruh kelas, builder ini memfilter ke satu siswa).
- `narasiPerMapel`: loop `mapelList`, panggil `CapaianKompetensiGenerator::generateNarasi($siswa, $mapel, $semester)` per mapel (BUKAN digabung jadi satu paragraf seperti `GenerateNarasiPerkembanganAction` di 04c — PDF butuh narasi TERPISAH per mapel, ditampilkan di baris masing-masing pada tabel nilai).
- Untuk jenjang PAUD: tambahan pengelompokan `narasiPerMapel` berdasarkan `KomponenPenilaian.elemen_cp` milik TP-TP yang berkontribusi ke narasi tertinggi/terendah tiap mapel — dikelompokkan ulang jadi 3 keranjang (`nilai_agama_moral`/`jati_diri`/`literasi_steam`) di dalam template `paud.blade.php` sendiri (bukan di builder — builder tetap mengembalikan struktur per-mapel yang sama untuk semua jenjang; pengelompokan 3-elemen adalah presentasi murni khusus template PAUD, dilakukan dengan `$mapelList->groupBy(...)` di dalam blade menggunakan `KomponenPenilaian::where('mata_pelajaran_id', $mapel->id)->whereNotNull('elemen_cp')->first()?->elemen_cp` per mapel).
- `absensi`: `PresensiAggregationService::agregasiPerKelas($kelas->id, $semester)`, lalu `->firstWhere('siswa_id', $siswa->id)` — service existing TIDAK diubah, builder ini hanya memfilter hasilnya ke satu siswa.
- `pengajuanRapor`: `PengajuanRapor::where('kelas_id', $kelas->id)->where('semester_id', $semester->id)->first()` — bisa `null` kalau belum pernah diajukan sama sekali (kasus ini tetap harus menghasilkan PDF draft yang valid, bukan error).
- `isDraft`: `true` kecuali `$pengajuanRapor?->status === StatusPengajuanRapor::Disetujui`.
- `namaWaliKelas`/`namaKepalaSekolah`: lookup `User::find($pengajuanRapor?->diverifikasi_oleh)?->guru?->nama` dan `User::find($pengajuanRapor?->disetujui_oleh)?->guru?->nama` — `null` kalau kolom itu sendiri `null` ATAU relasi guru tidak ditemukan.
- `namaOrangTua`: `$siswa->orangTua()->wherePivot('is_kontak_utama', true)->first()?->nama_lengkap`.
- `isGenap`: `$semester->urutan === 2`.
- `isTingkatAkhir`: `false` kecuali kombinasi `(bentuk_pendidikan, tingkat)` cocok salah satu: `('SD', '6')`, `('SLB', '6')`, `('SMP', '9')`, `('SMA', '12')`, `('SMK', '12')` — literal array pemetaan di dalam builder, bukan enum/abstraksi baru (YAGNI, sama seperti whitelist jenjang lain).
- `labelKenaikan`: `$isGenap && $isTingkatAkhir ? 'Keterangan Kelulusan' : 'Keterangan Kenaikan Kelas'` (nilainya tidak relevan/tidak dipakai template saat `!$isGenap`, tapi tetap dihitung untuk kesederhanaan — bukan `null`).
- `judulDokumen`: `"Laporan Hasil Belajar Semester {$semester->nama} — {$kelas->tahunAjaran->nama}"` (dipakai di SEMUA 4 template, termasuk PAUD — lihat §2.6).
- `absensiTahunan`: `null` kecuali `$isGenap`. Kalau `$isGenap`: cari `$semesterGanjil = Semester::where('tahun_ajaran_id', $semester->tahun_ajaran_id)->where('urutan', 1)->first()`; kalau ditemukan, jumlahkan hasil `PresensiAggregationService::agregasiPerKelas($kelas->id, $semesterGanjil)` (baris milik `$siswa` saja) dengan `absensi` (semester Genap berjalan) per kategori (`hadir`+`hadir`, dst); kalau `$semesterGanjil` tidak ditemukan (mis. data lama/tidak lengkap), `absensiTahunan` tetap `null` (JANGAN menghitung dari satu semester saja lalu menyebutnya "tahunan" — itu menyesatkan).
- `nilaiRataRataTahunan`: `null` kecuali `$isGenap` DAN `$semesterGanjil` (sama seperti di atas) ditemukan. Kalau keduanya terpenuhi: panggil `RaporCalculationService::hitungRekapKelas($kelas, $semesterGanjil)` untuk ambil nilai siswa di semester Ganjil, lalu untuk tiap `mata_pelajaran_id` di `mapelList`, rata-ratakan nilai Ganjil+Genap — **kalau salah satu semester `null` untuk mapel itu (siswa belum pernah dinilai di semester itu), pakai nilai yang ADA saja** (bukan dianggap 0), dan `null` HANYA kalau KEDUA semester `null` untuk mapel itu. `$semesterGanjil` tidak ditemukan → `nilaiRataRataTahunan` tetap `null` seluruhnya (sama seperti `absensiTahunan`, JANGAN silent-fallback ke data tidak lengkap).

---

## 5. Controller & Route

**Tidak ada controller baru.** Method `cetak()` ditambahkan ke:

- `Guru\RaporController::cetak(Siswa $siswa, Request $request): Response` — permission `rapor.input-wali`, guard kepemilikan `$siswa->kelas->wali_kelas_guru_id === $guru->id` (identik pola `edit()`/`update()`/`generateNarasi()` yang sudah ada). `semester_id` dari query string (pola sama seperti `edit()`). Route: `GET guru/rapor/cetak/{siswa}` → `guru.rapor.cetak`.
- `Lembaga\Rapor\PersetujuanController::cetak(PengajuanRapor $pengajuanRapor, Siswa $siswa): Response` — permission `canAny(['rapor.verify', 'rapor.approve'])` (SAMA seperti `index()`/`show()`), TANPA guard step-matching (lihat §2.2 — beda dari `show()`/`decision()` yang mewajibkan step aktor cocok). Guard tambahan: `abort_unless($siswa->kelas_id === $pengajuanRapor->kelas_id, 404)` — siswa yang diminta harus benar-benar anggota kelas pengajuan itu. Route: `GET admin/rapor/persetujuan/{pengajuanRapor}/cetak/{siswa}` → `admin.rapor.persetujuan.cetak`.

Kedua method memanggil `RaporPdfDataBuilder::build()` lalu `RaporPdfDataBuilder::templateUntukJenjang()`, lalu `Pdf::loadView($template, $data)->stream(...)` — pola identik `Admin\RaporController::cetak()` yang sudah ada (facade `Barryvdh\DomPDF\Facade\Pdf`, method `stream()` bukan `download()`, supaya PDF terbuka di tab baru bukan langsung terunduh).

Tombol "Cetak PDF Rapor" ditambahkan:
- Per baris siswa di `resources/views/portals/guru/rapor/catatan/index.blade.php` (sudah ada dari 04c) — link ke `guru.rapor.cetak`, `target="_blank"`.
- Per baris siswa di `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php` (sudah ada dari 04c, bagian "Catatan Wali Kelas Per Siswa") — link ke `admin.rapor.persetujuan.cetak`, `target="_blank"`.

---

## 6. 4 Template PDF

Direktori `resources/views/pdf/rapor/`, 2 partial dibagi ke-4 template untuk hindari duplikasi:

- `_identitas.blade.php`: header identitas siswa (nama, NIS/NISN, kelas, semester) + identitas lembaga (nama, NPSN, alamat — dari `Lembaga`).
- `_tanda-tangan.blade.php`: 3 blok tanda tangan (Wali Kelas / Orang Tua-Wali / Kepala Sekolah) memakai `namaWaliKelas`/`namaOrangTua`/`namaKepalaSekolah` dari builder, dengan placeholder teks kalau `null` (lihat §2.5).
- Watermark "DRAFT": CSS `position: fixed; transform: rotate(-30deg); opacity: 0.15;` teks besar di tengah halaman, dirender lewat `@if($isDraft)` di SETIAP dari ke-4 template (bukan di partial, karena penempatan CSS `position:fixed` DomPDF perlu berada di root tiap halaman — duplikasi 4x blok watermark identik diterima, bukan pelanggaran DRY yang signifikan mengingat ukurannya sangat kecil, ~5 baris).
- `_identitas.blade.php` menampilkan `$judulDokumen` sebagai judul utama dokumen — SATU-SATUNYA bagian dari §2.6 yang berlaku ke PAUD juga (lihat §2.6).

**`paud.blade.php`** (KB/TPA/SPS/TK): identitas (pakai `$judulDokumen`) + narasi kualitatif dikelompokkan 3 elemen CP (§4) + rekap pertumbuhan fisik dari `catatan->tinggi_badan_cm`/`berat_badan_kg`/`lingkar_kepala_cm` + `catatan->catatan_sikap`/`catatan_perkembangan` + tanda tangan. TIDAK ada tabel nilai angka (PAUD tidak punya nilai numerik per Kurikulum Merdeka — murni naratif) dan TIDAK ada section Keterangan Kenaikan Kelas/Kelulusan atau akumulasi absensi tahunan (§2.6 tidak berlaku untuk PAUD di luar judul dokumen).

**`sd.blade.php`** (SD/SLB): identitas (`$judulDokumen`) + tabel nilai angka per mapel (`rekapNilai`, TAMBAH kolom "Rata-Rata Tahunan" dari `nilaiRataRataTahunan` kalau `$isGenap && $nilaiRataRataTahunan !== null`) + narasi capaian TP tertinggi/terendah per mapel (`narasiPerMapel`) + `catatan->ekstrakurikuler` (tabel) + absensi semester berjalan (`absensi`, SELALU tampil) + tabel akumulasi tahunan tambahan dari `absensiTahunan` (HANYA kalau `$isGenap && $absensiTahunan !== null`) + section `{{ $labelKenaikan }}` berisi `catatan->keterangan_kenaikan` (HANYA kalau `$isGenap`, disembunyikan total kalau tidak) + `catatan->catatan_sikap` + tanda tangan.

**`smp-sma.blade.php`** (SMP/SMA): sama seperti `sd.blade.php` (termasuk seluruh logic §2.6 Ganjil/Genap di atas), TAMBAH pengelompokan mapel berdasarkan `KomponenPenilaian` → `MataPelajaran.kelompok` (enum `KelompokMataPelajaran` yang sudah ada: Umum/Pilihan/Mulok/dst) sebagai sub-header tabel nilai, untuk merepresentasikan struktur Fase F SMA (mapel umum vs pilihan). Tidak perlu data/kolom baru — `KelompokMataPelajaran` sudah ada di `MataPelajaran` sejak sebelum sub-task ini.

**`smk.blade.php`**: sama seperti `smp-sma.blade.php` (pengelompokan by `kelompok` termasuk case `Kejuruan` sebagai sub-header terpisah "Mata Pelajaran Kejuruan/Produktif", DAN seluruh logic §2.6 Ganjil/Genap) + `catatan->pkl_info` ditampilkan sebagai tabel "Keterangan PKL" (perusahaan/posisi/durasi, TANPA kolom nilai — lihat §1 di luar scope).

---

## 7. Testing

- **`RaporPdfDataBuilderTest`** (Feature, `tests/Feature/Akademik/`): `build()` mengembalikan struktur lengkap untuk siswa dengan nilai+catatan+approval lengkap; `isDraft` `true` saat `PengajuanRapor` belum `Disetujui` dan `false` saat sudah; `namaWaliKelas`/`namaKepalaSekolah` `null` saat kolom `diverifikasi_oleh`/`disetujui_oleh` masih `null`; `namaOrangTua` mengambil kontak `is_kontak_utama = true` bukan kontak lain kalau siswa punya &gt;1 orang tua tercatat; `build()` TIDAK error saat `PengajuanRapor` belum pernah dibuat sama sekali (kelas belum pernah diajukan). `templateUntukJenjang()` — 4 pemetaan eksplisit (`KB`/`TPA`/`SPS`/`TK` → `pdf.rapor.paud`; `SMK` → `pdf.rapor.smk`; `SMP`/`SMA` → `pdf.rapor.smp-sma`; SEMUA nilai lain termasuk `SD`/`SLB`/nilai tak dikenal di masa depan → `pdf.rapor.sd` sebagai default, karena SD adalah template paling sederhana/umum di antara ke-4 — filosofi sama seperti `ModePembelajaran::fromBentukPendidikan()` di 03b: default ke yang lebih sederhana untuk nilai enum baru yang belum dikenal kode).
- **Skenario Ganjil/Genap** (tambahan pada `RaporPdfDataBuilderTest`): `isGenap` `false` untuk `Semester` `urutan=1`, `true` untuk `urutan=2`; `absensiTahunan`/`nilaiRataRataTahunan` `null` di semester Ganjil; `absensiTahunan` benar menjumlahkan kedua semester saat Genap DAN `Semester` Ganjil pasangannya ada; `absensiTahunan`/`nilaiRataRataTahunan` tetap `null` (BUKAN dihitung dari satu semester) saat Genap tapi `Semester` Ganjil pasangannya tidak ditemukan di `TahunAjaran` yang sama; `nilaiRataRataTahunan` untuk satu mapel memakai nilai yang ada saja saat salah satu semester `null` (bukan `null` total, bukan dianggap 0); `labelKenaikan` jadi `'Keterangan Kelulusan'` untuk kombinasi `(bentuk_pendidikan, tingkat)` di whitelist tingkat-akhir, `'Keterangan Kenaikan Kelas'` untuk kombinasi lain (termasuk tingkat non-akhir di jenjang yang sama, buktikan bukan sekadar `$isGenap` saja yang menentukan).
- **`Guru\RaporController::cetak()`** (extend `tests/Feature/Guru/RaporControllerTest.php`): 200 + content-type PDF untuk wali kelas kelasnya sendiri; 403 untuk kelas bukan miliknya (pola sama seperti `edit()`).
- **`Lembaga\Rapor\PersetujuanController::cetak()`** (extend `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`): 200 untuk Waka DAN Kepsek (buktikan TIDAK digerbang step-matching, berbeda dari `show()`); 404 kalau `$siswa` bukan anggota `$pengajuanRapor->kelas`; cross-tenant 404 via route-model-binding.
- **Perluasan `elemen_cp`** (extend `tests/Feature/Admin/KomponenPenilaianCrudTest.php` dan `tests/Feature/Guru/KomponenPenilaianControllerTest.php`): field tersimpan saat dikirim, `null` saat tidak dikirim, TIDAK ada perubahan assertion pada test yang sudah ada (backward-compatible, sama seperti pembuktian `kktp_minimal` di 04b).
- Test PDF TIDAK perlu membuka/mem-parsing isi PDF biner — cukup assert response `200`/content-type `application/pdf` dan (untuk `RaporPdfDataBuilderTest`) menguji data array sebelum di-render, mengikuti pola `Admin\RaporController::cetak()`'s test existing (kalau ada — implementer WAJIB cek `tests/Feature/Admin/RaporControllerTest.php` dulu untuk pola assert PDF yang sudah divalidasi berjalan di codebase ini, jangan menebak API DomPDF testing dari nol).

---

## 8. Asumsi & Keputusan Desain

1. `RaporPdfDataBuilder` TIDAK melakukan pengecekan otorisasi apa pun (permission/kepemilikan) — itu tanggung jawab controller pemanggil, sama seperti `RaporCalculationService` (04a) yang juga pure data service tanpa otorisasi built-in.
2. PDF selalu di-generate on-the-fly per-request (tidak di-cache/disimpan sebagai file) — konsisten dengan `Admin\RaporController::cetak()` existing yang juga `Pdf::loadView(...)->stream()` langsung tanpa penyimpanan.
3. Tidak ada mode "cetak semua siswa sekaligus" (batch/zip) di 04d — tiap PDF dicetak satu-siswa-satu-request, sesuai sifat dokumen resmi per-siswa (beda dari `admin.rapor.cetak` yang memang matriks satu-kelas). Kalau kebutuhan batch print muncul nanti, itu sub-task terpisah.
4. `smp-sma.blade.php` dan `smk.blade.php` sengaja berbagi struktur pengelompokan-by-`kelompok` yang sama (bukan berarti keduanya harus jadi satu file — tetap 2 file terpisah persis seperti diminta master spec, hanya strukturnya kebetulan mirip karena keduanya sama-sama pakai `KelompokMataPelajaran`).
