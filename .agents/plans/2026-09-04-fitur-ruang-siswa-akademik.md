# Fitur Ruang Siswa Akademik Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun 3 halaman self-service Ruang Siswa (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya) yang saat ini sengaja disembunyikan, menggantikan placeholder `/dalam-pengembangan`.

**Architecture:** 3 controller independen (TIDAK ADA trait bersama — siswa akses `$request->user()->siswa` langsung, satu identitas, tidak perlu resolve-anak seperti paket Orang Tua) + 3 view minimal + 1 file route + pembaruan sidebar.

**Tech Stack:** Laravel 12, PHP 8.3, Pest, Blade (markup PALING DASAR — UI akan dipoles user sendiri lewat agent lain, JANGAN habiskan waktu untuk styling).

## Global Constraints

- TIDAK ADA trait resolve-anak — setiap controller pakai `$request->user()->siswa` langsung (relasi `User::siswa(): HasOneThrough` SUDAH membungkus `withoutGlobalScope(TenantScope::class)` di level definisi, jadi akses ini otomatis aman dari isu scope).
- `NilaiRaporSiswaController::unduhRapor()` TIDAK menerima parameter route apa pun — `$siswa` SELALU `$request->user()->siswa`. TIDAK ADA celah IDOR untuk ditutup (beda dari paket Orang Tua yang perlu `abort_unless(anakList->contains(...))`).
- WAJIB ikuti PERSIS pola `withoutGlobalScope(TenantScope::class)` per-model dari kode di spec (JadwalPelajaran: PAKAI; NilaiSiswa/PengajuanRapor/Presensi: TIDAK PAKAI) — JANGAN diseragamkan sendiri.
- UI WAJIB minimal/fungsional — `<x-app-layout>` + markup HTML/Tailwind paling dasar (`<table>`, `<select onchange="this.form.submit()">`), TIDAK PERLU komponen `<x-panel>`/`<x-badge>`/token warna khusus seperti paket Orang Tua. Prioritaskan data ter-render benar dan bisa di-`assertSee()`, BUKAN visual rapi.
- Route BARU sama sekali (`admin.nilai-rapor-saya.index`, `admin.jadwal-pelajaran-saya.index`, `admin.presensi-saya.index`) — JANGAN menimpa/memakai ulang route existing (`admin.jadwal-pelajaran.index` dkk, itu punya admin/guru untuk KELOLA jadwal, beda konteks).
- Tidak pindah branch, tetap di `akademik-v2`.

---

## Task 1: `NilaiRaporSiswaController` — Nilai & Rapor

**Files:**
- Create: `app/Http/Controllers/Admin/NilaiRaporSiswaController.php`
- Create: `resources/views/admin/siswa-akademik/nilai-rapor.blade.php`
- Create: `routes/admin/siswa-akademik.php`
- Test: `tests/Feature/Admin/NilaiRaporSiswaControllerTest.php`

**Interfaces:** Tidak ada — berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal**

Baca `tests/Feature/Admin/NilaiAnakControllerTest.php` (paket Orang Tua, sudah selesai & lolos review) untuk pola factory `Asesmen`/`KomponenPenilaian`/`PengajuanRapor` yang SUDAH terbukti benar — `Asesmen` dan `KomponenPenilaian` adalah entitas TERPISAH dan SEJAJAR (masing-masing `subjek_type`/`subjek_id` sendiri, BUKAN nested), `NilaiSiswa` punya `asesmen_id` DAN `komponen_penilaian_id` sebagai 2 FK independen. `JenisAsesmen` valid case: `SumatifLingkupMateri`/`SumatifAkhirSemester`/`SumatifAkhirJenjang`/dst — BUKAN `Sumatif`. `Siswa::factory()->create(['user_id' => $user->id, ...])` (baca `database/factories/SiswaFactory.php` — pola SAMA seperti `OrangTuaFactory`, `user_id` dibaca sebagai override untuk link `Person`, BUKAN kolom asli — kalau `user_id` tidak diisi, Person dibuat TANPA link ke User manapun).

