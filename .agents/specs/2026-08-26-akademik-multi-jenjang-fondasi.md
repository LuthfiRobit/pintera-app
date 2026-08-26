# Spec: Fondasi Akademik Multi-Jenjang (PAUD–SLTA)

**Status:** Draft untuk review — belum masuk plan eksekusi.
**Branch:** `akademik-v2`
**Konteks bisnis:** Sistem masih development/demo, belum ada sekolah produksi memakai sistem, tidak ada beban migrasi data nyata. Data demo saat ini: 9 `komponen_penilaian`, 2 `asesmen`, 0 baris dengan `elemen_cp` terisi — volume kecil, aman untuk breaking change.

## Latar Belakang

Audit terhadap kebutuhan multi-jenjang (PAUD/TK/KB/TPA → SD/MI → SMP/MTs → SMA/MA/SMK/MAK) menemukan bahwa fondasi tenant-level sudah cukup baik (`Lembaga.bentuk_pendidikan`, `Lembaga.naungan`, `ModePembelajaran::fromBentukPendidikan()`), tapi **engine akademik intinya masih berasumsi kuat bahwa setiap penilaian punya `MataPelajaran`** — sebuah asumsi yang tidak berlaku untuk PAUD (memakai "Elemen Capaian Pembelajaran", bukan mata pelajaran).

Verifikasi kode menemukan:
- `komponen_penilaian.mata_pelajaran_id` dan `asesmen.mata_pelajaran_id` **NOT NULL** — sementara `jadwal_pelajaran`, `sesi_pembelajaran`, `rpp` sudah **nullable** (fondasi sudah lebih siap dari dugaan awal untuk 3 tabel ini).
- `komponen_penilaian.elemen_cp` (enum `ElemenCapaianPembelajaran`) sudah ada dan **coexist** dengan `mata_pelajaran_id` pada baris yang sama — PAUD saat ini harus membuat "mata pelajaran dummy" untuk menampung nilai elemen CP-nya. Tambal sulam, bukan desain bersih.
- `nilai_siswa.nilai_angka` sudah nullable, kolom `predikat`/`catatan` sudah ada — tapi **tidak pernah dipakai di UI mana pun** (form input nilai guru hardcode `<input type="number" min="0" max="100">`).
- `RaporCalculationService`/`RaporPdfDataBuilder`/`CapaianKompetensiGenerator` mengelompokkan nilai per-mapel pakai **bare `$mapel->id`** — berisiko collision begitu ada tipe subjek kedua dengan ruang id sendiri.

Keputusan strategis (disepakati bersama): **refactor fondasi sekarang** (development-time, breaking changes diperbolehkan), **tapi tidak membangun modul bisnis (P5/PKL/UKK/Tracer Study/dll) sebelum ada kebutuhan pelanggan nyata**. `bentuk_pendidikan` tetap jadi *input konfigurasi*, bukan sumber `if/else` yang menyebar ke seluruh kode.

## Cakupan

Proyek ini dipecah jadi 5 sub-project (Sprint) yang saling bergantung secara berurutan:

| Sprint | Nama | Status spec |
|---|---|---|
| 1 | Domain Cleanup — Subjek Penilaian Polymorphic | **Detail penuh** (dokumen ini) |
| 2 | Assessment Type (Numeric/Narrative/Predicate) | Rancangan ringkas |
| 3 | Curriculum Phase (Fase Kurikulum Merdeka) | Rancangan ringkas |
| 4 | Academic Profile Service | Rancangan ringkas |
| 5 | Report Engine Abstraction | Rancangan ringkas |

Di luar cakupan proyek ini (sengaja ditunda sampai ada kebutuhan pelanggan nyata): P5 Engine, modul Vokasi/PKL/UKK, Tracer Study, e-Ijazah/SKL/STSB, Poin Pelanggaran, feature-gating penuh per paket langganan.

---

## Sprint 1 — Domain Cleanup: Subjek Penilaian Polymorphic

### Tujuan
`KomponenPenilaian` dan `Asesmen` tidak lagi mengasumsikan subjek penilaiannya pasti `MataPelajaran`. Subjek bisa `MataPelajaran` (SD/SMP/SMA/SMK, dst) **atau** `ElemenCp` (PAUD/TK/KB/TPA). `JadwalPelajaran`, `Rpp`, `SesiPembelajaran` **tidak disentuh** — mereka sudah nullable dan cukup begitu untuk mode Tematik.

