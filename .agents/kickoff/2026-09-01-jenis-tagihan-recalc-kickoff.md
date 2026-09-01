# Kickoff — Konsolidasi Jenis Tagihan (Sasaran/Tarif/Keringanan) + Engine Recalculate

Kamu (agent baru) tidak punya akses ke diskusi panjang yang menghasilkan dokumen ini. Baca file ini secara lengkap sebelum menyentuh kode apapun.

## Konteks

Repo: `d:\laragon\www\pintera-app` (Laravel 12 / PHP 8.3, aplikasi pendidikan multi-tenant "Pintera"). Branch kerja: `keuangan-v2`. Base commit sebelum paket ini: `0050f291`.

Ini adalah redesain modul Jenis Tagihan (Keuangan) supaya admin lembaga bisa mengelola Target Sasaran, Tarif Berdimensi, dan Keringanan & Potongan Biaya langsung dari 1 form (tanpa berpindah ke halaman edit Siswa), sekaligus menambahkan sebuah "engine" yang secara otomatis menghitung ulang `net_amount` pada Tagihan yang belum lunas ketika konfigurasi Tarif/Keringanan-nya berubah setelah tagihan dibuat.

## Dokumen yang WAJIB dibaca, urutan ini

1. **Spec (keputusan bisnis lengkap, sudah final & disetujui):** `.agents/specs/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md`
2. **Plan implementasi (task-by-task, TDD, kode lengkap):** `.agents/plans/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md`

Baca PLAN-nya secara utuh dulu sebelum mulai task pertama — jangan cuma baca task yang sedang dikerjakan lalu lupa Global Constraints di bagian atas plan; constraint itu berlaku implisit di SETIAP task.

## Keputusan Kritis yang TIDAK BOLEH diubah tanpa konfirmasi ulang ke user

Ini semua adalah hasil 3 putaran pendalaman sensitif soal uang dengan user — jangan didesain ulang sendiri kalau menemui sesuatu yang "kelihatannya bisa lebih baik dengan cara lain":

