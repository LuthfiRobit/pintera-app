# Modul Keuangan — Sub-project 2b-1: Form Jenis Tagihan (Mode + Sasaran + Tarif + Keringanan)

> Status: Disetujui — siap ke Implementation Plan.

## Konteks & Dependensi

Bergantung pada Sub-project 1 (skema: `jenis_tagihan_sasaran_grup`+`kriteria`, `jenis_tagihan_keringanan`+`kategori_keringanan`, `siswa_keringanan`, `nominal_tagihan_siswa`, field mode/tanggal_mulai/tanggal_selesai/tanggal_generate/hari_jatuh_tempo/default_amount di `jenis_tagihan`) dan Sub-project 2a (`TagihanBillingGenerator`, `JenisTagihanSasaranMatcher`, `TagihanNominalResolver` — sudah live, konsumen dari data yang dikelola form ini). Ini adalah bagian pertama dari 3 sub-plan Sub-project 2b (2b-1 form, 2b-2 tombol Proses Tagihan, 2b-3 dashboard monitoring) — dipecah karena masing-masing independen dan bisa diverifikasi sendiri.

`docs/superpowers/specs/2026-08-10-keuangan-02-billing-engine-admin-design.md` §"UI Admin — Form Jenis Tagihan" adalah spec asli sub-project 2 (sebelum dipecah 2a/2b); dokumen ini adalah versi detail & final untuk 2b-1 saja, dan menggantikan bagian tersebut sebagai sumber kebenaran untuk form.

## Tujuan

Admin (`admin_keuangan` / role dengan permission `jenis-tagihan.*`) bisa membuat dan mengedit `jenis_tagihan` dengan dua jalur form yang berbeda tergantung kategori:

- **Kategori `pendaftaran`/`daftar_ulang`**: form SAMA seperti sekarang (nama, kategori, bisa_dicicil, maks_cicilan) — mekanisme nominal tetap lewat halaman "Kelola Nominal" per jalur PPDB yang sudah ada, TIDAK disentuh sub-project ini.
- **Kategori lain (`lainnya`/`spp`/`tahunan`/`kegiatan`/`custom`)**: form penuh dengan 4 section (Informasi Dasar+Mode, Target Sasaran, Tarif Berdimensi, Keringanan) yang menyusun data untuk `TagihanBillingGenerator`.

## Verifikasi Keamanan Data (dicek sebelum spec ini ditulis)

Dikonfirmasi langsung dari migration schema: **tidak ada FK dari `billing_job_logs` atau `tagihan` ke `jenis_tagihan_sasaran_grup`/`_kriteria`/`jenis_tagihan_keringanan`**. `tagihan.discount_amount`/`discount_type` adalah snapshot value murni (computed saat generate, disimpan sebagai decimal/string, bukan FK). Nominal dari tarif grup juga di-bake ke `tagihan.total_tagihan`/`net_amount` saat generate, tidak ada referensi balik. **Konsekuensi:** strategi "replace-all-on-save" (hapus semua row sasaran/tarif/keringanan milik `jenis_tagihan` ini lalu buat ulang dari payload form setiap kali disimpan) aman — tidak bisa merusak `tagihan` atau `billing_job_logs` yang sudah ada, termasuk untuk `jenis_tagihan` yang sudah pernah digenerate.

## Arsitektur

Halaman terpisah (bukan modal/inline), route baru:
- `GET admin/jenis-tagihan/create` → `JenisTagihanController::create()`
- `POST admin/jenis-tagihan` → `JenisTagihanController::store()` (existing route, handler diperluas)
- `GET admin/jenis-tagihan/{jenisTagihan}/edit` → `JenisTagihanController::edit()`
- `PUT admin/jenis-tagihan/{jenisTagihan}` → `JenisTagihanController::update()` (existing route, handler diperluas)

Halaman index (`admin/jenis-tagihan`) disederhanakan: form inline card yang sekarang ada DIHAPUS, tombol "+ Tambah Jenis Tagihan" mengarah ke `create()`, baris tabel "Edit" mengarah ke `edit()` alih-alih `startEdit()` inline. Tabel/delete/filter tetap AJAX seperti sekarang (tidak diubah, tidak perlu ditulis ulang jadi SPA pattern penuh — di luar scope).