### 1. Skema Database

**Tabel baru `elemen_cp`** — referensi global, TIDAK tenant-scoped (3 elemen adalah standar nasional Kurikulum Merdeka, bukan kustomisasi per-lembaga):

```php
Schema::create('elemen_cp', function (Blueprint $table) {
    $table->id();
    $table->string('kode', 30)->unique();
    $table->string('nama');
    $table->unsignedTinyInteger('no_urut');
    $table->timestamps();
});
```

Seed 3 baris tetap (urutan & label persis dari `ElemenCapaianPembelajaran` enum existing):
```php
['kode' => 'nilai_agama_moral', 'nama' => 'Nilai Agama dan Budi Pekerti', 'no_urut' => 1],
['kode' => 'jati_diri', 'nama' => 'Jati Diri', 'no_urut' => 2],
['kode' => 'literasi_steam', 'nama' => 'Literasi, STEAM, Seni, dan Budaya', 'no_urut' => 3],
```

**Alter `komponen_penilaian`:**
```php
Schema::table('komponen_penilaian', function (Blueprint $table) {
    $table->string('subjek_type')->nullable()->after('lembaga_id');
    $table->unsignedBigInteger('subjek_id')->nullable()->after('subjek_type');
    $table->index(['subjek_type', 'subjek_id'], 'idx_komp_subjek');
});
```

**Alter `asesmen`:** kolom sama persis (`subjek_type`, `subjek_id`, index sama) ditambah setelah `lembaga_id`.

**Migration backfill** (satu migration terpisah, dijalankan SETELAH kolom baru ada, SEBELUM kolom lama di-drop):

Aturan precedence eksplisit (WAJIB, bukan implisit urutan kolom):
```text
UNTUK setiap baris komponen_penilaian/asesmen:
  JIKA elemen_cp (kolom lama) TERISI:
      subjek_type = 'elemen_cp'
      subjek_id   = ElemenCp::where('kode', $row->elemen_cp)->value('id')
  ELSE JIKA mata_pelajaran_id TERISI:
      subjek_type = 'mata_pelajaran'
      subjek_id   = $row->mata_pelajaran_id
  ELSE:
      FAIL migration (throw exception, sebutkan id baris yang bermasalah)
```//
Alasan precedence: `elemen_cp` adalah informasi domain yang LEBIH bermakna untuk PAUD daripada mapel dummy yang sekadar syarat FK. Migration harus mem-verifikasi SEMUA baris berhasil dipetakan sebelum melanjutkan ke migration berikutnya (drop kolom lama) — kalau ada baris yang gagal dipetakan, migration harus gagal dengan pesan error yang menyebutkan id baris spesifik, bukan silently skip.

**Migration cleanup** (migration terpisah lagi, setelah backfill terverifikasi via test):
```php
Schema::table('komponen_penilaian', function (Blueprint $table) {
    $table->dropColumn(['mata_pelajaran_id', 'elemen_cp']);
    $table->string('subjek_type')->nullable(false)->change();
    $table->unsignedBigInteger('subjek_id')->nullable(false)->change();
});
// sama untuk asesmen (drop mata_pelajaran_id saja, tabel ini tidak punya kolom elemen_cp)
```

3 migration terpisah (tambah kolom → backfill data → drop kolom lama + NOT NULL) supaya setiap tahap independently testable dan gagalnya jelas terlihat di tahap mana.

### 2. Morph Map (WAJIB, bukan opsional)

Didaftarkan terpusat di `AppServiceProvider::boot()` (atau provider domain Akademik kalau ada):
```php
Relation::enforceMorphMap([
    'mata_pelajaran' => \App\Domains\Akademik\Models\MataPelajaran::class,
    'elemen_cp' => \App\Domains\Akademik\Models\ElemenCp::class,
]);
```
Pakai `enforceMorphMap()` (bukan `morphMap()`) — akan melempar exception kalau ada kode yang mencoba assign `subjek_type` di luar 2 nilai ini, mencegah morph map "bocor" jadi FQCN mentah secara tidak sengaja. **Sprint 1 HANYA mendaftarkan 2 tipe ini** — `projek`/`kompetensi_kejuruan` TIDAK didaftarkan sebagai placeholder, baru ditambah saat fiturnya benar-benar dibangun.

