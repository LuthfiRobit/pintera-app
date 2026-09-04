# Spec: Fitur Ruang Siswa — Nilai & Rapor, Jadwal Pelajaran, Presensi Saya

**Tanggal**: 2026-09-04
**Branch**: `akademik-v2`
**Konteks**: 3 item menu "Ruang Siswa" (`Nilai & Rapor`, `Jadwal Pelajaran`, `Presensi Saya`) sengaja disembunyikan dari sidebar sejak 2026-09-03 (`resources/views/layouts/sidebar.blade.php:27-35`), placeholder ke `/dalam-pengembangan`. Kelanjutan langsung dari paket Ruang Orang Tua (`.agents/specs/2026-09-04-fitur-ruang-orang-tua-akademik.md`) yang baru selesai & bersih (2.813 test passed).

**Scope eksplisit**: HANYA sisi Siswa. **UI/UX sengaja MINIMAL/FUNGSIONAL** — user akan memoles tampilan sendiri lewat agent lain setelah backend selesai. Implementer TIDAK PERLU menghabiskan waktu untuk detail visual (warna, layout card, dsb) seperti paket Orang Tua — cukup pastikan halaman bisa diakses tanpa error dan menampilkan data dengan benar lewat markup Blade paling dasar.

## 1. Keputusan yang Sudah Final (dikonfirmasi lewat brainstorming, jangan tanya ulang)

- **Nilai & Rapor**: tabel nilai per mapel + filter semester + unduh Rapor PDF resmi (reuse `RaporPdfDataBuilder`, sama seperti sisi Orang Tua).
- **Jadwal Pelajaran**: 1 minggu penuh + filter semester (dropdown) — BUKAN cuma semester aktif seperti dashboard existing.
- **Presensi Saya**: daftar detail per sesi (tanggal, mapel, status, keterangan) dengan filter rentang tanggal — BUKAN cuma rekap agregat seperti dashboard existing. Ini query BARU, belum ada presedennya di codebase untuk siswa.
- **Test keamanan**: WAJIB 1 test regresi per controller (3 total) — buktikan data yang tampil SELALU dari `$request->user()->siswa` sendiri, walau ada siswa lain dengan data serupa di DB. BUKAN test IDOR lewat query string (permukaan itu tidak ada di sisi siswa — tidak ada parameter `siswa_id` yang bisa dimanipulasi, beda dari sisi Orang Tua).
- **Struktur**: 3 controller terpisah di `app/Http/Controllers/Admin/` (`NilaiRaporSiswaController`, `JadwalPelajaranSiswaController`, `PresensiSayaController`), TIDAK ADA trait resolve-anak (siswa akses `$request->user()->siswa` langsung, satu identitas, tidak perlu selector seperti Orang Tua).
- **Routing**: file baru `routes/admin/siswa-akademik.php` (TERPISAH dari `orang-tua-akademik.php`), ditambahkan ke daftar `require` di `routes/admin.php`.
- **UI**: minimal, reuse `<x-app-layout>` + markup tabel/list paling dasar. TIDAK perlu `<x-panel>`/token warna `text-ink` dkk seperti paket Orang Tua — cukup fungsional dan bisa diuji, karena akan ditimpa user sendiri.

**Catatan teknis penting soal `TenantScope`** (WAJIB dibaca sebelum implementasi, JANGAN diasumsikan): akun `User` siswa PUNYA `lembaga_id` terisi asli (`App\Services\AkunSiswaGenerator.php:18`), BEDA dari akun Orang Tua yang `lembaga_id`-nya `null`. Karena itu `TenantScope` untuk actor siswa resolve NORMAL lewat cabang akhir (`where('lembaga_id', $actor->lembaga_id)`), TIDAK kena kelas bug "`lembaga_id` null → `WHERE lembaga_id IS NULL` tidak pernah cocok" yang ditemukan 3× di paket Orang Tua. Relasi `User::siswa(): HasOneThrough` (`app/Models/User.php:100-110`) SUDAH membungkus `->withoutGlobalScope(TenantScope::class)` di level DEFINISI relasi — jadi `$request->user()->siswa` (akses property dinamis) SUDAH otomatis aman dari isu scope, TANPA perlu bypass tambahan di titik pakai. Query lanjutan (`NilaiSiswa`, `Presensi`) di dashboard existing TIDAK memakai `withoutGlobalScope` sama sekali dan SUDAH terbukti jalan di produksi — sedangkan `JadwalPelajaran` di dashboard existing MEMAKAI `withoutGlobalScope` meski secara teori tidak wajib (kedua model sama-sama `BelongsToTenant` dengan kolom `lembaga_id` asli). **Instruksi untuk implementasi**: ikuti PERSIS pola bypass-atau-tidak dari baris query dashboard yang berkorespondensi (lihat §2 di bawah, kode disalin verbatim) — JANGAN menyeragamkan sendiri jadi "selalu pakai" atau "selalu tidak pakai" tanpa dasar, karena pola existing ini SUDAH terverifikasi jalan di produksi dan mengubahnya tanpa alasan konkret cuma menambah risiko regresi tak terduga.

