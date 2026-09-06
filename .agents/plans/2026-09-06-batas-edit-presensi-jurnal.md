# Batas Waktu Edit Presensi & Jurnal KBM Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Guru mapel biasa tidak bisa lagi mengedit presensi/jurnal sesi yang sudah melewati batas waktu tertentu (dikonfigurasi per-Lembaga, default 3 hari) — kecuali Wali Kelas kelas itu sendiri, yang tetap bebas edit kapan pun.

**Architecture:** 1 kolom baru `lembaga.batas_edit_absen_hari` (default 3) diatur admin lewat halaman Pengaturan Akademik yang sudah ada. `JurnalKbmController` menghitung status "terkunci" (tanggal sesi vs batas hari, kecuali wali kelas kelasnya sendiri) dan menampilkannya di `show()` sebelum guru sempat isi form, serta menegakkannya di `update()` sebelum data tersimpan.

**Tech Stack:** Laravel 12, PHP 8.3, Pest, Blade + Alpine.js.

## Global Constraints

- Basis cutoff: rolling N hari dari hari ini (bukan terikat status rapor/semester), dikonfigurasi per-Lembaga, default 3.
- Override HANYA Wali Kelas untuk sesi kelasnya sendiri — BUKAN Waka Kurikulum (itu Proyek B terpisah, di luar cakupan plan ini).
- Lingkup yang dikunci: SELURUH form (materi + presensi + keterangan), bukan sebagian field.
- `show()` WAJIB tampilkan status terkunci sebelum guru isi form — jangan baru gagal saat submit.
- Validasi nilai setting: integer 1-365.
- `update()` yang ditolak karena cutoff TIDAK BOLEH memanggil `RecordJurnalDanPresensiAction` sama sekali — fail sebelum ada perubahan data.
- JANGAN merusak fitur Scan Presensi Kartu Digital Siswa yang sudah ada di file yang sama (`show.blade.php`, `JurnalKbmController`) — itu fitur terpisah yang harus tetap utuh.
- Tidak pakai worktree, kerja langsung di branch `akademik-v2`.

---

## File Structure

- **Create**: migrasi `add_batas_edit_absen_hari_to_lembaga_table`
- **Modify**: `app/Models/Lembaga.php` — tambah `$fillable`
- **Create**: `app/Domains/Akademik/DataTransferObjects/BatasEditAbsenLembagaData.php`
- **Create**: `app/Domains/Akademik/Actions/Kalender/UpdateBatasEditAbsenLembagaAction.php`
- **Modify**: `app/Http/Controllers/Admin/PengaturanAkademikController.php` — tambah `updateBatasEditAbsen()`
- **Modify**: `routes/admin/akademik-master.php` — tambah 1 route
- **Modify**: `resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php` — tambah field baru
- **Modify**: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php` — tambah `sesiTerkunci()`, modifikasi `show()` & `update()`
- **Modify**: `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php` — banner + disable form saat terkunci
- **Create**: `.agents/logs/2026-09-06-batas-edit-presensi-jurnal.md`
- **Modify**: `PETA_PENGEMBANGAN.md`

---

### Task 1: Migrasi & Model `Lembaga`

**Files:**
- Create: `database/migrations/2026_09_06_000002_add_batas_edit_absen_hari_to_lembaga_table.php`
- Modify: `app/Models/Lembaga.php`
- Test: `tests/Unit/Domains/Akademik/LembagaBatasEditAbsenTest.php`

**Interfaces:**
- Produces: kolom `lembaga.batas_edit_absen_hari` (integer, default `3`, NOT NULL), field `batas_edit_absen_hari` di `$fillable` — dipakai Task 2 (Action) dan Task 5 (enforcement).

- [ ] **Step 1: Buat migrasi**

```bash
php artisan make:migration add_batas_edit_absen_hari_to_lembaga_table --no-interaction
```

Isi (ganti nama file jadi `2026_09_06_000002_add_batas_edit_absen_hari_to_lembaga_table.php` kalau timestamp auto-generate beda):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->unsignedSmallInteger('batas_edit_absen_hari')->default(3)->after('hari_libur_mingguan');
        });
    }

    public function down(): void
    {
        Schema::table('lembaga', function (Blueprint $table) {
            $table->dropColumn('batas_edit_absen_hari');
        });
    }
};
```

- [ ] **Step 2: Jalankan migrasi**

Run: `php artisan migrate`
Expected: `Migrated: ..._add_batas_edit_absen_hari_to_lembaga_table` tanpa error.

- [ ] **Step 3: Tulis test yang gagal**

