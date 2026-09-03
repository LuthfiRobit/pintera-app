# Perbaikan Audit Menyeluruh Modul Akademik Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup 8 temuan audit modul Akademik (4 Prioritas Tinggi, 4 Prioritas Sedang) plus 1 hardening opsional Prioritas Rendah, sesuai `.agents/specs/2026-09-03-audit-akademik-perbaikan.md`.

**Architecture:** 9 task independen, dikelompokkan per prioritas. Tidak ada perubahan skema database — semua perbaikan memakai kolom/tabel yang sudah ada.

**Tech Stack:** Laravel 12, PHP 8.3, Pest v4, Alpine.js, Tailwind.

## Global Constraints

- **Tidak ada migration baru** — semua fix pakai data/kolom yang sudah ada (lihat spec §4: tidak ada tabel audit-log baru untuk Kenaikan Kelas).
- **Full suite (`php artisan test --compact`) HARUS dijalankan SENDIRIAN** setelah semua task selesai — proyek ini pernah kena insiden ratusan false-failure karena 2 proses `php artisan test` bentrok (`SQLSTATE[HY000]: 1412` / deadlock `SQLSTATE[40001]`). Cek `ps aux | grep artisan` bersih dulu sebelum run.
- **Task 4 (sembunyikan menu sidebar) HARUS pakai comment-out, bukan hapus baris** — konsisten dengan pola pembekuan menu PPDB yang sudah ada di file yang sama (`resources/views/layouts/sidebar.blade.php`).
- **Guard status di Task 1 HANYA memblokir status `Diverifikasi`/`Disetujui`** — status `Draft`, `Diajukan`, `Ditolak` tetap boleh (re)submit seperti sekarang, itu bukan bug.
- **Task 5 (Rekap Kehadiran guru mapel) TIDAK BOLEH mengubah perilaku wali kelas** — wali kelas tetap dapat rekap penuh lintas-mapel persis seperti sekarang. Filter cuma berlaku untuk guru yang BUKAN wali kelas kelas tsb.
- Jangan menyentuh modul di luar Akademik (Keuangan, SPMB, Kehadiran SDM, Kasus).

---

## Task 1: Guard Status — Cegah Reset Rapor yang Sudah Diverifikasi/Disetujui

**Files:**
- Modify: `app/Domains/Akademik/Actions/Rapor/SubmitPengajuanRaporAction.php`
- Modify: `resources/views/portals/guru/rapor/catatan/index.blade.php`
- Test: `tests/Feature/Akademik/SubmitPengajuanRaporActionGuardTest.php`

**Interfaces:**
- Konsumsi: `App\Domains\Akademik\Enums\StatusPengajuanRapor` (case `Draft`, `Diajukan`, `Diverifikasi`, `Disetujui`, `Ditolak`, method `label()`).
- Tidak ada interface baru yang diekspos ke task lain.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('menolak submit ulang kalau pengajuan rapor sudah Disetujui', function () {
    $kelas = Kelas::factory()->create();
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $kelas->tahun_ajaran_id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $user = User::factory()->create();

    CatatanWaliKelas::factory()->create(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]);

    $pengajuanRapor = PengajuanRapor::create([
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $kelas->lembaga_id,
        'status' => StatusPengajuanRapor::Disetujui,
        'diajukan_oleh' => $user->id,
        'diajukan_pada' => now(),
        'disetujui_oleh' => $user->id,
        'disetujui_pada' => now(),
    ]);

    expect(fn () => app(SubmitPengajuanRaporAction::class)->execute($kelas, $semester, $user))
        ->toThrow(ValidationException::class);

    $pengajuanRapor->refresh();
    expect($pengajuanRapor->status)->toBe(StatusPengajuanRapor::Disetujui);
});

it('menolak submit ulang kalau pengajuan rapor sedang Diverifikasi', function () {
    $kelas = Kelas::factory()->create();
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $kelas->tahun_ajaran_id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $user = User::factory()->create();

    CatatanWaliKelas::factory()->create(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]);

    PengajuanRapor::create([
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $kelas->lembaga_id,
        'status' => StatusPengajuanRapor::Diverifikasi,
        'diajukan_oleh' => $user->id,
        'diajukan_pada' => now(),
    ]);

    expect(fn () => app(SubmitPengajuanRaporAction::class)->execute($kelas, $semester, $user))
        ->toThrow(ValidationException::class);
});

it('tetap boleh submit ulang kalau status Ditolak', function () {
    $kelas = Kelas::factory()->create();
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $kelas->tahun_ajaran_id]);
    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $user = User::factory()->create();

    CatatanWaliKelas::factory()->create(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]);

    PengajuanRapor::create([
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $kelas->lembaga_id,
        'status' => StatusPengajuanRapor::Ditolak,
        'diajukan_oleh' => $user->id,
        'diajukan_pada' => now(),
    ]);

    $result = app(SubmitPengajuanRaporAction::class)->execute($kelas, $semester, $user);

    expect($result->status)->toBe(StatusPengajuanRapor::Diajukan);
});
```

`database/factories/PengajuanRaporFactory.php` dan `CatatanWaliKelasFactory.php` sudah ada — pakai `::factory()->create([...])` kalau lebih ringkas, kode di atas pakai `::create()` eksplisit murni supaya kolom yang dites terlihat jelas.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=SubmitPengajuanRaporActionGuardTest`
Expected: FAIL (3 test, yang pertama & kedua gagal karena belum ada guard, yang ketiga mestinya sudah lolos).

- [ ] **Step 3: Tambah guard di `SubmitPengajuanRaporAction::execute()`**

Ubah method `execute()` jadi:

