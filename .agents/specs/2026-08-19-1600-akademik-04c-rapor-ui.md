# Spec: Sub-Task 04c — Adaptive E-Rapor Engine: UI 4 Role

- **Document ID / Slug:** `2026-08-19-1600-akademik-04c-rapor-ui`
- **Bagian dari Master Plan:** [`.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md) (FASE 4c)
- **Tanggal:** 19 Agustus 2026
- **Bergantung pada:** Sub-Task 04a (migrasi Komponen Penilaian/Asesmen/Nilai Siswa/RaporCalculationService ke domain Akademik) dan Sub-Task 04b (backend headless: `PengajuanRapor`, `CatatanWaliKelas`, `CapaianKompetensiGenerator`, Action approval reuse Universal Workflow Engine) — keduanya **SELESAI** dan sudah direview.

---

## 1. Tujuan

Sub-Task 04b membangun seluruh logic bisnis (Action, Service, Model) untuk alur pengajuan-verifikasi-persetujuan rapor semester, tapi 100% headless — tidak ada satu pun controller/route/view. Sub-Task 04c membangun **antarmuka web** di atas fondasi itu untuk 3 role:

1. **Guru/Wali Kelas** — mengisi `CatatanWaliKelas` untuk tiap siswa di kelasnya, lalu mengajukan rapor kelasnya untuk direview.
2. **Waka Kurikulum** (role `admin_akademik`) — memverifikasi pengajuan rapor (step 1 workflow `RAPOR_SEMESTER`).
3. **Kepala Sekolah** (role `kepala_sekolah`) — menyetujui final pengajuan rapor (step 2, final).

**Di luar scope 04c** (per keputusan brainstorming): halaman monitoring khusus Admin Lembaga — cukup memakai halaman `admin.rapor.index` (Rekap Rapor) yang sudah ada dari 04a. Template cetak PDF rapor per-jenjang adalah Sub-Task 04d terpisah — 04c tidak membuat halaman cetak/PDF apa pun.

---

## 2. Precedent Wajib Diikuti

Semua keputusan struktural di bawah ini didasarkan pada kode nyata yang sudah ada di codebase (bukan asumsi), yang **wajib dijadikan acuan literal** oleh implementer:

- **Namespace controller** mengikuti pola *terbaru* (Sub-Task 04a/Pengadaan/Sarpras), bukan namespace `Admin\` yang lama:
  - Sisi wali kelas → `App\Http\Controllers\Guru\RaporController` (flat, sejajar dengan `Guru\AsesmenController`/`Guru\KomponenPenilaianController` dari 04a — **bukan** nested `Guru\Akademik\...`).
  - Sisi Waka & Kepsek → `App\Http\Controllers\Lembaga\Rapor\PersetujuanController` (sejajar `Lembaga\Pengadaan\...`/`Lembaga\Sarpras\...` — dipakai karena kedua step approval Rapor scope-nya lembaga, bukan yayasan, jadi **tidak** memakai namespace `Yayasan\...` seperti approver Pengadaan).
- **Route name** tetap di bawah prefix `admin.`/`guru.` yang sudah ada di `routes/admin.php` (nama route tidak mengikuti namespace PHP — `Lembaga\Sarpras\GedungController` sendiri di-route dengan nama `admin.sarpras.gedung.*`).
- **Pola list + AJAX partial**: `resources/views/portals/yayasan/pengadaan/inbox/index.blade.php` + `_daftar.blade.php`, memakai `x-data="dataTableFilter({...})"` yang membungkus filter dan `#wadah-daftar-tabel` yang di-`@include` partial saat render awal, lalu di-swap AJAX saat filter berubah. Controller mengecek `$request->ajax()` dan me-return HANYA view partial saat itu.
- **Pola halaman keputusan** (bukan modal, bukan inline): halaman terpisah (`review.blade.php`-setara) — form POST biasa (bukan AJAX) dengan `x-data` Alpine untuk state UI saja, redirect+flash `session('success')` setelah submit. Contoh acuan: `resources/views/portals/yayasan/pengadaan/inbox/review.blade.php`.
- **Pola guru mencari kelas walinya sendiri**: `Kelas::where('wali_kelas_guru_id', $guru->id)` — identik dengan `app/Http/Controllers/Guru/Akademik/RekapKehadiranController::index()` (Sub-Task 03c), termasuk pola selector Tahun Ajaran → Semester → Kelas dengan default ke yang aktif.
- **Pola form array repeatable** (untuk field `ekstrakurikuler`/`prestasi`/`pkl_info` yang di-cast `array` pada `CatatanWaliKelas`): `resources/views/portals/lembaga/pengadaan/proposal/create.blade.php` — Alpine `items` array + `<template x-for="(item, index) in items" :key="index">`, input `:name="`items[${index}][field]`"` + `x-model="item.field"`, `tambahItem()`/`hapusItem(index)` (pakai `confirmDialog()` global sebelum hapus, dan `x-show="items.length > 1"` untuk selalu sisakan minimal 1 baris). **Dipakai identik**, tinggal ganti nama field.
- **Sidebar**: satu file `resources/views/layouts/sidebar.blade.php`, array `$navGroups` di `@php` blok atas file. Grup "Ruang Guru" (baris ~11) dan "Akademik" (baris ~20) sudah ada — 04c HANYA menambah entri baru ke array `items` masing-masing grup yang sudah ada, TIDAK membuat grup baru.

---

## 3. Fondasi Backend yang Sudah Tersedia (tidak diubah oleh 04c, kecuali item 3.6)

Referensi cepat interface yang dipakai controller 04c (semua sudah ada & sudah lulus test dari 04b):

```php
// app/Domains/Akademik/Models/PengajuanRapor.php
class PengajuanRapor {
    // fillable: lembaga_id, kelas_id, semester_id, status (StatusPengajuanRapor),
    //   diajukan_oleh, diajukan_pada, diverifikasi_oleh, diverifikasi_pada,
    //   disetujui_oleh, disetujui_pada, catatan_revisi, tanggal_rapor
    public function kelas(): BelongsTo;
    public function semester(): BelongsTo;
    public function approvalRequest(): MorphOne; // -> App\Domains\Workflow\Models\ApprovalRequest
}

// app/Domains/Akademik/Enums/StatusPengajuanRapor.php
enum StatusPengajuanRapor: string {
    case Draft = 'draft'; case Diajukan = 'diajukan'; case Diverifikasi = 'diverifikasi';
    case Disetujui = 'disetujui'; case Ditolak = 'ditolak';
}

// app/Domains/Akademik/Models/CatatanWaliKelas.php
class CatatanWaliKelas {
    // fillable: lembaga_id, siswa_id, semester_id, catatan_sikap, catatan_perkembangan,
    //   tinggi_badan_cm, berat_badan_kg, lingkar_kepala_cm (decimal:1),
    //   ekstrakurikuler, prestasi, pkl_info (cast array), keterangan_kenaikan
}

// app/Domains/Akademik/DataTransferObjects/CatatanWaliKelasData.php
final readonly class CatatanWaliKelasData {
    public static function fromArray(array $data): self; // lihat field di atas, key snake_case
}

// app/Domains/Akademik/Actions/Rapor/SimpanCatatanWaliKelasAction.php
final class SimpanCatatanWaliKelasAction {
    public function execute(CatatanWaliKelasData $data): CatatanWaliKelas; // updateOrCreate by [siswa_id, semester_id]
}

// app/Domains/Akademik/Actions/Rapor/SubmitPengajuanRaporAction.php
final class SubmitPengajuanRaporAction {
    public function __construct(private readonly InitializeApprovalRequestAction $initializeApprovalRequestAction) {}
    /** @throws ValidationException jika ada siswa di $kelas yang belum punya CatatanWaliKelas utk $semester;
     *  pesan errornya di key 'catatan_wali_kelas', berisi daftar nama siswa yang belum lengkap */
    public function execute(Kelas $kelas, Semester $semester, User $user): PengajuanRapor;
}

// app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php (Waka)
final class VerifyPengajuanRaporAction {
    public function __construct(private readonly ProcessApprovalAction $processApprovalAction) {}
    /** @throws ValidationException jika $user->lembaga_id !== $pengajuanRapor->lembaga_id (guard tenant eksplisit,
     *  ditambahkan saat final review 04b), atau jika belum ada approvalRequest, atau jika role/step tidak sesuai
     *  (dilempar dari dalam ProcessApprovalAction via ApproverResolverService) */
    public function execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor;
}

// app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php (Kepsek) — signature identik VerifyPengajuanRaporAction

// app/Domains/Akademik/Services/RaporCalculationService.php
final class RaporCalculationService {
    /** @return array{siswaList: Collection<Siswa>, mapelList: Collection<MataPelajaran>,
     *   rekapNilai: array<int siswa_id, array<int mapel_id, float|null>>, classAvg: ?float, highestScore: ?float} */
    public function hitungRekapKelas(Kelas $kelas, Semester $semester): array;
}

// app/Domains/Akademik/Services/CapaianKompetensiGenerator.php
final class CapaianKompetensiGenerator {
    /** @return array{tertinggi: ?string, terendah: ?string} — null kalau siswa itu tidak punya nilai di mapel itu */
    public function generateNarasi(Siswa $siswa, MataPelajaran $mapel, Semester $semester): array;
}
```

**PENTING — batasan `ApprovalAction` yang boleh dipakai UI 04c:** enum `App\Domains\Workflow\Enums\ApprovalAction` punya 3 case (`Approve`, `Reject`, `RequestRevision`), dan Pengadaan menawarkan ketiganya di form keputusan. **`VerifyPengajuanRaporAction`/`ApprovePengajuanRaporAction` HANYA menangani `Approve` dan `Reject`** — kalau `RequestRevision` dikirim, `ProcessApprovalAction` akan tetap mengubah `ApprovalRequest.status` jadi `RevisionRequired`, TAPI kedua Action Rapor ini tidak punya cabang untuk itu sehingga `PengajuanRapor.status` (kolom sinkron milik domain Rapor sendiri) TIDAK ikut berubah — menyebabkan desync antara `ApprovalRequest.status` dan `PengajuanRapor.status`. **Form keputusan 04c HANYA boleh menyediakan 2 pilihan: Approve / Reject** — jangan menyalin opsi "Minta Revisi" dari form Pengadaan.

### 3.6. Satu Action Baru yang Perlu Ditambahkan di 04c

Tidak ada field tersimpan untuk "narasi capaian per mapel" — `CatatanWaliKelas` hanya punya satu field umum `catatan_perkembangan` per siswa (bukan per mapel), sedangkan `CapaianKompetensiGenerator::generateNarasi()` bekerja per satu `MataPelajaran`. Untuk tombol "Generate Otomatis" di form wali kelas, dibutuhkan satu Action baru yang me-loop semua mapel yang diikuti siswa di kelas+semester itu dan menggabungkan hasilnya jadi satu draft paragraf — **ditambahkan sebagai Action baru**, bukan logic di controller, supaya konsisten dengan pola domain yang sudah ada:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rapor;

use App\Domains\Akademik\Services\CapaianKompetensiGenerator;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;

final class GenerateNarasiPerkembanganAction
{
    public function __construct(
        private readonly RaporCalculationService $raporCalculationService,
        private readonly CapaianKompetensiGenerator $capaianKompetensiGenerator,
    ) {
    }

    /**
     * Gabungkan narasi capaian tertinggi/terendah lintas semua mapel yang diikuti siswa
     * di kelas+semester tsb jadi satu draft paragraf untuk field catatan_perkembangan.
     * String kosong jika siswa tidak punya nilai sama sekali di kelas itu.
     */
    public function execute(Siswa $siswa, Kelas $kelas, Semester $semester): string
    {
        $mapelList = $this->raporCalculationService->hitungRekapKelas($kelas, $semester)['mapelList'];

        $kalimat = [];
        foreach ($mapelList as $mapel) {
            $narasi = $this->capaianKompetensiGenerator->generateNarasi($siswa, $mapel, $semester);
            if ($narasi['tertinggi'] !== null) {
                $kalimat[] = $narasi['tertinggi'];
            }
            if ($narasi['terendah'] !== null) {
                $kalimat[] = $narasi['terendah'];
            }
        }

        return implode(' ', $kalimat);
    }
}
```

Dipanggil lewat endpoint AJAX kecil (`guru.rapor.catatan.generate-narasi`, lihat §4.3) yang mengembalikan JSON `{narasi: string}` untuk mengisi textarea via JS — BUKAN bagian dari `SimpanCatatanWaliKelasAction` (generate ≠ simpan, wali kelas tetap harus klik Simpan terpisah setelah mengedit hasil generate).

---

## 4. Sisi Guru / Wali Kelas

### 4.1. Deteksi Jenjang untuk Field Kondisional

`CatatanWaliKelas` punya field yang hanya relevan untuk jenjang tertentu. Deteksi jenjang dari `Kelas->lembaga->bentuk_pendidikan`, dengan whitelist **identik** dengan precedent `ModePembelajaran::fromBentukPendidikan()` (Sub-Task 03b, `app/Domains/Akademik/Enums/ModePembelajaran.php`) — jangan buat enum/helper baru, cukup reuse mapping berikut langsung di view/controller:

| `bentuk_pendidikan` | Field ditampilkan |
|---|---|
| `KB`, `TPA`, `SPS`, `TK` | Field umum + **antropometri** (`tinggi_badan_cm`, `berat_badan_kg`, `lingkar_kepala_cm`) |
| `SD`, `SLB` | Field umum saja |
| `SMP`, `SMA` | Field umum saja |
| `SMK` | Field umum + **`pkl_info`** (repeatable array) |
| nilai lain di masa depan (belum dikenal kode) | **default ke field umum saja** (whitelist-positif hanya untuk KB/TPA/SPS/TK dan SMK — kalau tidak cocok whitelist, jangan tampilkan field khusus, konsisten dengan filosofi "default ke yang lebih sederhana lebih aman" dari 03b) |

"Field umum" = `catatan_sikap`, `catatan_perkembangan`, `ekstrakurikuler` (repeatable array), `prestasi` (repeatable array), `keterangan_kenaikan` — ditampilkan untuk SEMUA jenjang tanpa kecuali.

### 4.2. `Guru\RaporController` — Routes & Actions

```
GET  guru/rapor                                    -> index()          [guru.rapor.catatan.index]
GET  guru/rapor/siswa/{siswa}                       -> edit()           [guru.rapor.catatan.edit]
PUT  guru/rapor/siswa/{siswa}                       -> update()         [guru.rapor.catatan.update]
POST guru/rapor/generate-narasi/{siswa}             -> generateNarasi() [guru.rapor.catatan.generate-narasi]
POST guru/rapor/ajukan                              -> ajukan()         [guru.rapor.pengajuan.submit]
```

Semua action `$this->authorize('rapor.input-wali')` KECUALI `ajukan()` yang pakai `$this->authorize('rapor.ajukan')` (permission sudah ada dari 04b, sudah di-assign ke role `guru`).

**`index(Request $request): View`**
- `$guru = $request->user()->guru; abort_if($guru === null, 403);`
- Selector Tahun Ajaran → Semester → Kelas identik pola `RekapKehadiranController::index()` (§2), TAPI query kelas difilter `Kelas::where('wali_kelas_guru_id', $guru->id)` (guru cuma bisa mengelola kelas yang dia jadi wali kelasnya — TIDAK sama dengan `authorizeMengajarMapel()`/`mengajarKombinasiIni` dari 04a yang berbasis jadwal mengajar, ini berbasis `wali_kelas_guru_id`).
- Kalau ada `$kelas` & `$semester` terpilih: ambil `$siswaList = Siswa::where('kelas_id', $kelas->id)->orderBy('nama_lengkap')->get()`, lalu untuk tiap siswa cek `CatatanWaliKelas::where('siswa_id', $siswa->id)->where('semester_id', $semester->id)->exists()` untuk badge Lengkap/Belum (definisi "lengkap" HARUS identik dengan yang dipakai `SubmitPengajuanRaporAction` — keberadaan baris, bukan validasi per-field — supaya badge tidak menyesatkan wali kelas).
- Cari `$pengajuanRapor = PengajuanRapor::where('kelas_id', $kelas->id)->where('semester_id', $semester->id)->first()` untuk tahu status saat ini (`null`/Draft = belum pernah diajukan, `Ditolak` = boleh ajukan ulang dengan `catatan_revisi` ditampilkan, `Diajukan`/`Diverifikasi`/`Disetujui` = tombol "Ajukan Rapor" disembunyikan, field CatatanWaliKelas tetap read-only-tampil tapi form edit tetap bisa dibuka — **submit ulang PUT tetap diizinkan** karena backend (`SimpanCatatanWaliKelasAction`) tidak mengecek status pengajuan; ini murni UX, bukan validasi keras. Nilai sudah dikunci lewat `SimpanNilaiSiswaAction` terpisah kalau `Disetujui`, tapi `CatatanWaliKelas` sendiri TIDAK dikunci oleh backend manapun — dokumentasikan ini sebagai keputusan sadar, bukan celah, karena revisi catatan naratif setelah approve dianggap kasus langka yang tidak perlu diblok backend).
- View: `portals.guru.rapor.catatan.index`, list pakai pola AJAX partial `_daftar` sama seperti §2 (walau jumlah siswa per kelas biasanya kecil, tetap ikuti pola codebase untuk konsistensi — TIDAK perlu pagination, cukup search-filter nama siswa).
- Tombol "Ajukan Rapor" POST ke `guru.rapor.pengajuan.submit` dengan hidden input `kelas_id`/`semester_id`, disabled (via Alpine, cek `siswaList.every(s => s.lengkap)`) kalau ada siswa belum lengkap.

**`edit(Siswa $siswa, Request $request): View`**
- `abort_unless($siswa->kelas?->wali_kelas_guru_id === $guru->id, 403)`.
- Ambil `$semester` dari query string `?semester_id=` (wajib ada, redirect ke index dengan error kalau tidak ada — halaman ini tidak berdiri sendiri tanpa konteks semester).
- Ambil/`new CatatanWaliKelas` existing by `[siswa_id, semester_id]` untuk prefill form.
- Hitung `$siswaListKelas = Siswa::where('kelas_id', $siswa->kelas_id)->orderBy('nama_lengkap')->get()` untuk tombol "Siswa Sebelumnya"/"Siswa Berikutnya" (index posisi siswa saat ini di collection itu → link ke `edit()` siswa index-1/index+1, disabled kalau di ujung list).
- View: `portals.guru.rapor.catatan.edit`. Field kondisional per §4.1. Field `ekstrakurikuler`/`prestasi`/(`pkl_info` kalau SMK) pakai pola Alpine repeatable-array dari §2 (items push/splice, `:name="`ekstrakurikuler[${index}][field]`"`).

**`update(Siswa $siswa, Request $request): RedirectResponse`**
- FormRequest baru `App\Http\Requests\Akademik\StoreCatatanWaliKelasRequest` — `authorize()` mengikuti precedent `StoreKomponenPenilaianRequest`/`StoreAsesmenRequest` (04a): `return $this->user()->can('rapor.input-wali');` (permission-only, ownership `wali_kelas_guru_id` tetap dicek terpisah di controller karena FormRequest tidak sederhana untuk itu, sama seperti pola `AsesmenController::authorizeMilikGuru()`). Rules: `catatan_sikap` nullable string max:2000, `catatan_perkembangan` nullable string max:2000, `tinggi_badan_cm`/`berat_badan_kg`/`lingkar_kepala_cm` nullable numeric min:0, `ekstrakurikuler`/`prestasi`/`pkl_info` nullable array, `keterangan_kenaikan` nullable string max:1000, `semester_id` required exists:semesters,id.
- `abort_unless($siswa->kelas?->wali_kelas_guru_id === $guru->id, 403)`.
- Panggil `SimpanCatatanWaliKelasAction` dengan `CatatanWaliKelasData::fromArray([...$request->validated(), 'siswa_id' => $siswa->id])`.
- Redirect: kalau request punya input hidden `next_siswa_id` (dari tombol "Simpan & Siswa Berikutnya") → redirect ke `edit()` siswa itu; kalau tidak (tombol "Simpan & Kembali ke Daftar") → redirect ke `index()`. Flash `session('success')`.

**`generateNarasi(Siswa $siswa, Request $request): JsonResponse`**
- `abort_unless($siswa->kelas?->wali_kelas_guru_id === $guru->id, 403)`.
- Validasi `semester_id`/`kelas_id` dari query, load `$semester`/`$kelas`.
- `return response()->json(['narasi' => app(GenerateNarasiPerkembanganAction::class)->execute($siswa, $kelas, $semester)]);`
- Endpoint ini dipanggil AJAX dari tombol "Generate Otomatis" di halaman `edit` — hasil JSON dipakai JS untuk mengisi textarea `catatan_perkembangan` (replace penuh, bukan append — wali kelas sadar ini overwrite draft manual sebelumnya kalau ada, tampilkan `confirmDialog()` global sebelum overwrite kalau textarea sudah tidak kosong).

**`ajukan(Request $request): RedirectResponse`**
- FormRequest `App\Http\Requests\Akademik\SubmitPengajuanRaporRequest` — `authorize()`: `return $this->user()->can('rapor.ajukan');`. Rules: `kelas_id` required exists:kelas,id, `semester_id` required exists:semesters,id.
- `abort_unless($kelas->wali_kelas_guru_id === $guru->id, 403)`.
- Panggil `SubmitPengajuanRaporAction::execute($kelas, $semester, $request->user())`.
- Tangkap `ValidationException` dari Action (siswa belum lengkap) — biarkan Laravel default exception handler redirect-back-with-errors seperti biasa (Action sudah melempar pesan yang jelas di key `catatan_wali_kelas`, tidak perlu ditangkap manual).
- Sukses: redirect ke `guru.rapor.catatan.index` dengan `session('success', 'Rapor kelas berhasil diajukan untuk verifikasi Waka Kurikulum.')`.

---

## 5. Sisi Waka & Kepsek

### 5.1. `Lembaga\Rapor\PersetujuanController` — Routes & Actions

```
GET  admin/rapor/persetujuan                        -> index()    [admin.rapor.persetujuan.index]
GET  admin/rapor/persetujuan/{pengajuanRapor}        -> show()     [admin.rapor.persetujuan.show]
POST admin/rapor/persetujuan/{pengajuanRapor}/keputusan -> decision() [admin.rapor.persetujuan.decision]
```

Route-model-binding `{pengajuanRapor}` otomatis tenant-scoped lewat `BelongsToTenant` pada `PengajuanRapor` — kelas dari lembaga lain akan 404 (bukan 403) sebelum masuk controller, konsisten dengan pola tenant-scoping yang sudah dipakai di seluruh modul ini.

**`index(Request $request): View`**
- `$this->authorize('rapor.verify')` ATAU `$this->authorize('rapor.approve')` — pakai `abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403)` (pola OR-permission sama seperti `ApprovalPengadaanController::authorizeApprover()`).
- Tentukan status yang dicari berdasarkan role aktor: `$request->user()->can('rapor.approve') ? StatusPengajuanRapor::Diverifikasi : StatusPengajuanRapor::Diajukan` — **catatan penting**: kalau satu user (jarang, tapi mungkin secara data) punya KEDUA permission, prioritaskan `rapor.approve` (tampilkan inbox tahap Kepsek) karena itu tahap akhir yang lebih genting; ini keputusan implementer-level yang aman karena kombinasi role ini seharusnya tidak terjadi (RoleSeeder sengaja memisahkan `rapor.verify`↔`admin_akademik` dan `rapor.approve`↔`kepala_sekolah`, "integritas dua-orang").
- Query: `PengajuanRapor::where('status', $statusYangDicari)->with(['kelas.tahunAjaran', 'semester'])`, TIDAK perlu filter `lembaga_id` manual (sudah otomatis lewat `BelongsToTenant` global scope terhadap `auth()->user()`).
- `$request->ajax()` → return partial `portals.lembaga.rapor.persetujuan._daftar`; else → `portals.lembaga.rapor.persetujuan.index` (list biasa, TIDAK perlu KPI card seperti Pengadaan — cukup tabel + search kelas).

**`show(PengajuanRapor $pengajuanRapor): View`**
- Otorisasi sama seperti `index()` (OR-permission), TAPI tambahkan pengecekan step yang sesuai: `abort_unless($pengajuanRapor->status === (auth()->user()->can('rapor.approve') ? StatusPengajuanRapor::Diverifikasi : StatusPengajuanRapor::Diajukan), 404, 'Pengajuan ini bukan berada di tahap Anda.')` — mencegah Waka membuka halaman kelas yang sudah lewat tahapnya (dan sebaliknya).
- `$pengajuanRapor->load(['kelas', 'semester', 'approvalRequest.logs.user', 'approvalRequest.currentStep'])`.
- `$rekap = app(RaporCalculationService::class)->hitungRekapKelas($pengajuanRapor->kelas, $pengajuanRapor->semester)`.
- `$catatanList = CatatanWaliKelas::where('semester_id', $pengajuanRapor->semester_id)->whereIn('siswa_id', $rekap['siswaList']->pluck('id'))->get()->keyBy('siswa_id')`.
- View: `portals.lembaga.rapor.persetujuan.show` — tampilkan rekap nilai (tabel siswa × mapel, pola sama seperti `admin.rapor.index`'s `_hasil.blade.php` yang sudah ada) DAN untuk tiap siswa expandable/list ringkasan `CatatanWaliKelas` (catatan_sikap, catatan_perkembangan, dst — read-only, Waka/Kepsek TIDAK mengedit catatan siswa, hanya membaca), lalu form keputusan di bagian bawah (§5.2).

**`decision(ProcessRaporApprovalRequest $request, PengajuanRapor $pengajuanRapor): RedirectResponse`**
- FormRequest baru `App\Http\Requests\Akademik\ProcessRaporApprovalRequest` — `authorize()` mengikuti precedent `ProcessApprovalRequest` milik Pengadaan: `return $this->user()?->canAny(['rapor.verify', 'rapor.approve']) ?? false;` (permission-only; pengecekan step-mana-yang-sesuai tetap di controller, sama seperti Pengadaan). Rules: `action` required, `Rule::in(['APPROVE', 'REJECT'])` (**HANYA 2 nilai — lihat batasan §3**), `catatan` nullable string max:1000.
- Otorisasi & pengecekan step sama seperti `show()`.
- `$action = ApprovalAction::from($request->validated()['action']);`
- Kalau role aktor `rapor.approve` (Kepsek, status saat ini `Diverifikasi`) → panggil `app(ApprovePengajuanRaporAction::class)->execute($pengajuanRapor, $request->user(), $action, $request->validated()['catatan'] ?? null)`.
- Kalau bukan (Waka, status saat ini `Diajukan`) → panggil `app(VerifyPengajuanRaporAction::class)->execute(...)` dengan parameter sama.
- Redirect ke `admin.rapor.persetujuan.index` dengan flash pesan sesuai `$action` (`Approve` → "berhasil diverifikasi/disetujui", `Reject` → "berhasil ditolak, wali kelas dapat mengajukan ulang setelah revisi").

### 5.2. Form Keputusan (`show.blade.php`)

Sama seperti Pengadaan `review.blade.php` TAPI **tanpa** evaluasi per-item (Rapor tidak punya konsep item barang) dan **tanpa** opsi radio "Minta Revisi" (lihat batasan §3):

```blade
<form action="{{ route('admin.rapor.persetujuan.decision', $pengajuanRapor) }}" method="POST" x-data="{ action: 'APPROVE' }">
    @csrf
    <div class="flex flex-wrap gap-4">
        <label><input type="radio" name="action" value="APPROVE" x-model="action"> Setujui</label>
        <label><input type="radio" name="action" value="REJECT" x-model="action"> Tolak, Minta Revisi Wali Kelas</label>
    </div>
    <textarea name="catatan" placeholder="Catatan untuk wali kelas..."></textarea>
    <button type="submit">Kirim Keputusan</button>
