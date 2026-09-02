# Kickoff — Verifikasi Browser Frontend Keuangan

Kamu (agent baru) tidak punya akses ke diskusi panjang yang menghasilkan dokumen ini. Baca file ini secara lengkap sebelum menyentuh apapun. **Paket ini dipilihkan khusus untuk kamu karena kamu punya akses tooling browser (Playwright atau setara) — sesi sebelumnya tidak punya akses itu, jadi seluruh pekerjaan sesi ini murni "bisa dilihat/diklik nyata" yang belum pernah terjadi sama sekali untuk modul yang dikerjakan.**

## Konteks

Repo: `d:\laragon\www\pintera-app` (Laravel 12, aplikasi pendidikan multi-tenant "Pintera"). Branch: `keuangan-v2`. Base commit sebelum paket ini: cek `git log -1` di branch tersebut — commit terakhir sebelum paket ini menambahkan sebuah bug-fix kecil (`$errors` tidak dirender di halaman Perlu Ditinjau).

Selama sesi 2026-09-01/02, modul Keuangan (billing untuk siswa reguler, bukan PPDB) dikerjakan besar-besaran: form Jenis Tagihan (Sasaran/Tarif/Keringanan), engine recalculate otomatis, halaman review "Perlu Ditinjau" + aksi koreksi nominal manual, monitoring per-Jenis-Tagihan, dan berbagai perbaikan di portal orang tua (dashboard, daftar tagihan, checkout guard). **Semua dikerjakan dengan TDD lewat Pest (test HTTP-assertion server-side) — TIDAK ADA satupun verifikasi browser nyata yang pernah dilakukan untuk semua UI ini.**

## Dokumen yang WAJIB dibaca, urutan ini

1. **Spec (analisa kode + daftar 20 alur verifikasi lengkap):** `.agents/specs/2026-09-02-verifikasi-browser-frontend-keuangan.md`
2. **Plan implementasi (6 task, breakdown per halaman):** `.agents/plans/2026-09-02-verifikasi-browser-frontend-keuangan.md`

Baca PLAN-nya secara utuh dulu sebelum mulai task pertama.

## Yang PENTING kamu pahami sebelum mulai

1. **Static-analysis kode SUDAH dilakukan** terhadap 10 file Blade/JS terkait — hasilnya 9 "confirmed safe secara struktur", 1 bug sudah ditemukan DAN DIPERBAIKI (halaman Perlu Ditinjau tidak pernah menampilkan pesan error validasi — sudah ditambal). Tugasmu BUKAN membaca ulang kode dari nol, tapi benar-benar MENJALANKAN dan MENGKLIK 20 alur yang tercantum di spec §4 — banyak jenis bug (TomSelect re-render, urutan event `@click.outside`, clipboard API, race condition Alpine) TIDAK BISA ditemukan lewat membaca kode saja.
2. **Ini bukan sesi membangun fitur baru.** Kalau sebuah alur berjalan sesuai spec, JANGAN diubah/di-refactor/"diperbaiki" tanpa alasan — tugasmu murni menemukan yang RUSAK, bukan mempercantik yang sudah benar.
3. **PPDB/SPMB di luar scope, sengaja dibekukan** — jangan disentuh sama sekali, meski kelihatan berhubungan.
4. **Reorder Tarif Berdimensi (Task 1, Verifikasi #3)**: ada temuan minor bahwa endpoint `PATCH .../tarif-grup/reorder` sepertinya tidak dipanggil oleh JS (reorder cuma persist lewat submit form penuh). **Ini kemungkinan besar memang desainnya begitu, BUKAN otomatis bug** — kalau kamu temukan ini saat verifikasi, cukup laporkan temuannya dengan jelas (apakah UX-nya terasa membingungkan atau tidak), JANGAN langsung membangun ulang jadi AJAX sendiri. Itu keputusan user, bukan keputusanmu.
5. **Full test suite (`php artisan test --compact`) WAJIB dijalankan SENDIRIAN** — proyek ini pernah mengalami insiden ratusan false-failure gara-gara 2 proses `php artisan test` berjalan bersamaan (migrasi RefreshDatabase 2 proses bentrok di database yang sama, error `SQLSTATE[HY000]: 1412 Table definition has changed`). Kalau kamu menjalankan test sambil proses lain (mis. dev server untuk browser testing) juga aktif, pastikan dev server TIDAK menjalankan test-nya sendiri secara bersamaan.

## Kalau ada ambiguitas atau menemukan sesuatu yang mencurigakan

**STOP dan laporkan ke user — JANGAN mengambil keputusan desain baru sendiri di tengah verifikasi.** Terutama untuk:
- Sesuatu yang terlihat seperti bug tapi kamu tidak yakin apakah itu memang perilaku yang diinginkan (mis. soal reorder-tarif di atas).
- Sebuah alur di checklist yang ternyata TIDAK BISA dijalankan sama sekali karena data uji sulit disiapkan, atau ada dependency yang hilang.
- Bug yang ditemukan ternyata cukup besar sehingga perbaikannya butuh keputusan arsitektur (bukan sekadar 1-2 baris kode).

## Mulai dari mana

Plan sudah dipecah jadi 6 task (per area halaman), masing-masing berisi langkah verifikasi konkret dari checklist 20 alur di spec §4. Kerjakan task 1 sampai 6 berurutan. Untuk tiap bug yang ditemukan: tulis test Pest kalau bisa dibuktikan lewat assertion server-side, atau screenshot before/after kalau murni bug interaksi browser. Setelah task terakhir, jalankan full suite + Pint + build sebagai langkah penutup, lalu tulis handoff log di `.agents/logs/2026-09-02-verifikasi-browser-frontend-keuangan.md` merangkum hasil ke-20 alur.