### 3. Model Baru: `ElemenCp`

`app/Domains/Akademik/Models/ElemenCp.php`:
```php
class ElemenCp extends Model implements SubjekPenilaian
{
    protected $table = 'elemen_cp';
    protected $fillable = ['kode', 'nama', 'no_urut'];
    // TIDAK pakai BelongsToTenant -- data global, bukan per-lembaga
}
```

### 4. Interface `SubjekPenilaian` (kontrak minimal)

`app/Domains/Akademik/Contracts/SubjekPenilaian.php`:
```php
interface SubjekPenilaian
{
    // Kontrak minimal: cuma yang benar-benar dipakai CapaianKompetensiGenerator
    // dan blade views (nama tampilan). JANGAN tambah method lain tanpa
    // kebutuhan konkret dari caller nyata -- ini bukan tempat menaruh
    // "siapa tahu nanti dibutuhkan".
}
```
`MataPelajaran` dan `ElemenCp` sama-sama `implements SubjekPenilaian`. Karena `nama` adalah kolom Eloquent biasa di kedua model (bukan method), interface ini murni **marker interface** (menandai "boleh jadi subjek morph", dipakai untuk type-hint `SubjekPenilaian $subjek` di `CapaianKompetensiGenerator::generateNarasi()`) — tidak mendeklarasikan method, karena kontrak `->nama` sebagai properti Eloquent tidak bisa dideklarasikan di PHP interface tanpa memaksa kedua model punya accessor eksplisit yang saat ini tidak diperlukan. Kalau ke depannya caller butuh kontrak method nyata (bukan cuma properti), baru ditambahkan saat itu.

### 5. Helper Terpusat: `SubjekPenilaianKey`

Wajib dipakai oleh SEMUA service yang butuh key unik lintas tipe subjek — tidak boleh ada service yang bikin string key sendiri.

`app/Domains/Akademik/Support/SubjekPenilaianKey.php`:
```php
final class SubjekPenilaianKey
{
    public static function dari(Model $subjek): string
    {
        return $subjek->getMorphClass() . ':' . $subjek->getKey();
    }
}
```
`getMorphClass()` membaca dari morph map yang didaftarkan di langkah 2 (`'mata_pelajaran'`/`'elemen_cp'`) — bukan FQCN — jadi key selalu konsisten (`"mata_pelajaran:3"`, `"elemen_cp:1"`) tanpa risiko divergensi antar service, karena satu-satunya sumber kebenaran adalah morph map Laravel sendiri, bukan implementasi manual per-file.

### 6. Perubahan Model

**`KomponenPenilaian`:**
- Hapus `mataPelajaran(): BelongsTo`, tambah `subjek(): MorphTo { return $this->morphTo(); }`.
- `$fillable`: ganti `'mata_pelajaran_id'` → `'subjek_type', 'subjek_id'`, hapus `'elemen_cp'`.
- Hapus cast `'elemen_cp' => ElemenCapaianPembelajaran::class`.
- `booted()` creating-hook: ganti `MataPelajaran::withoutGlobalScopes()->findOrFail($komponenPenilaian->mata_pelajaran_id)->lembaga_id` menjadi resolusi lembaga_id yang tergantung tipe subjek:
  ```php
  static::creating(function (self $komponen) {
      if (empty($komponen->lembaga_id)) {
          $komponen->lembaga_id = match ($komponen->subjek_type) {
              'mata_pelajaran' => MataPelajaran::withoutGlobalScopes()->findOrFail($komponen->subjek_id)->lembaga_id,
              'elemen_cp' => null, // ElemenCp global, lembaga_id HARUS di-set eksplisit dari context lain (semester/kelas) sebelum create -- lihat Action
          };
      }
  });
  ```
  **Catatan implementasi**: untuk `subjek_type = elemen_cp`, `lembaga_id` tidak bisa diturunkan dari subjek (karena global) — harus di-pass eksplisit dari `Semester`/`Kelas` yang sedang dipakai admin saat membuat komponen. `CreateKomponenPenilaianAction` perlu diperbarui untuk selalu inject `lembaga_id` dari `Semester::find($data->semesterId)->lembaga_id` sebagai fallback, bukan mengandalkan hook derivasi dari subjek.

