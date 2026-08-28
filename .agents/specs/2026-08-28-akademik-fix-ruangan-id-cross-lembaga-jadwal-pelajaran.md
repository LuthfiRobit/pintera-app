# Fix: `ruangan_id` Lolos Tanpa Cross-Check Lembaga pada Jadwal Pelajaran — Design Spec

**Tanggal**: 2026-08-28
**Branch**: `akademik-v2`
**Konteks**: Temuan dari audit mendalam lanjutan (area JadwalPelajaran CRUD), setelah audit sebelumnya menutup celah serupa pada `KurikulumAssignmentController` (tahun_ajaran vs lembaga) dan `UpdateKomponenPenilaianAction` (lembaga_id vs semester).

---

## 1. Latar Belakang & Masalah

`JadwalPelajaranController::store()` dan `::update()` (`app/Http/Controllers/Admin/JadwalPelajaranController.php`) memvalidasi 3 dari 4 field relasional terhadap `$kelas->lembaga_id` sebelum menyimpan:

- `mata_pelajaran_id` — dicek eksplisit (baris 188-199 store, 331-340 update).
- `guru_id` — dicek eksplisit (baris 201-207 store, 323-329 update).
- `semester_id` — dicek eksplisit (baris 209-215 store; pada update, `semester_id` diambil dari `$jadwalPelajaran->semester_id` sendiri sehingga tidak bisa diganti user, aman by construction).
- `ruangan_id` — **TIDAK dicek sama sekali** (baris 217 store, 342 update):
  ```php
  $ruanganId = ! empty($data['ruangan_id']) ? (int) $data['ruangan_id'] : $kelas->ruangan_id;
  ```
  Nilai ini langsung dipakai membentuk `JadwalPelajaranData` dan disimpan, tanpa pernah dibandingkan ke `lembaga_id` ruangan tersebut.

Validasi request (`StoreJadwalPelajaranRequest`/`UpdateJadwalPelajaranRequest`) hanya `'ruangan_id' => 'nullable|integer'` — sekadar memastikan berupa angka, bukan memastikan kepemilikan lembaga.

Dropdown ruangan di form (`edit()` baris 288-293, dan pembentukan `ruanganList` yang setara di `create()`) memang sudah difilter `lembaga_id = target OR is_shared = true` — tapi ini kontrol client-side/tampilan saja. POST langsung (DevTools, replay request, curl) dengan `ruangan_id` milik lembaga lain tidak ditolak oleh server.

**Dampak**: baris `jadwal_pelajaran` bisa berakhir dengan `kelas_id`/`guru_id` milik lembaga A tapi `ruangan_id` milik lembaga B — pelanggaran tenant-isolation di level tulis data. Pesan error dari `ValidateRoomClashAction` ("Ruangan yang dipilih sudah digunakan...", dilempar di `CreateJadwalPelajaranAction.php:33-36` / `UpdateJadwalPelajaranAction.php:34-37`) juga berpotensi disalahgunakan untuk enumerasi ID ruangan lembaga lain (menyimpulkan ID mana yang eksis dan sedang terpakai dari respons error), meski tampilan balik data ruangan itu sendiri tetap tertutup `TenantScope`.

**Reachability**: admin lembaga BIASA (lembaga-scoped, tanpa privilege khusus) — beda dari 2 bug sebelumnya yang hanya reachable oleh aktor yayasan mode "Semua Lembaga". Cukup mengganti `ruangan_id` di payload sebelum submit.

## 2. Keputusan Desain

**Fix minimal, mirror pola cross-check guru/mata_pelajaran yang sudah ada di controller**: tambahkan 1 blok pengecekan eksplisit sebelum baris pembentukan `$ruanganId`, di KEDUA method (`store()` dan `update()`).

**Aturan validitas ruangan** (analog ke pola dropdown existing): ruangan valid untuk suatu kelas jika `ruangan.lembaga_id === kelas.lembaga_id` ATAU `ruangan.is_shared === true`. Karena `Ruangan` model pakai `BelongsToTenant` (`TenantScope` aktif), query pencarian ruangan untuk keperluan validasi ini HARUS eksplisit `withoutGlobalScope(TenantScope::class)` — kalau tidak, untuk aktor lembaga-scoped biasa, `Ruangan::find()` terhadap ruangan lembaga lain akan otomatis `null` (menutup celah secara tidak sengaja untuk aktor itu), TAPI untuk aktor yayasan mode "Semua Lembaga" bisa saja `find()` sukses menembus—membuat perilaku validasi tidak konsisten antar jenis aktor jika tidak eksplisit bypass scope dulu lalu bandingkan manual. Pola ini sama seperti fix `UpdateKomponenPenilaianAction` sebelumnya: gunakan `withoutGlobalScope` + perbandingan manual, jangan mengandalkan efek samping TenantScope.

