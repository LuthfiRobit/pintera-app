# Handoff Log — Konsolidasi Jenis Tagihan (Sasaran, Tarif, Keringanan) & Engine Recalculate

**Tanggal**: 2026-09-01  
**Branch**: `keuangan-v2`  
**Base Commit**: `8c88a55a` (awal SDD: `0050f291` / `8c88a55a`)  
**Head Commit**: `05344997`  
**Dokumen Terkait**:
- Spec: `.agents/specs/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md`
- Plan: `.agents/plans/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md`
- Progress Ledger: `.superpowers/sdd/progress-recalc.md`

---

## 1. Apa yang Dikerjakan

Telah diimplementasikan secara menyeluruh 9 Tahap (17 Task) Subagent-Driven Development (SDD) dengan metodologi TDD ketat untuk fitur **Konsolidasi Konfigurasi Jenis Tagihan & Engine Recalculate Otomatis**:

1. **Stage 1 (Task 1)**: Hapus field mati `'lembaga'` dari kriteria sasaran/tarif karena Jenis Tagihan sudah di-scope per lembaga, serta memperketat validasi kriteria `'kelas'` agar hanya menerima ID kelas milik lembaga aktif.
2. **Stage 2 (Task 2)**: Membuat service `TagihanStatusResolver` tunggal untuk menentukan status tagihan (`lunas`, `sebagian`, `belum_bayar`, mempertahankan `dibatalkan`) dan merefaktor `PaymentAllocationService` agar menggunakan resolver ini.
3. **Stage 3 (Task 3–5)**:
   - Migration kolom `perlu_ditinjau_ulang` (boolean, default false) dan `alasan_perlu_ditinjau` (text nullable) pada tabel `tagihan`.
   - Action `RecalculateTagihanNominalAction` dengan guard overpayment (`net_amount` baru < `paid_amount`), guard skema cicilan aktif, guard `tagihable_type === Siswa::class` (no-op untuk PPDB `Pendaftaran`), dan concurrency locking via `lockForUpdate()`.
   - Action `SelesaikanTinjauanTagihanAction` beserta route `POST admin/tagihan/{tagihan}/selesai-ditinjau` untuk membebaskan flag tinjauan setelah ditangani manual oleh bendahara.
4. **Stage 4 (Task 6)**: Migration kolom `priority` pada `jenis_tagihan_sasaran_grup` beserta backfill deterministik berbasis urutan `id` per jenis tagihan, serta mengubah `TagihanNominalResolver::resolveNominal()` untuk mengevaluasi tarif grup berdasarkan `orderBy('priority')`.
5. **Stage 5 (Task 7)**: Kolom `bisa_digabung` pada `kategori_keringanan` dan logic kalkulasi diskon di `TagihanNominalResolver::resolveDiscount()` yang menjumlahkan diskon combinable di atas diskon non-combinable tertinggi dengan batas atas total nominal (`clamp`).
6. **Stage 6 (Task 8)**: Refactor `SyncJenisTagihanBillingConfigAction` agar *diff-aware* (hanya mendeteksi perubahan riil pada nominal tarif atau nilai/tipe keringanan) dan mengembalikan DTO `SyncBillingConfigResult` guna mencegah *recalc storm*.
7. **Stage 7 (Task 9–11)**:
   - Job `RecalculateTagihanNominalJob` untuk recalculate asynchronous 1 job per tagihan.
   - Trigger #2 & #3 di `JenisTagihanController::update()` saat tarif/keringanan berubah.
   - Trigger #1 di `SiswaKeringananController::store()` dan `destroy()` saat keringanan siswa ditambah/dihapus (recalc sinkron dengan query `tagihable_type = Siswa::class`).
   - Trigger #4 via `ReorderTarifGrupAction` dan route `PATCH admin/jenis-tagihan/{jenisTagihan}/tarif-grup/reorder` yang dispatch recalculate langsung saat urutan prioritas tarif digeser.
8. **Stage 8 (Task 12–14)**:
   - `TagihanDirevisiNotification` (database, mail, WhatsApp template `tagihan_direvisi`) yang dikirim ke kontak utama wali murid jika `net_amount` berubah.
   - Activitylog tracking pada model `Tagihan` dan badge counter review di topbar layout.
   - Halaman admin "Tagihan Perlu Ditinjau" (`GET admin/tagihan/perlu-ditinjau`) lengkap dengan tombol penyelesaian review.
