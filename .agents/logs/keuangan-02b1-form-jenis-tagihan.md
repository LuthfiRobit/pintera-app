# Handoff Log — Keuangan Sub-project 2b-1: Form Jenis Tagihan

**Tanggal selesai:** 2026-08-11
**Branch:** `demo` (dikerjakan langsung, tanpa worktree, sesuai instruksi eksplisit user)
**Status:** Semua 7 task selesai + final whole-plan review + 2 ronde fix, semua terverifikasi ulang independen. Belum di-push ke remote.

---

## Apa yang dikerjakan

Implementasi form Jenis Tagihan lengkap sesuai spec di `.agents/specs/keuangan-02b1-form-jenis-tagihan.md` dan plan di `.agents/plans/keuangan-02b1-form-jenis-tagihan.md`. Dikerjakan via `superpowers:subagent-driven-development` — 7 task, masing-masing implementer + task reviewer terpisah.

### 11 commit di `demo` (fd7ef0d..6177ccc):

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
| `bd1cdaa` | fix: address final-review findings (is_active bug, event ordering, x-show leakage, multi-select prefill, persen cap, ppdb nominal guard, kategori-baru auth) |
| `6177ccc` | fix: coerce multi-select value types for id-valued kriteria fields |

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

### 6. Final whole-plan review (Opus) menemukan 4 bug Critical + 5 Important lintas-task, semua diperbaiki dalam 2 ronde fix
Setelah 7 task selesai dan masing-masing lolos review per-task, dispatch review menyeluruh (whole-plan, bukan cuma final whole-BRANCH karena kerja langsung di `demo` tanpa branch terpisah) menemukan bug yang tidak kelihatan dari sudut pandang satu task saja:

- **C1 — `is_active` checkbox tidak pernah bisa dimatikan.** Checkbox unchecked tidak mengirim apa-apa di HTTP; `bisa_dicicil` sudah punya fix `$request->boolean(...)` tapi `is_active` terlewat. Diperbaiki di `store()` dan `update()`.
- **C2 — Mengaktifkan jenis_tagihan (centang "Aktif") sambil mengubah sasaran di request yang sama men-generate tagihan dari sasaran LAMA yang baru saja dihapus**, karena `JenisTagihan::booted()` (dari Sub-project 2a) fire event `BillTypeActivated` secara SINKRON saat `->update($data)` dipanggil — sebelum `syncBillingConfig()` sempat mengganti sasaran. Ini murni blind-spot lintas-task: Task 1's reviewer melihat controller-nya sendiri tapi tidak tahu tentang model hook 2a. Diperbaiki dengan menukar urutan: `syncBillingConfig()` dulu, baru `$jenisTagihan->update($data)`.
- **C3 — `x-show="!kategoriPpdb"` cuma sembunyikan CSS, input tetap ada di DOM dan tetap ikut ter-submit.** Spec eksplisit minta "TIDAK dirender sama sekali... tidak masuk payload submit" — implementasi awal tidak memenuhi ini di 4 tempat (mode block, Sasaran, Tarif, Keringanan). Konsekuensinya lebih parah dari stale value: mengubah kategori dari non-PPDB ke PPDB pada record yang sudah punya sasaran menjadi 422 loop yang tidak bisa di-recover dari UI (karena `old()` re-seed input yang sama). Diperbaiki: keempat wrapper diganti `<template x-if="!kategoriPpdb">`.
- **C4 — Multi-select kehilangan prefill saat edit**, karena Alpine bind `x-model` pada `<select>` sebelum child `<template x-for>`-nya sempat render `<option>`. Fix pertama (`:selected="(kriteria.value ?? []).includes(opt.value)"`) cuma benar untuk field dengan opsi string hardcoded (`jenis_kelamin`/`status_siswa`) — untuk field ber-ID (`lembaga`/`tahun_ajaran`/`kelas`), `kriteria.value` dari DB adalah string (`"3"`) sementara `opt.value` dari `@js($model->id)` adalah integer (`3`), jadi `Array.includes` (strict equality) selalu `false`. Butuh 2 ronde fix: ronde 1 menambah binding `:selected` (tapi salah tipe), ronde 2 (ditemukan oleh RE-REVIEW independen, bukan implementer sendiri) menambah `String()` coercion di kedua sisi perbandingan.
- **I1 — Persen keringanan >100% diterima server-side**, cuma dicegah di client (`:max="100"`). Diperbaiki dengan closure validation rule per-baris.
- **I2 — 4 kategori baru (spp/tahunan/kegiatan/custom) masih bisa akses halaman "Kelola Nominal"** yang seharusnya PPDB-only, karena guard lama cuma cek `=== 'lainnya'` (lengkap waktu cuma ada 3 kategori, jadi tidak lengkap lagi setelah plan ini nambah 4 kategori baru). Diperbaiki jadi allowlist PPDB.
- **I3 — Link "Kelola Nominal" tetap muncul di index untuk semua kategori.** Digate ke kategori PPDB saja.
- **I5 — `KategoriKeringananController` bisa 500 (bukan validasi rapi) untuk user yayasan tanpa lembaga aktif, dan cuma otorisasi `jenis-tagihan.create`** (padahal plan bilang harus terima create ATAU edit). Diperbaiki keduanya.
- **M1/M2/M4** — label kategori regresi ke raw enum value di index (diperbaiki, label map di-restore+extend), default keringanan selalu ke opsi pertama (diperbaiki jadi null+placeholder), array `value.*` tidak divalidasi elemennya (diperbaiki, tambah rule `string|max:255` per elemen).

