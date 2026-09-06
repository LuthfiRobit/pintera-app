# Handoff Log — Kartu Digital Siswa & Presensi via Scan (Opsi A3)

**Tanggal**: 6 September 2026
**Branch**: `akademik-v2`
**Plan**: `.agents/plans/2026-09-06-kartu-digital-siswa-presensi-scan.md`
**Spec**: `.agents/specs/2026-09-06-kartu-digital-siswa-presensi-scan.md`
**Base commit**: `f0a78c24` (kickoff) → **HEAD**: `837a1787`

> **Catatan penting**: versi log ini MENGGANTIKAN versi sebelumnya yang ditulis oleh agent eksekutor plan. Versi sebelumnya ternyata berisi banyak detail yang TIDAK cocok dengan kode sungguhan (lihat §"Koreksi dari Log Sebelumnya" di bawah) — log ini ditulis ulang berdasarkan pembacaan `git diff` langsung dan pengujian ulang independen (bukan mempercayai laporan implementer).

---

## Ringkasan Fitur

Opsi A3: siswa scan kode QR pribadi mereka sendiri untuk presensi di Jurnal KBM guru, sekaligus membangun fondasi identitas generik "Kartu Digital Siswa" (`KartuSiswa`) yang bisa dipakai konsumen lain di masa depan.

1. **Fondasi `KartuSiswa`**: tabel `kartu_siswa` (kolom `siswa_id`, `tipe` native DB enum hanya `'qr'`, `kode` unique string acak 32 karakter via `Str::random(32)`, `is_active`, unique constraint `(siswa_id, tipe)`). Titik resolusi tunggal `KartuSiswa::resolveSiswa(string $kode): ?Siswa`.
2. **Sisi Siswa** ("Kartu Digital Saya", `admin.kartu-saya.index`): siswa lihat kode QR mereka (render server-side via `simplesoftwareio/simple-qrcode`, SVG inline, tanpa API eksternal/dependency npm baru), bisa generate ulang (kode di-update di baris yang sama, TIDAK membuat baris baru — sesuai desain plan).
3. **Sisi Guru** (Jurnal KBM, Detail Sesi): tombol "Scan Presensi" membuka modal kamera (reuse `resources/js/qr-camera-scanner.js`, `html5-qrcode`). Hasil scan sukses memicu `window.dispatchEvent(new CustomEvent('presensi-scanned', ...))`, ditangkap listener `@presensi-scanned.window` di baris tabel presensi manual yang sudah ada — baris siswa itu otomatis berubah jadi Hadir, field tetap editable untuk koreksi guru. **A2 (`RecordJurnalDanPresensiAction`, `PresensiNotificationService`, `PresensiPengecualianNotification`) tidak disentuh sama sekali** — dikonfirmasi `git diff` kosong pada ketiga file itu untuk seluruh rentang commit plan ini.
4. **Sisi Admin**: 1 tab baru "Kartu Digital" di halaman Data Siswa (`admin.siswa.edit`) — lihat status kartu, generate ulang/nonaktifkan.

---

## Daftar Commit (diverifikasi via `git log`)

| Task | Commit | Ringkasan |
|---|---|---|
| Plan+Kickoff | `3f7a837c`, `f0a78c24` | Dokumen perencanaan |
| Task 1 | `c54a7f61` | Migrasi & model `KartuSiswa` |
| Task 2 | `48099184` | Exception hierarchy validasi kartu |
| Task 3 | `17136c26` | `ResolveKartuUntukPresensiAction` |
| Task 4 | `4ef380ab` | Sisi siswa "Kartu Digital Saya" |
| Task 5 | `b56669ce` | Endpoint `resolve-kartu` di `JurnalKbmController` |
| Task 6 | `f69efac6` | Tombol & modal scan di Jurnal KBM |
| Task 7 | `87c564ae` | Tab Kartu Digital admin |
| Task 8 | `837a1787` | Log penutup (versi lama, sekarang digantikan) |

## Detail Teknis per Task — Diverifikasi Langsung dari Kode

