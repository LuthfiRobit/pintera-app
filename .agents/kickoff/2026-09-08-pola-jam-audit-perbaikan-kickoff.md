# Kickoff: Audit & Perbaikan Menu Pola Jam

**Base commit**: `cad4c7d5` (`docs(pola-jam): implementation plan audit & perbaikan (IDOR + 5 item UX)`)
**Branch**: `akademik-v2` (SETARA `rbac-v2`, tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit menu Pola Jam & Jam Pelajaran menemukan **1 bug keamanan kritis** dan **5 celah UI/UX**, digabung dalam 1 plan atas persetujuan user ("jika tidak ada resiko mending digabung saja") — fix keamanan menyentuh file berbeda (`JamPelajaranController`) dari 5 item UX (`PolaJamController` + view), jadi aman digabung.

**Temuan kritis (Item A / Task 1)**: `JamPelajaranController::destroy()` TIDAK PUNYA pengecekan tenant sama sekali — aktor lembaga-scope dengan permission `jam-pelajaran.delete` (SUDAH digrant ke role `operator_akademik`, role staf akademik biasa) bisa menghapus slot Jam Pelajaran milik lembaga/yayasan LAIN, cukup dengan tahu ID-nya. **Dibuktikan empiris** lewat test HTTP sementara (dibuat lalu dihapus lagi setelah verifikasi): actor lembaga A `DELETE` slot milik lembaga B → HTTP 302 sukses → slot benar-benar terhapus. Root cause: model `JamPelajaran` tidak punya `BelongsToTenant`/`lembaga_id` sendiri, dan `destroy()` (beda dari `edit()`/`update()` di file yang sama, yang sudah benar) tidak melakukan pengecekan manual lewat `PolaJam::find()`.

**5 item UX (Item B-F / Task 2-6)**: badge scope di header index (satu-satunya menu di rangkaian audit ini yang belum punya), pill nama lembaga per-kartu yang selalu muncul (redundan di mode narrow), hint tombol "+ Tambah Pola Jam" saat mode agregat (opsional/prioritas rendah), halaman `create()`/`edit()` yang ternyata mati total (alur nyata 100% lewat modal Alpine di index, bukan halaman terpisah — pola sama dengan temuan halaman mati Tahun Ajaran sebelumnya), dan tombol Duplikat yang TIDAK PUNYA konfirmasi apapun (klik langsung membuat record baru) — ini kemungkinan besar akar "kebingungan user" yang jadi trigger audit ini.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-08-pola-jam-audit-perbaikan.md` — spec lengkap 6 item, termasuk detail root-cause Item A dan catatan koreksi (`scopeHeaderData()` sempat salah di draf pertama, sudah diperbaiki + diverifikasi ulang terhadap kode aktual).
2. `.agents/plans/2026-09-08-pola-jam-audit-perbaikan.md` — 7 task TDD, kode lengkap tiap step, sudah self-review.

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Task 1 (fix IDOR) WAJIB diselesaikan & di-commit LEBIH DULU, terpisah dari task lain** — ini fix keamanan LIVE di production, jangan ditunda demi item UX apapun.
- **Model `PolaJam` sendiri TIDAK diubah** — scoping-nya sudah benar lewat `BelongsToTenant`/`TenantScope` (dikonfirmasi via `tests/Feature/TenantScopeTest.php` yang sudah teruji solid, bukan re-implementasi manual seperti kasus lama Kurikulum Assignment). Fix HANYA di `JamPelajaran` (child model tanpa `BelongsToTenant`).
- **`PolaJamController.php` SAAT INI belum meng-`use App\Models\Lembaga;`** — Task 2 WAJIB menambahkan import ini. Tanpanya, `Lembaga::withoutGlobalScopes()->find()` di `scopeHeaderData()` akan fatal error.
- **`scopeHeaderData()` (Task 2) HARUS transkripsi PERSIS** dari kode di plan — sudah dicek ulang identik di `MataPelajaranController`/`KelasController`/`GuruController`/`TahunAjaranController`. JANGAN menulis ulang dari ingatan/asumsi: `resolveActiveLembagaId()` dipanggil TANPA gate `$isYayasan` di depan, dan `Lembaga::find()` WAJIB pakai `withoutGlobalScopes()`.
- **`_modal-pola.blade.php`, `_modal-edit-slot.blade.php`, `_modal-assign-kelas.blade.php` TIDAK disentuh** — hanya `index.blade.php`.
- **Permission `pola-jam.create`/`pola-jam.edit` di seeder TETAP DIPERTAHANKAN** di Task 5 (hapus halaman mati) — HANYA route GET + method controller + file view yang dihapus, permission-nya masih dipakai `@can()` di tombol modal dan `authorize()` di `store()`/`update()`/`duplicate()`.
- **Task 5 Step 1 (verifikasi grep + route:list) adalah gerbang WAJIB sebelum destructive action** — kalau ternyata ADA link navigasi ke `pola-jam.create`/`pola-jam.edit` di luar `@can()`, STOP total, jangan lanjut hapus apapun, laporkan ke user (berarti asumsi "halaman mati" salah).
- **Bug sistemik `TenantScope` untuk aktor platform-scope TIDAK disentuh** — backlog terpisah, konsisten dengan semua spec sebelumnya di rangkaian audit ini.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **Test home**: SEMUA test Item A baru masuk ke `tests/Feature/Admin/JamPelajaranCrudTest.php`; SEMUA test Item B-F baru masuk ke `tests/Feature/Admin/PolaJamCrudTest.php`. Helper existing: `actingAsJamPelajaranManager(Lembaga $lembaga, array $permissions = ['jam-pelajaran.edit', 'jam-pelajaran.delete'])` dan `actingAsPolaJamManager(Lembaga $lembaga): User` (lembaga-scope, role `operator_akademik`) — JANGAN bikin helper baru. TIDAK ADA helper untuk aktor yayasan-scope di `PolaJamCrudTest.php` — ikuti pola bikin role+user inline yang sudah ada di file yang sama (baris 62-115 sebagai contoh).
- **Task 3 Step 1 test kedua** pakai `substr_count($response->getContent(), ...) === 1`, BUKAN `assertDontSee()` — sengaja, karena nama lembaga tetap muncul SEKALI lewat badge header (dari Task 2) di mode narrow, jadi `assertDontSee()` polos akan salah gagal.
- **`Kelas` dan model lain yang dipakai test (`Lembaga`, `Yayasan`, `Role`, `User`, `Permission`) sudah di-`use` di kedua file test** — cek dulu sebelum menambah import baru, kemungkinan besar sudah tersedia.
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 7.

## 5. Instruksi Stop-and-Report

- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode, catat perbedaannya di laporan task.
- **Kalau Task 5 Step 1 menemukan link navigasi ke `pola-jam.create`/`pola-jam.edit` di luar `@can()`** — STOP TOTAL, JANGAN hapus apapun, laporkan ke user sebelum melanjutkan.
- **Kalau Task 1 Step 5 (regresi `JamPelajaranCrudTest.php` penuh) menunjukkan test "deletes a jam pelajaran with no jadwal pelajaran" atau "refuses to delete a jam pelajaran that has a jadwal pelajaran" GAGAL** — STOP TOTAL, laporkan ke user (berarti guard baru mengganggu delete yang sah dalam scope sendiri, bertentangan dengan premis fix).
- **Kalau Task 7 Step 2 (regresi `RolePermissionSeederTest`) GAGAL** — STOP TOTAL, laporkan ke user (berarti Task 5 tidak sengaja menyentuh permission seeder, padahal seharusnya hanya route/method/view yang dihapus).

## 6. Catatan Serah Terima

- Item A ditemukan BUKAN dari screenshot/laporan user, tapi dari inisiatif audit ulang sendiri setelah user secara eksplisit minta "coba audit lagi khawatir ada kekeliruan yang lain" — perlakukan dengan prioritas SETARA atau LEBIH TINGGI dari 5 item UX lain, karena ini keamanan data lintas-tenant nyata dan LIVE, bukan sekadar UX.
- Spec sempat mengandung 2 kekeliruan (import `Lembaga` hilang, pola `scopeHeaderData()` ditulis ulang dari ingatan alih-alih transkripsi persis) — SUDAH DIPERBAIKI dan diverifikasi ulang langsung terhadap kode aktual sebelum plan ini ditulis. Plan sudah memuat versi yang benar, tidak perlu dicurigai lagi.
- **User secara eksplisit meminta handoff log dibuat sebagai bagian dari kickoff ini** (beda dari plan-plan sebelumnya di rangkaian audit ini yang secara eksplisit TIDAK meminta handoff log) — Task 7 Step 5 di plan SUDAH mencakup instruksi ini, WAJIB dikerjakan, bukan opsional. File: `.agents/logs/2026-09-08-pola-jam-audit-perbaikan.md`, isi minimal: ringkasan temuan Item A (termasuk bukti empiris sebelum/sesudah fix), daftar 6 item yang diperbaiki, commit hash tiap task, hasil regresi test.
- Setelah Task 7 selesai, JANGAN merge branch `akademik-v2` ke branch manapun — keputusan terpisah milik user.

## 7. Mulai dari mana

Mulai dari **Task 1** (fix IDOR — prioritas tertinggi, harus selesai & commit lebih dulu) di `.agents/plans/2026-09-08-pola-jam-audit-perbaikan.md`, kerjakan berurutan Task 1 → 7, JANGAN dilompat/dibalik urutannya (lihat Keputusan Kritis di atas kenapa urutan ini penting).
