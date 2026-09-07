# Spec: Perbaikan Kebocoran Lintas-Yayasan & Kelalaian Karyawan Pool — Modul Karyawan & Guru

> **Branch**: `rbac-v2`
> **Tanggal**: 7 September 2026
> **Latar belakang**: Audit menyeluruh modul Karyawan & Guru (2 subagent paralel + investigasi lanjutan + verifikasi empiris via tinker, transaksi rollback) atas permintaan user, mengikuti pola audit Orang Tua/Siswa/Person sebelumnya. Ditemukan 6 bug — 2 di antaranya kebocoran data LINTAS YAYASAN nyata (bukan cuma lintas lembaga), dikonfirmasi lewat reproduksi langsung, bukan cuma pembacaan kode. Ini kelas bug yang sama dengan pola berulang di proyek ini (>10x sepanjang sesi-sesi sebelumnya per catatan project), kali ini muncul di 2 bentuk baru: validasi `exists:` yang lupa scope, dan query fallback resolver yang lupa filter yayasan untuk kasus "pool" (pegawai lintas-lembaga dalam 1 yayasan).

## Ringkasan Temuan (6 bug, 3 kelompok)

| # | Kelompok | Severity | Ringkasan |
|---|---|---|---|
| A.1-A.4 | Validasi `exists:` tanpa scope | 🔴 Kritis | 4 titik kode menerima `jenis_karyawan_id`/`jabatan_tambahan_master_id` milik yayasan LAIN sebagai valid |
| B.1-B.2 | Resolver query pool tanpa scope | 🔴 Kritis | `AttendancePolicyResolver` & `KuotaCutiResolver` bisa menerapkan kebijakan/kuota cuti milik yayasan lain ke karyawan pool |
| C | Karyawan pool terlewat dari alur operasional | 🟠 Tinggi | Tidak pernah ditandai Alpa otomatis, tidak muncul di 2 dropdown pemilih karyawan |
| D | `PersonAlreadyExistsException` tidak ditangkap | 🟡 Sedang | Race condition submit ganda di `GuruController::store()` bisa memunculkan 500 mentah |
| E | Index Karyawan snapshot client-side & tanpa pencarian NIK | 🟡 Sedang | Sama seperti bug lama OrangTua — data basi tanpa reload, NIK tidak bisa dicari |

## Keputusan Bisnis (dikonfirmasi dengan user)

1. **Karyawan pool tidak punya "hari libur mingguan" tersendiri** (`Yayasan` model TIDAK punya kolom setara `Lembaga.hari_libur_mingguan_sdm`, dikonfirmasi lewat pembacaan model). Diputuskan: **karyawan pool default SELALU dianggap hari kerja**, kecuali ada entri `KalenderKerjaSdm` nasional/yayasan eksplisit yang menandai tanggal itu libur. TIDAK menambah kolom baru ke `Yayasan` — di luar scope spec ini.
2. **`attendance_events.lembaga_id` (NOT NULL saat ini, dikonfirmasi dari `database/schema/mysql-schema.sql:203`) diubah jadi NULLABLE** supaya karyawan pool bisa dicatat Alpa otomatis tanpa mengasosiasikannya secara menyesatkan ke 1 lembaga tertentu yang bukan tempatnya bekerja. Ini paling jujur secara data (karyawan pool memang tidak terikat 1 lembaga), dikonfirmasi user, TIDAK memilih opsi "pakai lembaga pertama sebagai representasi" (menyesatkan) atau "skip total" (tidak menyelesaikan Bug C sepenuhnya).

---

## Kelompok A — Validasi `exists:` Tanpa Scope Yayasan (4 titik)

**Akar masalah bersama**: Laravel validation rule `exists:table,column` mengompilasi jadi query MENTAH ke tabel (`DatabasePresenceVerifier`), TIDAK PERNAH lewat Eloquent global scope (`YayasanScope`, yang baru dipasang ke `JenisKaryawanMaster`/`JabatanTambahanMaster`). Dikonfirmasi empiris lewat tinker: ID milik yayasan lain LOLOS validasi `exists:jenis_karyawan_master,id` tanpa syarat tambahan.

**Fix umum**: ganti `'exists:table,column'` (string) jadi `Rule::exists('table', 'column')->where('yayasan_id', $yayasanId)` di SETIAP titik, dengan `$yayasanId` yang tepat sesuai konteks masing-masing (aktor yang login untuk create, atau yayasan pemilik row target untuk operasi terkait row yang sudah ada).

### A.1 — `KaryawanController::store()` (`app/Http/Controllers/Admin/KaryawanController.php`, method `validateProfil()`, baris 241)

Kode saat ini:
```php
'jenis_karyawan_id' => ['required', 'exists:jenis_karyawan_master,id'],
```
`$yayasanId` SUDAH DIHITUNG di method yang sama (baris 217-223, dipakai untuk validasi NIK). Reuse variabel itu:
```php
'jenis_karyawan_id' => ['required', Rule::exists('jenis_karyawan_master', 'id')->where('yayasan_id', $yayasanId)],
```
Tambah `use Illuminate\Validation\Rule;` ke import (cek dulu belum ada — berdasarkan baca file sebelumnya, BELUM di-import).

