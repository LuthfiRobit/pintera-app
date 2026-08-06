# Program Pendampingan — Sistem Generate Tugas Berkala (Batch) — Design

**Tanggal:** 2026-08-06
**Status:** Approved

## Latar Belakang

Sejak Sub-proyek 4 (2026-08-04), `kasus_tugas` memakai model satu baris per pemberian tugas,
dengan `frekuensi` (`sekali`/`harian`/`mingguan`/`bulanan`) sebagai label ekspektasi cadence
saja — bukan penentu berapa baris yang dibuat. Pada 2026-08-05, gap spesifik untuk `frekuensi =
harian` ditutup lewat mekanisme "checklist per-tanggal" (`kasus_tugas_submission.tanggal`,
lock per-tanggal, tampilan checklist) — lihat
`docs/superpowers/specs/2026-08-05-pendampingan-checklist-harian-tugas-design.md`.

Dokumen ini **membalik keputusan itu secara sengaja**, atas permintaan eksplisit user setelah
klarifikasi langsung: `frekuensi` sekarang benar-benar menentukan **berapa baris `kasus_tugas`
yang dibuat sistem** dari satu definisi tugas yang diisi konselor — bukan satu baris dengan
tracking submission per-tanggal di dalamnya. Mekanisme checklist-per-tanggal 2026-08-05
**dicopot sepenuhnya**, digantikan oleh mekanisme ini.

## Tujuan

Konselor mengisi **satu** definisi tugas (judul, instruksi, frekuensi, tanggal_mulai,
tanggal_selesai, dan — khusus bulanan — tanggal_pengumpulan_bulanan). Sistem menghitung dan
membuat **banyak baris `kasus_tugas`** sesuai frekuensi (setelah aturan fallback diterapkan),
masing-masing sebagai tugas independen dengan siklus submit-review yang **sudah ada sejak Sub-
proyek 4** (satu form submit generik per baris, satu status per baris) — tidak ada mekanisme
submission baru yang perlu dibangun untuk siklus review-nya sendiri.

## Perubahan Skema

### `kasus_tugas` — kolom baru

- `batch_id` (`char(36)`, **not nullable**, UUID) — penanda seri. **Semua baris dari satu kali
  submit form** (termasuk `frekuensi = sekali`, yang selalu menghasilkan tepat 1 baris) berbagi
  satu `batch_id` yang sama. Diberi ke SEMUA baris tanpa kecuali (tidak nullable) supaya kode
  query/tampilan tidak perlu menangani dua kasus (ada-batch vs tidak-ada-batch) secara terpisah.
- `batch_urutan` (`unsignedInteger`, not nullable, default `1`) — urutan baris ini dalam seri
  (1, 2, 3, ...).
- `batch_total` (`unsignedInteger`, not nullable, default `1`) — jumlah total baris dalam seri
  ini (denormalisasi dari `COUNT` supaya label "Hari 3 dari 7" tidak perlu query tambahan per
  baris saat render).

Tidak ada perubahan pada kolom `frekuensi`, `mulai_pada`, `batas_selesai_pada`, `status` yang
sudah ada — makna dan tipe datanya tetap sama, hanya sekarang setiap baris merepresentasikan
SATU occurrence dalam seri, bukan satu rentang gabungan.

### `kasus_tugas_submission` — dicopot

Kolom `tanggal` (ditambahkan 2026-08-05,
`database/migrations/2026_08_06_100000_add_tanggal_to_kasus_tugas_submission.php`) dan migrasi
backfill-nya (`2026_08_07_000000_backfill_tanggal_kasus_tugas_submission_harian.php`) **di-drop**
lewat migrasi baru. Karena seluruh data terkait masih data `demo` (bukan produksi), tidak ada
migrasi data mundur yang perlu dijaga — cukup `dropColumn('tanggal')`, dan proyek menjalankan
`migrate:fresh --seed` setelah perubahan ini mendarat (sama seperti langkah pembersihan seed
data 2026-08-06 sebelumnya).

## Aturan Validasi & Fallback (satu-satunya sumber kebenaran: backend)

Dijalankan di `TugasBatchGenerator` (lihat bagian Service), dipanggil baik oleh endpoint submit
sungguhan maupun endpoint preview (lihat bagian Sinkronisasi) — **sehingga logic fallback ini
hanya ada satu implementasi, tidak pernah diduplikasi di JavaScript.**

0. Aturan dasar yang berlaku untuk semua frekuensi, sebelum aturan fallback apapun dicek
   (mempertahankan konvensi validasi yang sudah ada di form saat ini): `tanggal_selesai` wajib
   `after_or_equal:tanggal_mulai`.
