# Fix: Waka Kurikulum Level Yayasan Tidak Bisa Approve/Verify Rapor — Design Spec

**Tanggal**: 2026-08-28
**Branch**: `akademik-v2`
**Konteks**: Temuan #3 dari putaran audit ke-2 (workflow approval PengajuanRapor), sebelumnya sengaja dipisah dari siklus fix PolaJam/CatatanWaliKelas karena butuh keputusan desain dari user.

---

## 1. Latar Belakang & Masalah

`ApprovePengajuanRaporAction::execute()` (`app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php:28`) dan `VerifyPengajuanRaporAction::execute()` (`app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php:28`) sama-sama punya guard:

```php
if ((int) $pengajuanRapor->lembaga_id !== (int) $user->lembaga_id) {
    throw ValidationException::withMessages([...]);
}
```

Untuk aktor **level yayasan**, `$user->lembaga_id` selalu `NULL` by design (dia tidak terikat 1 lembaga tetap, memilih "lembaga aktif" lewat pengalih lembaga yang disimpan di `session('active_lembaga_id')`). Perbandingan `(int) $pengajuanRapor->lembaga_id !== (int) null` (mis. `5 !== 0`) SELALU `true` — exception SELALU dilempar, apapun lembaga aktif yang sudah dipilih. Akibatnya waka kurikulum level yayasan **tidak pernah bisa approve/verify rapor apa pun**, meski `PengajuanRapor` route-model-binding sudah benar dibatasi oleh `TenantScope` ke lembaga aktif yang dipilihnya.

**Keputusan desain yang sudah dikonfirmasi user**: aktor yayasan BOLEH approve/verify rapor, TAPI HANYA jika sudah memilih 1 lembaga aktif via pengalih lembaga (bukan mode "Semua Lembaga"). Ini konsisten dengan pola existing di codebase untuk situasi serupa — lihat `PolaJamController::store()` (baru saja diperbaiki di siklus sebelumnya), `KalenderAkademikController::store()`, `MataPelajaranController`, `PengaturanAkademikController::index()` — semuanya menolak dengan pesan eksplisit "pilih lembaga aktif dulu" ketika aktor yayasan belum memilih lembaga aktif.

## 2. Keputusan Desain

Ganti guard di KEDUA action (`ApprovePengajuanRaporAction`, `VerifyPengajuanRaporAction`) dengan pola yang sudah established di codebase ini (`GuruController::resolveLembagaId()` / `PolaJamController::store()`):

```php
public function execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor
{
    $effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
        ? session('active_lembaga_id')
        : $user->lembaga_id;

    if ($effectiveLembagaId === null) {
        throw ValidationException::withMessages([
            'approval' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum memproses pengajuan rapor.',
        ]);
    }

    if ((int) $pengajuanRapor->lembaga_id !== (int) $effectiveLembagaId) {
        throw ValidationException::withMessages([
            'approval' => 'Anda tidak berwenang menyetujui pengajuan rapor lembaga lain.', // atau pesan "memverifikasi" untuk VerifyPengajuanRaporAction
        ]);
    }

    // ... sisanya TIDAK BERUBAH
}
```

**Efek untuk masing-masing jenis aktor**:
- Aktor lembaga-scoped biasa (`widestScopeLevel() !== 'yayasan'`): `$effectiveLembagaId = $user->lembaga_id` (selalu terisi untuk jenis aktor ini) — perilaku TIDAK BERUBAH sama sekali dari sebelumnya.
- Aktor yayasan dengan lembaga aktif dipilih: `$effectiveLembagaId = session('active_lembaga_id')` — sekarang bisa lolos guard kalau `$pengajuanRapor` memang milik lembaga aktif itu (dan `TenantScope` sudah menjamin route-model-binding hanya bisa resolve `PengajuanRapor` dari lembaga aktif itu juga, jadi guard ini pada dasarnya defense-in-depth, bukan satu-satunya lapis).
- Aktor yayasan TANPA lembaga aktif (mode "Semua Lembaga"): ditolak dengan pesan baru yang jelas ("Pilih lembaga aktif..."), BUKAN pesan lama yang generik ("tidak berwenang..." — itu sekarang direservasi untuk kasus mismatch lembaga yang sesungguhnya).

`User` model sudah punya method `widestScopeLevel()` (dipakai identik di `PolaJamController`), tidak perlu import baru — `use App\Models\User;` sudah ada di kedua file action.

## 3. Non-Goals (eksplisit di luar scope)

- Tidak mengubah `PersetujuanController` — controller tidak perlu tahu soal derivasi `$effectiveLembagaId`, itu tanggung jawab action.
- Tidak mengubah `ProcessApprovalAction`/workflow engine generik (`app/Domains/Workflow/Actions/ProcessApprovalAction.php`) — di luar scope, tidak terkait bug ini.
- Tidak menambah UI/indikator baru yang menampilkan "lembaga aktif" di halaman persetujuan rapor — cukup pesan error teks yang sudah ada.
- Tidak mengubah perilaku untuk aktor lembaga-scoped biasa sama sekali (harus 100% tidak berubah, buktikan lewat regresi test).

## 4. Testing (acceptance criteria wajib)

**4.1 — Regresi wajib**: seluruh test existing terkait approval rapor (kemungkinan `tests/Feature/Akademik/RaporApprovalActionsTest.php` dan/atau `tests/Feature/Akademik/RaporApprovalTenantScopeTest.php` dan/atau `tests/Feature/Rapor/RaporPersetujuanControllerTest.php` — dikonfirmasi ulang saat plan) HARUS tetap PASS tanpa modifikasi assertion apa pun, termasuk skenario aktor lembaga-scoped biasa approve/verify sukses.

**4.2 — Bug reproduction (test baru)**: aktor yayasan dengan `session('active_lembaga_id')` ter-set ke lembaga yang benar (sama dengan `$pengajuanRapor->lembaga_id`) HARUS BISA approve/verify sukses — SEBELUM fix ini gagal (exception "tidak berwenang" meski lembaga aktifnya benar), SESUDAH fix berhasil.

**4.3 — Kasus tidak berubah / regresi negatif**:
- Aktor yayasan TANPA `active_lembaga_id` (mode "Semua Lembaga") → tetap DITOLAK, tapi dengan pesan BARU ("Pilih lembaga aktif...") bukan pesan lama.
- Aktor yayasan dengan `active_lembaga_id` ter-set ke lembaga YANG BEDA dari `$pengajuanRapor->lembaga_id` (kalau reachable — cek dulu apakah `TenantScope` route-model-binding memang bisa membuat kombinasi ini terjadi, atau otomatis 404 duluan) → tetap DITOLAK dengan pesan "tidak berwenang" seperti biasa.
- Aktor lembaga-scoped biasa approve/verify pengajuan rapor miliknya sendiri → tetap SUKSES seperti sebelumnya (test existing).
- Aktor lembaga-scoped biasa mencoba approve/verify pengajuan rapor lembaga lain (skenario ini kemungkinan sudah 404 duluan via TenantScope, cek test existing yang relevan) → perilaku tidak berubah.

## 5. Ringkasan Perubahan File

```text
app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php   [ganti guard lembaga_id, mirror pola resolveLembagaId()]
app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php    [ganti guard lembaga_id, mirror pola resolveLembagaId()]
tests/... (file test approval rapor, dikonfirmasi saat plan)         [+test reproduksi & regresi untuk aktor yayasan]
```