### A.2 — `KaryawanController::update()` (baris 163)

Kode saat ini:
```php
'jenis_karyawan_id' => ['required', 'exists:jenis_karyawan_master,id'],
```
Beda dari A.1: ini di method `update()`, TIDAK di `validateProfil()`, jadi `$yayasanId` belum dihitung di situ. Konteks yang benar: yayasan PEMILIK `$karyawan` yang sedang diedit (bukan aktor yang login — konsisten dengan pola guard delete di spec Jenis Karyawan/Jabatan Tambahan sebelumnya). Tambahkan sebelum `$request->validate()`:
```php
public function update(Request $request, Karyawan $karyawan): RedirectResponse
{
    $this->authorize('karyawan.edit');

    $data = $request->validate([
        'nama' => ['required', 'string', 'max:255'],
        'email' => ['nullable', 'email', 'max:255'],
        'no_hp' => ['nullable', 'string', 'max:20'],
        'jenis_karyawan_id' => ['required', Rule::exists('jenis_karyawan_master', 'id')->where('yayasan_id', $karyawan->yayasan_id)],
    ]);
    // ...sisa method tidak berubah
```

### A.3 — `AttendanceConfigurationController::storePolicy()` (`app/Http/Controllers/Admin/AttendanceConfigurationController.php`, baris ~335)

**Kasus paling rumit** — `$yayasanId` di method ini baru dihitung SETELAH `$request->validate()` jalan (lewat `resolveYayasanId($request, $lembagaId)`, baris ~355), yang bergantung pada `$isNasional`/`$lembagaId` yang JUGA hasil derivasi dari data tervalidasi. Perlu hitung ulang versi RAW (dari `$request` mentah, sebelum `validate()`) khusus untuk scoping rule ini:

```php
public function storePolicy(Request $request): RedirectResponse
{
    $this->authorize('kehadiran-sdm.kelola-konfigurasi');

    // Dihitung dari input mentah (belum tervalidasi) khusus untuk scoping Rule::exists di
    // bawah -- $yayasanId "resmi" tetap dihitung ulang via resolveYayasanId() setelah validasi
    // seperti sebelumnya, TIDAK diganti, supaya sisa logic method ini tidak berubah.
    $isNasionalMentah = $request->boolean('is_nasional');
    $lembagaIdMentah = $isNasionalMentah ? null : $this->resolveLembagaId($request);
    $yayasanIdUntukValidasi = $this->resolveYayasanId($request, $lembagaIdMentah);

    $data = $request->validate([
        'kategori_tipe' => ['required', 'in:guru,karyawan'],
        'jenis_ptk' => ['required_if:kategori_tipe,guru', 'nullable', 'in:guru_kelas,guru_mapel,kepala_sekolah,tenaga_administrasi,guru_bk'],
        'jenis_karyawan_id' => [
            'required_if:kategori_tipe,karyawan', 'nullable', 'integer',
            Rule::exists('jenis_karyawan_master', 'id')->where('yayasan_id', $yayasanIdUntukValidasi),
        ],
        'jam_masuk' => ['required', 'date_format:H:i'],
        'jam_pulang' => ['nullable', 'date_format:H:i'],
        'toleransi_menit' => ['required', 'integer', 'min:0'],
        'hari_kerja' => ['nullable', 'array'],
        'hari_kerja.*' => ['integer', 'between:0,6'],
        'is_nasional' => ['nullable', 'boolean'],
    ]);
    // ...sisa method (baris $isNasional dst.) TIDAK berubah, tetap dihitung ulang dari $data seperti sebelumnya
```
(Dikonfirmasi lewat pembacaan langsung `resolveYayasanId()`/`resolveLembagaId()` di file yang sama, baris 643-655: keduanya murni read-only — `resolveLembagaId()` cuma baca `session()`/`$request->user()`, `resolveYayasanId()` cuma baca `$request->user()->yayasan_id` atau `Lembaga::find()`. Aman dipanggil 2x tanpa efek samping.)

### A.4 — `Guru\JabatanTambahanController::store()` (`app/Http/Controllers/Admin/Guru/JabatanTambahanController.php`, baris 21)