```php
<?php

use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('lembaga baru otomatis dapat batas_edit_absen_hari default 3 tanpa disebut eksplisit', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    expect($lembaga->fresh()->batas_edit_absen_hari)->toBe(3);
});

it('batas_edit_absen_hari bisa diisi manual lewat mass assignment', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'batas_edit_absen_hari' => 7]);

    expect($lembaga->fresh()->batas_edit_absen_hari)->toBe(7);
});
```

- [ ] **Step 4: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Domains/Akademik/LembagaBatasEditAbsenTest.php --compact`
Expected: FAIL — test kedua gagal karena `batas_edit_absen_hari` belum ada di `$fillable` (mass assignment silently diabaikan Laravel, nilai tetap default 3, assertion `toBe(7)` gagal).

- [ ] **Step 5: Tambahkan ke `$fillable`**

Baca `app/Models/Lembaga.php`, tambahkan `'batas_edit_absen_hari'` ke akhir array `$fillable` yang sudah ada (setelah `'hari_libur_mingguan_sdm'`):

```php
    protected $fillable = [
        'yayasan_id', 'npsn', 'nss', 'nama', 'slug', 'kode_lembaga', 'bentuk_pendidikan', 'status_sekolah',
        'status_kepemilikan', 'naungan', 'sk_pendirian_nomor', 'sk_pendirian_tanggal',
        'sk_izin_operasional_nomor', 'sk_izin_operasional_tanggal', 'akreditasi',
        'sk_akreditasi_nomor', 'tanggal_sk_akreditasi', 'nama_kepala_sekolah', 'nama_bendahara_bosp',
        'alamat_jalan', 'rt', 'rw', 'nama_dusun', 'desa_kelurahan', 'kecamatan',
        'kabupaten_kota', 'provinsi', 'kode_pos', 'lintang', 'bujur',
        'telepon', 'fax', 'email', 'website',
        'nama_bank', 'cabang_kcp_unit', 'rekening_atas_nama', 'nomor_rekening',
        'mbs', 'nama_wajib_pajak', 'npwp',
        'status_aktif', 'hari_libur_mingguan', 'hari_libur_mingguan_sdm', 'batas_edit_absen_hari',
    ];
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Domains/Akademik/LembagaBatasEditAbsenTest.php --compact`
Expected: **2 passed**.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_06_000002_add_batas_edit_absen_hari_to_lembaga_table.php app/Models/Lembaga.php tests/Unit/Domains/Akademik/LembagaBatasEditAbsenTest.php
git commit -m "feat(akademik): kolom batas_edit_absen_hari di Lembaga -- default 3 hari

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: DTO & Action Pengaturan

**Files:**
- Create: `app/Domains/Akademik/DataTransferObjects/BatasEditAbsenLembagaData.php`
- Create: `app/Domains/Akademik/Actions/Kalender/UpdateBatasEditAbsenLembagaAction.php`
- Test: `tests/Unit/Domains/Akademik/UpdateBatasEditAbsenLembagaActionTest.php`

**Interfaces:**
- Produces: `BatasEditAbsenLembagaData` (`final readonly class`, `public int $batasHari`), `UpdateBatasEditAbsenLembagaAction::execute(Lembaga $lembaga, BatasEditAbsenLembagaData $data): Lembaga` — dipakai Task 3 (controller).

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Actions\Kalender\UpdateBatasEditAbsenLembagaAction;
use App\Domains\Akademik\DataTransferObjects\BatasEditAbsenLembagaData;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('mengubah batas_edit_absen_hari lembaga sesuai DTO', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'batas_edit_absen_hari' => 3]);

    $hasil = (new UpdateBatasEditAbsenLembagaAction)->execute($lembaga, new BatasEditAbsenLembagaData(batasHari: 7));

    expect($hasil->batas_edit_absen_hari)->toBe(7);
    expect($lembaga->fresh()->batas_edit_absen_hari)->toBe(7);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Unit/Domains/Akademik/UpdateBatasEditAbsenLembagaActionTest.php --compact`
Expected: FAIL — `Class "App\Domains\Akademik\DataTransferObjects\BatasEditAbsenLembagaData" not found`.

