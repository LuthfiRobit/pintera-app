# Kickoff: Fix ruangan_id Lolos Cross-Lembaga pada Jadwal Pelajaran

**Untuk**: Antigravity (execution agent)
**Branch**: `akademik-v2` (lanjutkan di branch ini, JANGAN buat branch baru)
**Spec**: `.agents/specs/2026-08-28-akademik-fix-ruangan-id-cross-lembaga-jadwal-pelajaran.md`
**Plan**: `.agents/plans/2026-08-28-akademik-fix-ruangan-id-cross-lembaga-jadwal-pelajaran.md`

---

## Peringatan Kritis — Baca Dulu Sebelum Mulai

1. **Baca `.agents/plans/2026-08-28-akademik-fix-ruangan-id-cross-lembaga-jadwal-pelajaran.md` SELURUHNYA sebelum menulis kode apa pun.** Plan ini punya 1 task dengan 8 step berurutan, tapi berisi kode lengkap untuk setiap step — jangan improvisasi di luar apa yang tertulis.
2. **Baca dulu `.ai/rules/index.md`**, lalu buka rule file yang cocok dengan glob untuk setiap path yang akan disentuh (`app/Http/Controllers/**` dan `tests/**`). Jalankan juga `grep -rin` untuk keyword relevan (`ruangan`, `TenantScope`, `jadwal`) di `.ai/rules`.
3. **`Ruangan` model TIDAK punya factory** (`database/factories/RuanganFactory.php` tidak ada, meski model pakai trait `HasFactory`). Plan Step 2 sudah menyediakan helper `buatRuanganUntukLembaga()` berbasis `Ruangan::create()` + `Gedung::create()` — JANGAN mencoba `Ruangan::factory()->create()`, itu akan error karena factory class-nya tidak eksis.
4. **Verifikasi baseline sebelum edit** — Step 1 di plan meminta kamu membaca ulang `JadwalPelajaranController.php` (bagian `store()` baris 209-219 dan `update()` baris 331-344) dan membandingkan dengan baseline yang tertulis di plan. Kalau berbeda, STOP dan laporkan.

## Konteks Masalah (ringkas)

`JadwalPelajaranController::store()`/`update()` sudah memvalidasi `guru_id`, `mata_pelajaran_id`, dan `semester_id` terhadap `$kelas->lembaga_id` sebelum menyimpan jadwal — tapi `ruangan_id` luput sama sekali dari cross-check yang sama. Dropdown UI memang sudah difilter per-lembaga, tapi itu kontrol client-side; POST langsung dengan `ruangan_id` milik lembaga lain tidak ditolak server.

**Beda penting dari 2 bug sebelumnya di sesi audit ini** (KurikulumAssignment, UpdateKomponenPenilaianAction): bug ini reachable oleh **admin lembaga BIASA** (lembaga-scoped, `actingAsJadwalManager()` di test), BUKAN cuma aktor yayasan mode "Semua Lembaga". Jangan bingung dan menganggap semua test reproduksi di modul ini harus pakai aktor yayasan — di sini aktor lembaga biasa sudah cukup untuk membuktikan bug.

Pengecualian penting: ruangan dengan `is_shared = true` HARUS tetap diterima meski beda lembaga — itu bukan bug, itu fitur ruangan bersama antar-lembaga dalam satu yayasan.

## Urutan Eksekusi

Plan hanya punya **1 task** dengan 8 step berurutan (baca baseline → tulis test gagal → jalankan & pastikan gagal → fix di store() → fix di update() → jalankan & pastikan semua lolos → pint → commit). Ikuti persis urutan step di plan, termasuk perintah `php artisan test` yang tertulis eksplisit di tiap step verifikasi.

## Titik Verifikasi Wajib