**Prioritas tertinggi kelompok A** — ini IDOR yang paling langsung dieksploitasi (guru & lembaga sudah diverifikasi `ensureTenantScope()`, tapi payload `jabatan_tambahan_master_id` tidak). Kode saat ini:
```php
'jabatan_tambahan_master_id' => ['required', 'integer', 'exists:jabatan_tambahan_master,id'],
```
`$guru` (parameter route) SUDAH tersedia sebelum validasi — dan `Guru` tidak punya `yayasan_id` langsung (cuma `lembaga_id`, terverifikasi sebelumnya), jadi ambil lewat relasi:
```php
public function store(Request $request, Guru $guru): RedirectResponse
{
    $this->authorize('guru.edit');
    $this->ensureTenantScope($request, $guru);

    $yayasanId = $guru->lembaga?->yayasan_id;

    $data = $request->validate([
        'jabatan_tambahan_master_id' => ['required', 'integer', Rule::exists('jabatan_tambahan_master', 'id')->where('yayasan_id', $yayasanId)],
        'mulai_periode' => ['required', 'date'],
        'akhir_periode' => ['nullable', 'date', 'after_or_equal:mulai_periode'],
        'no_sk' => ['nullable', 'string', 'max:100'],
    ]);
    // ...sisa method tidak berubah
```
Tambah `use Illuminate\Validation\Rule;` ke import file ini (belum ada).

---

## Kelompok B — Resolver Query Pool Tanpa Scope Yayasan (2 file)

**Akar masalah bersama**: kedua resolver melakukan resolusi kebijakan bertingkat (lembaga spesifik → nasional/yayasan), tapi tingkat PERTAMA (`where('lembaga_id', $pegawai->lembaga_id)`) untuk karyawan POOL (`lembaga_id = null`) diam-diam berubah jadi `whereNull('lembaga_id')` TANPA filter `yayasan_id` — bisa cocok dengan baris kebijakan milik yayasan LAIN yang kebetulan punya kategori sama. **Dikonfirmasi empiris via tinker (transaksi rollback)** untuk KEDUANYA — bukan cuma teori.

**Catatan penting**: audit awal (subagent) SEMPAT menyimpulkan `KuotaCutiResolver` "sudah benar" karena melihat ADA cabang yang benar (tier 3/4). Investigasi lanjutan (saya sendiri) menemukan ini SALAH — tier 1/2 (`$spesifikLembaga`/`$flatLembaga`) di file yang SAMA punya bug yang SAMA persis, dan tereksekusi LEBIH DULU sebelum tier 3/4 yang benar sempat dicek. Pelajaran: baca SELURUH method sebelum menyimpulkan "sudah benar", jangan berhenti di cabang pertama yang terlihat benar.

### B.1 — `AttendancePolicyResolver::resolvePolicy()` (`app/Domains/Sdm/Services/AttendancePolicyResolver.php`, baris 16-35)

Kode saat ini:
```php
public function resolvePolicy(Model $pegawai): ?AttendancePolicy
{
    $kolomKategori = $pegawai instanceof Guru ? 'jenis_ptk' : 'jenis_karyawan_id';
    $nilaiKategori = $pegawai instanceof Guru ? $pegawai->jenis_ptk : $pegawai->jenis_karyawan_id;

    $policyLembaga = AttendancePolicy::withoutGlobalScope(TenantScope::class)
        ->where('lembaga_id', $pegawai->lembaga_id)
        ->where($kolomKategori, $nilaiKategori)
        ->first();

    if ($policyLembaga) {
        return $policyLembaga;
    }

    return AttendancePolicy::withoutGlobalScope(TenantScope::class)
        ->whereNull('lembaga_id')
        ->where('yayasan_id', $pegawai->lembaga->yayasan_id)
        ->where($kolomKategori, $nilaiKategori)
        ->first();
}
```
**Fix** — pegawai POOL (`lembaga_id === null`) tidak mungkin punya kebijakan "per-lembaga spesifik" (dia tidak terikat 1 lembaga), jadi tier pertama HARUS dilewati total untuk pool, langsung ke tier kedua (yang SUDAH benar difilter `yayasan_id`):
```php
public function resolvePolicy(Model $pegawai): ?AttendancePolicy
{
    $kolomKategori = $pegawai instanceof Guru ? 'jenis_ptk' : 'jenis_karyawan_id';
    $nilaiKategori = $pegawai instanceof Guru ? $pegawai->jenis_ptk : $pegawai->jenis_karyawan_id;
    $yayasanId = $pegawai->lembaga_id !== null ? $pegawai->lembaga->yayasan_id : $pegawai->yayasan_id;

    if ($pegawai->lembaga_id !== null) {
        $policyLembaga = AttendancePolicy::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $pegawai->lembaga_id)
            ->where($kolomKategori, $nilaiKategori)
            ->first();

        if ($policyLembaga) {
            return $policyLembaga;
        }
    }

    return AttendancePolicy::withoutGlobalScope(TenantScope::class)
        ->whereNull('lembaga_id')
        ->where('yayasan_id', $yayasanId)
        ->where($kolomKategori, $nilaiKategori)
        ->first();
}
```
(`Guru` selalu punya `lembaga_id` terisi — tidak ada konsep pool guru, terverifikasi audit sebelumnya — jadi cabang `$pegawai->yayasan_id` di baris `$yayasanId = ...` HANYA pernah kena untuk `Karyawan` pool, yang PUNYA kolom `yayasan_id` langsung di `$fillable`. `Guru` tidak punya `yayasan_id` di `$fillable`, tapi karena `Guru` SELALU punya `lembaga_id !== null`, baris itu tidak pernah dieksekusi untuk `Guru` — verifikasi ini saat implementasi dengan test khusus Guru untuk memastikan tidak ada regresi type error.)

