# Spec: TD-AKADEMIK-002 — Retrofit Sprint 1-4 Akademik ke `laravel-feature-standard`

**Status:** Ready for Plan — disetujui user, siap masuk plan eksekusi.
**Branch:** `akademik-v2`
**Bergantung pada:** Sprint 1-5 Fondasi Akademik Multi-Jenjang (SELESAI semua, `TD-AKADEMIK-002` dicatat di `PETA_PENGEMBANGAN.md` setelah Sprint 4).

## Latar Belakang

Setelah Sprint 1-5 selesai, audit terhadap `.agents/skills/laravel-feature-standard/SKILL.md` menemukan 3 deviasi nyata (dicatat sbg `TD-AKADEMIK-002`):

1. Folder `app/Domains/Akademik/Support/` (isi: `SubjekPenilaianKey.php`, `AcademicProfile.php`) bukan folder resmi di skill.
2. `Admin\FaseDefaultMappingController` (Sprint 3) tidak pakai FormRequest/DTO/Action — validasi inline langsung ke Eloquent.
3. `Admin\KelasController` (existing sebelum Sprint 1, disentuh Sprint 3-4 utk `fase_id`) juga tidak pakai FormRequest/DTO/Action sama sekali.

**Keputusan hasil diskusi** (3 pertanyaan terpisah, dijawab eksplisit oleh user):
- Folder `Support/` → **dipindah isinya ke `Services/`** (bukan diresmikan jadi folder ke-8).
- `KelasController` → **di-retrofit PENUH** (bukan cuma bagian `fase_id`) — tidak bisa "sebagian FormRequest, sebagian inline" dalam satu method yang sama.
- FormRequest → **tetap dibuat penuh sesuai skill literal**, MESKIPUN ditemukan bahwa resource sekelas (`PolaJamController`) di codebase nyata TIDAK pakai FormRequest (cuma DTO+Action). Keputusan sadar: standar baru yang lebih ketat drpd `PolaJam`, bukan mengikuti konvensi yang sudah ada.

**Prinsip pemandu**: ini **refactor internal murni**, BUKAN kesempatan mengubah behavior. Setiap validasi/abort/pesan error yang ada sekarang harus menghasilkan hasil yang SAMA PERSIS setelah refactor — cuma dipindah lokasinya (dari inline controller ke FormRequest/DTO/Action), bukan diubah aturannya. Kalau menemukan celah/bug saat membaca ulang kode lama, LAPORKAN ke user, jangan diam-diam "sekalian diperbaiki".

## Bagian A — Pindahkan `Support/` → `Services/`

### Keputusan Desain
`SubjekPenilaianKey` dan `AcademicProfile` adalah stateless helper (tidak ada constructor dependency, murni static/readonly) — skill hanya mendaftar 7 folder resmi (`Actions/DataTransferObjects/Events/Listeners/Models/Services/ViewModels`), tidak ada `Support/`. `Services/` di skill sendiri sudah punya contoh yang stateless-friendly (`PermissionContextService`), jadi tidak perlu folder baru.

### File yang Dipindah (namespace berubah, isi TIDAK berubah)

| Dari | Ke |
|---|---|
| `app/Domains/Akademik/Support/SubjekPenilaianKey.php` (namespace `...\Support`) | `app/Domains/Akademik/Services/SubjekPenilaianKey.php` (namespace `...\Services`) |
| `app/Domains/Akademik/Support/AcademicProfile.php` (namespace `...\Support`) | `app/Domains/Akademik/Services/AcademicProfile.php` (namespace `...\Services`) |
| `tests/Unit/Support/SubjekPenilaianKeyTest.php` | `tests/Unit/Services/SubjekPenilaianKeyTest.php` |
| `tests/Unit/Support/AcademicProfileTest.php` | `tests/Unit/Services/AcademicProfileTest.php` |

Folder `app/Domains/Akademik/Support/` dan `tests/Unit/Support/` dihapus total setelah kosong (verifikasi kosong sebelum hapus).

### Referensi yang WAJIB diupdate (`use` statement, sudah diverifikasi lengkap via grep — 4 file)