- [ ] **Step 3: Tulis DTO**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class BatasEditAbsenLembagaData
{
    public function __construct(
        public int $batasHari,
    ) {}
}
```

- [ ] **Step 4: Tulis Action**

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Kalender;

use App\Domains\Akademik\DataTransferObjects\BatasEditAbsenLembagaData;
use App\Models\Lembaga;

final class UpdateBatasEditAbsenLembagaAction
{
    public function execute(Lembaga $lembaga, BatasEditAbsenLembagaData $data): Lembaga
    {
        $lembaga->update(['batas_edit_absen_hari' => $data->batasHari]);

        return $lembaga->fresh();
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Unit/Domains/Akademik/UpdateBatasEditAbsenLembagaActionTest.php --compact`
Expected: **1 passed**.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Akademik/DataTransferObjects/BatasEditAbsenLembagaData.php app/Domains/Akademik/Actions/Kalender/UpdateBatasEditAbsenLembagaAction.php tests/Unit/Domains/Akademik/UpdateBatasEditAbsenLembagaActionTest.php
git commit -m "feat(akademik): DTO & Action UpdateBatasEditAbsenLembagaAction

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Endpoint Admin Pengaturan Akademik

**Files:**
- Modify: `app/Http/Controllers/Admin/PengaturanAkademikController.php`
- Modify: `routes/admin/akademik-master.php`
- Test: `tests/Feature/Admin/PengaturanBatasEditAbsenTest.php`

**Interfaces:**
- Consumes: `UpdateBatasEditAbsenLembagaAction::execute()` (Task 2).
- Produces: `PUT admin/pengaturan/akademik/batas-edit-absen` (`admin.pengaturan.akademik.batas-edit-absen`) — 200 `{data: {batas_edit_absen_hari}}` atau 422 error validasi.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function buatAdminLembagaDenganPermission(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'pengaturan-akademik.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'operator_akademik_test', 'guard_name' => 'web']);
    $role->givePermissionTo('pengaturan-akademik.kelola');

    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($role);

    return $user;
}

it('admin dgn permission bisa update batas edit absen dgn nilai valid', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'batas_edit_absen_hari' => 3]);
    $admin = buatAdminLembagaDenganPermission($lembaga);

    $response = $this->actingAs($admin)->putJson(route('admin.pengaturan.akademik.batas-edit-absen'), ['batas_edit_absen_hari' => 10]);

    $response->assertOk();
    $response->assertJson(['data' => ['batas_edit_absen_hari' => 10]]);
    expect($lembaga->fresh()->batas_edit_absen_hari)->toBe(10);
});

it('menolak nilai di luar rentang 1-365', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $admin = buatAdminLembagaDenganPermission($lembaga);

    $response = $this->actingAs($admin)->putJson(route('admin.pengaturan.akademik.batas-edit-absen'), ['batas_edit_absen_hari' => 0]);

    $response->assertStatus(422);
});

it('menolak user tanpa permission pengaturan-akademik.kelola', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $response = $this->actingAs($user)->putJson(route('admin.pengaturan.akademik.batas-edit-absen'), ['batas_edit_absen_hari' => 10]);

    $response->assertStatus(403);
});
```

> Catatan: sesuaikan cara resolve "lembaga aktif" (`resolveActiveLembagaId`) dengan pola yang dipakai `updateHariAktif()` yang sudah ada — kalau ternyata butuh `session('active_lembaga_id')` untuk aktor yayasan (bukan `lembaga_id` langsung di User), PERIKSA LANGSUNG trait `ResolveLembagaScopeTrait` sebelum menulis test ini, sesuaikan setup test kalau perlu.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Admin/PengaturanBatasEditAbsenTest.php --compact`
Expected: FAIL — route `admin.pengaturan.akademik.batas-edit-absen` tidak ditemukan.

- [ ] **Step 3: Tambah route**

Di `routes/admin/akademik-master.php`, tambahkan baris ini setelah baris `pengaturan.akademik.hari-aktif`:

```php
Route::put('pengaturan/akademik/batas-edit-absen', [PengaturanAkademikController::class, 'updateBatasEditAbsen'])->name('pengaturan.akademik.batas-edit-absen');
```

- [ ] **Step 4: Tambah method controller**

Tambahkan ke `app/Http/Controllers/Admin/PengaturanAkademikController.php`, setelah method `updateHariAktif()`, dan tambahkan `use` statement untuk `UpdateBatasEditAbsenLembagaAction` dan `BatasEditAbsenLembagaData` di bagian atas file:

```php
use App\Domains\Akademik\Actions\Kalender\UpdateBatasEditAbsenLembagaAction;
use App\Domains\Akademik\DataTransferObjects\BatasEditAbsenLembagaData;
```

