# Modul Sistem Keuangan Sekolah Dinamis — Sub-project 1: Fondasi Skema

> Brainstorming ulang dari `BRAINSTORM_FINANCIAL.md` (v2, 2026-08-08) + temuan codebase existing (PPDB billing) + referensi fitur Pusdatren. Status: Disetujui — siap ke Implementation Plan.

## Konteks & Sumber

Dokumen ini adalah hasil sesi brainstorming lanjutan (2026-08-10) yang mengevaluasi ulang `BRAINSTORM_FINANCIAL.md` terhadap kondisi codebase nyata, dan menambahkan ide dari aplikasi demo Pusdatren (sistem keuangan pesantren) yang relevan untuk konteks yayasan multi-lembaga. Ditinjau ulang sekali lagi pada hari yang sama setelah user membagikan referensi visual form "Tambah Tagihan Baru" (mode Manual & Otomatis) dari Pusdatren — struktur Target Sasaran, Tarif Berdimensi, dan Keringanan direvisi mengikuti bentuk data yang terlihat di UI tersebut (lihat bagian Perubahan Skema §3–§5).

Modul ini terlalu besar untuk satu spec/implementation plan. Dipecah menjadi 6 sub-project berurutan (pola sama seperti Program Pendampingan):

1. **Fondasi Skema** (dokumen ini) — refactor `tagihan` → polymorphic, migrasi kompatibilitas PPDB, ekstensi `jenis_tagihan` (kriteria multi-dimensi, keringanan, mode generate)
2. Billing Engine Admin — generate tagihan (cron + manual), dashboard monitoring ("Lihat Penerima Tagihan", tunggakan)
3. Wallet & Auto-Allocation Engine + toggle global
4. Payment Channels — BRI VA/QRIS + Transfer Manual + Cash
5. Notifikasi — WhatsApp/Email/In-App per preferensi + reminder H-3/H-1
6. Parent Dashboard + Kwitansi PDF + upload logo yayasan

Sub-project 2–6 masing-masing akan dibrainstorming & di-spec terpisah setelah sub-project sebelumnya selesai diimplementasikan dan diverifikasi.

## Tujuan Sub-project 1

Membangun ulang skema `tagihan` agar bisa menampung baik tagihan PPDB (existing, production) maupun tagihan sekolah dinamis (SPP/kegiatan/tahunan, baru) dalam satu sumber kebenaran, tanpa merusak alur PPDB yang sudah berjalan. Sub-project ini TIDAK membangun wallet, payment gateway, atau UI generate tagihan — hanya fondasi data model + kompatibilitas mundur.

## Kondisi Existing (baseline)

- `tagihan`: FK `pendaftaran_id` (NOT NULL, cascade delete), unique `(pendaftaran_id, kategori)`, `kategori` ENUM `pendaftaran|daftar_ulang`, status `belum_bayar|dicicil|lunas`.
- `jenis_tagihan`: scoped per `lembaga_id`, `kategori` ENUM `pendaftaran|daftar_ulang|lainnya`, `bisa_dicicil`/`maks_cicilan`.
- `nominal_tagihan_jalur`: nominal per `jenis_tagihan` × `jalur_ppdb` (PPDB-only, tidak diubah).
- `pembayaran`: `metode` ENUM `transfer_manual|va_bri` (BRI VA sudah diantisipasi), status verifikasi admin sudah ada (`diverifikasi_oleh_user_id`, `catatan_verifikasi`).
- `skema_cicilan`/`cicilan`: rencana cicilan 1:1 ke `tagihan` (PPDB-only, tidak diubah).
- Digunakan oleh: `Admin/TagihanController`, `Admin/JenisTagihanController`, `Admin/PembayaranController`, `Portal/TagihanController` — semua production, harus tetap lulus test suite existing.
- `lembaga.memungut_iuran` / `nominal_iuran` / `periode_iuran`: field tampilan murni (form CRUD + tab profil `resources/views/admin/lembaga/tabs/profil.blade.php` + 1 assertion test `LembagaCrudTest.php`). **Tidak dibaca logic manapun** — aman didekomisi.

