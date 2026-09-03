# Kickoff — Siklus Hidup `kelas_id` Siswa Saat Status Berubah

Kamu (agent baru) tidak punya akses ke diskusi panjang yang menghasilkan dokumen ini. Baca file ini secara lengkap sebelum menyentuh kode apapun.

## Konteks

Repo: `d:\laragon\www\pintera-app` (Laravel 12 / PHP 8.3, aplikasi pendidikan multi-tenant "Pintera"). Branch kerja: `akademik-v2` (LANJUT DI BRANCH INI, jangan buat branch baru — keputusan eksplisit dari user). Base commit sebelum paket ini: `a12303e5`.

`SiswaController::updateStatus()` tidak pernah membersihkan `kelas_id` siswa saat status berubah ke `Lulus`/`Pindah`/`Keluar`. Akibatnya siswa yang sudah tidak aktif tetap "menempel" di kelas lama dan ikut terhitung di berbagai fitur berbasis `kelas_id` — salah satunya (`SubmitPengajuanRaporAction`) sudah terbukti jadi bug produksi aktif (memblokir submit rapor wali kelas) dan SUDAH ditambal terpisah sebelum paket ini ditulis (lihat commit `13d97610` di riwayat git branch ini). Paket ini menutup akar masalahnya secara permanen: kolom snapshot `kelas_terakhir_id`, CHECK constraint di database, `UpdateStatusSiswaAction`, dan accessor tampilan terpusat.

## Dokumen yang WAJIB dibaca, urutan ini

1. **Spec (keputusan sudah final & disetujui lewat brainstorming panjang, termasuk audit kode/data konkret di setiap keputusan):** `.agents/specs/2026-09-03-siklus-hidup-kelas-id-siswa.md`
2. **Plan implementasi (10 task, TDD, kode lengkap):** `.agents/plans/2026-09-03-siklus-hidup-kelas-id-siswa.md`
3. **Latar belakang audit asli (opsional tapi membantu konteks bisnis):** `.agents/logs/2026-09-03-audit-akademik-perbaikan.md`, bagian "Audit Bisnis Lanjutan: Kenaikan Kelas" dan "Putaran Verifikasi Ulang".

Baca PLAN-nya secara utuh dulu sebelum mulai task pertama — jangan cuma baca task yang sedang dikerjakan lalu lupa Global Constraints di bagian atas plan; constraint itu berlaku implisit di SETIAP task.

## Keputusan Kritis yang TIDAK BOLEH diubah tanpa konfirmasi ulang ke user

Semua ini hasil brainstorming panjang dengan audit kode/data nyata di setiap langkah (MySQL version dicek, volume data existing dicek langsung ke database, FK constraint dicek dari schema, koordinasi branch dicek lewat `git log`) — jangan didesain ulang sendiri kalau menemui sesuatu yang "kelihatannya bisa lebih baik dengan cara lain":