```php
    public function updateBatasEditAbsen(Request $request, UpdateBatasEditAbsenLembagaAction $action): JsonResponse
    {
        $this->authorize('pengaturan-akademik.kelola');

        $lembagaId = $this->resolveActiveLembagaId($request->user());
        if ($lembagaId === null) {
            return response()->json([
                'message' => 'Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.',
                'errors' => ['lembaga_id' => ['Pilih lembaga aktif melalui pengalih lembaga terlebih dahulu.']],
            ], 422);
        }

        $data = $request->validate([
            'batas_edit_absen_hari' => ['required', 'integer', 'between:1,365'],
        ]);

        $lembaga = Lembaga::findOrFail($lembagaId);

        $lembaga = $action->execute($lembaga, new BatasEditAbsenLembagaData(batasHari: $data['batas_edit_absen_hari']));

        return response()->json(['data' => ['batas_edit_absen_hari' => $lembaga->batas_edit_absen_hari]]);
    }
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Admin/PengaturanBatasEditAbsenTest.php --compact`
Expected: **3 passed**.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/PengaturanAkademikController.php routes/admin/akademik-master.php tests/Feature/Admin/PengaturanBatasEditAbsenTest.php
git commit -m "feat(akademik): endpoint update batas edit absen di Pengaturan Akademik

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: View Pengaturan Akademik

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php`

**Interfaces:**
- Consumes: `PUT admin.pengaturan.akademik.batas-edit-absen` (Task 3), `$lembaga->batas_edit_absen_hari`, `$bolehKelolaHariAktif` (variabel existing, reuse sebagai penentu boleh-kelola untuk field baru ini juga — permission-nya sama, `pengaturan-akademik.kelola`).

- [ ] **Step 1: Tambah field baru di tab "Hari Aktif Sekolah"**

Di `resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php`, tambahkan blok baru di dalam `<div x-show="tab === 'hari-aktif'" ...>` (yang sudah ada), setelah blok grid hari (`<div class="mt-4 grid grid-cols-2 gap-2.5 sm:grid-cols-4">...</div>`) dan sebelum tombol "Simpan Hari Aktif":

```blade
                <div class="mt-6 border-t border-gray-100 pt-5" x-data="{
                    batasHari: {{ (int) $lembaga->batas_edit_absen_hari }},
                    submittingBatas: false,
                    async simpanBatas() {
                        this.submittingBatas = true;
                        try {
                            const response = await fetch(@js(route('admin.pengaturan.akademik.batas-edit-absen')), {
                                method: 'PUT',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                },
                                body: JSON.stringify({ batas_edit_absen_hari: this.batasHari }),
                            });
                            const json = await response.json();
                            if (!response.ok) {
                                Alpine.store('toast').push('error', json.message ?? 'Gagal menyimpan batas waktu edit.');
                                return;
                            }
                            Alpine.store('toast').push('success', 'Batas waktu edit presensi berhasil disimpan.');
                        } catch (error) {
                            Alpine.store('toast').push('error', 'Gagal menyimpan batas waktu edit.');
                        } finally {
                            this.submittingBatas = false;
                        }
                    },
                }">
                    <p class="font-display text-sm font-bold text-gray-900">Batas Waktu Edit Presensi</p>
                    <p class="mt-1 text-sm text-gray-500">Guru mapel biasa tidak bisa lagi mengedit presensi/jurnal sesi yang lebih tua dari jumlah hari ini. Wali Kelas tetap bisa mengedit sesi kelasnya sendiri kapan pun, tanpa batas.</p>

                    <div class="mt-3 flex items-center gap-3">
                        <input
                            type="number"
                            min="1"
                            max="365"
                            x-model.number="batasHari"
                            :disabled="!{{ $bolehKelolaHariAktif ? 'true' : 'false' }}"
                            class="w-28 rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-gray-50"
                        >
                        <span class="text-sm text-gray-500">hari</span>
                    </div>

                    @can('pengaturan-akademik.kelola')
                        <div class="mt-3">
                            <x-primary-button type="button" x-bind:disabled="submittingBatas" @click="simpanBatas()">Simpan Batas Waktu Edit</x-primary-button>
                        </div>
                    @endcan
                </div>
```

- [ ] **Step 2: Verifikasi manual di browser**

Jalankan dev server (`php artisan serve` atau `composer run dev`), login sebagai akun dengan permission `pengaturan-akademik.kelola` (cek seeder demo untuk akun yang tepat — kemungkinan `kurikulum.sd@demo.test`), buka `admin/pengaturan/akademik`, pastikan:
- Field baru "Batas Waktu Edit Presensi" tampil di tab "Hari Aktif Sekolah" dengan nilai awal `3`.
- Ubah ke angka lain (misal `7`), klik "Simpan Batas Waktu Edit", toast sukses muncul.
- Refresh halaman, nilai tetap `7` (tersimpan).
- Coba isi angka `0` atau lebih dari `365`, submit — pastikan tidak error 500 (toast error muncul, bukan crash).

Laporkan hasil verifikasi ini secara eksplisit di laporan task.

- [ ] **Step 3: Commit**

```bash
git add resources/views/portals/lembaga/akademik/pengaturan/akademik.blade.php
git commit -m "feat(akademik): field Batas Waktu Edit Presensi di halaman Pengaturan Akademik

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Enforcement di `JurnalKbmController`