```php
public function execute(Kelas $kelas, Semester $semester, User $user): PengajuanRapor
{
    $existing = PengajuanRapor::where('kelas_id', $kelas->id)->where('semester_id', $semester->id)->first();

    if ($existing && in_array($existing->status, [StatusPengajuanRapor::Diverifikasi, StatusPengajuanRapor::Disetujui], true)) {
        throw ValidationException::withMessages([
            'status' => "Rapor kelas ini sudah berstatus \"{$existing->status->label()}\" dan tidak bisa diajukan ulang dari halaman ini.",
        ]);
    }

    $siswaList = $kelas->siswa()->get();
    $siswaIdsWithCatatan = CatatanWaliKelas::where('semester_id', $semester->id)
        ->whereIn('siswa_id', $siswaList->pluck('id'))
        ->pluck('siswa_id');

    $siswaBelumLengkap = $siswaList->whereNotIn('id', $siswaIdsWithCatatan);

    if ($siswaBelumLengkap->isNotEmpty()) {
        $daftarNama = $siswaBelumLengkap->pluck('nama_lengkap')->implode(', ');
        throw ValidationException::withMessages([
            'catatan_wali_kelas' => "Siswa berikut belum memiliki catatan wali kelas: {$daftarNama}.",
        ]);
    }

    return DB::transaction(function () use ($kelas, $semester, $user) {
        $pengajuanRapor = PengajuanRapor::updateOrCreate(
            ['kelas_id' => $kelas->id, 'semester_id' => $semester->id],
            ['status' => StatusPengajuanRapor::Diajukan, 'diajukan_oleh' => $user->id, 'diajukan_pada' => now()]
        );

        $existingApprovalRequest = $pengajuanRapor->approvalRequest;

        if ($existingApprovalRequest) {
            $firstStep = $existingApprovalRequest->workflowDefinition?->firstStep();
            $existingApprovalRequest->current_step_id = $firstStep?->id;
            $existingApprovalRequest->status = ApprovalStatus::Pending;
            $existingApprovalRequest->last_notes = null;
            $existingApprovalRequest->save();
        } else {
            $this->initializeApprovalRequestAction->execute('RAPOR_SEMESTER', $pengajuanRapor, $user);
        }

        return $pengajuanRapor->fresh();
    });
}
```

Tidak ada perubahan import baru (semua class yang dipakai sudah ter-import di file ini).

- [ ] **Step 4: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=SubmitPengajuanRaporActionGuardTest`
Expected: PASS (3/3).

- [ ] **Step 5: Tambah banner status di halaman guru + sembunyikan tombol**

Baca `resources/views/portals/guru/rapor/catatan/index.blade.php` baris ~55-120 dulu untuk melihat struktur banner `Ditolak` yang sudah ada dan kondisi tombol "Ajukan Rapor" saat ini. Tambahkan (letakkan tepat setelah blok banner `Ditolak` yang sudah ada, sebelum tabel siswa):

```blade
@if ($pengajuanRapor && in_array($pengajuanRapor->status, [\App\Domains\Akademik\Enums\StatusPengajuanRapor::Diverifikasi, \App\Domains\Akademik\Enums\StatusPengajuanRapor::Disetujui]))
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm font-medium text-blue-700">
        Rapor kelas ini sudah berstatus "{{ $pengajuanRapor->status->label() }}" sejak {{ $pengajuanRapor->diajukan_pada?->translatedFormat('d F Y, H:i') }}. Tidak bisa diajukan ulang dari halaman ini.
    </div>
@endif
```

Lalu cari kondisi yang men-disable/menyembunyikan tombol "Ajukan Rapor" berdasarkan kelengkapan catatan (`$siswaList->every(...)` sesuai temuan audit), dan tambahkan `&& ! in_array($pengajuanRapor?->status, [...])` ke kondisi yang sama supaya tombol juga hilang/disable kalau status sudah Diverifikasi/Disetujui. Sesuaikan nama variabel persis dengan yang ada di file (baca dulu, jangan asumsi nama variabel).

- [ ] **Step 6: Verifikasi manual lewat test feature controller (opsional kalau ada test existing utk halaman ini)**

Run: `php artisan test --filter=RaporControllerTest`
Expected: PASS, tidak ada regresi (test lama sudah cover cross-tenant/cross-semester, harus tetap hijau).

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Akademik/Actions/Rapor/SubmitPengajuanRaporAction.php resources/views/portals/guru/rapor/catatan/index.blade.php tests/Feature/Akademik/SubmitPengajuanRaporActionGuardTest.php
git commit -m "fix(akademik): cegah guru mengajukan ulang rapor yang sudah Diverifikasi/Disetujui"
```

---

## Task 2: Indikator Visual Kelas yang Sudah Diproses di Kenaikan Kelas

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php`
- Test: `tests/Feature/Akademik/KenaikanKelasIndicatorTest.php`

**Interfaces:**
- Konsumsi: `$kelasLamaList` dari `KenaikanKelasController::index()` — setiap item sudah punya `siswa_count` (dari `withCount('siswa')`, sudah ada, TIDAK perlu diubah controller-nya).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\User;

it('menampilkan indikator kelas kosong/sudah diproses di halaman Kenaikan Kelas', function () {
    $tahunAjaran = TahunAjaran::factory()->create();
    $kelasKosong = Kelas::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas Kosong Uji']);
    // Sengaja tidak buat Siswa untuk $kelasKosong -> siswa_count = 0

    $user = User::factory()->create(['lembaga_id' => $kelasKosong->lembaga_id]);
    $user->givePermissionTo('kenaikan-kelas.kelola');

    $response = $this->actingAs($user)->get(route('admin.kenaikan-kelas.index', ['tahun_ajaran_id' => $tahunAjaran->id]));

    $response->assertOk();
    $response->assertSee('Sudah diproses', false);
});
```

Sesuaikan cara memberi permission dengan pola test existing di `tests/Feature/Akademik/` (cek satu file test controller Akademik lain untuk pola `givePermissionTo`/role assignment yang benar dipakai proyek ini).

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=KenaikanKelasIndicatorTest`
Expected: FAIL (teks "Sudah diproses" belum ada di view).

- [ ] **Step 3: Tambah badge di view**

Baca `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php` dulu untuk menemukan baris yang me-render setiap `$kelasLamaList` (kemungkinan sebuah `<tr>` atau card per kelas dengan checkbox pemilihan). Tambahkan badge kondisional tepat di sebelah nama kelas:

```blade
@if ($kelas->siswa_count === 0)
    <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
        Sudah diproses / kosong
    </span>
@endif
```

Kalau baris kelas itu punya checkbox untuk dipilih massal (`x-model` Alpine atau `<input type="checkbox">`), pastikan checkbox untuk kelas dengan `siswa_count === 0` TIDAK tercentang secara default (biasanya default sudah tidak tercentang kalau tidak ada `checked` attribute — cukup pastikan tidak ada logic yang men-select-all termasuk kelas kosong ini; kalau ada tombol "Pilih Semua", tambahkan pengecualian `siswa_count > 0` di situ).

- [ ] **Step 4: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=KenaikanKelasIndicatorTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php tests/Feature/Akademik/KenaikanKelasIndicatorTest.php
git commit -m "fix(akademik): tampilkan indikator kelas kosong/sudah diproses di halaman Kenaikan Kelas"
```