**`Asesmen`:** perubahan identik (`mataPelajaran()` → `subjek()`, fillable, tidak ada elemen_cp cast di sini karena kolom ini memang cuma ada di `komponen_penilaian`).

**`ElemenCapaianPembelajaran` enum**: setelah backfill selesai dan kolom `elemen_cp` di-drop, enum ini jadi tidak terpakai sebagai cast model — TAPI tetap dipertahankan sebagai referensi `kode` valid (dipakai saat seeding `elemen_cp` table, dan opsional untuk validasi `subjek_id` yang mengarah ke `ElemenCp` yang kode-nya cocok). Tidak dihapus.

### 7. Validasi Tenant yang Berbeda per Tipe Subjek

Di `KomponenPenilaianController` dan `CreateKomponenPenilaianAction`/`UpdateKomponenPenilaianAction`, guard existing:
```php
abort_if($mataPelajaran === null || $semester === null, 404);
abort_if($mataPelajaran->lembaga_id !== $semester->lembaga_id, 404);
```
menjadi:
```php
$subjek = match ($data['subjek_type']) {
    'mata_pelajaran' => MataPelajaran::find($data['subjek_id']),
    'elemen_cp' => ElemenCp::find($data['subjek_id']),
};
abort_if($subjek === null || $semester === null, 404);
if ($data['subjek_type'] === 'mata_pelajaran') {
    abort_if($subjek->lembaga_id !== $semester->lembaga_id, 404); // ElemenCp global, skip cek ini
}
```

### 8. Form Request

`StoreKomponenPenilaianRequest`/`UpdateKomponenPenilaianRequest`/`StoreKomponenPenilaianSendiriRequest`/`UpdateKomponenPenilaianSendiriRequest`/`StoreAsesmenRequest`:

```php
'subjek_type' => ['required', Rule::in(['mata_pelajaran', 'elemen_cp'])],
'subjek_id' => ['required', 'integer', function ($attribute, $value, $fail) {
    $type = $this->input('subjek_type');
    $exists = match ($type) {
        'mata_pelajaran' => MataPelajaran::where('id', $value)->exists(),
        'elemen_cp' => ElemenCp::where('id', $value)->exists(),
        default => false,
    };
    if (! $exists) {
        $fail('Subjek penilaian yang dipilih tidak valid.');
    }
}],
```
Field `mata_pelajaran_id` dan `elemen_cp` dihapus dari rules. **Validasi backend ini adalah satu-satunya business rule yang mengikat** — pilihan radio button di UI (langkah 10) murni presentasi, bukan sumber kebenaran keamanan.

### 9. DTO

`KomponenPenilaianData`/`UpdateKomponenPenilaianData`/`AsesmenData`: ganti `public int $mataPelajaranId` → `public string $subjekType, public int $subjekId`. Update `::fromArray()`/factory method masing-masing.

### 10. UI — Toggle Jenis Subjek (2 portal: Lembaga/Admin DAN Guru)

Kedua form (create + edit, di kedua portal) mendapat radio toggle:
```blade
<div>
    <x-input-label value="Jenis Subjek Penilaian" />
    <div class="flex gap-4 mt-1.5">
        <label><input type="radio" name="subjek_type" value="mata_pelajaran" x-model="subjekType"> Mata Pelajaran</label>
        <label><input type="radio" name="subjek_type" value="elemen_cp" x-model="subjekType"> Elemen CP (PAUD)</label>
    </div>
</div>
<div x-show="subjekType === 'mata_pelajaran'">
    {{-- dropdown mata pelajaran, existing --}}
</div>
<div x-show="subjekType === 'elemen_cp'">
    <select name="subjek_id">
        @foreach ($elemenCpList as $elemen)
            <option value="{{ $elemen->id }}">{{ $elemen->nama }}</option>
        @endforeach
    </select>
</div>
```
Portal **Guru** saat ini TIDAK punya dropdown elemen CP sama sekali — ini penambahan UI baru, bukan migrasi dari yang sudah ada. Controller Guru (`Guru\KomponenPenilaianController`) perlu menambah `'elemenCpList' => ElemenCp::orderBy('no_urut')->get()` ke data view create/edit.

