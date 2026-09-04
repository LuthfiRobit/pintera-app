# Kickoff: Perbaikan Audit Akademik Putaran 4

**Base commit**: `c941d8e2` (branch `akademik-v2`)
**Spec**: `.agents/specs/2026-09-04-perbaikan-audit-akademik-putaran-4.md`
**Plan**: `.agents/plans/2026-09-04-perbaikan-audit-akademik-putaran-4.md`

## Konteks

Paket perbaikan ke-4 dari audit berkelanjutan modul Akademik pada branch `akademik-v2`. 9 task. Fokus utama: **Task 1 adalah root-fix CRITICAL** — `TenantScope` (dipakai hampir SEMUA model tenant-scoped di aplikasi, bukan cuma Akademik) tidak pernah memverifikasi ulang bahwa `session('active_lembaga_id')` masih benar-benar milik yayasan actor saat ini. Task 2-7 menambal 6 titik controller yang punya masalah SERUPA tapi di jalur TULIS-data (bukan baca), yang TIDAK otomatis tertutup oleh Task 1. Task 8 adalah race condition independen di pembuatan/perubahan Jadwal Pelajaran.

## Dokumen Wajib Dibaca

1. `.agents/specs/2026-09-04-perbaikan-audit-akademik-putaran-4.md` — spec lengkap §1-§4.
2. `.agents/plans/2026-09-04-perbaikan-audit-akademik-putaran-4.md` — plan 9 task, kode lengkap tiap step, TERMASUK beberapa "Catatan kejujuran" penting di Task 7 yang menjelaskan test mana yang murni regresi (bukan penutup celah aktif) — WAJIB dibaca supaya tidak salah paham soal severity saat mengeksekusi/mereview.

## Keputusan Kritis (JANGAN diubah tanpa eskalasi ke user)

1. **Titik perbaikan root (Task 1) HARUS di dalam `TenantScope::apply()` itu sendiri**, BUKAN di `ResolveTenant` middleware — middleware itu TIDAK disentuh sama sekali di paket ini. Alasan: `TenantScope` berlaku di SEMUA jalur (HTTP, command artisan, job antrian, test), middleware cuma jalan untuk request HTTP.
2. **Perilaku saat session basi terdeteksi**: diperlakukan SAMA seperti "belum pilih lembaga" — fallback ke cabang existing (batasi ke semua lembaga milik yayasan actor sendiri), BUKAN diblokir total (403/422). Cabang existing itu (baris 58-82 versi asli `TenantScope.php`) **TIDAK BOLEH DIUBAH SAMA SEKALI** — hanya bagian yang menentukan apakah masuk cabang itu atau tidak yang berubah.
3. **Task 2-7 WAJIB pakai ulang `ResolveLembagaScopeTrait::resolveActiveLembagaId(User $actor): ?int`** yang SUDAH ADA (dari paket putaran 3, `app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php`). JANGAN membuat trait/method baru.
4. **Di titik c, d, e, f, g, h (PolaJam, JenisTesMaster, RppController::verify, JadwalPelajaranController×3) — PERTAHANKAN struktur ternary/percabangan `widestScopeLevel() === 'yayasan' ? ... : $user->lembaga_id` PERSIS seperti kode asli.** JANGAN panggil `resolveActiveLembagaId()` unconditional di luar percabangan itu — untuk actor platform, kode asli sengaja TIDAK PERNAH membaca session sama sekali, dan memanggil trait method itu unconditional akan membuat actor platform ikut membaca session yang tidak relevan untuknya (regresi baru). Ini poin yang SEBELUMNYA salah saya tulis sendiri saat brainstorming dan sudah diperbaiki di plan final — implementer harus ikuti versi final di plan, bukan menebak ulang.
5. **Race condition Jadwal Pelajaran (Task 8)** dikunci lewat `JamPelajaran::where('id', $data->jamPelajaranId)->lockForUpdate()->first()` — BUKAN mengunci `Semester` seperti kasus Komponen Penilaian di putaran 3. Lock diambil di AWAL closure `DB::transaction()`, SEBELUM pengecekan bentrok ruangan/slot/guru manapun dimulai.
6. **Task 7's 3 test barunya SEBAGIAN BESAR bersifat regresi, BUKAN pembuktian penutup celah aktif** — sudah dijelaskan detail di plan ("Catatan kejujuran"). Implementer/reviewer JANGAN salah paham menganggap `update()`/`duplicate()` di Task 7 sebagai perbaikan yang "menutup lubang keamanan aktif" — `TenantScope` (Task 1) sudah menutup jalur eksploitasi praktisnya duluan lewat route-model-binding/`findOrFail()`. Perbaikan di titik itu tetap dikerjakan (kebersihan kode + defense-in-depth), tapi jangan melebih-lebihkan klaim di commit message/laporan akhir.
7. Tidak pindah branch, tetap di `akademik-v2`.