---

## Task 3: Blokir Hapus Kurikulum Assignment yang Masih Dipakai Kelas

**Files:**
- Modify: `app/Http/Controllers/Admin/KurikulumAssignmentController.php`
- Modify: `resources/views/admin/kurikulum-assignment/index.blade.php`
- Modify: `resources/views/admin/kurikulum-assignment/edit.blade.php`
- Test: `tests/Feature/Admin/KurikulumAssignmentDestroyGuardTest.php`

**Interfaces:**
- Konsumsi: `App\Domains\Akademik\Models\KurikulumAssignment` (kolom `lembaga_id`, `tahun_ajaran_id`, `tingkat`, `kurikulum`), `App\Models\Kelas` (kolom `kurikulum` — snapshot, `tingkat`, `tahun_ajaran_id`, `lembaga_id`).
- Route existing yang ditautkan dari view: `admin.kurikulum-assignment.resync` (sudah ada, jangan diubah).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Enums\KurikulumFramework;
use App\Domains\Akademik\Models\KurikulumAssignment;
use App\Models\Kelas;
use App\Models\User;

it('menolak hapus Kurikulum Assignment yang masih dipakai Kelas', function () {
    $kelas = Kelas::factory()->create(['kurikulum' => KurikulumFramework::Merdeka]);

    $assignment = KurikulumAssignment::create([
        'lembaga_id' => $kelas->lembaga_id,
        'tahun_ajaran_id' => $kelas->tahun_ajaran_id,
        'bentuk_pendidikan' => $kelas->lembaga->bentuk_pendidikan,
        'tingkat' => $kelas->tingkat,
        'kurikulum' => KurikulumFramework::Merdeka,
    ]);

    $user = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $user->givePermissionTo('kurikulum-assignment.delete');

    $response = $this->actingAs($user)->delete(route('admin.kurikulum-assignment.destroy', $assignment));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(KurikulumAssignment::find($assignment->id))->not->toBeNull();
});

it('tetap bisa hapus Kurikulum Assignment yang tidak dipakai Kelas manapun', function () {
    $assignment = KurikulumAssignment::factory()->create();

    $user = User::factory()->create(['lembaga_id' => $assignment->lembaga_id]);
    $user->givePermissionTo('kurikulum-assignment.delete');

    $response = $this->actingAs($user)->delete(route('admin.kurikulum-assignment.destroy', $assignment));

    $response->assertRedirect(route('admin.kurikulum-assignment.index'));
    expect(KurikulumAssignment::find($assignment->id))->toBeNull();
});
```

Kalau `KurikulumAssignment` belum punya factory, buat dulu (`php artisan make:factory KurikulumAssignmentFactory --model=App\\Domains\\Akademik\\Models\\KurikulumAssignment`), isi field wajib (`lembaga_id`, `tahun_ajaran_id`, `bentuk_pendidikan`, `tingkat`, `kurikulum`) pakai factory `Lembaga`/`TahunAjaran` terkait.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=KurikulumAssignmentDestroyGuardTest`
Expected: FAIL pada test pertama (assignment masih terhapus, seharusnya ditolak).

- [ ] **Step 3: Tambah guard di `destroy()`**

Ubah method `destroy()`:

```php
public function destroy(Request $request, KurikulumAssignment $kurikulumAssignment): RedirectResponse
{
    $this->authorize('kurikulum-assignment.delete');
    $this->authorizeAssignmentScope($request, $kurikulumAssignment->lembaga_id);

    if ($kurikulumAssignment->lembaga_id !== null) {
        $jumlahKelasTerdampak = Kelas::where('lembaga_id', $kurikulumAssignment->lembaga_id)
            ->where('tahun_ajaran_id', $kurikulumAssignment->tahun_ajaran_id)
            ->where('tingkat', $kurikulumAssignment->tingkat)
            ->where('kurikulum', $kurikulumAssignment->kurikulum)
            ->count();

        if ($jumlahKelasTerdampak > 0) {
            return redirect()->route('admin.kurikulum-assignment.index')
                ->with('error', "Assignment ini masih dipakai {$jumlahKelasTerdampak} Kelas. Reassign kelas-kelas itu dulu, atau gunakan tool \"Cek & Perbaiki Kurikulum/Fase\".");
        }
    }

    $kurikulumAssignment->delete();

    return redirect()->route('admin.kurikulum-assignment.index')->with('status', 'Assignment kurikulum berhasil dihapus.');
}
```

Tambahkan `use App\Models\Kelas;` di bagian import kalau belum ada (cek dulu — file ini kemungkinan sudah import `Kelas` untuk kebutuhan lain, jangan duplikat).

- [ ] **Step 4: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=KurikulumAssignmentDestroyGuardTest`
Expected: PASS (2/2).

- [ ] **Step 5: Tautkan tool Resync dari index & edit view**

Baca `resources/views/admin/kurikulum-assignment/index.blade.php` dan `edit.blade.php` untuk menemukan area tombol aksi yang pas. Tambahkan link (pola tombol sekunder, sesuaikan class Tailwind dengan tombol sejenis yang sudah ada di file yang sama):

```blade
<a href="{{ route('admin.kurikulum-assignment.resync') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">
    Cek &amp; Perbaiki Kurikulum/Fase Kelas
</a>
```

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/KurikulumAssignmentController.php resources/views/admin/kurikulum-assignment/index.blade.php resources/views/admin/kurikulum-assignment/edit.blade.php tests/Feature/Admin/KurikulumAssignmentDestroyGuardTest.php database/factories/KurikulumAssignmentFactory.php
git commit -m "fix(akademik): blokir hapus Kurikulum Assignment yang masih dipakai Kelas, tautkan tool resync"
```

---

## Task 4: Sembunyikan 6 Menu Sidebar Stub (Ruang Siswa & Ruang Orang Tua)

**Files:**
- Modify: `resources/views/layouts/sidebar.blade.php`
- Test: `tests/Feature/SidebarStubMenuHiddenTest.php`

**Interfaces:** Tidak ada perubahan interface — murni comment-out array item.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Models\User;

it('tidak menampilkan menu sidebar stub untuk siswa', function () {
    $user = User::factory()->create();
    $user->assignRole('siswa');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Nilai &amp; Rapor', false);
    $response->assertDontSee('Presensi Saya', false);
});

