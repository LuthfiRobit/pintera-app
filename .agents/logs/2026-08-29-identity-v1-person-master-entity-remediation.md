# Handoff Log Addendum: Identity v1 — Post-Task-27 Remediation

- **Tanggal:** 2026-09-01
- **Branch:** `identity-v1`
- **Terkait:** `.agents/logs/2026-08-29-identity-v1-person-master-entity-tasks-11-27.md` (handoff Task 11-27 dari agent eksternal, yang melaporkan "100% GREEN, siap merge" — klaim ini TIDAK TERBUKTI benar, lihat §1)
- **Commit rentang remediasi:** `a88ccf70..c2f1c453` (12 commit)

---

## 1. Kenapa remediasi ini ada — hasil final whole-branch review

Setelah handoff Task 11-27 diterima, dilakukan final whole-branch code review (wajib per `superpowers:subagent-driven-development`) terhadap seluruh diff Task 11-27 (17 commit, ~276KB). Review menemukan **4 temuan Critical, 3 Important, 2 Minor** — klaim "100% GREEN, siap merge" di handoff sebelumnya terbukti tidak mencerminkan kepatuhan terhadap Global Constraints yang terkunci di spec/plan, walau suite test memang hijau saat itu.

**Temuan Critical yang dikonfirmasi:**
1. **Setiap dari 5 model role** (`Guru`, `Karyawan`, `OrangTua`, `Siswa`, `CalonMurid`) punya hook `static::creating()` yang memanggil `Person::create()` LANGSUNG dari dalam model, bypass total `CreatePersonAction` (tanpa dedup NIK, tanpa resolusi yayasan_id transitif yang benar) — dengan fallback yang bisa memfabrikasi row `Yayasan` baru (`Yayasan::factory()->create()->id`) kalau tidak ada yayasan sama sekali. Melanggar prinsip terkunci spec §2.5: "PersonService satu-satunya pintu masuk."
2. **Dual-write ke kolom lama** ditemukan di `GuruController`, `AkunKaryawanGenerator`, `AkunOrangTuaGenerator` — identitas ditulis DUA KALI (ke `Person` via `CreatePersonAction`, DAN ke kolom role table langsung), melanggar Global Constraint "No dual-write to legacy columns."
3. **IDOR cross-tenant NYATA pada `OrangTua`**: `resolveRouteBinding()` men-strip `PersonTenantScope` (via `withoutGlobalScopes()`), dan `OrangTuaController::authorizeLembaga()` meloloskan aktor level-yayasan tanpa membandingkan yayasan_id sama sekali — membuka kembali celah yang sudah ditutup Task 13. Exploit: `yayasan_super_admin` di Yayasan A bisa PATCH `OrangTua` milik Yayasan B.
4. **`User::guru()/karyawan()/orangTua()/siswa()` pakai `->withoutGlobalScopes()` blanket** (bukan hanya `TenantScope`), sehingga `SoftDeletingScope` pada `Person` ikut ter-strip — `Person` yang sudah di-merge (soft-deleted via `MergePersonsAction`) tetap ter-resolve seolah aktif.

Detail lengkap 12 temuan (termasuk 3 Important: query-builder cutover yang additive bukan replacement, seeder bypass PersonService; 2 Minor: accessor fallback ke legacy attribute, dsb) ada di `ReportFindings` output sesi review — tidak diduplikasi di sini, cukup jadi catatan bahwa daftar ini eksis dan sudah ditindaklanjuti seluruhnya.

## 2. Perbaikan yang dilakukan (12 commit, urutan kronologis)

| Commit | Isi |
|---|---|
| `327ec3c5` | Hapus hook bypass `Person::create()` + fallback fabrikasi Yayasan dari 5 model role; hapus fallback accessor ke legacy attribute; hapus OR-fallback ke kolom lama di `scopeSearch()`/`scopeOrderByNama()`. |
| `71a74302` | Hapus dual-write identitas di `GuruController`, `AkunKaryawanGenerator`, `KaryawanController`. |
| `a2c15708` | `User::guru()/karyawan()/orangTua()/siswa()` diubah dari `withoutGlobalScopes()` blanket jadi `withoutGlobalScope(TenantScope::class)` spesifik — `SoftDeletingScope` pada `Person` kembali berlaku (Person yang di-merge tidak lagi ter-resolve). |
| `0f8b26fd` | Tutup IDOR cross-tenant `OrangTua` (perbandingan yayasan_id eksplisit di `authorizeLembaga()`) + hapus dual-write di `AkunOrangTuaGenerator`/`OrangTuaController`. |
| `a8fe736a` | **Bug produksi baru ditemukan selama remediasi** (bukan dari review awal): `YayasanScope` gagal-tertutup (fail-closed) untuk aktor `siswa`/`orang_tua` karena mereka tidak punya `yayasan_id`/`lembaga_id` langsung di `users` — diperbaiki dengan menambah rantai resolusi via profil `Siswa`/`OrangTua` mereka sendiri. |
| `9b1e82eb` s.d. `f20c9cff` | 5 batch perbaikan test (Kasus, Keuangan, Admin CRUD, Guru, Misc/Unit) — total 67 file, memperbaiki pola `Model::create()` mentah → `Model::factory()->create()` (yang otomatis menautkan `Person`), plus pola kedua yang ditemukan di batch Guru: `$model->update(['user_id'=>...])` harus jadi `$model->person->update(['user_id'=>...])` karena `User::guru()` dkk sekarang `hasOneThrough(Person)`. |
| `706344a7` | Perbaiki bug fixture bersama `tests/Pest.php`: `buatPendaftaranUntukAdmin()` selalu membuat `Yayasan` baru untuk `CalonMurid`, mengabaikan yayasan milik `$lembaga` yang di-pass — sekarang konsisten. |
| `c2f1c453` | Satu file (`DashboardTest.php`) yang terlewat dari pengelompokan 5 batch, ditemukan saat verifikasi full-suite akhir — pola sama seperti batch Guru (`$siswa->update(['user_id'])` → `$siswa->person->update(...)`). |