**Files:**
- Modify: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`
- Test: `tests/Feature/Guru/JurnalKbmBatasEditTest.php`

**Interfaces:**
- Consumes: `Lembaga::batas_edit_absen_hari` (Task 1), `Kelas::wali_kelas_guru_id` (existing).
- Produces: `JurnalKbmController::sesiTerkunci(SesiPembelajaran $sesi, Guru $guru): bool` (private) — dipakai Task 6 (view menerima hasil hitungnya lewat variabel `terkunci`).

**PENTING**: baca dulu isi `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php` SAAT INI (sudah berubah beberapa kali sesi-sesi sebelumnya untuk fitur resolve-kartu Kartu Digital Siswa) — pastikan modifikasimu tidak menghapus/mengubah method `resolveKartu()` atau `authorizeMilikGuru()` yang sudah ada.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanGuruMapelBiasa(int $batasEditHari = 3): array
{
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_batas_edit_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'batas_edit_absen_hari' => $batasEditHari]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Person::where('id', $guru->person_id)->update(['user_id' => $user->id]);
    $user->assignRole($role);

    $jadwal = JadwalPelajaran::factory()->create(['guru_id' => $guru->id, 'kelas_id' => $kelas->id]);

    return compact('user', 'guru', 'lembaga', 'kelas', 'jadwal');
}

it('guru mapel biasa bisa edit sesi dalam batas hari', function () {
    ['user' => $user, 'guru' => $guru, 'kelas' => $kelas, 'jadwal' => $jadwal] = siapkanGuruMapelBiasa(3);
    $sesi = SesiPembelajaran::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'jadwal_pelajaran_id' => $jadwal->id,
        'lembaga_id' => $guru->lembaga_id, 'tanggal' => now()->subDays(2)->toDateString(),
    ]);

    $response = $this->actingAs($user)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Materi baru', 'presensi' => [],
    ]);

    $response->assertRedirect(route('guru.jurnal-kbm.index'));
    $response->assertSessionHas('status');
    expect($sesi->fresh()->materi)->toBe('Materi baru');
});

it('guru mapel biasa DITOLAK edit sesi di luar batas hari, tidak ada perubahan tersimpan', function () {
    ['user' => $user, 'guru' => $guru, 'kelas' => $kelas, 'jadwal' => $jadwal] = siapkanGuruMapelBiasa(3);
    $sesi = SesiPembelajaran::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'jadwal_pelajaran_id' => $jadwal->id,
        'lembaga_id' => $guru->lembaga_id, 'tanggal' => now()->subDays(10)->toDateString(), 'materi' => 'Materi lama',
    ]);

    $response = $this->actingAs($user)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Coba diubah', 'presensi' => [],
    ]);

    $response->assertRedirect(route('guru.jurnal-kbm.index'));
    $response->assertSessionHas('error');
    expect($sesi->fresh()->materi)->toBe('Materi lama');
});

it('wali kelas kelasnya sendiri BISA edit sesi di luar batas hari', function () {
    ['user' => $user, 'guru' => $guru, 'kelas' => $kelas, 'jadwal' => $jadwal] = siapkanGuruMapelBiasa(3);
    $kelas->update(['wali_kelas_guru_id' => $guru->id]);
    $sesi = SesiPembelajaran::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'jadwal_pelajaran_id' => $jadwal->id,
        'lembaga_id' => $guru->lembaga_id, 'tanggal' => now()->subDays(10)->toDateString(),
    ]);

    $response = $this->actingAs($user)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Koreksi wali kelas', 'presensi' => [],
    ]);

    $response->assertRedirect(route('guru.jurnal-kbm.index'));
    $response->assertSessionHas('status');
    expect($sesi->fresh()->materi)->toBe('Koreksi wali kelas');
});

it('wali kelas KELAS LAIN tetap DITOLAK edit sesi di luar batas hari', function () {
    ['user' => $user, 'guru' => $guru, 'kelas' => $kelas, 'jadwal' => $jadwal, 'lembaga' => $lembaga] = siapkanGuruMapelBiasa(3);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'wali_kelas_guru_id' => $guru->id]);
    $sesi = SesiPembelajaran::factory()->create([
        'guru_id' => $guru->id, 'kelas_id' => $kelas->id, 'jadwal_pelajaran_id' => $jadwal->id,
        'lembaga_id' => $lembaga->id, 'tanggal' => now()->subDays(10)->toDateString(), 'materi' => 'Materi lama',
    ]);

    $response = $this->actingAs($user)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Coba diubah', 'presensi' => [],
    ]);

    $response->assertSessionHas('error');
    expect($sesi->fresh()->materi)->toBe('Materi lama');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Guru/JurnalKbmBatasEditTest.php --compact`