it('tidak menampilkan menu sidebar stub untuk orang tua', function () {
    $user = User::factory()->create();
    $person = \App\Domains\Identity\Models\Person::factory()->create(['user_id' => $user->id]);
    \App\Models\OrangTua::factory()->create(['person_id' => $person->id]);
    $user->assignRole('orang_tua');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Nilai Anak', false);
    $response->assertDontSee('Jadwal Anak', false);
    $response->assertDontSee('Riwayat Izin/Sakit Anak', false);
});
```

Sesuaikan cara membuat relasi `User`↔`OrangTua` dengan pola factory yang sudah ada di proyek (cek `tests/Feature/` lain yang men-test halaman orang tua untuk pola exact-nya — jangan asumsikan struktur factory).

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=SidebarStubMenuHiddenTest`
Expected: FAIL (menu masih tampil).

- [ ] **Step 3: Comment-out 6 item di sidebar**

Di `resources/views/layouts/sidebar.blade.php`, ubah blok "Ruang Siswa" (baris 25-34) jadi:

```blade
[
    'label' => 'Ruang Siswa',
    'group_icon' => 'backpack',
    'items' => array_filter([
        // Nilai & Rapor / Jadwal Pelajaran / Presensi Saya sengaja disembunyikan (2026-09-03)
        // -- halaman detailnya belum dibangun (masih arah ke placeholder dalam-pengembangan),
        // datanya sudah ada ringkas di widget Dashboard. Bangun sebagai proyek fitur terpisah
        // sebelum dikembalikan ke sini.
        // Auth::user()->hasRole('siswa') ? ['route' => 'dalam-pengembangan', 'params' => ['fitur' => 'nilai-rapor'], 'pattern' => 'dalam-pengembangan', 'label' => 'Nilai & Rapor', 'icon' => 'award'] : null,
        // Auth::user()->hasRole('siswa') ? ['route' => 'dalam-pengembangan', 'params' => ['fitur' => 'jadwal-pelajaran'], 'pattern' => 'dalam-pengembangan', 'label' => 'Jadwal Pelajaran', 'icon' => 'calendar-clock'] : null,
        // Auth::user()->hasRole('siswa') ? ['route' => 'dalam-pengembangan', 'params' => ['fitur' => 'presensi-saya'], 'pattern' => 'dalam-pengembangan', 'label' => 'Presensi Saya', 'icon' => 'clipboard-check'] : null,
        Auth::user()->hasRole('siswa') && Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
    ]),
],
```

Dan blok "Ruang Orang Tua" (baris 35-47) jadi:

```blade
[
    'label' => 'Ruang Orang Tua',
    'group_icon' => 'users',
    'items' => array_filter([
        // Nilai Anak / Jadwal Anak / Riwayat Izin-Sakit Anak sengaja disembunyikan (2026-09-03)
        // -- halaman detailnya belum dibangun (masih arah ke placeholder dalam-pengembangan),
        // datanya sudah ada ringkas di widget Dashboard. Bangun sebagai proyek fitur terpisah
        // sebelum dikembalikan ke sini.
        // Auth::user()->orangTua !== null ? ['route' => 'dalam-pengembangan', 'params' => ['fitur' => 'nilai-anak'], 'pattern' => 'dalam-pengembangan', 'label' => 'Nilai Anak', 'icon' => 'award'] : null,
        // Auth::user()->orangTua !== null ? ['route' => 'dalam-pengembangan', 'params' => ['fitur' => 'jadwal-anak'], 'pattern' => 'dalam-pengembangan', 'label' => 'Jadwal Anak', 'icon' => 'calendar-clock'] : null,
        // Auth::user()->orangTua !== null ? ['route' => 'dalam-pengembangan', 'params' => ['fitur' => 'riwayat-izin-sakit-anak'], 'pattern' => 'dalam-pengembangan', 'label' => 'Riwayat Izin/Sakit Anak', 'icon' => 'clipboard-check'] : null,
        Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.dashboard', 'pattern' => 'keuangan.dashboard', 'label' => 'Dompet & Tagihan Saya', 'icon' => 'wallet'] : null,
        Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.tagihan.index', 'pattern' => 'keuangan.tagihan.*', 'label' => 'Tagihan', 'icon' => 'receipt'] : null,
        Auth::user()->can('keuangan.akses') && Auth::user()->orangTua !== null ? ['route' => 'keuangan.riwayat.index', 'pattern' => 'keuangan.riwayat.*', 'label' => 'Riwayat', 'icon' => 'history'] : null,
        Auth::user()->orangTua !== null && Auth::user()->can('viewAny', \App\Domains\Kasus\Models\Kasus::class) ? ['route' => 'kasus.index', 'pattern' => 'kasus.*', 'label' => 'Kasus Pendampingan', 'icon' => 'stethoscope'] : null,
    ]),
],
```

(Baris nomor persis bisa berbeda kalau ada perubahan lain sejak audit — cari blok `'label' => 'Ruang Siswa'` dan `'label' => 'Ruang Orang Tua'` di file yang sama, JANGAN comment-out item Keuangan/Kasus yang ikut ada di blok itu.)

- [ ] **Step 4: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=SidebarStubMenuHiddenTest`
Expected: PASS (2/2).

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/sidebar.blade.php tests/Feature/SidebarStubMenuHiddenTest.php
git commit -m "fix(akademik): sembunyikan menu sidebar stub Ruang Siswa/Ortu yang belum dibangun"
```

---

## Task 5: Rekap Kehadiran untuk Guru Mapel (Difilter ke Sesinya Sendiri)

**Files:**
- Modify: `app/Domains/Akademik/Services/PresensiAggregationService.php`
- Modify: `app/Http/Controllers/Guru/Akademik/RekapKehadiranController.php`
- Modify: `resources/views/portals/guru/akademik/jurnal-kbm/rekap.blade.php`
- Test: `tests/Feature/Akademik/RekapKehadiranGuruMapelTest.php`

**Interfaces:**
- Produksi: `PresensiAggregationService::agregasiPerKelas(int $kelasId, ?Semester $semester = null, ?int $guruId = null): Collection` — signature baru, param ke-3 opsional (backward compatible, tidak mematahkan pemanggil lain).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;

