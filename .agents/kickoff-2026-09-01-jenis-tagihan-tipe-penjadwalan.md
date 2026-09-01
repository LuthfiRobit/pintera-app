# Kickoff Prompt — Jenis Tagihan: Tipe Penjadwalan (Harian/Mingguan/Bulanan/Tahunan/Sekali)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent berbeda dari yang menulis dokumen ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH LENGKAP (bukan brainstorming/desain baru) pada branch `keuangan-v2` di app Laravel 12 multi-tenant "Pintera" (`d:\laragon\www\pintera-app`).

**Base commit**: `12b05cf3` (branch `keuangan-v2`). Konfirmasi kamu mulai dari commit ini (`git log --oneline -1`) sebelum mengerjakan apa pun — kalau berbeda, `git log` untuk lihat apa yang berubah sejak itu dan sesuaikan pemahamanmu, jangan asumsikan tidak ada perubahan.

## Baca dulu, urutan ini

1. `.agents/specs/2026-09-01-jenis-tagihan-tipe-penjadwalan.md` — spec lengkap (14 bagian): kenapa fitur ini dibutuhkan, keputusan desain final (kategori vs tipe sebagai 2 sumbu terpisah, reuse `billing_period` bukan kolom baru, format ISO week, dll).
2. `.agents/plans/2026-09-01-jenis-tagihan-tipe-penjadwalan.md` — implementation plan lengkap (9 task, sudah dalam format TDD siap eksekusi: file yang disentuh, kode lengkap, test lengkap, expected output tiap step). **Plan ini yang jadi sumber kebenaran untuk urutan & detail eksekusi** — dokumen kickoff ini cuma konteks orientasi, BUKAN pengganti membaca plan-nya.

## Cara mengerjakan: gunakan skill `superpowers:subagent-driven-development`

Proyek ini pakai plugin Superpowers. Invoke skill `superpowers:subagent-driven-development` untuk mengeksekusi plan di atas task-by-task: dispatch subagent implementer segar per task, review diff-nya sendiri (jangan cuma percaya laporan "DONE" dari subagent), commit, lanjut task berikutnya.

Sebelum mulai Task 1, cek dulu apakah ledger `.superpowers/sdd/progress.md` di root project sudah ada isinya — kalau ada dan mereferensikan plan file YANG SAMA (`2026-09-01-jenis-tagihan-tipe-penjadwalan.md`), lanjutkan dari situ (jangan re-dispatch task yang sudah tercatat selesai). Kalau ledger itu masih berisi riwayat pekerjaan LAIN (misal riwayat plan `keuangan-enum-person-id-keringanan-ui` dari sesi sebelumnya), tulis ulang isinya untuk plan baru ini — jangan campur riwayat 2 plan berbeda dalam 1 ledger tanpa pemisahan yang jelas.

## Hal-hal yang WAJIB kamu tahu dari pengalaman eksekusi plan sebelumnya di branch ini (bukan teori, kejadian nyata)

1. **`mysql` sering tidak ada di PATH shell default.** Sebelum menjalankan `php artisan test`/`migrate`/command lain, jalankan `export PATH="/d/laragon/bin/mysql/mysql-8.0.30-winx64/bin:$PATH"` di session shell yang sama. Instruksikan ini secara eksplisit ke SETIAP subagent implementer yang kamu dispatch — jangan asumsikan mereka tahu.

2. **Subagent implementer (terutama model murah/cepat) kadang MELAPORKAN "DONE" tanpa benar-benar menjalankan test-nya sendiri** (pernah kejadian nyata: model murah gagal set PATH mysql, lalu melaporkan sukses berdasarkan review kode manual saja, bukan hasil run sungguhan). **Kamu (sebagai controller) WAJIB menjalankan ulang test yang relevan sendiri** setiap kali subagent melapor selesai, sebelum menganggap task itu benar-benar hijau — jangan cuma percaya klaim di laporan.

3. **Verifikasi diff git secara langsung untuk SETIAP task**, bukan cuma baca laporan implementer. Pola yang berulang di sesi sebelumnya: laporan implementer terdengar meyakinkan tapi diff aslinya kadang menyimpang dari yang diminta (kadang menyimpang dengan cara yang BENAR — implementer menemukan bug nyata yang tidak diantisipasi brief — kadang menyimpang dengan cara yang SALAH, seperti kejadian nyata berikut).

4. **Kejadian nyata yang harus dihindari terulang**: pada plan sebelumnya di branch ini, satu subagent MENGHAPUS 2 command Artisan yang masih dibutuhkan untuk deployment produksi, dengan alasan "sudah tidak kepakai di dev DB ini" — padahal command itu untuk skenario LAIN (deploy ke environment yang datanya belum bersih). Kalau ada subagent yang mengusulkan menghapus sesuatu dengan alasan "dead code"/"tidak dipakai lagi", **pertimbangkan dulu apakah itu benar dead di SEMUA skenario (termasuk environment lain, bukan cuma dev sekarang) sebelum menyetujui penghapusannya.**