## Perubahan Skema

### 1. `tagihan` → Polymorphic

```
tagihan
├─ tagihable_type   string        ← BARU, morphTo (App\Models\Pendaftaran | App\Models\Siswa)
├─ tagihable_id      unsignedBigInt ← BARU
├─ pendaftaran_id    NULLABLE      ← diubah dari NOT NULL (dipertahankan sementara utk transisi data,
│                                     lihat "Strategi Migrasi Data" — dihapus di migrasi berikutnya)
├─ kategori           ENUM('pendaftaran','daftar_ulang','spp','tahunan','kegiatan','custom')
├─ jenis_tagihan_id   FK, NULLABLE ← BARU (tagihan SPP/dinamis mengacu jenis_tagihan; PPDB tetap
│                                     via tagihan_item→jenis_tagihan seperti sekarang)
├─ billing_period     string(7) NULLABLE  ← BARU, format 'YYYY-MM', untuk idempotency recurring
├─ source_trigger     ENUM('manual','cron','event') DEFAULT 'manual'  ← BARU
├─ discount_amount    decimal(12,2) NULLABLE  ← BARU
├─ discount_type       ENUM('fixed','persen') NULLABLE  ← BARU
├─ net_amount          decimal(12,2) NULLABLE  ← BARU (total_tagihan - discount, dihitung saat generate)
├─ paid_amount         decimal(12,2) DEFAULT 0  ← BARU (akumulasi nominal terbayar; diupdate oleh payment channel di sub-project 4, tapi kolomnya jadi fondasi sejak sekarang karena dipakai dashboard monitoring sub-project 2)
├─ status              ENUM('belum_bayar','dicicil','lunas','sebagian','dibatalkan')  ← tambah 'sebagian','dibatalkan'
├─ cancelled_by        FK users, NULLABLE  ← BARU
├─ cancelled_at         timestamp NULLABLE  ← BARU
├─ cancel_reason        text NULLABLE  ← BARU
└─ (unique lama (pendaftaran_id, kategori) DIHAPUS — idempotency baru: lihat catatan di bawah)
```

**Idempotency generate** (menggantikan unique constraint lama): dicek di level service sebelum insert — `tagihable_type + tagihable_id + jenis_tagihan_id + billing_period` tidak boleh duplikat untuk tagihan aktif (non-dibatalkan). Untuk PPDB (`billing_period` selalu null, sekali per kategori), constraint efektif sama seperti perilaku lama.

**Alasan pilih polymorphic (bukan dual nullable FK):** orientasi jangka panjang — tabel `tagihan` bisa dipakai untuk entitas lain di masa depan (alumni, guru, vendor) tanpa migrasi skema berulang.

### 2. `jenis_tagihan` — Ekstensi

```
jenis_tagihan
├─ kategori            ENUM(...) + tambah 'spp','tahunan','kegiatan','custom'
├─ priority_score       unsignedInteger NULLABLE  ← untuk Auto-Allocation Engine (sub-project 3)
├─ mode                 ENUM('manual','otomatis') DEFAULT 'manual'
├─ tanggal_mulai         date NULLABLE       (mode otomatis)
├─ tanggal_selesai       date NULLABLE
├─ tanggal_generate      tinyInteger NULLABLE  (hari-ke dalam bulan, 1-31)
├─ hari_jatuh_tempo      tinyInteger NULLABLE  (offset hari dari tanggal generate)
├─ va_expire_hours       unsignedInteger NULLABLE  ← dipakai sub-project 4
├─ is_active             boolean DEFAULT true
└─ last_generated_period string(7) NULLABLE
```

### 3. Tabel Baru — Target Sasaran Multi-Kriteria (grup OR-of-AND)