| File | Baris yang berubah |
|---|---|
| `app/Domains/Akademik/Services/RaporPdfDataBuilder.php` | `use App\Domains\Akademik\Support\SubjekPenilaianKey;` → `use App\Domains\Akademik\Services\SubjekPenilaianKey;` |
| `app/Domains/Akademik/Services/RaporCalculationService.php` | sama seperti di atas (`SubjekPenilaianKey`) |
| `app/Domains/Akademik/Services/RaporPdfDataBuilder.php` | `use App\Domains\Akademik\Support\AcademicProfile;` → `use App\Domains\Akademik\Services\AcademicProfile;` (ditambahkan Sprint 5) |

**Acceptance criterion**: `grep -rn "Domains\\\\Akademik\\\\Support" app/ tests/` HARUS mengembalikan nol hasil setelah Bagian A selesai (kecuali di `.agents/` yang merupakan arsip historis, tidak diubah).

## Bagian B — Retrofit `Admin\FaseDefaultMappingController`

### File Baru

```text
app/Domains/Akademik/DataTransferObjects/FaseDefaultMappingData.php
app/Domains/Akademik/Actions/FaseMapping/CreateFaseDefaultMappingAction.php
app/Domains/Akademik/Actions/FaseMapping/UpdateFaseDefaultMappingAction.php
app/Http/Requests/Akademik/StoreFaseDefaultMappingRequest.php
app/Http/Requests/Akademik/UpdateFaseDefaultMappingRequest.php
```

### DTO

```php
final readonly class FaseDefaultMappingData
{
    public function __construct(
        public string $bentukPendidikan,
        public ?string $tingkat,
        public int $faseId,
        public ?int $lembagaId, // null = platform-wide; diisi Action, BUKAN dari FormRequest langsung (lihat §Keputusan Desain)
    ) {}
}
```

### Form Request — HANYA validasi format/tipe/existence (persis rules inline yang ada sekarang, tidak ada perubahan)

`StoreFaseDefaultMappingRequest::rules()`:
```php
return [
    'bentuk_pendidikan' => ['required', Rule::in(['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB'])],
    'tingkat' => ['nullable', 'string', 'max:10'],
    'fase_id' => ['required', 'exists:fase,id'],
    'lembaga_id' => ['nullable', 'integer', 'exists:lembaga,id'],
];
```
`UpdateFaseDefaultMappingRequest::rules()`: sama persis TANPA `lembaga_id` (mengikuti kode existing — `update()` tidak menerima `lembaga_id` dari input sama sekali, itu tetap dipertahankan).

Kedua FormRequest: `authorize(): bool { return true; }` (persis pola skill — permission check tetap di controller via `$this->authorize('fase-mapping.*')`, TIDAK dipindah ke FormRequest, supaya konsisten dgn seluruh controller lain di codebase).

**Whitelist `bentuk_pendidikan`**: dipindah dari `private const BENTUK_PENDIDIKAN` di controller ke FormRequest (didefinisikan sbg const lokal di masing-masing FormRequest ATAU const bersama — implementer pilih salah satu, TIDAK PERLU didiskusikan lagi, yang penting whitelist 9 nilai persis sama).

### Action — Menyerap logic yang SEKARANG ada di `store()`/`update()` controller (scope resolution, authorization scope, uniqueness check)

```php
final class CreateFaseDefaultMappingAction
{
    public function execute(FaseDefaultMappingData $data): FaseDefaultMapping
    {
        return FaseDefaultMapping::create([
            'lembaga_id' => $data->lembagaId,
            'bentuk_pendidikan' => $data->bentukPendidikan,
            'tingkat' => $data->tingkat,
            'fase_id' => $data->faseId,
        ]);
    }
}
```
```php
final class UpdateFaseDefaultMappingAction
{
    public function execute(FaseDefaultMapping $mapping, FaseDefaultMappingData $data): FaseDefaultMapping
    {
        $mapping->update([
            'bentuk_pendidikan' => $data->bentukPendidikan,
            'tingkat' => $data->tingkat,
            'fase_id' => $data->faseId,
        ]);

        return $mapping;
    }
}
```
**PENTING — yang TIDAK pindah ke Action**: uniqueness check (`FaseDefaultMapping::where(...)->exists()` → pesan error validasi) dan `authorizeMappingScope()` TETAP di controller, PERSIS seperti sekarang. Alasan: uniqueness check menghasilkan `back()->withErrors(...)->withInput()` (response HTTP, bukan business exception), dan `authorizeMappingScope()` adalah authorization (bukan business mutation) — memindahkan keduanya ke Action akan mencampur tanggung jawab HTTP-response dgn business-logic murni, melanggar semangat "Action tidak bergantung pada HTTP Request" di skill §3. Controller tetap memanggil `$lembagaId`/uniqueness/`authorizeMappingScope()` SEBELUM memanggil Action, sama urutannya dgn kode sekarang — HANYA `FaseDefaultMapping::create(...)`/`->update(...)` yang berpindah ke Action.