## 2. Desain Komponen

### 2.1. `NilaiRaporSiswaController`

**File baru**: `app/Http/Controllers/Admin/NilaiRaporSiswaController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Akademik\Services\RaporPdfDataBuilder;
use App\Models\Scopes\TenantScope;
use App\Models\Semester;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NilaiRaporSiswaController extends BaseController
{
    public function __construct(
        private readonly RaporPdfDataBuilder $raporPdfDataBuilder,
    ) {}

    public function index(Request $request): View
    {
        $siswa = $request->user()->siswa;

        $semesterList = $siswa && $siswa->kelas
            ? Semester::where('tahun_ajaran_id', $siswa->kelas->tahun_ajaran_id)->orderByDesc('id')->get()
            : collect();
        $semesterId = $request->integer('semester_id') ?: $semesterList->first()?->id;

        $nilaiList = ($siswa && $semesterId)
            ? NilaiSiswa::where('siswa_id', $siswa->id)
                ->whereNotNull('nilai_angka')
                ->whereHas('asesmen', fn ($q) => $q->where('semester_id', $semesterId)->whereIn('jenis', JenisAsesmen::masukRapor()))
                ->with(['komponenPenilaian.subjek', 'asesmen.subjek'])
                ->get()
            : collect();

        $pengajuanRapor = ($siswa && $semesterId)
            ? PengajuanRapor::where('kelas_id', $siswa->kelas_id)
                ->where('semester_id', $semesterId)
                ->where('status', StatusPengajuanRapor::Disetujui)
                ->first()
            : null;

        return view('admin.siswa-akademik.nilai-rapor', [
            'siswa' => $siswa,
            'semesterList' => $semesterList,
            'semesterId' => $semesterId,
            'nilaiList' => $nilaiList,
            'pengajuanRapor' => $pengajuanRapor,
        ]);
    }

    public function unduhRapor(Request $request): Response
    {
        $siswa = $request->user()->siswa;
        abort_if($siswa === null, 403);

        $semester = Semester::find((int) $request->query('semester_id'));
        abort_if($semester === null, 404);

        $pengajuanRapor = PengajuanRapor::where('kelas_id', $siswa->kelas_id)
            ->where('semester_id', $semester->id)
            ->where('status', StatusPengajuanRapor::Disetujui)
            ->first();
        abort_if($pengajuanRapor === null, 404, 'Rapor untuk semester ini belum tersedia.');

        $data = $this->raporPdfDataBuilder->build($siswa, $semester);
        $template = $this->raporPdfDataBuilder->templateUntukJenjang($siswa->kelas->lembaga->bentuk_pendidikan);

        $pdf = Pdf::loadView($template, $data);

        return $pdf->stream('rapor-'.Str::slug($siswa->nama_lengkap).'.pdf');
    }
}
```

**Catatan penting**: `unduhRapor()` TIDAK menerima parameter `{siswa}` sama sekali (beda dari sisi Orang Tua) — `$siswa` SELALU `$request->user()->siswa`, tidak ada permukaan input untuk dimanipulasi, jadi TIDAK ADA celah IDOR yang perlu ditutup lewat `abort_unless(anakList->contains(...))` seperti di sisi Orang Tua. `abort_if($siswa === null, 403)` cukup untuk kasus "user login tapi bukan siswa sungguhan" (akun rusak/salah role).

**Catatan soal `withoutGlobalScope`**: TIDAK dipakai di controller ini sama sekali, PERSIS meniru pola dashboard existing (`NilaiSiswa`/`PengajuanRapor` query di dashboard TIDAK memakainya). `$request->user()->siswa` sendiri sudah aman dari isu scope lewat definisi relasi `User::siswa()` (lihat §1).