1. **Guard `tagihable_type`** — `RecalculateTagihanNominalAction::execute()` WAJIB cek `$tagihan->tagihable_type !== Siswa::class` sebagai baris pertama, no-op (bukan exception) kalau gagal. Sasaran/Tarif/Keringanan cuma berlaku untuk tagihan Siswa, bukan PPDB (`Pendaftaran`-tagihable).
2. **`person_id` DILARANG untuk query trigger #1** — query "semua tagihan siswa ini" WAJIB pakai `tagihable_type = Siswa::class` + `tagihable_id`, TIDAK PERNAH `person_id`. `person_id` menyatukan ledger lintas `tagihable_type` (PPDB + Siswa) — memakainya di sini akan menarik tagihan PPDB lama ke scope recalc secara diam-diam.
3. **Guard overpayment** — hasil recalc HANYA diterapkan otomatis kalau `net_amount` baru `>= paid_amount`. Kalau tidak, Tagihan di-flag `perlu_ditinjau_ulang=true` dengan alasan, TIDAK diubah nilainya.
4. **Cicilan sepenuhnya di luar scope otomatisasi ini** — kalau `$tagihan->skemaCicilan()->exists()`, SELALU flag `perlu_ditinjau_ulang`, tidak pernah auto-apply. `Cicilan.nominal` adalah snapshot beku yang tidak pernah direkonsiliasi ke `Tagihan` — mengubah `net_amount` di baliknya akan menciptakan drift permanen yang tidak pernah terdeteksi kode manapun.
5. **`TagihanStatusResolver` adalah SATU-SATUNYA sumber kebenaran** untuk transisi status Tagihan (`lunas`/`sebagian`/`belum_bayar`, `dibatalkan` selalu dipertahankan). Baik `PaymentAllocationService::allocate()` maupun `RecalculateTagihanNominalAction` WAJIB memanggil service yang sama — jangan duplikasi logic if/elseif di tempat lain.
6. **`lockForUpdate()` wajib** di `RecalculateTagihanNominalAction` (dalam `DB::transaction()`), mengikuti pola persis yang sudah ada di `PaymentAllocationService::allocate()`, untuk mencegah race antara pembayaran dan recalc yang berjalan bersamaan pada Tagihan yang sama.
7. **Urutan 9 tahap di plan WAJIB diikuti persis**, jangan dikerjakan ulang urutannya atau dipararelkan sembarangan — ada dependency asli antar tahap (contoh: `TagihanStatusResolver` di Tahap 2 harus ada sebelum `RecalculateTagihanNominalAction` di Tahap 3 dipakai; `SyncJenisTagihanBillingConfigAction` harus jadi diff-aware di Tahap 6 SEBELUM trigger #2/#3 di Tahap 7 diaktifkan — kalau dibalik, setiap save form yang tidak menyentuh Tarif/Keringanan akan memicu "recalc storm" palsu karena `SyncJenisTagihanBillingConfigAction` selama ini delete-recreate SEMUA baris Tarif/Keringanan di SETIAP save, relevan atau tidak).
8. **Trigger #4 (reorder priority Tarif) adalah endpoint terpisah** (`ReorderTarifGrupAction` + route `PATCH .../tarif-grup/reorder`) yang dispatch recalc LANGSUNG dari dalam aksinya sendiri — TIDAK lewat form submit / diff-detection Tahap 6, karena UI reorder tidak pernah submit form penuh.
9. **1 job per Tagihan untuk trigger bulk (#2, #3, #4)** — jangan pernah 1 job besar yang loop semua tagihan di dalamnya.
10. **`SiswaKeringanan` tetap data GLOBAL milik siswa**, tidak di-scope ke 1 Jenis Tagihan. Halaman assignment yang sudah ada di edit Siswa (`SiswaKeringananController`) TETAP DIPERTAHANKAN — widget baru di form Jenis Tagihan (Tahap 9) cuma pintu masuk kedua ke endpoint yang SAMA, bukan backend baru.
11. **Temuan audit #6 (dugaan divergensi cek jenis_kelamin SQL vs PHP) sudah diverifikasi BUKAN bug** — jangan diperbaiki lagi, cukup ada komentar dokumentasi (Tahap 1 Task 1 Step 6). Kalau menemukan sesuatu yang terlihat seperti masalah yang sama, verifikasi ulang dengan baca kode langsung sebelum berasumsi ini regresi baru.

## Fakta operasional yang perlu diketahui

- Ini adalah **Job class (`ShouldQueue`) PERTAMA di codebase ini** — tidak ada pola existing untuk ditiru selain konvensi Laravel standar. `QUEUE_CONNECTION=database` sudah dikonfigurasi (`.env`) — job yang di-dispatch di production butuh `php artisan queue:work` berjalan untuk benar-benar diproses.
- Ikuti CLAUDE.md project ini: jalankan `vendor/bin/pint --dirty --format agent` di akhir setiap task yang menyentuh PHP, dan untuk task UI (Tahap 9) lakukan verifikasi manual di browser (`npm run build` dulu) — jangan cuma andalkan test otomatis untuk klaim fitur UI selesai.
- Baca `.ai/rules/index.md` dan rule file yang relevan (`.ai/rules/actions.md`, `.ai/rules/services.md`, `.ai/rules/controllers.md`, `.ai/rules/migrations.md`, `.ai/rules/tests.md`, dll — sesuai path yang disentuh task yang sedang dikerjakan) SEBELUM menulis kode di setiap task, sesuai instruksi project ini.

## Kalau menemukan ambiguitas atau gap saat implementasi

**STOP dan laporkan ke user — JANGAN mengambil keputusan desain baru sendiri di tengah eksekusi**, walaupun keputusan itu terlihat kecil atau "jelas". Semua keputusan desain di paket ini (termasuk yang terlihat sepele) sudah melalui pertimbangan sensitif soal uang dan sudah final lewat diskusi panjang — kalau plan atau spec ternyata tidak mengcover sebuah kasus, itu tandanya perlu dikembalikan ke user untuk diputuskan, bukan diisi sendiri berdasarkan asumsi "yang paling masuk akal".

Contoh situasi yang HARUS stop-and-report, bukan diputuskan sendiri:
- Signature/nama method di plan ternyata tidak cocok dengan kode aktual di file (grep dulu untuk konfirmasi, tapi kalau memang berbeda dan plan tidak punya fallback instruction yang jelas untuk situasi itu — laporkan, jangan tebak).
- Test yang diminta plan gagal karena asumsi struktur data yang salah (misal nama kolom berbeda dari yang plan tulis) DAN perbaikannya butuh keputusan desain (bukan sekadar typo).
- Menemukan interaksi baru dengan bagian sistem lain yang tidak disebut di spec/plan sama sekali (misal ternyata ada mekanisme ketiga yang juga mengubah `net_amount` selain yang sudah didaftar).

## Mulai dari mana

Plan sudah dipecah jadi 9 tahap / 17 task berurutan (lihat daftar isi di bagian atas plan). Kerjakan mulai Task 1, TDD (test dulu, gagal, implementasi, lolos, commit), satu task per commit sesuai yang tertulis di plan. Setelah task terakhir (Task 17), jalankan `php artisan test --compact` full suite dan `vendor/bin/pint --dirty --format agent` sebagai langkah penutup, seperti diinstruksikan di bagian akhir plan.