Validasi keamanan tetap di backend (langkah 8) — radio button hanya menentukan field mana yang dikirim, tidak pernah dipercaya sebagai otorisasi.

### 11. Blade Views — Ganti `->mataPelajaran` → `->subjek`

Semua situs berikut (dari inventarisasi teknis) ganti `->mataPelajaran->nama` menjadi `->subjek->nama`, dan eager-load `'mataPelajaran'` menjadi `'subjek'`:

- `resources/views/admin/dashboard/siswa.blade.php:117`
- `resources/views/admin/dashboard/orang-tua.blade.php:102`
- `resources/views/portals/guru/akademik/komponen-penilaian/_daftar.blade.php:22,66`
- `resources/views/portals/guru/akademik/komponen-penilaian/edit.blade.php:45`
- `resources/views/portals/guru/akademik/asesmen/show.blade.php:38`
- `resources/views/portals/guru/akademik/asesmen/index.blade.php:95,123`
- `resources/views/portals/lembaga/akademik/komponen-penilaian/_daftar.blade.php:60,104`
- `resources/views/portals/lembaga/akademik/komponen-penilaian/edit.blade.php:46`
- `app/Http/Controllers/Admin/DashboardController.php:136,207-208` (eager-load nested)
- `app/Http/Controllers/Admin/KomponenPenilaianController.php` (semua `->with(['mataPelajaran', ...])`)
- `app/Http/Controllers/Guru/KomponenPenilaianController.php`, `Guru/AsesmenController.php` (eager-load)
- `app/Services/DashboardStatsService.php:137` (`whereHas('mataPelajaran', ...)` → `whereHas('subjek', ...)` — TAPI query di dalamnya `where('lembaga_id', ...)` cuma valid untuk `mata_pelajaran`; perlu jadi `whereHasMorph('subjek', [MataPelajaran::class], fn ($q) => $q->where('lembaga_id', $kelas->lembaga_id))` supaya baris ber-`ElemenCp` tidak ikut ter-exclude secara keliru)

Query `KomponenPenilaian::whereHas('mataPelajaran')` (baris 45 `Admin/KomponenPenilaianController.php`, dipakai untuk filter "komponen yang subjeknya masih valid") harus jadi `whereNotNull('subjek_id')` (morph relation tidak bisa di-`whereHas` tanpa `whereHasMorph`, dan cukup cek non-null karena constraint NOT NULL sudah menjamin integritas).

`KomponenPenilaianController::whereIn('mata_pelajaran_id', $mapelIds)` (Guru controller, filter "komponen utk mapel yang diajar guru ini") jadi `where('subjek_type', 'mata_pelajaran')->whereIn('subjek_id', $mapelIds)` — guru TIDAK melihat komponen ElemenCp (guru mapel bukan guru PAUD/kelas, di luar cakupan use-case saat ini; kalau nanti guru PAUD butuh akses, itu perluasan terpisah).

### 12. `RaporCalculationService`, `RaporPdfDataBuilder`, `CapaianKompetensiGenerator`

`RaporCalculationService::hitungRekapKelas()`:
```php
$asesmenList = Asesmen::where('kelas_id', $kelas->id)
    ->where('semester_id', $semester->id)
    ->with('subjek')
    ->get();

$subjekList = $asesmenList->pluck('subjek')->unique(fn ($s) => SubjekPenilaianKey::dari($s))->sortBy('nama');

foreach ($siswaList as $siswa) {
    foreach ($subjekList as $subjek) {
        $key = SubjekPenilaianKey::dari($subjek);
        $subjekAsesmenIds = $asesmenList->filter(fn ($a) => SubjekPenilaianKey::dari($a->subjek) === $key)->pluck('id');
        // ... rest identik, tapi $rekapNilai[$siswa->id][$key] bukan [$mapel->id]
    }
}
```
`RaporPdfDataBuilder` konsumsi `mapelList`/`rekapNilai` ganti seluruh key `$mapel->id` jadi `SubjekPenilaianKey::dari($subjek)`, termasuk di loop tahunan (baris 98-109 pada laporan teknis).

