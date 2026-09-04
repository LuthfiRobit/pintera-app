# Fitur Ruang Orang Tua Akademik Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun 3 halaman self-service Ruang Orang Tua (Nilai & Rapor Anak, Jadwal Anak, Riwayat Izin/Sakit Anak) yang saat ini sengaja disembunyikan, menggantikan placeholder `/dalam-pengembangan`.

**Architecture:** 1 trait bersama (`ResolveAnakOrangTuaTrait`) + 3 controller independen + 3 view + 1 file route + pembaruan sidebar. Semua read-only kecuali unduh Rapor PDF (reuse service existing).

**Tech Stack:** Laravel 12, PHP 8.3, Pest, Blade + Alpine.js (tanpa komponen JS baru — cuma native `<select onchange>` untuk selector anak, karena jumlah anak per orang tua biasanya kecil, jauh di bawah ambang batas Tom Select).

## Global Constraints

- Trait `ResolveAnakOrangTuaTrait` di `app/Domains/Akademik/Support/` — dipakai oleh KETIGA controller. `resolveAnakTerpilih()` HARUS diam-diam fallback ke anak pertama kalau `siswa_id` di request tidak valid/bukan milik actor — JANGAN `abort()`/error di method ini.
- Endpoint `NilaiAnakController::unduhRapor()` adalah PENGECUALIAN — WAJIB `abort_unless(..., 403)` tegas kalau `$siswa` bukan anak actor, BUKAN diam-diam fallback (beda filosofi dari `resolveAnakTerpilih()`, lihat spec §2.2).
- JANGAN tambahkan `$this->authorize()`/permission check apa pun di method `index()` ketiga controller — akses cukup lewat middleware `auth` + `resolveAnakList()` yang otomatis kosong untuk non-orang-tua (lihat spec §2.2 "Kenapa TIDAK ada authorize()").
- Style UI WAJIB konsisten dengan `resources/views/admin/dashboard/orang-tua.blade.php` (persona yang sama) — pakai `<x-app-layout>`, `<x-panel>`, `<x-badge tone="...">`, token warna `text-ink`/`text-slate`/`bg-paper`/`font-display`, BUKAN token abu-abu generik (`text-gray-*`) yang dipakai halaman admin operasional lain. Untuk ikon DI DALAM konten halaman pakai `<x-icon name="...">` (Material-Symbols-SVG, cuma nama yang terdaftar di `resources/views/components/icon.blade.php` yang valid) — BEDA dari ikon sidebar yang pakai Lucide (`x-lucide-*`, sudah benar di spec, tidak perlu diubah).
- Sisi Siswa (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya) — TIDAK disentuh sama sekali di paket ini.
- Form pengajuan izin/sakit baru — TIDAK dibangun, `RiwayatIzinSakitAnakController` murni read-only.
- Tidak pindah branch, tetap di `akademik-v2`.

---

## Task 1: Trait `ResolveAnakOrangTuaTrait`

**Files:**
- Create: `app/Domains/Akademik/Support/ResolveAnakOrangTuaTrait.php`
- Test: `tests/Unit/Support/ResolveAnakOrangTuaTraitTest.php`

**Interfaces:**
- Produksi: `resolveAnakList(User $actor): Collection<int, Siswa>`, `resolveAnakTerpilih(Collection $anakList, ?int $siswaIdDiminta): ?Siswa` — dipakai Task 2, 3, 4.

- [ ] **Step 1: Tulis test yang gagal**

Baca `tests/Unit/Support/ResolveLembagaScopeTraitTest.php` untuk pola helper anonymous-class yang sudah dipakai di sesi ini (`use TraitName; public function panggil(...) { return $this->method(...); }`). Buat file baru:

```php
<?php

declare(strict_types=1);

use App\Domains\Akademik\Support\ResolveAnakOrangTuaTrait;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function objekPakaiResolveAnakOrangTua(): object
{
    return new class
    {
        use ResolveAnakOrangTuaTrait;

        public function panggilResolveList(User $actor)
        {
            return $this->resolveAnakList($actor);
        }

        public function panggilResolveTerpilih($anakList, ?int $siswaIdDiminta): ?Siswa
        {
            return $this->resolveAnakTerpilih($anakList, $siswaIdDiminta);
        }
    };
}

it('resolveAnakList: mengembalikan collection kosong kalau user bukan orang tua', function () {
    $user = User::factory()->create();
    $obj = objekPakaiResolveAnakOrangTua();

    expect($obj->panggilResolveList($user))->toHaveCount(0);
});

it('resolveAnakList: mengembalikan semua anak yang terhubung lewat pivot siswa_orang_tua', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create();
    $orangTua = OrangTua::factory()->create(['user_id' => $user->id]);
    $anakSatu = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $anakDua = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $orangTua->siswa()->attach([$anakSatu->id => ['hubungan' => 'ayah'], $anakDua->id => ['hubungan' => 'ayah']]);

    $obj = objekPakaiResolveAnakOrangTua();

    $anakList = $obj->panggilResolveList($user->fresh());
    expect($anakList->pluck('id')->sort()->values()->all())->toBe(collect([$anakSatu->id, $anakDua->id])->sort()->values()->all());
});

it('resolveAnakTerpilih: mengembalikan anak sesuai siswa_id kalau ada di daftar', function () {
    $anakSatu = Siswa::factory()->make(['id' => 1]);
    $anakDua = Siswa::factory()->make(['id' => 2]);
    $anakList = collect([$anakSatu, $anakDua]);
    $obj = objekPakaiResolveAnakOrangTua();

    expect($obj->panggilResolveTerpilih($anakList, 2)->id)->toBe(2);
});

it('resolveAnakTerpilih: diam-diam fallback ke anak pertama kalau siswa_id tidak ada di daftar (IDOR guard)', function () {
    $anakSatu = Siswa::factory()->make(['id' => 1]);
    $anakDua = Siswa::factory()->make(['id' => 2]);
    $anakList = collect([$anakSatu, $anakDua]);
    $obj = objekPakaiResolveAnakOrangTua();

    // 999 = ID anak orang tua LAIN (bukan milik actor ini) -- harus diabaikan, bukan error.
    expect($obj->panggilResolveTerpilih($anakList, 999)->id)->toBe(1);
});

it('resolveAnakTerpilih: mengembalikan null kalau anakList kosong', function () {
    $obj = objekPakaiResolveAnakOrangTua();

    expect($obj->panggilResolveTerpilih(collect(), null))->toBeNull();
});
```

**Catatan penting soal factory**: `OrangTua` TIDAK punya kolom `user_id` sungguhan (link sebenarnya lewat `person_id` → `Person.user_id`, dan `User::orangTua()` adalah `hasOneThrough(OrangTua::class, Person::class, ...)`). `OrangTuaFactory::definition()` (`database/factories/OrangTuaFactory.php:38-62`) sudah menangani ini — `'user_id'` yang dikirim ke `OrangTua::factory()->create(['user_id' => $existingUserId])` dibaca sebagai OVERRIDE di `definition()` untuk menentukan `Person` mana yang dibuat/dipakai, BUKAN kolom asli. Karena itu WAJIB pakai pola `$orangTua = OrangTua::factory()->create(['user_id' => $user->id]);` (user dibuat DULU, id-nya dioper SAAT create), JANGAN `OrangTua::factory()->create()` lalu `->update(['user_id' => ...])` setelahnya (`update()` akan diam-diam no-op karena `user_id` tidak ada di `$fillable` model — `$user->orangTua` akan selalu `null` kalau pola ini dipakai, bikin SEMUA test di Task 1-4 gagal karena setup rusak, bukan karena fitur salah).

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=ResolveAnakOrangTuaTraitTest`
Expected: FAIL — class trait belum ada.

- [ ] **Step 3: Buat trait**

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

- [ ] **Step 4: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=ResolveAnakOrangTuaTraitTest`
Expected: PASS.

