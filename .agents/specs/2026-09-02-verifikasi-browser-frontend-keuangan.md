# Spec: Verifikasi Browser Frontend Keuangan (Sesi 2026-09-01/02)

**Tanggal**: 2026-09-02
**Branch**: `keuangan-v2`
**Konteks**: Selama sesi 2026-09-01/02, modul Keuangan (billing reguler + engine recalculate) dikerjakan dengan TDD lewat Pest — semua test HTTP-assertion (`assertSee`, dsb) hijau. **Tidak ada satupun verifikasi browser nyata yang dilakukan** untuk UI yang dibangun/diubah sesi ini — sesi yang mengerjakan tidak punya akses tooling browser. Paket ini murni untuk menutup gap itu: jalankan aplikasi sungguhan, klik alur-alur di bawah, perbaiki apapun yang rusak.

## 1. Analisa Kode yang Sudah Dilakukan (sebelum paket ini)

Static-analysis penuh (baca kode, cross-reference tiap `x-model`/`x-show`/`@click` Alpine terhadap `x-data`/file JS) sudah dilakukan terhadap 10 file Blade/JS berikut, hasilnya **9 CONFIRMED SAFE secara struktur kode**, **1 bug ditemukan dan SUDAH DIPERBAIKI** (lihat §2):

1. `resources/views/portals/lembaga/keuangan/jenis-tagihan/form.blade.php`
2. `resources/js/jenis-tagihan-form.js`
3. `resources/views/portals/lembaga/keuangan/jenis-tagihan/_modal-kategori-baru.blade.php`
4. `resources/views/portals/lembaga/keuangan/tagihan/perlu-ditinjau.blade.php`
5. `resources/views/portals/lembaga/keuangan/jenis-tagihan/monitoring/index.blade.php`
6. `resources/views/portals/portal/keuangan/dashboard.blade.php`
7. `resources/views/portals/portal/keuangan/tagihan/index.blade.php`
8. `resources/views/portals/portal/keuangan/tagihan/show.blade.php`
9. `resources/views/layouts/topbar.blade.php`
10. `resources/views/layouts/sidebar.blade.php`

**Penting**: "CONFIRMED SAFE" di atas artinya struktur kode konsisten (tidak ada property Alpine yang salah rujuk, tidak ada `<form>` bersarang, route yang dipanggil benar-benar ada) — BUKAN berarti perilaku di browser sungguhan sudah dikonfirmasi benar. TomSelect re-render, animasi Alpine transition, urutan event `x-show`/`@click.outside`, clipboard API, dan pengalaman visual/responsif TIDAK bisa diverifikasi lewat pembacaan kode saja — itulah tugas paket ini.

**Temuan minor non-blocking** (dicatat, tidak perlu diperbaiki kecuali ditemukan masalah nyata saat verifikasi): `reorderTarifUrl`/endpoint `PATCH .../tarif-grup/reorder` tidak pernah dipanggil oleh `moveTarifUp`/`moveTarifDown` di `jenis-tagihan-form.js` — reorder tarif saat ini cuma persist lewat submit form penuh (index-based field names), bukan AJAX langsung. Ini bekerja secara fungsional untuk create/edit biasa, tapi endpoint dedicated-nya jadi sepertinya tidak terpakai dari UI. Verifikasi #3 di bawah akan mengonfirmasi apakah reorder+submit form biasa sudah cukup, atau memang ada ekspektasi AJAX-instan yang belum terpasang.

## 2. Bug yang SUDAH Ditemukan & Diperbaiki (di luar paket ini, referensi saja)

`app/Http/Controllers/Lembaga/Keuangan/TagihanController.php::koreksiNominal()` — halaman `perlu-ditinjau.blade.php` tidak pernah merender `$errors` sama sekali. Kalau admin submit "Koreksi Nominal" dengan `discount_amount > total_tagihan`, request redirect balik dengan validation error tapi TIDAK ADA pesan apapun yang terlihat — popover tertutup lagi (state Alpine `open: false` reset di setiap full-page load), admin tidak tahu kenapa koreksinya tidak diterapkan. **Sudah diperbaiki** (tambah blok `@if ($errors->any())` di atas halaman, commit terpisah sebelum paket ini) — verifikasi #7 di bawah tetap perlu mengonfirmasi perbaikan ini benar-benar terlihat di browser.

