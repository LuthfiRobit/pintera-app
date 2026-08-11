# Handoff Log — Keuangan Sub-project 2b-1: Form Jenis Tagihan

**Tanggal selesai:** 2026-08-11
**Branch:** `demo` (dikerjakan langsung, tanpa worktree, sesuai instruksi eksplisit user)
**Status:** Semua 7 task selesai, review bersih (2 fix round kecil). Belum di-push ke remote.

---

## Apa yang dikerjakan

Implementasi form Jenis Tagihan lengkap sesuai spec di `.agents/specs/keuangan-02b1-form-jenis-tagihan.md` dan plan di `.agents/plans/keuangan-02b1-form-jenis-tagihan.md`. Dikerjakan via `superpowers:subagent-driven-development` — 7 task, masing-masing implementer + task reviewer terpisah.

### 9 commit di `demo` (fd7ef0d..bdb89b1):

| SHA | Deskripsi |
|-----|-----------|
| `6de3160` | feat: extend jenis-tagihan controller with mode/sasaran/tarif/keringanan (Task 1) |
| `9ea52b3` | feat: add inline kategori-keringanan creation endpoint (Task 2) |
| `c6cc712` | feat: add jenis-tagihan form page skeleton with informasi dasar section (Task 3) |
| `038ec6f` | feat: add target sasaran and tarif berdimensi sections (Task 4) |
| `6b77859` | fix: resolve this-binding bug in jenisTagihanForm hydrateGrup (Task 4 fix round) |
| `ff17cdd` | feat: add keringanan section with inline kategori-baru creation (Task 5) |
| `122a65c` | feat: wire jenis-tagihan index to the new create/edit pages (Task 6) |
| `bdb89b1` | fix: use correct icon name in jenis-tagihan tambah button (Task 6 fix round) |

### File baru:
- `app/Http/Controllers/Admin/KategoriKeringananController.php`
- `resources/views/admin/jenis-tagihan/form.blade.php`
- 5 file test baru di `tests/Feature/Admin/`

### File dimodifikasi:
- `app/Http/Controllers/Admin/JenisTagihanController.php` — `create()`/`edit()` baru, `store()`/`update()` diperluas dengan validasi kategori-bercabang + replace-all persistence
- `routes/admin.php` — route `jenis-tagihan.create`/`.edit` + `kategori-keringanan.store`
- `resources/views/admin/jenis-tagihan/index.blade.php` — form inline dihapus, link ke halaman create/edit
- `resources/js/jenis-tagihan-table.js` — `startEdit`/`cancelEdit`/`submit` dihapus (148→39 baris)

---

## Keputusan penting yang diambil

### 1. Form bercabang berdasarkan kategori (dikonfirmasi dengan user sebelum spec ditulis)
Kategori `pendaftaran`/`daftar_ulang` tetap pakai mekanisme nominal-per-jalur lama (tidak disentuh). Kategori lain (`lainnya`/`spp`/`tahunan`/`kegiatan`/`custom`) dapat form penuh mode+sasaran+tarif+keringanan. Validasi server-side menolak (422) payload `sasaran`/`tarif`/`keringanan` untuk kategori PPDB — bukan cuma disembunyikan di UI.

### 2. Halaman terpisah + plain form POST, bukan modal/AJAX (dikonfirmasi dengan user)
Mengikuti precedent yang sudah ada di codebase ini sendiri (`admin/jenis-tagihan/nominal.blade.php` — halaman dedicated pakai `<form method="POST">` biasa, bukan fetch/JSON). Alpine hanya mengatur reaktivitas UI (repeatable groups), semua field pakai `:name` dinamis yang di-parse Laravel sebagai nested array biasa. Validasi gagal pakai `back()->withErrors()->withInput()` standar, bukan custom JS error handling.

### 3. Replace-all-on-save aman (diverifikasi sebelum spec ditulis)
Dikonfirmasi langsung dari migration schema: tidak ada FK dari `billing_job_logs`/`tagihan` ke `jenis_tagihan_sasaran_grup`/`_kriteria`/`jenis_tagihan_keringanan`. Test eksplisit (Task 1) membuktikan mengedit sasaran pada `jenis_tagihan` yang SUDAH pernah digenerate billing-nya tidak merusak `tagihan` yang sudah ada.

### 4. Bug `this`-binding di Alpine factory (ditemukan saat review Task 4, diperbaiki)
Kode asli plan (Task 4) punya pola `sasaran: config.initialSasaran.map((g) => this.hydrateGrup(g))` di dalam object literal yang sedang dikonstruksi — `this` tidak resolve ke object tersebut karena `jenisTagihanForm()` dipanggil sebagai plain function (bukan `new`). Ini akan throw `TypeError` di halaman edit begitu ada `jenis_tagihan` dengan sasaran/tarif tersimpan, meng-abort seluruh Alpine component (bukan cuma section sasaran/tarif — SELURUH form termasuk mode toggle dan submit). Diperbaiki dengan memindah `hydrateGrup` jadi local closure. Task 5 secara eksplisit dicek ulang untuk pola bug yang sama — tidak ditemukan.