`CapaianKompetensiGenerator::generateNarasi()`: signature `MataPelajaran $mapel` → `SubjekPenilaian $subjek`. Query internal `where('mata_pelajaran_id', $mapel->id)` → `where('subjek_type', $subjek->getMorphClass())->where('subjek_id', $subjek->getKey())`.

### 13. Factories

```php
// KomponenPenilaianFactory
public function definition(): array
{
    return [
        'subjek_type' => 'mata_pelajaran',
        'subjek_id' => MataPelajaran::factory(),
        // ...
    ];
}

public function elemenCp(): static
{
    return $this->state(fn () => [
        'subjek_type' => 'elemen_cp',
        'subjek_id' => ElemenCp::factory(), // atau ElemenCp::inRandomOrder()->first()?->id jika seed sudah jalan
    ]);
}
```
`AsesmenFactory` identik. `ElemenCpFactory` baru dibuat (sederhana, `kode`/`nama`/`no_urut` faker atau siklus 3 nilai tetap).

**Catatan penting**: `'subjek_id' => MataPelajaran::factory()` TIDAK otomatis bekerja untuk kolom polymorphic biasa (`subjek_id` butuh id integer, bukan factory closure yang di-resolve Eloquent relation biasa) — perlu ditulis eksplisit sbg `MataPelajaran::factory()->create()->id` atau pakai factory state callback yang di-resolve saat definition() dipanggil. Implementer harus verifikasi pola ini jalan dengan test nyata di Sprint 1, bukan asumsi.

### 14. Test yang Wajib Diperbarui

26 file per inventarisasi teknis. Prioritas tertinggi (assert langsung terhadap `mata_pelajaran_id`, akan gagal jika tidak diupdate):
`KomponenPenilaianCrudTest.php` (39 occurrence), `Guru/KomponenPenilaianControllerTest.php` (12), `Guru/AsesmenControllerTest.php` (9), `Akademik/CapaianKompetensiGeneratorTest.php` (7), `Unit/Services/RaporCalculationServiceTest.php` (5), `Akademik/RaporPdfDataBuilderTest.php` (4), `Admin/RaporControllerTest.php` (6), `Unit/KomponenPenilaianSeederTest.php` (3), `Unit/Models/NilaiSiswaTest.php` (2), `Guru/RaporControllerTest.php` (2), `Akademik/RaporApprovalActionsTest.php` (2), `Admin/KenaikanKelasControllerTest.php` (2), `Unit/Models/AsesmenTest.php` (1), `Feature/DashboardTest.php` (5), `Feature/AkademikTenantScopeTest.php` (8), `Feature/Akademik/GenerateNarasiPerkembanganActionTest.php` (4), `Feature/Akademik/JurnalKbmAdaptiveTest.php` (4 — verifikasi dulu apakah ini benar terpengaruh atau cuma false-positive grep dari `sesi_pembelajaran.mata_pelajaran_id`).

**Test baru yang wajib ditambahkan** (bukan cuma migrasi test lama):
1. Migration backfill: precedence `elemen_cp` > `mata_pelajaran_id` ketika keduanya terisi (buat data seed dengan kondisi ini secara eksplisit, jalankan migration, assert `subjek_type = 'elemen_cp'`).
2. Migration backfill: baris yang tidak punya keduanya (`mata_pelajaran_id` NULL — seharusnya mustahil hari ini karena NOT NULL, tapi tetap test defensif) → migration harus throw, bukan silent skip.
3. `SubjekPenilaianKey::dari()` menghasilkan key berbeda untuk `MataPelajaran#3` vs `ElemenCp#3` (regresi langsung utk kekhawatiran collision).
4. `RaporCalculationService::hitungRekapKelas()` dengan kelas yang punya asesmen campuran (sebagian `mata_pelajaran`, sebagian `elemen_cp`) — pastikan rekap tidak collision dan kedua subjek muncul terpisah di hasil.
5. `KomponenPenilaianController` guard: assign `subjek_type=elemen_cp` dari lembaga manapun harus LOLOS (karena global), assign `subjek_type=mata_pelajaran` dari lembaga lain harus 404 (regresi tenant isolation).
6. UI Guru: guru bisa buat komponen dengan `subjek_type=elemen_cp` (fitur baru yang belum pernah ada).

### Acceptance Criteria Sprint 1