Ditinjau ulang 2026-08-10 dari referensi UI Pusdatren ("Tambah Tagihan Baru" — Target Sasaran & Tarif Berdimensi). Struktur asli (flat-AND, satu tabel) diganti struktur dua-level: **grup** (1 grup = 1 "Sasaran #N"/"Tarif #N" card di UI, field di dalamnya di-AND-kan) dan **antar-grup di-OR-kan**. Struktur ini dipakai ulang oleh dua fitur — Target Sasaran (eligibility) dan Tarif Berdimensi (pricing rule) — lewat satu pasang tabel generik + kolom pembeda `tipe`:

```
jenis_tagihan_sasaran_grup
├─ id
├─ jenis_tagihan_id  FK → jenis_tagihan, cascade delete
├─ tipe               ENUM('sasaran','tarif')   ← membedakan grup eligibility vs grup pricing
├─ nominal            decimal(12,2) NULLABLE     (diisi HANYA jika tipe='tarif')
└─ timestamps

jenis_tagihan_sasaran_kriteria
├─ id
├─ jenis_tagihan_sasaran_grup_id  FK → jenis_tagihan_sasaran_grup, cascade delete
├─ field                           ENUM('lembaga','tahun_ajaran','tingkat','kelas','jenis_kelamin','status_siswa')
├─ operator                         ENUM('in','not_in')
├─ value                             json  (array of ids/values sesuai field)
└─ timestamps
```

**Semantik:**
- Grup `tipe='sasaran'`: siswa masuk target tagihan ini jika match SEMUA kriteria dalam SATU grup manapun (AND dalam grup, OR antar grup). Tidak ada grup `tipe='sasaran'` sama sekali = "Semua Siswa" di lembaga terkait.
- Grup `tipe='tarif'`: dievaluasi urutan dibuat (`id` ASC) setelah siswa lolos sasaran; grup pertama yang match menentukan `nominal` yang dipakai. Tidak ada grup `tipe='tarif'` yang match → fallback ke `jenis_tagihan.default_amount`.
- Field set sengaja dibatasi ke entitas yang benar-benar ada di skema sekolah kita (`lembaga`, `tahun_ajaran`, `tingkat` dari `kelas.tingkat`, `kelas`, `jenis_kelamin`, `status_siswa`) — field pesantren-spesifik (Wilayah/Blok/Kamar/Angkatan/Status Mondok) tidak diadopsi karena tidak ada padanan entitasnya di skema kita.

### 4. Nominal Override per Siswa (tetap ada, berdampingan dengan Tarif Berdimensi)

```
nominal_tagihan_siswa
├─ id
├─ jenis_tagihan_id  FK → jenis_tagihan, cascade delete
├─ siswa_id           FK → siswa, cascade delete
├─ nominal             decimal(12,2)
└─ timestamps

unique(jenis_tagihan_id, siswa_id)
```
Analog `nominal_tagihan_jalur` (PPDB) tapi untuk siswa individual pada tagihan dinamis — dipakai untuk kasus 1-off yang tidak masuk pola kelompok manapun.

**Urutan resolusi nominal saat generate (sub-project 2):** (1) cek `nominal_tagihan_siswa` untuk siswa ini → jika ada, pakai. (2) Jika tidak ada, evaluasi grup `jenis_tagihan_sasaran_grup` bertipe `tarif` yang match → pakai `nominal` grup pertama yang cocok. (3) Jika tidak ada yang match, pakai `jenis_tagihan.default_amount`.

### 5. Tabel Baru — Keringanan (rule per-jenis-tagihan)

Ditinjau ulang 2026-08-10: potongan untuk kondisi yang sama (mis. "Anak Pegawai") bisa berbeda besarannya antar jenis tagihan (SPP vs uang kegiatan), sesuai pola Pusdatren. `kategori_keringanan` jadi murni master daftar kondisi (tanpa nilai default); besaran potongan pindah ke rule per-tagihan:

```
kategori_keringanan
├─ id
├─ lembaga_id       FK → lembaga, cascade delete
├─ nama             string  (mis. "Anak Pegawai", "Beasiswa Prestasi", "Yatim/Piatu")
├─ keterangan        text NULLABLE
└─ timestamps

jenis_tagihan_keringanan
├─ id
├─ jenis_tagihan_id           FK → jenis_tagihan, cascade delete
├─ kategori_keringanan_id     FK → kategori_keringanan, restrict delete
├─ tipe_potongan               ENUM('fixed','persen')
├─ nilai                        decimal(12,2)
├─ keterangan                   text NULLABLE
└─ timestamps

unique(jenis_tagihan_id, kategori_keringanan_id)

siswa_keringanan
├─ id
├─ siswa_id                   FK → siswa, cascade delete
├─ kategori_keringanan_id     FK → kategori_keringanan, restrict delete
├─ berlaku_dari                date
├─ berlaku_sampai              date NULLABLE
└─ timestamps
```

`siswa_keringanan` hanya menandai kondisi apa yang dimiliki siswa (tanpa nilai potongan). Saat tagihan digenerate (sub-project 2), engine cek kondisi aktif siswa pada `tagihable_id` (jika `tagihable_type=Siswa`), cari row `jenis_tagihan_keringanan` yang cocok untuk `jenis_tagihan_id` tagihan ini, lalu **snapshot** hasil potongan ke `tagihan.discount_amount`/`discount_type` — bukan live-reference — agar histori tagihan lama tidak berubah jika rule keringanan diedit belakangan. Jika siswa punya beberapa kondisi yang match beberapa rule sekaligus, ambil yang nilai potongannya terbesar (business rule sederhana; bisa direvisi di sub-project 2 jika ada kasus akumulasi).

### 6. `pembayaran` — Ekstensi (forward-compat, tidak dipakai penuh sampai sub-project 3–4)

```
pembayaran
├─ metode              ENUM(...) + tambah 'cash','qris','wallet_auto','wallet_saldo'
├─ sumber               ENUM(...) + tambah 'orang_tua' (pembayar tagihan siswa aktif, beda dari 'calon_siswa' yang PPDB-only)
├─ wallet_id            FK, NULLABLE   ← BARU (kolom disiapkan; tabel wallets dibuat di sub-project 3)
├─ is_auto_allocation   boolean DEFAULT false  ← BARU
├─ channel_reference     string NULLABLE  ← BARU (VA number/QR ID/referensi BRI)
└─ identifier_method     ENUM('manual','nfc') DEFAULT 'manual'  ← BARU (forward-compat kartu siswa)
```

### 7. Tabel Baru — Junction Alokasi Multi-Tagihan

```
pembayaran_tagihan
├─ id
├─ pembayaran_id      FK → pembayaran, cascade delete
├─ tagihan_id          FK → tagihan, cascade delete
├─ amount_allocated     decimal(12,2)
└─ timestamps
```
Satu `pembayaran` bisa mengalokasikan ke banyak `tagihan` (dipakai untuk batch payment manual multi-select DAN Auto-Allocation Engine di sub-project 3).

## Strategi Migrasi Data & Kompatibilitas PPDB

1. Migrasi skema: tambah semua kolom baru + `tagihable_type`/`tagihable_id`, `pendaftaran_id` diubah nullable.
2. Migrasi data (dalam migration yang sama atau seeder terpisah, dijalankan sekali): untuk setiap row `tagihan` existing, set `tagihable_type = App\Models\Pendaftaran::class`, `tagihable_id = pendaftaran_id`.
3. Refactor `Admin/TagihanController`, `Portal/TagihanController`, `Admin/PembayaranController`, dan model `Tagihan` (relasi `pendaftaran()` → tetap disediakan sebagai accessor/relasi turunan dari `tagihable()` agar kode existing yang memanggil `$tagihan->pendaftaran` tidak perlu diubah satu-satu).
4. Test suite PPDB existing (`tests/Feature/Admin/*Tagihan*`, `tests/Feature/Portal/*Tagihan*`, dll.) **harus tetap hijau** — ini bagian dari definition-of-done sub-project ini, bukan pekerjaan terpisah.
5. Kolom `pendaftaran_id` DIPERTAHANKAN (nullable) di sub-project ini untuk mengurangi risiko — baru di-drop di migration terpisah setelah dipastikan tidak ada query lain yang masih bergantung padanya.

