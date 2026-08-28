# Kickoff: Fix lembaga_id Basi pada Update Komponen Penilaian

**Untuk**: Antigravity (execution agent)
**Branch**: `akademik-v2` (lanjutkan di branch ini, JANGAN buat branch baru)
**Spec**: `.agents/specs/2026-08-27-akademik-fix-lembaga-id-stale-komponen-penilaian.md`
**Plan**: `.agents/plans/2026-08-27-akademik-fix-lembaga-id-stale-komponen-penilaian.md`

---

## Peringatan Kritis — Baca Dulu Sebelum Mulai

1. **Baca `.agents/plans/2026-08-27-akademik-fix-lembaga-id-stale-komponen-penilaian.md` SELURUHNYA sebelum menulis kode apa pun.** Plan ini punya 1 task tapi berisi kode lengkap untuk setiap step — jangan improvisasi di luar apa yang tertulis.
2. **Baca dulu `.ai/rules/index.md`**, lalu buka rule file yang cocok dengan glob untuk setiap path yang akan disentuh (`app/Domains/Akademik/Actions/**` dan `tests/**`). Jalankan juga `grep -rin` untuk keyword relevan (`komponen`, `lembaga_id`, `TenantScope`) di `.ai/rules` untuk menangkap rule yang tidak tertangkap oleh glob semata.
3. **Gunakan tools Laravel Boost** (`database-schema`, `search-docs`, dsb) bila perlu memverifikasi asumsi skema — jangan asumsikan tanpa cek.
4. **Constructor `KomponenPenilaianData` di plan Step 2 sudah dikoreksi eksplisit** — `bobot` dan `kktpMinimal` bertipe `int`, BUKAN `float`. Pastikan kode test yang kamu tulis pakai `int` literal (`100`, bukan `100.0`).
5. **Verifikasi baseline sebelum edit** — Step 1 di plan meminta kamu membaca ulang `UpdateKomponenPenilaianAction.php` dan membandingkan dengan baseline yang tertulis di plan. Kalau berbeda, STOP dan laporkan, jangan lanjut mengasumsikan.

## Konteks Masalah (ringkas)

`CreateKomponenPenilaianAction` sudah benar: `lembaga_id` selalu di-derive dari `Semester::findOrFail($semesterId)->lembaga_id`. Tapi `UpdateKomponenPenilaianAction` mengubah `subjek_type`/`subjek_id`/`semester_id` TANPA pernah menyentuh `lembaga_id` — sehingga kalau `semester_id` komponen dipindah ke semester lembaga lain, `lembaga_id` komponen jadi basi/tidak konsisten.

Bug ini HANYA reachable oleh **aktor level yayasan** dalam mode "Semua Lembaga" (tanpa `session('active_lembaga_id')`) — karena `TenantScope` mengizinkan mereka menemukan semester lintas-lembaga dalam yayasan yang sama via `Semester::find()`. Aktor lembaga-scoped biasa TIDAK BISA memicu bug ini (`Semester::find()` sudah mengembalikan `null` untuk semester lembaga lain, sehingga guard `abort_if($semester === null, 404)` di controller sudah menutupnya duluan). **Test reproduksi WAJIB pakai aktor `scope_level: 'yayasan'`, bukan `'lembaga'`** — ini poin paling penting di seluruh plan, jangan sampai salah pilih aktor test.

## Urutan Eksekusi

Plan hanya punya **1 task** dengan 6 step berurutan (baca test baseline → tulis test gagal → jalankan & pastikan gagal → implementasi fix → jalankan & pastikan semua lolos → commit). Ikuti persis urutan step di plan, termasuk perintah `php artisan test` yang tertulis eksplisit di tiap step verifikasi.

## Titik Verifikasi Wajib