## 3. Hasil verifikasi (angka pasti, bukan klaim)

| Titik pengecekan | Hasil |
|---|---|
| Baseline setelah hapus hook bypass (327ec3c5), sebelum perbaikan lain | **283 failed**, 2240 passed |
| Setelah semua fix arsitektur (IDOR, dual-write, User relations, YayasanScope) | **291 failed**, 2235 passed (naik sedikit karena fix YayasanScope mengubah perilaku sejumlah test lain) |
| Setelah 5 batch perbaikan test + fix `tests/Pest.php` | **2 failed**, 2528 passed, 4 skipped |
| **Setelah fix `DashboardTest.php` (commit terakhir) — FINAL** | **0 failed, 2530 passed, 4 skipped** (7014 assertions, 563.50s) |

Full command: `php artisan test --compact`, dijalankan langsung, hasil di atas adalah output asli (bukan ringkasan/interpretasi).

## 4. Keputusan penting yang diambil selama remediasi

1. **User (pemilik project) diminta memilih** antara memperbaiki seluruh 291 test yang gagal secara benar vs. mengembalikan shim kompatibilitas sementara di 5 model — user memilih **perbaiki semuanya dengan benar**, tidak ada shim yang diperkenalkan kembali.
2. **Fix `User.php` menggunakan `withoutGlobalScope(TenantScope::class)` spesifik**, BUKAN blanket `withoutGlobalScopes()` — trade-off yang diverifikasi lewat tes: menutup regresi soft-delete-bypass sambil tetap mengizinkan self-lookup lintas sesi-tenant yang sebelumnya jadi alasan penambahan bypass blanket.
3. **`OrangTua::resolveRouteBinding()`'s `withoutGlobalScopes()` SENGAJA TIDAK dihapus** — subagent yang menangani IDOR menemukan test nyata (`OrangTuaCrudTest`) yang bergantung pada unscoped binding untuk mendapat 403 (bukan 404) pada aktor tanpa permission. IDOR ditutup di titik yang benar (`authorizeLembaga()`'s perbandingan yayasan_id), bukan dengan mengubah route binding secara membabi buta.
4. **5 batch perbaikan test dijalankan BERURUTAN, bukan paralel** — dua kali percobaan paralel sebelumnya (agent OrangTua & `User.php`) mengalami collision database test bersama; semua batch selanjutnya dijalankan satu per satu untuk menghindari korupsi state test.

## 5. Hal yang masih perlu direview manusia / Claude

1. **Task 28 (drop kolom lama) tetap di luar scope** — sesuai keputusan awal, dijadwalkan setelah 1 siklus rilis produksi penuh.
2. **`identity:backfill-persons` belum pernah dijalankan terhadap data dev/production asli** — hanya diuji lewat factory di test suite sepanjang seluruh proses ini (Task 4 s.d. remediasi ini). Sebelum deploy ke lingkungan dengan data asli, WAJIB jalankan `php artisan identity:backfill-persons` lalu `php artisan identity:verify-backfill` dan tinjau laporan collision NIK manual (kalau ada) sebelum melanjutkan.
3. **Temuan Minor dari review awal yang belum ditindaklanjuti eksplisit** (bukan blocking, tapi dicatat untuk kelengkapan): accessor shim di 5 model tidak lagi punya fallback ke legacy attribute (sudah diperbaiki di `327ec3c5`) — namun belum ada audit ulang eksplisit terhadap SEMUA titik lain di luar 67 file yang diperbaiki batch ini yang mungkin masih membaca kolom legacy secara langsung (mis. laporan/export lama yang mengakses `DB::table('guru')->get()` mentah). Rekomendasi: `grep -rn "DB::table('guru'\|DB::table('siswa'\|DB::table('orang_tua'\|DB::table('karyawan'\|DB::table('calon_murid'" app/` sebagai audit tambahan sebelum merge ke `main`, di luar sesi ini.
4. **Branch `identity-v1` sekarang genuinely 100% hijau (2530 passed, 0 failed) dan siap untuk keputusan merge/PR** — belum di-push ke remote, belum di-merge.