View baru: `resources/views/admin/jenis-tagihan/form.blade.php` (dipakai oleh `create` dan `edit`, menerima `$jenisTagihan` nullable + semua data referensi). Alpine state di-seed dari `@json($jenisTagihan?->load(['sasaranGrup.kriteria', 'keringananRules.kategoriKeringanan'])->toArray() ?? null)` agar form edit ter-populate.

## Section 1: Informasi Dasar

Field selalu tampil: `nama`, `kategori` (select: Pendaftaran, Daftar Ulang, Lainnya, SPP, Tahunan, Kegiatan, Custom), `bisa_dicicil` + `maks_cicilan` (checkbox+conditional, existing behavior unchanged), toggle `is_active`.

**Kalau `kategori` BUKAN `pendaftaran`/`daftar_ulang`** (reaktif via Alpine `x-show`, computed dari `form.kategori`), section ini menambah:
- `default_amount` (number, nominal fallback kalau tidak ada tarif grup yang match)
- Toggle `mode` (Manual / Otomatis, default `manual`)
- **Kalau `mode === 'otomatis'`**: `tanggal_mulai` (date), `tanggal_selesai` (date, nullable — "Tanpa batas akhir" checkbox untuk null), `tanggal_generate` (number 1-31, hari dalam bulan), `hari_jatuh_tempo` (number, nullable, hari setelah tanggal_generate untuk jatuh tempo)

**Kalau `kategori` ADALAH `pendaftaran`/`daftar_ulang`**: section 2/3/4 di bawah (Target Sasaran, Tarif Berdimensi, Keringanan) TIDAK dirender sama sekali (bukan cuma disembunyikan CSS — tidak ada di DOM, dan tidak masuk payload submit).

## Section 2: Target Sasaran (hanya untuk kategori non-PPDB)

Radio: "Semua Siswa" (default, tidak ada grup) vs "Berdasarkan Kriteria".

Kalau "Berdasarkan Kriteria": list card "Sasaran #N", masing-masing = satu `jenis_tagihan_sasaran_grup(tipe='sasaran')`. Tiap card berisi list baris kriteria (field-row builder, lihat di bawah), tombol "+ Tambah Kriteria" per card, tombol "+ Tambah Sasaran" untuk card baru (OR antar card), tombol hapus per card.

### Field-row builder (dipakai Section 2 dan 3)

Tiap baris kriteria: `field` (select), `operator` (select: "Termasuk" = in / "Tidak Termasuk" = not_in), `value` (multi-select, opsi tergantung `field` yang dipilih):

| `field` | Opsi `value` (sumber) |
|---|---|
| `lembaga` | Daftar `Lembaga` (nama) — untuk konteks yayasan multi-lembaga |
| `tahun_ajaran` | Daftar `TahunAjaran` milik lembaga (nama) |
| `tingkat` | Nilai unik `Kelas.tingkat` milik lembaga (string, mis. "7","8","9" atau "A","B") — `SELECT DISTINCT tingkat FROM kelas WHERE lembaga_id = ?` |
| `kelas` | Daftar `Kelas` milik lembaga (nama), difilter tahun ajaran aktif |
| `jenis_kelamin` | `[['L','Laki-laki'],['P','Perempuan']]` |
| `status_siswa` | `StatusSiswa` enum cases: `aktif`,`lulus`,`pindah`,`keluar` |

Semua field pakai `Rule::in()` daftar valid di atas, bukan cuma dropdown UI — payload `field` harus salah satu dari 6 nilai ini, `operator` harus `in`/`not_in`, `value` array tidak boleh kosong.

## Section 3: Tarif Berdimensi (opsional, kategori non-PPDB)

Struktur identik Section 2 (field-row builder sama), plus satu field tambahan per card: **Nominal** (number, required). Tiap card = satu `jenis_tagihan_sasaran_grup(tipe='tarif')`. Urutan card menentukan prioritas match (card pertama yang match dipakai — `TagihanNominalResolver` sudah pakai `orderBy('id')`, jadi urutan simpan = urutan `id` = urutan card saat pertama dibuat; drag-reorder TIDAK termasuk scope ini).