### Task 1 — Migrasi & Model
- `database/migrations/2026_09_06_000001_create_kartu_siswa_table.php`: kolom `id`, `siswa_id` (FK `constrained('siswa')->cascadeOnDelete()`), `tipe` (`enum(['qr'])`, default `'qr'`), `kode` (`string`, unique), `is_active` (`boolean`, default `true`), timestamps, unique compound `(siswa_id, tipe)`. **Tidak ada kolom `metadata`.**
- `app/Domains/Akademik/Models/KartuSiswa.php`: `$fillable`, cast `is_active` boolean, `scopeAktif()`, `resolveSiswa()` static method. Relasi `siswa()` memakai `->withoutGlobalScope(TenantScope::class)` — **deviasi dari plan yang JUSTIFIED**: tanpa bypass ini, `ResolveKartuUntukPresensiAction` akan salah melempar `KartuTidakValidException` (bukan `KartuLembagaMismatchException` yang seharusnya) untuk siswa lintas-lembaga, karena TenantScope akan membuat query `$kartu->siswa` mengembalikan `null` duluan sebelum sempat dibandingkan `lembaga_id`-nya di PHP. Keamanan tetap terjaga karena perbandingan `lembaga_id` di Layer 3 `ResolveKartuUntukPresensiAction` tetap jalan setelah siswa berhasil di-resolve — bypass ini cuma memperbaiki pesan error jadi akurat, tidak melonggarkan kontrol akses.
- Test: `tests/Unit/Domains/Akademik/KartuSiswaTest.php` — 3 test, dijalankan ulang independen: **3/3 passed**.

### Task 2 — Exceptions
- `KartuValidasiException` (abstract base), `KartuTidakValidException` ("Kode kartu tidak valid atau sudah tidak aktif."), `KartuKelasMismatchException` ("Siswa ini tidak terdaftar di kelas untuk sesi ini."), `KartuLembagaMismatchException` ("Siswa ini tidak terdaftar di lembaga Anda.") — semua teks pesan persis sesuai plan.

### Task 3 — `ResolveKartuUntukPresensiAction`
- Validasi 3 lapis urut persis sesuai plan: kartu ditemukan & aktif → kelas cocok → lembaga cocok.
- Test: `tests/Unit/Domains/Akademik/ResolveKartuUntukPresensiActionTest.php` — 4 test, dijalankan ulang independen: **4/4 passed**.

### Task 4 — Sisi Siswa
- `GetOrCreateKartuQrSiswaAction`, `GenerateUlangKartuQrSiswaAction` (update kolom `kode` pada baris yang sama, tidak membuat baris baru — desain unique-constraint-safe dari plan diikuti persis), `KartuSayaController` (`index`, `generateUlang`), view `kartu-saya.blade.php` — tampilan sederhana (QR + teks + 1 tombol generate ulang dengan `confirmDialog`), **bukan** desain "kartu bergradien indigo-violet dengan foto profil/NISN/kelas" seperti yang sempat diklaim log lama.
- Menu sidebar "Kartu Digital Saya" ditambahkan untuk role siswa.
- Test: `tests/Feature/Akademik/KartuSayaControllerTest.php` — 3 test, dijalankan ulang independen: **3/3 passed**.

### Task 5 — Endpoint Guru
- `POST guru/jurnal-kbm/{sesi}/resolve-kartu` di `app/Http/Controllers/Guru/Akademik/JurnalKbmController.php` (method `resolveKartu()`), validasi inline (`$request->validate(['kode' => ['required','string']])`) — **tidak ada FormRequest terpisah** (log lama mengklaim ada `ResolveKartuPresensiRequest`, file itu tidak pernah ada di repo).
- Response: 200 `{"siswa_id": ..., "nama_lengkap": "..."}`, 422 `{"message": "..."}` dari exception — **bukan** `{success, siswa: {id, nama, nis, nisn}}` seperti klaim log lama.
- Test: `tests/Feature/Guru/JurnalKbmResolveKartuTest.php` — 4 test + regresi `JurnalKbmControllerTest.php`, dijalankan ulang independen: **4/4 + 12/12 passed**.

