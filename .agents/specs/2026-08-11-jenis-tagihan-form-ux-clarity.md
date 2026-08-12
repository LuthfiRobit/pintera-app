# Jenis Tagihan Form — UX Clarity Pass

> Status: Disetujui — siap ke Implementation Plan.

## Konteks & Dependensi

Bergantung pada `resources/views/admin/jenis-tagihan/form.blade.php` dan `resources/js/jenis-tagihan-form.js` sebagaimana ada setelah rework "gold standard" (`.agents/logs/2026-08-11-jenis-tagihan-ui-ux.md`, komit `d562cee`..`9383f72`), dan `JenisTagihanController::referenceData()` (`app/Http/Controllers/Admin/JenisTagihanController.php:335`).

**Peringatan proses eksplisit dari user:** rework sebelumnya menandai dirinya selesai tanpa pernah menjalankan `php artisan test` (cuma `npm run build`), menyebabkan 3 test jadi basi tanpa ketahuan sampai diaudit ulang terpisah (lihat koreksi di log yang sama). Plan turunan spec ini **WAJIB** menyertakan langkah `php artisan test` yang eksplisit untuk file test terkait `admin/jenis-tagihan` di setiap task verifikasi — bukan cuma build check.

## Tujuan

Form Jenis Tagihan (create & edit) sudah fungsional tapi kurang menjelaskan konsekuensi dari pilihan admin — enam celah kejelasan UX ditemukan lewat audit terpisah, semua murni presentasi (label/microcopy/query kolom tambahan untuk display), TIDAK menyentuh logic backend (validasi, generator tagihan, matcher) atau data finansial apa pun.

## 6 Temuan & Perbaikan

### 1. Opsi Kelas di TomSelect ambigu (tidak ada info tahun ajaran)

**Sekarang:** `referenceOptions.kelas` di-generate dari `$kelasList->map(fn ($k) => ['value' => $k->id, 'label' => $k->nama])` — cuma nama kelas (mis. "7A"). Kalau ada kelas bernama sama di tahun ajaran berbeda, admin tidak bisa membedakan.

**Perbaikan:**
- `JenisTagihanController::referenceData()`: query `kelasList` diperluas dengan eager-load relasi `tahunAjaran` (`Kelas::where('lembaga_id', $lembagaId)->with('tahunAjaran')->orderBy('nama')->get(['id', 'nama', 'tahun_ajaran_id'])` — tambah kolom `tahun_ajaran_id` ke select supaya eager-load bisa resolve, `tahunAjaran` sendiri diambil lewat relasi bukan kolom tambahan di tabel `kelas`).
- Blade: `referenceOptions.kelas` di `x-data` config diformat ulang jadi `$kelasList->map(fn ($k) => ['value' => $k->id, 'label' => $k->nama.' ('.$k->tahunAjaran->nama.')'])` — hasil label mis. **"7A (2026/2027)"**.
- Ini murni perubahan SELECT tambahan kolom untuk kebutuhan tampilan (bukan perubahan logic bisnis apa pun) — `kelas_id` yang disimpan ke `jenis_tagihan_sasaran_kriteria.value` tetap `$k->id` seperti sebelumnya, tidak berubah.

### 2. Field Mode Otomatis tidak dijelaskan

**Sekarang:** 4 field muncul saat `form.mode === 'otomatis'` (Tanggal Mulai, Tanggal Selesai, Tanggal Generate, Hari Jatuh Tempo) — cuma label, tidak ada penjelasan.

**Perbaikan:** tambah `<p class="mt-1 text-[10px] text-gray-400 leading-tight">` di bawah TIAP field:
- Tanggal Mulai: *"Tanggal jenis tagihan ini mulai aktif digenerate otomatis."*
- Tanggal Selesai: *"Kosongkan jika tidak ada batas akhir."*
- Tanggal Generate: *"Tanggal setiap bulan saat tagihan otomatis dibuat (mis. isi 1 untuk tanggal 1 tiap bulan)."*
- Hari Jatuh Tempo: *"Jumlah hari setelah tanggal generate sampai batas waktu pembayaran."*

### 3. Field kriteria menampilkan raw key, bukan label manusia

