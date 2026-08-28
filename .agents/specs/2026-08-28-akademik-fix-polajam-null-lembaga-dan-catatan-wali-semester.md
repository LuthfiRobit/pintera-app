# Fix: PolaJam lembaga_id NULL untuk Aktor Lembaga Biasa & Catatan Wali Kelas Tanpa Cross-Check Semester — Design Spec

**Tanggal**: 2026-08-28
**Branch**: `akademik-v2`
**Konteks**: 2 temuan dari putaran audit ke-2 (area PolaJam CRUD & CatatanWaliKelas write-path), digabung dalam satu siklus fix karena keduanya Medium severity dan file terpisah tidak saling bergantung.

---

## 1. Latar Belakang & Masalah

### Temuan 1 — `PolaJamController::store()` tidak mengisi `lembaga_id` untuk aktor lembaga biasa

```php
// app/Http/Controllers/Admin/PolaJamController.php:50-59
$lembagaId = null;
if ($request->user()->widestScopeLevel() === 'yayasan') {
    $lembagaId = session('active_lembaga_id');
    if ($lembagaId === null) {
        return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'])->withInput();
    }
}

$action->execute(new PolaJamData(nama: $data['nama'], lembagaId: $lembagaId));
```

`$lembagaId` HANYA diisi kalau aktor level yayasan. Untuk aktor lembaga-scoped biasa (`widestScopeLevel() !== 'yayasan'` — kasus MAYORITAS penggunaan fitur ini), `$lembagaId` tetap `null` sampai dikirim ke `CreatePolaJamAction`, yang langsung `PolaJam::create(['lembaga_id' => null, ...])`.

**Dampak**: baris `pola_jam` baru tersimpan dengan `lembaga_id = NULL`. `TenantScope` untuk aktor lembaga-scoped melakukan `where('lembaga_id', $actingUser->lembaga_id)` — karena kolom aktualnya `NULL`, aktor yang BARU SAJA membuat pola jam itu justru **tidak bisa melihat kembali** miliknya sendiri di halaman index. Ini bukan false positif — `tests/Feature/Admin/PolaJamCrudTest.php:34-44` (test `creates a pola jam`) lolos karena hanya mengecek `PolaJam::where('nama', ...)->exists()` (query tanpa scope aktor, jadi tetap true meski `lembaga_id` NULL) — tidak pernah memverifikasi `lembaga_id`-nya benar.

**Referensi pola yang sudah benar** di controller sejenis lain, WAJIB di-mirror: `GuruController::resolveLembagaId()` (`app/Http/Controllers/Admin/GuruController.php:181-188`):
```php
private function resolveLembagaId(Request $request): ?int
{
    if ($request->user()->widestScopeLevel() === 'yayasan') {
        return session('active_lembaga_id');
    }

    return $request->user()->lembaga_id;
}
```

### Temuan 2 — `Guru\RaporController::update()` tidak cross-check semester vs tahun ajaran kelas

Fix sebelumnya (2026-08-28, commit `dd757eb2`) menambahkan `abort_if($semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404)` di 3 method (`edit`, `generateNarasi`, `cetak`) dan `abort_if($semester->tahun_ajaran_id !== $kelas->tahun_ajaran_id, 404)` di `ajukan()`. TAPI method ke-5, `update()` (`app/Http/Controllers/Guru/RaporController.php:156-176`) — method yang justru MENYIMPAN `CatatanWaliKelas` — tidak pernah mendapat guard yang sama:

```php
public function update(Siswa $siswa, StoreCatatanWaliKelasRequest $request): RedirectResponse
{
    $guru = $request->user()->guru;
    abort_if($guru === null, 403);
    abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403);

    $this->simpanCatatanWaliKelasAction->execute(
        CatatanWaliKelasData::fromArray([...$request->validated(), 'siswa_id' => $siswa->id])
    );
    // ...
}
```

`StoreCatatanWaliKelasRequest::rules()` (`app/Http/Requests/Akademik/StoreCatatanWaliKelasRequest.php:25`) hanya `'semester_id' => ['required', 'integer', 'exists:semester,id']` — rule `exists` adalah query DB mentah yang TIDAK menghormati `TenantScope`, apalagi konsistensi tahun ajaran.

**Dampak**: `CatatanWaliKelas` tetap ter-scope `lembaga_id` benar (diturunkan dari `siswa_id` via model event), jadi BUKAN kebocoran baca lintas-tenant. Tapi guru bisa menyimpan catatan wali kelas dengan `semester_id` yang salah tahun ajaran (mis. semester tahun ajaran lain, masih dalam lembaga sendiri) — mencemari flag `catatan_lengkap` di `RaporController::index()` (baris 95-102) dan pengecekan kelengkapan di `SubmitPengajuanRaporAction` (baris 31-33) untuk semester yang SEHARUSNYA dicek, karena data malah tersimpan di `semester_id` yang salah.

## 2. Keputusan Desain

### Fix Temuan 1 — mirror `GuruController::resolveLembagaId()`