## Section 4: Keringanan (opsional, kategori non-PPDB)

List rule, tiap baris: `kategori_keringanan_id` (select dari `KategoriKeringanan` milik lembaga, dengan tombol "+ Kategori Baru" yang buka mini-form inline nama+keterangan → AJAX POST ke `kategori-keringanan` → hasilnya langsung masuk opsi select), `tipe_potongan` (select: Nominal Tetap = fixed / Persentase = persen), `nilai` (number — Rupiah kalau fixed, 0-100 kalau persen, validasi `max:100` reaktif saat `tipe_potongan===persen`), `keterangan` (text, nullable).

Constraint DB yang harus dihormati: `unique(jenis_tagihan_id, kategori_keringanan_id)` — satu jenis_tagihan tidak boleh punya 2 rule untuk kategori_keringanan yang sama. Validasi backend harus reject duplikat dalam satu payload sebelum insert (bukan mengandalkan DB constraint error mentah).

## Backend: Validasi & Penyimpanan

**Store/update flow:**
1. Validasi field Section 1 seperti sekarang, plus field baru (`default_amount`, `mode`, `tanggal_mulai`, dst — semua `nullable`/`required_if:mode,otomatis` sesuai toggle).
2. **Kalau `kategori` in `['pendaftaran','daftar_ulang']`**: request TIDAK BOLEH mengandung key `sasaran`, `tarif`, atau `keringanan` sama sekali — kalau ada, tolak dengan 422 (`"Target sasaran, tarif berdimensi, dan keringanan hanya berlaku untuk kategori selain Pendaftaran/Daftar Ulang."`). Ini validasi eksplisit di controller, BUKAN cuma mengandalkan form tidak mengirim field itu — server tidak boleh percaya UI.
3. **Kalau kategori lain**: validasi `sasaran`/`tarif` sebagai array of `{kriteria: [{field, operator, value[]}]}` (tarif tambah `nominal`), `keringanan` sebagai array of `{kategori_keringanan_id, tipe_potongan, nilai, keterangan}` dengan cek duplikat `kategori_keringanan_id` dalam payload.
4. Simpan `jenis_tagihan` (create/update seperti sekarang).
5. **Replace-all**: `$jenisTagihan->sasaranGrup()->delete()` (cascade ke `kriteria` lewat FK `cascadeOnDelete` — cek migration untuk konfirmasi), lalu buat ulang dari payload `sasaran`+`tarif` (tipe dibedakan). `$jenisTagihan->keringananRules()->delete()`, buat ulang dari payload `keringanan`. Semua dalam satu `DB::transaction()`.

## Testing

- Feature test: create/update kategori PPDB tetap jalan seperti sekarang (regression, tidak berubah).
- Feature test: create/update kategori non-PPDB dengan sasaran+tarif+keringanan lengkap → assert row-row child table sesuai payload.
- Feature test: kirim payload `sasaran` untuk kategori `pendaftaran` → assert 422, assert TIDAK ada row `jenis_tagihan_sasaran_grup` dibuat.
- Feature test: edit `jenis_tagihan` yang SUDAH punya `tagihan` ter-generate (dari 2a) dengan sasaran berbeda → assert `tagihan` lama tetap utuh (nominal/discount tidak berubah), assert sasaran baru menggantikan yang lama sepenuhnya.
- Feature test: kirim 2 rule keringanan dengan `kategori_keringanan_id` sama dalam satu payload → assert 422.
- Feature test: `KategoriKeringananController@store` (AJAX inline-create) → assert kategori baru muncul, scoped ke lembaga yang benar.

## Yang TIDAK Termasuk 2b-1

- Tombol "Proses Tagihan" dan hasil ringkasnya (→ 2b-2).
- Dashboard monitoring/penerima/tunggakan (→ 2b-3).
- Drag-reorder prioritas Tarif Berdimensi (dicatat sebagai keterbatasan, urutan = urutan dibuat).
- `va_expire_hours` (dipakai sub-project 4/BRI, tidak ada UI di sini).