- Setelah Step 3 (sebelum fix): 2 test reproduksi (`rejects a ruangan_id belonging to another lembaga on store`, `rejects updating ruangan_id to a ruangan from another lembaga`) HARUS **gagal** — ini membuktikan bug memang ada sebelum fix diterapkan. Kalau ternyata sudah PASS di titik ini, STOP — ada kesalahan setup test, jangan lanjut ke fix.
- Setelah Step 6 (setelah fix di store() DAN update()): jalankan seluruh file `tests/Feature/Admin/JadwalPelajaranCrudTest.php` dan pastikan SEMUA test PASS — file ini besar (>50 test existing), tidak satupun boleh diubah assertion-nya. Tidak ada test existing yang mengirim `ruangan_id` di payload, jadi fix ini seharusnya tidak menyentuh jalur manapun yang sudah ada.
- **TIDAK PERLU full suite** untuk fix sekecil ini (sudah dikonfirmasi user) — cukup `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`.
- Jalankan `vendor/bin/pint --dirty --format agent` sebelum commit final (Step 7).

## Pelajaran Penting dari Sprint-Sprint Akademik Sebelumnya

- **Pola "field relasional yang tidak ikut di-cross-check" berulang di modul ini** — sudah terjadi di `KurikulumAssignmentController` (tahun_ajaran_id vs lembaga_id), `UpdateKomponenPenilaianAction` (lembaga_id vs semester_id), dan sekarang `ruangan_id` vs lembaga kelas. Kalau menemukan pola serupa di field lain saat mengerjakan task ini, JANGAN diam-diam memperbaikinya di luar scope — laporkan sebagai temuan terpisah di handoff log.
- **Gunakan `withoutGlobalScope(TenantScope::class)` + perbandingan manual, JANGAN mengandalkan efek samping TenantScope untuk validasi ownership** — kalau hanya mengandalkan `Ruangan::find()` tanpa bypass scope, perilaku validasi akan berbeda-beda tergantung jenis aktor yang login (lembaga-scoped vs yayasan-scoped), padahal yang diinginkan adalah aturan yang konsisten untuk SEMUA jenis aktor. Ini persis pelajaran dari fix `UpdateKomponenPenilaianAction` sebelumnya.
- **Cek dulu apakah factory ada sebelum memakainya di test baru** — sempat ditemukan kasus `Ruangan` tidak punya factory sama sekali; helper `buatRuanganUntukLembaga()` di plan sudah menyediakan solusinya, tidak perlu membuat factory baru untuk fix ini (di luar scope).

## Kalau Menemukan Sesuatu Tidak Sesuai Plan

- Kalau baseline kode di repo berbeda dari yang tertulis di plan Step 1, STOP dan laporkan detail perbedaannya di handoff log — jangan menebak dan memaksakan kode plan tetap jalan.
- Kalau ternyata `tests/Feature/Akademik/JadwalSarprasCollisionTest.php` (referensi pola `Ruangan::create()`) sudah berubah/dihapus sejak plan ditulis, baca ulang skema tabel `ruangan` via Laravel Boost `database-schema` untuk memastikan kolom yang dipakai di helper `buatRuanganUntukLembaga()` masih valid, sebelum menjalankan test.
- Kalau ada test lain (di luar 4 test baru) yang tiba-tiba FAIL setelah fix diterapkan, JANGAN diam-diam mengubah assertion test itu. Laporkan sebagai temuan di handoff log.

## Definisi Selesai

- [ ] `JadwalPelajaranController.php` mendapat 2 blok cross-check baru (1 di `store()`, 1 di `update()`), sesuai kode di plan Step 4 dan Step 5.
- [ ] Helper `buatRuanganUntukLembaga()` + 4 test baru ditambahkan di `JadwalPelajaranCrudTest.php` dan PASS.
- [ ] Seluruh test existing di file yang sama tetap PASS tanpa modifikasi assertion.
- [ ] `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact` hijau semua.
- [ ] `vendor/bin/pint --dirty --format agent` sudah dijalankan.
- [ ] Commit dibuat sesuai pesan di plan Step 8.
- [ ] Handoff log ditulis ke `.agents/logs/2026-08-28-akademik-fix-ruangan-id-cross-lembaga-jadwal-pelajaran.md` mengikuti format handoff log sebelumnya (lihat `.agents/logs/2026-08-27-akademik-fix-lembaga-id-stale-komponen-penilaian.md` sebagai contoh format): ringkasan apa yang dikerjakan, keputusan penting yang diambil, hal yang perlu direview manusia.