```php
<?php

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;

function buatSiswaDenganAkun(Lembaga $lembaga, Kelas $kelas): array
{
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return [$user->fresh(), $siswa];
}

it('menampilkan nilai untuk semester yang dipilih', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    [$user, $siswa] = buatSiswaDenganAkun($lembaga, $kelas);

    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'jenis' => JenisAsesmen::SumatifLingkupMateri]);
    NilaiSiswa::factory()->create(['siswa_id' => $siswa->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 92]);

    $response = $this->actingAs($user)->get(route('admin.nilai-rapor-saya.index', ['semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('nilaiList', fn ($list) => $list->contains(fn ($n) => $n->nilai_angka === 92));
});

it('regresi identitas: siswa A hanya melihat nilainya sendiri, bukan campur dengan siswa B', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    [$userA, $siswaA] = buatSiswaDenganAkun($lembaga, $kelas);
    [$userB, $siswaB] = buatSiswaDenganAkun($lembaga, $kelas);

    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'jenis' => JenisAsesmen::SumatifLingkupMateri]);
    NilaiSiswa::factory()->create(['siswa_id' => $siswaA->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 70]);
    NilaiSiswa::factory()->create(['siswa_id' => $siswaB->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 95]);

    $response = $this->actingAs($userA)->get(route('admin.nilai-rapor-saya.index', ['semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('nilaiList', fn ($list) => $list->count() === 1 && $list->first()->nilai_angka === 70);
});

it('mengizinkan unduh rapor kalau PengajuanRapor sudah Disetujui', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $siswa] = buatSiswaDenganAkun($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Disetujui]);

    $response = $this->actingAs($user)->get(route('admin.nilai-rapor-saya.unduh-rapor', ['semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('menolak unduh rapor kalau PengajuanRapor belum Disetujui', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $siswa] = buatSiswaDenganAkun($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Diajukan]);

    $response = $this->actingAs($user)->get(route('admin.nilai-rapor-saya.unduh-rapor', ['semester_id' => $semester->id]));

    $response->assertStatus(404);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=NilaiRaporSiswaControllerTest`
Expected: FAIL — route/controller belum ada.

- [ ] **Step 3: Buat file route**

Buat `routes/admin/siswa-akademik.php`:
```php
<?php

use App\Http\Controllers\Admin\NilaiRaporSiswaController;
use Illuminate\Support\Facades\Route;

Route::get('nilai-rapor-saya', [NilaiRaporSiswaController::class, 'index'])->name('nilai-rapor-saya.index');
Route::get('nilai-rapor-saya/unduh-rapor', [NilaiRaporSiswaController::class, 'unduhRapor'])->name('nilai-rapor-saya.unduh-rapor');
```
Tambahkan `require base_path('routes/admin/siswa-akademik.php');` ke `routes/admin.php` (baca dulu isinya — kalau baris `require base_path('routes/admin/orang-tua-akademik.php');` sudah ada dari paket sebelumnya, taruh setelah itu; kalau belum ada, taruh setelah `require base_path('routes/admin/kasus-admin.php');`).

- [ ] **Step 4: Buat `NilaiRaporSiswaController`**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Akademik\Enums\JenisAsesmen;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Akademik\Services\RaporPdfDataBuilder;
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

- [ ] **Step 5: Buat view minimal**

