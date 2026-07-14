# M3 — SPMB: Verifikasi & Keputusan — Design Spec

## 1. Latar Belakang & Tujuan

M2 (SPMB Portal Publik) menghasilkan pendaftaran berstatus `menunggu_verifikasi` — tapi tidak ada satu pun kode yang pernah mengubah status itu lagi. M3 menutup lingkaran alur SPMB: dokumen yang di-upload calon murid diverifikasi oleh panitia, nilai tes seleksi dicatat, lalu pihak berwenang menetapkan keputusan diterima/ditolak per pendaftaran, dan hasil final diterbitkan sebagai SK resmi per gelombang.

**Prinsip desain utama** (dari diskusi brainstorming):
1. **Tidak ada role hardcode.** Siapa yang boleh memverifikasi dokumen, menilai tes, menetapkan keputusan, atau menerbitkan SK murni ditentukan oleh permission granular yang diberikan admin yayasan lewat halaman Roles — bukan dicek lewat nama role tertentu di kode. Ini mencerminkan kenyataan di lapangan: validasi harian dilakukan panitia/operator, tapi tanggung jawab hukum akhir ada di Kepala Sekolah (SPTJM, SK Penetapan Hasil) — pembagian ini bisa berbeda-beda per lembaga, jadi harus dikonfigurasi, bukan ditentukan sistem.
2. **Sistem tidak memutuskan, manusia yang memutuskan.** Nilai tes dicatat dan ditotal terbobot sebagai referensi, tapi keputusan diterima/ditolak selalu aksi eksplisit oleh manusia. Auto-scoring/zonasi eksplisit di luar lingkup M3.
3. **Tampilan mengadopsi pola halaman Roles** (server-side datatable AJAX, toast notifikasi, tanpa reload) — bukan pola wizard kartu-per-langkah seperti M2, karena ini fitur admin internal.

**Di luar lingkup M3** (ditunda sengaja): auto-scoring/ranking berdasarkan zonasi atau nilai tertinggi; alur usul-lalu-tetapkan dua tahap (cukup satu aksi keputusan dibatasi permission); status `daftar_ulang`/`aktif` dan apapun terkait tagihan (wilayah M4-M7); e-signature digital sungguhan untuk SK (representasi nama+jabatan saja).

## 2. Peran & Model Perizinan

Modul permission baru `spmb-pendaftaran`, mengikuti konvensi `modul.aksi` yang sudah ada di `PermissionCatalog`:

| Aksi | Label | Kegunaan |
|---|---|---|
| `view` | Lihat | Lihat daftar & detail pendaftaran |
| `verifikasi-dokumen` | Verifikasi Dokumen | Terima/tolak per-berkas dokumen |
| `nilai-seleksi` | Input Nilai | Isi/ubah nilai hasil seleksi (massal maupun per-calon) |
| `tetapkan-keputusan` | Tetapkan Keputusan | Set diterima/ditolak per-pendaftaran |
| `terbitkan-sk` | Terbitkan SK | Generate PDF SK batch untuk satu gelombang |

Kelima permission ini otomatis muncul di matrix halaman Roles (Phase B) tanpa perubahan kode di halaman itu — admin yayasan mencentang siapa dapat apa. Setiap aksi di UI M3 (tombol verifikasi, input nilai, tetapkan keputusan, terbitkan SK) diperiksa lewat permission ini, bukan lewat pengecekan nama role.

**Default seeder** (bisa diubah admin kapan saja, bukan aturan mati):
- `admin_administrasi`: `view`, `verifikasi-dokumen`, `nilai-seleksi`
- `kepala_sekolah`: seluruh 5 aksi
- `yayasan_super_admin`: seluruh 5 aksi (seperti modul lain, akses penuh scope yayasan)

## 3. Skema Data

### 3.1 Perubahan `dokumen_pendaftaran`
Tambah kolom:
- `catatan_verifikasi` (text, nullable) — wajib diisi lewat validasi form saat status diubah ke `ditolak`, opsional saat `diterima`.
- `diverifikasi_oleh_user_id` (foreign ke `users`, nullable)
- `diverifikasi_pada` (timestamp, nullable)