```php
public function store(Request $request, CreatePolaJamAction $action): RedirectResponse
{
    $this->authorize('pola-jam.create');

    $data = $request->validate([
        'nama' => ['required', 'string', 'max:255'],
    ]);

    $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
        ? session('active_lembaga_id')
        : $request->user()->lembaga_id;

    if ($lembagaId === null) {
        return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum membuat pola jam.'])->withInput();
    }

    $action->execute(new PolaJamData(nama: $data['nama'], lembagaId: $lembagaId));

    return redirect()->route('admin.pola-jam.index')->with('status', 'Pola jam berhasil dibuat.');
}
```

Perubahan: pesan error yang sama tetap dipertahankan (relevan untuk kedua jenis aktor sekarang — kalau `lembaga_id` user sendiri entah kenapa NULL, pesannya tetap masuk akal karena kasus itu memang seharusnya tidak terjadi untuk aktor lembaga-scoped normal, tapi tetap defensif).

### Fix Temuan 2 — mirror guard existing di 3 method lain

```php
public function update(Siswa $siswa, StoreCatatanWaliKelasRequest $request): RedirectResponse
{
    $guru = $request->user()->guru;
    abort_if($guru === null, 403);
    abort_unless($siswa->kelas && $siswa->kelas->wali_kelas_guru_id === $guru->id, 403);

    $semester = Semester::find($request->validated('semester_id'));
    abort_if($semester === null || $semester->tahun_ajaran_id !== $siswa->kelas->tahun_ajaran_id, 404);

    $this->simpanCatatanWaliKelasAction->execute(
        CatatanWaliKelasData::fromArray([...$request->validated(), 'siswa_id' => $siswa->id])
    );
    // ... sisanya TIDAK BERUBAH
}
```

`Semester` sudah di-import di file ini (dipakai di 4 method lain). Tidak ada import baru.

## 3. Non-Goals (eksplisit di luar scope)

- Tidak mengubah `CreatePolaJamAction`, `AssignKelasToPolaJamAction`, `JamPelajaranController` — sudah dikonfirmasi aman oleh audit.
- Tidak mengubah `StoreCatatanWaliKelasRequest::rules()` — validasi `exists:semester,id` tetap dipertahankan sebagai lapis pertama (semester harus benar-benar eksis), guard baru adalah lapis kedua di controller, pola yang sama seperti 4 method lain di file yang sama.
- Tidak mengubah `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction` (bug fungsional level-yayasan yang ditemukan di putaran audit yang sama) — user secara eksplisit memisahkan ini sebagai item terpisah, bukan bagian dari siklus fix ini.
- Tidak memperbaiki `PolaJam` yang SUDAH TERLANJUR tersimpan dengan `lembaga_id = NULL` di database manapun (tidak ada data migration/backfill) — di luar scope bug-fix kode; kalau ada data production yang sudah terlanjur rusak, itu keputusan terpisah.
- Tidak mengubah skema/migration.

## 4. Testing (acceptance criteria wajib)

**4.1 — Regresi wajib**: seluruh test existing di `tests/Feature/Admin/PolaJamCrudTest.php` dan `tests/Feature/Guru/RaporControllerTest.php` HARUS tetap PASS tanpa modifikasi assertion apa pun.

**4.2 — Bug reproduction Temuan 1**: aktor LEMBAGA BIASA (bukan yayasan, pola `actingAsPolaJamManager()` yang sudah ada di test file) membuat pola jam baru → `PolaJam` yang tersimpan HARUS punya `lembaga_id` sama dengan `lembaga_id` aktor tersebut (BUKAN null). Test harus eksplisit assert `lembaga_id`, bukan cuma `exists()` seperti test lama.

**4.3 — Kasus tidak berubah (regresi negatif Temuan 1)**: aktor yayasan dengan `active_lembaga_id` ter-set tetap membuat pola jam dengan `lembaga_id` sesuai lembaga aktif tersebut (perilaku existing untuk jalur yayasan tidak boleh berubah). Aktor yayasan TANPA `active_lembaga_id` (mode "Semua Lembaga") tetap ditolak dengan pesan error yang sama seperti sebelumnya.

**4.4 — Bug reproduction Temuan 2**: guru wali kelas mengirim `semester_id` yang valid (milik lembaganya sendiri) tapi tahun ajarannya beda dari kelas siswa → `update()` (endpoint simpan catatan wali kelas) HARUS mengembalikan 404, dan TIDAK ADA baris `CatatanWaliKelas` yang tersimpan/berubah.

**4.5 — Kasus tidak berubah (regresi negatif Temuan 2)**: `semester_id` yang valid dan satu tahun ajaran dengan kelas siswa → tetap sukses seperti sebelumnya (test existing `saves catatan wali kelas via update...` dan `redirects to the next siswa...` harus tetap PASS tanpa modifikasi).

## 5. Ringkasan Perubahan File

```text
app/Http/Controllers/Admin/PolaJamController.php   [ubah derivasi $lembagaId di store(), mirror GuruController::resolveLembagaId()]
app/Http/Controllers/Guru/RaporController.php       [+1 abort_if cross-check semester vs tahun ajaran kelas di update()]
tests/Feature/Admin/PolaJamCrudTest.php             [+test reproduksi & regresi Temuan 1, +assert lembaga_id di test existing 'creates a pola jam' kalau relevan]
tests/Feature/Guru/RaporControllerTest.php          [+test reproduksi Temuan 2]
```