- [ ] **Step 5: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/Akademik/Support/ResolveAnakOrangTuaTrait.php tests/Unit/Support/ResolveAnakOrangTuaTraitTest.php
git commit -m "feat(akademik): trait ResolveAnakOrangTuaTrait untuk resolve anak milik orang tua + anak terpilih"
```

---

## Task 2: `NilaiAnakController` — Nilai & Rapor Anak

**Files:**
- Create: `app/Http/Controllers/Admin/NilaiAnakController.php`
- Create: `resources/views/admin/orang-tua/nilai-anak.blade.php`
- Test: `tests/Feature/Admin/NilaiAnakControllerTest.php`

**Interfaces:**
- Konsumsi: `ResolveAnakOrangTuaTrait::resolveAnakList()`/`resolveAnakTerpilih()` (Task 1).

- [ ] **Step 1: Tulis test yang gagal**

Baca `tests/Feature/Akademik/RppWorkflowTest.php` baris 1-59 untuk pola `beforeEach` + factory setup lengkap (Yayasan/Lembaga/TahunAjaran/Semester/Kelas) yang sudah established di sesi ini. Baca juga `app/Domains/Akademik/Models/PengajuanRapor.php` untuk field wajib exact (`kelas_id`, `semester_id`, `status`) dan `App\Domains\Akademik\Enums\StatusPengajuanRapor` untuk nilai enum yang valid. Buat file baru:

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
use App\Models\OrangTua;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;

function buatOrangTuaDenganAnak(Lembaga $lembaga, Kelas $kelas): array
{
    $user = User::factory()->create();
    $orangTua = OrangTua::factory()->create(['user_id' => $user->id]);
    $anak = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $orangTua->siswa()->attach($anak->id, ['hubungan' => 'ayah']);

    return [$user->fresh(), $anak];
}

it('menampilkan nilai anak untuk semester yang dipilih', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    [$user, $anak] = buatOrangTuaDenganAnak($lembaga, $kelas);

    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'jenis' => JenisAsesmen::SumatifLingkupMateri]);
    NilaiSiswa::factory()->create(['siswa_id' => $anak->id, 'asesmen_id' => $asesmen->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 88]);

    $response = $this->actingAs($user)->get(route('admin.nilai-anak.index', ['siswa_id' => $anak->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('nilaiList', fn ($list) => $list->contains(fn ($n) => $n->nilai_angka === 88));
});

it('menolak kebocoran nilai anak orang tua lain lewat siswa_id di query string (IDOR)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$userA, $anakA] = buatOrangTuaDenganAnak($lembaga, $kelas);
    [$userB, $anakB] = buatOrangTuaDenganAnak($lembaga, $kelas);

    $response = $this->actingAs($userA)->get(route('admin.nilai-anak.index', ['siswa_id' => $anakB->id]));

    $response->assertOk();
    $response->assertViewHas('anak', fn ($anak) => $anak->id === $anakA->id);
});

it('menampilkan tombol unduh rapor kalau PengajuanRapor sudah Disetujui', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $anak] = buatOrangTuaDenganAnak($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Disetujui]);

    $response = $this->actingAs($user)->get(route('admin.nilai-anak.index', ['siswa_id' => $anak->id, 'semester_id' => $semester->id]));

    $response->assertOk();
    $response->assertViewHas('pengajuanRapor', fn ($p) => $p !== null);
});

it('menolak unduh rapor untuk anak orang tua lain (403 tegas, bukan fallback)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$userA, $anakA] = buatOrangTuaDenganAnak($lembaga, $kelas);
    [$userB, $anakB] = buatOrangTuaDenganAnak($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Disetujui]);

    $response = $this->actingAs($userA)->get(route('admin.nilai-anak.unduh-rapor', ['siswa' => $anakB->id, 'semester_id' => $semester->id]));

    $response->assertStatus(403);
});

it('menolak unduh rapor kalau PengajuanRapor belum Disetujui', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $anak] = buatOrangTuaDenganAnak($lembaga, $kelas);
    PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id, 'status' => StatusPengajuanRapor::Diajukan]);

    $response = $this->actingAs($user)->get(route('admin.nilai-anak.unduh-rapor', ['siswa' => $anak->id, 'semester_id' => $semester->id]));

    $response->assertStatus(404);
});
```

**Catatan penting soal struktur `Asesmen`/`KomponenPenilaian`**: keduanya entitas TERPISAH dan SEJAJAR (masing-masing punya `subjek_type`/`subjek_id` sendiri, BUKAN `Asesmen belongsTo KomponenPenilaian` — tidak ada kolom `komponen_penilaian_id` di tabel `asesmen`). `NilaiSiswa` punya `asesmen_id` DAN `komponen_penilaian_id` sebagai 2 foreign key independen (lihat `database/factories/NilaiSiswaFactory.php`) — keduanya WAJIB dibuat terpisah seperti kode di atas, JANGAN dinested. `JenisAsesmen` cuma py 6 case valid: `DiagnostikKognitif`, `DiagnostikNonKognitif`, `Formatif`, `SumatifLingkupMateri`, `SumatifAkhirSemester`, `SumatifAkhirJenjang` (BUKAN `Sumatif` — tidak ada case dengan nama itu) — `masukRapor()` cuma mengembalikan 3 case `Sumatif*`.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=NilaiAnakControllerTest`
Expected: FAIL — route/controller belum ada.

- [ ] **Step 3: Buat `NilaiAnakController`**

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

- [ ] **Step 4: Tambah route (sementara, langsung di file baru — didaftarkan penuh di Task 5)**

Buat file `routes/admin/orang-tua-akademik.php`:
```php
<?php

use App\Http\Controllers\Admin\NilaiAnakController;
use Illuminate\Support\Facades\Route;

