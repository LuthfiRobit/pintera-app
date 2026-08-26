# Spec: Fondasi Akademik Multi-Jenjang — Sprint 3 (Curriculum Phase)

**Status:** Draft untuk review — belum masuk plan eksekusi.
**Branch:** `akademik-v2`
**Bergantung pada:** Tidak bergantung teknis pada Sprint 1-2 (fase tidak menyentuh `subjek_type`/`assessment_type`), tapi diurutkan setelahnya sesuai roadmap.
**Technical debt terkait (TIDAK bagian scope ini):** `TD-AKADEMIK-001` (`ElemenCp` vs `MataPelajaran.tipe=aspek_perkembangan`) tetap dicatat di `PETA_PENGEMBANGAN.md`, tidak disentuh Sprint 3.

## Latar Belakang

`Kelas.tingkat` (string bebas, mis. `"1"`-`"12"` untuk SD-SMK, `"A"`/`"B"` untuk KB/TK) hanya label rombel — bukan representasi Fase Kurikulum Merdeka (Fondasi, A-F) yang dibutuhkan untuk reasoning kurikulum (nanti: pemetaan CP/TP, rapor per-fase, dll). Sprint 3 memperkenalkan `Fase` sebagai entitas eksplisit terpisah dari `tingkat`.

**Koreksi desain krusial (hasil diskusi)**: mapping default `bentuk_pendidikan` + `tingkat` → `fase` **tidak boleh** jadi business rule hardcoded (`match()`/`if-else`) di service — karena kebijakan kurikulum bisa berubah (revisi pemerintah, variasi per sekolah/naungan). Mapping default harus jadi **data yang bisa dikonfigurasi**, bukan logika yang tertanam permanen di kode.

## Keputusan Desain

1. **`fase` tetap global dan stabil, bukan per-lembaga.** `Foundation`/`A`-`F` adalah vocabulary resmi Kurikulum Merdeka — definisinya sendiri tidak bervariasi antar lembaga. Yang bervariasi adalah *mapping*-nya ke tingkat/bentuk_pendidikan, bukan fase itu sendiri. Tabel `fase` sama pola dengan `elemen_cp` (Sprint 1): tidak tenant-scoped, tidak ada `lembaga_id`.

2. **Mapping default adalah DATA di tabel `fase_default_mapping`, bukan kode.** `FaseDefaultResolver` murni **query** ke tabel ini — tidak ada `match($bentukPendidikan)`/`if ($tingkat === ...)` di dalam resolver. Kalau aturan baru muncul (kurikulum baru, revisi tingkat→fase, variasi naungan), itu operasi **tambah/ubah baris data**, bukan deploy kode baru.

3. **Precedence resolusi 4 tingkat**, dari paling spesifik ke paling umum:
   ```text
   1. lembaga_id = X, tingkat = exact match
   2. lembaga_id = X, tingkat = NULL (catch-all lembaga X)
   3. lembaga_id = NULL, tingkat = exact match   (default platform)
   4. lembaga_id = NULL, tingkat = NULL           (catch-all platform utk bentuk_pendidikan itu)
   → tidak ada yang cocok = null (tidak ada suggestion, admin isi manual)
   ```
   `lembaga_id = NULL` punya semantik **"default platform"**, bukan "berlaku untuk lembaga manapun secara ambigu" — ini eksplisit dan diuji (lihat §5 uniqueness).

4. **`kelas.fase_id` adalah snapshot assignment, bukan nilai turunan hidup.** Resolver hanya dipanggil sekali, saat form create Kelas dibuka (untuk pre-fill dropdown) — hasilnya disimpan sebagai kolom biasa. Mengubah baris `fase_default_mapping` di kemudian hari **tidak pernah** mengubah `fase_id` Kelas yang sudah ada, karena tidak ada job/observer yang menyinkronkan ulang. Ini otomatis benar by construction (bukan butuh guard tambahan) selama resolver tidak dipanggil ulang di luar konteks create/reset-manual.

5. **Admin selalu bisa override.** Dropdown fase di form Kelas pre-selected dari `FaseDefaultResolver`, tapi tetap `<select>` biasa yang bebas diganti — sama pola UX dengan `assessment_type` di Sprint 2 (§4 spec Sprint 2).

6. **Tidak ada dimensi "pilih kurikulum" (Merdeka vs K13 vs KMA-450 Kemenag) di Sprint 3.** Hanya satu ruleset (Kurikulum Merdeka) yang di-seed sekarang, karena hanya itu yang benar-benar dipakai lembaga aktif saat ini. Skema `fase_default_mapping` sengaja dirancang agar penambahan dimensi ini nanti (misal kolom `kurikulum` nullable) adalah **extend**, bukan rewrite — tapi kolom itu TIDAK dibuat sekarang (YAGNI: kebutuhan itu belum nyata, sesuai `docs/superpowers/specs/2026-07-24-presensi-asesmen-design.md` §10 yang sendiri menyatakan K13/KTSP sengaja ditunda).