- Setelah Step 3 (sebelum fix): 2 test reproduksi (`recomputes lembaga_id...`) HARUS **gagal** — ini membuktikan bug memang ada sebelum fix diterapkan. Kalau ternyata sudah PASS di titik ini, STOP — berarti ada kesalahan setup test (kemungkinan besar aktor test salah scope), jangan lanjut ke fix.
- Setelah Step 5 (setelah fix): jalankan seluruh file `tests/Feature/Admin/KomponenPenilaianCrudTest.php` dan pastikan SEMUA test PASS — termasuk 4 test update existing di baris 254-352 yang TIDAK BOLEH dimodifikasi assertion-nya sama sekali.
- **TIDAK PERLU full suite** untuk fix sekecil ini (sudah dikonfirmasi user) — cukup `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact`.
- Setelah edit PHP apa pun, jalankan `vendor/bin/pint --dirty --format agent` sebelum commit final.

## Pelajaran Penting dari Sprint-Sprint Akademik Sebelumnya

- **Pola "berbeda field ownership vs field yang menentukan tenant" berulang di modul ini** — sudah terjadi di `RppController` (guru_id vs kelas_id/jadwal), `KurikulumAssignmentController` (lembaga_id vs tahun_ajaran_id), dan sekarang di sini (lembaga_id vs semester_id). Selalu cek: apakah field yang menentukan "milik siapa" benar-benar dihitung ulang setiap kali field yang MENENTUKANNYA berubah?
- **Jangan percaya "audit sudah selesai" tanpa verifikasi ulang** — bug ini sendiri ditemukan justru setelah 3 putaran fix sebelumnya diklaim menutup semua celah. Kalau menemukan pola serupa di file lain saat mengerjakan task ini, JANGAN diam-diam memperbaikinya di luar scope — laporkan saja sebagai temuan terpisah di handoff log, biar dibuatkan spec/plan sendiri.
- **Test dengan aktor scope yang salah adalah false negative yang meyakinkan** — test bisa "PASS" (assertNotFound) padahal itu justru membuktikan aktor test tidak reachable ke jalur bug (lihat contoh test existing baris 329 `KomponenPenilaianCrudTest.php` yang pakai aktor lembaga-scoped dan justru MEMBUKTIKAN aktor itu tidak bisa memicu bug — bukan bukti bug sudah fix untuk semua aktor).

## Kalau Menemukan Sesuatu Tidak Sesuai Plan

- Kalau constructor/signature yang ditulis di plan ternyata berbeda dari kode aktual di repo (selain koreksi `KomponenPenilaianData` yang sudah diantisipasi), STOP dan laporkan detail perbedaannya di handoff log — jangan menebak-nebak dan memaksakan kode plan tetap jalan.
- Kalau ada test lain (di luar 3 test baru dan 4 test existing yang disebut plan) yang tiba-tiba FAIL setelah fix diterapkan, JANGAN diam-diam mengubah assertion test itu. Laporkan sebagai temuan di handoff log dan biarkan itu jadi keputusan manusia.

## Definisi Selesai

- [ ] `UpdateKomponenPenilaianAction.php` sudah mendapat 1 baris derivasi `lembaga_id` + 1 import, sesuai kode di plan Step 4.
- [ ] 3 test baru (`recomputes lembaga_id...` x2, `does not touch lembaga_id...`) ditambahkan di `KomponenPenilaianCrudTest.php` dan PASS.
- [ ] 4 test update existing (baris 254-352 versi lama) tetap PASS tanpa modifikasi assertion.
- [ ] `php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php --compact` hijau semua.
- [ ] `vendor/bin/pint --dirty --format agent` sudah dijalankan.
- [ ] Commit dibuat sesuai pesan di plan Step 6.
- [ ] Handoff log ditulis ke `.agents/logs/2026-08-27-akademik-fix-lembaga-id-stale-komponen-penilaian.md` mengikuti format handoff log sebelumnya (lihat `.agents/logs/2026-08-27-akademik-fix-tahun-ajaran-lembaga-kurikulum-assignment.md` sebagai contoh format): ringkasan apa yang dikerjakan, keputusan penting yang diambil, hal yang perlu direview manusia.