Fix ronde 1 (`bd1cdaa`) mengaku memperbaiki semua di atas dengan 40/40 test — **RE-REVIEW independen (bukan cuma percaya laporan) membuktikan C1/C2 via revert-and-fail (kode dikembalikan ke versi buggy, test yang sama gagal persis seperti prediksi), tapi menemukan C4 baru separuh diperbaiki** (tipe data string-vs-integer). Fix ronde 2 (`6177ccc`) menutup sisa C4 tsb. Setelah kedua ronde, controller sesi ini menjalankan ulang SEMUA test secara independen (bukan cuma percaya subagent): 41/41 test task-specific, 55/55 suite Keuangan (2a tidak tersentuh), full suite 1398 passed / 6 failed (baseline pre-existing yang sama seperti sebelum plan ini dimulai).

Pelajaran: bug-bug Critical ini SEMUA lolos dari 7 review per-task (yang masing-masing cuma lihat diff satu task), dan baru ketahuan saat ada satu pass yang melihat keseluruhan plan sekaligus dengan trace data-flow controller→view→Alpine penuh. Final whole-plan/whole-branch review bukan formalitas — untuk plan dengan banyak task yang saling terhubung erat (satu file Blade yang di-build bertahap lintas 3 task, satu controller yang disentuh berkali-kali), ini menangkap kelas bug yang task-level review secara struktural tidak bisa lihat.

---

## Hasil Verification (Stage 6/7 + final whole-plan review + 2 ronde fix)

### Test evidence FINAL (dijalankan ulang langsung oleh controller SETELAH kedua ronde fix, bukan cuma percaya laporan subagent):

**Task-specific + fix-round test files — 41 tests, 125 assertions: SEMUA PASS**
- `JenisTagihanFormTest` 5/5, `KategoriKeringananTest` 3/3, `JenisTagihanFormPageTest` 2/2, `JenisTagihanSasaranFormTest` 3/3 (termasuk 1 test baru untuk C4 id-valued field), `JenisTagihanKeringananFormTest` 2/2, `JenisTagihanTest` (regresi PPDB lama) 21/21, `JenisTagihanFinalReviewFixesTest` 5/5 (test baru untuk C1/C2/I1/I2).

**`tests/Feature/Keuangan/` — 55 tests, 130 assertions: SEMUA PASS**
Cocok persis dengan baseline Sub-project 2a — konfirmasi `TagihanBillingGenerator`/`JenisTagihanSasaranMatcher`/`TagihanNominalResolver` (2a, sudah merged) tidak tersentuh sub-project ini.

**Full suite: 1398 passed, 6 failed (4379 assertions)**
Ke-6 failure (`LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest`) adalah baseline pre-existing yang SAMA seperti sebelum Sub-project 2a merge dan sebelum plan ini dimulai — tidak menyentuh kode Keuangan. 1398 vs baseline 1378 = +20 test baru dari plan ini secara keseluruhan (task-level + fix-round).

---

## Hal yang masih perlu direview manusia / Claude

