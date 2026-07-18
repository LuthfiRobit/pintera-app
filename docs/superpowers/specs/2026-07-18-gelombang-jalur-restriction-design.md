# Pembatasan Jalur per Gelombang PPDB — Design Spec

**Tanggal:** 2026-07-18
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Masalah

`gelombang_ppdb` dan `jalur_ppdb` saat ini adalah dua dimensi independen — keduanya hanya terikat ke `tahun_ajaran_id`, tidak ada relasi langsung satu sama lain. `jalur_ppdb.status_aktif` adalah flag per jalur per tahun ajaran, bukan per kombinasi gelombang.

Akibatnya, di alur publik (`app/Http/Controllers/Spmb/PortalController.php:27-30`), begitu calon murid mendarat di sebuah gelombang yang sedang buka, mereka ditawari **semua** jalur yang `status_aktif = true` di tahun ajaran itu — tanpa peduli gelombang mana yang terpilih:

```php
$jalurList = JalurPpdb::where('tahun_ajaran_id', $gelombang->tahun_ajaran_id)
    ->where('status_aktif', true)
    ->orderBy('nama')
    ->get();
```

Ini jadi masalah kalau sebuah lembaga ingin, misalnya, "Gelombang 1 cuma buka jalur Reguler & Prestasi, Gelombang 2 baru tambah jalur Afirmasi" — skenario yang realistis terjadi di lapangan (PRD menyebutkan jalur seperti reguler/prestasi/afirmasi dengan kebijakan berbeda per lembaga). Sistem saat ini tidak punya cara mengekspresikan itu.

`seleksi_ppdb` (tabel jalur × gelombang yang sudah ada) **bukan** solusinya — itu cuma mengonfigurasi tes opsional (jenis tes, jadwal, kriteria) untuk kombinasi yang sudah dianggap valid, bukan gate ketersediaan.

## 2. Lingkup

**Termasuk:**
- Tabel pivot baru untuk merepresentasikan pembatasan jalur→gelombang.
- Perubahan query di `PortalController::index()` (alur publik SPMB) supaya menghormati pembatasan itu.
- UI di form Gelombang PPDB (create & edit) untuk mengatur pembatasan.
- Indikator status pembatasan di halaman index Gelombang PPDB.
- Validasi backend (cegah assign jalur lintas tenant/tahun ajaran).

**Tidak termasuk (didorong ke plan terpisah, sesuai arahan "selesaikan ini dulu"):**
- Perubahan di form Jalur PPDB (tidak menampilkan daftar gelombang dari sisi jalur — search dilakukan lewat sisi gelombang saja, sesuai keputusan lokasi konfigurasi).
- Perubahan di halaman Verifikasi & Keputusan (Pendaftaran admin) — halaman itu murni menampilkan histori jalur×gelombang yang sudah dipilih calon murid, tidak terpengaruh oleh konfigurasi pembatasan yang berlaku SEKARANG.
- Perubahan di `seleksi_ppdb` (tes opsional) — tetap independen, tidak digabung dengan mekanisme pembatasan ini.
- Redesign visual form Gelombang PPDB lainnya (sudah selesai di commit `dcffb9a`/`4666617`) — spec ini cuma menambah satu bagian baru ke form yang sudah ada.

## 3. Model Data

Tabel pivot baru **`gelombang_jalur`**, murni penghubung tanpa kolom tambahan:

```php
Schema::create('gelombang_jalur', function (Blueprint $table) {
    $table->foreignId('gelombang_ppdb_id')->constrained('gelombang_ppdb')->cascadeOnDelete();
    $table->foreignId('jalur_ppdb_id')->constrained('jalur_ppdb')->cascadeOnDelete();
    $table->primary(['gelombang_ppdb_id', 'jalur_ppdb_id']);
});
```

**Aturan intinya: nol baris di pivot untuk sebuah gelombang = tidak dibatasi (semua jalur aktif tersedia, perilaku default/lama). Satu atau lebih baris = dibatasi hanya ke jalur yang tercantum.** Tidak ada kolom "is_restricted" terpisah — keberadaan baris itu sendiri yang jadi penanda, supaya tidak ada dua sumber kebenaran yang bisa tidak sinkron.

Relasi baru:
- `GelombangPpdb::jalur(): BelongsToMany` (via `gelombang_jalur`)
- `JalurPpdb::gelombang(): BelongsToMany` (via `gelombang_jalur`)

Tidak perlu kolom `lembaga_id` di pivot — kedua sisi relasi sudah tenant-scoped lewat `tahun_ajaran_id` masing-masing, dan UI hanya pernah menawarkan jalur dari tahun ajaran yang sama dengan gelombang yang sedang diedit (dijaga di validasi, bagian 5).

## 4. Query Publik (SPMB)

`PortalController::index()` diubah jadi:

```php
$dibatasi = $gelombang->jalur()->exists();

$jalurList = JalurPpdb::where('tahun_ajaran_id', $gelombang->tahun_ajaran_id)
    ->where('status_aktif', true)
    ->when($dibatasi, fn ($q) => $q->whereHas('gelombang', fn ($q2) => $q2->whereKey($gelombang->id)))
    ->orderBy('nama')
    ->get();
```

`status_aktif = false` tetap selalu menyembunyikan jalur, terlepas dari status pembatasan — kill-switch existing tidak berubah. Kalau gelombang tidak dibatasi (`$dibatasi` false), hasilnya identik dengan query lama — jadi ini murni penambahan, bukan perubahan perilaku untuk yayasan yang tidak memakai fitur ini.

## 5. Form Gelombang PPDB (create & edit)

Bagian baru di `_form`-nya (atau langsung di view, mengikuti pola form Gelombang yang sudah ada — bukan komponen partial terpisah seperti Lembaga karena formnya kecil): **"Batasi Jalur (Opsional)"**, checkbox list semua jalur `status_aktif = true` milik tahun ajaran yang sama dengan gelombang yang sedang dibuat/diedit. Semua *tidak tercentang* secara default (baik saat create maupun saat edit gelombang yang belum pernah dibatasi). Teks bantuan: "Kosongkan semua supaya semua jalur aktif tersedia untuk gelombang ini. Centang jalur tertentu untuk membatasi hanya jalur itu yang bisa dipilih calon murid."

Kalau tahun ajaran yang relevan belum punya jalur sama sekali, bagian ini menampilkan pesan singkat mengarahkan ke halaman Jalur PPDB, bukan checkbox list kosong yang membingungkan.

## 6. Validasi & Penyimpanan

Di `GelombangPpdbController::store()`/`update()`, tambahan validasi:

```php
'jalur_ids' => ['nullable', 'array'],
'jalur_ids.*' => [
    'integer',
    Rule::exists('jalur_ppdb', 'id')->where('tahun_ajaran_id', $tahunAjaranId),
],
```

`Rule::exists(...)->where('tahun_ajaran_id', ...)` menolak ID jalur yang valid tapi milik tahun ajaran/lembaga lain — mencegah manipulasi request lintas tenant meski `jalur_ppdb` sendiri sudah tenant-scoped lewat `BelongsToTenant` (pertahanan berlapis, konsisten dengan pola validasi lain di controller ini seperti `Rule::unique(...)->where(...)`).

Setelah `GelombangPpdb::create()`/`update()` berhasil, sinkronkan pivot:

```php
$gelombang->jalur()->sync($request->input('jalur_ids', []));
```

`sync([])` (checkbox semua di-uncheck) membersihkan seluruh baris pivot untuk gelombang itu — otomatis kembali ke status "tidak dibatasi", tidak perlu logic khusus.

## 7. Index Gelombang PPDB

Tabel index (sudah ada dari redesign sebelumnya) dapat kolom tambahan kecil — badge status pembatasan, ditempatkan dekat kolom Kuota:
- Tidak dibatasi → badge netral "Semua Jalur" (tone `slate`/abu).
- Dibatasi → badge "N Jalur Dibatasi" (tone `brass`/indigo), dengan N = jumlah jalur yang di-assign.

Controller meng-eager-load `withCount('jalur')` di query index yang sudah ada — tidak menambah query N+1 baru.

## 8. Testing

Test baru di `tests/Feature/Spmb/PortalEntryTest.php` (atau file terkait alur publik pilih-jalur) dan `tests/Feature/Admin/GelombangPpdbTest.php`:

- **Regresi wajib hijau:** gelombang tanpa pembatasan tetap menampilkan semua jalur aktif ke publik (perilaku lama tidak berubah).
- Publik hanya melihat jalur yang di-assign ketika gelombang dibatasi.
- Jalur dengan `status_aktif = false` tetap tidak pernah muncul ke publik meski di-assign ke gelombang (kill-switch).
- Validasi menolak `jalur_ids` yang menunjuk ke jalur milik tahun ajaran/lembaga lain.
- Menyimpan gelombang dengan sebagian jalur tercentang menghasilkan pivot yang benar (`sync` menambah dan menghapus baris sesuai perubahan).
- Meng-uncheck semua jalur pada gelombang yang sebelumnya dibatasi mengembalikannya ke status "tidak dibatasi" (pivot kosong, publik kembali melihat semua jalur aktif).
- Badge di index menunjukkan "Semua Jalur" vs "N Jalur Dibatasi" sesuai kondisi masing-masing.

## 9. Langkah Berikutnya

Spec ini sengaja dibatasi ke satu sisi (konfigurasi dari form Gelombang PPDB + dampaknya ke alur publik). Perubahan terkait lain yang disebutkan saat diskusi — tampilan di form Jalur PPDB, dampak ke halaman Verifikasi & Keputusan, dsb. — didorong ke plan/spec terpisah setelah fitur inti ini selesai dan terbukti berjalan, sesuai arahan eksplisit untuk menyelesaikan masalah ini dulu sebelum merencanakan hal-hal yang berelasi.