### 3.2 Perubahan `pendaftaran`
Tambah kolom:
- `catatan_keputusan` (text, nullable)
- `ditetapkan_oleh_user_id` (foreign ke `users`, nullable)
- `ditetapkan_pada` (timestamp, nullable)
- `sk_ppdb_id` (foreign ke `sk_ppdb`, nullable) — diisi otomatis saat pendaftaran ini benar-benar tercakup dalam sebuah penerbitan SK (lihat 3.4 & 5). Karena satu gelombang boleh punya banyak SK (mis. susulan), kolom ini adalah satu-satunya sumber kebenaran soal SK mana yang benar-benar mencantumkan pendaftaran ini — bukan hasil tebakan dari tanggal/gelombang.

### 3.3 Tabel baru `hasil_seleksi`
Satu baris per (pendaftaran × seleksi_ppdb) — mengisi entity yang sudah didefinisikan di PRD bagian 5.2, tapi untuk skema manual (bukan auto-scoring):
- `pendaftaran_id` (FK, cascade delete)
- `seleksi_ppdb_id` (FK, cascade delete)
- `nilai` (decimal nullable) — skor 0-100, dipakai untuk semua jenis tes termasuk yang kualitatif (mis. wawancara dinilai subjektif oleh panitia dalam skala yang sama)
- `catatan` (text nullable) — catatan kualitatif pelengkap nilai
- `dinilai_oleh_user_id` (FK ke `users`, nullable)
- `dinilai_pada` (timestamp, nullable)
- Unique constraint `(pendaftaran_id, seleksi_ppdb_id)` — satu nilai per kombinasi, entry ulang meng-update baris yang sama (`updateOrCreate`), bukan menambah baris baru.

### 3.4 Tabel baru `sk_ppdb`
Satu baris per penerbitan SK (satu gelombang boleh punya banyak SK — mis. revisi/susulan):
- `gelombang_ppdb_id` (FK, cascade delete)
- `lembaga_id` (FK, untuk tenant scoping — sama pola dengan model M1/M2 lain yang pakai `BelongsToTenant`)
- `nomor_sk` (string) — diisi manual oleh staf, mengikuti format birokrasi masing-masing institusi, tidak di-generate sistem
- `tanggal_terbit` (date)
- `diterbitkan_oleh_user_id` (FK ke `users`) — nama & jabatan user ini yang tercantum di PDF sebagai representasi penandatangan, bukan e-signature sungguhan
- `file_path` (string) — path PDF di storage, dibuat via `barryvdh/laravel-dompdf` (sudah terpasang sejak M2)

### 3.5 Audit trail
Reuse `activity_log` (Spatie Activitylog, sudah dipakai model lain seperti `Lembaga`/`GelombangPpdb`) — tambahkan `LogsActivity` ke model `Pendaftaran` dan `DokumenPendaftaran` untuk mencatat siapa mengubah apa kapan, tanpa membangun tabel log baru.

## 4. Alur Verifikasi Dokumen & Halaman Kerja

**Halaman index** (`/admin/spmb-pendaftaran`, pola sama seperti Roles — server-side datatable AJAX):
- Kolom: nama calon murid, kode pendaftaran, jalur, gelombang, status (badge — reuse `x-badge` yang sudah ada tone amber/green/red), progres dokumen ("3/4 terverifikasi").
- Bisa dicari (nama/kode pendaftaran/NIK), difilter (gelombang, jalur, status), diurutkan, dipaginasi — server-side lewat AJAX/fetch, sama seperti `RoleController::data()`.
- Hanya menampilkan pendaftaran milik lembaga staf yang login (tenant-scoped, pola sama dengan seluruh admin panel).

