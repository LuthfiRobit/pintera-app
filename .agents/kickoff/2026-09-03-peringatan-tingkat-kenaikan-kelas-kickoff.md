# Kickoff — Peringatan Validasi Tingkat di Kenaikan Kelas (Kelompok B)

Kamu (agent baru) tidak punya akses ke diskusi panjang yang menghasilkan dokumen ini. Baca file ini secara lengkap sebelum menyentuh kode apapun.

## Konteks

Repo: `d:\laragon\www\pintera-app` (Laravel 12 / PHP 8.3, aplikasi pendidikan multi-tenant "Pintera"). Branch kerja: `akademik-v2` (LANJUT DI BRANCH INI, jangan buat branch baru). Base commit sebelum paket ini: `9b641e13`.

Ini adalah **Kelompok B** dari audit bisnis Kenaikan Kelas — topik SEPENUHNYA TERPISAH dari **Kelompok A** (siklus hidup `kelas_id` siswa, sudah SELESAI diimplementasikan di branch yang sama, lihat commit `8654c8ce`..`81ec96a3`). Jangan bingung keduanya; paket ini TIDAK bergantung pada Kelompok A sama sekali.

`ProsesKenaikanKelasAction` dan UI-nya tidak pernah memvalidasi/memperingatkan hubungan tingkat antara kelas asal dan kelas tujuan — admin bisa memetakan siswa ke tingkat yang sama (tinggal kelas), lompat, atau bahkan mundur tanpa sinyal apapun. Paket ini menambahkan **peringatan non-blocking** di UI (bukan validasi backend) untuk kasus tingkat yang tidak wajar.

## Dokumen yang WAJIB dibaca, urutan ini

1. **Spec (keputusan sudah final & disetujui lewat brainstorming, termasuk audit kode konkret di setiap keputusan):** `.agents/specs/2026-09-03-peringatan-tingkat-kenaikan-kelas.md`
2. **Plan implementasi (3 task, TDD, kode lengkap):** `.agents/plans/2026-09-03-peringatan-tingkat-kenaikan-kelas.md`

Baca PLAN-nya secara utuh dulu sebelum mulai task pertama.

## Keputusan Kritis yang TIDAK BOLEH diubah tanpa konfirmasi ulang ke user

Semua ini hasil brainstorming panjang dengan audit kode konkret di setiap langkah — jangan didesain ulang sendiri kalau menemui sesuatu yang "kelihatannya bisa lebih baik dengan cara lain":

1. **Warning non-blocking SAJA — TIDAK ADA validasi/penolakan backend apapun.** `ProsesKenaikanKelasAction` TIDAK BOLEH disentuh. Kasus mundur tingkat, lompat tingkat, atau tinggal kelas SEMUA harus tetap bisa diproses backend tanpa hambatan — ini keputusan bisnis eksplisit (admin kadang perlu koreksi data manual, mis. siswa pindahan dari sekolah lain).
2. **Perbandingan WAJIB berbasis INDEX terhadap `BentukPendidikan::validTingkatValues()`, BUKAN aritmatika angka (`tingkat + 1`).** `validTingkatValues()` untuk jenjang KB/TPA/SPS/TK adalah `['A', 'B']` — string non-numerik. Kode yang mencoba `parseInt(tingkat) + 1` atau semacamnya akan RUSAK TOTAL untuk jenjang itu. Selalu cari index tingkat asal & tujuan di dalam array `validTingkatValues()` milik `bentuk_pendidikan` LEMBAGA (bukan kelas), lalu bandingkan SELISIH INDEX.
3. **TIDAK ADA tabel/kolom/laporan baru untuk "siswa tinggal kelas".** Ini keputusan eksplisit (Opsi A dipilih atas Opsi B saat brainstorming) — kalau menemukan dorongan untuk "sekalian bikin pencatatan riwayat", itu di luar scope, JANGAN dikerjakan tanpa spec baru.
4. **TIDAK ADA library testing JS baru (Jest/Cypress/Vitest).** Project ini tidak punya toolchain itu sama sekali (dikonfirmasi lewat `package.json` saat spec ditulis). SEMUA test untuk fitur ini WAJIB Pest Feature test yang assert HTML markup mentah dari respons HTTP — mengikuti pola persis `tests/Feature/Admin/KenaikanKelasControllerUxTest.php` yang sudah ada untuk warning kurikulum-berbeda (cek `data-*` attribute dan string ekspresi Alpine di `x-data`/`x-text`, BUKAN menjalankan JS sungguhan).
5. **2 gaya pesan berbeda, bukan 1 gaya generik**: "tinggal kelas" (selisih index = 0) → info netral (warna abu-abu, BUKAN warna alarm). "Tidak wajar" (selisih index selain 0 dan 1) → warning amber (level sama dengan warning kurikulum-berbeda yang sudah ada). Jangan disatukan jadi 1 pesan generik — alasan bisnisnya (mencegah alert fatigue karena "tinggal kelas" itu kasus RUTIN, bukan langka) ada di spec §1.
6. **Baris `<p x-show="tingkatTujuan !== null" ...>Tingkat tujuan: ...</p>` yang sudah ada TIDAK boleh dihapus/diubah.** 2 baris pesan baru ditambahkan SETELAHNYA, bukan menggantikan.
7. **Tidak pindah branch** — semua kerja tetap di `akademik-v2`.