- [ ] `komponen_penilaian`/`asesmen` tidak lagi punya kolom `mata_pelajaran_id`/`elemen_cp` — diganti `subjek_type`/`subjek_id` NOT NULL.
- [ ] Morph map cuma berisi `mata_pelajaran` dan `elemen_cp` — didaftarkan via `enforceMorphMap()`.
- [ ] `SubjekPenilaianKey::dari()` adalah SATU-SATUNYA tempat composite key dibuat — dipakai konsisten di `RaporCalculationService`, `RaporPdfDataBuilder`, `CapaianKompetensiGenerator` (verifikasi: grep tidak ada string concatenation manual `$type . ':' . $id` di luar helper ini).
- [ ] Migration backfill mengikuti precedence eksplisit (elemen_cp > mata_pelajaran_id) dan FAIL keras kalau ada baris tak terpetakan — bukan silent skip.
- [ ] Validasi tenant: `ElemenCp` selalu valid lintas lembaga, `MataPelajaran` tetap harus sama lembaga dengan semester.
- [ ] Portal Guru punya toggle Elemen CP yang sebelumnya tidak ada, tervalidasi di backend (bukan cuma UI hiding).
- [ ] Semua 26 file test diperbarui + 6 test baru di atas, full suite akademik hijau.
- [ ] `git grep "mata_pelajaran_id"` di `komponen_penilaian`/`asesmen`-related code (bukan `jadwal_pelajaran`/`rpp`/`sesi_pembelajaran`) menghasilkan nol hasil.
- [ ] **Verifikasi final sebelum migration ketiga (drop kolom) dijalankan**: `git grep` untuk `mata_pelajaran_id`, `elemen_cp`, dan `->mataPelajaran` di seluruh `app/` dan `resources/views/` (kecuali `JadwalPelajaran`/`Rpp`/`SesiPembelajaran` yang di luar cakupan) menghasilkan **nol hasil** — refactor kode HARUS selesai lebih dulu, baru migration drop dijalankan. Urutan ini wajib, bukan opsional: migration final yang menghapus kolom lama TIDAK BOLEH dijalankan selama masih ada satu pun kode yang bergantung padanya, karena begitu dijalankan tidak ada jalan mundur tanpa restore dari backup.

---

## Sprint 2 — Assessment Type (Rancangan Ringkas)

**Tujuan**: `KomponenPenilaian` punya `assessment_type` (`numeric`/`narrative`/`predicate`), dan form input nilai guru mengikuti tipe ini alih-alih hardcode numerik.

- Kolom baru `komponen_penilaian.assessment_type` (string, default `'numeric'` — semua data existing otomatis kompatibel, non-breaking).
- Enum `AssessmentType` (`Numeric`, `Narrative`, `Predicate`) di `App\Domains\Akademik\Enums`.
- `portals/guru/akademik/asesmen/show.blade.php` (form isi nilai matrix): render input berbeda per `komponen->assessment_type` — numeric tetap `<input type="number">` ke `nilai_angka`, narrative jadi `<textarea>` ke `catatan`, predicate jadi `<select>` (opsi predikat dikonfigurasi per lembaga atau tetap sederhana A/B/C/D dulu — detail dibahas saat spec Sprint 2 ditulis penuh) ke `predikat`.
- Default `assessment_type` saat create komponen: kalau `subjek_type === 'elemen_cp'` → default `narrative`; kalau `mata_pelajaran` → default `numeric`. Admin tetap bisa override manual.
- **Bergantung pada Sprint 1** (butuh `subjek_type` untuk menentukan default assessment_type saat create).

## Sprint 3 — Curriculum Phase (Rancangan Ringkas)

**Tujuan**: entitas `Fase` eksplisit menggantikan `Kelas.tingkat` sebagai string bebas untuk keperluan yang butuh reasoning kurikulum (bukan cuma label tampilan).

- Tabel `fase` (id, kode: `foundation/a/b/c/d/e/f`, nama, urutan) — data referensi nasional, seed tetap, tidak tenant-scoped (sama seperti `elemen_cp`).
- `kelas.fase_id` (nullable FK) ditambahkan **berdampingan** dengan `Kelas.tingkat` (tidak menghapus `tingkat` — itu tetap label bebas untuk nama rombel, `fase_id` murni untuk logika kurikulum).
- Mapping default per `bentuk_pendidikan` + tingkat numerik (misal SD tingkat 1-2 → Fase A) disediakan sebagai helper/service, BUKAN enforced otomatis saat create Kelas (admin tetap pilih manual saat ini, auto-suggest bisa jadi Sprint lanjutan kalau dibutuhkan).
- **Independen dari Sprint 1-2** — bisa dikerjakan paralel kalau mau, tapi diurutkan setelah supaya tim tidak context-switch berlebihan.