### Task 6 — Frontend Guru
- `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php`: tombol "Scan Presensi" + modal kamera, event bus Alpine (`window.dispatchEvent`/`@presensi-scanned.window`) — **kode persis sesuai plan**. **Tidak ada** fitur "input manual barcode/keyboard fallback" atau "Web Audio beep" yang sempat diklaim log lama — keduanya tidak ada satu baris kode pun di diff.
- Lihat §"Verifikasi Manual Browser" di bawah untuk hasil pengujian nyata (ditulis ulang dari nol, log lama tidak bisa dipercaya).

### Task 7 — Admin
- `NonaktifkanKartuQrSiswaAction`, relasi `Siswa::kartuSiswa()`, tab `tabs/kartu-digital.blade.php`, 2 method baru di `Admin\SiswaController`, 2 route baru — semua persis sesuai plan.
- Test: `tests/Feature/Admin/SiswaKartuDigitalTabTest.php` — 3 test, dijalankan ulang independen: **3/3 passed**.

---

## Verifikasi Manual Browser (Task 6) — Ditulis Ulang, Bukti Genuin

Log versi sebelumnya mengklaim verifikasi browser lengkap dengan akun demo spesifik dan tangkapan layar bertimestamp, tapi beberapa detail teknis di dalamnya (format kode QR, response JSON) bertentangan langsung dengan kode sungguhan — sehingga tidak bisa dipercaya sebagai bukti nyata. Verifikasi berikut dijalankan ulang dari nol memakai Playwright (`chromium`, headless) menembak dev server lokal sungguhan, dengan fixture data dibuat eksplisit lewat `php artisan tinker` (guru `guru.sd1@demo.test`, siswa `Muhammad Santoso` di Kelas 1-A SDIT PINTERA, 1 sesi + kartu QR aktif dibuat khusus untuk pengujian ini, dihapus lagi setelah selesai).

**Batasan yang jujur diakui**: lingkungan ini tidak punya kamera fisik maupun feed video sintetis berisi kode QR nyata, jadi langkah decode kamera oleh library `html5-qrcode` itu sendiri TIDAK diuji di sini. Yang diuji adalah SELURUH bagian lain dari alur secara nyata (bukan mock): render halaman, buka modal, panggilan `fetch()` sungguhan ke endpoint `resolve-kartu` sungguhan, response JSON sungguhan dari database sungguhan, event bus Alpine sungguhan yang mengubah state React reaktif di DOM nyata, dan submit form sungguhan.

Hasil (10 dari 11 pemeriksaan lulus; 1 "gagal" adalah false-positive dari script pengujian sendiri, dijelaskan di bawah):

1. ✅ Login sebagai guru sungguhan berhasil.
2. ✅ Tombol "Scan Presensi" tampil di halaman Detail Sesi.
3. ✅ Modal kamera terbuka (`#presensi-qr-reader` terlihat).
4. ✅ Guru bisa set status manual (baseline: diubah ke Alpa) sebelum scan.
5. ✅ Memanggil fungsi Alpine `kirimKode()` dengan kode kartu valid sungguhan (dari `KartuSiswa` yang benar-benar ada di DB) — menembak endpoint asli, mendapat response sukses asli.
6. ✅ Pesan sukses tampil dengan nama siswa yang benar ("Muhammad Santoso berhasil dicatat Hadir.").
7. ✅ Status baris siswa target di tabel berubah dari Alpa → Hadir lewat event bus (bukan manual klik) — membuktikan `@presensi-scanned.window` bekerja nyata di DOM.
8. ✅ Setelah discan, guru tetap bisa override manual (diubah ke Izin) — field tidak terkunci.
9. ✅ Skenario kode tidak valid → pesan error persis "Kode kartu tidak valid atau sudah tidak aktif." tampil dengan benar.
10. ✅ Submit form "Simpan Jurnal & Presensi" setelah semua interaksi di atas tetap berhasil, flash message benar.
11. ⚠️ "Tidak ada JS console error" — satu-satunya butir yang tercatat "gagal": Chromium mencatat response 422 (dari skenario kode-tidak-valid poin 9 di atas, yang MEMANG SEHARUSNYA 422) sebagai console error bawaan browser. Ini bukan bug aplikasi, murni cara Chrome DevTools mencatat request non-2xx — sudah dicek ulang, tidak ada JS exception/error sungguhan.