```blade
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4 pt-2">
        <h1 class="text-xl font-bold">Nilai & Rapor</h1>

        <form method="GET">
            <label class="text-sm font-medium">Semester</label>
            <select name="semester_id" onchange="this.form.submit()" class="block rounded border-gray-300 text-sm">
                @foreach ($semesterList as $semesterOpsi)
                    <option value="{{ $semesterOpsi->id }}" @selected($semesterId == $semesterOpsi->id)>{{ $semesterOpsi->nama }}</option>
                @endforeach
            </select>
        </form>

        @if ($pengajuanRapor)
            <a href="{{ route('admin.nilai-rapor-saya.unduh-rapor', ['semester_id' => $semesterId]) }}" target="_blank" class="inline-block rounded bg-blue-600 px-3 py-2 text-sm text-white">Unduh Rapor</a>
        @endif

        <table class="w-full border text-sm">
            <thead>
                <tr><th class="border p-2 text-left">Mata Pelajaran</th><th class="border p-2 text-left">Nilai</th></tr>
            </thead>
            <tbody>
                @forelse ($nilaiList as $nilai)
                    <tr>
                        <td class="border p-2">{{ $nilai->komponenPenilaian?->subjek?->nama ?? $nilai->asesmen?->subjek?->nama ?? '-' }}</td>
                        <td class="border p-2">{{ $nilai->nilai_angka }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="border p-2 text-center text-gray-500">Belum ada nilai untuk semester ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=NilaiRaporSiswaControllerTest`
Expected: PASS.

- [ ] **Step 7: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/NilaiRaporSiswaController.php resources/views/admin/siswa-akademik/nilai-rapor.blade.php routes/admin/siswa-akademik.php routes/admin.php tests/Feature/Admin/NilaiRaporSiswaControllerTest.php
git commit -m "feat(akademik): halaman Nilai & Rapor untuk Ruang Siswa"
```

---

## Task 2: `JadwalPelajaranSiswaController` — Jadwal Pelajaran

**Files:**
- Create: `app/Http/Controllers/Admin/JadwalPelajaranSiswaController.php`
- Create: `resources/views/admin/siswa-akademik/jadwal-pelajaran.blade.php`
- Modify: `routes/admin/siswa-akademik.php`
- Test: `tests/Feature/Admin/JadwalPelajaranSiswaControllerTest.php`

**Interfaces:** Tidak ada — berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;

function buatSiswaDenganAkunDanJadwal(Lembaga $lembaga, Kelas $kelas): array
{
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return [$user->fresh(), $siswa];
}

it('menampilkan jadwal 1 minggu penuh untuk semester yang dipilih', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamSenin = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true, 'hari' => 'senin']);
    $jamSelasa = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true, 'hari' => 'selasa']);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'mata_pelajaran_id' => $mapel->id, 'jam_pelajaran_id' => $jamSenin->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'mata_pelajaran_id' => $mapel->id, 'jam_pelajaran_id' => $jamSelasa->id, 'semester_id' => $semester->id]);
    [$user, $siswa] = buatSiswaDenganAkunDanJadwal($lembaga, $kelas);

    $response = $this->actingAs($user)->get(route('admin.jadwal-pelajaran-saya.index', ['semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('jadwalList', fn ($list) => $list->flatten()->count() === 2 && $list->keys()->sort()->values()->all() === ['selasa', 'senin']);
});

it('regresi identitas: siswa A hanya melihat jadwal kelasnya sendiri, bukan kelas siswa B', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $polaA = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $polaB = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasA = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaA->id]);
    $kelasB = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $polaB->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jamA = JamPelajaran::factory()->create(['pola_jam_id' => $polaA->id, 'is_pelajaran' => true, 'hari' => 'senin']);
    $jamB = JamPelajaran::factory()->create(['pola_jam_id' => $polaB->id, 'is_pelajaran' => true, 'hari' => 'senin']);
    JadwalPelajaran::create(['kelas_id' => $kelasA->id, 'guru_id' => $guru->id, 'mata_pelajaran_id' => $mapel->id, 'jam_pelajaran_id' => $jamA->id, 'semester_id' => $semester->id]);
    JadwalPelajaran::create(['kelas_id' => $kelasB->id, 'guru_id' => $guru->id, 'mata_pelajaran_id' => $mapel->id, 'jam_pelajaran_id' => $jamB->id, 'semester_id' => $semester->id]);
    [$userA, $siswaA] = buatSiswaDenganAkunDanJadwal($lembaga, $kelasA);
    [$userB, $siswaB] = buatSiswaDenganAkunDanJadwal($lembaga, $kelasB);

    $response = $this->actingAs($userA)->get(route('admin.jadwal-pelajaran-saya.index', ['semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('jadwalList', fn ($list) => $list->flatten()->count() === 1 && $list->flatten()->first()->kelas_id === $kelasA->id);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=JadwalPelajaranSiswaControllerTest`