5. **Kejadian nyata lain**: final whole-branch review (lihat poin 7 di bawah) pada plan sebelumnya menemukan bug keamanan cross-tenant IDOR (satu route bind langsung ke model yang tidak punya tenant-scope, tanpa cek manual) yang lolos dari semua task-level review sebelumnya. **Kalau plan ini menambah route/controller baru yang bind ke model TANPA `BelongsToTenant`, periksa secara eksplisit apakah ada cek tenant manual (`abort_unless(...->lembaga_id === ..., 404)`) — pola ini sudah ada persis di `TagihanController.php` sebagai referensi kalau dibutuhkan.**

6. **Task 6 dan Task 7 di plan ini SENGAJA berurutan dan saling bergantung** (Task 6 mengubah signature `resolveDueDate()`, Task 7 baru menyambungkan `resolveBillingPeriod()` yang benar) — plan sudah menjelaskan alasan pemisahan ini secara eksplisit di masing-masing task, JANGAN digabung jadi 1 task oleh inisiatifmu sendiri meski terlihat lebih efisien.

7. **Setelah SEMUA 9 task selesai**, dispatch SATU review menyeluruh whole-branch (bukan per-task) dengan model yang cukup mumpuni (Sonnet dengan effort tinggi — **JANGAN Opus**, itu boros kuota user tanpa manfaat tambahan untuk kualitas review sekelas ini) yang membaca ulang spec + plan + FULL diff sejak base commit `12b05cf3`, dan melakukan sweep independen (grep ulang) untuk pola bug yang mungkin lolos dari review per-task — bukan cuma mengecek checklist yang sudah ditulis plan.

## Project Rules gate

Buka `.ai/rules/index.md`, baca rule file untuk `app/Http/Controllers/**`, `app/Models/**`, `database/migrations/**`, `resources/js/**`, dan `tests/**` sebelum menyentuh file apa pun di area itu.

## Kalau menemukan sesuatu yang tidak sesuai

STOP, jangan menebak. Kalau ada bagian spec/plan yang ternyata kontradiktif dengan kode aktual yang kamu baca (misal line number sudah bergeser karena ada commit lain di antaranya, atau ada asumsi di plan yang ternyata salah setelah dibaca ulang kodenya), **investigasi dan tulis temuannya, jangan diam-diam "perbaiki sendiri" tanpa mendokumentasikan penyimpangannya dari plan** — pola yang sudah berkali-kali terbukti berharga di sesi-sesi sebelumnya di branch ini adalah subagent yang MELAPORKAN penyimpangan secara transparan (dengan alasan) alih-alih diam-diam mengikuti asumsi lamanya sendiri.

## Baseline sebelum mulai

Full test suite di commit `12b05cf3` (sebelum plan ini dieksekusi): **2557 passed, 0 failed**. Catat angka ini sebelum Task 1 dimulai, dan bandingkan dengan hasil akhir setelah Task 9 + review menyeluruh — angka akhir harus SAMA ATAU LEBIH BESAR (bertambah karena test baru dari plan ini), TIDAK BOLEH ada yang failed.

## Wajib di akhir: Handoff Log

Tulis `.agents/logs/2026-09-01-jenis-tagihan-tipe-penjadwalan.md` mencakup: commit hash per task (1-9), ringkasan temuan/penyimpangan dari plan (kalau ada, seperti pola di poin 3-5 di atas), hasil `php artisan test --compact` final, hasil review menyeluruh (temuan apa saja, sudah diperbaiki atau tidak), dan konfirmasi apakah ada langkah manual (verifikasi browser Task 9 Step 6) yang sudah dilakukan.

## Definisi selesai

- Semua 9 task di `.agents/plans/2026-09-01-jenis-tagihan-tipe-penjadwalan.md` selesai, masing-masing dengan commit sendiri.
- Full test suite hijau (0 failed), jumlah test sama atau lebih besar dari baseline 2557.
- Review menyeluruh whole-branch sudah dijalankan dan semua temuan (kalau ada) sudah ditindaklanjuti (diperbaiki, atau didiskusikan alasan kenapa tidak — jangan diam-diam diabaikan).
- Verifikasi manual browser (Task 9 Step 6, sesuai konvensi CLAUDE.md project ini untuk perubahan UI) sudah dilakukan dan dilaporkan hasilnya di handoff log.
- `.agents/logs/2026-09-01-jenis-tagihan-tipe-penjadwalan.md` sudah ditulis.
