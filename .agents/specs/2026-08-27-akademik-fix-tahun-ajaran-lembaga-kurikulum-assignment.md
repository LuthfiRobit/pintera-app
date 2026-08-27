# Fix Data Master: Konsistensi `tahun_ajaran_id` vs `lembaga_id` pada Kurikulum Assignment — Design Spec

**Tanggal**: 2026-08-27
**Branch**: `akademik-v2`
**Konteks**: Temuan dari audit ulang total layer Data Master. `StoreKurikulumAssignmentRequest` memvalidasi `tahun_ajaran_id` hanya dengan `exists:tahun_ajaran,id` — tidak mengecek kepemilikan lembaga. Bukan celah lewat UI normal (dropdown tahun ajaran di form create sudah difilter ke lembaga aktor), murni celah defense-in-depth via POST manual.

---

## 1. Latar Belakang & Masalah

`KurikulumAssignmentController::store()` (`app/Http/Controllers/Admin/KurikulumAssignmentController.php:58-83`) menghitung `$lembagaId` efektif:
```php
$lembagaId = $isPlatformOrYayasan ? ($validated['lembaga_id'] ?? null) : $request->user()->lembaga_id;
```
Untuk admin lembaga biasa (non-platform/yayasan), `lembaga_id` dari request **selalu diabaikan**, dipaksa ke lembaga milik aktor sendiri — jadi tidak ada celah spoofing `lembaga_id`. Tapi `$validated['tahun_ajaran_id']` (dari `StoreKurikulumAssignmentRequest::rules()`, hanya `exists:tahun_ajaran,id`) **tidak pernah dicocokkan** dengan `$lembagaId` itu. Lewat POST manual (bukan lewat UI, karena dropdown form sudah difilter benar), aktor bisa mengirim `tahun_ajaran_id` milik lembaga lain, menghasilkan baris `kurikulum_assignment` dengan `lembaga_id=A` tapi `tahun_ajaran_id` milik lembaga B — data cross-tenant tidak konsisten.

**Skema relevan**: `kurikulum_assignment.lembaga_id` NULLABLE (mendukung baris "default nasional" lintas-lembaga, sengaja tidak terikat 1 lembaga). `kurikulum_assignment.tahun_ajaran_id` NOT NULL, selalu FK ke satu `tahun_ajaran` yang dimiliki tepat satu lembaga.

## 2. Keputusan Desain

**Invariant berbasis nilai `lembaga_id` yang akan tersimpan** (bukan jenis aktor):

- **`lembaga_id` efektif TERISI** (baik dipaksa controller ke lembaga aktor non-platform, MAUPUN dipilih eksplisit oleh platform/yayasan) → `tahun_ajaran_id` WAJIB menunjuk `tahun_ajaran` dengan `lembaga_id` yang SAMA. Beda → tolak.
- **`lembaga_id` efektif NULL** (kasus "default nasional", keputusan disengaja mendukung lintas-lembaga) → TIDAK ADA validasi ownership tambahan, perilaku existing dipertahankan apa adanya.

**Scope ketat**: HANYA menambah satu validasi konsistensi ownership antara `kurikulum_assignment.lembaga_id` dan `tahun_ajaran.lembaga_id` — bukan perubahan otorisasi aktor (otorisasi siapa-boleh-apa tetap sepenuhnya ditangani `authorize()`/`authorizeAssignmentScope()` yang sudah ada, tidak disentuh). TIDAK mengubah `KurikulumAssignmentResolver`, TIDAK mengubah semantik "default nasional", TIDAK mengubah `UpdateKurikulumAssignmentRequest` (update tidak mengizinkan ganti `tahun_ajaran_id`/`lembaga_id` sama sekali — sudah aman, dikonfirmasi di audit sebelumnya).

**Implementasi** — ditambahkan di `KurikulumAssignmentController::store()`, konsisten dengan gaya inline-validation yang SUDAH ADA di method yang sama (cek duplikat assignment via `back()->withErrors(...)->withInput()`), BUKAN di `StoreKurikulumAssignmentRequest` — karena nilai `$lembagaId` efektif hanya bisa dihitung setelah logic `isPlatformOrYayasan()` di controller, memindahkannya ke FormRequest akan menduplikasi logic tsb:

```php
public function store(StoreKurikulumAssignmentRequest $request, CreateKurikulumAssignmentAction $action): RedirectResponse
{
    $this->authorize('kurikulum-assignment.create');

    $validated = $request->validated();
    $tingkat = ($validated['tingkat'] ?? '') !== '' ? $validated['tingkat'] : null;

    $isPlatformOrYayasan = $this->isPlatformOrYayasan($request);
    $lembagaId = $isPlatformOrYayasan ? ($validated['lembaga_id'] ?? null) : $request->user()->lembaga_id;

    $this->authorizeAssignmentScope($request, $lembagaId);

    if ($lembagaId !== null) {
        $tahunAjaranValid = TahunAjaran::whereKey($validated['tahun_ajaran_id'])
            ->where('lembaga_id', $lembagaId)
            ->exists();

        if (! $tahunAjaranValid) {
            return back()->withErrors(['tahun_ajaran_id' => 'Tahun ajaran yang dipilih bukan milik lembaga ini.'])->withInput();
        }
    }

    if (KurikulumAssignment::where('lembaga_id', $lembagaId)->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])->where('bentuk_pendidikan', $validated['bentuk_pendidikan'])->where('tingkat', $tingkat)->exists()) {
        return back()->withErrors(['bentuk_pendidikan' => 'Sudah ada assignment kurikulum untuk kombinasi tahun ajaran, jenjang, dan tingkat ini. Edit baris yang ada, jangan buat duplikat.'])->withInput();
    }

    // ... sisanya TIDAK BERUBAH (action->execute + redirect)
}
```

