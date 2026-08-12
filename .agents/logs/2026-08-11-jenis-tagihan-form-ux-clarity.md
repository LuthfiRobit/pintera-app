# Handoff Log: Jenis Tagihan Form — UX Clarity Pass

**Referensi:**
- Spec: `.agents/specs/2026-08-11-jenis-tagihan-form-ux-clarity.md`
- Plan: `.agents/plans/2026-08-11-jenis-tagihan-form-ux-clarity.md`

**Status:** 6 dari 6 task implementasi selesai + diverifikasi. Task 7 (regresi penuh + log ini) dijalankan langsung oleh controller (bukan subagent) atas instruksi eksplisit user, TANPA Step 3 (full suite `php artisan test`) untuk menghemat token — lihat "Yang TIDAK Dijalankan" di bawah.

---

## Apa yang Dikerjakan

Enam celah kejelasan UX di form Jenis Tagihan diperbaiki — semua murni presentasi (label/microcopy/satu kolom query tambahan untuk display), tidak ada logic backend yang berubah:

1. **Kelas TomSelect ambigu** (`abebffb`) — label opsi kelas sekarang menyertakan tahun ajaran, mis. "7A (2026/2027)", lewat eager-load `Kelas::tahunAjaran()` di `JenisTagihanController::referenceData()`.
2. **Field Mode Otomatis tidak dijelaskan** (`36eb7ac`) — 4 helper text ditambahkan di bawah Tanggal Mulai/Selesai/Generate/Hari Jatuh Tempo.
3. **Field kriteria menampilkan raw key** (`4a862e1`) — `fieldLabels` lookup ditambahkan ke `jenisTagihanForm()` (Alpine), `x-text` di kedua select field kriteria (Sasaran + Tarif) diganti dari raw key ke label manusia.
4. **Logika DAN/ATAU tidak dijelaskan** (`d67c780`) — 1 baris "DAN" per Grup card, 1 baris "ATAU" per section (Sasaran + Tarif, total 4 insersi).
5. **Urutan prioritas Tarif Berdimensi tidak dijelaskan** (`0afed95`) — caption di bawah header "Tarif Berdimensi".
6. **Unit Nilai Potongan Keringanan ambigu** (`c684910`) — placeholder reaktif Rp vs % sesuai `tipe_potongan`.

Semua 6 task dieksekusi via `subagent-driven-development`: implementer TDD per task, task reviewer terpisah, semua review bersih (0 findings) KECUALI Task 1.

### Fix Round — Task 1 (bug nyata, bukan false alarm)