### Controller Setelah Refactor (kerangka, method lain seperti `index`/`create`/`edit`/`destroy` TIDAK berubah)

```php
public function store(StoreFaseDefaultMappingRequest $request, CreateFaseDefaultMappingAction $action): RedirectResponse
{
    $this->authorize('fase-mapping.create');

    $validated = $request->validated();
    $tingkat = $validated['tingkat'] !== '' ? ($validated['tingkat'] ?? null) : null;

    $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
    $lembagaId = $isPlatformOrYayasan ? ($validated['lembaga_id'] ?? null) : $request->user()->lembaga_id;

    $this->authorizeMappingScope($request, $lembagaId);

    if (FaseDefaultMapping::where('lembaga_id', $lembagaId)->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
        return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada mapping default untuk kombinasi jenjang dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
    }

    $action->execute(new FaseDefaultMappingData(
        bentukPendidikan: $validated['bentuk_pendidikan'],
        tingkat: $tingkat,
        faseId: (int) $validated['fase_id'],
        lembagaId: $lembagaId,
    ));

    return redirect()->route('admin.fase-mapping.index')->with('status', 'Mapping default berhasil disimpan.');
}
```
`update()` mengikuti pola yang sama (uniqueness check exclude `id` sendiri, `lembagaId` diambil dari `$faseMapping->lembaga_id` existing — persis logic sekarang, cuma `FaseDefaultMapping::create()`/`update()` di akhir yang diganti panggilan Action).

## Bagian C — Retrofit `Admin\KelasController`

### File Baru

```text
app/Domains/Akademik/DataTransferObjects/KelasData.php
app/Domains/Akademik/Actions/Kelas/CreateKelasAction.php
app/Domains/Akademik/Actions/Kelas/UpdateKelasAction.php
app/Http/Requests/Akademik/StoreKelasRequest.php
app/Http/Requests/Akademik/UpdateKelasRequest.php
```

### DTO

```php
final readonly class KelasData
{
    public function __construct(
        public int $tahunAjaranId,
        public string $nama,
        public ?string $tingkat,
        public ?int $faseId,
        public ?int $waliKelasGuruId,
        public ?int $polaJamId,
    ) {}
}
```
**`lembaga_id` (utk yayasan-scope create) SENGAJA TIDAK masuk DTO** — itu bukan bagian data Kelas yang divalidasi dari form, itu hasil resolusi `session('active_lembaga_id')` yang terjadi SETELAH DTO dibuat, tepat sebelum `Kelas::create()`. Action `CreateKelasAction::execute()` menerima `KelasData` + `?int $lembagaIdOverride = null` sbg parameter kedua terpisah, supaya tetap eksplisit bahwa field ini datang dari sumber berbeda (session, bukan input form) — mencegah percampuran 2 sumber data yang beda konteks dalam satu DTO.

### Form Request — HANYA validasi format/tipe (PERSIS rules inline sekarang — `integer` polos, TANPA `exists:` tambahan utk `tahun_ajaran_id`/`wali_kelas_guru_id`/`pola_jam_id`)

