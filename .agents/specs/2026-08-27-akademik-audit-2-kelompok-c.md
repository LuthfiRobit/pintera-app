# Audit Sistematis Akademik Tahap 2 — Kelompok C (RPP Reporting & Test Coverage) — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks**: Kelompok terakhir dari audit sistematis tahap 2 modul Akademik. Setelah Kelompok C selesai, checkpoint full test suite gabungan (B+C) dijalankan sekali sebagai penutup — bukan per-kelompok, sesuai kesepakatan kadensi test proyek ini.

---

## 1. Latar Belakang & Masalah

1. **RPP tidak bisa dilaporkan per kurikulum**: sistem `KurikulumFramework`/`KurikulumAssignment` (Priority #1) sudah menstempel `kelas.kurikulum` saat kelas dibuat, tapi `Rpp`/`ListRppAction` tidak pernah memanfaatkannya. Admin tidak bisa menjawab "berapa guru sudah pakai Modul Ajar Merdeka vs masih K13" tanpa buka file satu-satu.
2. **`StoreRppRequest`/`UpdateRppRequest` tidak memvalidasi konsistensi kelas-semester**: hanya `exists:kelas,id`/`exists:semester,id` — tidak ada cek bahwa `kelas.tahun_ajaran_id` cocok dengan tahun ajaran semester yang dipilih. Severity rendah (UI dropdown yang benar sudah menyaring ini secara alami), tapi bisa di-bypass via POST manual.
3. **Tidak ada test regresi cross-tenant IDOR untuk `ekstrakurikuler_lembaga`**: kode guard (`abort_unless($ekstrakurikuler->lembaga_id === $lembaga->id, 404)` di `EkstrakurikulerController::update()`/`destroy()`) sudah benar dan sudah diverifikasi manual saat audit, tapi belum ada test otomatis yang membuktikannya tetap benar ke depan.

## 2. Keputusan Desain

### 2.1 — Badge & filter kurikulum di daftar RPP (tanpa ubah skema)

`Rpp::kelas()` relation SUDAH di-eager-load di `ListRppAction::execute()` (baris 59: `->with(['guru', 'kelas', 'mataPelajaran', 'semester', 'tahunAjaran', 'verifiedBy'])`) — badge kurikulum di `_daftar.blade.php` TIDAK BUTUH query tambahan, cukup baca `$rpp->kelas->kurikulum?->label()` langsung dari relasi yang sudah ada.

**Badge** — ditambahkan di kolom "Kelas & Semester" (`_daftar.blade.php` baris 186-190):
```blade
<td class="px-5 py-3.5 text-gray-700 text-xs">
    <span class="font-bold text-gray-900">{{ $rpp->kelas->nama }}</span>
    <p class="text-gray-500 text-[11px]">{{ $rpp->semester->nama }} &bull; {{ $rpp->tahunAjaran->nama ?? '' }}</p>
    @if ($rpp->kelas->kurikulum)
        <x-badge tone="{{ $rpp->kelas->kurikulum->value === 'merdeka' ? 'green' : 'blue' }}">{{ $rpp->kelas->kurikulum->label() }}</x-badge>
    @else
        <x-badge tone="slate">Belum Diketahui</x-badge>
    @endif
</td>
```
Kelas dengan `kurikulum` null (data legacy) tetap muncul di listing dengan badge abu-abu "Belum Diketahui" (keputusan eksplisit — tidak hilang dari daftar).

**Filter** — param baru `kurikulum` ditambahkan ke `ListRppAction::execute()` (setelah parameter `$mapelId`):
```php
if ($kurikulum) {
    $query->whereHas('kelas', fn ($q) => $q->where('kurikulum', $kurikulum));
}
```

**Kontrak validasi input `kurikulum` — WAJIB divalidasi terhadap enum, bukan string bebas.** `RppController::index()` membaca query param dan MENOLAK nilai yang tidak dikenal (fallback ke `null` = tanpa filter), bukan meneruskan string mentah ke Action:
```php
$kurikulum = $request->query('kurikulum');
if ($kurikulum !== null && ! in_array($kurikulum, array_column(\App\Domains\Akademik\Enums\KurikulumFramework::cases(), 'value'), true)) {
    $kurikulum = null;
}
```
**Behavior eksplisit `?kurikulum=<invalid>`** (mis. `?kurikulum=foobar`): request TIDAK error/500, dan diperlakukan PERSIS seperti tidak ada filter kurikulum sama sekali (fallback ke `null`) — bukan "tidak ada hasil". Ini deterministik dan konsisten dengan pola filter lain di controller yang sama (`$tahunAjaranId`, `$kelasId`, dll., semuanya nullable-passthrough tanpa validasi enum ketat karena berbasis FK `exists` implisit dari dropdown — `kurikulum` berbeda karena bukan FK, jadi perlu whitelist eksplisit sendiri).

**Kontrak AJAX fragment vs full-page — WAJIB konsisten, ini risiko implementasi paling nyata di §2.1.** `RppController::index()` sudah memanggil `$this->listRppAction->execute(...)` SATU KALI di awal method, SEBELUM percabangan `if ($request->ajax())` yang menentukan apakah response berupa fragment `_daftar` atau halaman penuh `index` (lihat kode existing baris 68-87). Karena filter `kurikulum` masuk sbg parameter tambahan ke method `execute()` yang sama, filter ini OTOMATIS berlaku di KEDUA jalur (fragment AJAX dan full-page) tanpa percabangan kode terpisah — TIDAK ADA tempat baru di mana implementer bisa "lupa meneruskan `$kurikulum`" khusus untuk salah satu jalur, selama parameter itu ditambahkan ke satu pemanggilan `execute()` yang sudah ada. Plan implementasi WAJIB memverifikasi ini via test yang mengirim request dengan header AJAX (`->get(..., ['X-Requested-With' => 'XMLHttpRequest'])`) DAN request biasa, keduanya harus menghasilkan filter yang sama.

View `index.blade.php` menambah dropdown baru di baris filter mata pelajaran (baris 189-198), berisi opsi dari `KurikulumFramework::cases()` (`K13` = `'k13'`, `Merdeka` = `'merdeka'`), plus `filters.kurikulum` di `x-data` Alpine `rppPageManager(...)` yang sudah ada supaya filter ikut ter-refresh via AJAX fragment (`muatUlangDaftar()`) — pola AJAX fragment yang sudah baku di proyek ini, tidak ada mekanisme baru.

**Tidak ada perubahan skema** — filter murni via `whereHas('kelas', ...)`, tidak menambah kolom apa pun ke tabel `rpp`.

### 2.2 — Validasi konsistensi kelas-semester

**`StoreRppRequest`** — tambah `withValidator()`:
```php
public function withValidator(\Illuminate\Validation\Validator $validator): void
{
    $validator->after(function ($validator) {
        $kelasId = $this->input('kelas_id');
        $semesterId = $this->input('semester_id');
        if (! $kelasId || ! $semesterId) {
            return;
        }

        $kelas = \App\Models\Kelas::find($kelasId);
        $semester = \App\Models\Semester::find($semesterId);
        if ($kelas && $semester && $kelas->tahun_ajaran_id !== $semester->tahun_ajaran_id) {
            $validator->errors()->add('kelas_id', 'Kelas yang dipilih bukan berasal dari tahun ajaran yang sama dengan semester ini.');
        }
    });
}
```
Guard `! $kelasId || ! $semesterId` mencegah duplikasi pesan error kalau rule dasar (`required`/`exists`) sudah gagal duluan — `withValidator` tetap jalan meski rule dasar gagal, jadi query `Kelas::find(null)`/`Semester::find(null)` harus dihindari secara eksplisit.

**`UpdateRppRequest`** — pola sama, tapi `semester_id` TIDAK ADA di request (semester RPP tidak bisa diubah saat update, hanya `kelas_id`). Perbandingan dilakukan terhadap semester milik `Rpp` yang sedang diedit (route model binding):
```php
public function withValidator(\Illuminate\Validation\Validator $validator): void
{
    $validator->after(function ($validator) {
        $kelasId = $this->input('kelas_id');
        $rpp = $this->route('rpp');
        if (! $kelasId || ! $rpp) {
            return;
        }

        $kelas = \App\Models\Kelas::find($kelasId);
        if ($kelas && $kelas->tahun_ajaran_id !== $rpp->semester->tahun_ajaran_id) {
            $validator->errors()->add('kelas_id', 'Kelas yang dipilih bukan berasal dari tahun ajaran yang sama dengan semester dokumen RPP ini.');
        }
    });
}
```

Kedua perubahan ini murni validasi tambahan — tidak mengubah `RppData`/`CreateRppAction`/`UpdateRppAction`, tidak mengubah `toDTO()`. **`withValidator()` menambah lapis validasi RELASIONAL di ATAS `exists:kelas,id`/`exists:semester,id` yang sudah ada — bukan pengganti.** Rule `exists` tetap wajib ada persis seperti sekarang (menangani "ID ini benar-benar ada di database"); `withValidator()->after()` HANYA menangani kasus di mana kedua ID itu masing-masing valid tapi TIDAK KONSISTEN satu sama lain (kelas dari tahun ajaran berbeda dari semester). Kedua lapis ini independen dan keduanya tetap jalan.

### 2.3 — Test regresi cross-tenant IDOR ekstrakurikuler

Ditambahkan ke `tests/Feature/Admin/LembagaRelationalManagementTest.php` (file existing, memakai fixture `$this->manager`/`$this->lembaga` dari `beforeEach`) — 2 test baru: `update` dan `destroy` ekskul lintas-lembaga ditolak 404, dan data lembaga pemilik asli tidak berubah. Murni test tambahan, TIDAK ADA perubahan kode produksi (`EkstrakurikulerController` sudah benar, hanya belum dibuktikan test).

**Aktor & data test HARUS eksplisit berbeda entitas** — bukan sekadar 2 record ekskul, tapi 2 LEMBAGA berbeda dengan manager masing-masing: buat `$lembagaA` (pemilik record `EkstrakurikulerLembaga` yang jadi target serangan) dan `$lembagaB` terpisah dengan `$managerB` sendiri (juga punya permission `lembaga.edit`, scope ke `$lembagaB`). Request `PUT`/`DELETE` dikirim dengan `actingAs($managerB)` ke URL yang menyebut `$lembagaB` di route param `{lembaga}` TAPI `{ekstrakurikuler}` milik `$lembagaA` — ini kombinasi yang benar-benar menguji guard `abort_unless($ekstrakurikuler->lembaga_id === $lembaga->id, 404)`, bukan sekadar "user tidak diautentikasi" atau "user tidak punya permission apa pun".

## 3. Non-Goals (eksplisit di luar scope)

- Tidak ada perubahan skema `rpp` — filter kurikulum murni via `whereHas('kelas', ...)`.
- Tidak mengubah `RppData`/`CreateRppAction`/`UpdateRppAction` — validasi §2.2 murni di layer FormRequest, gagal sebelum DTO dibuat.
- Tidak menambah kolom/state baru ke `Kelas`/`KurikulumAssignment` — Kelompok C murni konsumsi data yang sudah ada dari Priority #1.
- Tidak mengubah `EkstrakurikulerController` — guard-nya sudah benar, §2.3 murni menambah test, bukan fix kode.
- **Full test suite gabungan B+C hanya dijalankan SEKALI sebagai checkpoint penutup setelah seluruh implementasi Kelompok B dan Kelompok C selesai** (di step terakhir plan Kelompok C) — TIDAK dijalankan sebagai checkpoint terpisah setelah masing-masing kelompok selesai sendiri-sendiri.

## 4. Testing (acceptance criteria wajib)

**4.1 — Badge & filter kurikulum RPP**:
- Siapkan MINIMAL 2 fixture RPP dalam satu test: 1 dari kelas berkurikulum `merdeka`, 1 dari kelas berkurikulum `k13`, dengan `judul_topik`/nama yang unik & mudah dibedakan.
- **Badge scoped per baris (bukan `assertSee` global)**: assert label "Kurikulum Merdeka" muncul PADA SUBSTRING HTML yang memuat RPP Merdeka tsb (pola pencarian substring manual seperti Kelompok B, karena proyek ini tidak punya `symfony/dom-crawler`) — bukan sekadar `assertSee('Kurikulum Merdeka')` di level response penuh, supaya tidak false-positive kalau ada teks serupa di tempat lain di halaman.
- RPP dengan `kelas.kurikulum = null` → badge "Belum Diketahui" muncul PADA SUBSTRING HTML yang memuat RPP tsb secara spesifik (bukan cek keberadaan teks "Belum Diketahui" di sembarang tempat di halaman), dan response tidak error/crash.
- **Filter DUA ARAH, bukan cuma satu nilai**: `?kurikulum=merdeka` → RPP Merdeka muncul di `rppList`, RPP K13 TIDAK muncul. Test TERPISAH: `?kurikulum=k13` → RPP K13 muncul, RPP Merdeka TIDAK muncul. Assert existence dulu (kedua RPP benar-benar tersimpan, exists di DB) sebelum assert exclusion masing-masing arah.
- **Kontrak AJAX vs full-page**: jalankan filter `?kurikulum=merdeka` dua kali dalam satu test — sekali sbg request biasa (dapat halaman penuh `index`), sekali dengan header `X-Requested-With: XMLHttpRequest` (dapat fragment `_daftar`) — KEDUANYA harus menghasilkan `rppList` yang sama-sama sudah terfilter (assert via `assertViewHas('rppList', ...)` untuk kedua request).
- **Behavior nilai invalid**: `?kurikulum=foobar` → response tetap `assertOk()` (bukan 500), dan `rppList` berisi SEMUA RPP tanpa terfilter (setara "tidak ada filter kurikulum") — bukan hasil kosong.

**4.2 — Validasi kelas-semester `StoreRppRequest`**:
- Kelas dari tahun ajaran A + semester dari tahun ajaran B (berbeda) → `store()` gagal validasi, `assertSessionHasErrors(['kelas_id'])` SPESIFIK (bukan `assertSessionHasErrors()` generik tanpa field) — membuktikan error benar-benar menempel ke field yang dimaksud, bukan field lain. `assertDatabaseMissing('rpp', [...kolom identitas RPP yang dicoba dibuat...])` untuk memastikan tidak ada `Rpp` baru tersimpan (bukan cuma cek response, tapi state DB eksplisit).
- Kelas dan semester dari tahun ajaran yang SAMA → `store()` sukses, `assertSessionDoesntHaveErrors()`, DAN assert `Rpp` baru benar-benar tersimpan di DB (`assertDatabaseHas`) — regresi negatif membuktikan validasi baru tidak menolak kombinasi yang valid.

**4.3 — Validasi kelas-semester `UpdateRppRequest`**:
- `Rpp` existing dengan semester tahun ajaran A, di-update dengan `kelas_id` dari tahun ajaran B → `assertSessionHasErrors(['kelas_id'])` SPESIFIK. Assert `Rpp` di DB TIDAK berubah `kelas_id`-nya dari nilai semula (`assertDatabaseHas` dgn `kelas_id` lama, atau `$rpp->fresh()->kelas_id` tetap sama).
- Update dengan `kelas_id` lain yang tahun ajarannya SAMA dengan semester `Rpp` existing → sukses, `assertSessionDoesntHaveErrors()`, DAN assert `kelas_id` di DB benar-benar berubah ke nilai baru.

**4.4 — Cross-tenant IDOR ekstrakurikuler (test baru, kode produksi TIDAK disentuh)**:
- Lembaga B (dengan manager `$managerB` yang PUNYA `lembaga.edit`, scope ke Lembaga B) mencoba `PUT`/`DELETE` ke ekskul milik Lembaga A (route `{lembaga}` = Lembaga B, route `{ekstrakurikuler}` = milik Lembaga A) → `assertNotFound()` (404).
- Setelah percobaan gagal itu, data ekskul milik Lembaga A di database TIDAK berubah: `assertDatabaseHas('ekstrakurikuler_lembaga', [...nilai asli sebelum request...])` untuk kasus update, dan `assertDatabaseHas` (record masih ada, belum ter-soft/hard-delete) untuk kasus destroy — bukan cuma assert response 404.

## 5. Ringkasan Perubahan File

```text
app/Domains/Akademik/Actions/Rpp/ListRppAction.php               [+parameter kurikulum, +whereHas filter]
app/Http/Controllers/Admin/RppController.php                     [+baca query kurikulum, teruskan ke action & view]
app/Http/Requests/Akademik/StoreRppRequest.php                   [+withValidator() konsistensi kelas-semester]
app/Http/Requests/Akademik/UpdateRppRequest.php                  [+withValidator() konsistensi kelas-semester]
resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php   [+badge kurikulum]
resources/views/portals/lembaga/akademik/rpp/index.blade.php     [+dropdown filter kurikulum]
tests/Feature/Akademik/RppKurikulumReportingTest.php             [BARU]
tests/Feature/Akademik/StoreRppRequestKelasSemesterTest.php      [BARU]
tests/Feature/Admin/LembagaRelationalManagementTest.php          [+2 test IDOR ekskul, file existing]
```
