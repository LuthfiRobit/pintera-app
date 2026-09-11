# Perbaikan Audit Jadwal Piket Guru Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menutup 7 kelompok temuan audit (3 High, 4 Medium) pada fitur Jadwal Piket Guru — gerbang keamanan yang menentukan siapa boleh mengisi jurnal/presensi sesi guru lain — plus 1 pass konsistensi UI/UX sesuai standar proyek.

**Architecture:** 8 task independen-sebisa-mungkin di domain Akademik (`app/Domains/Akademik/Actions/Piket/*`, `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php`, `app/Http/Controllers/{Admin,Guru/Akademik}/*`, 2 view Blade, test baru). TIDAK menyentuh `app/Domains/Workflow/*`.

**Tech Stack:** Laravel 12 (PHP 8.3), Pest (test file fitur ini pakai Pest function-style `it(...)` dengan helper function biasa), Blade, Alpine.js, TomSelect (via `tomSelectPegawai` Alpine component yang sudah ada).

## Global Constraints

- Task 1 (timezone) HARUS pakai `now('Asia/Jakarta')` di titik-titik SPESIFIK yang disebut di bawah — JANGAN mengubah `config/app.php` atau `.env` `APP_TIMEZONE` secara global, itu SENGAJA ditolak (butuh audit terpisah, blast radius terlalu besar melampaui fitur ini).
- Task 1 WAJIB implementer verifikasi dulu bagaimana `Carbon::setTestNow()` berinteraksi dengan `now('Asia/Jakarta')` SEBELUM menulis assertion test baru — JANGAN diasumsikan otomatis benar.
- Task 4 (`diisi_oleh_guru_id`) PALING BERISIKO REGRESI — WAJIB jalankan ulang `tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php` SEBELUM task lain dianggap final, verifikasi asumsi null→null no-op benar-benar teruji, bukan cuma dipercaya dari pembacaan kode.
- Task 3 (kalender admin) HARUS aditif — JANGAN mengganti/menghapus variabel `overrides` yang sudah ada dan dipakai form override existing, TAMBAH variabel baru `piketHarianMendatang` di sampingnya.
- 4 item SENGAJA TIDAK masuk scope, JANGAN dikerjakan: perubahan `APP_TIMEZONE` global, validasi semester overlap, race condition locking (`lockForUpdate`), item Fase 2 (LaporanPiket, alur verifikasi Kepala Sekolah, cetak dokumen, dll — sudah di-exclude spec `.agents/specs/2026-09-06-guru-piket-jurnal-kbm.md` §5).
- JANGAN sentuh `app/Domains/Workflow/*` sama sekali.

---

## Task 1: Timezone Scoped `Asia/Jakarta` + Perbaikan Mismatch Tanggal

**Files:**
- Modify: `app/Domains/Akademik/Actions/Piket/GenerateJadwalPiketHarianAction.php`
- Modify: `app/Domains/Akademik/Actions/Piket/RegenerateJadwalPiketHarianAction.php`
- Modify: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`
- Test: `tests/Feature/Guru/JurnalKbmSesiPiketTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: tidak ada interface baru untuk task lain — perubahan murni internal ke logic "hari ini".

- [ ] **Step 1: Tulis test yang gagal — guru piket tetap bisa akses di jam pagi WIB yang setara "kemarin" di UTC**

**WAJIB dilakukan lebih dulu sebelum menulis assertion**: jalankan eksperimen kecil untuk memverifikasi interaksi `Carbon::setTestNow()` dengan `now('Asia/Jakarta')`. Jalankan:
```bash
php artisan tinker --execute '
Carbon\Carbon::setTestNow(Carbon\Carbon::parse("2026-08-19 23:30:00", "Asia/Jakarta"));
echo now()->toDateTimeString() . " (bare now(), app timezone " . config("app.timezone") . ")\n";
echo now("Asia/Jakarta")->toDateTimeString() . " (now Asia/Jakarta)\n";
echo now("Asia/Jakarta")->toDateString() . " (tanggal Asia/Jakarta)\n";
'
```
Amati hasilnya: `Carbon::setTestNow()` men-set "waktu absolut" (instant), dan `now('Asia/Jakarta')` akan mengonversi instant itu ke timezone yang diminta — jadi kalau `setTestNow` di-set ke jam 23:30 WIB (yang setara 16:30 UTC hari yang SAMA di kasus ini, TIDAK melewati batas hari), pilih waktu test yang BENAR-BENAR melewati batas hari UTC vs WIB untuk membuktikan perbaikan. Titik kritis: **00:00–06:59 WIB = 17:00–23:59 UTC hari SEBELUMNYA**. Contoh yang benar: WIB `2026-08-20 03:00:00` (dini hari) setara UTC `2026-08-19 20:00:00` (masih hari sebelumnya). Pakai contoh ini di test.

Tambahkan di `tests/Feature/Guru/JurnalKbmSesiPiketTest.php`, di akhir file (baca dulu isi file untuk pola helper yang sudah ada — kemungkinan besar mirip `siapkanSesiDanGuruPiketUntukAksesTest()` di `JurnalKbmPiketAksesTest.php`, reuse pola construksi Yayasan/Lembaga/Guru/PiketHarian yang sama, JANGAN duplikasi helper yang fungsinya sama):

```php
it('guru piket tetap terdeteksi piket pada jam dini hari WIB yang setara hari sebelumnya di UTC', function () {
    // 03:00 WIB tanggal 20 = 20:00 UTC tanggal 19 (hari SEBELUMNYA jika server pakai UTC bare now()).
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-08-20 03:00:00', 'Asia/Jakarta'));

    $yayasan = \App\Models\Yayasan::factory()->create();
    $lembaga = \App\Models\Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    \Illuminate\Support\Facades\Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = \App\Models\Role::firstOrCreate(['name' => 'guru_timezone_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $guruPiket = \App\Models\Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userPiket = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Identity\Models\Person::where('id', $guruPiket->person_id)->update(['user_id' => $userPiket->id]);
    $userPiket->assignRole($role);

    // Baris PiketHarian untuk "20 Agustus" (tanggal WIB sungguhan saat ini).
    \App\Domains\Akademik\Models\PiketHarian::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guruPiket->id, 'tanggal' => '2026-08-20', 'sumber' => 'override_manual',
    ]);

    $response = $this->actingAs($userPiket)->get(route('guru.jurnal-kbm.index'));

    $response->assertOk();
    $response->assertSee('Sesi Piket Hari Ini');

    \Carbon\Carbon::setTestNow();
});
```