Expected: FAIL — semua test gagal karena belum ada pengecekan cutoff (kasus "di luar batas" saat ini masih berhasil, `assertSessionHas('error')` gagal).

- [ ] **Step 3: Tambah `use App\Models\Guru;` dan method `sesiTerkunci()`**

Tambahkan `use App\Models\Guru;` ke bagian atas `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php` (setelah `use App\Enums\Hari;`), lalu tambahkan private method baru setelah `authorizeMilikGuru()`:

```php
    private function sesiTerkunci(SesiPembelajaran $sesi, Guru $guru): bool
    {
        $sesi->loadMissing('kelas');

        if ($sesi->kelas->wali_kelas_guru_id === $guru->id) {
            return false;
        }

        $batasHari = $guru->lembaga->batas_edit_absen_hari ?? 3;

        return $sesi->tanggal->lt(now()->subDays($batasHari)->startOfDay());
    }
```

- [ ] **Step 4: Modifikasi `update()`**

Ganti method `update()` yang ada:

```php
    public function update(UpdateJurnalPresensiRequest $request, SesiPembelajaran $sesi): RedirectResponse
    {
        $this->authorize('presensi.isi');
        // Ownership check is already enforced by UpdateJurnalPresensiRequest::authorize(),
        // which runs before this method body — no need to call authorizeMilikGuru() again here.

        $guru = $request->user()->guru;

        if ($this->sesiTerkunci($sesi, $guru)) {
            $batasHari = $guru->lembaga->batas_edit_absen_hari ?? 3;

            return redirect()->route('guru.jurnal-kbm.index')
                ->with('error', "Sesi ini sudah melewati batas waktu edit ({$batasHari} hari). Hubungi Wali Kelas kelas ini untuk koreksi.");
        }

        $this->recordJurnalDanPresensiAction->execute($sesi, $request->toDTO());

        return redirect()->route('guru.jurnal-kbm.index')->with('status', 'Jurnal dan presensi berhasil disimpan.');
    }
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Guru/JurnalKbmBatasEditTest.php --compact`
Expected: **4 passed**.

- [ ] **Step 6: Modifikasi `show()` (untuk dipakai Task 6)**

Ganti method `show()` yang ada:

```php
    public function show(SesiPembelajaran $sesi): View
    {
        $this->authorize('presensi.isi');
        $this->authorizeMilikGuru($sesi);

        $sesi->loadMissing('kelas.tahunAjaran');
        $mapelTerjadwal = $this->mapelTerjadwalUntukSesiTematik(collect([$sesi]), $sesi->tanggal);

        $guru = auth()->user()->guru;
        $terkunci = $this->sesiTerkunci($sesi, $guru);

        return view('portals.guru.akademik.jurnal-kbm.show', [
            'sesi' => $sesi,
            'presensiList' => $sesi->presensi()->with('siswa')->get(),
            'mapelTerjadwal' => $mapelTerjadwal[$sesi->kelas_id] ?? null,
            'terkunci' => $terkunci,
            'batasEditHari' => $guru->lembaga->batas_edit_absen_hari ?? 3,
        ]);
    }
```

- [ ] **Step 7: Jalankan ulang test existing, pastikan tidak regresi**

Run: `php artisan test tests/Feature/Guru/JurnalKbmControllerTest.php tests/Feature/Guru/JurnalKbmResolveKartuTest.php tests/Feature/Akademik/JurnalKbmTanggalSusulanTest.php --compact`
Expected: semua test lama tetap **passed** (kalau ada yang gagal karena view sekarang butuh variabel `terkunci`/`batasEditHari` yang belum dikirim di test lama, itu wajar — Task 6 akan menambahkan variabel itu ke view; pastikan test yang MEMANGGIL `show()` langsung via HTTP tetap lulus karena controller sudah mengirim keduanya).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Guru/Akademik/JurnalKbmController.php tests/Feature/Guru/JurnalKbmBatasEditTest.php
git commit -m "feat(akademik): enforcement batas edit absen di JurnalKbmController -- wali kelas dikecualikan

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Frontend — Banner & Disable Form Saat Terkunci

**Files:**
- Modify: `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php`
- Modify: `tests/Feature/Guru/JurnalKbmControllerTest.php`

**Interfaces:**
- Consumes: variabel `$terkunci` (bool) dan `$batasEditHari` (int) dari `show()` (Task 5).