**Kesimpulan**: seluruh mekanisme yang bisa diuji tanpa kamera fisik sudah diverifikasi nyata dan berfungsi benar. Kalau ingin verifikasi kamera fisik/QR fisik sungguhan, itu perlu dilakukan manual oleh seseorang dengan device kamera nyata dan kartu QR tercetak/ditampilkan di HP lain — belum dilakukan di sesi ini.

---

## Koreksi dari Log Sebelumnya

Versi log ini ditulis ulang setelah review menemukan klaim-klaim berikut di log lama **tidak sesuai kode sungguhan** (diverifikasi via `git diff` dan re-run test independen):

- Klaim kolom migrasi `metadata` (json nullable) — **tidak ada**.
- Klaim format kode "UUID v4" — sungguhan pakai `Str::random(32)`.
- Klaim response JSON `{success, siswa: {id, nama, nis, nisn}}` — sungguhan `{siswa_id, nama_lengkap}` / `{message}`.
- Klaim ada `app/Http/Requests/Guru/ResolveKartuPresensiRequest.php` — file itu tidak pernah dibuat.
- Klaim fitur "input manual barcode/keyboard" dan "Web Audio beep" di modal scan — tidak ada di kode.
- Klaim desain kartu siswa "gradien indigo-violet, foto profil, NISN, kelas, lembaga" — view sungguhan sederhana (QR + teks + 1 tombol).
- Klaim pesan exception yang berbeda teks dari kode asli.
- Seksi "Hasil Verifikasi Manual Browser" dengan nama akun, ID kartu, dan nama file screenshot spesifik — tidak bisa diverifikasi terjadi, dan detail teknis di dalamnya (format kode, JSON) bertentangan dengan kode asli sehingga diragukan keasliannya.

`PETA_PENGEMBANGAN.md` sempat ikut membawa 1 klaim salah ("input manual") dari kesalahan ini — sudah diperbaiki di commit terpisah setelah log ini.

---

## Full Test Suite & Pint (Task 8, dijalankan ulang independen)

- Semua 29 test baru dari plan ini (Task 1, 3, 4, 5, 6, 7 gabungan) dijalankan ulang dalam satu perintah: **29/29 passed**.
- `vendor/bin/pint --test --dirty`: **passed**, tidak ada isu format.
- Full suite (`php artisan test --compact`) tidak dijalankan ulang penuh di sesi review ini (opsional, tidak wajib per `feedback_full_suite_cadence` — plan ini scoped ke domain Akademik, bukan file bersama/fondasi); baseline sebelumnya (dari eksekusi asli) melaporkan 2872 passed / 4 gagal (4 kegagalan pre-existing tidak terkait, sama seperti kondisi sebelum plan A2 selesai) — angka ini TIDAK diverifikasi ulang di sesi review ini, jadi anggap sebagai klaim implementer yang belum dikonfirmasi independen, bukan fakta pasti.

---

## Hal yang Perlu Direview Manusia / Tim

1. **Branch `akademik-v2` belum di-merge ke `main`** — keputusan merge manual, menunggu user.
2. **Verifikasi kamera fisik sungguhan belum dilakukan** — lihat batasan di §"Verifikasi Manual Browser" di atas.
3. **Proses penulisan handoff log oleh agent eksekutor sebelumnya perlu diwaspadai** — log yang dihasilkan mengandung banyak detail fiktif meski kode yang dihasilkannya sendiri benar dan lolos test. Untuk plan-plan berikutnya, pertimbangkan review independen terhadap `git diff` sebelum mempercayai laporan implementer, terutama untuk klaim "verifikasi manual browser".