Expected: FAIL.

- [ ] **Step 3: Tambah route**

Tambahkan ke `routes/admin/siswa-akademik.php`:
```php
use App\Http\Controllers\Admin\JadwalPelajaranSiswaController;
```
dan:
```php
Route::get('jadwal-pelajaran-saya', [JadwalPelajaranSiswaController::class, 'index'])->name('jadwal-pelajaran-saya.index');
```

- [ ] **Step 4: Buat `JadwalPelajaranSiswaController`**

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

- [ ] **Step 5: Buat view minimal**

```blade
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4 pt-2">
        <h1 class="text-xl font-bold">Jadwal Pelajaran</h1>

        <form method="GET">
            <label class="text-sm font-medium">Semester</label>
            <select name="semester_id" onchange="this.form.submit()" class="block rounded border-gray-300 text-sm">
                @foreach ($semesterList as $semesterOpsi)
                    <option value="{{ $semesterOpsi->id }}" @selected($semesterId == $semesterOpsi->id)>{{ $semesterOpsi->nama }}</option>
                @endforeach
            </select>
        </form>

        @forelse ($jadwalList as $hari => $jadwalHari)
            <div>
                <h3 class="font-semibold text-sm uppercase">{{ $hari }}</h3>
                <table class="w-full border text-sm">
                    <tbody>
                        @foreach ($jadwalHari as $jadwal)
                            <tr>
                                <td class="border p-2">{{ $jadwal->jamPelajaran?->jam_mulai }} - {{ $jadwal->jamPelajaran?->jam_selesai }}</td>
                                <td class="border p-2">{{ $jadwal->mataPelajaran?->nama ?? 'Tematik' }}</td>
                                <td class="border p-2">{{ $jadwal->guru?->nama }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="text-sm text-gray-500">Belum ada jadwal pelajaran untuk semester ini.</p>
        @endforelse
    </div>
</x-app-layout>
```

- [ ] **Step 6: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=JadwalPelajaranSiswaControllerTest`
Expected: PASS.

- [ ] **Step 7: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/JadwalPelajaranSiswaController.php resources/views/admin/siswa-akademik/jadwal-pelajaran.blade.php routes/admin/siswa-akademik.php tests/Feature/Admin/JadwalPelajaranSiswaControllerTest.php
git commit -m "feat(akademik): halaman Jadwal Pelajaran untuk Ruang Siswa"
```

---

## Task 3: `PresensiSayaController` — Presensi Saya

**Files:**
- Create: `app/Http/Controllers/Admin/PresensiSayaController.php`
- Create: `resources/views/admin/siswa-akademik/presensi-saya.blade.php`
- Modify: `routes/admin/siswa-akademik.php`
- Test: `tests/Feature/Admin/PresensiSayaControllerTest.php`

**Interfaces:** Tidak ada — berdiri sendiri.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;