## Dekomisi `lembaga.memungut_iuran`/`nominal_iuran`/`periode_iuran`

- **Dampak terverifikasi**: hanya dipakai di `LembagaController` (validasi + assignment), `_form.blade.php` (input), `profil.blade.php` (tampilan read-only), `LembagaSeeder.php` (4 lembaga ter-seed), `LembagaCrudTest.php` (1 test assertion checkbox behavior).
- **Rencana**: migration untuk setiap `lembaga` dengan `memungut_iuran=true`, buat satu row `jenis_tagihan` (kategori `spp`, `default_amount = nominal_iuran`, `mode = otomatis`, frequency sesuai `periode_iuran`). Setelah data termigrasi dan diverifikasi, kolom di-drop dari `lembaga`, form/tab profil dibersihkan, `LembagaSeeder` & `LembagaCrudTest` diupdate.
- Ini dieksekusi sebagai langkah terakhir sub-project 1 (setelah tabel `jenis_tagihan` baru siap menerima data), bukan blocker di awal.

## Yang TIDAK Termasuk Sub-project 1

- UI admin untuk membuat/edit `jenis_tagihan` dengan kriteria & keringanan (→ sub-project 2)
- Job scheduler/cron generate tagihan aktual (→ sub-project 2)
- Dashboard monitoring "Lihat Penerima Tagihan" / tunggakan (→ sub-project 2)
- Tabel `wallets`, `bri_virtual_accounts`, `bri_qris_payments`, `system_settings`, Auto-Allocation Engine (→ sub-project 3)
- Integrasi BRI API, upload bukti transfer, input cash (→ sub-project 4)
- `user_notification_preferences`, `notification_events`, `notification_logs`, reminder H-3/H-1 (→ sub-project 5)
- Parent dashboard, kwitansi PDF, upload logo yayasan (→ sub-project 6)
- Kartu siswa/NFC fisik (kolom `identifier_method` disiapkan, implementasi ditunda tanpa batas waktu)

## Ambiguitas Terselesaikan (sesi ini)

- [x] Reuse vs sistem terpisah → Generalize skema PPDB existing (polymorphic)
- [x] Bentuk polymorphic → `tagihable_type`/`tagihable_id` (bukan dual nullable FK)
- [x] Field iuran lama di `lembaga` → Deprecate & migrate ke `jenis_tagihan`
- [x] Push notification → Drop dari scope; 3 channel: WA (Fonnte)/Email/In-App (Laravel database notifications)
- [x] Batch payment multi-select → 1 VA/QRIS gabungan + tabel junction `pembayaran_tagihan`
- [x] Kwitansi PDF → Template standar sistem, personalisasi dari data `lembaga` + `yayasan.logo` (perlu UI upload baru)
- [x] Reminder jatuh tempo → Masuk scope (H-3, H-1), sub-project 5
- [x] Target scope tagihan → Multi-kriteria combo, bukan single enum *(struktur tabelnya direvisi lagi di sesi lanjutan di bawah — lihat §3)*
- [x] Keringanan/diskon → Master data reusable, snapshot ke tagihan saat generate *(mekanisme nilainya direvisi lagi di sesi lanjutan di bawah — lihat §5)*
- [x] Dashboard monitoring admin → Masuk scope, sub-project 2
- [x] Kartu siswa/NFC loket → Tetap manual sekarang, kolom `identifier_method` disiapkan agar forward-compatible
- [x] Refactor PPDB existing → Bagian dari scope & definition-of-done sub-project 1, test suite lama harus tetap hijau

### Sesi lanjutan — referensi visual Pusdatren (2026-08-10)

