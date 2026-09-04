# Spec: Fitur Ruang Orang Tua — Nilai Anak, Jadwal Anak, Riwayat Izin/Sakit Anak

**Tanggal**: 2026-09-04
**Branch**: `akademik-v2`
**Konteks**: 3 item menu "Ruang Orang Tua" (`Nilai Anak`, `Jadwal Anak`, `Riwayat Izin/Sakit Anak`) sengaja disembunyikan dari sidebar sejak 2026-09-03 (`resources/views/layouts/sidebar.blade.php:43-49`, dikomentari dengan catatan "halaman detailnya belum dibangun, datanya sudah ada ringkas di widget Dashboard, bangun sebagai proyek fitur terpisah"). Fondasi query untuk ketiganya sudah terbukti jalan di `DashboardController::index()` (blok `hasRole('orang_tua')`, baris 167-245) dalam bentuk ringkas (limit 5 / hari-ini saja). Modul Akademik baru saja diaudit 4 putaran di sesi ini (IDOR lintas-tenant, root-fix `TenantScope`, race condition, session-staleness) — fondasi keamanan/scoping data akademik sudah solid dan terverifikasi, tidak perlu diaudit ulang dari nol untuk spec ini.

**Scope eksplisit**: HANYA sisi Orang Tua (3 fitur di atas). Sisi Siswa (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya) sengaja DITUNDA, jadi paket terpisah menyusul.

## 1. Keputusan yang Sudah Final (dikonfirmasi lewat brainstorming, jangan tanya ulang)

- **Nilai Anak**: tabel nilai per mapel dengan filter semester, DITAMBAH fitur lihat/unduh Rapor PDF resmi (reuse `RaporPdfDataBuilder`) kalau `PengajuanRapor` semester itu berstatus `Disetujui`.
- **UX multi-anak**: selector pindah-anak (dropdown/tab), diterapkan KONSISTEN di ketiga halaman — bukan "tampilkan semua anak sekaligus".
- **Riwayat Izin/Sakit Anak**: MURNI read-only. TIDAK ada form pengajuan izin/sakit baru dari sisi orang tua di paket ini.
- **Test keamanan**: WAJIB 1 test eksplisit "orang tua A tidak bisa lihat data anak orang tua B" di SETIAP dari 3 fitur (bukan cukup 1 representatif).
- **Struktur**: 3 controller terpisah (`NilaiAnakController`, `JadwalAnakController`, `RiwayatIzinSakitAnakController`) di `app/Http/Controllers/Admin/` (BUKAN `Portal/` — namespace itu dipakai untuk portal pendaftaran/PPDB calon murid, konsep berbeda; siswa/orang tua yang sudah login tetap lewat `Admin/*` seperti `KasusController`/`DashboardController` yang sudah ada, dibedakan lewat pengecekan role di dalam controller).
- **Shared logic**: trait baru `ResolveAnakOrangTuaTrait` dipakai ketiga controller, supaya logic "resolve anak milik orang tua yang login + anak yang sedang dipilih" tidak diduplikasi 3x.
- **Routing**: file baru `routes/admin/orang-tua-akademik.php`, ditambahkan ke daftar `require` di `routes/admin.php` (pola sama seperti 11 file route admin lain yang sudah ada — otomatis dapat prefix URL `/admin`, prefix nama `admin.`, middleware `auth`+`verified` dari group pembungkus di `routes/admin.php`).
- **Verifikasi scoping `Presensi`**: model ini TIDAK pakai `BelongsToTenant` (beda dari `NilaiSiswa`). Setelah ditelusuri: ini BUKAN gap, karena `RiwayatIzinSakitAnakController` TIDAK PERNAH melakukan query terbuka lintas-tenant — batasan keamanannya adalah "siswa_id harus salah satu anak dari `resolveAnakList()`", dan `resolveAnakList()` sendiri sudah menjamin `$anakList` HANYA berisi anak yang benar-benar terhubung ke orang tua yang login lewat relasi pivot `siswa_orang_tua` (bukan hasil query yang bisa dipengaruhi input user). Query `Presensi::where('siswa_id', $anak->id)` karenanya aman oleh KONSTRUKSI, terlepas dari `Presensi` punya `TenantScope` atau tidak.