7. **Tidak ada curriculum designer, tidak ada versioning kurikulum, tidak ada mapping CP/TP.** Sprint 3 murni fondasi: fase sbg reference, mapping sbg config, assignment per Kelas. Reasoning kurikulum lanjutan (kalau nanti dibutuhkan) dibangun di atas fondasi ini, bukan bagian Sprint 3.

8. **"Tidak ada versioning" berlaku untuk KONFIGURASI mapping, bukan untuk assignment.** Dua hal ini berbeda dan tidak boleh tercampur:
   - **Versioning mapping** (TIDAK dibangun Sprint 3): tidak ada riwayat "SD tingkat 1 dulu → A, sekarang → B, berlaku sejak tanggal X". Edit mapping langsung menimpa state aktif satu-satunya.
   - **Snapshot assignment** (SUDAH dibangun via `kelas.fase_id`): setiap Kelas menyimpan hasil resolusi pada saat dibuat, permanen sampai diubah manual. Ini bukan "history" dalam arti audit trail — ini fakta domain biasa (kolom biasa di baris Kelas), sama seperti `nama`/`tingkat`.
   Rumusan tegas: **tidak ada configuration history, tapi assignment snapshot tetap ada.** Distinction ini penting dipertahankan eksplisit karena Sprint kurikulum lanjutan nanti kemungkinan besar akan butuh membedakan keduanya lagi.

9. **`fase_default_mapping` bukan "konfigurasi kurikulum"** — namanya sengaja spesifik ("default mapping"/assignment policy), bukan `kurikulum_lembaga` atau semacamnya, supaya tidak ada kesan Sprint 3 sudah menyelesaikan domain kurikulum. Struktur kurikulum sesungguhnya (Subjek, CP, TP, Assessment per fase) adalah lapisan terpisah yang BELUM dibangun:
   ```text
   Fase (reference)
     └── FaseDefaultMapping (assignment policy)
             └── Kelas.fase_id (assignment)
                     └── [BELUM ADA] Curriculum Structure (Subjek/CP/TP/Assessment per fase)
   ```
   Tabel `fase_default_mapping` hanya menjawab "fase apa yang cocok untuk Kelas ini", bukan "apa isi kurikulum fase itu".

## §1. Skema Database

### 1a. Tabel `fase` (global, non-tenant)

```php
Schema::create('fase', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 20)->unique(); // 'foundation', 'a', 'b', 'c', 'd', 'e', 'f'
    $table->string('nama'); // 'Fase Fondasi', 'Fase A', dst.
    $table->unsignedTinyInteger('urutan'); // 0=foundation, 1=A, ..., 6=F
    $table->timestamps();
});
```
**Catatan tegas soal `urutan`**: kolom ini murni **display/sort order** (urutan tampil di dropdown/tabel admin), BUKAN semantic level pendidikan. Jangan diasumsikan bahwa `Fase F > Fase E > Fase D` berarti sesuatu secara business logic (mis. "boleh naik dari D ke F langsung", "F selalu lebih tinggi dari D dalam semua reasoning kurikulum") — itu klaim domain yang belum pernah divalidasi dan di luar cakupan Sprint 3. Kalau nanti ada kebutuhan nyata seperti "fase setelah X"/"fase sebelum X" sbg business rule, itu keputusan desain terpisah yang harus dibahas eksplisit saat kebutuhannya muncul, bukan diam-diam diwarisi dari kolom `urutan` yang sekarang hanya untuk sorting UI.

### 1b. Tabel `fase_default_mapping`

```php
Schema::create('fase_default_mapping', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lembaga_id')->nullable()->constrained('lembaga')->cascadeOnDelete();
    $table->string('bentuk_pendidikan', 10); // 'KB','TPA','SPS','TK','SD','SMP','SMA','SMK','SLB' — sesuai whitelist Admin\LembagaController
    $table->string('tingkat', 10)->nullable(); // exact match ke Kelas.tingkat; NULL = catch-all utk bentuk_pendidikan ini
    $table->foreignId('fase_id')->constrained('fase')->restrictOnDelete();
    $table->timestamps();
});
```

`restrictOnDelete()` pada `fase_id`: mencegah penghapusan baris `fase` yang masih dirujuk mapping — kesalahan konfigurasi harus gagal keras, bukan silently null-kan mapping.

### 1c. `kelas.fase_id`

```php
Schema::table('kelas', function (Blueprint $table) {
    $table->foreignId('fase_id')->nullable()->after('tingkat')->constrained('fase')->nullOnDelete();
});
```
`nullOnDelete()`: kalau baris `fase` dihapus (kasus langka, hanya mungkin kalau tidak ada mapping merujuknya lagi — lihat 1b), Kelas yang assignment-nya sudah snapshot tidak ikut terhapus, cuma `fase_id`-nya jadi `NULL` (fallback ke `tingkat` saja, seperti sebelum Sprint 3). Non-breaking: kolom nullable, semua Kelas existing otomatis `fase_id = NULL`.