it('guru mapel (bukan wali kelas) bisa lihat Rekap Kehadiran, difilter ke sesinya sendiri', function () {
    $kelas = Kelas::factory()->create();
    $guruWali = Guru::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $kelas->update(['wali_kelas_guru_id' => $guruWali->id]);

    $userMapel = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $personMapel = \App\Domains\Identity\Models\Person::factory()->create(['user_id' => $userMapel->id]);
    $guruMapel = Guru::factory()->create(['person_id' => $personMapel->id, 'lembaga_id' => $kelas->lembaga_id]);
    $userMapel->assignRole('guru');

    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id, 'status' => 'aktif']);

    $sesiMapel = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'guru_id' => $guruMapel->id]);
    $sesiWali = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'guru_id' => $guruWali->id]);

    \App\Domains\Akademik\Models\Presensi::create(['sesi_pembelajaran_id' => $sesiMapel->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);
    \App\Domains\Akademik\Models\Presensi::create(['sesi_pembelajaran_id' => $sesiWali->id, 'siswa_id' => $siswa->id, 'status' => 'hadir']);
    \App\Domains\Akademik\Models\Presensi::create(['sesi_pembelajaran_id' => $sesiWali->id, 'siswa_id' => $siswa->id, 'status' => 'izin']);

    $response = $this->actingAs($userMapel)->get(route('guru.jurnal-kbm.rekap', ['kelas_id' => $kelas->id]));

    $response->assertOk();
    // Guru mapel cuma lihat 1 sesi miliknya (1 hadir), bukan total kelas (2 hadir + 1 izin)
    $rekap = $response->viewData('rekap');
    $barisSiswa = collect($rekap)->firstWhere('siswa_id', $siswa->id);
    expect($barisSiswa['hadir'])->toBe(1);
    expect($barisSiswa['izin'])->toBe(0);
});
```

Cek dulu factory `SesiPembelajaran`/`Presensi` yang sudah ada (`database/factories/SesiPembelajaranFactory.php`) untuk field wajib yang perlu diisi (`jadwal_pelajaran_id` mungkin perlu, `tanggal`, `jam_mulai`, `jam_selesai`, `status`) — sesuaikan pemanggilan factory di atas kalau ada field wajib yang belum di-cover default factory-nya. Catatan: `User::guru()` (`app/Models/User.php:76-86`) adalah `hasOneThrough(Guru::class, Person::class, 'user_id', 'person_id', 'id', 'id')` — TIDAK bisa `$user->guru()->save($guru)` langsung, makanya test di atas membuat `Person` dengan `user_id` lebih dulu baru `Guru` dengan `person_id` mengarah ke situ.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=RekapKehadiranGuruMapelTest`
Expected: FAIL (halaman kemungkinan 403/redirect karena guru mapel belum diizinkan akses kelas yang bukan wali-nya, ATAU angka hadir yang salah).

- [ ] **Step 3: Tambah parameter `$guruId` di `PresensiAggregationService`**

```php
public function agregasiPerKelas(int $kelasId, ?Semester $semester = null, ?int $guruId = null): Collection
{
    $siswaList = Siswa::where('kelas_id', $kelasId)->where('status', 'aktif')->with('person')->orderByNama()->get();

    $query = DB::table('presensi')
        ->select('presensi.siswa_id', 'presensi.status', DB::raw('count(*) as total'))
        ->join('sesi_pembelajaran', 'sesi_pembelajaran.id', '=', 'presensi.sesi_pembelajaran_id')
        ->where('sesi_pembelajaran.kelas_id', $kelasId);

    if ($guruId !== null) {
        $query->where('sesi_pembelajaran.guru_id', $guruId);
    }

    if ($semester && $semester->tanggal_mulai && $semester->tanggal_selesai) {
        $query->whereBetween('sesi_pembelajaran.tanggal', [$semester->tanggal_mulai, $semester->tanggal_selesai]);
    }

    $counts = $query->groupBy('presensi.siswa_id', 'presensi.status')
        ->get()
        ->groupBy('siswa_id');

    return $siswaList->map(function (Siswa $siswa) use ($counts) {
        $byStatus = $counts->get($siswa->id, collect())->pluck('total', 'status');

        return [
            'siswa_id' => $siswa->id,
            'nis' => $siswa->nis,
            'nama' => $siswa->nama_lengkap,
            'hadir' => (int) ($byStatus['hadir'] ?? 0),
            'izin' => (int) ($byStatus['izin'] ?? 0),
            'sakit' => (int) ($byStatus['sakit'] ?? 0),
            'alpa' => (int) ($byStatus['alpa'] ?? 0),
            'terlambat' => (int) ($byStatus['terlambat'] ?? 0),
        ];
    });
}
```

- [ ] **Step 4: Ubah `RekapKehadiranController::index()` supaya guru mapel juga bisa akses**

Ganti baris:

```php
$kelasQuery = Kelas::where('wali_kelas_guru_id', $guru->id);
```

jadi:

```php
$kelasIdDiajar = JadwalPelajaran::where('guru_id', $guru->id)->distinct()->pluck('kelas_id');
$kelasQuery = Kelas::where(function ($q) use ($guru, $kelasIdDiajar) {
    $q->where('wali_kelas_guru_id', $guru->id)->orWhereIn('id', $kelasIdDiajar);
});
```

Tambahkan `use App\Models\JadwalPelajaran;` ke import kalau belum ada.

Lalu ubah pemanggilan `agregasiPerKelas()`:

```php
$rekap = collect();
$isWaliKelas = false;
if ($kelas) {
    $isWaliKelas = $kelas->wali_kelas_guru_id === $guru->id;
    $rekap = $this->aggregationService->agregasiPerKelas($kelas->id, $selectedSemester, $isWaliKelas ? null : $guru->id);
}
```

Tambahkan `'isWaliKelas' => $isWaliKelas,` ke array data yang dikirim ke view (`return view(...)` di akhir method).

- [ ] **Step 5: Tambah indikator di view**

Baca `resources/views/portals/guru/akademik/jurnal-kbm/rekap.blade.php`, tambahkan di dekat judul/header tabel:

```blade
@if ($kelas)
    <p class="text-sm text-gray-500">
        @if ($isWaliKelas)
            Rekap penuh kelas (semua mata pelajaran) — Anda wali kelas ini.
        @else
            Rekap disaring untuk sesi yang Anda ajar saja.
        @endif
    </p>
@endif
```