</form>
```

---

## 6. Navigasi

Tambah ke `resources/views/layouts/sidebar.blade.php`, HANYA menambah entri `array_filter([...])` baru ke dalam array `items` grup yang SUDAH ADA (tidak membuat grup baru, tidak mengubah struktur array):

**Grup "Ruang Guru"** (setelah entri `guru.asesmen.*` yang sudah ada):
```php
Auth::user()->can('rapor.input-wali') ? ['route' => 'guru.rapor.catatan.index', 'pattern' => 'guru.rapor.*', 'label' => 'Rapor Wali Kelas', 'icon' => 'book-text'] : null,
```

**Grup "Akademik"** (setelah entri `admin.rapor.*` yang sudah ada dari 04a):
```php
Auth::user()->canAny(['rapor.verify', 'rapor.approve']) ? ['route' => 'admin.rapor.persetujuan.index', 'pattern' => 'admin.rapor.persetujuan.*', 'label' => 'Persetujuan Rapor', 'icon' => 'check-square'] : null,
```

---

## 7. Testing

Feature test (bukan unit) per controller, pola sama seperti 04a/04b — nama file & assersi minimal wajib ada:

- **`tests/Feature/Guru/RaporControllerTest.php`**:
  - Guru hanya bisa lihat/edit siswa di kelas yang dia jadi wali kelasnya (`wali_kelas_guru_id`), bukan kelas lain (403).
  - `update()` menyimpan `CatatanWaliKelas` lewat `SimpanCatatanWaliKelasAction` dengan benar (assert row tersimpan, bukan assert internal Action-nya — itu sudah ditest di 04b).
  - `generateNarasi()` mengembalikan JSON `narasi` non-kosong ketika siswa punya nilai, dan string kosong ketika tidak ada nilai sama sekali.
  - `ajukan()` redirect-with-errors ketika ada siswa yang belum punya `CatatanWaliKelas` (pesan dari `SubmitPengajuanRaporAction` diteruskan apa adanya, jangan ditest ulang isi pesannya di sini — cukup assert redirect + session errors ada).
  - Field antropometri MUNCUL di form untuk kelas jenjang TK, TIDAK muncul untuk kelas jenjang SMP (assert `assertSee`/`assertDontSee` pada response `edit()`).

- **`tests/Feature/Lembaga/Rapor/PersetujuanControllerTest.php`**:
  - Waka (`rapor.verify`) hanya melihat pengajuan berstatus `Diajukan` di inbox-nya, TIDAK melihat yang `Diverifikasi`/`Disetujui`.
  - Kepsek (`rapor.approve`) hanya melihat yang `Diverifikasi`.
  - `show()` 404 kalau Waka membuka pengajuan yang sudah `Diverifikasi` (bukan tahapnya lagi).
  - `decision()` dengan `action=APPROVE` oleh Waka mengubah status jadi `Diverifikasi` (assert lewat DB, bukan assert ulang logic Action).
  - `decision()` menolak `action=REQUEST_REVISION` dengan 422 (membuktikan batasan §3 benar-benar ditegakkan di FormRequest, bukan cuma dokumentasi).
  - Cross-tenant: `PengajuanRapor` lembaga lain 404 lewat route-model-binding (pola sama seperti `RaporApprovalTenantScopeTest` di 04b, tapi di level HTTP kali ini).

Tidak perlu menulis ulang test untuk logic Action/Service (`SimpanCatatanWaliKelasAction`, `SubmitPengajuanRaporAction`, `VerifyPengajuanRaporAction`, `ApprovePengajuanRaporAction`, `RaporCalculationService`, `CapaianKompetensiGenerator`) — itu tanggung jawab test 04b yang sudah lulus. Test 04c fokus ke: routing, otorisasi berbasis permission+kepemilikan, dan pemetaan HTTP request → parameter Action yang benar.

---

## 8. Asumsi & Keputusan Desain

1. `CatatanWaliKelas` TIDAK dikunci setelah rapor `Disetujui` (berbeda dengan `NilaiSiswa` yang dikunci `SimpanNilaiSiswaAction` di 04b) — wali kelas tetap bisa mengedit catatan naratif kapan saja lewat `update()`. Ini keputusan sadar (dianggap kasus langka, tidak ada requirement eksplisit untuk mengunci ini), bukan celah yang perlu ditambal 04c.
2. `catatan_revisi` dari siklus penolakan sebelumnya TIDAK otomatis dibersihkan oleh backend saat resubmit (temuan final review 04b) — halaman `guru.rapor.catatan.index` HARUS mengecek `$pengajuanRapor->status` untuk menentukan apakah `catatan_revisi` yang ditampilkan itu masih relevan (tampilkan hanya kalau `status === Ditolak`), jangan asumsikan non-null berarti penolakan aktif.
3. Definisi "siswa sudah lengkap" di badge UI 04c HARUS sama persis dengan yang dipakai `SubmitPengajuanRaporAction` (keberadaan baris `CatatanWaliKelas`, bukan validasi tiap field wajib diisi) — supaya badge "Lengkap" di UI tidak pernah berbohong dibanding apa yang sebenarnya membuat submit berhasil/gagal.
4. Field jenjang-kondisional (§4.1) memakai whitelist yang sama dengan `ModePembelajaran` (03b) TAPI TIDAK memanggil enum itu langsung (enum itu untuk keperluan mode pembelajaran harian, konteks beda) — cukup duplikasi logic `in_array($bentukPendidikan, [...])` sesederhana mungkin di controller/view, JANGAN membuat abstraksi/enum baru untuk satu keperluan tampilan form ini (YAGNI).
