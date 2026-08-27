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

**Scope ketat**: HANYA menambah 1 pengecekan konsistensi/otorisasi data. TIDAK mengubah `KurikulumAssignmentResolver`, TIDAK mengubah semantik "default nasional", TIDAK mengubah `UpdateKurikulumAssignmentRequest` (update tidak mengizinkan ganti `tahun_ajaran_id`/`lembaga_id` sama sekali — sudah aman, dikonfirmasi di audit sebelumnya).

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
        $tahunAjaran = TahunAjaran::find($validated['tahun_ajaran_id']);
        if ($tahunAjaran === null || (int) $tahunAjaran->lembaga_id !== (int) $lembagaId) {
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

## 4. Testing (acceptance criteria wajib)

1. `lembaga_id` efektif terisi + `tahun_ajaran_id` milik lembaga yang SAMA → sukses tersimpan (regresi negatif, kombinasi valid tidak boleh ditolak).
2. `lembaga_id` efektif terisi + `tahun_ajaran_id` milik lembaga LAIN → ditolak, `assertSessionHasErrors(['tahun_ajaran_id'])`, tidak ada baris `kurikulum_assignment` baru tersimpan.
3. `lembaga_id` efektif NULL (platform/yayasan membuat default nasional, sengaja tidak isi `lembaga_id`) + `tahun_ajaran_id` dari lembaga mana pun → tetap sukses seperti perilaku existing (regresi negatif, memastikan fix ini tidak diam-diam mengetatkan kasus yang sengaja dibiarkan longgar).

## 5. Ringkasan Perubahan File

```text
app/Http/Controllers/Admin/KurikulumAssignmentController.php   [+cek konsistensi tahun_ajaran_id vs lembaga_id di store()]
tests/Feature/Akademik/KurikulumAssignmentControllerTest.php   [+3 test, file existing]
```