## §2. Uniqueness / Anti-Ambiguitas `fase_default_mapping`

**Masalah yang harus dicegah**: dua baris platform (`lembaga_id = NULL`) dengan `bentuk_pendidikan` + `tingkat` sama tapi `fase_id` beda — resolver akan mengambil salah satu secara implisit (mis. berdasar urutan insert/`orderBy`), yang merupakan **konfigurasi ambigu**, bukan bug resolver.

**Constraint DB tidak portable secara langsung** karena kombinasi nullable (`lembaga_id` NULL = platform, `tingkat` NULL = catch-all) — MySQL memperlakukan `NULL` sebagai "tidak sama dengan NULL lain" di unique index komposit biasa, sehingga index unique naif `(lembaga_id, bentuk_pendidikan, tingkat)` **tidak** akan mencegah duplikat saat kedua kolom nullable itu sama-sama NULL.

**Solusi: kolom bantu ternormalisasi + unique index di atasnya**, bukan pada kolom nullable asli:

```php
Schema::table('fase_default_mapping', function (Blueprint $table) {
    $table->unsignedBigInteger('lembaga_key')->storedAs('COALESCE(lembaga_id, 0)');
    $table->string('tingkat_key', 10)->storedAs("COALESCE(tingkat, '*')");
    $table->unique(['lembaga_key', 'bentuk_pendidikan', 'tingkat_key'], 'fase_default_mapping_scope_unique');
});
```
(`storedAs` = generated/computed column MySQL — `lembaga_id=NULL` → `lembaga_key=0`, `tingkat=NULL` → `tingkat_key='*'`; kombinasi keduanya sekarang punya nilai konkret yang bisa di-unique-kan.) `0` aman dipakai sebagai sentinel karena `lembaga.id` auto-increment mulai dari `1`; `'*'` aman karena tidak pernah muncul sebagai nilai `tingkat` nyata (whitelist tingkat: digit `1`-`12` atau huruf `A`/`B`).

**Compatibility sudah diverifikasi untuk environment project ini**: server dev memakai MySQL 8.0.30 (`d:/laragon/bin/mysql/mysql-8.0.30-winx64`) — `STORED GENERATED` column dan `UNIQUE` index di atasnya didukung penuh sejak MySQL 5.7, jauh di bawah versi yang dipakai. Tidak ada risiko compatibility yang perlu ditandai sbg unknown di plan; implementer TETAP wajib menjalankan migration ini di environment test project (bukan asumsi) sbg bagian normal siklus TDD migration, tapi tidak perlu riset alternatif engine.

**Application-level validation** (lapis kedua, defense-in-depth — pesan error yang jelas sebelum menyentuh DB constraint):
`StoreFaseDefaultMappingRequest`/`UpdateFaseDefaultMappingRequest` menolak submit baru/edit kalau kombinasi `(lembaga_id, bentuk_pendidikan, tingkat)` sudah ada di baris lain (query eksplisit `FaseDefaultMapping::where(...)->where('id', '!=', $this->route('mapping')?->id)->exists()`), dengan pesan: *"Sudah ada mapping default untuk kombinasi jenjang dan tingkat ini. Edit baris yang ada, jangan buat duplikat."*

**Test concurrency/duplicate wajib**:
- Dua insert konkuren (lewat 2 request paralel disimulasikan via 2 pemanggilan Action berurutan tanpa refresh state di antaranya) dengan kombinasi identik → yang kedua gagal karena `fase_default_mapping_scope_unique`, bukan lolos jadi duplikat data (DB constraint adalah sumber kebenaran akhir; validasi Request adalah UX, bukan pengganti).
- `lembaga_id = NULL` dua kali dengan `bentuk_pendidikan` + `tingkat` sama → ditolak (constraint berlaku persis sama untuk platform-level seperti untuk lembaga-level, karena `lembaga_key` menyeragamkan NULL jadi `0`).
- `tingkat = NULL` dua kali (dua catch-all) untuk `bentuk_pendidikan` yang sama & `lembaga_id` yang sama → ditolak dengan alasan yang sama.
- Kombinasi berbeda (`bentuk_pendidikan` beda, atau `lembaga_id` beda, atau salah satu exact vs NULL yang secara logis tidak bentrok) → **lolos**, membuktikan constraint tidak overreach.

## §3. Model & Enum

`app/Domains/Akademik/Models/Fase.php`:
```php
<?php

namespace App\Domains\Akademik\Models;

use Illuminate\Database\Eloquent\Model;

class Fase extends Model
{
    protected $table = 'fase';

    protected $fillable = ['kode', 'nama', 'urutan'];
}
```
Tidak pakai trait `BelongsToTenant`/tenant scope — sama seperti `ElemenCp` (Sprint 1), karena `fase` adalah data referensi global.