- [ ] **Step 6: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=RekapKehadiranGuruMapelTest`
Expected: PASS.

- [ ] **Step 7: Jalankan regresi wali kelas (pastikan tidak berubah)**

Run: `php artisan test --filter=RekapKehadiran`
Expected: PASS semua (termasuk test lama untuk wali kelas kalau ada — rekap wali kelas harus tetap penuh/tidak difilter).

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Akademik/Services/PresensiAggregationService.php app/Http/Controllers/Guru/Akademik/RekapKehadiranController.php resources/views/portals/guru/akademik/jurnal-kbm/rekap.blade.php tests/Feature/Akademik/RekapKehadiranGuruMapelTest.php
git commit -m "fix(akademik): guru mapel bisa lihat Rekap Kehadiran, difilter ke sesinya sendiri"
```

---

## Task 6: Jurnal & Presensi — Dukung Isi Susulan Tanggal Sebelumnya

**Files:**
- Modify: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`
- Modify: `resources/views/portals/guru/akademik/jurnal-kbm/index.blade.php`
- Test: `tests/Feature/Akademik/JurnalKbmTanggalSusulanTest.php`

**Interfaces:** Tidak ada perubahan interface ke task lain — `GenerateSesiHarianAction::execute(Guru $guru, CarbonInterface $tanggal)` sudah mendukung tanggal arbitrer, tidak perlu diubah.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\User;

it('bisa lihat sesi kemarin lewat query param tanggal', function () {
    $guru = Guru::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $guru->lembaga_id]);
    \App\Domains\Identity\Models\Person::where('id', $guru->person_id)->update(['user_id' => $user->id]);
    $user->assignRole('guru');

    $kemarin = now()->subDay();
    $jadwal = JadwalPelajaran::factory()->create(['guru_id' => $guru->id]);
    $sesiKemarin = SesiPembelajaran::factory()->create([
        'jadwal_pelajaran_id' => $jadwal->id,
        'guru_id' => $guru->id,
        'kelas_id' => $jadwal->kelas_id,
        'tanggal' => $kemarin->toDateString(),
    ]);

    $response = $this->actingAs($user)->get(route('guru.jurnal-kbm.index', ['tanggal' => $kemarin->toDateString()]));

    $response->assertOk();
    $sesiList = $response->viewData('sesiList');
    expect($sesiList->pluck('id'))->toContain($sesiKemarin->id);
});

it('menolak tanggal di masa depan', function () {
    $guru = Guru::factory()->create();
    $user = User::factory()->create(['lembaga_id' => $guru->lembaga_id]);
    \App\Domains\Identity\Models\Person::where('id', $guru->person_id)->update(['user_id' => $user->id]);
    $user->assignRole('guru');

    $besok = now()->addDay()->toDateString();

    $response = $this->actingAs($user)->get(route('guru.jurnal-kbm.index', ['tanggal' => $besok]));

    $response->assertRedirect(route('guru.jurnal-kbm.index'));
    $response->assertSessionHas('error');
});
```

(`User::guru()` adalah `hasOneThrough` lewat `Person` — pola di atas menautkan lewat `Person.user_id`, bukan `save()` langsung.)

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=JurnalKbmTanggalSusulanTest`
Expected: FAIL.

- [ ] **Step 3: Ubah `JurnalKbmController::index()` menerima `?tanggal`**

```php
public function index(Request $request): View|RedirectResponse
{
    $this->authorize('presensi.isi');

    $guru = $request->user()->guru;

    $tanggalInput = $request->query('tanggal');
    $tanggal = $tanggalInput ? \Carbon\Carbon::parse($tanggalInput) : now();

    if ($tanggal->isFuture()) {
        return redirect()->route('guru.jurnal-kbm.index')->with('error', 'Tidak bisa mengisi jurnal untuk tanggal yang belum terjadi.');
    }

    $hariIni = $tanggal;

    if ($guru) {
        $this->generateSesiHarianAction->execute($guru, $hariIni);
    }

    $sesiList = $guru
        ? SesiPembelajaran::where('guru_id', $guru->id)->whereDate('tanggal', $hariIni)->with('kelas.tahunAjaran', 'mataPelajaran')->get()
        : collect();

    return view('portals.guru.akademik.jurnal-kbm.index', [
        'sesiList' => $sesiList,
        'mapelTerjadwal' => $this->mapelTerjadwalUntukSesiTematik($sesiList, $hariIni),
        'tanggalDipilih' => $hariIni->toDateString(),
    ]);
}
```

Tambahkan `use Illuminate\Http\RedirectResponse;` ke import, dan ubah return type hint method dari `View` jadi `View|RedirectResponse`.

- [ ] **Step 4: Tambah navigasi tanggal di view**

Baca `resources/views/portals/guru/akademik/jurnal-kbm/index.blade.php`, tambahkan di atas daftar sesi:

```blade
<form method="GET" action="{{ route('guru.jurnal-kbm.index') }}" class="mb-4 flex items-center gap-2">
    <a href="{{ route('guru.jurnal-kbm.index', ['tanggal' => \Carbon\Carbon::parse($tanggalDipilih)->subDay()->toDateString()]) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm hover:bg-gray-50">&larr; Sebelumnya</a>
    <input type="date" name="tanggal" value="{{ $tanggalDipilih }}" max="{{ now()->toDateString() }}" class="rounded-lg border-gray-200 text-sm" onchange="this.form.submit()">
    <a href="{{ route('guru.jurnal-kbm.index') }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm hover:bg-gray-50">Hari Ini</a>
</form>
```

- [ ] **Step 5: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=JurnalKbmTanggalSusulanTest`
Expected: PASS (2/2).

- [ ] **Step 6: Jalankan regresi**

Run: `php artisan test --filter=JurnalKbm`
Expected: PASS semua test existing (akses tanpa `?tanggal` tetap default ke hari ini seperti sebelumnya).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Guru/Akademik/JurnalKbmController.php resources/views/portals/guru/akademik/jurnal-kbm/index.blade.php tests/Feature/Akademik/JurnalKbmTanggalSusulanTest.php
git commit -m "feat(akademik): dukung isi Jurnal & Presensi susulan untuk tanggal sebelumnya"
```

---

## Task 7: Halaman Riwayat Persetujuan Rapor

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`
- Modify: `resources/views/portals/lembaga/rapor/persetujuan/index.blade.php`
- Test: `tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php`

**Interfaces:** Tidak ada perubahan interface ke task lain.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\User;