### B.2 — `KuotaCutiResolver::resolveConfig()` (`app/Domains/Sdm/Services/KuotaCutiResolver.php`, baris 22-61)

Pola bug identik B.1, di 2 tier (`$spesifikLembaga` baris 27-30, `$flatLembaga` baris 35-39) — keduanya HARUS dilewati untuk pegawai pool, langsung ke tier 3/4 yang sudah benar:

```php
public function resolveConfig(Model $pegawai): ?KuotaCutiConfig
{
    $kolomKategori = $pegawai instanceof Guru ? 'jenis_ptk' : 'jenis_karyawan_id';
    $nilaiKategori = $pegawai instanceof Guru ? $pegawai->jenis_ptk : $pegawai->jenis_karyawan_id;
    $yayasanId = $pegawai->lembaga_id !== null ? $pegawai->lembaga->yayasan_id : $pegawai->yayasan_id;

    if ($pegawai->lembaga_id !== null) {
        $spesifikLembaga = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $pegawai->lembaga_id)
            ->where($kolomKategori, $nilaiKategori)
            ->first();
        if ($spesifikLembaga) {
            return $spesifikLembaga;
        }

        $flatLembaga = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
            ->where('lembaga_id', $pegawai->lembaga_id)
            ->whereNull('jenis_ptk')
            ->whereNull('jenis_karyawan_id')
            ->first();
        if ($flatLembaga) {
            return $flatLembaga;
        }
    }

    $spesifikNasional = KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
        ->whereNull('lembaga_id')
        ->where('yayasan_id', $yayasanId)
        ->where($kolomKategori, $nilaiKategori)
        ->first();
    if ($spesifikNasional) {
        return $spesifikNasional;
    }

    return KuotaCutiConfig::withoutGlobalScope(TenantScope::class)
        ->whereNull('lembaga_id')
        ->where('yayasan_id', $yayasanId)
        ->whereNull('jenis_ptk')
        ->whereNull('jenis_karyawan_id')
        ->first();
}
```
(Baris `$yayasanId = ...` PERSIS sama pola B.1 — jangan ditulis beda.)

**Verifikasi WAJIB**: grep ulang `app/Domains/Sdm/Services/` untuk pola `where('lembaga_id', $pegawai->lembaga_id)`/`where('lembaga_id', $.*->lembaga_id)` SETELAH fix diterapkan, pastikan cuma 2 file ini yang punya pola tersebut (sudah dikonfirmasi saat spec ditulis, verifikasi ulang tidak ada yang baru muncul/terlewat).

---

## Kelompok C — Karyawan Pool Terlewat dari Alur Operasional

### C.1 — `TandaiAlpaOtomatisSdm` tidak pernah memproses karyawan pool (`app/Console/Commands/TandaiAlpaOtomatisSdm.php`)

Command saat ini HANYA loop `foreach (Lembaga::all() as $lembaga)` dan query `Karyawan::where('lembaga_id', $lembaga->id)` — karyawan pool (`lembaga_id=null`) tidak pernah masuk iterasi manapun.

**Prasyarat migrasi (Keputusan Bisnis poin 2 di atas)** — buat migrasi baru `php artisan make:migration make_lembaga_id_nullable_on_attendance_events_table --no-interaction`:
```php
public function up(): void
{
    Schema::table('attendance_events', function (Blueprint $table) {
        $table->dropForeign(['lembaga_id']);
    });

    Schema::table('attendance_events', function (Blueprint $table) {
        $table->unsignedBigInteger('lembaga_id')->nullable()->change();
    });

    Schema::table('attendance_events', function (Blueprint $table) {
        $table->foreign('lembaga_id')->references('id')->on('lembaga')->cascadeOnDelete();
    });
}

public function down(): void
{
    Schema::table('attendance_events', function (Blueprint $table) {
        $table->dropForeign(['lembaga_id']);
    });

    Schema::table('attendance_events', function (Blueprint $table) {
        $table->unsignedBigInteger('lembaga_id')->nullable(false)->change();
    });

    Schema::table('attendance_events', function (Blueprint $table) {
        $table->foreign('lembaga_id')->references('id')->on('lembaga')->cascadeOnDelete();
    });
}
```
(FK di-drop dulu lalu ditambah lagi setelah ubah nullability — pola aman lintas versi Laravel untuk mengubah kolom yang punya foreign key constraint, tidak bergantung pada `doctrine/dbal`. `down()` akan GAGAL kalau saat itu sudah ada baris `lembaga_id IS NULL` di data — ini WAJAR/lossy secara sengaja karena reversal butuh nilai lembaga yang valid untuk baris yang sebelumnya sengaja dibuat null; kalau perlu rollback di data yang sudah terlanjur ada baris pool, itu keputusan manual terpisah, bukan tanggung jawab `down()` otomatis.)