**PENTING**: baca dulu isi `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php` SAAT INI (sudah berubah signifikan untuk fitur Scan Presensi Kartu Digital Siswa) — JANGAN hapus/rusak blok Scan Presensi atau event bus `@presensi-scanned.window` yang sudah ada.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Guru/JurnalKbmControllerTest.php` (file existing):

```php
it('menampilkan banner terkunci utk sesi yang sudah lewat batas waktu edit', function () {
    ['guruUser' => $guruUser, 'siswa' => $siswa] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));
    $sesi = \App\Domains\Akademik\Models\SesiPembelajaran::firstOrFail();
    $sesi->update(['tanggal' => now()->subDays(10)->toDateString()]);

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.show', $sesi));

    $response->assertOk();
    $response->assertSee('sudah melewati batas waktu edit');
});

it('tidak menampilkan banner terkunci utk sesi yang masih dalam batas waktu edit', function () {
    ['guruUser' => $guruUser] = siapkanGuruDenganJadwalHariIni();
    $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.index'));
    $sesi = \App\Domains\Akademik\Models\SesiPembelajaran::firstOrFail();

    $response = $this->actingAs($guruUser)->get(route('guru.jurnal-kbm.show', $sesi));

    $response->assertOk();
    $response->assertDontSee('sudah melewati batas waktu edit');
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test tests/Feature/Guru/JurnalKbmControllerTest.php --compact --filter="batas waktu edit"`
Expected: FAIL — teks banner belum ada di view.

- [ ] **Step 3: Modifikasi view**

Di `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php`, tambahkan banner baru persis setelah blok `@if ($errors->any())` yang sudah ada (sebelum "Header & Breadcrumb"):

```blade
        @if ($terkunci)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Sesi ini sudah melewati batas waktu edit ({{ $batasEditHari }} hari).</p>
                <p class="mt-1 text-xs">Form di bawah ditampilkan hanya untuk dilihat. Hubungi Wali Kelas kelas ini kalau perlu koreksi.</p>
            </div>
        @endif
```

Ubah tag `<textarea name="materi" ...>` (Section 1: Jurnal) — tambahkan atribut `@disabled($terkunci)`:

```blade
                        <textarea
                            name="materi"
                            rows="3"
                            placeholder="Contoh: Pembahasan Aljabar, Latihan Soal Halaman 20, dll."
                            class="mt-1.5 w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-gray-50"
                            @disabled($terkunci)
                        >{{ old('materi', $sesi->materi) }}</textarea>
```

Bungkus SELURUH blok "Scan Presensi via Kartu Digital" dengan `@unless($terkunci) ... @endunless` (kalau terkunci, blok scan tidak perlu ditampilkan sama sekali — percuma scan kalau submit akan ditolak):

```blade
                    @unless ($terkunci)
                    {{-- Scan Presensi via Kartu Digital --}}
                    <div ...>
                        ...
                    </div>
                    @endunless
```

(Bungkus blok yang sudah ada apa adanya di antara `@unless`/`@endunless` — JANGAN mengubah isi blok itu sendiri.)

Tambahkan `:disabled="{{ $terkunci ? 'true' : 'false' }}"` ke setiap `<input type="radio" ...>` di dalam loop status presensi:

```blade
                                                            <input 
                                                                type="radio" 
                                                                name="presensi[{{ $presensi->siswa_id }}]" 
                                                                value="{{ $status->value }}" 
                                                                x-model="status" 
                                                                :disabled="{{ $terkunci ? 'true' : 'false' }}"
                                                                class="sr-only"
                                                            >
```

Tambahkan `@disabled($terkunci)` ke `<input type="text" name="keterangan[...]" ...>`:

```blade
                                                <template x-if="status === 'izin' || status === 'sakit'">
                                                    <input
                                                        type="text"
                                                        name="keterangan[{{ $presensi->siswa_id }}]"
                                                        value="{{ old('keterangan.'.$presensi->siswa_id, $presensi->keterangan) }}"
                                                        placeholder="Contoh: Demam, surat dari orang tua, dll."
                                                        class="w-full rounded-lg border-gray-200 text-xs text-gray-900 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:bg-gray-50"
                                                        @disabled($terkunci)
                                                    >
                                                </template>
```

Tambahkan `@disabled($terkunci)` ke tombol submit di footer:

```blade
                    <x-primary-button type="submit" class="shadow-sm transition-all duration-200 active:scale-[0.98]" @disabled($terkunci)>
                        Simpan Jurnal &amp; Presensi
                    </x-primary-button>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test tests/Feature/Guru/JurnalKbmControllerTest.php --compact`
Expected: semua test (lama + 2 baru) **passed**.

- [ ] **Step 5: Verifikasi manual di browser**

Buka sesi yang tanggalnya lebih dari 3 hari lalu (bisa pakai tinker untuk update tanggal sesi demo), pastikan: banner kuning muncul, semua field terlihat nonaktif (abu-abu), tombol Simpan tidak bisa diklik, blok Scan Presensi tidak muncul. Buka sesi hari ini, pastikan semua normal seperti sebelumnya (termasuk tombol Scan Presensi tetap ada). Laporkan hasil verifikasi ini di laporan task.

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php tests/Feature/Guru/JurnalKbmControllerTest.php
git commit -m "feat(akademik): banner & disable form Jurnal KBM saat sesi terkunci batas edit

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 7: Penutup — Full Test Suite & Dokumentasi

**Files:**
- Create: `.agents/logs/2026-09-06-batas-edit-presensi-jurnal.md`
- Modify: `PETA_PENGEMBANGAN.md`

- [ ] **Step 1: Pastikan tidak ada proses test lain berjalan**

Run (PowerShell): `Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Select ProcessId,CommandLine`

- [ ] **Step 2: Full test suite**

Run: `php artisan test --compact`
Expected: 0 kegagalan baru dibanding baseline sebelum plan ini (baseline terakhir yang diketahui: ~2872 passed, 4 gagal pre-existing tidak terkait — bandingkan angka `passed` naik sejumlah test baru plan ini, 4 kegagalan lama tetap sama).

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` atau `"fixed"`.

- [ ] **Step 4: Tulis handoff log**

Tulis `.agents/logs/2026-09-06-batas-edit-presensi-jurnal.md` — ringkas per task, commit hash, hasil test, hasil 2 verifikasi manual browser (Task 4 & Task 6).

- [ ] **Step 5: Update `PETA_PENGEMBANGAN.md`**

Cari paragraf yang baru ditambahkan (2026-09-06) soal "Item dengan keputusan DITUNDA sengaja ... presensi/jurnal ... cutoff" (di dekat "Status Akhir Audit Sistematis Akademik Tahap 2"). JANGAN hapus paragraf itu — tambahkan 1 baris SETELAHNYA yang menandai ini SELESAI, dengan tanggal & referensi handoff log baru, dan catat eksplisit bahwa Proyek B (Waka Kurikulum akses lintas-guru) dan Proyek C (guru piket) masih backlog terpisah, belum dikerjakan.

- [ ] **Step 6: Commit**

```bash
git add .agents/logs/2026-09-06-batas-edit-presensi-jurnal.md PETA_PENGEMBANGAN.md
git commit -m "docs(akademik): handoff log & update roadmap -- batas edit presensi jurnal selesai

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Cakupan spec**: §2 (8 poin keputusan) semua tercermin — poin 1 (Task 1, kolom baru), poin 2 (Task 1, `$fillable`), poin 3 (Task 5, cek wali kelas kelasnya sendiri), poin 4 (TIDAK ada kode Waka Kurikulum di plan ini, sesuai), poin 5 (Task 6, seluruh form dikunci), poin 6 (Task 5+6, `show()` kirim status sebelum submit), poin 7 (Task 3, validasi 1-365), poin 8 (Task 5, `sesiTerkunci()` persis rumus itu). §3.1-3.3 masing-masing dapat task sendiri (Task 1 / Task 2-4 / Task 5-6). §4 (9 skenario test) tercakup: #1-4 di Task 5, #5-6 di Task 6, #7 di Task 3, #8 di Task 1, #9 di Task 5 Step 7.

**2. Placeholder scan**: semua step berisi kode lengkap. 1 catatan "PERIKSA LANGSUNG" di Task 3 (soal `resolveActiveLembagaId`) adalah instruksi verifikasi eksplisit yang actionable, bukan placeholder — trait itu sudah dipakai di method `index()`/`updateHariAktif()` yang sama, implementer cukup baca pola yang sudah ada.

**3. Konsistensi tipe**: `BatasEditAbsenLembagaData` (Task 2, `public int $batasHari`) dipakai identik di Task 3 (`new BatasEditAbsenLembagaData(batasHari: ...)`). `UpdateBatasEditAbsenLembagaAction::execute(Lembaga $lembaga, BatasEditAbsenLembagaData $data): Lembaga` (Task 2) dipakai identik di Task 3. `sesiTerkunci(SesiPembelajaran $sesi, Guru $guru): bool` (Task 5) — signature sama dipakai di `update()` dan `show()` di task yang sama, variabel `$terkunci`/`$batasEditHari` yang dikirim `show()` (Task 5) dipakai identik namanya di view (Task 6).