**Sekarang:** `<option :value="fieldOpt" x-text="fieldOpt">` — merender `jenis_kelamin`, `tahun_ajaran`, `status_siswa`, `lembaga`, `tingkat`, `kelas` apa adanya.

**Perbaikan:** tambah objek `fieldLabels` di `jenisTagihanForm()` (Alpine factory, `resources/js/jenis-tagihan-form.js`):
```js
fieldLabels: {
    lembaga: 'Lembaga', tahun_ajaran: 'Tahun Ajaran', tingkat: 'Tingkat',
    kelas: 'Kelas', jenis_kelamin: 'Jenis Kelamin', status_siswa: 'Status Siswa',
},
```
Ganti KEDUA `x-text="fieldOpt"` (Sasaran `form.blade.php:184`, Tarif `:237`) jadi `x-text="fieldLabels[fieldOpt] ?? fieldOpt"`. `:value="fieldOpt"` (raw key yang dikirim ke server) TIDAK berubah — cuma teks yang ditampilkan.

### 4. Logika DAN/ATAU antar-kriteria tidak dijelaskan

**Sekarang:** admin tidak tahu bahwa kriteria dalam satu Grup = DAN, sementara antar-Grup = ATAU (perilaku asli `JenisTagihanSasaranMatcher`, tidak berubah).

**Perbaikan:** tambah 1 baris teks di tiap card Grup (Sasaran `form.blade.php:173-204`, Tarif `:223-257`), diletakkan tepat sebelum tombol "+ Tambah Kriteria":
```blade
<p class="text-[10px] text-gray-400 leading-tight">Semua kriteria di atas harus terpenuhi bersamaan (DAN).</p>
```
Dan 1 baris di bawah tombol "+ Tambah Grup Sasaran/Tarif Baru" (di luar `x-for`, sekali per section):
```blade
<p class="text-[10px] text-gray-400 leading-tight">Setiap Grup adalah alternatif terpisah — siswa cukup cocok salah satu (ATAU).</p>
```

### 5. Urutan prioritas Tarif Berdimensi tidak dijelaskan

**Sekarang:** badge nomor bulat (①②③) di tiap card Grup Tarif ada tapi maknanya (urutan prioritas evaluasi) tidak dijelaskan.

**Perbaikan:** tambah 1 baris caption di bawah header "Tarif Berdimensi" (`form.blade.php:217`, sebelum `<div class="space-y-4 pt-1">`):
```blade
<p class="text-[10px] text-gray-400 leading-tight">Diproses berurutan dari atas — Grup pertama yang cocok dengan siswa akan dipakai nominalnya.</p>
```

### 6. Unit "Nilai Potongan" Keringanan tidak jelas (Rp vs %)

**Sekarang:** placeholder statis `"Nilai Potongan"` terlepas dari `tipe_potongan` yang dipilih.

**Perbaikan:** `form.blade.php:284` — ganti `placeholder="Nilai Potongan"` jadi binding reaktif:
```blade
:placeholder="rule.tipe_potongan === 'persen' ? 'Contoh: 20 (%)' : 'Contoh: 50000 (Rupiah)'"
```

## Yang TIDAK Termasuk

- Tidak ada perubahan ke `JenisTagihanController::store()`/`update()`/validasi — murni presentasi.
- Tidak ada perubahan ke `TagihanBillingGenerator`/`JenisTagihanSasaranMatcher`/urutan evaluasi Tarif yang sesungguhnya — cuma menjelaskan perilaku yang SUDAH ADA, bukan mengubahnya.
- Tidak menyentuh index.blade.php atau halaman lain — scope murni `form.blade.php` + `jenis-tagihan-form.js` + satu query di controller.

## Testing (WAJIB, per instruksi eksplisit user)

Setiap task di plan turunan spec ini harus menyertakan langkah `php artisan test` untuk file test yang relevan (minimal `tests/Feature/Admin/JenisTagihanFormPageTest.php`, `JenisTagihanSasaranFormTest.php`, `JenisTagihanKeringananFormTest.php`, dan `JenisTagihanFormTest.php` — jalankan foreground, satu perintah, sesuai konvensi proyek ini soal shared-test-DB). Task terakhir plan WAJIB menjalankan full suite `php artisan test` (bukan cuma `npm run build`) sebagai bukti tidak ada regresi — bukan asumsi "seharusnya aman" seperti rework sebelumnya.