**Fix** — tambah pass KEDUA setelah loop per-lembaga, khusus karyawan pool per-yayasan:
```php
public function handle(): int
{
    $tanggal = now()->subDay()->toImmutable();
    $jumlahDitandai = 0;

    foreach (Lembaga::all() as $lembaga) {
        $pegawaiList = collect()
            ->concat(Guru::where('lembaga_id', $lembaga->id)->where('status_aktif', 'aktif')->get())
            ->concat(Karyawan::where('lembaga_id', $lembaga->id)->where('status_aktif', 'aktif')->get())
            ->filter(fn ($pegawai) => ! $this->resolver->resolveLibur($pegawai, $tanggal)['libur'])
            ->filter(fn ($pegawai) => ! $this->punyaPengajuanPending($pegawai, $tanggal));

        $jumlahDitandai += $this->tandaiPegawaiTanpaRecord($pegawaiList, $lembaga, $tanggal);
    }

    // Pass kedua: karyawan pool (lembaga_id null), diproses per-yayasan (bukan per-lembaga,
    // karena tidak terikat lembaga manapun). Guru TIDAK punya konsep pool (selalu lembaga_id
    // terisi, terverifikasi audit sebelumnya) sehingga tidak perlu pass tambahan untuk Guru.
    foreach (Yayasan::all() as $yayasan) {
        $karyawanPoolList = Karyawan::whereNull('lembaga_id')
            ->where('yayasan_id', $yayasan->id)
            ->where('status_aktif', 'aktif')
            ->get()
            ->filter(fn ($pegawai) => ! $this->resolver->resolveLibur($pegawai, $tanggal)['libur'])
            ->filter(fn ($pegawai) => ! $this->punyaPengajuanPending($pegawai, $tanggal));

        $jumlahDitandai += $this->tandaiPegawaiTanpaRecordPool($karyawanPoolList, $yayasan, $tanggal);
    }

    $this->info("{$jumlahDitandai} pegawai ditandai Alpa otomatis untuk tanggal {$tanggal->toDateString()}.");

    return self::SUCCESS;
}
```
Tambah `use App\Models\Yayasan;` ke import.

`tandaiPegawaiTanpaRecord()` saat ini (baris 66-95) menerima parameter `Lembaga $lembaga` dan memakainya untuk `'lembaga_id' => $lembaga->id` di `attendanceEvents()->create()` (baris 81) — karyawan pool TIDAK punya `lembaga_id` untuk dicatat di situ. Buat method BARU `tandaiPegawaiTanpaRecordPool()` (BUKAN modifikasi method existing, supaya tidak mengubah perilaku pegawai ber-lembaga):
```php
private function tandaiPegawaiTanpaRecordPool(Collection $pegawaiList, Yayasan $yayasan, \Carbon\CarbonImmutable $tanggal): int
{
    $jumlah = 0;

    foreach ($pegawaiList as $pegawai) {
        $sudahAda = AttendanceRecord::where('pegawai_type', $pegawai::class)
            ->where('pegawai_id', $pegawai->id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->exists();

        if ($sudahAda) {
            continue;
        }

        $pegawai->attendanceEvents()->create([
            'lembaga_id' => null,
            'method' => AttendanceMethod::System,
            'arah' => 'masuk',
            'status' => AttendanceStatus::Alpa,
            'waktu' => $tanggal->setTime(23, 59),
            'dicatat_oleh_user_id' => null,
            'catatan' => 'Ditandai otomatis oleh sistem — tidak ada aktivitas kehadiran pada hari kerja ini (karyawan pool yayasan).',
        ]);

        $this->aggregator->sync($pegawai, $tanggal);
        $jumlah++;
    }

    return $jumlah;
}
```
(Kolom `lembaga_id` di `attendance_events` sudah dipastikan nullable lewat migrasi prasyarat di atas — `'lembaga_id' => null` di sini AMAN, tidak akan menabrak constraint NOT NULL.)

**Dependensi WAJIB**: C.1 HANYA aman dijalankan SETELAH B.1 (`AttendancePolicyResolver`) diperbaiki — `resolveLibur()` (dipanggil baris `resolver->resolveLibur($pegawai, $tanggal)`) untuk pegawai pool tanpa fix B.1 akan memanggil `$this->kalenderResolver->resolve($pegawai->lembaga, $tanggal)` dengan `$pegawai->lembaga` bernilai `null` — `KalenderKerjaSdmResolver::resolve()` mensyaratkan parameter `Lembaga $lembaga` NON-NULLABLE (`app/Domains/Sdm/Services/KalenderKerjaSdmResolver.php:16`), jadi akan `TypeError` fatal untuk SETIAP karyawan pool yang policy-nya tidak match/tidak set `hari_kerja`. Fix B.1 SENDIRI TIDAK menyelesaikan ini — `resolveLibur()` (bukan `resolvePolicy()`) juga perlu penyesuaian:

**Fix tambahan — `AttendancePolicyResolver::resolveLibur()`** (baris 40-53), sesuai Keputusan Bisnis di atas (pool = default selalu hari kerja kecuali ada entri kalender eksplisit):
```php
public function resolveLibur(Model $pegawai, CarbonInterface $tanggal): array
{
    $policy = $this->resolvePolicy($pegawai);

    if ($policy && $policy->hari_kerja !== null) {
        $adalahHariKerja = in_array($tanggal->dayOfWeek, $policy->hari_kerja, true);

        return $adalahHariKerja
            ? ['libur' => false, 'alasan' => 'Hari kerja sesuai kebijakan peran']
            : ['libur' => true, 'alasan' => 'Hari libur sesuai kebijakan peran'];
    }

    if ($pegawai->lembaga_id === null) {
        // Karyawan pool tidak terikat 1 lembaga, jadi tidak punya sumber "hari libur mingguan"
        // (Yayasan tidak punya kolom setara Lembaga.hari_libur_mingguan_sdm -- keputusan bisnis
        // eksplisit: default SELALU hari kerja untuk pool, kecuali policy di atas menyatakan
        // lain, atau entri KalenderKerjaSdm nasional/yayasan eksplisit menandai tanggal ini libur.
        return $this->resolveLiburPool($pegawai->yayasan_id, $tanggal);
    }

    return $this->kalenderResolver->resolve($pegawai->lembaga, $tanggal);
}

private function resolveLiburPool(int $yayasanId, CarbonInterface $tanggal): array
{
    $entriNasional = KalenderKerjaSdm::withoutGlobalScope(TenantScope::class)
        ->whereNull('lembaga_id')
        ->where('yayasan_id', $yayasanId)
        ->where(function ($q) use ($tanggal) {
            $tgl = $tanggal->toDateString();
            $q->whereDate('tanggal', '<=', $tgl)
                ->where(fn ($q2) => $q2->whereDate('tanggal_selesai', '>=', $tgl)
                    ->orWhere(fn ($q3) => $q3->whereNull('tanggal_selesai')->whereDate('tanggal', '>=', $tgl))
                );
        })
        ->first();

    if ($entriNasional) {
        return [
            'libur' => $entriNasional->tipe === TipeKalenderKerjaSdm::Libur,
            'alasan' => $entriNasional->nama,
        ];
    }

    return ['libur' => false, 'alasan' => 'Hari kerja efektif (karyawan pool, default hari kerja)'];
}
```
Tambah import `App\Domains\Sdm\Models\KalenderKerjaSdm` dan `App\Domains\Sdm\Enums\TipeKalenderKerjaSdm` ke `AttendancePolicyResolver.php`.

### C.2 — Karyawan pool tidak muncul di dropdown pemilih karyawan (`AttendanceConfigurationController.php:107`, `AttendanceController.php:52`)

**PERINGATAN — kedua titik ini BUKAN cuma tambal query, ada `.map()`/`.values()` transformasi setelah `.get()` yang HARUS dipertahankan verbatim** (dropped secara tidak sengaja di draf pertama spec ini — dikoreksi di sini setelah re-review langsung ke kode). Kedua file JUGA TIDAK identik: `AttendanceConfigurationController::index()` SUDAH punya `$yayasanId` terhitung di baris 51 (`$this->resolveYayasanId($request, $lembagaId)`) sebelum baris `$karyawanList`; `AttendanceController::create()` **TIDAK PUNYA `$yayasanId` sama sekali** (cuma `resolveLembagaId()`, tidak ada helper yayasan) — harus dihitung baru di titik ini.

**Kode saat ini, KEDUANYA PERSIS SAMA** (`AttendanceConfigurationController.php:106-109`, `AttendanceController.php:48-51`):
```php
$karyawanList = $lembagaId
    ? Karyawan::where('lembaga_id', $lembagaId)->with('person')->orderByNama()->get(['karyawan.id', 'karyawan.nama', 'karyawan.email', 'karyawan.person_id'])
        ->map(fn ($k) => ['id' => (string) $k->id, 'nama' => $k->nama, 'subtext' => $k->email ?? ''])->values()
    : collect();
```

**Fix — `AttendanceConfigurationController.php`** (`$yayasanId` reuse yang sudah ada di baris 51, TIDAK dihitung ulang):
```php
$karyawanList = $lembagaId
    ? Karyawan::withoutGlobalScope(TenantScope::class)
        ->where(function ($q) use ($lembagaId, $yayasanId) {
            $q->where('lembaga_id', $lembagaId)
                ->orWhere(fn ($q2) => $q2->whereNull('lembaga_id')->where('yayasan_id', $yayasanId));
        })
        ->with('person')->orderByNama()->get(['karyawan.id', 'karyawan.nama', 'karyawan.email', 'karyawan.person_id'])
        ->map(fn ($k) => ['id' => (string) $k->id, 'nama' => $k->nama, 'subtext' => $k->email ?? ''])->values()
    : collect();
```