function buatSiswaDenganAkunDanPresensi(Lembaga $lembaga, Kelas $kelas): array
{
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['user_id' => $user->id, 'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    return [$user->fresh(), $siswa];
}

it('menampilkan riwayat presensi (semua status) dalam rentang tanggal default (bulan ini)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $siswa] = buatSiswaDenganAkunDanPresensi($lembaga, $kelas);
    $sesiHadir = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->startOfMonth()->addDays(1)]);
    $sesiSakit = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->startOfMonth()->addDays(2)]);
    Presensi::factory()->create(['siswa_id' => $siswa->id, 'sesi_pembelajaran_id' => $sesiHadir->id, 'status' => 'hadir']);
    Presensi::factory()->create(['siswa_id' => $siswa->id, 'sesi_pembelajaran_id' => $sesiSakit->id, 'status' => 'sakit', 'keterangan' => 'Demam']);

    $response = $this->actingAs($user)->get(route('admin.presensi-saya.index'));

    $response->assertOk();
    // Kalau ini gagal (list kosong padahal harus ada 2), berarti whereHas('sesiPembelajaran', ...) BUTUH
    // withoutGlobalScope(TenantScope::class) juga -- tambahkan di controller, JANGAN ubah assertion ini.
    $response->assertViewHas('riwayatList', fn ($list) => $list->count() === 2);
});

it('tidak menampilkan riwayat di luar rentang tanggal filter', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $siswa] = buatSiswaDenganAkunDanPresensi($lembaga, $kelas);
    $sesiLampau = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->subMonths(3)]);
    Presensi::factory()->create(['siswa_id' => $siswa->id, 'sesi_pembelajaran_id' => $sesiLampau->id, 'status' => 'izin']);

    $response = $this->actingAs($user)->get(route('admin.presensi-saya.index'));

    $response->assertOk();
    $response->assertViewHas('riwayatList', fn ($list) => $list->count() === 0);
});