1. **Ketiga status non-aktif (`Lulus`, `Pindah`, `Keluar`) diperlakukan SAMA** — semuanya null-kan `kelas_id` via snapshot ke `kelas_terakhir_id` saat transisi keluar dari `Aktif`. Ini juga menyeragamkan jalur Lulus-manual (dulu tidak null-kan) dengan Lulus-via-Kenaikan-Kelas (dulu sudah null-kan).
2. **Reversal otomatis wajib** — transisi balik ke `Aktif` HARUS memulihkan `kelas_id` dari `kelas_terakhir_id`, lalu `kelas_terakhir_id` di-null-kan lagi. Alasan yang sudah disepakati: "Aktif tanpa kelas" adalah kegagalan SENYAP (siswa hilang dari semua fitur berbasis kelas tanpa tanda visual apapun), sedangkan "kelas yang dipulihkan mungkin sudah usang" adalah kegagalan KELIHATAN (gampang dikoreksi manual lewat form edit Siswa yang sudah ada). Jangan hilangkan reversal ini demi "kesederhanaan".
3. **Idempotency wajib** — kalau status target sama dengan status sekarang, JANGAN ubah `kelas_id`/`kelas_terakhir_id` sama sekali, walaupun secara logic "tidak masalah" untuk dijalankan ulang.
4. **Urutan migration WAJIB**: backfill data existing (`UPDATE ... SET kelas_terakhir_id = kelas_id, kelas_id = NULL WHERE status != 'aktif' ...`) HARUS jalan SEBELUM `ALTER TABLE ADD CONSTRAINT`. MySQL memvalidasi SEMUA baris existing terhadap CHECK constraint baru saat `ALTER TABLE` — kalau dibalik, migration akan GAGAL total begitu ada 1 saja baris yang melanggar.
5. **Backfill SINKRON, 1 statement SQL — BUKAN queued/chunked job.** Ini sudah dikonfirmasi lewat query langsung ke database dev (0 dari 336 siswa berstatus non-aktif saat spec ditulis) — skalanya kecil, tidak butuh pola job-per-row seperti di modul Keuangan. Jangan "upgrade" jadi queued job atas inisiatif sendiri.
6. **Accessor `kelas_efektif` WAJIB dipakai di SEMUA 3 tempat** yang menampilkan kelas siswa non-aktif (`RaporPdfDataBuilder`, `_daftar.blade.php`, `profil.blade.php`) — JANGAN bikin 3 logic fallback (`?? Kelas::find(...)`) terpisah di masing-masing tempat. Kalau menemukan tempat KE-4 yang butuh hal serupa saat implementasi, pakai accessor yang sama, jangan bikin pola baru — dan laporkan ke user kalau itu bukan salah satu dari yang sudah didaftar plan (lihat bagian "ambiguitas" di bawah).
7. **`JenisTagihanSasaranMatcher.php` dan `TagihanBillingGenerator.php` (`app/Domains/Keuangan/`) TIDAK BOLEH disentuh SAMA SEKALI** di paket ini — lihat "Catatan Serah-Terima ke Sesi Keuangan" di bawah.
8. **Urutan 10 task di plan WAJIB diikuti persis** — ada dependency nyata antar task, khususnya: Task 2 (migration) menambahkan CHECK constraint yang akan langsung mematahkan test lama `SesiPembelajaranGeneratorTest.php` begitu dijalankan (RefreshDatabase menerapkan SEMUA migration untuk SETIAP test run) — Task 2 Step 5-6 WAJIB menambal ini dengan fix INTERIM (bukan final) sebelum lanjut, dan Task 4 baru menyempurnakannya pakai `UpdateStatusSiswaAction` (yang baru ada di Task 3). Kalau urutan ini dilompat, test suite akan merah di antara task-task itu.
9. **Tidak pindah branch** — semua kerja tetap di `akademik-v2`, sudah keputusan eksplisit user.
10. **Tidak ada perubahan pada validasi tingkat Kenaikan Kelas atau konsep "siswa tinggal kelas"** — itu topik terpisah (Kelompok B) yang BELUM dibuat spec-nya, jangan diimprovisasi di paket ini.

## Catatan Serah-Terima ke Sesi Keuangan (`keuangan-v2`)

**Ini BUKAN task di plan ini — murni informasi untuk direlay, bukan untuk dikerjakan di sini.** Saat audit, ditemukan bahwa `JenisTagihanSasaranMatcher::resolveTargetSiswa()` dan `::countTotalSiswaPool()` (base query-nya cuma filter `lembaga_id`, dipakai juga oleh `TagihanBillingGenerator`) **tidak memfilter status siswa sama sekali** — independen dari masalah `kelas_id` yang ditutup paket ini. Siswa yang sudah `Lulus`/`Pindah`/`Keluar`, kalau kriteria sasaran billing-nya non-kelas (mis. "semua siswa" atau kriteria tingkat tanpa kelas spesifik), akan **tetap kena tagihan baru** walaupun `kelas_id` mereka sudah `NULL` berkat paket ini.

**Kenapa tidak ditambal di sini**: file yang sama (`JenisTagihanSasaranMatcher.php`) sedang aktif digarap paralel di branch `keuangan-v2` (dikonfirmasi lewat `git log keuangan-v2` — ada histori kerja baru-baru ini di area billing, dan file ini eksplisit disebut di brainstorming redesain form Jenis Tagihan di sana). Menyentuhnya dari paket ini berisiko konflik merge/kerja duplikat tanpa visibilitas ke state terkini sesi Keuangan itu.

**Yang perlu direlay ke siapa pun yang melanjutkan sesi/spec Keuangan**: base query `resolveTargetSiswa()`/`countTotalSiswaPool()` di `app/Domains/Keuangan/Services/JenisTagihanSasaranMatcher.php` perlu ditambah `->where('status', \App\Enums\StatusSiswa::Aktif->value)` — perbaikan ini TERPISAH dan TIDAK BERGANTUNG pada mekanisme `kelas_terakhir_id`/`UpdateStatusSiswaAction` di paket ini, jadi bisa dikerjakan kapan saja tanpa menunggu paket ini selesai.