**Halaman detail** (`/admin/spmb-pendaftaran/{pendaftaran}`), tiga panel:
1. **Data calon murid** — read-only, ringkasan data pribadi/alamat/keluarga/jawaban formulir tambahan dari M2.
2. **Dokumen** — daftar tiap `dokumen_syarat_ppdb` beserta file yang di-upload, tombol Terima/Tolak per dokumen (AJAX + toast, tanpa reload — pola sama `role-form.js`), field catatan (wajib saat menolak). Indikator "X dari Y dokumen wajib terverifikasi" — informatif, tidak memblokir aksi apapun di panel lain.
3. **Penilaian & Keputusan** — daftar `seleksi_ppdb` untuk jalur tsb dengan nilai yang bisa diisi/diedit langsung (titik masuk kedua selain halaman entry massal di 4.2), total nilai terbobot (Σ nilai×bobot / Σ bobot) sebagai referensi, lalu form Tetapkan Keputusan (diterima/ditolak + catatan_keputusan). Bisa ditetapkan kapan saja terlepas dari kelengkapan verifikasi dokumen atau nilai (non-blocking, sesuai keputusan brainstorming).

**Kontrol akses per elemen:** user dengan `spmb-pendaftaran.view` bisa melihat seluruh halaman; tombol/form spesifik (verifikasi, nilai, keputusan) muncul aktif hanya kalau user punya permission terkait — kalau tidak, elemen tetap terlihat tapi read-only/disabled (transparansi tanpa memberi akses ubah).

## 4.2 Halaman Entry Nilai Massal

Halaman terpisah (`/admin/spmb-pendaftaran/nilai-massal`): pilih gelombang + jenis tes (`seleksi_ppdb`), lalu tabel semua peserta jalur terkait dalam gelombang itu dengan satu kolom nilai yang bisa diisi cepat baris-per-baris tanpa reload (submit per-baris via AJAX, atau submit semua sekaligus — detail teknis diputuskan di tahap implementation plan). Menulis ke tabel `hasil_seleksi` yang sama dengan entry di halaman detail (4.1) — `updateOrCreate` berdasarkan `(pendaftaran_id, seleksi_ppdb_id)`, jadi tidak pernah terjadi data ganda antara dua titik masuk.

## 5. Terbitkan SK

- Dari halaman index (difilter ke satu gelombang) atau tombol khusus, staf dengan `terbitkan-sk` mengisi nomor SK manual dan tanggal terbit, lalu klik terbitkan.
- Sistem generate PDF: kop lembaga, nomor SK, tanggal, tabel seluruh pendaftaran gelombang tsb yang sudah berstatus final (nama, kode pendaftaran, status), blok "ditetapkan oleh" (nama + jabatan staf penerbit). Disimpan sebagai baris `sk_ppdb` baru + file PDF di storage — dan **setiap `pendaftaran` yang benar-benar tercantum di PDF itu langsung diisi `sk_ppdb_id`-nya** ke SK yang baru dibuat. Ini membuat "SK mana yang mencakup pendaftaran ini" jadi baca-langsung lewat FK, bukan tebakan tanggal/gelombang — penting karena satu gelombang boleh punya banyak SK (mis. gelombang 1 diterbitkan duluan, susulan belakangan menyusul dengan SK terpisah untuk pendaftaran yang baru final setelahnya).
- Kalau masih ada pendaftaran berstatus `menunggu_verifikasi` di gelombang itu: tampilkan peringatan konfirmasi ("X pendaftaran belum punya keputusan final, tetap terbitkan?") — bukan blokir keras, staf tetap bisa lanjut (mis. untuk menerbitkan hasil gelombang 1 duluan, susulan belakangan).
- **PDF SK batch ini hanya bisa diakses lewat panel admin** (route di dalam middleware `auth`) — tidak ada rute publik untuk mengunduhnya, karena berisi nama & status semua peserta sekaligus.

## 6. Integrasi ke Portal Publik (M2)