`app/Domains/Akademik/Models/FaseDefaultMapping.php`:
```php
<?php

namespace App\Domains\Akademik\Models;

use Illuminate\Database\Eloquent\Model;

class FaseDefaultMapping extends Model
{
    protected $table = 'fase_default_mapping';

    protected $fillable = ['lembaga_id', 'bentuk_pendidikan', 'tingkat', 'fase_id'];

    public function fase()
    {
        return $this->belongsTo(Fase::class);
    }

    public function lembaga()
    {
        return $this->belongsTo(\App\Models\Lembaga::class);
    }
}
```
Model ini SENGAJA tidak pakai `BelongsToTenant` meski punya `lembaga_id` nullable — baris `lembaga_id = NULL` (platform-wide) harus tetap terlihat lintas tenant, yang bertentangan dengan asumsi dasar `TenantScope`. Filtering by lembaga dilakukan eksplisit di `FaseDefaultResolver`, bukan lewat global scope.

**Konsekuensi: karena tidak ada `TenantScope`, authorization manajemen mapping WAJIB eksplisit** (tidak bisa mengandalkan proteksi otomatis tenant scoping seperti model tenant-scoped lain). Mengikuti pola routing existing yang sudah memisahkan platform-level (`routes/admin/*`, mis. `Admin\LembagaController` di `routes/admin/lembaga.php`) dari institution-level (`routes/lembaga/*`):

- **`Admin\FaseDefaultMappingController`** (`routes/admin/*`, middleware/guard platform sama seperti `Admin\LembagaController`) — HANYA bisa create/update/delete baris `lembaga_id = NULL` (platform-wide). Request tidak pernah menerima `lembaga_id` dari input sama sekali di controller ini — selalu dipaksa `null` di server, terlepas apa pun yang dikirim client.
- **`Lembaga\Akademik\FaseDefaultMappingController`** (`routes/lembaga/*`, middleware/guard institution existing — sama seperti controller Kelas/Mata Pelajaran di namespace ini) — HANYA bisa create/update/delete baris milik lembaga yang sedang login (`lembaga_id = Auth::user()->lembaga_id`, dipaksa dari session, bukan dari input request). Tidak ada endpoint yang menerima `lembaga_id` sembarang dari body/query untuk memilih lembaga siapa yang dimanipulasi — ini pola yang sama dengan controller institution-scoped lain di codebase (mis. `Lembaga\Akademik\KelasController`), bukan mekanisme baru.

Dengan begitu, isolasi tenant untuk mapping tidak bergantung pada logika baru yang berisiko — cukup mengikuti pola routing/guard yang sudah terbukti benar untuk resource institution-scoped lain, hanya field `lembaga_id`-nya yang dipaksa dari session/context, bukan input.

`app/Models/Kelas.php`: tambah `'fase_id'` ke `$fillable`, tambah relasi:
```php
public function fase()
{
    return $this->belongsTo(\App\Domains\Akademik\Models\Fase::class);
}
```

## §4. `FaseDefaultResolver`

`app/Domains/Akademik/Services/FaseDefaultResolver.php`:
```php
<?php

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;

class FaseDefaultResolver
{
    public function resolve(string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): ?Fase
    {
        $match = FaseDefaultMapping::where('bentuk_pendidikan', $bentukPendidikan)
            ->where(function ($q) use ($lembagaId) {
                $q->where('lembaga_id', $lembagaId)->orWhereNull('lembaga_id');
            })
            ->orderByRaw('lembaga_id IS NULL, tingkat IS NULL')
            ->when($tingkat !== null, function ($q) use ($tingkat) {
                $q->orderByRaw('tingkat = ? DESC', [$tingkat]);
            })
            ->first();

        return $match?->fase;
    }
}
```
**Penjelasan precedence sbg urutan `ORDER BY` eksplisit** (bukan filter kandidat di memori — perbaikan dari draft sebelumnya, supaya semantik prioritas hidup di query, bukan tersembunyi di urutan closure yang mudah tergeser saat resolver berkembang):
- `lembaga_id IS NULL` sbg kunci sort pertama: `0` (false, lembaga-spesifik) diurutkan SEBELUM `1` (true, platform) — baris lembaga-spesifik selalu menang atas platform, apa pun status `tingkat`-nya.
- `tingkat IS NULL` sbg kunci sort kedua: dalam grup lembaga yang sama, baris ber-`tingkat` (bukan NULL) diurutkan sebelum baris catch-all — exact match menang atas catch-all dalam scope yang sama.
- `tingkat = ? DESC` (dengan `?` = `$tingkat` yang dicari) sbg penegasan tambahan: baris yang `tingkat`-nya benar-benar sama persis dengan yang dicari naik ke atas, memastikan baris exact-match tidak tertukar urutan dengan baris ber-`tingkat` lain yang kebetulan bukan NULL tapi juga bukan match (query WHERE sudah membatasi ke `bentuk_pendidikan` yang sama, tapi tidak ke `tingkat` yang sama — supaya baris catch-all ikut jadi kandidat).
- Hasil akhir: urutan baris yang di-`first()`-kan persis mengikuti 4 level precedence di keputusan desain poin 3, dinyatakan langsung sbg `ORDER BY`, bukan logika precedence yang tersembunyi di closure PHP yang bisa diam-diam salah urutan kalau resolver di-refactor nanti.

