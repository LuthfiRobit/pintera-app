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
`RppController::index()` membaca `$request->query('kurikulum')` dan meneruskannya. View `index.blade.php` menambah dropdown baru di baris filter mata pelajaran (baris 189-198), berisi opsi dari `KurikulumFramework::cases()` (`K13` = `'k13'`, `Merdeka` = `'merdeka'`), plus `filters.kurikulum` di `x-data` Alpine `rppPageManager(...)` yang sudah ada supaya filter ikut ter-refresh via AJAX fragment (`muatUlangDaftar()`) — pola AJAX fragment yang sudah baku di proyek ini, tidak ada mekanisme baru.

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

Kedua perubahan ini murni validasi tambahan — tidak mengubah `RppData`/`CreateRppAction`/`UpdateRppAction`, tidak mengubah `toDTO()`.

### 2.3 — Test regresi cross-tenant IDOR ekstrakurikuler

Ditambahkan ke `tests/Feature/Admin/LembagaRelationalManagementTest.php` (file existing, memakai fixture `$this->manager`/`$this->lembaga` dari `beforeEach`) — 2 test baru: `update` dan `destroy` ekskul lintas-lembaga ditolak 404, dan data lembaga pemilik asli tidak berubah. Murni test tambahan, TIDAK ADA perubahan kode produksi (`EkstrakurikulerController` sudah benar, hanya belum dibuktikan test).

## 3. Non-Goals (eksplisit di luar scope)

- Tidak ada perubahan skema `rpp` — filter kurikulum murni via `whereHas('kelas', ...)`.
- Tidak mengubah `RppData`/`CreateRppAction`/`UpdateRppAction` — validasi §2.2 murni di layer FormRequest, gagal sebelum DTO dibuat.
- Tidak menambah kolom/state baru ke `Kelas`/`KurikulumAssignment` — Kelompok C murni konsumsi data yang sudah ada dari Priority #1.
- Tidak mengubah `EkstrakurikulerController` — guard-nya sudah benar, §2.3 murni menambah test, bukan fix kode.
- Full test suite TIDAK dijalankan di akhir plan ini secara terpisah — ditunda jadi checkpoint gabungan bersama Kelompok B (keduanya kecil dan berurutan), dijalankan sekali di step terakhir plan Kelompok C.

## 4. Testing (acceptance criteria wajib)

**4.1 — Badge & filter kurikulum RPP**:
- RPP dengan `kelas.kurikulum = 'merdeka'` → response mengandung label "Kurikulum Merdeka" di baris RPP tsb (assert scoped ke baris, bukan `assertSee` global — proyek ini tidak punya `symfony/dom-crawler`, gunakan pola pencarian substring manual seperti Kelompok B).
- RPP dengan `kelas.kurikulum = null` → response mengandung badge "Belum Diketahui" untuk baris itu, TIDAK error/crash.
- Filter `?kurikulum=merdeka` pada index → hanya RPP dari kelas berkurikulum Merdeka yang muncul di `rppList`; RPP dari kelas berkurikulum K13 TIDAK muncul. Assert existence dulu (kedua RPP benar-benar tersimpan) sebelum assert exclusion.

**4.2 — Validasi kelas-semester `StoreRppRequest`**:
- Kelas dari tahun ajaran A + semester dari tahun ajaran B (berbeda) → `store()` gagal validasi pada field `kelas_id`, tidak ada `Rpp` baru tersimpan.
- Kelas dan semester dari tahun ajaran yang SAMA → `store()` sukses seperti biasa (regresi negatif — pastikan validasi baru tidak menolak kombinasi yang valid).

**4.3 — Validasi kelas-semester `UpdateRppRequest`**:
- `Rpp` existing dengan semester tahun ajaran A, di-update dengan `kelas_id` dari tahun ajaran B → gagal validasi pada `kelas_id`.
- Update dengan `kelas_id` lain yang tahun ajarannya SAMA dengan semester `Rpp` existing → sukses.

**4.4 — Cross-tenant IDOR ekstrakurikuler (test baru, kode produksi TIDAK disentuh)**:
- Lembaga B (dengan manager yang py `lembaga.edit`) mencoba `PUT`/`DELETE` ke ekskul milik Lembaga A → `assertNotFound()` (404).
- Setelah percobaan gagal itu, data ekskul milik Lembaga A di database TIDAK berubah (assert `nama_ekskul`/field lain tetap sama seperti sebelum request, dan record tidak terhapus untuk kasus destroy).

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