```php
// store(), tepat sebelum baris pembentukan $ruanganId (baris 217 lama)
if (! empty($data['ruangan_id'])) {
    $ruangan = Ruangan::withoutGlobalScope(TenantScope::class)->find((int) $data['ruangan_id']);
    if (! $ruangan || (! $ruangan->is_shared && $ruangan->lembaga_id !== $kelas->lembaga_id)) {
        $msg = 'Ruangan harus berasal dari lembaga yang sama dengan kelas ini, atau berupa ruangan bersama.';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['status' => 'error', 'message' => $msg, 'errors' => ['ruangan_id' => [$msg]]], 422);
        }
        return back()->withErrors(['ruangan_id' => $msg])->withInput();
    }
}

$ruanganId = ! empty($data['ruangan_id']) ? (int) $data['ruangan_id'] : $kelas->ruangan_id;
```

Blok yang sama (dengan return format `update()`, yang sudah ada persis) ditambahkan di `update()` tepat sebelum baris 342 lama.

**Catatan penting — `$kelas->ruangan_id` (default/fallback saat `ruangan_id` tidak dikirim) TIDAK divalidasi ulang**: nilai itu adalah `ruangan_id` bawaan milik `Kelas` itu sendiri (data yang sudah ada, bukan input baru dari request ini), sehingga di luar scope fix ini — kalau `Kelas.ruangan_id` sendiri sudah salah lembaga, itu bug terpisah di alur pembuatan/update `Kelas`, bukan di `JadwalPelajaranController`.

**Import yang diperlukan**: `Ruangan` (`App\Domains\Sarpras\Models\Ruangan`) sudah di-import di controller (baris 11 existing). `TenantScope` (`App\Models\Scopes\TenantScope`) sudah di-import (baris 21 existing, dipakai di `edit()`). Tidak ada import baru yang diperlukan.

## 3. Non-Goals (eksplisit di luar scope)

- Tidak mengubah `$kelas->ruangan_id` fallback logic — asumsi `Kelas.ruangan_id` selalu valid milik lembaganya sendiri (tidak diverifikasi ulang di sini).
- Tidak mengubah `ValidateRoomClashAction` (bentrok jadwal ruangan) — perilaku itu independen dan sudah benar untuk apa yang dicek (bentrok slot waktu, bukan ownership lembaga).
- Tidak mengubah dropdown/filter UI di `create()`/`edit()` — sudah benar sebagai kontrol UX, fix ini murni menambah lapis validasi server-side yang hilang.
- Tidak menyamarkan/mengubah pesan error `ValidateRoomClashAction` terkait potensi enumerasi ID — di luar scope bug-fix murni ini (kalau mau ditangani, itu keputusan produk terpisah tentang granularitas pesan error).
- Tidak ada perubahan skema/migration.

## 4. Testing (acceptance criteria wajib)

**4.1 — Regresi wajib**: seluruh test existing terkait `JadwalPelajaranController` (cari file test yang relevan, kemungkinan `tests/Feature/Admin/JadwalPelajaranControllerTest.php` atau nama serupa) HARUS tetap PASS tanpa modifikasi assertion — termasuk skenario yang memakai `ruangan_id` valid (milik lembaga sama) dan skenario tanpa `ruangan_id` sama sekali (fallback ke `$kelas->ruangan_id`).

**4.2 — Bug reproduction (test baru)**:
- `store()`: admin lembaga A (lembaga-scoped biasa, TIDAK perlu aktor yayasan — bug ini reachable oleh aktor lembaga biasa) mengirim `ruangan_id` milik Lembaga B (bukan `is_shared`) saat membuat jadwal untuk kelas Lembaga A → SEBELUM fix: tersimpan (bug). SESUDAH fix: ditolak dengan `assertSessionHasErrors('ruangan_id')` (atau `422` + `errors.ruangan_id` untuk request AJAX), dan pastikan TIDAK ada baris `JadwalPelajaran` baru yang tercipta.
- `update()`: skenario yang sama tapi untuk update jadwal existing — ganti `ruangan_id` jadwal yang sudah ada ke ruangan Lembaga B → ditolak, dan `ruangan_id` jadwal tetap nilai lama (`fresh()` dibandingkan).
- Ruangan dengan `is_shared = true` milik lembaga LAIN → HARUS tetap diterima (bukan ditolak) baik di `store()` maupun `update()`, karena `is_shared` adalah pengecualian yang sah.

**4.3 — Kasus tidak berubah (regresi negatif)**: `ruangan_id` milik lembaga yang SAMA dengan kelas → tetap sukses seperti sebelumnya (tidak ada regresi pada jalur normal).

## 5. Ringkasan Perubahan File

```text
app/Http/Controllers/Admin/JadwalPelajaranController.php   [+cross-check ruangan_id vs kelas->lembaga_id di store() dan update()]
tests/Feature/Admin/JadwalPelajaranCrudTest.php             [+test baru untuk reproduksi bug + regresi is_shared]
```

File test dikonfirmasi: `tests/Feature/Admin/JadwalPelajaranCrudTest.php` (bukan `JadwalPelajaranControllerTest.php`). Plan akan membaca helper actor yang sudah ada di file ini terlebih dahulu sebelum menambahkan test baru, mengikuti pola actor yang konsisten dengan test existing di file tersebut.