Route::get('nilai-anak', [NilaiAnakController::class, 'index'])->name('nilai-anak.index');
Route::get('nilai-anak/{siswa}/unduh-rapor', [NilaiAnakController::class, 'unduhRapor'])->name('nilai-anak.unduh-rapor');
```
Tambahkan `require base_path('routes/admin/orang-tua-akademik.php');` ke `routes/admin.php` setelah baris `require base_path('routes/admin/kasus-admin.php');`.

- [ ] **Step 5: Buat view `nilai-anak.blade.php`**

Baca `resources/views/admin/dashboard/orang-tua.blade.php` (sudah dibaca sebelumnya — pola `<x-app-layout>`, `<x-panel>`, token `text-ink`/`text-slate`/`font-display`) untuk acuan visual PERSIS. Buat:

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 pt-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold tracking-tight text-ink">Nilai & Rapor Anak</h1>
                <p class="mt-1 text-sm text-slate">Pantau nilai per mata pelajaran dan unduh rapor resmi anak Anda.</p>
            </div>
        </div>

        @if ($anakList->isEmpty())
            <x-panel class="p-8 text-center">
                <p class="text-sm text-slate">Belum ada data siswa yang terhubung dengan akun Anda.</p>
            </x-panel>
        @else
            <x-panel class="p-6">
                <form method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Pilih Anak" />
                        <select name="siswa_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-ink/15 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($anakList as $anakOpsi)
                                <option value="{{ $anakOpsi->id }}" @selected($anak?->id === $anakOpsi->id)>{{ $anakOpsi->nama_lengkap }} &middot; {{ $anakOpsi->kelas?->nama ?? 'Belum Ditentukan' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Semester" />
                        <select name="semester_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-ink/15 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($semesterList as $semesterOpsi)
                                <option value="{{ $semesterOpsi->id }}" @selected($semesterId == $semesterOpsi->id)>{{ $semesterOpsi->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </x-panel>

            @if ($pengajuanRapor)
                <x-panel class="p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                                <x-icon name="receipt" class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="font-display font-bold text-sm text-ink">Rapor Resmi Tersedia</h3>
                                <p class="text-xs text-slate">Rapor semester ini sudah disetujui dan siap diunduh.</p>
                            </div>
                        </div>
                        <a href="{{ route('admin.nilai-anak.unduh-rapor', ['siswa' => $anak->id, 'semester_id' => $semesterId]) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-brand-700 transition">
                                <x-icon name="print" class="h-4 w-4" />
                                Unduh Rapor
                            </a>
                        </div>
                </x-panel>
            @endif

            <x-panel class="p-6">
                <div class="flex items-center justify-between pb-4 border-b border-ink/10">
                    <div>
                        <h3 class="font-display font-bold text-lg text-ink">Daftar Nilai</h3>
                        <p class="text-xs text-slate">{{ $anak?->nama_lengkap }} &middot; {{ $semesterList->firstWhere('id', $semesterId)?->nama ?? '-' }}</p>
                    </div>
                </div>

                @if ($nilaiList->isEmpty())
                    <div class="py-10 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-paper text-slate">
                            <x-icon name="assessment" class="h-6 w-6" />
                        </span>
                        <p class="mt-3 text-xs font-medium text-slate">Belum ada nilai yang tercatat untuk semester ini.</p>
                    </div>
                @else
                    <ul class="mt-4 divide-y divide-ink/10">
                        @foreach ($nilaiList as $nilai)
                            <li class="flex items-center justify-between py-3 text-sm">
                                <span class="text-ink">{{ $nilai->komponenPenilaian?->subjek?->nama ?? $nilai->asesmen?->subjek?->nama ?? '-' }}</span>
                                <x-badge tone="brass">{{ $nilai->nilai_angka }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-panel>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 6: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=NilaiAnakControllerTest`
Expected: PASS.