**Catatan tegas untuk implementer**: fungsi ini TIDAK BOLEH tumbuh jadi `match($bentukPendidikan)`/`if ($tingkat === 'sesuatu')` di masa depan. Kalau ada kebutuhan aturan baru, tambah baris di `fase_default_mapping`, jangan tambah cabang logika di sini. Ini prinsip desain inti Sprint 3, bukan preferensi gaya. Implementer WAJIB verifikasi sintaks `orderByRaw` dengan parameter binding di atas benar-benar berjalan sesuai Query Builder Laravel 12 (test §8 akan membuktikan urutannya, tapi sintaks persis wajib dicek saat implementasi — bukan diasumsikan benar dari spec).

## §5. Seed Data Awal

`database/seeders/FaseSeeder.php` (reference data, dijalankan sekali, idempotent via `updateOrCreate`):
```php
<?php

namespace Database\Seeders;

use App\Domains\Akademik\Models\Fase;
use Illuminate\Database\Seeder;

class FaseSeeder extends Seeder
{
    public function run(): void
    {
        $fases = [
            ['kode' => 'foundation', 'nama' => 'Fase Fondasi', 'urutan' => 0],
            ['kode' => 'a', 'nama' => 'Fase A', 'urutan' => 1],
            ['kode' => 'b', 'nama' => 'Fase B', 'urutan' => 2],
            ['kode' => 'c', 'nama' => 'Fase C', 'urutan' => 3],
            ['kode' => 'd', 'nama' => 'Fase D', 'urutan' => 4],
            ['kode' => 'e', 'nama' => 'Fase E', 'urutan' => 5],
            ['kode' => 'f', 'nama' => 'Fase F', 'urutan' => 6],
        ];

        foreach ($fases as $fase) {
            Fase::updateOrCreate(['kode' => $fase['kode']], $fase);
        }
    }
}
```

`database/seeders/FaseDefaultMappingSeeder.php` (data konfigurasi platform, `lembaga_id = NULL`, idempotent):
```php
<?php

namespace Database\Seeders;

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use Illuminate\Database\Seeder;

class FaseDefaultMappingSeeder extends Seeder
{
    public function run(): void
    {
        $faseByKode = Fase::pluck('id', 'kode');

        $mapping = [
            // PAUD/TK (semua bentuk non-formal) → catch-all Fase Fondasi
            ['bentuk_pendidikan' => 'KB', 'tingkat' => null, 'kode' => 'foundation'],
            ['bentuk_pendidikan' => 'TPA', 'tingkat' => null, 'kode' => 'foundation'],
            ['bentuk_pendidikan' => 'SPS', 'tingkat' => null, 'kode' => 'foundation'],
            ['bentuk_pendidikan' => 'TK', 'tingkat' => null, 'kode' => 'foundation'],
            // SD
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '1', 'kode' => 'a'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '2', 'kode' => 'a'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '3', 'kode' => 'b'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '4', 'kode' => 'b'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '5', 'kode' => 'c'],
            ['bentuk_pendidikan' => 'SD', 'tingkat' => '6', 'kode' => 'c'],
            // SMP → catch-all Fase D
            ['bentuk_pendidikan' => 'SMP', 'tingkat' => null, 'kode' => 'd'],
            // SMA
            ['bentuk_pendidikan' => 'SMA', 'tingkat' => '10', 'kode' => 'e'],
            ['bentuk_pendidikan' => 'SMA', 'tingkat' => '11', 'kode' => 'f'],
            ['bentuk_pendidikan' => 'SMA', 'tingkat' => '12', 'kode' => 'f'],
            // SMK — sama pola tingkat dgn SMA
            ['bentuk_pendidikan' => 'SMK', 'tingkat' => '10', 'kode' => 'e'],
            ['bentuk_pendidikan' => 'SMK', 'tingkat' => '11', 'kode' => 'f'],
            ['bentuk_pendidikan' => 'SMK', 'tingkat' => '12', 'kode' => 'f'],
            // SLB: tidak diberi mapping sama sekali — kurikulum SLB punya penyesuaian
            // tersendiri di luar cakupan Sprint 3; resolver akan return null,
            // admin isi fase_id manual kalau memang relevan.
        ];
        // Catatan domain: baris di atas adalah REKOMENDASI PLATFORM SAAT INI
        // ("platform saat ini merekomendasikan Fase Fondasi untuk KB"), bukan
        // kebenaran definisional yang tertanam permanen ("KB secara definisi
        // selalu Fondasi"). Wording ini sengaja dijaga konsisten dengan prinsip
        // keputusan desain poin 2 — kebijakan bisa berubah tanpa deployment,
        // termasuk baris seed ini sendiri (lewat UI admin mapping, bukan re-seed).

        foreach ($mapping as $m) {
            FaseDefaultMapping::updateOrCreate(
                ['lembaga_id' => null, 'bentuk_pendidikan' => $m['bentuk_pendidikan'], 'tingkat' => $m['tingkat']],
                ['fase_id' => $faseByKode[$m['kode']]]
            );
        }
    }
}
```