## 3. Non-Goals

- Tidak menyentuh apapun di luar 10 file/alur di atas.
- Tidak membangun ulang jalur reorder-tarif jadi AJAX kalau ternyata submit-form-penuh sudah cukup memadai secara UX — cuma laporkan temuannya, biarkan user memutuskan kalau memang perlu perubahan.
- Tidak mengaudit ulang backend/business-logic — itu sudah selesai di paket-paket sebelumnya (lihat `.agents/logs/2026-09-02-perbaikan-audit-billing-reguler.md`).
- Tidak menyentuh PPDB/SPMB — di luar scope, sengaja dibekukan.

## 4. Checklist Verifikasi Browser (20 alur, wajib dijalankan semua)

Gunakan skill `run` (project ini) untuk menjalankan aplikasi dan skill browser-driving yang tersedia (mis. Playwright, sesuai konvensi yang sudah pernah dipakai di proyek ini — lihat commit lama `f21f339c test(keuangan): add playwright verification` sebagai referensi pola). Login sebagai admin (`bendahara_lembaga`) untuk alur admin, dan sebagai `orang_tua` untuk alur portal orang tua.

1. **Form Jenis Tagihan — create lengkap**: buka `/lembaga/keuangan/jenis-tagihan/create`, pilih kategori "SPP", ganti Mode Otomatis/Manual dan Tipe (harian/mingguan/bulanan/tahunan/sekali), konfirmasi field tanggal/offset yang relevan muncul/hilang sesuai tipe, tanpa error console.
2. **Target Sasaran — kriteria dinamis**: pindah ke "Berdasarkan Kriteria Khusus", tambah Grup, tambah beberapa baris kriteria, ganti field (mis. dari `lembaga` ke `kelas`) berkali-kali — konfirmasi widget TomSelect me-refresh opsi dengan benar, tidak nyangkut opsi lama atau duplikat elemen.
3. **Tarif Berdimensi — reorder**: tambah 3+ Grup Tarif, klik ↑/↓ berkali-kali, konfirmasi urutan visual berubah dan preview "Hitung Siswa" tetap merujuk ke grup yang benar setelah diurutkan ulang (risiko index-binding). **Sekaligus konfirmasi**: apakah reorder ini perlu di-submit lewat tombol Simpan form, atau ada ekspektasi tersimpan otomatis — laporkan temuannya (lihat catatan `reorderTarifUrl` di §1).
4. **Keringanan — modal "Buat Kategori Baru"**: buka modal, submit kategori baru, konfirmasi langsung muncul di dropdown Keringanan tanpa reload, modal tertutup, toast sukses tampil.
5. **Widget "Kelola Assignment Siswa"**: buka panel, cari/filter by kelas, centang/hilangkan centang keringanan seorang siswa, konfirmasi checkbox merefleksikan state dengan benar (tidak kedip/reset), dan state disabled-saat-toggle cuma menonaktifkan 1 checkbox yang sedang diproses, bukan seluruh baris.
6. **Field `priority_score`**: isi angka, simpan, buka lagi halaman edit, konfirmasi nilainya tersimpan & muncul kembali.
7. **Perlu Ditinjau — Koreksi Nominal, jalur sukses**: klik "Koreksi Nominal", isi total/potongan valid, submit, konfirmasi redirect menampilkan pesan "Nominal tagihan berhasil dikoreksi." dan tagihan hilang dari daftar (flag ter-clear).
8. **Perlu Ditinjau — Koreksi Nominal, jalur GAGAL validasi (prioritas tinggi, ini yang barusan diperbaiki)**: isi `discount_amount` lebih besar dari `total_tagihan`, submit, **konfirmasi pesan error SEKARANG terlihat** (banner merah di atas halaman) — bukan cuma redirect diam-diam seperti sebelum perbaikan.
9. **Perlu Ditinjau — dismiss popover**: buka "Koreksi Nominal", klik di luar popover → tertutup; klik di DALAM area input popover → TIDAK tertutup (`@click.outside` harus benar).
10. **Monitoring — tab & modal batalkan**: pindah tab "Daftar Penerima"/"Daftar Tunggakan", klik "Batalkan" di baris `belum_bayar`, konfirmasi modal terbuka dengan `cancelUrl` baris yang benar, dan membatalkan 1 baris tidak "bocor" ke modal baris lain saat dibuka berikutnya.
11. **Dashboard Orang Tua — notifikasi**: klik notifikasi ber-`tagihan_id` → tertandai terbaca (titik hilang, `unreadCount` berkurang) DAN navigasi ke halaman detail tagihan; klik notifikasi TANPA `tagihan_id` → cuma tertandai terbaca, tidak navigasi.
12. **Dashboard Orang Tua — "Tandai Semua Terbaca"**: klik, semua titik hilang, tombolnya sendiri ikut hilang (guard `unreadCount > 0`).
13. **Dashboard Orang Tua — modal Top Up**: buka/tutup lewat tombol & klik backdrop, konfirmasi "Salin VA" menyalin ke clipboard + toast (perhatikan izin clipboard di browser, cek tidak ada error console permission).
14. **Dashboard Orang Tua — pilih tagihan & "Bayar Terpilih"**: pilih beberapa tagihan (konfirmasi yang `perlu_ditinjau_ulang` memang disabled/tidak bisa dipilih), konfirmasi jumlah terpilih dan query string URL checkout (`tagihan_ids[]=...`) sesuai pilihan.
15. **Daftar Tagihan Orang Tua — filter tab**: toggle "Semua"/"Jatuh Tempo", konfirmasi `selected` ke-reset saat ganti tab dan hitungan cocok dengan item yang benar-benar terlambat.
16. **Daftar Tagihan Orang Tua — checkbox pilih semua**: klik checkbox header, konfirmasi cuma memilih/membatalkan item yang bisa dipilih (exclude `perlu_ditinjau_ulang`), dan checked-state header merefleksikan pilihan sebagian/penuh dengan benar.
17. **Daftar Tagihan Orang Tua — link "Lihat Detail"**: klik nama tagihan, konfirmasi navigasi ke halaman detail dengan angka breakdown yang cocok.
18. **Topbar — bell notifikasi lintas role**: cek untuk user yayasan & non-yayasan, dan untuk orang tua vs bukan orang tua (link "Lihat Detail" cuma boleh muncul untuk orang tua).
19. **Topbar — badge "Tagihan Perlu Ditinjau"**: sebagai admin ber-`tagihan.view`/`tagihan.edit`, konfirmasi angka badge dan tujuan link cocok dengan data sebenarnya.
20. **Sidebar — konfirmasi menu PPDB benar-benar hilang**: login sebagai admin yang tadinya punya `tagihan.view`/`pembayaran.view`, konfirmasi tidak ada link "Tagihan"/"Verifikasi Pembayaran" di sidebar manapun, dan TIDAK ADA PHP warning/error di halaman admin manapun (sanity check comment-out array tidak merusak parsing).

## 5. Definition of Done

- Semua 20 alur di atas benar-benar diklik/diuji lewat browser sungguhan (bukan cuma dibaca kodenya).
- Setiap bug yang ditemukan selama verifikasi diperbaiki dengan TDD (test dulu kalau bisa ditulis test Pest-nya, kalau murni bug interaksi browser yang tidak bisa di-assert lewat Pest — cukup screenshot/rekaman before-after sebagai bukti perbaikan).
- Full test suite (`php artisan test --compact`, dijalankan SENDIRIAN — jangan sampai ada 2 proses `php artisan test` berjalan bersamaan, insiden sebelumnya di proyek ini pernah menyebabkan ratusan false failure lewat `SQLSTATE[HY000]: 1412 Table definition has changed`) tetap hijau di akhir.
- Handoff log baru ditulis merangkum hasil tiap dari 20 alur (lolos / ada bug+diperbaiki / catatan).