1. Hitung `$selisihHari = $tanggalMulai->diffInDays($tanggalSelesai)` (inklusif — rentang
   1 hari sampai 1 hari sama dengan `$selisihHari = 0`; dipakai apa adanya, tidak
   di-`+1`, supaya ambang "> 7 hari" cocok persis dengan definisi user: rentang 8 Agustus s/d
   15 Agustus = selisih 7 hari → TIDAK lolos syarat mingguan, jatuh ke harian; 8 Agustus s/d 16
   Agustus = selisih 8 hari → lolos).
2. **Frekuensi akhir** dihitung dengan urutan fallback berantai (bukan tiga pengecekan
   terpisah — satu bulanan yang gagal syarat, setelah turun ke mingguan, tetap dicek ulang
   syarat mingguannya juga):
   ```
   $frekuensiAkhir = $frekuensiDipilih;
   if ($frekuensiAkhir === 'bulanan' && $selisihHari <= 30) {
       $frekuensiAkhir = 'mingguan';
   }
   if ($frekuensiAkhir === 'mingguan' && $selisihHari <= 7) {
       $frekuensiAkhir = 'harian';
   }
   ```
3. `tanggal_pengumpulan_bulanan` **wajib diisi** kalau (dan hanya kalau) `$frekuensiAkhir ===
   'bulanan'` — validasi `required_if` terhadap frekuensi HASIL AKHIR, bukan frekuensi yang
   dipilih di form (supaya kalau user pilih "Bulanan" tapi jatuh ke "Mingguan" karena rentang
   terlalu pendek, field ini otomatis tidak wajib, dan sebaliknya tidak relevan karena mingguan/
   harian tidak pernah butuh field ini).