`App\Models\TahunAjaran` sudah di-import di file ini (dipakai `tahunAjaranListForScope()`) — tidak perlu import baru.

## 3. Non-Goals (eksplisit di luar scope)

- Tidak mengubah `KurikulumAssignmentResolver::resolve()` atau semantik "default nasional" (`lembaga_id = null`) sama sekali.
- Tidak mengubah `UpdateKurikulumAssignmentRequest`/`KurikulumAssignmentController::update()` — sudah dikonfirmasi aman (tidak mengizinkan ganti `tahun_ajaran_id`).
- Tidak menyelidiki/memperbaiki potensi bug fungsional "default nasional tidak benar-benar lintas lembaga" yang disinggung saat diskusi (`KurikulumAssignmentResolver` mencocokkan `tahun_ajaran_id` eksak, bukan periode) — dicatat sbg technical debt terpisah, BUKAN bagian fix ini.
- Tidak mengubah skema, tidak ada migration.
- **Race condition pada cek duplikat existing** (`if (KurikulumAssignment::where(...)->exists())`, baris terpisah dari fix ini) — dua request bersamaan bisa sama-sama lolos `exists()` sebelum salah satunya insert, menghasilkan duplikat. Diketahui dan disadari, TAPI eksplisit di luar scope fix ini (tidak terkait dengan bug ownership yang sedang diperbaiki) — dicatat sbg technical debt terpisah.

## 4. Testing (acceptance criteria wajib)

Karena keputusan desain digeneralisasi berbasis nilai `lembaga_id` (bukan jenis aktor), matrix test WAJIB mencakup baik aktor admin lembaga maupun platform/yayasan — supaya implementasi tidak diam-diam jadi role-specific meski desainnya sudah digeneralisasi:

| # | Aktor | `lembaga_id` efektif | Tahun ajaran milik | Ekspektasi |
|---|---|---|---|---|
| 1 | Admin lembaga A | A (dipaksa controller) | Lembaga A | ✅ sukses tersimpan |
| 2 | Admin lembaga A | A (dipaksa controller) | Lembaga B | ❌ ditolak |
| 3 | Platform/yayasan | A (dipilih eksplisit di form) | Lembaga A | ✅ sukses tersimpan |
| 4 | Platform/yayasan | A (dipilih eksplisit di form) | Lembaga B | ❌ ditolak |
| 5 | Platform/yayasan | NULL (default nasional, sengaja tidak isi `lembaga_id`) | Lembaga mana pun | Tidak ditolak KARENA ownership — lihat catatan di bawah |

**Baris 1 (Admin lembaga A + tahun ajaran A) SUDAH punya test existing** (`it('creates a kurikulum assignment', ...)` di `KurikulumAssignmentControllerTest.php` baris 28-41) — cukup pastikan tetap PASS, tidak perlu test baru untuk baris ini.

**Baris 5 — catatan penting soal klaim test**: fix ini menambah SATU pengecekan (`if ($lembagaId !== null) { ... }`) yang secara desain SAMA SEKALI TIDAK DIEKSEKUSI ketika `$lembagaId` null — jadi tidak mungkin ada penolakan yang BERASAL dari validasi baru ini untuk kasus tsb. Test untuk baris 5 TIDAK BOLEH mengklaim "tetap sukses seperti sebelumnya" secara umum (itu overclaim — bisa saja ada rule LAIN yang sudah ada sebelumnya yang menolak request untuk alasan tak terkait, di luar kendali fix ini). Klaim yang benar dan sempit: **"request tidak ditolak karena ownership mismatch tahun_ajaran_id"** — dibuktikan dengan memastikan TIDAK ADA pesan error pada field `tahun_ajaran_id` di response (`assertSessionDoesntHaveErrors(['tahun_ajaran_id'])`), bukan `assertRedirect()`/klaim sukses total. Kalau ternyata request itu sukses total juga (redirect ke index), itu bonus temuan, bukan yang divalidasi test.

## 5. Ringkasan Perubahan File

```text
app/Http/Controllers/Admin/KurikulumAssignmentController.php   [+cek konsistensi tahun_ajaran_id vs lembaga_id di store()]
tests/Feature/Akademik/KurikulumAssignmentControllerTest.php   [+3 test, file existing]
```