it('menampilkan pengajuan yang sudah Disetujui di tab riwayat, bukan tab default', function () {
    $kelas = Kelas::factory()->create();
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $kelas->tahun_ajaran_id]);

    $pengajuanDisetujui = PengajuanRapor::create([
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $kelas->lembaga_id,
        'status' => StatusPengajuanRapor::Disetujui,
        'diajukan_pada' => now(),
    ]);

    $user = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $user->givePermissionTo('rapor.approve');

    $responseDefault = $this->actingAs($user)->get(route('admin.rapor.persetujuan.index'));
    $responseDefault->assertDontSee($kelas->nama);

    $responseRiwayat = $this->actingAs($user)->get(route('admin.rapor.persetujuan.index', ['tab' => 'riwayat']));
    $responseRiwayat->assertSee($kelas->nama);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=PersetujuanRaporRiwayatTest`
Expected: FAIL pada assertion tab riwayat (belum ada dukungan `?tab=riwayat`).

- [ ] **Step 3: Ubah `PersetujuanController::index()`**

```php
public function index(Request $request): View|string
{
    abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);

    $tab = $request->query('tab', 'menunggu');

    if ($tab === 'riwayat') {
        $effectiveLembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;

        $query = PengajuanRapor::whereIn('status', [StatusPengajuanRapor::Disetujui, StatusPengajuanRapor::Ditolak])
            ->when($effectiveLembagaId, fn ($q) => $q->where('lembaga_id', $effectiveLembagaId))
            ->with(['kelas.tahunAjaran', 'semester'])
            ->when($request->search, function ($q, $search) {
                $q->whereHas('kelas', fn ($k) => $k->where('nama', 'like', "%{$search}%"));
            })
            ->latest();
    } else {
        $statusYangDicari = $this->statusUntukAktor($request);

        $query = PengajuanRapor::where('status', $statusYangDicari)
            ->with(['kelas.tahunAjaran', 'semester'])
            ->when($request->search, function ($q, $search) {
                $q->whereHas('kelas', fn ($k) => $k->where('nama', 'like', "%{$search}%"));
            })
            ->latest();
    }

    $pengajuanList = $query->get();

    if ($request->ajax()) {
        return view('portals.lembaga.rapor.persetujuan._daftar', compact('pengajuanList', 'tab'))->render();
    }

    return view('portals.lembaga.rapor.persetujuan.index', compact('pengajuanList', 'tab'));
}
```

Tambahkan `use App\Domains\Akademik\Enums\StatusPengajuanRapor;` ke import kalau belum ada.

- [ ] **Step 4: Tambah tab UI**

Baca `resources/views/portals/lembaga/rapor/persetujuan/index.blade.php`, tambahkan di atas daftar:

```blade
<div class="mb-4 flex gap-2 border-b border-gray-200">
    <a href="{{ route('admin.rapor.persetujuan.index') }}" class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'menunggu' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500' }}">
        Menunggu Keputusan Saya
    </a>
    <a href="{{ route('admin.rapor.persetujuan.index', ['tab' => 'riwayat']) }}" class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'riwayat' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500' }}">
        Riwayat
    </a>
</div>
```

Sesuaikan class Tailwind persis dengan pola tab yang sudah ada di proyek ini kalau ada komponen tab reusable (cek `resources/views/components/` untuk komponen tab sebelum menulis manual).

- [ ] **Step 5: Jalankan test lagi, pastikan lolos**

Run: `php artisan test --filter=PersetujuanRaporRiwayatTest`
Expected: PASS.

- [ ] **Step 6: Jalankan regresi**

Run: `php artisan test --filter=PersetujuanController`
Expected: PASS semua (tab default tanpa `?tab=` harus tetap berperilaku identik seperti sebelumnya).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php resources/views/portals/lembaga/rapor/persetujuan/index.blade.php tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php
git commit -m "feat(akademik): tambah tab Riwayat di halaman Persetujuan Rapor"
```

---

## Task 8: Cegah Race Condition Approve/Reject Rapor Ganda

**Files:**
- Modify: `app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php`
- Modify: `app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php`
- Test: `tests/Feature/Akademik/RaporApprovalLockTest.php`

**Interfaces:** Tidak ada perubahan signature — `execute()` tetap menerima `PengajuanRapor $pengajuanRapor` dari controller, tapi di dalam transaksi instance itu di-refresh dengan lock.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Actions\Rapor\ApprovePengajuanRaporAction;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\User;

it('memanggil execute dua kali berurutan tidak menghasilkan ApprovalLog ganda untuk hasil yang sama', function () {
    $kelas = Kelas::factory()->create();
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $kelas->tahun_ajaran_id]);
    $user = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $user->givePermissionTo('rapor.approve');

    $pengajuanRapor = PengajuanRapor::create([
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $kelas->lembaga_id,
        'status' => StatusPengajuanRapor::Diverifikasi,
        'diajukan_pada' => now(),
    ]);
    app(\App\Domains\Workflow\Actions\InitializeApprovalRequestAction::class)->execute('RAPOR_SEMESTER', $pengajuanRapor, $user);
    // Majukan approvalRequest ke step approve (samakan dengan cara VerifyPengajuanRaporAction menaikkan step di test lain kalau ada, atau cek WorkflowDefinition seed RAPOR_SEMESTER)

    app(ApprovePengajuanRaporAction::class)->execute($pengajuanRapor->fresh(), $user, ApprovalAction::Approve, null);

    $pengajuanRapor->refresh();
    expect($pengajuanRapor->status)->toBe(StatusPengajuanRapor::Disetujui);

    // Panggil lagi setelah sudah Disetujui -- harus ditolak wajar (bukan double-process),
    // karena ProcessApprovalAction sendiri sudah menolak approve di step yang sudah selesai.
    expect(fn () => app(ApprovePengajuanRaporAction::class)->execute($pengajuanRapor->fresh(), $user, ApprovalAction::Approve, null))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