**Catatan**: import `use Illuminate\Support\Facades\Permission;` di atas SALAH (namespace yang benar `Spatie\Permission\Models\Permission`) — SUDAH ditulis dengan namespace penuh `\Illuminate\Support\Facades\Permission` di kode di atas sebagai PENGINGAT untuk implementer memverifikasi & memperbaiki ke namespace yang benar (`Spatie\Permission\Models\Permission`) sebelum menjalankan — kalau file test target sudah punya `use` statement untuk ini di bagian atas file, pakai `Permission::firstOrCreate(...)` tanpa prefix namespace sama sekali, ikuti pola import yang SUDAH ADA di file itu.

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Guru/JurnalKbmSesiPiketTest.php --filter="guru piket tetap terdeteksi piket pada jam dini hari"`
Expected: FAIL — `assertSee('Sesi Piket Hari Ini')` gagal karena `now()->toDateString()` (bare, UTC) mengevaluasi ke `2026-08-19` (bukan `2026-08-20`), tidak match baris `PiketHarian` yang dibuat untuk tanggal `2026-08-20`.

- [ ] **Step 3: Ubah `GenerateJadwalPiketHarianAction.php`**

Baris 33 saat ini:
```php
        $tanggalMulai = $semester->tanggal_mulai->isPast() ? now()->startOfDay() : $semester->tanggal_mulai;
```
Ubah jadi:
```php
        $tanggalMulai = $semester->tanggal_mulai->isPast() ? now('Asia/Jakarta')->startOfDay() : $semester->tanggal_mulai;
```

- [ ] **Step 4: Ubah `RegenerateJadwalPiketHarianAction.php`**

Baris 32-35 saat ini:
```php
            $kandidat = PiketHarian::where('lembaga_id', $lembagaId)
                ->where('tanggal', '>=', now()->toDateString())
                ->where('tanggal', '<=', $semester->tanggal_selesai)
                ->where('sumber', 'dari_jadwal_mingguan')
                ->get();
```
Ubah jadi:
```php
            $kandidat = PiketHarian::where('lembaga_id', $lembagaId)
                ->where('tanggal', '>=', now('Asia/Jakarta')->toDateString())
                ->where('tanggal', '<=', $semester->tanggal_selesai)
                ->where('sumber', 'dari_jadwal_mingguan')
                ->get();
```

- [ ] **Step 5: Ubah `JurnalKbmController::index()` — perbaiki timezone DAN mismatch tanggal sekaligus**

Baris 76-90 saat ini:
```php
        $sesiPiket = null;
        if ($guru) {
            $piketHariIni = PiketHarian::where('lembaga_id', $guru->lembaga_id)
                ->where('guru_id', $guru->id)
                ->where('tanggal', now()->toDateString())
                ->exists();

            if ($piketHariIni) {
                $sesiPiket = SesiPembelajaran::where('lembaga_id', $guru->lembaga_id)
                    ->where('guru_id', '!=', $guru->id)
                    ->whereDate('tanggal', $hariIni)
                    ->with('kelas.tahunAjaran', 'mataPelajaran', 'guru')
                    ->get();
            }
        }
```
Ubah jadi:
```php
        $sesiPiket = null;
        if ($guru) {
            $tanggalHariIniSungguhan = now('Asia/Jakarta')->toDateString();
            $piketHariIni = PiketHarian::where('lembaga_id', $guru->lembaga_id)
                ->where('guru_id', $guru->id)
                ->where('tanggal', $tanggalHariIniSungguhan)
                ->exists();

            if ($piketHariIni) {
                $sesiPiket = SesiPembelajaran::where('lembaga_id', $guru->lembaga_id)
                    ->where('guru_id', '!=', $guru->id)
                    ->whereDate('tanggal', $tanggalHariIniSungguhan)
                    ->with('kelas.tahunAjaran', 'mataPelajaran', 'guru')
                    ->get();
            }
        }
```

(Perhatikan: `$hariIni` [variabel tanggal yang sedang di-browse, dari query string] TIDAK LAGI dipakai di blok ini sama sekali — diganti `$tanggalHariIniSungguhan` di KEDUA query. Variabel `$hariIni` tetap dipakai di bagian lain method untuk `$sesiList` [daftar sesi kelas sendiri] — itu TIDAK diubah, hanya blok "Sesi Piket Hari Ini" yang berubah.)

- [ ] **Step 6: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Guru/JurnalKbmSesiPiketTest.php --compact`
Expected: PASS — semua test di file ini hijau (test lama + test baru).

- [ ] **Step 7: Jalankan test file terkait lain untuk cek regresi**

Run: `vendor/bin/pest tests/Feature/Guru tests/Unit/Domains/Akademik/GenerateJadwalPiketHarianActionTest.php tests/Unit/Domains/Akademik/RegenerateJadwalPiketHarianActionTest.php --compact`
Expected: semua PASS — perubahan timezone TIDAK boleh membuat test existing yang pakai `Carbon::setTestNow()` dengan tanggal biasa (siang hari) jadi gagal, karena `now('Asia/Jakarta')` dan `now()` menghasilkan TANGGAL yang sama selama waktu test bukan di jendela dini-hari WIB.

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Akademik/Actions/Piket/GenerateJadwalPiketHarianAction.php app/Domains/Akademik/Actions/Piket/RegenerateJadwalPiketHarianAction.php app/Http/Controllers/Guru/Akademik/JurnalKbmController.php tests/Feature/Guru/JurnalKbmSesiPiketTest.php
git commit -m "fix(piket): scoped timezone Asia/Jakarta di titik penentuan hari-ini + perbaiki mismatch tanggal browsing vs hari sungguhan

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Badge "Mode Piket" di Halaman Isi Jurnal

**Files:**
- Modify: `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`
- Modify: `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php`
- Test: `tests/Feature/Guru/JurnalKbmPiketAksesTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: `siapkanSesiDanGuruPiketUntukAksesTest()` (helper existing di file test yang sama, TIDAK diubah).
- Produces: tidak ada interface baru.

- [ ] **Step 1: Tulis test yang gagal — banner muncul untuk guru piket, tidak muncul untuk pemilik**

Tambahkan di `tests/Feature/Guru/JurnalKbmPiketAksesTest.php`, di akhir file:

```php
it('menampilkan banner Mode Piket saat guru piket membuka sesi guru lain', function () {
    ['sesi' => $sesi, 'userPiket' => $userPiket] = siapkanSesiDanGuruPiketUntukAksesTest();

    $response = $this->actingAs($userPiket)->get(route('guru.jurnal-kbm.show', $sesi));

    $response->assertOk();
    $response->assertSee('Guru Piket');
});

it('tidak menampilkan banner Mode Piket saat guru pemilik membuka sesinya sendiri', function () {
    $yayasan = \App\Models\Yayasan::factory()->create();
    $lembaga = \App\Models\Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $kelas = \App\Models\Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => \App\Models\TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id])->id]);

    Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = \App\Models\Role::firstOrCreate(['name' => 'guru_badge_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $guruPemilik = \App\Models\Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userPemilik = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Identity\Models\Person::where('id', $guruPemilik->person_id)->update(['user_id' => $userPemilik->id]);
    $userPemilik->assignRole($role);

    $sesi = \App\Domains\Akademik\Models\SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruPemilik->id, 'tanggal' => now()->toDateString(),
    ]);

    $response = $this->actingAs($userPemilik)->get(route('guru.jurnal-kbm.show', $sesi));

    $response->assertOk();
    $response->assertDontSee('Guru Piket');
});
```

**Catatan**: sesuaikan `use` statement di bagian atas file test target dengan yang SUDAH ADA — baca file dulu, kalau sudah ada `use Spatie\Permission\Models\Permission;` dkk di atas, pakai tanpa prefix namespace penuh, JANGAN duplikasi.

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Guru/JurnalKbmPiketAksesTest.php --filter="menampilkan banner Mode Piket"`
Expected: FAIL — teks "Guru Piket" tidak ada di response manapun.