## Sprint 4 — Academic Profile Service (Rancangan Ringkas)

**Tujuan**: satu service `AcademicProfile::fromBentukPendidikan(string $bentukPendidikan)` yang mengembalikan value object berisi `learningMode` (reuse `ModePembelajaran`), `defaultAssessmentType`, `subjectRequired` (bool), `reportTemplate` (string key, dipakai Sprint 5). **Tanpa tabel database baru** — murni derivasi statis, mengikuti pola `ModePembelajaran::fromBentukPendidikan()` yang sudah ada dan terbukti benar.

- `app/Domains/Akademik/Services/AcademicProfile.php` (atau DTO + static factory method).
- Dipakai untuk menyederhanakan tempat-tempat yang saat ini query `bentuk_pendidikan` manual berulang (`KomponenPenilaianController`, dll) jadi satu titik derivasi.
- **Bergantung pada Sprint 1-2** (field `reportTemplate`/`defaultAssessmentType` butuh konsep yang baru ada setelah sprint tsb).

## Sprint 5 — Report Engine Abstraction (Rancangan Ringkas)

**Tujuan**: `RaporPdfDataBuilder` jadi orchestrator, bukan tempat semua logika render diletakkan. Hanya implementasi `DikdasReportBuilder` (rename dari builder existing) yang benar-benar dibangun sekarang — `PaudReportBuilder` dkk BELUM diimplementasikan (tidak ada pelanggan PAUD nyata), tapi interfacenya disiapkan supaya penambahan builder baru nanti tidak perlu membongkar orchestrator.

- Interface `ReportBuilder` (kontrak: `build(Siswa $siswa, Semester $semester): array`).
- `RaporPdfDataBuilder` (existing) di-rename/refactor jadi `DikdasReportBuilder implements ReportBuilder`.
- Orchestrator baru `ReportEngine` yang memilih builder berdasarkan `AcademicProfile::reportTemplate` (Sprint 4) — untuk sekarang cuma ada 1 mapping (`dikdas` → `DikdasReportBuilder`), builder lain throw `NotImplementedException` yang jelas kalau dipanggil untuk `reportTemplate` yang belum ada implementasinya (bukan silent fallback ke Dikdas).
- **Bergantung pada Sprint 1 dan 4.**

---

## Self-Review

- **Placeholder scan**: tidak ada "TBD"/"nanti dijelaskan lebih lanjut" di Sprint 1 (detail penuh). Sprint 2-5 sengaja ringkas sesuai kesepakatan ("rancangan ringkas"), akan di-detail-kan penuh saat gilirannya tiba untuk dieksekusi.
- **Konsistensi internal**: `SubjekPenilaian` interface, `SubjekPenilaianKey` helper, morph map alias (`mata_pelajaran`/`elemen_cp`) dipakai konsisten di semua bagian Sprint 1 — tidak ada penamaan yang menyimpang antar seksi.
- **Cakupan precedence backfill**: eksplisit ditulis sesuai permintaan review (`elemen_cp` menang kalau keduanya terisi), termasuk kasus fail-fast.
- **Blast radius**: seksi 11 mendaftar SEMUA 15+ file blade/controller dari inventarisasi teknis, bukan sampel — tidak ada file yang "lupa disebut lalu ketahuan pas eksekusi".
- **Ambiguitas yang diselesaikan eksplisit**: (a) `ElemenCp` tidak punya `lembaga_id` — diputuskan sengaja, dengan alasan. (b) Guru mapel tidak melihat komponen ElemenCp — diputuskan sengaja sebagai batasan use-case saat ini, dicatat sebagai keputusan bukan oversight. (c) Interface `SubjekPenilaian` sengaja kosong (marker interface) dengan alasan teknis PHP (properti Eloquent tidak bisa dikontrak sebagai interface method tanpa accessor eksplisit).