- [ ] **Step 7: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/NilaiAnakController.php resources/views/admin/orang-tua/nilai-anak.blade.php routes/admin/orang-tua-akademik.php routes/admin.php tests/Feature/Admin/NilaiAnakControllerTest.php
git commit -m "feat(akademik): halaman Nilai & Rapor Anak untuk Ruang Orang Tua"
```

---

## Task 3: `JadwalAnakController` — Jadwal Anak

**Files:**
- Create: `app/Http/Controllers/Admin/JadwalAnakController.php`
- Create: `resources/views/admin/orang-tua/jadwal-anak.blade.php`
- Modify: `routes/admin/orang-tua-akademik.php`
- Test: `tests/Feature/Admin/JadwalAnakControllerTest.php`

**Interfaces:** Konsumsi: `ResolveAnakOrangTuaTrait` (Task 1).

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
use App\Models\OrangTua;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;

function buatOrangTuaDenganAnakDanJadwal(Lembaga $lembaga, Kelas $kelas): array
{
    $user = User::factory()->create();
    $orangTua = OrangTua::factory()->create(['user_id' => $user->id]);
    $anak = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $orangTua->siswa()->attach($anak->id, ['hubungan' => 'ibu']);

    return [$user->fresh(), $anak];
}

it('menampilkan jadwal 1 minggu penuh untuk anak terpilih', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $pola = PolaJam::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'pola_jam_id' => $pola->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $jam = JamPelajaran::factory()->create(['pola_jam_id' => $pola->id, 'is_pelajaran' => true, 'hari' => 'senin']);
    JadwalPelajaran::create(['kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'mata_pelajaran_id' => $mapel->id, 'jam_pelajaran_id' => $jam->id, 'semester_id' => $semester->id]);
    [$user, $anak] = buatOrangTuaDenganAnakDanJadwal($lembaga, $kelas);

    $response = $this->actingAs($user)->get(route('admin.jadwal-anak.index', ['siswa_id' => $anak->id]));

    $response->assertOk();
    $response->assertViewHas('jadwalList', fn ($list) => $list->flatten()->count() === 1);
});

it('menolak kebocoran jadwal anak orang tua lain lewat siswa_id (IDOR)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$userA, $anakA] = buatOrangTuaDenganAnakDanJadwal($lembaga, $kelas);
    [$userB, $anakB] = buatOrangTuaDenganAnakDanJadwal($lembaga, $kelas);

    $response = $this->actingAs($userA)->get(route('admin.jadwal-anak.index', ['siswa_id' => $anakB->id]));

    $response->assertOk();
    $response->assertViewHas('anak', fn ($anak) => $anak->id === $anakA->id);
});
```

Cek dulu `JamPelajaran` factory/model untuk field `hari` (kemungkinan enum `Hari`, sesuaikan value `'senin'` dengan yang valid — baca `App\Enums\Hari` dulu).

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=JadwalAnakControllerTest`
Expected: FAIL.

- [ ] **Step 3: Buat `JadwalAnakController`**

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
                ->sortBy(fn (JadwalPelajaran $jadwal) => $jadwal->jamPelajaran->jam_mulai)
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

- [ ] **Step 4: Tambah route**

Tambahkan ke `routes/admin/orang-tua-akademik.php`:
```php
use App\Http\Controllers\Admin\JadwalAnakController;
```
(tambahkan use statement di atas), dan:
```php
Route::get('jadwal-anak', [JadwalAnakController::class, 'index'])->name('jadwal-anak.index');
```

- [ ] **Step 5: Buat view `jadwal-anak.blade.php`**

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 pt-2">
        <div>
            <h1 class="font-display text-2xl font-bold tracking-tight text-ink">Jadwal Anak</h1>
            <p class="mt-1 text-sm text-slate">Jadwal pelajaran mingguan anak Anda pada semester aktif.</p>
        </div>

        @if ($anakList->isEmpty())
            <x-panel class="p-8 text-center">
                <p class="text-sm text-slate">Belum ada data siswa yang terhubung dengan akun Anda.</p>
            </x-panel>
        @else
            <x-panel class="p-6">
                <form method="GET">
                    <x-input-label value="Pilih Anak" />
                    <select name="siswa_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-ink/15 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:max-w-sm">
                        @foreach ($anakList as $anakOpsi)
                            <option value="{{ $anakOpsi->id }}" @selected($anak?->id === $anakOpsi->id)>{{ $anakOpsi->nama_lengkap }} &middot; {{ $anakOpsi->kelas?->nama ?? 'Belum Ditentukan' }}</option>
                        @endforeach
                    </select>
                </form>
            </x-panel>

            @if ($jadwalList->isEmpty())
                <x-panel class="p-10 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-paper text-slate">
                        <x-icon name="event" class="h-6 w-6" />
                    </span>
                    <p class="mt-3 text-xs font-medium text-slate">Belum ada jadwal pelajaran untuk semester aktif.</p>
                </x-panel>
            @else
                @foreach ($jadwalList as $hari => $jadwalHari)
                    <x-panel class="p-6">
                        <h3 class="font-display font-bold text-sm uppercase tracking-wider text-slate pb-3 border-b border-ink/10">{{ ucfirst($hari) }}</h3>
                        <ul class="mt-4 space-y-3">
                            @foreach ($jadwalHari as $jadwal)
                                <li class="flex items-center justify-between rounded-2xl border border-ink/10 bg-paper/40 p-3.5">
                                    <div class="min-w-0 flex-1">
                                        <h4 class="truncate font-display font-bold text-xs text-ink">{{ $jadwal->mataPelajaran?->nama ?? 'Tematik' }}</h4>
                                        <p class="text-[11px] text-slate/80 mt-0.5">
                                            {{ $jadwal->jamPelajaran?->jam_mulai }} - {{ $jadwal->jamPelajaran?->jam_selesai }}
                                            @if ($jadwal->guru)
                                                &middot; <span class="font-medium text-ink/70">{{ $jadwal->guru->nama }}</span>
                                            @endif
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-panel>
                @endforeach
            @endif
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 6: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=JadwalAnakControllerTest`
Expected: PASS.