- [ ] **Step 3: Eager-load relasi `guru` di controller**

Baris 148 di `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php`, method `show()`, saat ini:
```php
        $sesi->loadMissing('kelas.tahunAjaran');
```
Ubah jadi:
```php
        $sesi->loadMissing('kelas.tahunAjaran', 'guru');
```

- [ ] **Step 4: Tambah banner di `show.blade.php`**

Baris 11-16 saat ini (blok `@if ($terkunci)`):
```blade
        @if ($terkunci)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Sesi ini sudah melewati batas waktu edit ({{ $batasEditHari }} hari).</p>
                <p class="mt-1 text-xs">Form di bawah ditampilkan hanya untuk dilihat. Hubungi Wali Kelas kelas ini kalau perlu koreksi.</p>
            </div>
        @endif
```
Ubah jadi (tambah blok baru SETELAH blok existing, SEBELUM baris 18 "Header & Breadcrumb"):
```blade
        @if ($terkunci)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Sesi ini sudah melewati batas waktu edit ({{ $batasEditHari }} hari).</p>
                <p class="mt-1 text-xs">Form di bawah ditampilkan hanya untuk dilihat. Hubungi Wali Kelas kelas ini kalau perlu koreksi.</p>
            </div>
        @endif

        @if ($sesi->guru_id !== (auth()->user()->guru->id ?? null))
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Anda mengisi sebagai Guru Piket untuk kelas milik {{ $sesi->guru?->nama ?? 'guru lain' }}.</p>
                <p class="mt-1 text-xs">Data yang Anda isi akan tercatat sebagai diisi oleh Anda (piket), bukan guru pemilik asli sesi ini.</p>
            </div>
        @endif
```

- [ ] **Step 5: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Guru/JurnalKbmPiketAksesTest.php --compact`
Expected: PASS — semua test di file ini hijau.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Guru/Akademik/JurnalKbmController.php resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php tests/Feature/Guru/JurnalKbmPiketAksesTest.php
git commit -m "fix(piket): tampilkan banner Mode Piket di halaman isi jurnal saat guru mengisi sesi guru lain

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Kalender `PiketHarian` Read-Only untuk Admin (Semua Sumber)

**Files:**
- Modify: `app/Http/Controllers/Admin/JadwalPiketMingguanController.php`
- Modify: `resources/views/portals/lembaga/akademik/piket-guru/index.blade.php`
- Test: `tests/Feature/Admin/JadwalPiketMingguanControllerTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: view `portals.lembaga.akademik.piket-guru.index` menerima variabel baru `piketHarianMendatang` (Collection `PiketHarian` kedua sumber) — TIDAK menggantikan `overrides` (tetap ada, tetap `override_manual` saja, tetap dipakai form hapus existing).

- [ ] **Step 1: Tulis test yang gagal — view data berisi kedua sumber PiketHarian**

Tambahkan di `tests/Feature/Admin/JadwalPiketMingguanControllerTest.php`, di akhir file (baca dulu isi file untuk pola helper setup lembaga/user yang sudah ada, reuse):

```php
it('mengirim piketHarianMendatang berisi kedua sumber (otomatis dan manual) ke view', function () {
    $yayasan = \App\Models\Yayasan::factory()->create();
    $lembaga = \App\Models\Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'piket.kelola', 'guard_name' => 'web']);
    $role = \App\Models\Role::firstOrCreate(['name' => 'admin_piket_kalender_test', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['piket.kelola']);
    $admin = \App\Models\User::factory()->create(['lembaga_id' => $lembaga->id]);
    $admin->assignRole($role);

    $guru = \App\Models\Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Akademik\Models\PiketHarian::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->addDay()->toDateString(), 'sumber' => 'dari_jadwal_mingguan',
    ]);
    \App\Domains\Akademik\Models\PiketHarian::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id, 'tanggal' => now()->addDays(2)->toDateString(), 'sumber' => 'override_manual',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.piket-guru.index'));

    $response->assertOk();
    $response->assertViewHas('piketHarianMendatang', fn ($list) => $list->count() === 2);
    $response->assertViewHas('overrides', fn ($list) => $list->count() === 1);
});
```

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Admin/JadwalPiketMingguanControllerTest.php --filter="mengirim piketHarianMendatang"`
Expected: FAIL — key `piketHarianMendatang` tidak ada di view data sama sekali.

- [ ] **Step 3: Tambah query baru di `index()`**

Baris 25-41 di `app/Http/Controllers/Admin/JadwalPiketMingguanController.php` saat ini:
```php
    public function index(Request $request): View
    {
        $this->authorize('piket.kelola');

        $lembagaId = $this->resolveLembagaIdAktif($request);

        return view('portals.lembaga.akademik.piket-guru.index', [
            'jadwalList' => JadwalPiketMingguan::where('lembaga_id', $lembagaId)->with(['guru', 'semester.tahunAjaran'])->orderBy('hari')->get(),
            'overrides' => PiketHarian::where('lembaga_id', $lembagaId)
                ->where('sumber', 'override_manual')
                ->where('tanggal', '>=', now()->toDateString())
                ->with('guru')
                ->orderBy('tanggal')
                ->get(),
            'guruList' => Guru::where('lembaga_id', $lembagaId)->orderByNama()->get(),
        ]);
    }
```
Ubah jadi:
```php
    public function index(Request $request): View
    {
        $this->authorize('piket.kelola');

        $lembagaId = $this->resolveLembagaIdAktif($request);

        return view('portals.lembaga.akademik.piket-guru.index', [
            'jadwalList' => JadwalPiketMingguan::where('lembaga_id', $lembagaId)->with(['guru', 'semester.tahunAjaran'])->orderBy('hari')->get(),
            'overrides' => PiketHarian::where('lembaga_id', $lembagaId)
                ->where('sumber', 'override_manual')
                ->where('tanggal', '>=', now()->toDateString())
                ->with('guru')
                ->orderBy('tanggal')
                ->get(),
            'piketHarianMendatang' => PiketHarian::where('lembaga_id', $lembagaId)
                ->where('tanggal', '>=', now()->toDateString())
                ->with('guru')
                ->orderBy('tanggal')
                ->limit(60)
                ->get(),
            'guruList' => Guru::where('lembaga_id', $lembagaId)->orderByNama()->get(),
        ]);
    }