**PENTING — jangan tambah `exists:` yang belum ada sekarang.** Kode existing sengaja TIDAK pakai `exists:tahun_ajaran,id` dkk di validasi — sebagai gantinya, ownership dicek manual (`TahunAjaran::find()` + `abort_if(...,404)`), yang menghasilkan response `404` (halaman not found), BUKAN `422` (validation error redirect). Menambah `exists:` di FormRequest akan diam-diam MENGUBAH kode HTTP response dari 404 jadi 422 untuk kasus ID tidak valid — ini PERUBAHAN BEHAVIOR yang tidak diminta, DILARANG dilakukan di retrofit ini. `fase_id` adalah SATU-SATUNYA field yang memang sudah punya `exists:fase,id` di kode existing (Sprint 3) — itu saja yang dipertahankan apa adanya.

`StoreKelasRequest::rules()` (identik `UpdateKelasRequest::rules()`):
```php
return [
    'tahun_ajaran_id' => ['required', 'integer'],
    'nama' => ['required', 'string', 'max:255'],
    'tingkat' => ['nullable', 'string', 'max:20'],
    'fase_id' => ['nullable', 'integer', 'exists:fase,id'],
    'wali_kelas_guru_id' => ['nullable', 'integer'],
    'pola_jam_id' => ['nullable', 'integer'],
];
```

### Action — Menyerap SELURUH logic ownership-check yang sekarang ada di `store()`/`update()`

```php
final class CreateKelasAction
{
    public function execute(KelasData $data, ?int $lembagaIdOverride = null): Kelas
    {
        $tahunAjaran = TahunAjaran::find($data->tahunAjaranId);
        abort_if($tahunAjaran === null, 404);

        $waliKelasGuruId = null;
        if ($data->waliKelasGuruId !== null) {
            $guru = Guru::find($data->waliKelasGuruId);
            abort_if($guru === null || $guru->lembaga_id !== $tahunAjaran->lembaga_id, 404);
            $waliKelasGuruId = $guru->id;
        }

        $polaJamId = null;
        if ($data->polaJamId !== null) {
            $polaJam = PolaJam::find($data->polaJamId);
            abort_if($polaJam === null || $polaJam->lembaga_id !== $tahunAjaran->lembaga_id, 404);
            $polaJamId = $polaJam->id;
        }

        return Kelas::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => $data->nama,
            'tingkat' => $data->tingkat,
            'fase_id' => $data->faseId,
            'wali_kelas_guru_id' => $waliKelasGuruId,
            'pola_jam_id' => $polaJamId,
            'lembaga_id' => $lembagaIdOverride,
        ]);
    }
}
```
Catatan (SUDAH DIVERIFIKASI, bukan lagi asumsi): `Kelas` model pakai `BelongsToTenant` (`app/Models/Concerns/BelongsToTenant.php`), yang di event `creating` mengecek `$model->lembaga_id === null` lalu auto-fill dari `auth()->user()->lembaga_id` KHUSUS utk `widestScopeLevel() === 'lembaga'`. Menyertakan `'lembaga_id' => $lembagaIdOverride` di array `create()` — baik `$lembagaIdOverride` itu `null` (scope lembaga, tidak override) MAUPUN berisi ID (scope yayasan, override eksplisit) — SAMA-SAMA AMAN: kalau `null`, attribute tetap `null` sebelum hook `creating` jalan sehingga auto-fill tetap terpicu normal; kalau berisi ID, attribute sudah terisi sebelum hook jalan sehingga kondisi `=== null` gagal dan auto-fill dilewati (override dihormati). Jadi array `'lembaga_id' => $lembagaIdOverride` BOLEH selalu disertakan tanpa kondisional — tidak ada bug tersembunyi di sini.

`UpdateKelasAction::execute(Kelas $kelas, KelasData $data): Kelas` — sama pola ownership-check tapi cek `!== $kelas->lembaga_id` (bukan `$tahunAjaran->lembaga_id` krn `update()` existing pakai `$kelas->lembaga_id` sbg referensi, bukan `$tahunAjaran` baru — PERSIS kode sekarang di `update()`).

### Controller Setelah Refactor (kerangka)