- [ ] **Step 7: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/JadwalAnakController.php resources/views/admin/orang-tua/jadwal-anak.blade.php routes/admin/orang-tua-akademik.php tests/Feature/Admin/JadwalAnakControllerTest.php
git commit -m "feat(akademik): halaman Jadwal Anak untuk Ruang Orang Tua"
```

---

## Task 4: `RiwayatIzinSakitAnakController` — Riwayat Izin/Sakit Anak

**Files:**
- Create: `app/Http/Controllers/Admin/RiwayatIzinSakitAnakController.php`
- Create: `resources/views/admin/orang-tua/riwayat-izin-sakit-anak.blade.php`
- Modify: `routes/admin/orang-tua-akademik.php`
- Test: `tests/Feature/Admin/RiwayatIzinSakitAnakControllerTest.php`

**Interfaces:** Konsumsi: `ResolveAnakOrangTuaTrait` (Task 1).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;

function buatOrangTuaDenganAnakDanPresensi(Lembaga $lembaga, Kelas $kelas): array
{
    $user = User::factory()->create();
    $orangTua = OrangTua::factory()->create(['user_id' => $user->id]);
    $anak = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $orangTua->siswa()->attach($anak->id, ['hubungan' => 'ayah']);

    return [$user->fresh(), $anak];
}

it('menampilkan riwayat izin/sakit anak dalam rentang tanggal default (bulan ini)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $anak] = buatOrangTuaDenganAnakDanPresensi($lembaga, $kelas);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->startOfMonth()->addDays(2)]);
    Presensi::factory()->create(['siswa_id' => $anak->id, 'sesi_pembelajaran_id' => $sesi->id, 'status' => 'sakit', 'keterangan' => 'Demam']);

    $response = $this->actingAs($user)->get(route('admin.riwayat-izin-sakit-anak.index', ['siswa_id' => $anak->id]));

    $response->assertOk();
    $response->assertViewHas('riwayatList', fn ($list) => $list->count() === 1);
});

it('tidak menampilkan riwayat di luar rentang tanggal filter', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$user, $anak] = buatOrangTuaDenganAnakDanPresensi($lembaga, $kelas);
    $sesiLampau = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => now()->subMonths(3)]);
    Presensi::factory()->create(['siswa_id' => $anak->id, 'sesi_pembelajaran_id' => $sesiLampau->id, 'status' => 'izin']);

    $response = $this->actingAs($user)->get(route('admin.riwayat-izin-sakit-anak.index', ['siswa_id' => $anak->id]));

    $response->assertOk();
    $response->assertViewHas('riwayatList', fn ($list) => $list->count() === 0);
});

it('menolak kebocoran riwayat anak orang tua lain lewat siswa_id (IDOR)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    [$userA, $anakA] = buatOrangTuaDenganAnakDanPresensi($lembaga, $kelas);
    [$userB, $anakB] = buatOrangTuaDenganAnakDanPresensi($lembaga, $kelas);

    $response = $this->actingAs($userA)->get(route('admin.riwayat-izin-sakit-anak.index', ['siswa_id' => $anakB->id]));

    $response->assertOk();
    $response->assertViewHas('anak', fn ($anak) => $anak->id === $anakA->id);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=RiwayatIzinSakitAnakControllerTest`
Expected: FAIL.

- [ ] **Step 3: Buat `RiwayatIzinSakitAnakController`**

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
        $request->validate([
            'dari_tanggal' => ['nullable', 'date'],
            'sampai_tanggal' => ['nullable', 'date', 'after_or_equal:dari_tanggal'],
        ]);

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

- [ ] **Step 4: Tambah route**

Tambahkan ke `routes/admin/orang-tua-akademik.php`:
```php
use App\Http\Controllers\Admin\RiwayatIzinSakitAnakController;
```
dan:
```php
Route::get('riwayat-izin-sakit-anak', [RiwayatIzinSakitAnakController::class, 'index'])->name('riwayat-izin-sakit-anak.index');
```

- [ ] **Step 5: Buat view `riwayat-izin-sakit-anak.blade.php`**