### 2.2. `JadwalPelajaranSiswaController`

**File baru**: `app/Http/Controllers/Admin/JadwalPelajaranSiswaController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\JadwalPelajaran;
use App\Models\Scopes\TenantScope;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JadwalPelajaranSiswaController extends BaseController
{
    private const HARI_ORDER = [
        'senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4,
        'jumat' => 5, 'sabtu' => 6, 'minggu' => 7,
    ];

    public function index(Request $request): View
    {
        $siswa = $request->user()->siswa;

        $semesterList = $siswa && $siswa->kelas
            ? Semester::where('tahun_ajaran_id', $siswa->kelas->tahun_ajaran_id)->orderByDesc('id')->get()
            : collect();
        $semesterId = $request->integer('semester_id')
            ?: ($siswa && $siswa->kelas
                ? Semester::where('tahun_ajaran_id', $siswa->kelas->tahun_ajaran_id)->where('status_aktif', true)->value('id')
                : null)
            ?: $semesterList->first()?->id;

        $jadwalList = ($siswa && $siswa->kelas_id !== null && $semesterId)
            ? JadwalPelajaran::withoutGlobalScope(TenantScope::class)
                ->where('kelas_id', $siswa->kelas_id)
                ->where('semester_id', $semesterId)
                ->with([
                    'jamPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                    'mataPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                    'guru' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with('person'),
                ])
                ->get()
                ->sortBy(fn (JadwalPelajaran $jadwal) => sprintf(
                    '%d-%s',
                    self::HARI_ORDER[$jadwal->jamPelajaran?->hari?->value ?? ''] ?? 9,
                    $jadwal->jamPelajaran?->jam_mulai ?? ''
                ))
                ->groupBy(fn (JadwalPelajaran $jadwal) => $jadwal->jamPelajaran->hari->value)
            : collect();

        return view('admin.siswa-akademik.jadwal-pelajaran', [
            'siswa' => $siswa,
            'semesterList' => $semesterList,
            'semesterId' => $semesterId,
            'jadwalList' => $jadwalList,
        ]);
    }
}
```

**Catatan soal resolusi semester default**: sudah diverifikasi langsung dari kode — `JadwalPelajaran::scopeSemesterAktif()` (`app/Models/JadwalPelajaran.php:83-86`) adalah query SCOPE (`whereHas('semester', fn($q) => $q->where('status_aktif', true))`), BUKAN method yang mengembalikan ID semester. Karena halaman ini butuh filter dropdown (bukan cuma scope query tersembunyi), resolusi default-nya dilakukan manual lewat `Semester::where('tahun_ajaran_id', ...)->where('status_aktif', true)->value('id')` seperti kode final di atas — JANGAN pakai `scopeSemesterAktif()` di sini, method itu untuk konteks berbeda (dashboard yang tidak punya filter semester).

**Catatan soal `withoutGlobalScope`**: DIPAKAI di sini, PERSIS meniru pola dashboard existing untuk `JadwalPelajaran`. Ganti filter `whereHas('jamPelajaran', fn($q) => $q->where('hari', $hariIni))` (versi dashboard, cuma hari ini) jadi TANPA filter hari sama sekali (ambil semua hari dalam semester itu, dikelompokkan) — sesuai keputusan "1 minggu penuh".

### 2.3. `PresensiSayaController`

**File baru**: `app/Http/Controllers/Admin/PresensiSayaController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Models\Presensi;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class PresensiSayaController extends BaseController
{
    public function index(Request $request): View
    {
        $request->validate([
            'dari_tanggal' => ['nullable', 'date'],
            'sampai_tanggal' => ['nullable', 'date', 'after_or_equal:dari_tanggal'],
        ]);

        $siswa = $request->user()->siswa;

        $dariTanggal = $request->date('dari_tanggal') ?: now()->startOfMonth();
        $sampaiTanggal = $request->date('sampai_tanggal') ?: now()->endOfMonth();

        $riwayatList = $siswa
            ? Presensi::where('siswa_id', $siswa->id)
                ->whereHas('sesiPembelajaran', fn ($q) => $q->whereBetween('tanggal', [$dariTanggal->toDateString(), $sampaiTanggal->toDateString()]))
                ->with(['sesiPembelajaran.mataPelajaran'])
                ->latest('id')
                ->get()
            : collect();

        return view('admin.siswa-akademik.presensi-saya', [
            'siswa' => $siswa,
            'dariTanggal' => $dariTanggal,
            'sampaiTanggal' => $sampaiTanggal,
            'riwayatList' => $riwayatList,
        ]);
    }
}
```