1. **Manual browser verification — MASIH BELUM DILAKUKAN, tetap jadi risiko terbuka (keputusan sadar, bukan kelalaian).** Dicoba ulang secara eksplisit pada 2026-08-11: dicek langsung (ToolSearch, dua kali) dan lewat dispatch subagent terpisah (agent type "claude", diinstruksikan untuk mengecek tool browser sebelum mencoba apa pun) — **tidak ada tool browser/Playwright/Puppeteer/MCP browser_navigate-style yang tersedia di environment ini sama sekali**, baik untuk sesi controller maupun subagent manapun. User diberi pilihan (siapkan checklist manual untuk dijalankan sendiri / pasang Laravel Dusk atau Playwright sebagai dependency baru / skip dulu) dan memilih **skip untuk sekarang**, lanjut ke Sub-project 2b-2 dengan risiko ini tetap tercatat terbuka. Catatan penting: final whole-plan review BERHASIL menemukan dan memperbaiki C3 (x-show leakage) dan C4 (multi-select prefill) TANPA browser — lewat pembacaan kode Alpine yang teliti plus test HTTP-level yang membuktikan payload akhir. Tapi ini bukan pengganti verifikasi visual sungguhan. Checklist yang tetap perlu dicek manual — oleh manusia, di browser lokal — kapan pun nanti dilakukan:
   - Index page: "+ Tambah Jenis Tagihan" mengarah ke halaman create, ikon tombol benar (sudah diperbaiki jadi "add").
   - Create page: pilih kategori "SPP" → section Mode/Sasaran/Tarif/Keringanan muncul (sekarang via `x-if`, benar-benar hilang dari DOM); pilih "Pendaftaran" → section tsb hilang bersih.
   - Tambah 1 grup Sasaran + 1 kriteria ber-ID (mis. field `kelas`), 1 grup Tarif + 1 kriteria + nominal, 1 rule Keringanan pakai "+ Kategori Baru" → submit → redirect ke index, row baru muncul.
   - Edit jenis_tagihan yang baru dibuat tsb → **khususnya cek multi-select untuk field ber-ID (kelas/lembaga/tahun_ajaran) benar-benar ter-checklist di browser**, bukan cuma ada di payload — ini persis bug C4 yang dua ronde fix targetkan, coverage test yang ada tetap tidak bisa mengeksekusi Alpine sungguhan.
   - Toggle "Aktif" pada jenis_tagihan yang sudah punya sasaran, SAMBIL mengubah sasaran di form yang sama, submit → cek `tagihan` yang ter-generate memakai sasaran BARU (behavior C2 fix).
   - "Kelola Nominal" cuma muncul untuk kategori Pendaftaran/Daftar Ulang (behavior I2/I3 fix), tetap jalan untuk kategori tsb.
   - Uncheck "Status Aktif" pada edit, submit → jenis_tagihan benar-benar nonaktif (behavior C1 fix).

2. **Minor findings yang TIDAK diperbaiki (sengaja, tidak blocking):**
   - `newKeringanan()`'s Alpine `x-model.number` mengubah placeholder kosong jadi `0` bukan `null` saat submit tanpa pilih kategori — pesan error jadi "kategori tidak valid" bukan "wajib diisi" yang lebih jelas. Kosmetik, server tetap menolak dengan benar.
   - Radio "Semua Siswa"/"Berdasarkan Kriteria" tidak punya atribut `name` HTML asli (cuma `x-model`) — kehilangan native radio-group grouping untuk keyboard/screen-reader. Aksesibilitas, bukan fungsional.
   - `create()` yang gagal validasi lembaga aktif pakai `back()` di request GET — kalau tidak ada referer, bisa bounce ke `/`. Edge case jarang.
   - `hari_jatuh_tempo` tidak ada batas atas di validasi.
   - `use KategoriKeringananController` di `routes/admin.php` memutus urutan alfabetis import satu baris. Kosmetik murni.
   - Tidak ada automated test yang mengeksekusi JS Alpine sungguhan (Feature test Laravel cuma cek HTML/payload, bukan browser). Project ini memang belum punya tooling test JS/Dusk sama sekali — gap sistemik, bukan spesifik ke plan ini, tapi inilah yang membuat C4 butuh 2 ronde untuk benar-benar tuntas (ronde 1 lolos test HTTP tapi masih buggy di browser sungguhan).

3. **Drag-reorder prioritas Tarif Berdimensi** — di luar scope 2b-1 sejak awal (dicatat di spec), urutan = urutan grup dibuat pertama kali.

4. **Sub-project 2b-2 (tombol "Proses Tagihan") dan 2b-3 (dashboard monitoring)** — belum dikerjakan, plan belum ditulis.

5. **Git state:** Semua 11 commit ada di `demo` langsung (tidak ada branch/worktree terpisah, sesuai instruksi user), HEAD di `6177ccc`. **Belum di-push ke remote.** Final whole-plan review (Opus, mencakup seluruh range `fd7ef0d..bdb89b1`) SUDAH didispatch dan menemukan 4 Critical + 5 Important — semua diperbaiki dalam 2 ronde fix, masing-masing ronde di-re-review independen (bukan cuma percaya laporan implementer). Ini setara dengan final whole-branch review yang biasanya jadi syarat sebelum `finishing-a-development-branch`, hanya saja dilakukan langsung di `demo` karena tidak ada branch terpisah untuk di-merge.