## 2. Desain Komponen

### 2.1. Trait `ResolveAnakOrangTuaTrait`

**File baru**: `app/Domains/Akademik/Support/ResolveAnakOrangTuaTrait.php`

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Support;

use App\Models\Scopes\TenantScope;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Collection;

trait ResolveAnakOrangTuaTrait
{
    /**
     * @return Collection<int, Siswa>
     */
    private function resolveAnakList(User $actor): Collection
    {
        $orangTua = $actor->orangTua;
        if ($orangTua === null) {
            return collect();
        }

        return $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->with('kelas')->get();
    }

    /**
     * @param  Collection<int, Siswa>  $anakList
     */
    private function resolveAnakTerpilih(Collection $anakList, ?int $siswaIdDiminta): ?Siswa
    {
        if ($anakList->isEmpty()) {
            return null;
        }

        if ($siswaIdDiminta !== null) {
            $anak = $anakList->firstWhere('id', $siswaIdDiminta);
            if ($anak !== null) {
                return $anak;
            }
        }

        return $anakList->first();
    }
}
```

**Kenapa `resolveAnakTerpilih()` diam-diam fallback, bukan `abort(403)`**: pola "derive, don't validate" yang sama seperti `ResolveLembagaScopeTrait` — kalau `siswa_id` di query string menunjuk ke anak ORANG TUA LAIN (baik salah ketik atau percobaan IDOR), sistem tidak perlu membedakan keduanya; cukup abaikan nilai itu dan tampilkan anak pertama milik actor sendiri. Ini otomatis menutup celah IDOR di titik SATU tempat untuk ketiga controller, tanpa perlu `abort_if` terpisah di masing-masing.

### 2.2. `NilaiAnakController`

**File baru**: `app/Http/Controllers/Admin/NilaiAnakController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Akademik\Services\RaporPdfDataBuilder;
use App\Domains\Akademik\Support\ResolveAnakOrangTuaTrait;
use App\Models\Scopes\TenantScope;
use App\Models\Semester;
use App\Models\Siswa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NilaiAnakController extends BaseController
{
    use ResolveAnakOrangTuaTrait;

    public function __construct(
        private readonly RaporPdfDataBuilder $raporPdfDataBuilder,
    ) {}

    public function index(Request $request): View
    {
        $anakList = $this->resolveAnakList($request->user());
        $anak = $this->resolveAnakTerpilih($anakList, $request->integer('siswa_id') ?: null);

        $semesterList = $anak && $anak->kelas
            ? Semester::where('tahun_ajaran_id', $anak->kelas->tahun_ajaran_id)->orderByDesc('id')->get()
            : collect();
        $semesterId = $request->integer('semester_id') ?: $semesterList->first()?->id;

        $nilaiList = ($anak && $semesterId)
            ? NilaiSiswa::withoutGlobalScope(TenantScope::class)
                ->where('siswa_id', $anak->id)
                ->whereNotNull('nilai_angka')
                ->whereHas('asesmen', fn ($q) => $q->withoutGlobalScope(TenantScope::class)
                    ->where('semester_id', $semesterId)
                    ->whereIn('jenis', JenisAsesmen::masukRapor()))
                ->with([
                    'komponenPenilaian' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with(['subjek' => fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)]),
                    'asesmen' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with(['subjek' => fn ($q2) => $q2->withoutGlobalScope(TenantScope::class)]),
                ])
                ->get()
            : collect();

        $pengajuanRapor = ($anak && $semesterId)
            ? PengajuanRapor::withoutGlobalScope(TenantScope::class)
                ->where('kelas_id', $anak->kelas_id)
                ->where('semester_id', $semesterId)
                ->where('status', StatusPengajuanRapor::Disetujui)
                ->first()
            : null;

        return view('admin.orang-tua.nilai-anak', [
            'anakList' => $anakList,
            'anak' => $anak,
            'semesterList' => $semesterList,
            'semesterId' => $semesterId,
            'nilaiList' => $nilaiList,
            'pengajuanRapor' => $pengajuanRapor,
        ]);
    }

    public function unduhRapor(Request $request, Siswa $siswa): Response
    {
        $anakList = $this->resolveAnakList($request->user());
        abort_unless($anakList->contains('id', $siswa->id), 403);

        $semester = Semester::withoutGlobalScope(TenantScope::class)->find((int) $request->query('semester_id'));
        abort_if($semester === null, 404);

        $pengajuanRapor = PengajuanRapor::withoutGlobalScope(TenantScope::class)
            ->where('kelas_id', $siswa->kelas_id)
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

**Catatan otorisasi `unduhRapor()`**: berbeda dari `Guru\RaporController::cetak()` yang membolehkan wali kelas cetak rapor draft (belum disetujui) untuk keperluan koreksi, di sini WAJIB status `Disetujui` — orang tua tidak boleh melihat rapor yang masih dalam proses. `Siswa $siswa` route-model-binding TIDAK dipakai sebagai satu-satunya penjaga (karena `Siswa` sendiri tenant-scoped, bukan orang-tua-scoped) — verifikasi eksplisit `$anakList->contains('id', $siswa->id)` WAJIB ada sebelum apa pun diproses, INI beda dari pola `resolveAnakTerpilih()` yang "diam-diam fallback" — di endpoint unduh file, permintaan untuk anak yang BUKAN miliknya harus `403` tegas (bukan diam-diam ganti ke anak lain), supaya tidak ada ambiguitas soal file PDF siapa yang ke-download.

**Kenapa TIDAK ada `$this->authorize()`/permission check di `index()` ketiga controller**: mengikuti pola `DashboardController` yang sudah ada — akses ke data "anak saya sendiri" tidak digerbang lewat permission Spatie (`nilai-siswa.view` dkk itu untuk konteks admin/guru melihat data LEMBAGA, beda konteks dari orang tua melihat anak sendiri). Gerbangnya cukup 2 lapis: (1) middleware `auth` di `routes/admin.php` — harus login; (2) `resolveAnakList()` mengembalikan collection KOSONG kalau `$actor->orangTua` null — user yang bukan orang tua otomatis tidak melihat data apa pun (bukan error, cukup halaman kosong), konsisten dengan filosofi "derive, don't validate" yang sama dipakai di §2.1. JANGAN tambahkan Policy/permission check baru di sini kecuali ditemukan alasan konkret saat implementasi (misalnya kalau ternyata `NilaiSiswa`/`JadwalPelajaran`/`Presensi` punya global scope tersembunyi yang butuh permission spesifik — verifikasi dulu, jangan asumsi perlu).

### 2.3. `JadwalAnakController`

**File baru**: `app/Http/Controllers/Admin/JadwalAnakController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Support\ResolveAnakOrangTuaTrait;
use App\Models\JadwalPelajaran;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class JadwalAnakController extends BaseController
{
    use ResolveAnakOrangTuaTrait;

    public function index(Request $request): View
    {
        $anakList = $this->resolveAnakList($request->user());
        $anak = $this->resolveAnakTerpilih($anakList, $request->integer('siswa_id') ?: null);

        $jadwalList = ($anak && $anak->kelas_id !== null)
            ? JadwalPelajaran::withoutGlobalScope(TenantScope::class)
                ->where('kelas_id', $anak->kelas_id)
                ->semesterAktif()
                ->with([
                    'jamPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                    'mataPelajaran' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                    'guru' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->with('person'),
                ])
                ->get()
                ->groupBy(fn (JadwalPelajaran $jadwal) => $jadwal->jamPelajaran->hari->value)
            : collect();

        return view('admin.orang-tua.jadwal-anak', [
            'anakList' => $anakList,
            'anak' => $anak,
            'jadwalList' => $jadwalList,
        ]);
    }
}
```

**Catatan**: `groupBy(...hari->value)` mengelompokkan jadwal per hari untuk render tabel mingguan — cek dulu view existing (`portals.lembaga.akademik.jadwal-pelajaran.index` atau serupa) untuk pola tampilan tabel/grid per hari yang SUDAH ADA, ikuti strukturnya alih-alih membuat pola baru.

### 2.4. `RiwayatIzinSakitAnakController`

**File baru**: `app/Http/Controllers/Admin/RiwayatIzinSakitAnakController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Support\ResolveAnakOrangTuaTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class RiwayatIzinSakitAnakController extends BaseController
{
    use ResolveAnakOrangTuaTrait;

    public function index(Request $request): View
    {
        $anakList = $this->resolveAnakList($request->user());
        $anak = $this->resolveAnakTerpilih($anakList, $request->integer('siswa_id') ?: null);

        $dariTanggal = $request->date('dari_tanggal') ?: now()->startOfMonth();
        $sampaiTanggal = $request->date('sampai_tanggal') ?: now()->endOfMonth();

        $riwayatList = $anak
            ? Presensi::where('siswa_id', $anak->id)
                ->whereIn('status', ['izin', 'sakit'])
                ->whereHas('sesiPembelajaran', fn ($q) => $q->whereBetween('tanggal', [$dariTanggal, $sampaiTanggal]))
                ->with(['sesiPembelajaran.mataPelajaran'])
                ->latest('id')
                ->get()
            : collect();

        return view('admin.orang-tua.riwayat-izin-sakit-anak', [
            'anakList' => $anakList,
            'anak' => $anak,
            'dariTanggal' => $dariTanggal,
            'sampaiTanggal' => $sampaiTanggal,
            'riwayatList' => $riwayatList,
        ]);
    }
}
```

**Catatan tipe `dari_tanggal`/`sampai_tanggal`**: `$request->date()` mengembalikan `Carbon` langsung dari query string (format `Y-m-d`) — validasi/normalisasi format eksplisit lewat `$request->validate([...])` sebaiknya ditambahkan saat implementasi (bukan ditulis lengkap di sini) supaya pesan error jelas kalau user mengirim format tanggal salah, ikuti pola `Illuminate\Validation` yang sudah dipakai controller lain.

### 2.5. Routes

**File baru**: `routes/admin/orang-tua-akademik.php`

```php
<?php

use App\Http\Controllers\Admin\JadwalAnakController;
use App\Http\Controllers\Admin\NilaiAnakController;
use App\Http\Controllers\Admin\RiwayatIzinSakitAnakController;
use Illuminate\Support\Facades\Route;

Route::get('nilai-anak', [NilaiAnakController::class, 'index'])->name('nilai-anak.index');
Route::get('nilai-anak/{siswa}/unduh-rapor', [NilaiAnakController::class, 'unduhRapor'])->name('nilai-anak.unduh-rapor');
Route::get('jadwal-anak', [JadwalAnakController::class, 'index'])->name('jadwal-anak.index');
Route::get('riwayat-izin-sakit-anak', [RiwayatIzinSakitAnakController::class, 'index'])->name('riwayat-izin-sakit-anak.index');
```

**Registrasi**: tambahkan `require base_path('routes/admin/orang-tua-akademik.php');` ke `routes/admin.php` (setelah baris `require base_path('routes/admin/kasus-admin.php');`, mengikuti urutan yang sudah ada — tidak signifikan tapi menjaga keterbacaan).

### 2.6. Sidebar

Buka kembali komentar di `resources/views/layouts/sidebar.blade.php:43-49` (3 baris untuk "Nilai Anak", "Jadwal Anak", "Riwayat Izin/Sakit Anak"), ganti target `route` dari `'dalam-pengembangan'` + `params` ke route baru masing-masing:
```php
Auth::user()->orangTua !== null ? ['route' => 'admin.nilai-anak.index', 'pattern' => 'admin.nilai-anak.*', 'label' => 'Nilai & Rapor Anak', 'icon' => 'award'] : null,
Auth::user()->orangTua !== null ? ['route' => 'admin.jadwal-anak.index', 'pattern' => 'admin.jadwal-anak.*', 'label' => 'Jadwal Anak', 'icon' => 'calendar-clock'] : null,
Auth::user()->orangTua !== null ? ['route' => 'admin.riwayat-izin-sakit-anak.index', 'pattern' => 'admin.riwayat-izin-sakit-anak.*', 'label' => 'Riwayat Izin/Sakit Anak', 'icon' => 'clipboard-check'] : null,
```
(Label "Nilai Anak" diubah jadi "Nilai & Rapor Anak" supaya mencerminkan fitur unduh rapor yang ikut ditambahkan — konsisten dengan penamaan "Nilai & Rapor" di sisi siswa yang disebut di menu asli.)

## 3. Non-Goals

- Sisi Siswa (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya) — TIDAK masuk paket ini, menyusul terpisah.
- Form pengajuan izin/sakit baru dari orang tua — TIDAK dibangun, `RiwayatIzinSakitAnakController` murni read-only.
- Perubahan pada `Presensi` model (menambah `BelongsToTenant`) — TIDAK diperlukan untuk paket ini (lihat §1, "Verifikasi scoping Presensi").
- View "Bottom Nav" untuk fitur ini — tidak dibahas di spec ini; kalau ada pola bottom-nav existing untuk Orang Tua (`resources/views/layouts/bottom-nav.blade.php`, disebut ada di riwayat rbac-v2), cek keberadaannya saat implementasi dan tambahkan entri yang setara HANYA kalau pola serupa (3 item Keuangan Saya) sudah ada di situ untuk orang tua — kalau tidak ada presedennya, cukup sidebar saja.

## 4. Test Plan

| # | Area | Skenario |
|---|---|---|
| 1 | `NilaiAnakController::index()` | Orang tua A dengan anak yang punya nilai → tabel nilai tampil sesuai semester default (semester terbaru), filter semester lain bekerja. |
| 2 | `NilaiAnakController::index()` — IDOR | Orang tua A mengirim `?siswa_id=<anak orang tua B>` → data yang tampil TETAP anak A sendiri (anak pertama A), BUKAN data anak B, BUKAN error. |
| 3 | `NilaiAnakController::unduhRapor()` | `PengajuanRapor` anak A untuk semester tsb berstatus `Disetujui` → unduh berhasil (response PDF). |
| 4 | `NilaiAnakController::unduhRapor()` | `PengajuanRapor` anak A untuk semester tsb BELUM `Disetujui` (draft/diajukan) → 404, bukan PDF kosong/error 500. |
| 5 | `NilaiAnakController::unduhRapor()` — IDOR | Orang tua A mencoba akses `/nilai-anak/{siswa milik orang tua B}/unduh-rapor` → 403 tegas (BUKAN fallback diam-diam, beda dari test #2). |
| 6 | `JadwalAnakController::index()` | Jadwal 1 minggu penuh anak A tampil lengkap (bukan cuma hari ini), dikelompokkan per hari. |
| 7 | `JadwalAnakController::index()` — IDOR | Orang tua A dengan `?siswa_id=<anak orang tua B>` → jadwal yang tampil tetap anak A sendiri. |
| 8 | `RiwayatIzinSakitAnakController::index()` | Riwayat izin/sakit anak A dalam rentang tanggal default (bulan ini) tampil lengkap dengan konteks mapel dari `sesiPembelajaran`. |
| 9 | `RiwayatIzinSakitAnakController::index()` — IDOR | Orang tua A dengan `?siswa_id=<anak orang tua B>` → riwayat yang tampil tetap anak A sendiri. |
| 10 | `RiwayatIzinSakitAnakController::index()` — filter tanggal | Filter `dari_tanggal`/`sampai_tanggal` custom membatasi hasil sesuai rentang, tidak menampilkan riwayat di luar rentang. |
| 11 | `ResolveAnakOrangTuaTrait::resolveAnakList()` | User yang login TAPI `$user->orangTua` null (bukan orang tua sungguhan, atau akun rusak) → return collection kosong, tidak error/exception. |
| 12 | Sidebar | Menu 3 item ini muncul untuk role `orang_tua`, TIDAK muncul untuk role lain (regresi — pastikan kondisi `Auth::user()->orangTua !== null` tidak longgar ke role lain). |