### 5. Icon `name="plus"` tidak ada, harus `name="add"` (ditemukan saat review Task 6, diperbaiki)
`resources/views/components/icon.blade.php` cuma punya case `add`, bukan `plus`. Silent fallback ke default icon (placeholder "?") di tombol "Tambah Jenis Tagihan". Diperbaiki jadi `name="add"`, konsisten dengan 10+ tombol "+" lain di codebase.

---

## Hasil Verification (Stage 6/7)

### Test evidence (dijalankan ulang langsung oleh controller, bukan cuma percaya laporan subagent):

**Task-specific test files — 35 tests, 99 assertions: SEMUA PASS**
- `JenisTagihanFormTest` 5/5, `KategoriKeringananTest` 3/3, `JenisTagihanFormPageTest` 2/2, `JenisTagihanSasaranFormTest` 2/2, `JenisTagihanKeringananFormTest` 2/2, `JenisTagihanTest` (regresi PPDB lama) 21/21.

**`tests/Feature/Keuangan/` — 55 tests, 130 assertions: SEMUA PASS**
Cocok persis dengan baseline Sub-project 2a — konfirmasi `TagihanBillingGenerator`/`JenisTagihanSasaranMatcher`/`TagihanNominalResolver` (2a, sudah merged) tidak tersentuh sub-project ini.

**Full suite: 1392 passed, 6 failed (4353 assertions)**
Ke-6 failure (`LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest`) adalah baseline pre-existing yang SAMA seperti yang dikonfirmasi tidak berhubungan sebelum Sub-project 2a merge — tidak menyentuh kode Keuangan. 1392 vs baseline 1378 sebelumnya = +14 test baru dari plan ini (35 baru - beberapa overlap dengan yang sudah ada di hitungan Keuangan/JenisTagihanTest).

---

## Hal yang masih perlu direview manusia / Claude

1. **Manual browser verification (brief Task 6 Step 4) — BELUM DILAKUKAN.** Tidak ada subagent maupun controller session ini yang punya akses browser/Playwright. Item checklist yang perlu dicek manual oleh manusia sebelum benar-benar dianggap selesai:
   - Index page: "+ Tambah Jenis Tagihan" mengarah ke halaman create.
   - Create page: pilih kategori "SPP" → section Mode/Sasaran/Tarif/Keringanan muncul; pilih "Pendaftaran" → section tsb hilang.
   - Tambah 1 grup Sasaran + 1 kriteria, 1 grup Tarif + 1 kriteria + nominal, 1 rule Keringanan pakai "+ Kategori Baru" → submit → redirect ke index, row baru muncul.
   - Edit jenis_tagihan yang baru dibuat tsb → semua 3 section ter-prefill sesuai yang disimpan (ini bagian yang paling berisiko — sudah lolos test HTML-rendering tapi belum pernah dieksekusi JS-nya di browser sungguhan).
   - "Kelola Nominal" dan "Hapus" masih jalan untuk row kategori Pendaftaran (regresi).

2. **Minor findings yang TIDAK diperbaiki (sengaja, tidak blocking):**
   - `newKeringanan()` selalu default ke kategori keringanan pertama di list — bisa bikin validasi unique constraint gagal kalau user tambah 2 baris tanpa ganti kategori baris kedua. UX friction kecil, bukan bug data.
   - `use KategoriKeringananController` di `routes/admin.php` memutus urutan alfabetis import satu baris. Kosmetik murni.
   - Tidak ada automated test yang menjaga class-bug `this`-binding di Alpine (Feature test Laravel tidak mengeksekusi JS browser). Project ini memang belum punya tooling test JS/Dusk sama sekali — gap sistemik, bukan spesifik ke task ini.

3. **Drag-reorder prioritas Tarif Berdimensi** — di luar scope 2b-1 sejak awal (dicatat di spec), urutan = urutan grup dibuat pertama kali.

4. **Sub-project 2b-2 (tombol "Proses Tagihan") dan 2b-3 (dashboard monitoring)** — belum dikerjakan, plan belum ditulis.

5. **Git state:** Semua 9 commit ada di `demo` langsung (tidak ada branch/worktree terpisah, sesuai instruksi user). **Belum di-push ke remote.** Tidak ada final whole-branch code review terpisah yang didispatch untuk plan ini — task-level review (7 task, 2 fix round) dianggap memadai mengingat besarnya scope; kalau user mau, whole-branch review terpisah bisa didispatch sebelum push.