```

Catatan: test ini fokus membuktikan bahwa setelah perbaikan `lockForUpdate()`, alur approve tetap benar (tidak regresi) — race condition asli (2 request HTTP simultan) sulit disimulasikan di test biasa, jadi cukup pastikan behavior sekuensial tetap benar dan tidak ada exception baru yang tidak diharapkan.

- [ ] **Step 2: Jalankan test, catat hasil awal (baseline, boleh sudah PASS sebelum perubahan — ini bukan test TDD ketat, murni regresi)**

Run: `php artisan test --filter=RaporApprovalLockTest`
Expected: kemungkinan sudah PASS bahkan sebelum step 3 (karena `ProcessApprovalAction` sudah menolak approve step yang sudah kelar) — itu OK, lanjut ke step 3 untuk menutup celah lock di level row `PengajuanRapor` sendiri.

- [ ] **Step 3: Tambah `lockForUpdate()` di `ApprovePengajuanRaporAction`**

Ubah bagian `DB::transaction()`:

```php
return DB::transaction(function () use ($pengajuanRapor, $approvalRequest, $user, $action, $catatan) {
    $pengajuanRapor = PengajuanRapor::lockForUpdate()->findOrFail($pengajuanRapor->id);

    $this->processApprovalAction->execute($approvalRequest, $user, $action, $catatan);
    $approvalRequest->refresh();

    if ($approvalRequest->status === ApprovalStatus::Rejected) {
        $pengajuanRapor->status = StatusPengajuanRapor::Ditolak;
        $pengajuanRapor->catatan_revisi = $catatan;
    } elseif ($approvalRequest->status === ApprovalStatus::Approved) {
        $pengajuanRapor->status = StatusPengajuanRapor::Disetujui;
        $pengajuanRapor->disetujui_oleh = $user->id;
        $pengajuanRapor->disetujui_pada = now();
    }

    $pengajuanRapor->save();

    return $pengajuanRapor->fresh();
});
```

- [ ] **Step 4: Terapkan perubahan yang sama di `VerifyPengajuanRaporAction`**

```php
return DB::transaction(function () use ($pengajuanRapor, $approvalRequest, $user, $action, $catatan) {
    $pengajuanRapor = PengajuanRapor::lockForUpdate()->findOrFail($pengajuanRapor->id);

    $this->processApprovalAction->execute($approvalRequest, $user, $action, $catatan);
    $approvalRequest->refresh();

    if ($approvalRequest->status === ApprovalStatus::Rejected) {
        $pengajuanRapor->status = StatusPengajuanRapor::Ditolak;
        $pengajuanRapor->catatan_revisi = $catatan;
    } elseif ($approvalRequest->status === ApprovalStatus::InReview) {
        $pengajuanRapor->status = StatusPengajuanRapor::Diverifikasi;
        $pengajuanRapor->diverifikasi_oleh = $user->id;
        $pengajuanRapor->diverifikasi_pada = now();
    }

    $pengajuanRapor->save();

    return $pengajuanRapor->fresh();
});
```

- [ ] **Step 5: Jalankan test lagi + regresi penuh area Rapor**

Run: `php artisan test --filter=RaporApprovalLockTest`
Run: `php artisan test --filter=Rapor`
Expected: semua PASS, tidak ada regresi di test approval/verifikasi existing.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php tests/Feature/Akademik/RaporApprovalLockTest.php
git commit -m "fix(akademik): kunci row PengajuanRapor saat approve/verify untuk cegah race condition"
```

---

## Task 9 (Opsional, Prioritas Rendah): Hardening Validasi Tenant Eksplisit di Jadwal Pelajaran

**Files:**
- Modify: `app/Http/Controllers/Admin/JadwalPelajaranController.php`
- Test: `tests/Feature/Admin/JadwalPelajaranTenantGuardTest.php`

**Interfaces:** Tidak ada perubahan interface.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Models\Kelas;
use App\Models\User;

it('menolak store Jadwal Pelajaran untuk Kelas lembaga lain', function () {
    $kelasLembagaLain = Kelas::factory()->create();
    $user = User::factory()->create(); // lembaga berbeda dari $kelasLembagaLain
    $user->givePermissionTo('jadwal-pelajaran.kelola');

    $response = $this->actingAs($user)->post(route('admin.jadwal-pelajaran.store'), [
        'kelas_id' => $kelasLembagaLain->id,
        // isi field wajib lain sesuai StoreJadwalPelajaranRequest -- baca dulu file itu
    ]);

    $response->assertStatus(404);
});
```

Baca `app/Http/Requests/Akademik/StoreJadwalPelajaranRequest.php` (atau nama sebenarnya) dulu untuk tahu field wajib lengkap sebelum menulis body request test ini.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=JadwalPelajaranTenantGuardTest`
Expected: kemungkinan SUDAH PASS karena `TenantScope` sudah menutup ini di level model (`Kelas::find()` tidak akan ketemu row lembaga lain buat user yang bukan platform/yayasan) — kalau sudah PASS di titik ini, itu KONFIRMASI bahwa scope global sudah cukup, lanjut ke Step 3 untuk defense-in-depth eksplisit saja (opsional, boleh skip task ini kalau plan sudah dianggap cukup panjang).

- [ ] **Step 3: Tambah guard eksplisit di awal `store()`/`update()`**

Baca `app/Http/Controllers/Admin/JadwalPelajaranController.php::store()` dan `::update()`. Tambahkan di baris pertama setelah `$kelas` di-resolve (nama variabel exact sesuaikan dengan yang ada di file):

```php
$lembagaId = $request->user()->widestScopeLevel() === 'yayasan' ? session('active_lembaga_id') : $request->user()->lembaga_id;
abort_if($kelas->lembaga_id !== $lembagaId, 404);
```

Tempatkan persis sebelum validasi FK guru/mapel/ruangan yang sudah ada, ikuti pola exact `duplicate()` (baris 424-441) yang sudah benar di file yang sama.

- [ ] **Step 4: Jalankan test lagi + regresi**

Run: `php artisan test --filter=JadwalPelajaran`
Expected: PASS semua, tidak ada regresi.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/JadwalPelajaranController.php tests/Feature/Admin/JadwalPelajaranTenantGuardTest.php
git commit -m "chore(akademik): tambah validasi tenant eksplisit di JadwalPelajaranController store/update"
```

---

## Final Step: Full Test Suite

- [ ] Pastikan tidak ada proses `php artisan test` lain berjalan (`ps aux | grep artisan`).
- [ ] Run: `php artisan test --compact` — SENDIRIAN.
- [ ] Expected: PASS, 0 failures.
- [ ] Run `vendor/bin/pint --dirty --format agent`, commit hasil format terpisah kalau ada perubahan.
- [ ] Tulis handoff log di `.agents/logs/2026-09-03-audit-akademik-perbaikan.md` merangkum ke-9 task (atau 8 kalau Task 9 di-skip), status tiap task, dan hasil full suite.

**Plan selesai ketika semua task Prioritas Tinggi & Sedang selesai (Task 9 opsional, boleh dilewati), full suite hijau, dan handoff log tertulis.**