- [x] Struktur Target Sasaran & Tarif Berdimensi → Grup OR-of-AND (`jenis_tagihan_sasaran_grup` + `jenis_tagihan_sasaran_kriteria`), menggantikan flat-AND, dipakai ulang untuk kedua fitur via kolom `tipe`
- [x] Tarif Berdimensi vs override per-siswa → Berdampingan, urutan resolusi: override siswa → tarif dimensi match pertama → default_amount
- [x] Keringanan → Rule per-jenis-tagihan (`jenis_tagihan_keringanan`), bukan nilai default global; `kategori_keringanan` jadi master nama kondisi saja, `siswa_keringanan` hanya menandai kepemilikan kondisi
- [x] "Potongan Perizinan" (pesantren, potongan saat santri izin asrama) → Drop dari scope, tidak ada padanan konsep boarding di sekolah harian

## Amendemen Setelah Implementasi

- Unique index `(pendaftaran_id, kategori)` TIDAK dihapus — dipertahankan apa adanya, karena MySQL memperlakukan NULL sebagai berbeda-beda (distinct) pada unique index sehingga index ini tidak pernah menghalangi baris Siswa polymorphic (yang punya `pendaftaran_id` NULL), sementara menghapusnya justru akan menghilangkan safety net dedupe PPDB yang nyata untuk submission bersamaan. Lihat commit abfffa1 pada implementasi.
- Ketiga controller PPDB (`Admin/TagihanController`, `Portal/TagihanController`, `Admin/PembayaranController`) TIDAK direfactor, dan `pendaftaran()` TIDAK diubah menjadi accessor turunan dari `tagihable()` — sengaja dibiarkan tidak tersentuh karena `pendaftaran_id` tetap terisi untuk setiap tagihan PPDB ke depannya, sehingga pola existing `$tagihan->pendaftaran->lembaga_id` di controller-controller tersebut tetap berfungsi tanpa perubahan apa pun. Ini keputusan berisiko lebih rendah yang diambil saat implementasi, menggantikan rencana awal spec yang lebih invasif.
- `paid_amount` pada `tagihan` saat ini hanya reliably terisi ke depannya untuk baris bertarget Siswa (SPP) via alur pembayaran sub-project mendatang — jalur pembayaran PPDB yang ada (`app/Services/PembayaranService.php`) belum menulis ke kolom ini saat menandai tagihan `lunas`. Dashboard/laporan mana pun yang membaca `paid_amount` sebaiknya di-scope ke `tagihable_type = App\Models\Siswa::class`, atau memperlakukan `paid_amount` baris PPDB sebagai tidak reliable sampai hal ini ditangani secara eksplisit (dicatat di sini untuk siapa pun yang merencanakan pekerjaan tersebut, tidak diperbaiki di sub-project ini agar tidak menyentuh kode pembayaran PPDB tanpa perlu).

## Ambiguitas Sisa (untuk sub-project 2 dan seterusnya)

- [ ] Format `hari_jatuh_tempo` untuk kelas/tingkat berbeda tahun ajaran (mis. siswa pindah kelas di tengah periode) — detail behavior migrasi kriteria saat re-evaluate
- [ ] Apakah `kategori_keringanan` bisa berlaku lintas lembaga (yayasan-level) atau strictly per lembaga — saat ini didesain per lembaga, perlu dikonfirmasi ulang saat sub-project 2 jika ada kasus siswa pindah lembaga dalam satu yayasan
- [ ] Jika siswa punya beberapa kondisi keringanan yang match beberapa rule sekaligus pada satu jenis tagihan yang sama → sub-project 1 memakai aturan sederhana "ambil nilai potongan terbesar"; perlu dikonfirmasi ulang di sub-project 2 apakah ini yang benar-benar diinginkan atau harus akumulatif/pilihan admin
- [ ] Urutan evaluasi grup `tipe='tarif'` yang overlap (dua grup sama-sama match untuk satu siswa) → saat ini "grup pertama dibuat (id ASC) yang menang"; perlu UI yang jelas menampilkan urutan prioritas ke admin di sub-project 2