it('regresi identitas: siswa A hanya melihat presensinya sendiri, bukan milik siswa B', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$userA, $siswaA] = buatSiswaDenganAkunDanPresensi($lembaga, $kelas);
    [$userB, $siswaB] = buatSiswaDenganAkunDanPresensi($lembaga, $kelas);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->startOfMonth()->addDays(1)]);
    Presensi::factory()->create(['siswa_id' => $siswaA->id, 'sesi_pembelajaran_id' => $sesi->id, 'status' => 'hadir']);
    Presensi::factory()->create(['siswa_id' => $siswaB->id, 'sesi_pembelajaran_id' => $sesi->id, 'status' => 'sakit']);

    $response = $this->actingAs($userA)->get(route('admin.presensi-saya.index'));

    $response->assertOk();
    $response->assertViewHas('riwayatList', fn ($list) => $list->count() === 1 && $list->first()->siswa_id === $siswaA->id);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=PresensiSayaControllerTest`
Expected: FAIL (belum ada route/controller). Perhatikan HASIL test pertama setelah controller dibuat di Step 4 — kalau ternyata `riwayatList` kosong padahal seharusnya ada 2, itu bukti `withoutGlobalScope(TenantScope::class)` diperlukan untuk `sesiPembelajaran` (lihat catatan di Step 4).

- [ ] **Step 3: Tambah route**

Tambahkan ke `routes/admin/siswa-akademik.php`:
```php
use App\Http\Controllers\Admin\PresensiSayaController;
```
dan:
```php
Route::get('presensi-saya', [PresensiSayaController::class, 'index'])->name('presensi-saya.index');
```

- [ ] **Step 4: Buat `PresensiSayaController`**

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

**PENTING — jalankan Step 2 (test gagal) dan Step 6 (test lolos) dengan teliti**: kode di atas SENGAJA TIDAK memakai `withoutGlobalScope(TenantScope::class)` pada `whereHas('sesiPembelajaran', ...)`/`with('sesiPembelajaran...')`, meniru pola dashboard existing. KALAU test "menampilkan riwayat presensi..." di Step 6 GAGAL (riwayat kosong padahal seharusnya ada 2 baris), itu BUKTI EMPIRIS bahwa bypass diperlukan di sini (beda dari asumsi awal) — kalau itu terjadi, tambahkan `withoutGlobalScope(TenantScope::class)` ke KEDUA closure (`whereHas` dan `with`) lalu jalankan ulang test sampai lolos. JANGAN mengubah assertion test untuk memaksa lolos — assertion-nya sudah benar (`count() === 2`), yang boleh diubah cuma kode controller.

- [ ] **Step 5: Buat view minimal**

```blade
<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-4 pt-2">
        <h1 class="text-xl font-bold">Presensi Saya</h1>

        <form method="GET" class="flex gap-3">
            <div>
                <label class="text-sm font-medium">Dari Tanggal</label>
                <input type="date" name="dari_tanggal" value="{{ $dariTanggal->toDateString() }}" onchange="this.form.submit()" class="block rounded border-gray-300 text-sm">
            </div>
            <div>
                <label class="text-sm font-medium">Sampai Tanggal</label>
                <input type="date" name="sampai_tanggal" value="{{ $sampaiTanggal->toDateString() }}" onchange="this.form.submit()" class="block rounded border-gray-300 text-sm">
            </div>
        </form>

        <table class="w-full border text-sm">
            <thead>
                <tr><th class="border p-2 text-left">Tanggal</th><th class="border p-2 text-left">Mata Pelajaran</th><th class="border p-2 text-left">Status</th><th class="border p-2 text-left">Keterangan</th></tr>
            </thead>
            <tbody>
                @forelse ($riwayatList as $presensi)
                    <tr>
                        <td class="border p-2">{{ $presensi->sesiPembelajaran?->tanggal?->translatedFormat('d F Y') }}</td>
                        <td class="border p-2">{{ $presensi->sesiPembelajaran?->mataPelajaran?->nama ?? 'Tematik' }}</td>
                        <td class="border p-2">{{ $presensi->status->label() }}</td>
                        <td class="border p-2">{{ $presensi->keterangan ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="border p-2 text-center text-gray-500">Tidak ada riwayat presensi pada rentang tanggal ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=PresensiSayaControllerTest`
Expected: PASS. (Lihat catatan penting di Step 4 kalau test pertama gagal.)

- [ ] **Step 7: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/PresensiSayaController.php resources/views/admin/siswa-akademik/presensi-saya.blade.php routes/admin/siswa-akademik.php tests/Feature/Admin/PresensiSayaControllerTest.php
git commit -m "feat(akademik): halaman Presensi Saya untuk Ruang Siswa"
```

---

## Task 4: Buka Kembali Menu Sidebar + Full Test Suite Final

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: regresi penuh (tidak ada file test baru wajib, kecuali test sidebar)

**Interfaces:** Tidak ada — task penutup.

- [ ] **Step 1: Tulis test yang gagal — sidebar**

Baca `tests/Feature/SidebarStubMenuHiddenTest.php`/`tests/Feature/SidebarPengelompokanTest.php` (sudah disesuaikan di paket Orang Tua untuk grup Ruang Orang Tua — pola acuan PERSIS untuk grup Ruang Siswa). Tambahkan test setara untuk grup Ruang Siswa (kemungkinan di file yang SAMA, cek dulu apakah ada test existing untuk grup Ruang Siswa yang perlu disesuaikan seperti pola Orang Tua, atau perlu ditambah baru):
```php
it('shows Ruang Siswa group with real routes, dalam-pengembangan stub links hidden', function () {
    // Setup actor role siswa -- ikuti pola actor existing di file test sidebar ini.
    // preg_match('/<nav.*?<\/nav>/s', ...) untuk isolasi markup <nav>.
    // Assert HTML mengandung route('admin.nilai-rapor-saya.index')/jadwal-pelajaran-saya.index/presensi-saya.index,
    // dan TIDAK mengandung 'dalam-pengembangan?fitur=nilai-rapor'/'jadwal-pelajaran'/'presensi-saya'.
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="shows Ruang Siswa group"`
Expected: FAIL — menu masih mengarah ke `dalam-pengembangan`.

- [ ] **Step 3: Buka kembali komentar di sidebar**

Baca `resources/views/layouts/sidebar.blade.php` baris 27-35 (grup Ruang Siswa). Ganti baris yang dikomentari:
```php
Auth::user()->hasRole('siswa') ? ['route' => 'admin.nilai-rapor-saya.index', 'pattern' => 'admin.nilai-rapor-saya.*', 'label' => 'Nilai & Rapor', 'icon' => 'award'] : null,
Auth::user()->hasRole('siswa') ? ['route' => 'admin.jadwal-pelajaran-saya.index', 'pattern' => 'admin.jadwal-pelajaran-saya.*', 'label' => 'Jadwal Pelajaran', 'icon' => 'calendar-clock'] : null,
Auth::user()->hasRole('siswa') ? ['route' => 'admin.presensi-saya.index', 'pattern' => 'admin.presensi-saya.*', 'label' => 'Presensi Saya', 'icon' => 'clipboard-check'] : null,
```
**PENTING**: kondisi guard TETAP `Auth::user()->hasRole('siswa')` PERSIS seperti baris asli sebelum dikomentari — JANGAN diubah ke `Auth::user()->siswa !== null` meski tampak lebih konsisten dengan pola Orang Tua (`Auth::user()->orangTua !== null`). Baris asli sudah pakai `hasRole('siswa')`, ubah HANYA `route`/`pattern`.

- [ ] **Step 4: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter="shows Ruang Siswa group"`
Expected: PASS.

- [ ] **Step 5: Pastikan tidak ada proses test lain berjalan**

Run: `ps aux | grep artisan | grep -v grep`
Expected: kosong.

- [ ] **Step 6: Jalankan full suite sendirian**

Run: `php artisan test --compact`
Expected: SEMUA test PASS, 0 failures (kecuali test SPMB flaky yang sudah diketahui, jalankan ulang sendirian untuk konfirmasi kalau muncul).

- [ ] **Step 7: Pint final**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`.

- [ ] **Step 8: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php tests/Feature/SidebarStubMenuHiddenTest.php tests/Feature/SidebarPengelompokanTest.php
git commit -m "feat(akademik): buka kembali menu Ruang Siswa (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya)"
```

(Sesuaikan daftar file test yang di-`git add` dengan file yang benar-benar disentuh di Step 1/3.)

---

## Self-Review

**1. Spec coverage**: §2.1 (`NilaiRaporSiswaController`) → Task 1. §2.2 (`JadwalPelajaranSiswaController`) → Task 2. §2.3 (`PresensiSayaController`) → Task 3. §2.4 (routes) → tersebar Task 1-3 Step 3 (incremental). §2.6 (sidebar) → Task 4. §3 Non-Goals — tidak ada task yang melanggarnya (tidak ada trait, tidak ada test IDOR query-string, `DashboardController` tidak disentuh).

**2. Placeholder scan**: Task 4 Step 1 minta baca file test sidebar existing dulu untuk pola persis (bukan TBD kosong — instruksi eksplisit "baca dulu", konsisten dengan yang terbukti perlu di paket Orang Tua). Semua task lain berisi kode lengkap, termasuk instruksi eksplisit "kalau test gagal karena X, lakukan Y" (Task 3 Step 4) untuk skenario `TenantScope` yang secara sengaja tidak diasumsikan sepihak — ini instruksi kondisional yang jelas, bukan TBD.

**3. Type consistency**: pola `buatSiswaDenganAkun*()` (helper per file test) konsisten memakai `Siswa::factory()->create(['user_id' => $user->id, ...])`, sesuai pola `OrangTuaFactory`/`SiswaFactory` yang sudah diverifikasi. `HARI_ORDER` const dipakai identik di Task 2 (disalin dari `JadwalAnakController` paket Orang Tua yang sudah terbukti benar).

**4. Dependency antar-task**: Task 1, 2, 3 SEMUA menyunting file `routes/admin/siswa-akademik.php` yang sama — kalau lewat subagent, WAJIB serial (satu per satu), JANGAN paralel. Task 4 bergantung pada Task 1-3 selesai (butuh ketiga route sudah terdaftar). Tidak ada dependency lain antar Task 1-3 (independen satu sama lain, boleh urutan bebas TAPI tetap serial karena file route bersama).