**Acceptance criterion eksplisit**: seeder ini adalah **initial configuration**, bukan aturan yang dipaksa ulang setiap `db:seed` dijalankan lagi. `updateOrCreate` pada kombinasi scope (bukan `insert`/`create` polos) memang akan meng-update `fase_id` baris platform itu kalau seeder dijalankan ulang dengan data berbeda — TAPI **tidak pernah menyentuh `kelas.fase_id`** yang sudah tersimpan (§sesuai keputusan desain poin 4: resolver hanya dipanggil saat create Kelas, seeder tidak pernah menulis ke tabel `kelas`). Test regresi wajib membuktikan ini: jalankan seeder, buat Kelas (fase_id ter-assign dari mapping saat itu), jalankan ulang seeder dengan mapping yang sudah diubah manual, assert `Kelas::find($id)->fase_id` **tidak berubah**.

Kedua seeder didaftarkan di `DatabaseSeeder::run()` (setelah seeder yang sudah ada seperti `ElemenCpSeeder` dari Sprint 1, urutan: `FaseSeeder` sebelum `FaseDefaultMappingSeeder` karena yang kedua butuh ID dari yang pertama).

## §6. UI — Form Create/Edit Kelas

`resources/views/portals/lembaga/akademik/kelas/_form.blade.php` (atau file form Kelas yang berlaku saat ini — implementer verifikasi path aktual): tambah dropdown Fase, berdampingan dengan input `tingkat` existing:

```blade
<div>
    <x-input-label value="Fase Kurikulum" />
    <select name="fase_id" class="mt-1.5 block w-full rounded-lg border-gray-200 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">— Tidak ditentukan —</option>
        @foreach ($faseList as $fase)
            <option value="{{ $fase->id }}" @selected(old('fase_id', $faseIdSuggested ?? $kelas?->fase_id) == $fase->id)>{{ $fase->nama }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500">Saran otomatis berdasar jenjang & tingkat — boleh diganti manual.</p>
</div>
```

Controller (`KelasController@create`/`@store`, path aktual diverifikasi implementer saat eksekusi): saat form create dibuka (bukan submit), panggil resolver untuk pre-fill:
```php
$faseIdSuggested = $request->filled('tingkat')
    ? optional(app(FaseDefaultResolver::class)->resolve($lembaga->bentuk_pendidikan, $request->query('tingkat'), $lembaga->id))->id
    : null;
```
Karena `tingkat` biasanya diisi lewat form yang sama (bukan query string terpisah), implementasi realistis: pre-fill dilakukan lewat **Alpine.js di sisi klien** (mirip pola `assessmentType` auto-set di Sprint 2 §4) — saat user mengetik/memilih `tingkat`, JS memanggil endpoint kecil `GET /lembaga/akademik/kelas/fase-suggestion?tingkat={tingkat}`. Endpoint ini murni memanggil `FaseDefaultResolver::resolve()` dengan `bentuk_pendidikan` dari lembaga yang sedang login (guard middleware existing, `Auth::user()->lembaga_id`) dan `lembaga_id`-nya sendiri — tidak menyimpan apa pun, hanya query read-only.

**Contract response tidak hanya `fase_id` mentah** — supaya UI tidak perlu tahu apa pun soal skema mapping, cukup render field yang diterima:
```json
{ "suggestion": { "id": 2, "kode": "a", "nama": "Fase A" } }
```
atau, kalau tidak ada mapping yang cocok:
```json
{ "suggestion": null }
```
Alpine cukup baca `response.suggestion?.id` untuk set value dropdown dan (opsional) `response.suggestion?.nama` untuk teks bantuan — tidak perlu logika tambahan di JS untuk menafsirkan `null` vs objek.

`KelasController@store`/`@update`: `fase_id` diterima apa adanya dari form (nullable FK, divalidasi `['nullable', 'exists:fase,id']`) — **TIDAK** dihitung ulang otomatis di server saat submit. Suggestion adalah UX di sisi klien; nilai final yang disimpan adalah apa pun yang ada di dropdown saat submit (baik hasil suggestion yang diterima maupun override manual). Ini konsisten dengan keputusan desain poin 4 & 5.

## §7. Non-Goals Sprint 3 (eksplisit)