```blade
<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 pt-2">
        <div>
            <h1 class="font-display text-2xl font-bold tracking-tight text-ink">Riwayat Izin/Sakit Anak</h1>
            <p class="mt-1 text-sm text-slate">Catatan izin dan sakit anak Anda dalam rentang tanggal tertentu.</p>
        </div>

        @if ($anakList->isEmpty())
            <x-panel class="p-8 text-center">
                <p class="text-sm text-slate">Belum ada data siswa yang terhubung dengan akun Anda.</p>
            </x-panel>
        @else
            <x-panel class="p-6">
                <form method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label value="Pilih Anak" />
                        <select name="siswa_id" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-ink/15 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($anakList as $anakOpsi)
                                <option value="{{ $anakOpsi->id }}" @selected($anak?->id === $anakOpsi->id)>{{ $anakOpsi->nama_lengkap }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Dari Tanggal" />
                        <input type="date" name="dari_tanggal" value="{{ $dariTanggal->toDateString() }}" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-ink/15 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <x-input-label value="Sampai Tanggal" />
                        <input type="date" name="sampai_tanggal" value="{{ $sampaiTanggal->toDateString() }}" onchange="this.form.submit()" class="mt-1.5 block w-full rounded-lg border-ink/15 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    @if ($anak)
                        <input type="hidden" name="siswa_id" value="{{ $anak->id }}">
                    @endif
                </form>
            </x-panel>

            <x-panel class="p-6">
                @if ($riwayatList->isEmpty())
                    <div class="py-10 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-paper text-slate">
                            <x-icon name="history" class="h-6 w-6" />
                        </span>
                        <p class="mt-3 text-xs font-medium text-slate">Tidak ada riwayat izin/sakit pada rentang tanggal ini.</p>
                    </div>
                @else
                    <ul class="divide-y divide-ink/10">
                        @foreach ($riwayatList as $presensi)
                            <li class="flex items-center justify-between py-3 text-sm">
                                <div>
                                    <p class="text-ink font-medium">{{ $presensi->sesiPembelajaran?->tanggal?->translatedFormat('d F Y') }}</p>
                                    <p class="text-xs text-slate mt-0.5">{{ $presensi->sesiPembelajaran?->mataPelajaran?->nama ?? 'Tematik' }} &middot; {{ $presensi->keterangan ?: '-' }}</p>
                                </div>
                                <x-badge tone="{{ $presensi->status->value === 'sakit' ? 'red' : 'amber' }}">{{ $presensi->status->label() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-panel>
        @endif
    </div>
</x-app-layout>
```

Catatan: `siswa_id` dikirim dobel (dropdown + hidden input) supaya tetap terkirim saat form disubmit lewat perubahan tanggal — cek dulu apakah ada pola existing yang lebih rapi untuk ini (mis. hidden input tunggal yang di-update via Alpine `x-model` sebelum submit) dan ikuti itu kalau ada, alih-alih duplikasi field seperti di atas.

- [ ] **Step 6: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=RiwayatIzinSakitAnakControllerTest`
Expected: PASS.

- [ ] **Step 7: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Admin/RiwayatIzinSakitAnakController.php resources/views/admin/orang-tua/riwayat-izin-sakit-anak.blade.php routes/admin/orang-tua-akademik.php tests/Feature/Admin/RiwayatIzinSakitAnakControllerTest.php
git commit -m "feat(akademik): halaman Riwayat Izin/Sakit Anak untuk Ruang Orang Tua"
```

---

## Task 5: Buka Kembali Menu Sidebar + Full Test Suite Final

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: regresi penuh (tidak ada file test baru)

**Interfaces:** Tidak ada — task penutup.

- [ ] **Step 1: Tulis test yang gagal — sidebar**