```php
public function store(StoreKelasRequest $request, CreateKelasAction $action): RedirectResponse
{
    $this->authorize('kelas.create');

    $data = KelasData::fromValidated($request->validated());

    $lembagaIdOverride = null;
    if ($request->user()->widestScopeLevel() === 'yayasan') {
        $lembagaIdOverride = session('active_lembaga_id');

        if ($lembagaIdOverride === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat kelas.'])->withInput();
        }
    }

    $action->execute($data, $lembagaIdOverride);

    return redirect()->route('admin.kelas.index')->with('status', 'Kelas berhasil disimpan.');
}
```
`KelasData::fromValidated(array $validated): self` — static factory method di DTO, mapping field snake_case (`tahun_ajaran_id`) ke property camelCase (`tahunAjaranId`), pola sama dgn `NilaiData::fromRequest()` di contoh skill §4.

## §Test Matrix (Acceptance Criteria WAJIB — regresi, BUKAN fitur baru)

Prinsip test: SETIAP test existing yang menguji `FaseDefaultMappingController`/`KelasController` (dari Sprint 3, Sprint 4, dan test lama pra-Sprint-1 kalau ada utk Kelas) HARUS TETAP PASS TANPA PERUBAHAN ASSERTION — hanya boleh berubah kalau memang ada penyesuaian teknis murni (mis. nama exception class kalau berubah, TAPI di sini TIDAK ADA perubahan behavior yang direncanakan, jadi seharusnya 0 assertion berubah).

Test BARU yang wajib ditambah (unit, langsung ke Action, melewati HTTP sepenuhnya — pola sama dgn `SimpanNilaiSiswaAction` di Sprint 2):
- `CreateFaseDefaultMappingAction`/`UpdateFaseDefaultMappingAction`: test langsung panggil `execute()` dgn `FaseDefaultMappingData` buatan tangan, verifikasi baris tersimpan benar.
- `CreateKelasAction`/`UpdateKelasAction`: test langsung panggil `execute()` dgn `KelasData` buatan tangan, TERMASUK skenario ownership-check gagal (guru/polaJam beda lembaga → tetap 404 via `abort_if`, dibuktikan lewat `AbortHttpResponseException`/assertion setara Pest utk abort).

Test FormRequest (opsional tapi disarankan): `StoreKelasRequest`/`StoreFaseDefaultMappingRequest` diuji `assertValidationRules()` atau lewat `$this->post(...)` end-to-end (yang terakhir sudah tercakup test Feature existing, jadi tidak wajib duplikasi).

## Non-Goals

- TIDAK mengubah behavior/pesan error/HTTP status code apa pun yang sudah ada — SETIAP perbedaan hasil sebelum-vs-sesudah refactor WAJIB dilaporkan ke user sbg temuan, bukan diselesaikan sendiri sbg "sekalian diperbaiki".
- TIDAK menyentuh method lain di kedua controller (`index`, `create`, `edit`, `destroy`, `faseSuggestion`) — HANYA `store()`/`update()`.
- TIDAK menambah FormRequest/DTO/Action ke controller LAIN yang juga belum patuh skill (mis. `PolaJamController`) — scope `TD-AKADEMIK-002` HANYA `FaseDefaultMappingController` + `KelasController`, sesuai catatan debt.
- TIDAK menyentuh `TD-AKADEMIK-001` (`ElemenCp` vs `aspek_perkembangan`) — itu debt terpisah, dibahas di sesi lain.

## Self-Review

- Semua 3 keputusan diskusi masuk eksplisit: (1) `Support/`→`Services/` §Bagian A, (2) `KelasController` retrofit PENUH §Bagian C, (3) FormRequest tetap dibuat meski `PolaJam` tidak pakai — dicatat sadar sbg standar lebih ketat §Latar Belakang.
- Placeholder scan: tidak ada. Ketidakpastian soal `BelongsToTenant` auto-fill `lembaga_id` di §Bagian C SUDAH diverifikasi langsung terhadap kode (`app/Models/Concerns/BelongsToTenant.php`) sebelum spec ini final — bukan lagi ditandai "perlu dicek nanti".
- Scope check: murni refactor internal 2 controller + 1 pemindahan folder. Tidak melebar ke controller lain atau `TD-AKADEMIK-001`.
- Konsistensi tipe: `KelasData`/`FaseDefaultMappingData` dipakai identik antara §Bagian B/C (definisi) dan bagian controller "setelah refactor" (pemanggilan).