```

- [ ] **Step 4: Tambah seksi baru di `index.blade.php`**

SETELAH penutup `</div>` seksi "Override Manual Piket Harian" yang sudah ada (baris 101 saat ini), SEBELUM penutup `</div>` container utama (baris 102 saat ini), sisipkan:
```blade

        {{-- Seksi Kalender Piket Harian Mendatang (Semua Sumber, Read-Only) --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-card space-y-4">
            <div class="border-b border-gray-150 pb-3">
                <h2 class="font-display text-base font-bold text-gray-900">Kalender Piket Harian Mendatang</h2>
                <p class="text-xs text-gray-500 mt-0.5">Hasil generate otomatis dari Jadwal Piket Mingguan di atas, digabung dengan Override Manual. Maksimal 60 baris ke depan ditampilkan. Baris "Otomatis" TIDAK bisa dihapus langsung dari sini — ubah lewat Jadwal Piket Mingguan di atas.</p>
            </div>

            @if ($piketHarianMendatang->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-gray-200">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 text-left text-gray-500 font-semibold border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-2.5">Tanggal</th>
                                <th class="px-4 py-2.5">Guru Piket</th>
                                <th class="px-4 py-2.5">Sumber</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-150 bg-white">
                            @foreach ($piketHarianMendatang as $item)
                                <tr>
                                    <td class="px-4 py-2.5 font-medium text-gray-900">{{ \Carbon\Carbon::parse($item->tanggal)->isoFormat('dddd, D MMMM Y') }}</td>
                                    <td class="px-4 py-2.5 text-gray-700">{{ $item->guru?->nama ?? '-' }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($item->sumber === 'override_manual')
                                            <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-semibold text-purple-700">Manual</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-semibold text-blue-700">Otomatis</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-xs text-gray-500">Belum ada baris piket harian mendatang. Buat Jadwal Piket Mingguan di atas untuk mulai generate otomatis.</p>
            @endif
        </div>
```

- [ ] **Step 5: Jalankan test untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Admin/JadwalPiketMingguanControllerTest.php --compact`
Expected: PASS — semua test di file ini hijau, TERMASUK test existing yang mengecek `overrides` (variabel itu TIDAK berubah perilakunya).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/JadwalPiketMingguanController.php resources/views/portals/lembaga/akademik/piket-guru/index.blade.php tests/Feature/Admin/JadwalPiketMingguanControllerTest.php
git commit -m "feat(piket): tambah kalender read-only PiketHarian (semua sumber) di halaman admin, admin bisa verifikasi hasil generate otomatis

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: `diisi_oleh_guru_id` Tidak Ditimpa Null Saat Guru Pemilik Submit Ulang

**Files:**
- Modify: `app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php`
- Test: `tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: tidak ada dari task lain.
- Produces: tidak ada interface baru — signature `execute()` TIDAK berubah.

**PALING BERISIKO REGRESI dari semua task di plan ini** — baca instruksi Step 5 dengan sangat teliti sebelum menganggap task ini selesai.

- [ ] **Step 1: Tulis test yang gagal — pemilik submit ulang setelah pernah diisi piket, jejak TIDAK hilang**

Tambahkan di `tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php`, di akhir file (helper `siapkanGuruPiketDanSesiUntukAkuntabilitasTest()` SUDAH ADA di file ini, reuse):

```php
it('guru pemilik submit ulang SETELAH pernah diisi guru piket -- diisi_oleh_guru_id TIDAK tertimpa null', function () {
    ['sesi' => $sesi, 'userPiket' => $userPiket, 'guruPiket' => $guruPiket, 'userPemilik' => $userPemilik, 'siswa' => $siswa] = siapkanGuruPiketDanSesiUntukAkuntabilitasTest();

    // Guru piket isi duluan.
    $this->actingAs($userPiket)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Diisi guru piket', 'presensi' => [$siswa->id => 'hadir'],
    ]);
    expect($sesi->fresh()->diisi_oleh_guru_id)->toBe($guruPiket->id);

    // Guru pemilik asli submit ulang (koreksi kecil) beberapa saat kemudian.
    $this->actingAs($userPemilik)->put(route('guru.jurnal-kbm.update', $sesi), [
        'materi' => 'Dikoreksi oleh guru pemilik', 'presensi' => [$siswa->id => 'hadir'],
    ]);

    // Jejak akuntabilitas HARUS tetap menunjuk ke guru piket, TIDAK tertimpa null.
    expect($sesi->fresh()->diisi_oleh_guru_id)->toBe($guruPiket->id);
    expect($sesi->fresh()->materi)->toBe('Dikoreksi oleh guru pemilik');
});
```

- [ ] **Step 2: Jalankan test untuk memastikan GAGAL (bug belum diperbaiki)**

Run: `vendor/bin/pest tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php --filter="TIDAK tertimpa null"`
Expected: FAIL — `diisi_oleh_guru_id` jadi `null` setelah guru pemilik submit ulang (bug saat ini: kolom selalu ditimpa).

- [ ] **Step 3: Ubah `RecordJurnalDanPresensiAction.php`**

Baris 21-24 saat ini:
```php
            $sesi->update([
                'materi' => $data->materi,
                'diisi_oleh_guru_id' => $diisiOlehGuruId,
            ]);
```
Ubah jadi:
```php
            $sesi->update(array_filter([
                'materi' => $data->materi,
                'diisi_oleh_guru_id' => $diisiOlehGuruId,
            ], fn ($value, $key) => $key !== 'diisi_oleh_guru_id' || $value !== null, ARRAY_FILTER_USE_BOTH));
```

- [ ] **Step 4: Jalankan test baru untuk memastikan LOLOS**

Run: `vendor/bin/pest tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php --compact`
Expected: PASS — semua test di file ini hijau (2 test lama + 1 test baru).

- [ ] **Step 5: WAJIB — verifikasi eksplisit test lama "diisi_oleh_guru_id TETAP null" masih benar-benar lolos untuk ALASAN YANG BENAR**

Test lama `'guru pemilik asli submit jurnal untuk sesinya sendiri -- diisi_oleh_guru_id TETAP null'` (baris 56-64 file yang sama) menguji sesi yang BELUM PERNAH diisi siapa pun (kolom `diisi_oleh_guru_id` sudah `null` dari awal via factory). Setelah perubahan Step 3, `array_filter` akan MEMBUANG key `diisi_oleh_guru_id` dari payload `update()` karena `$diisiOlehGuruId === null` — artinya kolom itu TIDAK disentuh sama sekali oleh `update()` ini, bukan "di-set ulang ke null". Karena nilai kolom itu MEMANG SUDAH `null` dari awal (belum pernah diisi), hasil akhirnya tetap `null` — TAPI lewat mekanisme "tidak disentuh", bukan "di-set ke null". Efeknya sama, tapi implementer WAJIB menjalankan ulang test spesifik ini untuk MEMBUKTIKAN, bukan berasumsi:

Run: `vendor/bin/pest tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php --filter="TETAP null"`
Expected: PASS. Kalau GAGAL, itu tanda ada asumsi yang salah di Step 3 — STOP, jangan lanjut ke task lain, laporkan detail kegagalannya.

- [ ] **Step 6: Jalankan seluruh test JurnalKbm untuk cek regresi lebih luas**

Run: `vendor/bin/pest tests/Feature/Guru --compact`
Expected: semua PASS — perubahan ini menyentuh Action inti yang dipakai SEMUA alur isi jurnal (piket maupun bukan), jadi regresi di luar file `JurnalKbmDiisiOlehGuruTest.php` juga harus dicek.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Akademik/Actions/Presensi/RecordJurnalDanPresensiAction.php tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php
git commit -m "fix(piket): jangan timpa diisi_oleh_guru_id jadi null saat guru pemilik submit ulang, pertahankan jejak akuntabilitas piket

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 5: Ganti `confirm()` Native Jadi `confirmDialog()` Standar Proyek

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/piket-guru/index.blade.php`

**Interfaces:**
- Consumes: `window.confirmDialog(title, message, options): Promise<boolean>` (global function, sudah terdaftar, TIDAK perlu registrasi baru).
- Produces: tidak ada interface baru.

- [ ] **Step 1: Ubah form hapus Jadwal Piket Mingguan**

Baris 34 saat ini:
```blade
                                <form method="POST" action="{{ route('admin.piket-guru.destroy', $jadwal) }}" class="inline" onsubmit="return confirm('Hapus jadwal piket ini?')">
```
Ubah jadi:
```blade
                                <form method="POST" action="{{ route('admin.piket-guru.destroy', $jadwal) }}" class="inline" @submit.prevent="confirmDialog('Hapus Jadwal Piket?', @js('Piket ' . ($jadwal->guru?->nama ?? 'guru ini') . ' pada hari ' . ($namaHari[$jadwal->hari] ?? $jadwal->hari) . ' akan dihapus. Baris piket harian mendatang yang terkait juga akan ikut disesuaikan otomatis.'), { confirmLabel: 'Ya, Hapus', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })">
```

- [ ] **Step 2: Ubah form hapus Override Manual**

Baris 90 saat ini:
```blade
                                        <form method="POST" action="{{ route('admin.piket-harian.destroy', $override) }}" class="inline" onsubmit="return confirm('Hapus override manual ini?')">
```
Ubah jadi:
```blade
                                        <form method="POST" action="{{ route('admin.piket-harian.destroy', $override) }}" class="inline" @submit.prevent="confirmDialog('Hapus Override Piket?', @js('Override piket manual untuk ' . \Carbon\Carbon::parse($override->tanggal)->isoFormat('D MMMM Y') . ' akan dihapus.'), { confirmLabel: 'Ya, Hapus', isDanger: true }).then(confirmed => { if (confirmed) $el.submit() })">
```

- [ ] **Step 3: Build asset frontend**

Run: `npm run build`
Expected: build sukses tanpa error (perubahan murni Blade+Alpine directive, tidak ada JS baru, tapi tetap build untuk memastikan tidak ada syntax error ter-compile).

- [ ] **Step 4: Verifikasi manual dev-server**

Login sebagai admin dengan permission `piket.kelola`, buka `/admin/piket-guru`, klik "Hapus" pada baris Jadwal Piket Mingguan — konfirmasi dialog custom bertema muncul (BUKAN popup browser native), pesan menyebutkan nama guru+hari+efek cascade. Klik "Batal" — form TIDAK submit. Ulangi untuk baris Override Manual.

- [ ] **Step 5: Jalankan test existing untuk memastikan tidak ada regresi**

Run: `vendor/bin/pest tests/Feature/Admin/JadwalPiketMingguanControllerTest.php tests/Feature/Admin/PiketHarianControllerTest.php --compact`
Expected: semua PASS (perubahan murni Blade attribute, tidak ada test yang seharusnya terpengaruh — tapi WAJIB dijalankan untuk konfirmasi, `@submit.prevent` + JS confirm tidak memengaruhi test backend yang langsung POST ke route tanpa lewat browser).

- [ ] **Step 6: Commit**

```bash
git add resources/views/portals/lembaga/akademik/piket-guru/index.blade.php
git commit -m "fix(piket): ganti confirm() native jadi confirmDialog standar proyek utk hapus jadwal/override piket

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 6: Test Regresi `resolveKartu()` untuk Guru Piket

**Files:**
- Test: `tests/Feature/Guru/JurnalKbmResolveKartuTest.php` (tambah di akhir file)

**Interfaces:**
- Consumes: `siapkanSesiDanGuruPiketUntukAksesTest()` (helper existing di `tests/Feature/Guru/JurnalKbmPiketAksesTest.php` — PENTING: helper ini didefinisikan TANPA `function_exists()` guard di file itu, cek dulu apakah perlu di-duplicate ke file target atau bisa dipanggil langsung karena Pest memuat semua file test dalam 1 process — kalau ada konflik nama function saat dijalankan bersamaan, implementer perlu menyesuaikan, JANGAN memaksa reuse kalau ternyata bentrok).
- Produces: tidak ada interface baru.

- [ ] **Step 1: Tulis test baru — guru piket bisa resolve-kartu untuk sesi yang dia isi sebagai piket**

Tambahkan di `tests/Feature/Guru/JurnalKbmResolveKartuTest.php`, di akhir file:

```php
it('resolve-kartu berfungsi untuk guru piket yang mengisi sesi guru lain, bukan cuma guru pemilik', function () {
    Permission::firstOrCreate(['name' => 'presensi.isi', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'guru_piket_resolve_kartu_test', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $role->givePermissionTo(['presensi.isi']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);

    $guruPemilik = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $sesi = \App\Domains\Akademik\Models\SesiPembelajaran::factory()->create([
        'lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'guru_id' => $guruPemilik->id, 'tanggal' => now()->toDateString(),
    ]);

    $guruPiket = Guru::factory()->create(['lembaga_id' => $lembaga->id]);
    $userPiket = User::factory()->create(['lembaga_id' => $lembaga->id]);
    \App\Domains\Identity\Models\Person::where('id', $guruPiket->person_id)->update(['user_id' => $userPiket->id]);
    $userPiket->assignRole($role);
    \App\Domains\Akademik\Models\PiketHarian::create([
        'lembaga_id' => $lembaga->id, 'guru_id' => $guruPiket->id, 'tanggal' => now()->toDateString(), 'sumber' => 'override_manual',
    ]);

    KartuSiswa::create(['siswa_id' => $siswa->id, 'tipe' => 'qr', 'kode' => 'kode-guru-piket-scan', 'is_active' => true]);

    $response = $this->actingAs($userPiket)->postJson(route('guru.jurnal-kbm.resolve-kartu', $sesi), ['kode' => 'kode-guru-piket-scan']);

    $response->assertOk();
    $response->assertJson(['siswa_id' => $siswa->id, 'nama_lengkap' => $siswa->nama_lengkap]);
});
```

**Catatan**: pakai import class yang SUDAH ADA di bagian atas file (`Guru`, `Kelas`, `Lembaga`, `Role`, `Semester`, `Siswa`, `TahunAjaran`, `User`, `Yayasan`, `KartuSiswa`, `Permission` — semua sudah di-import file existing) — untuk `SesiPembelajaran` dan `Person` yang mungkin belum di-import (dipakai versi `siapkanGuruDenganJadwalHariIni()` yang existing lewat cara lain), pakai namespace penuh seperti dicontohkan di atas, ATAU tambah `use` statement baru kalau lebih konsisten dengan gaya file — cek dulu bagaimana file ini biasa melakukannya.

- [ ] **Step 2: Jalankan test untuk memastikan LOLOS (bukan TDD merah-hijau — ini test regresi murni, fitur sudah ada)**

Run: `vendor/bin/pest tests/Feature/Guru/JurnalKbmResolveKartuTest.php --compact`
Expected: PASS — semua test di file ini hijau (4 test lama + 1 test baru). Test baru ini TIDAK diharapkan gagal sebelum ada perubahan kode apa pun (tidak ada bug yang diperbaiki di task ini, murni menutup gap cakupan test — `resolveKartu()` secara kode sudah mendukung piket lewat `authorizeMilikGuru()` yang sama).

**Kalau test baru ini GAGAL** — itu tanda ada bug nyata di `resolveKartu()` untuk kasus guru piket yang SEBELUMNYA tidak terdeteksi karena tidak ada test-nya. STOP, laporkan detail kegagalan, JANGAN modifikasi test supaya lolos tanpa investigasi lebih dulu.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Guru/JurnalKbmResolveKartuTest.php
git commit -m "test(piket): tambah regresi resolveKartu() untuk guru piket, menutup gap cakupan test skenario 12 spec

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 7: Pass Konsistensi UI/UX — Analisa & Sesuaikan ke Standar Proyek

**Files:**
- Modify: `resources/views/portals/lembaga/akademik/piket-guru/{index,create,edit}.blade.php`
- Controller `JadwalPiketMingguanController.php` TIDAK PERLU diubah — `guruList` yang sudah dikirim controller ke ketiga view CUKUP, `guruOptions` yang dibutuhkan `tomSelectPegawai` diturunkan LOKAL di Blade lewat blok `@php`, lihat Step 1.

**Interfaces:**
- Consumes: pola `tomSelectPegawai` Alpine component (SUDAH ADA di `resources/js/`, dipakai `resources/views/admin/kelas/_form.blade.php`) dan komponen `<x-select>` (SUDAH ADA di `resources/views/components/select.blade.php`).
- Produces: tidak ada interface baru untuk task lain (task terakhir sebelum penutup).

**INI BUKAN TASK KODE MEKANIS SEPERTI TASK LAIN — ini instruksi AUDIT-DAN-SESUAIKAN.** Halaman `piket-guru/{index,create,edit}.blade.php` saat ini pakai `<select>` native untuk pemilihan guru (tanpa search) dan tidak pakai `<x-select>` di select lain — TAPI JANGAN langsung copy-paste kode dari halaman lain tanpa analisa, karena konteks bisa berbeda (pernah terjadi di sesi audit sebelumnya: `<x-select>` yang dipasang di elemen dengan `:name` dinamis Alpine bentrok dengan compiler Blade, harus dicek dulu case-per-case).

**PRASYARAT: Task 3 dan Task 5 WAJIB sudah selesai & di-commit** (task ini menyentuh file `index.blade.php` yang sama, dikerjakan terakhir untuk hindari konflik).

- [ ] **Step 1: Baca pola established `tomSelectPegawai` — JANGAN asumsikan bentuk datanya**

Baca `resources/views/admin/kelas/_form.blade.php` baris 1-11 DAN baris 91-108 (WAJIB kedua blok, bukan cuma satu). Baris 1-11 (`@php` di paling atas file) mengungkapkan fakta PENTING yang gampang terlewat: `$guruOptions` BUKAN dikirim dari controller — variabel itu DITURUNKAN LOKAL di Blade lewat blok `@php`, dari `$guruList` yang MEMANG sudah dikirim controller (`KelasController.php:99,157` mengirim `guruList`, BUKAN `guruOptions`):
```php
@php
    $guruOptions = collect([['id' => '', 'nama' => '— Belum ditentukan —', 'subtext' => '']])
        ->concat($guruList->map(fn ($g) => [
            'id' => (string) $g->id,
            'nama' => $g->nama,
            'subtext' => $g->nip ? 'NIP: '.$g->nip : ($g->nuptk ? 'NUPTK: '.$g->nuptk : ($g->jenis_ptk ? str_replace('_', ' ', ucwords($g->jenis_ptk, '_')) : '')),
        ]))
        ->values();
@endphp
```
Format tiap opsi: array asosiatif `['id' => ..., 'nama' => ..., 'subtext' => ...]`, dengan 1 baris tambahan di depan untuk opsi kosong/placeholder. Baris 91-108: `x-data="tomSelectPegawai({ options: @js($guruOptions), oldValue: @js($val('wali_kelas_guru_id')), placeholder: '...' })"` dipasang di elemen WRAPPER (bukan langsung di `<select>`), dan `<select>` di dalamnya punya `x-ref="selectElement"` — INI BUKAN memakai `<x-select>` sama sekali, `tomSelectPegawai` adalah Alpine component TERPISAH yang meng-enhance `<select>` NATIVE jadi searchable, bukan menggantikannya dengan komponen Blade lain.

**Kesimpulan Step 1**: KARENA `piket-guru/{index,create,edit}.blade.php` SUDAH menerima `guruList` dari controller (dikonfirmasi lewat pembacaan `JadwalPiketMingguanController.php` — ketiga method `index()`/`create()`/`edit()` sudah mengirim `guruList`), TIDAK PERLU mengubah controller apa pun. Cukup tambah blok `@php $guruOptions = ...` (pola sama persis seperti di atas) di masing-masing dari 3 file Blade yang butuh dropdown guru searchable.

- [ ] **Step 2: Baca 2-3 pemakaian `<x-select>` di halaman lain untuk konvensi prop**

Baca `resources/views/components/select.blade.php` (definisi komponen — sudah pernah dibaca sesi ini sebelumnya untuk modul Pengadaan, cek `@props` yang didukung: `disabled`, `error`, dan attribute pass-through lewat `$attributes->merge()`). Baca minimal 2 pemakaian nyata di halaman admin lain (mis. `resources/views/admin/roles/_form.blade.php` atau halaman lain yang dikonfirmasi memakai `<x-select>` dari audit-audit sesi ini sebelumnya) untuk lihat pola `name`/`x-model`/`required` yang biasa dipasangkan.

- [ ] **Step 3: Bandingkan dengan kondisi `piket-guru/{index,create,edit}.blade.php` SAAT INI, per elemen `<select>`**

Untuk SETIAP `<select>` di 3 file ini (index.blade.php baris 58 [pilih guru override], create.blade.php baris 12 [guru], 21 [hari], 30 [semester], edit.blade.php baris serupa — baca file `edit.blade.php` dulu untuk baris pastinya, JANGAN asumsikan sama persis dengan create.blade.php), jawab 3 pertanyaan SEBELUM mengubah apa pun:
1. Apakah `<select>` ini di dalam `x-for`/loop dengan `:name` dinamis (butuh tetap native karena konflik compiler Blade)? — kalau YA, JANGAN diubah ke `<x-select>` maupun `tomSelectPegawai`, biarkan native, TAMBAHKAN komentar penjelasan kenapa (ikuti pola yang sudah established di modul lain untuk kasus serupa).
2. Apakah `<select>` ini murni pilihan singkat (≤7-10 opsi, mis. "Hari" cuma 7 opsi) yang TIDAK butuh pencarian? — kalau YA, `<x-select>` saja cukup (untuk styling konsisten), TIDAK perlu `tomSelectPegawai` (searchable itu overkill untuk 7 opsi).
3. Apakah `<select>` ini daftar guru (berpotensi banyak, >10 di lembaga besar) yang MEMANG butuh pencarian? — kalau YA (ini kasus select "Guru" di ketiga file), terapkan `tomSelectPegawai` mengikuti pola `admin/kelas/_form.blade.php` PERSIS (termasuk struktur wrapper `x-data` + `x-ref="selectElement"`), BUKAN sekadar mengganti tag jadi `<x-select>` (itu tidak menutup kebutuhan search yang jadi alasan utama perubahan ini).

- [ ] **Step 4: Terapkan penyesuaian sesuai hasil analisa Step 3**

Untuk select "Guru" (index.blade.php baris 58, create.blade.php baris 12, edit.blade.php — baris yang sesuai): terapkan `tomSelectPegawai` — tambah blok `@php $guruOptions = ...` LOKAL di masing-masing dari 3 file Blade (pola persis dikutip Step 1, TIDAK perlu ubah controller sama sekali, `guruList` yang jadi sumbernya sudah tersedia di ketiga view).

Untuk select "Hari" (create.blade.php baris 21, edit.blade.php baris sesuai) dan "Tahun Ajaran & Semester" (create.blade.php baris 30, edit.blade.php baris sesuai): terapkan `<x-select>` (opsi sedikit, tidak butuh search) — pastikan atribut `class` Tailwind manual yang ada saat ini DIHAPUS (styling sudah built-in di komponen `<x-select>`, jangan dipertahankan dobel).

Tambah `id`/`for` yang hilang di `<x-input-label>` + `<select>`/`<x-select>` terkait sepanjang perbaikan ini (gap a11y yang sama juga ditemukan audit — tutup sekalian karena sudah menyentuh baris yang sama).

- [ ] **Step 5: Build asset frontend**

Run: `npm run build`
Expected: build sukses tanpa error.

- [ ] **Step 6: Verifikasi manual dev-server**

Buka `/admin/piket-guru`, `/admin/piket-guru/create`, dan edit salah satu jadwal — konfirmasi: dropdown Guru sekarang searchable (ketik nama, hasil ter-filter), dropdown Hari/Semester pakai styling `<x-select>` konsisten dengan halaman lain, label form terhubung `for`/`id` (klik label memfokuskan input terkait).

- [ ] **Step 7: Jalankan test existing untuk memastikan tidak ada regresi**

Run: `vendor/bin/pest tests/Feature/Admin/JadwalPiketMingguanControllerTest.php --compact`
Expected: semua PASS — perubahan murni tampilan/enhancement JS di Blade, TIDAK mengubah controller sama sekali (lihat kesimpulan Step 1), jadi test existing yang assert `assertViewHas('guruList', ...)` otomatis tetap lolos tanpa penyesuaian apa pun.

- [ ] **Step 8: Commit**

```bash
git add resources/views/portals/lembaga/akademik/piket-guru/index.blade.php resources/views/portals/lembaga/akademik/piket-guru/create.blade.php resources/views/portals/lembaga/akademik/piket-guru/edit.blade.php
git commit -m "style(piket): select guru jadi searchable via tomSelectPegawai, <x-select> utk dropdown singkat, perbaiki a11y label

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 8: Regresi Penutup

**Files:**
- Tidak ada file yang dimodifikasi — task ini murni verifikasi.

**Interfaces:**
- Consumes: seluruh perubahan dari Task 1-7.
- Produces: tidak ada.

- [ ] **Step 1: Jalankan seluruh test terkait fitur ini**

Run: `vendor/bin/pest tests/Feature/Admin/JadwalPiketMingguanControllerTest.php tests/Feature/Admin/PiketHarianControllerTest.php tests/Feature/Guru/JurnalKbmPiketAksesTest.php tests/Feature/Guru/JurnalKbmSesiPiketTest.php tests/Unit/Domains/Akademik/GenerateJadwalPiketHarianActionTest.php tests/Unit/Domains/Akademik/PiketAccessCheckerTest.php tests/Unit/Domains/Akademik/PiketModelsTest.php tests/Unit/Domains/Akademik/RegenerateJadwalPiketHarianActionTest.php tests/Feature/Akademik/JurnalKbmTanggalSusulanTest.php tests/Feature/Guru/JurnalKbmResolveKartuTest.php tests/Feature/Guru/JurnalKbmBatasEditTest.php tests/Feature/Guru/JurnalKbmDiisiOlehGuruTest.php tests/Feature/Akademik/JurnalKbmAdaptiveTest.php tests/Feature/Guru/JurnalKbmControllerTest.php tests/Feature/Guru/JurnalKbmTenantScopeTest.php --compact`
Expected: semua PASS, tidak ada yang gagal.

- [ ] **Step 2: Jalankan Pint pada semua file yang diubah**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}` (jalankan ulang sampai `passed` kalau ada auto-fix diterapkan).

- [ ] **Step 3: Jalankan full test suite proyek**

Run: `php artisan test --compact`
Expected: HANYA 3 kegagalan pre-existing yang sudah dikenal (`Tests\Unit\M3DemoDataSeederTest` x2, `Tests\Feature\Akademik\SubjekTenantValidationTest`) yang muncul. KALAU ADA kegagalan lain — STOP, jangan lanjut, laporkan detail (nama test, pesan error) alih-alih mengasumsikan pre-existing. **WAJIB jalankan SENDIRIAN** — bukan bersamaan dengan proses `pest`/`artisan test` lain yang sedang berjalan (pelajaran dari insiden sesi ini sebelumnya: 2 proses test paralel ke database test yang sama menghasilkan kegagalan palsu massal akibat rebutan koneksi).

- [ ] **Step 4: Verifikasi manual dev-server — rekap checklist UI dari Task 2, 3, 5, 7**

Checklist ulang (boleh screenshot untuk laporan handoff): banner "Mode Piket" muncul saat guru piket isi sesi guru lain (Task 2), kalender read-only PiketHarian tampil dengan badge sumber yang benar (Task 3), confirmDialog custom muncul untuk hapus jadwal/override (Task 5), dropdown guru searchable + styling konsisten (Task 7).

- [ ] **Step 5: Commit penutup (kalau ada sisa perubahan dari Pint)**

```bash
git status
```

Kalau ada perubahan tersisa dari auto-fix Pint yang belum ter-commit:

```bash
git add -u
git commit -m "style(piket): rapikan format Pint hasil perbaikan audit jadwal piket guru

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

Kalau working tree bersih, tidak perlu commit apa pun di step ini.

---

## Self-Review — Putaran 1 (cakupan spec + placeholder + konsistensi tipe)

**Cakupan spec**: §2.1+§2.5→Task 1, §2.2→Task 2, §2.3→Task 3, §2.4→Task 4, §2.6→Task 5, §2.7→Task 6, item §3 poin 3 (UI polish)→Task 7, §5 (pengujian)→tersebar ke tiap task + Task 8. §3 (item di luar scope) dikonfirmasi TIDAK ADA task yang menyentuh `config/app.php`/`.env` APP_TIMEZONE, validasi overlap semester, `lockForUpdate()`, atau item Fase 2 spec lama.

**Placeholder scan**: tidak ditemukan "TBD"/"TODO" di Task 1-6, 8 — semua kode lengkap siap salin. Task 7 SENGAJA instruksional (bukan kode template) sesuai permintaan eksplisit user, TAPI tetap punya langkah konkret bernomor (baca-bandingkan-analisa-terapkan-verifikasi), bukan instruksi kosong.

**Konsistensi tipe**: `now('Asia/Jakarta')` dipakai KONSISTEN persis sama di 3 titik Task 1. Variabel view `piketHarianMendatang` didefinisikan Task 3, tidak dikonsumsi task lain (murni tampilan). `array_filter` pattern Task 4 tidak dipakai di task lain (independen).

## Self-Review — Putaran 2 (verifikasi terhadap kode aktual & test existing)

- Dikonfirmasi ulang `RecordJurnalDanPresensiAction.php` baris 21-24 PERSIS seperti dikutip di Task 4 — dibaca langsung dari file sebelum plan ditulis.
- Dikonfirmasi ulang helper `siapkanSesiDanGuruPiketUntukAksesTest()` dan `siapkanGuruPiketDanSesiUntukAkuntabilitasTest()` MEMANG ada di file test yang dirujuk, dengan struktur return yang dipakai persis di Task 2 dan Task 4.
- Dikonfirmasi ulang pola `tomSelectPegawai` di `admin/kelas/_form.blade.php:91-108` — struktur wrapper `x-data`+`x-ref="selectElement"` dikutip akurat di Task 7 Step 1.
- Ditambahkan CATATAN EKSPLISIT di Task 1 Step 1 soal eksperimen `tinker` WAJIB dijalankan dulu untuk memverifikasi interaksi `Carbon::setTestNow()`+`now('Asia/Jakarta')` SEBELUM menulis assertion — sesuai instruksi kickoff, bukan diasumsikan.
- Ditambahkan CATATAN EKSPLISIT di Task 4 Step 5 yang memisahkan "PASS" dari "PASS untuk alasan yang benar" — supaya implementer tidak cuma lihat centang hijau tapi paham MENGAPA test lama itu tetap valid setelah perubahan (null→null via "tidak disentuh" ekuivalen null→null via "di-set eksplisit").

## Self-Review — Putaran 3 (dependency antar-task & urutan risiko)

- **Task 1 dan Task 2 sama-sama menyentuh file di bawah `Guru/Akademik/JurnalKbmController.php`** — dikonfirmasi ulang BEDA method (Task 1 di `index()`, Task 2 di `show()`) dan BEDA baris — aman independen, TIDAK perlu urutan khusus di antara keduanya.
- **Task 3 dan Task 5 SAMA-SAMA menyentuh `piket-guru/index.blade.php`** — dikonfirmasi baris yang disentuh BERBEDA (Task 3 menambah seksi baru SETELAH baris 101; Task 5 mengubah `onsubmit` di baris 34 dan 90, SEBELUM baris 101) — TIDAK overlap persis, tapi Task 7 (yang JUGA menyentuh file sama) tetap diurutkan PALING TERAKHIR di antara ketiganya untuk kehati-hatian ekstra (dicatat eksplisit sebagai PRASYARAT di Task 7).
- **Task 4 PALING BERISIKO** karena mengubah Action inti yang dipakai SEMUA alur isi jurnal (bukan cuma piket) — Step 6 di Task 4 SENGAJA menjalankan `tests/Feature/Guru` PENUH (bukan cuma file piket) untuk menangkap regresi di alur non-piket juga, sudah dicatat eksplisit.
- **Task 6 murni tambahan test, TIDAK ADA risiko regresi kode** — aman dikerjakan kapan saja, tapi tetap diurutkan sebelum Task 7/8 mengikuti pola plan lain di sesi ini (task test-only biasanya di tengah, bukan di awal/akhir mutlak).

## Self-Review — Putaran 4 (baca ulang dengan mata segar, cek instruksi Task 7 & kickoff)

- Dicek ulang Task 7 — SENGAJA ditulis TIDAK sebagai kode template siap-tempel (beda dari Task 1-6), melainkan 8 step yang memandu ANALISA dulu (Step 1-3) baru TERAPKAN (Step 4) — ini SELARAS dengan permintaan eksplisit user di percakapan untuk kickoff nanti memuat instruksi ke "agent lain" soal analisa UI/UX, bukan instruksi mekanis. Kickoff yang akan ditulis setelah ini WAJIB merujuk balik ke Task 7 dengan penekanan yang sama.
- Dicek ulang: Task 7 Step 3 poin 1 (cek elemen `x-for`/`:name` dinamis) SENGAJA mengingatkan risiko konflik binding `<x-select>` vs Alpine yang PERNAH ditemukan sesi ini sebelumnya (modul lain) — dicatat sebagai preseden konkret, bukan kekhawatiran abstrak, supaya implementer tahu ini BUKAN teori tapi kejadian nyata yang pernah terjadi.
- Dicek ulang total 8 task — cocok dengan 7 kelompok temuan spec §2 (Task 1-6, digabung sesuai §2.1+§2.5) + 1 task UI polish dari §3 poin 3 (Task 7) + 1 penutup (Task 8) = 8. Tidak ada yang tertukar posisi atau hilang.
- Dicek ulang commit message tiap task — semua pakai prefix `fix(piket)`/`feat(piket)`/`style(piket)`/`test(piket)` konsisten, memudahkan `git log --oneline | grep piket` untuk review nanti.