- **Curriculum Designer** — tidak ada UI untuk mendesain struktur kurikulum baru dari nol.
- **Curriculum Versioning** — tidak ada konsep "mapping berlaku mulai tanggal X" atau riwayat perubahan mapping; setiap `fase_default_mapping` hanya satu state aktif per scope, edit langsung menimpa (audit trail kalau dibutuhkan adalah pekerjaan terpisah).
- **Mapping CP/TP (Capaian Pembelajaran/Tujuan Pembelajaran) ke Fase** — di luar cakupan, hanya `fase_id` sbg assignment, tidak ada struktur konten kurikulum di bawahnya.
- **Dimensi "pilih kurikulum" (K13/KMA-450/dsb)** — hanya satu ruleset (Merdeka) yang di-seed; lihat keputusan desain poin 6.
- **Auto-assign fase_id massal untuk Kelas existing** — Kelas yang sudah ada sebelum Sprint 3 tetap `fase_id = NULL` sampai admin edit manual; tidak ada migration data/backfill otomatis (kalau di-backfill otomatis, itu justru melanggar prinsip "resolver hanya dipanggil di titik create" karena akan memanggil resolver massal di luar konteks create Kelas baru).
- **P5 (Projek Penguatan Profil Pelajar Pancasila)** — sudah dicatat di `docs/superpowers/specs/2026-07-24-presensi-asesmen-design.md` §10 sbg struktur terpisah yang belum dibangun, tetap tidak bagian Sprint 3.
- **Konsolidasi `ElemenCp`** — tetap technical debt `TD-AKADEMIK-001`, tidak disentuh.

## §8. Test Matrix (Acceptance Criteria WAJIB)

**`FaseDefaultResolver` (unit test):**