## Fakta Operasional yang Perlu Diketahui

- Task 2 di plan ini SENGAJA menulis test yang diharapkan **langsung lolos di percobaan pertama** (bukan pola TDD merah-dulu biasa) — ini pembuktian eksplisit bahwa backend memang tidak pernah validasi tingkat. Kalau test itu GAGAL, itu tanda ada validasi tersembunyi yang tidak diketahui siapa pun saat spec ditulis — STOP, jangan lanjutkan, laporkan ke user (jangan menambah kode untuk membuatnya lolos, dan jangan mengasumsikan itu bug yang harus ditambal sendiri).
- Ikuti CLAUDE.md project ini: jalankan `vendor/bin/pint --dirty --format agent` di akhir setiap task yang menyentuh PHP/Blade.
- Baca `.ai/rules/index.md` dan rule file yang relevan (`.ai/rules/views.md`, `.ai/rules/tests.md`) sebelum menulis kode.
- File `resources/views/portals/lembaga/akademik/kenaikan-kelas/index.blade.php` sudah beberapa kali diubah sepanjang sesi-sesi sebelumnya (badge "Sudah diproses/kosong", opsi tindakan "Lewati") — baca isi file AKTUAL sebelum mengedit, JANGAN asumsikan nomor baris di plan 100% akurat kalau sudah ada perubahan lain sejak plan ditulis (plan sudah mengingatkan ini di Task 1 Step 1).

## Kalau Menemukan Ambiguitas atau Gap Saat Implementasi

**STOP dan laporkan ke user — JANGAN mengambil keputusan desain baru sendiri di tengah eksekusi**, walaupun kelihatannya kecil atau "jelas". Contoh situasi yang HARUS stop-and-report:

- Nomor baris di plan tidak cocok lagi dengan file aktual DAN strukturnya sudah berubah signifikan (bukan sekadar pergeseran baris kecil).
- `BentukPendidikan::validTingkatValues()` ternyata sudah berubah isinya (mis. ada jenjang baru ditambahkan) sejak spec ditulis — verifikasi dulu, tapi kalau ternyata ada jenjang dengan pola nilai yang tidak sesuai asumsi spec (bukan cuma alfabet 'A'/'B' atau numerik berurutan), laporkan.
- Test Task 2 (pembuktian non-blocking) GAGAL — lihat "Fakta Operasional" di atas, ini WAJIB dilaporkan, bukan ditambal sendiri.
- Menemukan bahwa `ProsesKenaikanKelasAction` atau file terkait sudah diubah oleh pekerjaan lain (mis. Kelompok A atau spec lain) dengan cara yang membuat asumsi spec ini tidak berlaku lagi.

## Mulai dari Mana

Plan sudah dipecah jadi 3 task berurutan (Task 1: implementasi + test frontend, Task 2: pembuktian backend, Task 3: full suite final). Kerjakan mulai Task 1, TDD (test dulu, gagal, implementasi, lolos, commit) untuk Task 1, satu task per commit. Task 2 punya catatan khusus soal "harus langsung lolos" (lihat di atas). Setelah Task 3 (full suite final), laporkan ke user bahwa paket Kelompok B ini selesai.