**Beda dari `RiwayatIzinSakitAnakController` (Orang Tua)**: TIDAK ada `->whereIn('status', ['izin', 'sakit'])` — halaman ini menampilkan SEMUA status presensi (hadir/izin/sakit/alpa/terlambat), bukan cuma izin/sakit, sesuai keputusan "Presensi Saya" mencakup riwayat lengkap kehadiran, bukan cuma ketidakhadiran.

**Catatan soal `withoutGlobalScope`**: TIDAK dipakai, meniru pola dashboard existing untuk `Presensi` (dashboard juga tidak memakainya untuk query `Presensi`). Kalau ternyata `sesiPembelajaran` butuh bypass juga (mengingat kasus serupa di sisi Orang Tua kemarin butuh bypass untuk model yang SAMA), **WAJIB dicek ulang lewat test yang benar-benar dijalankan** (lihat §4 test #7) — JANGAN diasumsikan aman/tidak aman tanpa bukti dari hasil test. Perbedaan dengan kasus Orang Tua: actor di sini adalah SISWA (lembaga_id asli terisi), bukan Orang Tua (`lembaga_id` null) — kemungkinan besar TIDAK butuh bypass, tapi WAJIB dibuktikan lewat test yang gagal-dulu (`assertOk()` + assert data benar tampil), bukan diasumsikan dari analisis semata.

### 2.4. Routes

**File baru**: `routes/admin/siswa-akademik.php`

```php
<?php

use App\Http\Controllers\Admin\JadwalPelajaranSiswaController;
use App\Http\Controllers\Admin\NilaiRaporSiswaController;
use App\Http\Controllers\Admin\PresensiSayaController;
use Illuminate\Support\Facades\Route;

Route::get('nilai-rapor-saya', [NilaiRaporSiswaController::class, 'index'])->name('nilai-rapor-saya.index');
Route::get('nilai-rapor-saya/unduh-rapor', [NilaiRaporSiswaController::class, 'unduhRapor'])->name('nilai-rapor-saya.unduh-rapor');
Route::get('jadwal-pelajaran-saya', [JadwalPelajaranSiswaController::class, 'index'])->name('jadwal-pelajaran-saya.index');
Route::get('presensi-saya', [PresensiSayaController::class, 'index'])->name('presensi-saya.index');
```

**Catatan nama route**: sengaja diberi akhiran `-saya`/prefix berbeda dari sisi Orang Tua (`nilai-anak` dkk) supaya tidak bentrok nama route DAN untuk mencerminkan sudut pandang "punya sendiri" vs "punya anak". Route existing `admin.jadwal-pelajaran.index` (dipakai ADMIN/GURU untuk kelola jadwal) TIDAK BOLEH dipakai ulang atau ditimpa — ini route BARU sama sekali, `admin.jadwal-pelajaran-saya.index`, BEDA dari `admin.jadwal-pelajaran.index` yang sudah ada.

**Registrasi**: tambahkan `require base_path('routes/admin/siswa-akademik.php');` ke `routes/admin.php` (setelah baris `require base_path('routes/admin/orang-tua-akademik.php');` kalau baris itu sudah ada dari paket sebelumnya, atau setelah `kasus-admin.php` kalau belum).

### 2.5. Views (MINIMAL, fungsional saja)

3 file baru: `resources/views/admin/siswa-akademik/nilai-rapor.blade.php`, `jadwal-pelajaran.blade.php`, `presensi-saya.blade.php`. Markup PALING DASAR — `<x-app-layout>` + heading + form filter (`<select onchange="this.form.submit()">` untuk semester/tanggal, pola sama seperti Orang Tua tapi TANPA komponen `<x-panel>`/`<x-badge>`/token warna khusus) + tabel HTML polos (`<table>`/`<tr>`/`<td>` dengan class Tailwind minimal `border`, `p-2`, dsb — TIDAK PERLU class Tailwind lengkap/rapi, cukup supaya elemen data ter-render dan bisa di-assert lewat `assertSee()` di test). Detail markup diserahkan ke implementer saat menulis kode — TIDAK perlu contoh lengkap di spec ini (beda dari paket Orang Tua) karena akan ditimpa user.

### 2.6. Sidebar

Buka kembali komentar di `resources/views/layouts/sidebar.blade.php:27-35` (3 baris grup Ruang Siswa untuk "Nilai & Rapor", "Jadwal Pelajaran", "Presensi Saya"), ganti target `route` dari `'dalam-pengembangan'` ke route baru:
```php
Auth::user()->hasRole('siswa') ? ['route' => 'admin.nilai-rapor-saya.index', 'pattern' => 'admin.nilai-rapor-saya.*', 'label' => 'Nilai & Rapor', 'icon' => 'award'] : null,
Auth::user()->hasRole('siswa') ? ['route' => 'admin.jadwal-pelajaran-saya.index', 'pattern' => 'admin.jadwal-pelajaran-saya.*', 'label' => 'Jadwal Pelajaran', 'icon' => 'calendar-clock'] : null,
Auth::user()->hasRole('siswa') ? ['route' => 'admin.presensi-saya.index', 'pattern' => 'admin.presensi-saya.*', 'label' => 'Presensi Saya', 'icon' => 'clipboard-check'] : null,
```
(Kondisi TETAP `Auth::user()->hasRole('siswa')`, PERSIS seperti versi lama yang dikomentari — JANGAN diubah ke `Auth::user()->siswa !== null` meski itu tampak lebih konsisten dengan pola Orang Tua, karena baris ASLI sebelum dikomentari sudah pakai `hasRole('siswa')` — ubah HANYA `route`/`pattern`/kalau label perlu disesuaikan, jangan ubah kondisi guard-nya tanpa alasan.)

## 3. Non-Goals

- Polish visual/UI detail — SENGAJA di luar scope, user kerjakan sendiri lewat agent lain.
- Trait resolve-anak seperti sisi Orang Tua — TIDAK relevan, siswa akses `$request->user()->siswa` langsung.
- Test IDOR lewat manipulasi parameter — TIDAK ADA permukaan itu di sisi siswa (tidak ada `siswa_id` di query string manapun).
- Perubahan pada `DashboardController` — TIDAK disentuh, widget dashboard existing tetap seperti sebelumnya (halaman baru ini MELENGKAPI, bukan menggantikan dashboard).

## 4. Test Plan

| # | Area | Skenario |
|---|---|---|
| 1 | `NilaiRaporSiswaController::index()` | Menampilkan nilai untuk semester default (terbaru), filter semester lain bekerja. |
| 2 | `NilaiRaporSiswaController::index()` — regresi identitas | 2 siswa (A dan B) sama-sama punya nilai tercatat — siswa A login, HANYA lihat nilai A, bukan campur dengan nilai B. |
| 3 | `NilaiRaporSiswaController::unduhRapor()` | `PengajuanRapor` siswa untuk semester itu `Disetujui` → unduh berhasil (response PDF). |
| 4 | `NilaiRaporSiswaController::unduhRapor()` | `PengajuanRapor` belum `Disetujui` → 404. |
| 5 | `JadwalPelajaranSiswaController::index()` | Jadwal 1 minggu penuh tampil lengkap (bukan cuma hari ini), dikelompokkan per hari, filter semester bekerja. |
| 6 | `JadwalPelajaranSiswaController::index()` — regresi identitas | 2 siswa beda kelas dengan jadwal berbeda — siswa A login, HANYA lihat jadwal kelasnya sendiri. |
| 7 | `PresensiSayaController::index()` | Riwayat presensi (SEMUA status, bukan cuma izin/sakit) dalam rentang tanggal default (bulan ini) tampil lengkap dengan konteks mapel — test ini SEKALIGUS pembuktian empiris apakah `withoutGlobalScope` diperlukan untuk `sesiPembelajaran` (lihat catatan §2.3) — kalau test ini gagal karena data tidak muncul padahal seharusnya ada, itu tandanya bypass diperlukan, tambahkan, JANGAN diamkan test yang gagal. |
| 8 | `PresensiSayaController::index()` — filter tanggal | Filter `dari_tanggal`/`sampai_tanggal` custom membatasi hasil sesuai rentang. |
| 9 | `PresensiSayaController::index()` — regresi identitas | 2 siswa dengan riwayat presensi berbeda — siswa A login, HANYA lihat presensinya sendiri. |
| 10 | Sidebar | Menu 3 item ini muncul untuk role `siswa`, TIDAK muncul untuk role lain (regresi). |