## Fakta Operasional yang Perlu Diketahui

- Migration ini mengubah tabel `siswa` yang dipakai HAMPIR SEMUA modul (Akademik, Keuangan, Kasus, dsb) — jalankan full suite (`php artisan test --compact`) setelah Task 2 (migration) dan sekali lagi di akhir (Task 10), BUKAN cuma test yang berkaitan langsung. Pastikan tidak ada proses `php artisan test` lain berjalan bersamaan sebelum full suite (`ps aux | grep artisan`) — riwayat proyek ini pernah kena false-failure massal dari 2 proses test overlap.
- Laravel Schema Builder TIDAK punya method fluent untuk CHECK constraint — WAJIB pakai `DB::statement()` mentah, persis seperti contoh kode di plan Task 2.
- Ikuti CLAUDE.md project ini: jalankan `vendor/bin/pint --dirty --format agent` di akhir SETIAP task yang menyentuh PHP/Blade.
- Baca `.ai/rules/index.md` dan rule file yang relevan (`.ai/rules/models.md`, `.ai/rules/actions.md`, `.ai/rules/controllers.md`, `.ai/rules/migrations.md`, `.ai/rules/views.md`, `.ai/rules/tests.md`) SEBELUM menulis kode di setiap task, sesuai instruksi project ini.
- Model `Siswa.php` ada di `app/Models/` (root, BUKAN `app/Domains/`) — ini "frozen legacy zone" per `.ai/rules/models.md`, tapi menambah relasi/accessor ke model YANG SUDAH ADA di sana (bukan file model baru) tetap diperbolehkan; yang dilarang cuma membuat FILE MODEL BARU di root itu.

## Kalau Menemukan Ambiguitas atau Gap Saat Implementasi

**STOP dan laporkan ke user — JANGAN mengambil keputusan desain baru sendiri di tengah eksekusi**, walaupun keputusan itu terlihat kecil atau "jelas". Spec dan plan ini sudah melalui audit kode/data konkret berkali-kali (bukan asumsi) — kalau ternyata ada kasus yang tidak ter-cover, itu tandanya perlu dikembalikan ke user untuk diputuskan, bukan diisi sendiri.

Contoh situasi yang HARUS stop-and-report, bukan diputuskan sendiri:
- Nomor baris atau isi kode di plan ternyata tidak cocok lagi dengan kode aktual di file (mis. file sudah berubah sejak spec/plan ditulis) DAN perbedaannya bukan sekadar pergeseran baris kecil — verifikasi dulu dengan membaca file aktual, tapi kalau strukturnya sudah beda secara signifikan, laporkan, jangan improvisasi.
- Test yang diminta plan gagal karena alasan yang TIDAK dijelaskan di plan (bukan salah ketik/typo sederhana) — terutama kalau kegagalannya menunjukkan ada jalur tulis LAIN ke `kelas_id`/`status` siswa yang tidak ter-audit di spec §"Urutan Implementasi Wajib".
- Menemukan titik konsumen ke-8 (di luar 7 yang sudah didaftar spec §8) yang query berdasarkan `kelas_id` siswa tanpa filter status — laporkan sebagai temuan baru, jangan langsung ditambal sendiri tanpa tahu apakah itu masuk kategori "gratis" (tidak perlu kode) atau butuh accessor `kelas_efektif` seperti 3 titik yang sudah ada.
- Route name atau method controller untuk halaman "profil siswa" (dipakai Task 6 Step 6) ternyata berbeda dari dugaan plan (`admin.siswa.show`) — plan sudah memberi instruksi fallback untuk cek `route:list`, tapi kalau setelah dicek pun masih ambigu strukturnya, laporkan.

## Mulai dari Mana

Plan sudah dipecah jadi 10 task berurutan. Kerjakan mulai Task 1, TDD (test dulu, gagal, implementasi, lolos, commit), satu task per commit sesuai yang tertulis di plan. Task 2 dan Task 4 punya catatan dependency khusus (lihat Keputusan Kritis #8 di atas) — baca catatan di Task 2 Step 5-6 dan Task 4 dengan teliti sebelum mengerjakannya. Setelah Task 10 (full suite final), laporkan ke user bahwa paket ini selesai, TERMASUK mengingatkan soal "Catatan Serah-Terima ke Sesi Keuangan" di atas kalau user belum sempat merelaynya.