## Fakta Operasional

- Jalankan `ps aux | grep artisan | grep -v grep` sebelum test suite apa pun — jangan sampai 2 proses `php artisan test` jalan bersamaan.
- `TenantScope` (Task 1) dipakai HAMPIR SEMUA model `BelongsToTenant` di seluruh aplikasi (bukan cuma Akademik) — WAJIB jalankan regresi luas setelah Task 1 (`--filter=Akademik`, `--filter=Guru`, `--filter=Rpp`, dst — sudah ada di Step 6 Task 1), jangan cuma test file yang baru ditambah.
- Test SPMB yang kadang flaky (`tests/Feature/Spmb/WizardShellComponentsTest.php`) — kalau gagal, jalankan ulang sendirian untuk konfirmasi flaky, BUKAN regresi paket ini.
- `vendor/bin/pint --dirty --format agent` wajib setelah setiap task yang mengubah PHP, SEBELUM commit.
- Beberapa test butuh baca file test existing dulu untuk field wajib FormRequest yang mungkin belum lengkap di payload plan (dicatat eksplisit di Task 2 dan Task 7 Step 1) — kalau ternyata ada field wajib tambahan, tambahkan ke payload, JANGAN mengubah rules FormRequest untuk "memudahkan" test lolos.
- Format commit: judul singkat bahasa Indonesia teknis (`fix(...): ...`), akhiri dengan `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`.

## Kalau Menemukan Ambiguitas

STOP dan laporkan ke user, jangan menebak-nebak, terutama untuk:
- Field wajib FormRequest yang berbeda dari asumsi plan (`StoreKelasRequest`, `StoreJadwalPelajaranRequest`, dll — plan sudah eksplisit minta baca dulu sebelum finalisasi test).
- Kalau ternyata Task 7's test #1 (`store()`, session stale) hasilnya BUKAN `assertStatus(302)` seperti prediksi plan (mis. malah error 500 atau tetap 404 karena alasan lain) — itu tanda ada interaksi tak terduga antara Task 1 dan `store()`'s logic lain yang belum dipertimbangkan, laporkan sebelum memaksakan assertion supaya lolos.
- Kalau ada test existing yang GAGAL akibat Task 1 (TenantScope menyempit ke fallback yayasan) — tinjau dulu apakah test lama itu SENDIRI mengasumsikan bug lama sebagai benar (kemungkinan besar bukan, karena Task 1 hanya mengubah kasus session BASI, bukan kasus valid/absen) — kalau ragu, laporkan sebelum mengubah assertion test lama.

## Catatan Serah-Terima (di luar scope paket ini, JANGAN dikerjakan)

- `JalurPpdbController`/`GelombangPpdbController` (session-staleness PPDB) — utang teknis dari putaran 3, masih ditunda.
- Pola serupa di modul SPMB (`SkPpdbController`, `TagihanSusulanController`, `PendaftaranAdminController`) — temuan baru dari audit putaran 4, di LUAR modul Akademik, dicatat terpisah untuk audit SPMB berikutnya.
- Cutoff edit presensi — item lama tanpa keputusan desain, masih ditunda.
- Gap Activitylog `ProsesKenaikanKelasAction` — utang teknis dari putaran 3, masih ditunda.
- `JadwalPelajaranController::index()` baris 71 (filter dropdown session-based) — murni UI, sengaja di-skip (lihat spec §2.2 catatan).

## Mulai dari Mana

Gunakan `superpowers:subagent-driven-development`. **Urutan WAJIB**: Task 1 dulu (root-fix, banyak task lain berasumsi ini sudah selesai untuk hasil test yang benar) → Task 2 → 3 → 4 → 5 → 6 → 7 → 8 (independen, bisa kapan saja setelah Task 1, tapi ikuti urutan plan) → Task 9 (full suite final). Setiap task: implementer subagent → task reviewer subagent → fix loop kalau ada temuan Critical/Important → lanjut task berikutnya. Setelah Task 9 (full suite hijau), lakukan final whole-branch review sebelum melapor selesai ke user — review khusus perhatikan poin 6 di atas (jangan sampai laporan akhir melebih-lebihkan dampak fix Task 7).