4. Nilai `tanggal_pengumpulan_bulanan`: integer 1–31, ATAU string literal `'akhir_bulan'`
   (opsi "hari terakhir bulan tersebut" dari form). Divalidasi `nullable` (hanya wajib sesuai
   aturan #3), lalu kalau ada: `required_if` di atas + `in:akhir_bulan` ATAU `integer|between:1,31`
   (custom rule, bukan satu aturan bawaan Laravel — ditulis sebagai closure/custom rule class).

## Algoritma Generate per Frekuensi Akhir (`TugasBatchGenerator::generate()`)

- **`sekali`**: 1 baris. `mulai_pada` = `tanggal_mulai` form, `batas_selesai_pada` =
  `tanggal_selesai` form (perilaku ini SAMA PERSIS dengan `sekali` sebelum perubahan ini —
  tidak ada regresi).
- **`harian`**: 1 baris per tanggal kalender dari `tanggal_mulai` s/d `tanggal_selesai`
  inklusif. Tiap baris: `mulai_pada = batas_selesai_pada = tanggal itu`.
- **`mingguan`**: blok 7 hari berurutan (`Hari 1-7`, `8-14`, `15-21`, ...) dimulai dari
  `tanggal_mulai`. **Sisa hari yang tidak genap 7 di akhir rentang menjadi satu blok pendek
  terpisah**, bukan digabung ke blok sebelumnya (keputusan desain, lihat "Alasan Keputusan
  Desain" di bawah). Setiap blok: `mulai_pada` = hari pertama blok, `batas_selesai_pada` = hari
  terakhir blok (atau `tanggal_selesai` kalau itu lebih awal dari 7 hari penuh — untuk blok
  terakhir).
- **`bulanan`**: 1 baris per segmen bulan kalender. Untuk segmen ke-N:
  - `batas_selesai_pada` = tanggal jatuh tempo di bulan itu (hari ke-`tanggal_pengumpulan_bulanan`,
    atau `Carbon::endOfMonth()` kalau opsi "akhir bulan" dipilih), **dibatasi (`min()`) supaya
    tidak pernah melewati `tanggal_selesai` keseluruhan form**.
  - `mulai_pada` segmen pertama = `tanggal_mulai` form; segmen berikutnya = 1 hari setelah
    `batas_selesai_pada` segmen sebelumnya.
  - Berhenti generate begitu `batas_selesai_pada` suatu segmen sama dengan `tanggal_selesai`
    form (segmen terakhir, bahkan kalau lebih pendek dari sebulan penuh).
  - Kalau `tanggal_pengumpulan_bulanan` (hari ke-N) sudah lewat relatif terhadap `mulai_pada`
    segmen pertama (mis. form dimulai tanggal 20, due-date = tanggal 15), jatuh-tempo pertama
    dihitung di BULAN BERIKUTNYA (bukan mundur ke bulan berjalan yang sudah lewat) — dipakai
    `Carbon::day($n)` lalu `->addMonthNoOverflow()` kalau hasilnya `lt($mulaiSegmen)`.

Setiap baris hasil generate dari satu batch mendapat `batch_id` (UUID baru, sama untuk seluruh
baris batch itu), `batch_urutan` (1-based, berurutan), `batch_total` (jumlah baris final),
`judul`/`instruksi` disalin apa adanya dari input form (tidak ditambah suffix ke kolom itu
sendiri — label "Hari 3 dari 7" dibangun di tampilan dari `batch_urutan`/`batch_total`, bukan
disimpan ke `judul`).

## Alasan Keputusan Desain: Sisa Hari Mingguan Jadi Blok Terpisah, Bukan Digabung

Kalau sisa hari (mis. 3 hari tersisa dari rentang 30 hari) digabung ke blok terakhir, blok itu
jadi 10 hari — berbeda dari 4 blok lain yang masing-masing 7 hari. Konselor yang menilai
kemajuan per-minggu akan bingung kenapa satu "minggu" lebih panjang dari yang lain, dan
label "Minggu ke-5" secara implisit menjanjikan durasi yang sama seperti minggu-minggu
sebelumnya padahal tidak. Blok pendek terpisah di akhir menjaga konsistensi arti tiap blok
("7 hari, kecuali blok terakhir yang boleh lebih pendek kalau rentangnya tidak habis dibagi
7") — pola yang sama dipakai sistem penjadwalan berulang lain (mis. sprint/siklus billing
terakhir yang lebih pendek), dan tetap jelas batasnya tanpa mendistorsi blok-blok sebelumnya.

## Sinkronisasi Backend-Frontend: Endpoint Preview, Bukan Duplikasi Logic

**Tidak ada logic tanggal/fallback yang ditulis ulang di JavaScript.** Endpoint baru
`POST kasus/{kasus}/tugas/preview` memanggil `TugasBatchGenerator::generate()` yang SAMA PERSIS
dipakai endpoint submit sungguhan, dalam mode dry-run (hasil `Collection` dikembalikan sebagai
JSON, tidak pernah di-`INSERT`). Form pemberian tugas memanggil endpoint ini (debounced) setiap
kali field tanggal/frekuensi/tanggal_pengumpulan_bulanan berubah, menampilkan pratinjau real-time:
frekuensi akhir yang akan dipakai (kalau berbeda dari yang dipilih, tampilkan peringatan
eksplisit "akan diproses sebagai Harian karena rentang ≤7 hari"), jumlah baris yang akan
dibuat, dan tanggal `mulai_pada`/`batas_selesai_pada` tiap baris. Validasi submit sungguhan
tetap dijalankan penuh di backend sebagai jaring pengaman — preview TIDAK PERNAH dipercaya
sebagai validasi final (kondisi race, JS dimatikan, dsb tetap tertangkap saat submit).

## Perubahan pada Form "Beri Tugas" (UI)

Form multi-baris manual yang ada sekarang (konselor menambah beberapa baris independen sekaligus
lewat tombol "Tambah Baris", masing-masing dengan judul/instruksi/frekuensi/tanggal sendiri-
sendiri) **diganti** dengan form SATU definisi tugas per submit (judul, instruksi, frekuensi,
tanggal_mulai, tanggal_selesai, dan field `tanggal_pengumpulan_bulanan` yang muncul kondisional
hanya kalau frekuensi akhir hasil preview = bulanan). Kalau konselor ingin memberi lebih dari
satu seri tugas berbeda, submit form ini beberapa kali secara terpisah — bukan digabung dalam
satu submit seperti pola lama.

Tampilan daftar tugas di tab "Tugas" (`_tab-tugas.blade.php`) mengelompokkan baris-baris dengan
`batch_id` yang sama di bawah satu header seri (judul + "Hari 3 dari 7" dari
`batch_urutan`/`batch_total`), dengan tiap baris di dalamnya memakai **alur submit-review
generik yang sudah ada sejak Sub-proyek 4 tanpa perubahan** (satu form submit per baris, satu
status per baris, aksi Terima/Minta Revisi per baris) — mekanisme checklist-per-tanggal
2026-08-05 dicopot seluruhnya (kolom `tanggal`, lock per-tanggal, cabang tampilan `@if
($tugas->frekuensi === 'harian')` khusus).

## Testing

- Konselor submit tugas `harian` rentang 3 hari → 3 baris `kasus_tugas` dibuat, `batch_total =
  3`, `batch_urutan` 1/2/3, masing-masing `mulai_pada = batas_selesai_pada` = tanggal
  berurutan.
- Konselor submit tugas `mingguan` rentang 5 hari (≤7) → fallback otomatis ke `harian`, 5 baris
  dibuat (bukan 1 baris mingguan).
- Konselor submit tugas `mingguan` rentang 30 hari → 5 baris (`7,7,7,7,2` hari), blok terakhir
  bukan gabungan 9 hari.
- Konselor submit tugas `bulanan` rentang 25 hari (≤30) → fallback ke `mingguan`; kalau hasil
  fallback itu sendiri ≤7 hari, fallback berantai sampai `harian`.
- Konselor submit tugas `bulanan` rentang 4 bulan dengan `tanggal_pengumpulan_bulanan = 15` →
  4 baris, masing-masing `batas_selesai_pada` jatuh di tanggal 15 bulan berjalan, baris terakhir
  dipotong (`min()`) ke `tanggal_selesai` form kalau lebih awal dari tanggal 15.
- Konselor submit tugas `bulanan` dengan opsi "hari terakhir bulan" pada bulan Februari →
  `batas_selesai_pada` = 28 atau 29 Februari sesuai tahun kabisat (`Carbon::endOfMonth()`).
- `tanggal_pengumpulan_bulanan` dikosongkan padahal frekuensi akhir = bulanan → error validasi.
- `tanggal_pengumpulan_bulanan` diisi padahal frekuensi akhir (setelah fallback) = mingguan/
  harian → diabaikan/tidak disimpan, tidak menyebabkan error validasi.
- Endpoint preview mengembalikan hasil identik (jumlah baris, tanggal tiap baris, frekuensi
  akhir) dengan apa yang benar-benar dibuat saat submit sungguhan — dijalankan lewat pemanggilan
  service yang sama, bukan diuji dengan asersi terpisah yang bisa diam-diam berbeda.
- Setiap baris hasil batch tetap tunduk pada aturan yang sudah ada sebelumnya tanpa perubahan:
  guard status kasus (`Ditugaskan`/`Berjalan`/`Eskalasi` saja), `assertKonselorPemegangKasus()`,
  transisi `Ditugaskan → Dikerjakan` per baris saat submission pertama masuk, guard trashed
  kasus, dan job `terlewat` (tidak menimpa baris yang sudah ada submission).
- Regresi: `frekuensi = sekali` tetap menghasilkan tepat 1 baris dengan `mulai_pada`/
  `batas_selesai_pada` identik dengan yang diisi form (tidak terpengaruh sama sekali oleh
  perubahan ini).

## Penyederhanaan yang Disepakati (v1)

- **Tidak ada aksi bulk "selesaikan semua baris dalam satu batch sekaligus"** — tombol "Tandai
  Selesai" tetap per-baris seperti sekarang. Kalau kebutuhan ini muncul nanti, bisa dibangun di
  atas `batch_id` yang sudah ada tanpa perubahan skema lagi.
- **Konselor tidak bisa menambah beberapa definisi tugas berbeda dalam satu kali submit form**
  (satu submit = satu definisi = satu batch). Kalau perlu tugas harian DAN tugas mingguan
  sekaligus, submit form dua kali.
- **Tidak ada migrasi data lama** — karena seluruh data kasus/tugas saat ini adalah data `demo`,
  perubahan skema ini destruktif terhadap data lama (drop kolom `tanggal`) dan diikuti
  `migrate:fresh --seed`, bukan migrasi mundur yang menjaga baris lama tetap valid.
- **`Carbon::diffInDays` dipakai untuk ambang validasi (bukan `diffInMonths`)** — `diffInMonths`
  ambigu untuk bulan dengan jumlah hari berbeda (Februari vs Januari), sementara ambang "> 30
  hari"/"> 7 hari" yang diminta eksplisit berbasis hari, bukan unit kalender bulan/minggu.

## Di Luar Cakupan

- Reminder/notifikasi otomatis H-1 sebelum jatuh tempo tiap baris bulanan (`SesiReminderNotification`
  pattern dari Sub-proyek 6 khusus untuk sesi, belum ada padanan untuk tugas — bisa dibangun
  terpisah nanti kalau dibutuhkan).
- Mengedit satu definisi batch setelah baris-barisnya ter-generate (mis. mengubah tanggal
  jatuh tempo bulanan di tengah jalan) — v1 hanya generate sekali di awal; kalau perlu diubah,
  konselor menghapus (belum ada UI hapus-tugas-individual — di luar cakupan juga) dan submit
  ulang secara manual.
- Laporan/ringkasan progres batch (mis. "3 dari 7 hari sudah dikerjakan, 2 revisi") — data
  dasarnya (`batch_id`, `status` per baris) sudah cukup untuk membangun ini nanti, tidak
  termasuk cakupan v1.