Task 1 awalnya di-approve, tapi saat Task 3 dikerjakan, implementernya menemukan test lain (`JenisTagihanSasaranFormTest`'s "exposes both...") gagal dan menandainya "pre-existing, tidak terkait". **Controller TIDAK menerima klaim itu begitu saja** — diverifikasi ulang secara independen dan terbukti SALAH: ini adalah regresi NYATA dari Task 1 sendiri, bukan sesuatu yang sudah rusak sebelumnya.

**Root cause:** `App\Models\TahunAjaran` pakai `BelongsToTenant`; `TenantScope`-nya memfilter berdasarkan `lembaga_id` USER YANG SEDANG LOGIN, bukan tenant milik objek yang di-query. Saat `Kelas::with('tahunAjaran')` (perubahan Task 1) di-eager-load, relasi itu ikut ter-filter oleh TenantScope — kalau `Kelas` milik lembaga admin yang login TAPI `tahun_ajaran_id`-nya menunjuk ke `TahunAjaran` milik lembaga LAIN, relasi resolve jadi `null` dan halaman crash ("Attempt to read property 'nama' on null"). Ini bukan cuma soal data test yang berantakan — bisa terjadi di data produksi nyata mana pun tahun_ajaran sebuah kelas kebetulan tidak selaras lembaga viewer.

**Fix** (`793e912`, disempurnakan `a68198e`):
- `TenantScope` di-bypass KHUSUS pada closure eager-load `tahunAjaran` (query luar `Kelas::where('lembaga_id', $lembagaId)` tetap utuh — batas tenant yang sah tidak disentuh).
- Fallback null-safe `?->` + `'-'` ditambahkan di Blade sebagai defense-in-depth.
- Ditemukan JUGA bug kedua saat memperbaiki ini: helper test `$asEmbeddedJs` di `JenisTagihanSasaranFormTest` cuma `json_encode()` satu lapis, tidak meniru double-escaping `@js()` Blade untuk garis miring (`/`) — baru ketahuan karena label baru mengandung "/". Diperbaiki dengan delegasi ke `Illuminate\Support\Js::from()`.
- Review ulang (Opus): 5/5 spec compliance, Approved dengan 1 Minor (test regresi cuma assert `assertOk()`, tidak assert NILAI yang benar — jadi tidak membuktikan fix controller-nya benar-benar berfungsi, bukan cuma fallback Blade yang menyembunyikan masalah). Diperbaiki (`a68198e`): tambah assert `viewData('kelasList')->first()->tahunAjaran?->nama === '2026/2027'`.

**Pelajaran eksplisit:** jangan pernah menerima diagnosis "pre-existing, tidak terkait" dari subagent tanpa verifikasi independen — kali ini itu keliru dua kali lipat (bukan pre-existing, DAN terkait langsung).

---

## Keputusan Penting

- Semua helper text pakai style yang SUDAH ADA di file ini sebelum plan ini (`text-[10px] text-gray-400 leading-tight`) — tidak ada gaya visual baru diperkenalkan.
- `:value="fieldOpt"` (raw key yang dikirim ke server) TIDAK PERNAH diubah di Task 3 — hanya `x-text` (label tampilan) yang diganti. Validasi backend tetap menerima raw key seperti sebelumnya.
- TenantScope bypass di fix Task 1 di-scope SESEMPIT mungkin (hanya closure eager-load, bukan query `Kelas` luar) — prinsip yang sama dengan bypass TenantScope lain yang sudah ada di codebase ini (mis. `JenisTagihanSasaranMatcher`).

---

## Hasil Verification (Stage 6/7)

**Build frontend:**
```
npm run build
✓ 144 modules transformed, built in 2.31s — tidak ada error.
```

**4 file test yang disentuh plan ini — 19 tests, 57 assertions: SEMUA PASS**
```
JenisTagihanFormPageTest: 5/5
JenisTagihanSasaranFormTest: 6/6
JenisTagihanKeringananFormTest: 3/3
JenisTagihanFormTest: 5/5
Tests: 19 passed (57 assertions)
```
Dijalankan langsung oleh controller (bukan dipercaya dari laporan subagent), foreground, satu perintah — bukti nyata, bukan asumsi "seharusnya aman" seperti rework sebelumnya.

## Yang TIDAK Dijalankan

**Step 3 plan (full suite `php artisan test` tanpa filter) SENGAJA DILEWATI** atas instruksi eksplisit user ("jalankan semua step di task 7 tanpa step 3 karena akan memakan banyak token"). Ini BUKAN pengulangan kesalahan rework sebelumnya (yang lupa/skip verifikasi sama sekali tanpa sepengetahuan siapa pun) — ini adalah keputusan sadar, terdokumentasi, dan diminta langsung oleh user setelah 4 file test yang relevan sudah dikonfirmasi hijau. Baseline full-suite terakhir yang diketahui (dari `.agents/logs/keuangan-audit-fixes-01-04.md`, sebelum plan ini): **6 failure pre-existing, semua tidak terkait Keuangan/jenis-tagihan** (`LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest`).

**Rekomendasi untuk siapa pun yang lanjut kerja di modul ini:** jalankan full suite sebelum push/merge ke `main`, untuk konfirmasi akhir tidak ada regresi tak terduga di luar 4 file yang sudah dicek di sini — perubahan plan ini murni Blade/JS/satu query controller jadi risiko regresi ke modul lain sangat rendah, tapi belum 100% dibuktikan lewat full-suite run.

## Hal yang Masih Perlu Direview

1. **Full suite belum dijalankan** (lihat di atas) — item terbuka satu-satunya dari plan ini sendiri.
2. **Manual browser verification tidak dilakukan** — konsisten dengan keterbatasan sistemik yang sudah dicatat berkali-kali di log-log lain (`keuangan-02b1`, `keuangan-02b2`, `keuangan-02b3`, `2026-08-11-jenis-tagihan-ui-ux.md`): tidak ada tool browser di sesi mana pun sepanjang proyek ini. Semua 6 fix di plan ini murni teks/microcopy statis (tidak ada logic Alpine baru selain reaktivitas placeholder yang sudah ada polanya di file yang sama), jadi risiko bug render-time yang tidak terdeteksi test HTTP relatif rendah dibanding rework besar sebelumnya — tapi tetap belum diverifikasi visual.
3. **Git state:** Semua 9 commit (`abebffb`..`c684910`, termasuk fix round Task 1) ada di `demo` langsung. **Belum di-push ke remote.**