Cari test existing untuk sidebar (`grep -rn "sidebar" tests/ -il` dulu untuk menemukan file yang tepat — kemungkinan `tests/Feature/**/*Sidebar*Test.php`). Kalau ada, tambahkan test baru mengikuti pola file itu:
```php
it('menampilkan menu Nilai & Rapor Anak, Jadwal Anak, dan Riwayat Izin/Sakit Anak untuk orang tua', function () {
    // Setup actor dengan role orang_tua dan relasi OrangTua -- ikuti pola actor existing di file test sidebar ini.
    // Assert response->assertSee untuk ketiga label menu.
});

it('tidak menampilkan menu Ruang Orang Tua untuk actor bukan orang tua', function () {
    // Setup actor role lain (mis. guru) -- assert response->assertDontSee ketiga label menu.
});
```
Kalau TIDAK ada file test sidebar existing, buat `tests/Feature/SidebarOrangTuaAkademikTest.php` baru dengan struktur test di atas, ikuti pola `actingAs`+assert HTML dari test feature lain di sesi ini (mis. render halaman apa saja yang memuat sidebar, lalu assert isi HTML-nya).

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter="menampilkan menu Nilai & Rapor Anak"`
Expected: FAIL — menu masih dikomentari/mengarah ke `dalam-pengembangan`.

- [ ] **Step 3: Buka kembali komentar di sidebar**

Baca `resources/views/layouts/sidebar.blade.php` baris 41-50 (grup Ruang Orang Tua). Ganti baris 43-49 (yang dikomentari):
```php
Auth::user()->orangTua !== null ? ['route' => 'admin.nilai-anak.index', 'pattern' => 'admin.nilai-anak.*', 'label' => 'Nilai & Rapor Anak', 'icon' => 'award'] : null,
Auth::user()->orangTua !== null ? ['route' => 'admin.jadwal-anak.index', 'pattern' => 'admin.jadwal-anak.*', 'label' => 'Jadwal Anak', 'icon' => 'calendar-clock'] : null,
Auth::user()->orangTua !== null ? ['route' => 'admin.riwayat-izin-sakit-anak.index', 'pattern' => 'admin.riwayat-izin-sakit-anak.*', 'label' => 'Riwayat Izin/Sakit Anak', 'icon' => 'clipboard-check'] : null,
```
(Nama ikon `award`/`calendar-clock`/`clipboard-check` adalah nama Lucide — TETAP DIPAKAI PERSIS seperti versi lama yang dikomentari, JANGAN diubah ke nama `<x-icon>` Material-Symbols — sidebar pakai `x-lucide-*` via `<x-dynamic-component>`, sistem ikon BERBEDA dari yang dipakai di dalam konten halaman.)

- [ ] **Step 4: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter="menampilkan menu Nilai & Rapor Anak"`
Run: `php artisan test --filter="tidak menampilkan menu Ruang Orang Tua"`
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

- [ ] **Step 8: Pint dan commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/layouts/sidebar.blade.php tests/Feature/SidebarOrangTuaAkademikTest.php
git commit -m "feat(akademik): buka kembali menu Ruang Orang Tua (Nilai, Jadwal, Riwayat Izin/Sakit Anak)"
```

(Sesuaikan nama file test yang di-`git add` kalau ternyata ditambahkan ke file existing, bukan file baru.)

---

## Self-Review

**1. Spec coverage**: §2.1 (trait) → Task 1. §2.2 (`NilaiAnakController`) → Task 2. §2.3 (`JadwalAnakController`) → Task 3. §2.4 (`RiwayatIzinSakitAnakController`) → Task 4. §2.5 (routes) → tersebar di Task 2/3/4 Step 4 (ditambah incremental, bukan 1 file jadi sekaligus — supaya tiap task tetap bisa di-test independen begitu route-nya sendiri sudah ada). §2.6 (sidebar) → Task 5. §3 Non-Goals — tidak ada task yang melanggarnya (siswa side, form tulis izin/sakit, perubahan `Presensi` model — semua tidak disentuh).

**2. Placeholder scan**: beberapa step (Task 1 Step 1 soal relasi `User::orangTua()`, Task 2 Step 1 soal field factory `KomponenPenilaian`/`Asesmen`/`PengajuanRapor`, Task 3 Step 1 soal enum `Hari`, Task 4 Step 5 soal pola hidden-input existing, Task 5 Step 1 soal file test sidebar existing) meminta implementer membaca struktur aktual dulu sebelum finalisasi — ini instruksi eksplisit "verifikasi dulu", bukan TBD kosong, konsisten dengan pola yang sudah terbukti perlu di paket-paket audit sebelumnya (kesalahan tebak field pernah terjadi berulang kali kalau tidak diverifikasi).

**3. Type consistency**: `resolveAnakList(User $actor): Collection`/`resolveAnakTerpilih(Collection $anakList, ?int $siswaIdDiminta): ?Siswa` dipakai identik nama/parameter di Task 1 (definisi) dan Task 2/3/4 (pemakaian). Style UI (`<x-panel>`, `<x-badge tone="...">`, token `text-ink`/`text-slate`/`font-display`) konsisten di ketiga view baru, mengikuti `admin/dashboard/orang-tua.blade.php` yang sudah ada.

**4. Dependency antar-task**: Task 2, 3, 4 SEMUA bergantung pada Task 1 (trait) — HARUS dikerjakan setelah Task 1 selesai. Task 2/3/4 sendiri independen satu sama lain (boleh urutan bebas di antara ketiganya), TAPI ketiganya menyunting file `routes/admin/orang-tua-akademik.php` yang sama — kalau dikerjakan lewat subagent paralel, WAJIB serial (satu per satu) untuk file itu supaya tidak saling menimpa, konsisten dengan aturan umum subagent-driven-development (implementer dispatch satu per satu, tidak paralel).