9. **Stage 9 (Task 15–17)**:
   - Endpoint Live Preview Target Sasaran (`POST admin/jenis-tagihan/preview-sasaran`).
   - Endpoint Live Preview Tarif & Keringanan (`POST admin/jenis-tagihan/preview-tarif-keringanan`).
   - UI form Jenis Tagihan terintegrasi: counter sasaran siswa dinamis, counter tarif & keringanan, tombol reorder prioritas tarif (&uarr;&darr;), serta modal pembuatan Kategori Keringanan inline.

---

## 2. Keputusan Penting yang Diambil

1. **Polymorphic Tagihable Guard**: `RecalculateTagihanNominalAction` secara eksplisit memeriksa `$tagihan->tagihable_type !== Siswa::class` pada baris pertama dan langsung mengembalikan no-op tanpa exception untuk tagihan PPDB (`Pendaftaran`).
2. **Trigger #1 Query Binding**: Query untuk trigger penambahan/pembatalan keringanan siswa secara ketat menggunakan `where('tagihable_type', Siswa::class)->where('tagihable_id', $siswa->id)`, **bukan** `person_id`, sesuai 11 Critical Decisions.
3. **Pemisahan Reorder Tarif Endpoint**: Urutan prioritas tarif disimpan langsung via API terpisah `PATCH .../tarif-grup/reorder` dan men-dispatch job recalculate independen dari submit form create/edit utama.
4. **Perlindungan Overpayment & Skema Cicilan**: Jika kalkulasi ulang menghasilkan `net_amount` < `paid_amount` atau jika tagihan terhubung dengan skema cicilan aktif (`skemaCicilan()->exists()`), sistem menandai `perlu_ditinjau_ulang = true` beserta deskripsi alasan tanpa memodifikasi nominal tagihan secara sepihak.
5. **Idempotensi Sync Billing Config**: Perubahan nama sasaran atau penataan ulang kriteria yang nilainya sama tidak memicu recalculate tagihan existing.

---

## 3. Hasil Verifikasi & Testing

- **Suite Keuangan**: 352 passing (865 assertions, 0 failed).
- **Suite Keseluruhan**: 2,642 passing (7,218 assertions, 0 failed).
- **Code Style (Laravel Pint)**: 100% lulus format standar project (`vendor/bin/pint --format agent`).
- **Frontend Bundle**: Vite production build (`npm run build`) berhasil tanpa error (`public/build/assets/app-*` up-to-date).

---

## 4. Hal yang Perlu Direview Manusia / Claude

1. ~~**WhatsApp Notification Provider Staging**~~ — **DIKOREKSI 2026-09-02, BUKAN masalah.** Sudah diverifikasi langsung: `WhatsAppChannel::send()` mengirim teks bebas ke `https://api.fonnte.com/send` tanpa ID template apapun, dan `WhatsAppTemplate::renderKode()` cuma `strtr()` lokal terhadap tabel `whatsapp_template` — tidak pernah memanggil API provider manapun untuk registrasi template. Fonnte (provider yang memang dipakai) bukan WhatsApp Business Cloud API resmi yang mewajibkan template pre-approved. Template `tagihan_direvisi` sudah otomatis siap pakai, dan bisa diedit lewat halaman admin `WhatsAppTemplateController` kalau perlu. Tidak ada tindakan lanjutan diperlukan.
2. **Status Git Saat Ini**:
   - Branch: `keuangan-v2`
   - Semua perubahan tersimpan rapi dalam commit-commit atomik per task.
   - Belum di-push ke remote (siap untuk direview dan di-merge/push sesuai workflow branch management tim).

---

## 5. Hasil Review Pasca-Implementasi (2026-09-01)

Review kode dilakukan langsung (bukan cuma percaya ringkasan handoff di atas) terhadap semua 11 keputusan kritis di `.agents/kickoff/2026-09-01-jenis-tagihan-recalc-kickoff.md`, commit range `0050f291..05344997`. Semua CONFIRMED, tidak ada bug ditemukan:

- Guard `tagihable_type !== Siswa::class` di `RecalculateTagihanNominalAction.php:35-37` — no-op murni, urutan cek benar (setelah lockForUpdate + status lunas/dibatalkan).
- Query trigger #1 (`SiswaKeringananController::store()/destroy()`) memang pakai `tagihable_type`+`tagihable_id`, tidak ada `person_id` di jalur recalc manapun.
- Guard overpayment & guard cicilan bekerja benar (OR'd, cicilan selalu memaksa jalur `perlu_ditinjau_ulang` terlepas hasil overpayment).
- `TagihanStatusResolver` benar-benar jadi satu-satunya sumber transisi status, dipanggil di kedua tempat (`PaymentAllocationService::allocate()` & `RecalculateTagihanNominalAction`).
- `lockForUpdate()` dipasang sebelum `paid_amount` dibaca.
- `SyncJenisTagihanBillingConfigAction` genuinely diff-aware (snapshot before/after delete-recreate); test regresi "save tanpa sentuh tarif/keringanan → 0 job" ada dan tidak vakum (dibuktikan test sebelahnya yang memverifikasi job memang terkirim saat rule berubah).
- Trigger #4 (`ReorderTarifGrupAction`) benar memotong jalur diff §5.5, dispatch recalc langsung.
- Semua trigger bulk (#2/#3/#4) dispatch 1 job per tagihan, tidak ada job besar yang loop.
- `SiswaKeringananController` tetap jadi endpoint aktif (bukan diganti), widget form baru di Stage 9 murni tambahan pintu masuk.
- `resolveDiscount()`/`resolveNominal()` cocok persis dengan algoritma di spec (best-non-combinable + sum-combinable, clamp; orderBy priority, backfill via ROW_NUMBER partition).

**Update mandiri implementer yang bagus, tidak perlu diperbaiki**:
- `JenisTagihan::$syncBillingConfigResult` dideklarasikan sebagai **typed public property biasa** di model (bukan dynamic property/`#[AllowDynamicProperties]` seperti disarankan plan) — pilihan ini lebih baik karena otomatis tidak ikut ter-serialize ke `toArray()`/`toJson()` (relevan karena `JenisTagihanController::update()` mengembalikan JSON response di request yang sama). Dipertahankan apa adanya.
- Halaman "Tagihan Perlu Ditinjau" (`TagihanController::perluDitinjau()`) melakukan filter lembaga lintas 3 jalur relasi (jenisTagihan/pendaftaran/Siswa morph), lebih luas dari scope recalc yang cuma Siswa-only — masuk akal untuk halaman review umum (bisa saja tagihan PPDB di-flag lewat mekanisme lain di masa depan), tidak mempengaruhi invariant recalc manapun. Dipertahankan apa adanya.

**Kesimpulan**: implementasi sesuai spec dan plan tanpa penyimpangan berisiko. Siap lanjut ke tahap merge/push sesuai keputusan user.

---

## 6. Gap Ditemukan & Ditutup (2026-09-01, sesi lanjutan)

Saat menjelaskan alur form ke user, ditemukan **Task 17 (plan) tidak selesai**: widget assignment siswa-ke-kategori-keringanan langsung di form Jenis Tagihan tidak pernah dibangun oleh implementer. Yang sudah ada sebelumnya cuma live preview counter dan modal "Buat Kategori Baru" (nama kategori saja) — meng-assign siswa TERTENTU ke kategori masih harus lewat halaman edit Siswa lama, padahal ini permintaan inti user di awal paket ini ("tak perlu ke halaman edit siswa untuk mengatur diskon atau apapun").

**Ditutup pada commit ini** (belum di-commit terpisah saat log ini ditulis, akan menyusul commit `feat(keuangan): add in-form siswa-to-keringanan assignment widget`):

1. Endpoint baru `POST admin/jenis-tagihan/preview-siswa-keringanan` (`JenisTagihanController::previewSiswaKeringanan()`) — mengembalikan daftar siswa yang cocok dengan draft Target Sasaran form ini, beserta map assignment keringanan mereka saat ini (`{siswa: [{id, nama, assignments: {kategori_id: siswa_keringanan_id}}]}`).
2. `SiswaKeringananController::store()`/`destroy()` sekarang JSON-aware (`$request->wantsJson()`) tanpa mengubah endpoint/permission/guard-nya sama sekali — cuma menambah cabang response, backend assignment-nya TETAP endpoint lama yang sudah ada (sesuai keputusan desain awal: tidak bikin backend baru untuk assignment).
3. Widget di kartu "Keringanan & Potongan Biaya" (`form.blade.php` + `jenis-tagihan-form.js`): tombol "Kelola Assignment Siswa" membuka panel tabel siswa (dengan filter pencarian nama) × kategori keringanan, checkbox per sel yang langsung memanggil endpoint store/destroy di atas via AJAX.

Test baru: `JenisTagihanPreviewSiswaKeringananTest.php`, `JenisTagihanFormKeringananWidgetTest.php`, plus 2 test tambahan di `SiswaKeringananControllerTest.php` untuk mode JSON. Regresi diverifikasi: 425 test keuangan/jenis-tagihan/siswa-keringanan terkait — semua PASS, 0 gagal. Full suite proyek dijalankan ulang secara langsung (bukan cuma dipercaya) sebelum dan sesudah gap-closing ini, keduanya hijau (~2644 passed).

Dengan ini, permintaan inti user ("semua pengaturan Jenis Tagihan, termasuk assign siswa ke keringanan, selesai di 1 form") sudah tercapai penuh.

---

## 7. Analisa Sisi Siswa/Orang Tua & Penutupan Gap Risiko (2026-09-01, sesi lanjutan kedua)

Diminta user menganalisa bagaimana portal Ruang Orang Tua (`/keuangan`, BUKAN `/portal` PPDB) berinteraksi dengan engine recalculate yang baru. Temuan (via Explore subagent, diverifikasi baca kode langsung):

1. `perlu_ditinjau_ulang` 100% admin-only — tidak ada satupun view/controller di `portals/portal/keuangan/**` yang query kolom ini.
2. **Risiko nyata**: tagihan yang gagal guard recalc (overpayment/cicilan/lunas) tetap tampil dengan nominal LAMA apa adanya di dashboard/list orang tua, tanpa keterangan sedang ditinjau — orang tua berisiko membayar nominal yang sebentar lagi direvisi.
3. `TagihanDirevisiNotification` sampai tapi tenggelam di bell notifikasi generik (sama seperti notifikasi finance lain, tanpa diferensiasi/link balik).
4. Tidak ada breakdown tarif vs diskon di UI orang tua — tidak transparan kenapa nominal segitu.

**Ditutup pada sesi ini (poin #2, risiko finansial paling nyata)**:

- `CheckoutController::create()` dan `resolveSelectedTagihan()` (dipakai `wallet()`/`qris()`/`transfer()`/`vaInfo()`) sekarang menambahkan `->where('perlu_ditinjau_ulang', false)` — tagihan yang sedang ditinjau TIDAK BISA dipilih untuk dibayar lewat jalur apapun (wallet/QRIS/VA/transfer manual), baik lewat UI normal maupun request yang di-craft manual.
- Pesan error mismatch-jumlah tagihan ("Sebagian tagihan yang dipilih sudah lunas...") diperluas jadi "...sudah lunas atau sedang ditinjau ulang oleh admin..." supaya tetap akurat.
- `resources/views/portals/portal/keuangan/dashboard.blade.php` dan `tagihan/index.blade.php`: tagihan yang di-flag tetap TAMPIL di list (transparansi bahwa tagihan itu ada), tapi checkbox-nya disabled dan ada badge "Sedang Ditinjau" + pesan umum "Nominal sedang ditinjau ulang oleh admin, sementara belum bisa dibayar." — **alasan detail teknis (`alasan_perlu_ditinjau`) sengaja TIDAK diekspos ke orang tua**, cuma admin yang lihat detailnya di halaman "Tagihan Perlu Ditinjau".

Poin #3 (notifikasi tenggelam) dan #4 (breakdown tarif/diskon) BELUM dikerjakan — disepakati sebagai peningkatan UX terpisah, bukan risiko finansial mendesak, menunggu prioritas user berikutnya.

Test baru: 2 di `TagihanControllerTest.php`/`DashboardControllerTest.php` (badge + non-leak alasan), 1 di `CheckoutControllerWalletTest.php`, 1 di `CheckoutControllerCreateTest.php`. Semua PASS, regresi 35 test controller terkait PASS, full suite proyek dijalankan ulang untuk verifikasi akhir.

---

## 8. Poin #3 & #4 Dikerjakan: Halaman Detail Tagihan + Deep-Link Notifikasi (2026-09-01, sesi lanjutan ketiga)

Spec: `.agents/specs/2026-09-01-portal-ortu-detail-tagihan-notifikasi.md`. Scope kecil & rendah risiko (murni tampilan baca, tidak menyentuh nominal/pembayaran) — dikerjakan langsung tanpa plan terpisah, TDD per fitur.

**Temuan penting yang mengecilkan scope**: semua notifikasi finance (`TagihanDiterbitkanNotification`, `PembayaranBerhasilNotification`, `SaldoTidakCukupNotification`, `DueReminderNotification`, `TagihanDirevisiNotification`) SUDAH menyimpan `tagihan_id` di payload `toDatabase()` — cuma 2 notifikasi transfer manual yang tidak. Jadi gap #3 murni masalah UI (bell tidak pernah memakai data yang sudah ada), bukan backend.

**Dikerjakan**:
1. Halaman detail baru `GET keuangan/tagihan/{tagihan}` (`TagihanController::show()`, view `portals/portal/keuangan/tagihan/show.blade.php`) — breakdown Nominal Awal/Potongan/Nominal Akhir/Sisa, banner "sedang ditinjau" kalau relevan (tanpa expose alasan teknis). Guard kepemilikan baru `AuthorizesTagihanAccess` trait (pola sama `AuthorizesPembayaran`) — cek SEMUA anak orang tua, bukan cuma `activeSiswa` yang sedang dipilih, supaya link lama tetap valid setelah ganti anak aktif.
2. Baris tagihan di `tagihan/index.blade.php` dan `dashboard.blade.php` jadi link ke halaman detail.
3. Bell notifikasi (`topbar.blade.php`, dipakai bersama admin+ortu) dan panel notifikasi khusus di `portals/portal/keuangan/dashboard.blade.php` (dua tempat terpisah, keduanya dulu render polos): keduanya sekarang menampilkan "Lihat Detail →" dan menavigasi ke halaman baru kalau notifikasi punya `tagihan_id`. Di topbar (shared), link digerbangi `Auth::user()->orangTua !== null` supaya tidak jadi link mati untuk admin.

**Gotcha ditemukan saat testing**: `@js()` Blade directive membungkus value jadi `JSON.parse('...')` dengan slash di-escape (`\/`) — assert URL literal di test HARUS pakai `Illuminate\Support\Js::from()` untuk hasil yang sama persis, bukan `route()` mentah (pola yang sama persis sudah didokumentasikan di `JenisTagihanSasaranFormTest.php` dari sesi sebelumnya, kena lagi di sini).

Test baru: 5 di `TagihanControllerTest.php` (breakdown, hide-zero-discount, banner tanpa leak, akses anak non-aktif, tolak orang tua lain), 2 di `TopbarNotificationBellTest.php`, 1 di `DashboardNotificationMarkAsReadTest.php`. Regresi: seluruh namespace `Keuangan` (368 test) PASS. Full suite proyek dijalankan ulang untuk verifikasi akhir.

Dengan ini, kedua gap transparansi orang tua yang tersisa dari §7 sudah ditutup. Status: Admin selesai, Orang Tua selesai (risiko + transparansi), Siswa tidak punya portal terpisah (di luar scope, belum pernah dibangun sama sekali).

---

## 9. Perbaikan UI Widget Assignment Siswa (2026-09-02)

User feedback: list siswa di widget Keringanan (Task 17 sebelumnya) kurang informatif (tidak ada info kelas) dan terlalu panjang ke bawah kalau siswa banyak. Diminta meniru pola tampilan modal "Generate Virtual Account" (`resources/views/portals/lembaga/keuangan/virtual-account/_generate-modal.blade.php`) yang sudah punya UX serupa: search + filter kelas + tabel scrollable.

**Backend** (`JenisTagihanController::previewSiswaKeringanan()`): tambah parameter opsional `search` (pakai `Siswa::search()` scope yang sudah ada) dan `kelas_id`, plus field `kelas` (nama kelas) di tiap baris hasil — pola query & response persis meniru `VirtualAccountController::calonGenerate()`.

**Frontend** (`jenis-tagihan-form.js` + `form.blade.php`): ganti filter client-side (`siswaKeringananListFiltered` getter) jadi server-side (fetch ulang saat search/kelas berubah, dengan debounce 400ms) — konsisten dengan pola `virtualAccountGenerateModal`. Tabel sekarang dibungkus `max-h-64 overflow-y-auto` (tidak lagi meluas tak terbatas ke bawah), header sticky, kolom baru "Kelas" antara Nama dan kolom-kolom kategori keringanan. Dropdown filter kelas reuse `referenceOptions.kelas` yang sudah ada di config form (tidak perlu data baru dari controller).

Test baru: 3 di `JenisTagihanPreviewSiswaKeringananTest.php` (kolom kelas, filter search, filter kelas_id). Regresi widget/preview test (9 test) PASS. Full suite proyek dijalankan ulang untuk verifikasi akhir.

---

## 10. Keputusan Bisnis: Cicilan Dibatasi ke Tampilan Form PPDB Saja (2026-09-02)

Diskusi lanjutan soal gap rekonsiliasi cicilan (§ sebelumnya) menemukan: `bisa_dicicil` secara struktur tersedia untuk SEMUA kategori Jenis Tagihan, tapi di data yang ada sekarang cuma pernah dipakai 1 kali untuk `daftar_ulang` (PPDB) — nol untuk SPP/Tahunan/Kegiatan/Lainnya.

**Keputusan user**: backend TIDAK diubah/dibatasi (tetap generik untuk kategori manapun, "karena memang sudah disiapkan untuk semua kategori") — tapi di **form admin**, opsi "Bisa Dicicil" hanya ditampilkan untuk kategori PPDB (`pendaftaran`/`daftar_ulang`), disembunyikan untuk kategori lain.

**Implementasi**: `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php` — blok checkbox "Bisa Dicicil" + field "Maksimal Jumlah Cicilan" dibungkus `<template x-if="kategoriPpdb">` (kebalikan dari pola Sasaran/Tarif/Keringanan yang dibungkus `!kategoriPpdb`). Tidak ada perubahan validasi backend, model, atau `PembayaranService` — murni pembatasan UI.

**Efek samping yang diterima sadar**: guard cicilan di `RecalculateTagihanNominalAction` (§5.2 spec awal) jadi praktis tidak akan pernah trigger lagi untuk tagihan baru, karena engine recalculate memang sudah tidak pernah menyentuh tagihan PPDB (`tagihable_type !== Siswa::class` guard). Guard itu TETAP DIPERTAHANKAN di kode sebagai defense-in-depth (bukan dihapus), untuk berjaga-jaga kalau ada data lama/edge-case yang lolos dari pembatasan form ini.

Test baru: `JenisTagihanCicilanFormVisibilityTest.php` (2 test, create & edit page) memverifikasi markup `x-if="kategoriPpdb"` membungkus field `bisa_dicicil`. Regresi 125 test JenisTagihan-related PASS. Full suite proyek dijalankan ulang untuk verifikasi akhir.

Dengan ini, gap "rekonsiliasi cicilan" dari §7-8 dianggap SELESAI lewat jalur pencegahan (tidak bisa dikonfigurasi lagi di luar PPDB), bukan lewat membangun UI reconciliation yang sempat diusulkan sebelumnya.

**Temuan tambahan yang menguatkan kesimpulan "selesai"**: ditelusuri lebih dalam, endpoint pembuatan skema cicilan (`TagihanController::buatSkemaCicilan()`) TERNYATA sudah mustahil dicapai untuk tagihan non-PPDB SEJAK SEBELUM sesi ini — `TagihanCicilanEligibilityService::bisaDicicil()` mengecek `$tagihan->item()` (relasi ke `TagihanItem`), dan `TagihanItem` cuma pernah diisi oleh `TagihanGenerator` yang cuma dipanggil untuk `Pendaftaran` (PPDB). Tagihan siswa reguler (jalur `TagihanBillingGenerator`) tidak pernah punya baris `TagihanItem`, jadi `bisaDicicil()` selalu `false` untuk mereka. Pembatasan form yang baru ditambahkan adalah lapis kedua (mencegah admin salah konfigurasi), bukan satu-satunya pertahanan.

## 11. Bug Kecil Ditemukan & Diperbaiki: Crash 500 (bukan 404) untuk Aksi Cicilan pada Tagihan Non-PPDB (2026-09-02)

Saat menelusuri poin di atas, ditemukan 4 method di `TagihanController` (`buatSkemaCicilan`, `simpanNominalCicilan`, `catatManualTagihan`, `catatManualCicilan`) semuanya mengakses `$tagihan->pendaftaran->lembaga_id` tanpa null-check. Karena endpoint ini memang tidak pernah reachable untuk tagihan non-PPDB lewat UI manapun (lihat temuan di atas), bug ini tidak pernah ter-trigger di praktik — tapi kalau ada yang mencoba akses paksa (id tagihan siswa reguler), hasilnya error PHP mentah (500), bukan 404 yang rapi.

**Perbaikan**: tambah helper `TagihanController::resolvePendaftaranLembagaId(Tagihan $tagihan): int` yang null-safe (`$tagihan->pendaftaran?->lembaga_id`, `abort_if(null, 404)`), dipakai di keempat method tersebut menggantikan akses langsung yang rawan crash.

Test baru: 4 test "404s (not crashes)" di `SkemaCicilanTest.php` (2) dan `CatatManualPembayaranTest.php` (2), masing-masing membuktikan tagihan siswa reguler mendapat 404 rapi, bukan exception. Regresi 27 test terkait PASS. Full suite proyek dijalankan ulang untuk verifikasi akhir.

---

## 12. Re-Screening Sisi Admin: 1 Bug Serupa Ditemukan & Diperbaiki (2026-09-02)

Diminta user re-screening sisi admin sebelum lanjut ke sisi orang tua. Dispatch review agent untuk mencari pola bug yang sama (null-dereference tanpa guard) di seluruh controller Keuangan admin, plus verifikasi ulang invariant engine recalculate & konsistensi validasi PPDB/non-PPDB setelah semua perubahan sesi ini.

**Hasil**: 4 dari 5 area CONFIRMED SAFE (validasi server-side `bisa_dicicil` tetap permisif sesuai desain, invariant `RecalculateTagihanNominalAction` tidak tersentuh oleh commit-commit terakhir, ke-4 method `TagihanController` yang sudah diperbaiki sebelumnya benar, tidak ada method ke-5 yang lolos di file itu, controller lain — `JenisTagihanController`/`KategoriKeringananController`/`SiswaKeringananController`/`VirtualAccountController`/`ManualPaymentController` — bersih).

**1 bug baru ditemukan**: `PembayaranController::verifikasi()` (`app/Http/Controllers/Lembaga/Keuangan/PembayaranController.php:100-101` sebelum fix) — pola identik dengan yang barusan diperbaiki di `TagihanController`: `$pembayaran->tagihan?->pendaftaran->lembaga_id ?? $pembayaran->cicilan->skemaCicilan->tagihan->pendaftaran->lembaga_id` crash kalau `$pembayaran->tagihan` adalah tagihan siswa reguler (non-PPDB, `pendaftaran` selalu null). Endpoint ini normalnya cuma di-link dari halaman detail Pendaftaran PPDB, tapi route `POST admin/pembayaran/{pembayaran}/verifikasi` tidak dibatasi jenis tagihan-nya — bisa dipanggil paksa dengan id pembayaran manapun.

**Perbaikan**: helper `resolvePendaftaranLembagaId(Pembayaran $pembayaran): int` (pola sama seperti di `TagihanController`, pakai `?->` berantai + `abort_if(null, 404)`).

Test baru: 1 test "404s (not crashes)" di `VerifikasiPembayaranTest.php`. Regresi 430 test Keuangan/Pembayaran-related PASS. Full suite proyek dijalankan ulang untuk verifikasi akhir.