**Fix — `AttendanceController.php`** (`$yayasanId` BELUM ada, tambahkan SEBELUM baris `$karyawanList`, pola sama seperti `KaryawanController::index()`'s `Lembaga::find($lembagaId)?->yayasan_id`):
```php
public function create(Request $request): View
{
    $this->authorize('kehadiran-sdm.catat');

    $lembagaId = $this->resolveLembagaId($request);
    $yayasanId = $lembagaId ? Lembaga::find($lembagaId)?->yayasan_id : null;

    $guruList = $lembagaId
        ? Guru::where('lembaga_id', $lembagaId)->with('person')->orderByNama()->get(['guru.id', 'guru.nama', 'guru.nip', 'guru.nuptk', 'guru.person_id'])
            ->map(fn ($g) => ['id' => (string) $g->id, 'nama' => $g->nama, 'subtext' => $g->nip ? 'NIP: '.$g->nip : ($g->nuptk ? 'NUPTK: '.$g->nuptk : '')])->values()
        : collect();
    $karyawanList = $lembagaId
        ? Karyawan::withoutGlobalScope(TenantScope::class)
            ->where(function ($q) use ($lembagaId, $yayasanId) {
                $q->where('lembaga_id', $lembagaId)
                    ->orWhere(fn ($q2) => $q2->whereNull('lembaga_id')->where('yayasan_id', $yayasanId));
            })
            ->with('person')->orderByNama()->get(['karyawan.id', 'karyawan.nama', 'karyawan.email', 'karyawan.person_id'])
            ->map(fn ($k) => ['id' => (string) $k->id, 'nama' => $k->nama, 'subtext' => $k->email ?? ''])->values()
        : collect();
    // ...sisa method (titikAbsen, return view) TIDAK berubah
```
Tambah `use App\Models\Lembaga;` dan `use App\Models\Scopes\TenantScope;` ke import `AttendanceController.php` (dikonfirmasi KEDUANYA belum ada lewat pembacaan import file saat ini).

---

## Kelompok D — `PersonAlreadyExistsException` Tidak Ditangkap di `GuruController::store()`

`app/Http/Controllers/Admin/GuruController.php`, method `store()` (baris 92-156) memanggil `CreatePersonAction::execute()` di dalam `DB::transaction()` TANPA try/catch. `validateProfil()` sudah melakukan pre-check duplikat NIK (baris 268-280), TAPI ada celah race condition: 2 submit bersamaan lolos pre-check yang sama, keduanya masuk `CreatePersonAction`, salah satu akan menerima `PersonAlreadyExistsException` yang TIDAK tertangkap — 500 mentah, bukan pesan validasi ramah.

**Fix**:
```php
public function store(Request $request): RedirectResponse
{
    $this->authorize('guru.create');

    $data = $this->validateProfil($request);

    $lembagaId = $this->resolveLembagaId($request);
    if ($lembagaId === null) {
        return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah data guru.'])->withInput();
    }

    try {
        DB::transaction(function () use ($data, $lembagaId) {
            // ...isi transaction() TIDAK berubah sama sekali
        });
    } catch (PersonAlreadyExistsException $exception) {
        return back()
            ->withErrors(['nik' => 'NIK ini sudah terdaftar untuk profil lain di yayasan ini.'])
            ->withInput();
    }

    return redirect()->route('admin.guru.index')->with('status', 'Data guru & akun berhasil dibuat.');
}
```
Tambah `use App\Domains\Identity\Exceptions\PersonAlreadyExistsException;` ke import (namespace dikonfirmasi persis lewat lokasi file `app/Domains/Identity/Exceptions/PersonAlreadyExistsException.php`).

---

## Kelompok E — Index Karyawan: Snapshot Client-Side & Tanpa Pencarian NIK

**Prioritas PALING RENDAH di spec ini** (tidak ada kebocoran data, murni UX) — pola identik bug lama OrangTua (`.agents/specs/2026-09-07-orang-tua-siswa-person-tautan.md` Bug #1). `resources/views/admin/karyawan/index.blade.php` mengirim SELURUH `$karyawanList` sekali via `@json($spaItems)`, filter/search/pagination murni Alpine di atas snapshot statis, TANPA mekanisme refresh. Field `nik` bahkan TIDAK disertakan di payload JSON (baris 228-244, dikonfirmasi lewat pembacaan langsung) — NIK tidak bisa dicari sama sekali dari list ini, padahal itu identifier utama yang dipakai di form create/edit.

**Fix minimal (di luar full rombak jadi server-side seperti OrangTua, supaya scope spec ini tidak melebar)** — `resources/views/admin/karyawan/index.blade.php`:

1. Tambah field `nik` ke `$spaItems` mapping (baris 228-244), sisipkan setelah `'nama' => $k->nama,` (baris 231):
```php
'nama' => $k->nama,
'nik' => $k->person?->nik,
```
2. Tambah `nik` ke kondisi pencarian client-side (`filteredItems` getter, baris 285) — kondisi saat ini:
```js
res = res.filter(i => i.nama.toLowerCase().includes(q) || i.jenis_nama.toLowerCase().includes(q) || i.lembaga_nama.toLowerCase().includes(q));
```
Sesudah:
```js
res = res.filter(i => i.nama.toLowerCase().includes(q) || (i.nik && i.nik.toLowerCase().includes(q)) || i.jenis_nama.toLowerCase().includes(q) || i.lembaga_nama.toLowerCase().includes(q));
```
(`i.nik &&` guard diperlukan karena `nik` bisa `null` kalau `person` relasinya kosong — beda dari `nama`/`jenis_nama`/`lembaga_nama` yang punya fallback `??` di PHP saat mapping, sedangkan `nik` sengaja TIDAK diberi fallback string supaya nilai `null` asli tidak salah dikira NIK literal "null"/"-" saat dicari.)

TIDAK mengubah arsitektur SPA jadi server-side (beda keputusan dari OrangTua) — karena tidak ada bukti bug fungsional NYATA di sini (tidak ada alur "tautkan dari halaman lain" yang bikin data basi seperti kasus OrangTua-Siswa), murni penambahan field pencarian yang hilang.

---

## Di Luar Scope / Backlog Terpisah

1. **Kolom `hari_libur_mingguan_sdm` di `Yayasan`** — sengaja TIDAK ditambahkan (keputusan bisnis eksplisit, lihat bagian atas). Kalau nanti user berubah pikiran, ini backlog terpisah.
2. **Rombak arsitektur index Karyawan jadi server-side penuh** (seperti OrangTua) — di luar scope, Kelompok E cuma tambal pencarian NIK.
3. **Method lain di `AttendanceConfigurationController`/`AttendanceController`** yang mungkin py pola serupa Kelompok C.2 tapi belum diverifikasi eksplisit di spec ini — TIDAK diperiksa, cakupan dibatasi ke 2 titik yang sudah dikonfirmasi.

---

## Ringkasan Test yang Wajib Ditambahkan

| Kelompok | Test |
|---|---|
| A.1-A.4 | Feature test per controller — submit `jenis_karyawan_id`/`jabatan_tambahan_master_id` milik yayasan LAIN, assert 422 (validasi gagal), bukan lolos |
| B.1 | Tambahkan ke `tests/Feature/Sdm/AttendancePolicyTenantIsolationTest.php` (SUDAH ADA — dicek isinya, cuma menguji 1 aspek berbeda: bypass TenantScope aktor yang login, BUKAN kebocoran lintas-yayasan pool. Judul filenya "TenantIsolation" tapi belum benar-benar menguji isolasi lintas-yayasan untuk pool — tambahkan test baru di file ini, jangan bikin file terpisah) — skenario PERSIS tinker verifikasi (karyawan pool yayasan A, AttendancePolicy pool yayasan B kategori sama), assert hasil BUKAN milik yayasan B |
| B.2 | Tambahkan ke `tests/Feature/Sdm/KuotaCutiResolverTest.php` (SUDAH ADA — baca dulu isinya saat plan ditulis untuk pola konsisten) — sama skenario B.1 |
| C.1 | Feature test `TandaiAlpaOtomatisSdm` — karyawan pool tanpa AttendanceRecord kemarin, assert ditandai Alpa setelah command jalan; test terpisah untuk hari yang seharusnya "hari kerja default" (tanpa entri kalender apapun) |
| C.2 | Feature test kedua controller — karyawan pool muncul di `$karyawanList` saat lembaga aktif dipilih |
| D | Feature test `GuruController@store` — simulasikan `PersonAlreadyExistsException` (mis. lewat 2 person dgn nik_hash sama dibuat manual sebelum submit), assert redirect+error, BUKAN 500 |
| E | Feature/Dusk-level assertion — cari karyawan by NIK di index, assert hasil ketemu |

Regresi wajib (SEMUA file berikut SUDAH ADA di codebase, dikonfirmasi lewat pencarian saat spec ditulis): `KaryawanCrudTest.php`, `GuruCrudTest.php`, `GuruRelationalProfileTest.php`, `AttendancePolicyResolverTest.php` (`tests/Unit/Services/`), `AttendancePolicyTenantIsolationTest.php`, `AttendancePolicyControllerTest.php`, `AttendancePolicyViewTest.php`, `AttendancePolicyModelTest.php` (`tests/Feature/Sdm/`), `KuotaCutiResolverTest.php`, `KuotaCutiConfigTest.php` (`tests/Feature/Sdm/`), `KuotaCutiConfigControllerTest.php` (`tests/Feature/Admin/`), `TandaiAlpaOtomatisSdmTest.php` (`tests/Feature/Sdm/`), `AttendanceConfigurationControllerTest.php`, `AttendanceConfigurationKalenderControllerTest.php`, `AttendanceControllerTest.php` (`tests/Feature/Admin/`).