- `resources/views/spmb/status-hasil.blade.php` — badge status sudah otomatis benar begitu `pendaftaran.status` berubah (kode dari M2 sudah mendukung ketiga state, tidak perlu perubahan).
- `resources/views/pdf/bukti-pendaftaran.blade.php` — tambahkan baris kondisional: kalau `pendaftaran.sk_ppdb_id` terisi (lihat 3.2/5), tampilkan "Ditetapkan berdasarkan SK No. {nomor_sk} tanggal {tanggal_terbit}" (baca langsung dari relasi `skPpdb`). Kalau masih null (keputusan sudah ditetapkan tapi SK belum diterbitkan untuk batch ini), baris ini tidak muncul — cukup status apa adanya, tanpa tebakan.
- **Tidak ada** rute publik baru yang mengekspos isi `sk_ppdb` secara langsung — wali murid hanya melihat data anaknya sendiri lewat bukti pendaftaran yang sudah ada.

## 7. Keamanan & Privasi

- PDF SK batch (berisi data semua peserta) hanya lewat rute admin ber-auth, tidak pernah diekspos publik — mencegah kebocoran data peserta lain ke satu wali murid.
- Semua query di halaman index/detail admin di-scope ke lembaga staf yang login (tenant isolation, pola sama seluruh admin panel).
- Aksi verifikasi/nilai/keputusan/SK dicatat di `activity_log` untuk jejak audit siapa-kapan-apa.
- Validasi: `catatan_verifikasi` wajib diisi saat menolak dokumen; `nilai` di `hasil_seleksi` divalidasi rentang wajar (0-100); `nomor_sk` wajib unik per lembaga (belum tentu unik global, karena format bisa mengandung tahun/lembaga sendiri).

## 8. Keputusan Arsitektur (ringkasan)

| Keputusan | Pilihan |
|---|---|
| Auto-scoring/zonasi | Di luar lingkup M3 |
| Alur keputusan | Satu tahap, dibatasi permission dinamis (bukan usul→tetapkan dua tahap) |
| Dokumen SK | PDF digenerate sistem, representasi nama+jabatan (bukan e-signature digital) |
| Granularitas SK | Per-batch (satu gelombang), bukan per-siswa |
| Gerbang verifikasi dokumen sebelum keputusan | Non-blocking (peringatan saja) |
| Entry nilai | Dua titik masuk (massal + per-calon), satu sumber data `hasil_seleksi` |
| Nilai terbobot | Ditampilkan sebagai referensi, tidak dipakai sistem untuk memutuskan otomatis |
| Akses SK batch dari halaman publik | Tidak ada — wali murid cuma lihat status+referensi nomor SK di bukti pendaftaran individual |
| Pola tampilan | Server-side datatable + AJAX (pola halaman Roles), bukan wizard kartu M2 |

## 9. Rencana Pengujian

- **Permission gating** — tiap 1 dari 5 permission diuji independen (403/elemen disabled tanpa izin, berfungsi dengan izin).
- **Verifikasi dokumen** — terima/tolak, catatan wajib saat tolak, audit field terisi.
- **Penilaian** — entry massal & per-calon menulis ke baris `hasil_seleksi` yang sama (tidak duplikat), perhitungan nilai terbobot benar.
- **Keputusan** — mengubah `pendaftaran.status` + catatan + audit field; tetap bisa ditetapkan meski dokumen/nilai belum lengkap.
- **Terbitkan SK** — membuat baris `sk_ppdb` + PDF; peringatan (bukan blokir) saat ada pendaftaran belum final; satu gelombang boleh banyak SK; **setiap pendaftaran yang tercantum di PDF benar-benar dapat `sk_ppdb_id` terisi ke SK yang baru**, dan pendaftaran yang belum final (tidak tercantum) tetap `sk_ppdb_id` null; pendaftaran yang baru final SETELAH SK pertama terbit tidak ikut ter-set ke SK pertama itu (harus menunggu SK susulan).
- **Integrasi publik** — badge status berubah otomatis; baris referensi SK muncul/tidak muncul sesuai kondisi; **regression test kritis**: tidak ada rute publik yang bisa mengakses PDF SK batch atau data `hasil_seleksi`/`sk_ppdb` milik pendaftaran lain.
- **Isolasi tenant** — staf lembaga A tidak bisa memverifikasi/menilai/memutuskan/menerbitkan SK untuk pendaftaran lembaga B.
- **Datatable index** — search/filter/sort/paginate server-side, mengikuti pola pengujian yang sudah ada di `RoleController` test suite.