| Skenario | Ekspektasi |
|---|---|
| `bentuk_pendidikan=SD, tingkat=1`, tidak ada override lembaga | Fase A (dari mapping platform) |
| `bentuk_pendidikan=SD, tingkat=1`, lembaga X override tingkat `1` → Fase B | Fase B (lembaga menang atas platform) |
| `bentuk_pendidikan=SD, tingkat=1`, lembaga X hanya punya catch-all (tingkat NULL) → Fase Foundation | Fase Foundation (catch-all lembaga menang atas exact-match platform — prioritas #2 > #3) |
| `bentuk_pendidikan=SMP, tingkat=7`, hanya ada catch-all platform (tingkat NULL → Fase D) | Fase D |
| `bentuk_pendidikan=SLB, tingkat=6`, tidak ada mapping apa pun | `null` (tidak ada suggestion) |
| `bentuk_pendidikan=SD, tingkat=99` (tingkat tidak dikenal), ada catch-all platform SD? | Tidak ada catch-all SD di seed (§5) → `null` |

**Uniqueness/konflik (feature test, lihat §2):**
- Insert 2 baris platform identik (`bentuk_pendidikan`+`tingkat` sama, `lembaga_id=NULL` keduanya) → baris kedua gagal (`QueryException` dari constraint `fase_default_mapping_scope_unique`), DAN ditolak lebih awal di level Request dengan pesan jelas kalau lewat form admin.
- Insert 2 baris catch-all (`tingkat=NULL`) untuk `bentuk_pendidikan` yang sama & `lembaga_id` yang sama → gagal, sama seperti di atas.
- Insert baris platform + baris lembaga-spesifik dengan `bentuk_pendidikan`+`tingkat` sama tapi `lembaga_id` beda (satu NULL, satu terisi) → **berhasil keduanya** (scope beda, bukan duplikat).

**Authorization/tenant-isolation (feature test, kritis karena model TIDAK pakai `TenantScope` — lihat §3):**
- Admin lembaga A membuat mapping lewat `Lembaga\Akademik\FaseDefaultMappingController` → baris tersimpan dengan `lembaga_id = lembaga A`, terlepas apa pun yang (kalau dipaksakan) dikirim di payload sbg `lembaga_id` lain — assert server selalu memakai `Auth::user()->lembaga_id`, bukan input.
- Admin lembaga B mencoba mengedit/menghapus baris milik lembaga A (mis. tebak-tebak ID lewat URL `/lembaga/akademik/fase-mapping/{id}/edit`) → `403`/`404` (mengikuti pola proteksi resource institution-scoped lain di codebase), bukan berhasil.
- Admin lembaga A mencoba membuat/mengedit baris platform (`lembaga_id = NULL`) lewat rute institution-nya → ditolak (rute ini secara desain tidak pernah menerima `lembaga_id = NULL` sbg pilihan; kalaupun dipaksa lewat payload, server override ke `lembaga_id` sendiri, bukan `NULL`).
- User tanpa role admin platform mencoba akses `Admin\FaseDefaultMappingController` → ditolak middleware guard platform (sama seperti proteksi existing `Admin\LembagaController`).
- `FaseDefaultResolver::resolve()` (read-only, dipanggil dari endpoint suggestion §6) tetap bisa membaca baris platform DAN baris lembaga manapun yang relevan dengan `lembagaId` yang diberikan — konfirmasi bahwa larangan di atas murni pada operasi tulis (create/update/delete), bukan pada kemampuan resolver membaca lintas scope (yang memang perlu, itulah alasan model ini sengaja tidak pakai `TenantScope`).

**Immutability `kelas.fase_id` (feature test, kritis — §5 acceptance criterion):**
- Buat Kelas baru dengan mapping platform SD tingkat 1 → Fase A aktif, assert `fase_id` tersimpan = Fase A.
- Ubah baris `fase_default_mapping` platform SD tingkat 1 → Fase B (lewat Action/model langsung, simulasi admin platform mengubah kebijakan).
- Re-fetch Kelas yang dibuat di langkah pertama, assert `fase_id` **masih Fase A** (tidak ikut berubah).
- Buat Kelas BARU (setelah perubahan mapping) dengan `bentuk_pendidikan`+`tingkat` yang sama → assert suggestion baru mengarah ke Fase B (mapping baru berlaku untuk assignment baru, bukan retroaktif ke yang lama).

**Seed idempotency (feature test — §5):**
- Jalankan `FaseSeeder` + `FaseDefaultMappingSeeder` dua kali berturut-turut → assert jumlah baris `fase` dan `fase_default_mapping` tidak berlipat ganda (masih sesuai jumlah entri di seeder, bukan 2x).

**UI/endpoint suggestion (`GET .../fase-suggestion`):**
- Request dengan `tingkat` yang match mapping → response berisi `fase_id` yang benar.
- Request dengan `tingkat` yang tidak match apa pun → response `fase_id: null`, tidak error 500.
- Endpoint diverifikasi tenant-aware: dua lembaga berbeda dengan override berbeda untuk `tingkat` yang sama → masing-masing dapat suggestion sesuai override lembaganya sendiri (bukan tertukar).

**Create/Update Kelas dengan `fase_id`:**
- Submit form Kelas dengan `fase_id` valid → tersimpan apa adanya.
- Submit dengan `fase_id` kosong (`""`/tidak dikirim) → tersimpan `NULL`, tidak error.
- Submit dengan `fase_id` yang tidak ada di tabel `fase` → ditolak validasi `exists:fase,id`.
- Edit Kelas existing, ganti `fase_id` secara manual ke fase lain (override eksplisit admin) → tersimpan sesuai pilihan admin, bukan direset ke suggestion resolver.

## Self-Review

- Semua poin kesepakatan user masuk eksplisit: (1) `fase` global stabil tanpa `lembaga_id` §1a/§3, (2) mapping sbg data bukan kode §1b/§4 dengan penekanan tegas resolver tidak boleh tumbuh jadi if/match, (3) precedence 4 tingkat §2 (keputusan desain)/§4, (4) `kelas.fase_id` snapshot immutable §1c/§5/§8, (5) tidak ada dimensi kurikulum baru sekarang §keputusan desain poin 6/§7, (6) uniqueness/conflict protection lengkap dengan solusi teknis konkret (generated column) §2, ditest §8, (7) seed sbg initial configuration bukan business logic §5 dengan acceptance criterion eksplisit.
- **Revisi putaran review kedua (4 poin), semua diterapkan**: (a) `urutan` diperjelas sbg display/sort order murni, bukan semantic level pendidikan (§1a); (b) precedence resolver dipindah dari filter in-memory (closure) ke `ORDER BY` eksplisit di query, supaya semantik prioritas tidak tersembunyi (§4); (c) authorization/tenant-isolation untuk `FaseDefaultMapping` dijabarkan eksplisit mengikuti pola routing platform-vs-institution existing, plus 5 test kasus di §8, karena model ini sengaja tidak pakai `TenantScope` (§3/§8); (d) compatibility generated column diverifikasi konkret terhadap MySQL 8.0.30 yang benar-benar dipakai environment ini (§2), bukan dibiarkan sbg risiko tak terverifikasi. Juga ditambahkan: distinction eksplisit "no configuration history, but assignment snapshot exists" (keputusan desain poin 8), penegasan `fase_default_mapping` bukan "kurikulum" (keputusan desain poin 9), contract endpoint suggestion berisi objek fase lengkap bukan `fase_id` mentah (§6), dan wording seed sbg rekomendasi platform saat ini bukan kebenaran definisional (§5).
- Placeholder scan: path file Blade/Controller Kelas ditandai eksplisit "implementer verifikasi path aktual" (§6) — bukan placeholder isi kode, tapi ketidakpastian lokasi file yang jujur (belum pernah dibaca langsung dalam sesi ini). Plan WAJIB memuat task awal untuk implementer membaca struktur folder `resources/views/portals/lembaga/akademik/kelas/` dan `app/Http/Controllers/Lembaga/Akademik/KelasController.php` (atau path setara) sebelum menulis kode form/controller.
- Scope check: fokus tunggal pada fondasi Fase (fase, mapping, resolver, assignment Kelas) — tidak melebar ke CP/TP/P5/curriculum designer, sesuai §7.
- Konsistensi tipe: `FaseDefaultResolver::resolve(string $bentukPendidikan, ?string $tingkat, ?int $lembagaId): ?Fase` dipakai identik di §4 (definisi) dan §6 (pemanggilan dari controller/endpoint) dan §8 (test).
