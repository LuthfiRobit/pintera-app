# Kickoff: Perbaikan Audit Menyeluruh Halaman Kenaikan Kelas

**Base commit**: `98cababd` (`docs(akademik): plan perbaikan audit menyeluruh kenaikan kelas...`)
**Branch**: `rbac-v2` (TETAP di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit menyeluruh (backend/bisnis/scope + UI/UX/frontend/wording, 2 putaran audit + 1 putaran audit ulang atas permintaan eksplisit user) pada halaman Kenaikan Kelas menemukan 1 Critical, 2 High, 2 Medium, dan gabungan Low — total 7 kelompok perbaikan. **Akar masalah Critical**: `ProsesKenaikanKelasAction` memakai mass-update Query Builder (`Siswa::where(...)->update([...])`) untuk memindahkan/meluluskan siswa — ini melewati SELURUH mekanisme Eloquent model event Laravel sekaligus, dengan 3 akibat: (1) siswa yang "Lulus" tetap bisa login selamanya (akun tidak dinonaktifkan), (2) tagihan SPP baru tidak pernah otomatis dibuat untuk siswa yang naik tingkat (kehilangan pendapatan senyap), (3) tidak ada jejak audit sama sekali untuk aksi bulk ini.

**Fakta penting**: fitur ini SEBELUMNYA sudah pernah disentuh spec lain (`.agents/specs/2026-09-03-siklus-hidup-kelas-id-siswa.md`) yang menetapkan `UpdateStatusSiswaAction` sebagai jalur RESMI untuk transisi status siswa (termasuk deaktivasi akun). Spec itu mendaftarkan `ProsesKenaikanKelasAction` sebagai "titik gratis" — TAPI HANYA dalam artian "query lain yang baca `kelas_id` otomatis benar", BUKAN audit apakah Action ini sendiri memakai jalur resmi itu. Ternyata TIDAK — gap ini genuinely belum tersentuh sebelumnya, dikonfirmasi lewat pembacaan langsung spec lama + kode aktual sebelum spec baru ini ditulis.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-03-siklus-hidup-kelas-id-siswa.md` — KONTEKS PENTING, bukan bagian dari plan ini, tapi WAJIB dipahami dulu karena Task 1 me-reuse `UpdateStatusSiswaAction` yang ditetapkan spec ini. Baca minimal §5 (definisi Action itu) dan §8 (kenapa `ProsesKenaikanKelasAction` sebelumnya dianggap "gratis").
2. `.agents/specs/2026-09-11-kenaikan-kelas-audit-perbaikan.md` — spec lengkap sesi ini, 5 putaran self-review, kode current-vs-fix KONKRET untuk semua 7 kelompok temuan (§2.1-§2.7), plus 4 item yang SENGAJA dikeluarkan dari scope (§3).
3. `.agents/plans/2026-09-11-kenaikan-kelas-audit-perbaikan.md` — 8 task TDD, kode lengkap per task (setiap task MANDIRI), 5 putaran self-review.

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Task 1 WAJIB reuse `App\Domains\Akademik\Actions\Siswa\UpdateStatusSiswaAction`** (SUDAH ADA di path itu persis) untuk tindakan `lulus` — JANGAN duplikasi logic `is_active`/`kelas_terakhir_id` secara terpisah. Draft awal spec sempat begitu, DIKOREKSI sebelum final setelah membaca ulang spec 2026-09-03. Kalau tergoda "lebih simpel tulis ulang logicnya di sini", STOP — itu sudah dipertimbangkan dan ditolak.
- **Task 1 → Task 5 urutan WAJIB** — Task 5 (ringkasan flash message) memakai return value baru (`siswaNaik`/`siswaLulus`/`kelasDilewati`) yang baru ada setelah Task 1.
- **Task 2 → Task 3 → Task 6 → Task 7 urutan WAJIB** — keempatnya menyentuh file `index.blade.php` yang sama, dan Task 7 Step 6 secara eksplisit menulis ulang blok yang SUDAH ditambah binding oleh Task 6 — kalau dikerjakan di luar urutan, kode "current" yang dikutip Task 7 tidak akan cocok dengan kondisi file yang sesungguhnya.
- **Task 1 TIDAK boleh menambah hard-block validasi tingkat/kurikulum di backend** — `tests/Feature/Admin/KenaikanKelasControllerTest.php:286-307` (test existing, JANGAN diubah) secara eksplisit mendokumentasikan keputusan arsitektur "backend tidak pernah menolak kombinasi tingkat apapun, warning hanya di frontend". Kalau menemukan diri berpikir "sekalian validasi keras saja di backend", STOP — itu bertentangan dengan keputusan yang sudah didokumentasikan test existing.
- **Task 6 TIDAK boleh mengubah getter/state `x-data` per-baris yang SUDAH ADA** (`kurikulumAsal`, `kurikulumTujuan`, `tingkatAsal`, `tingkatTujuan`, `selisihIndexTingkat`, `onKelasTujuanChange`) — HANYA menambah binding/attribute baru di elemen `<tr>` yang sama. Test existing (`KenaikanKelasControllerUxTest.php`) meng-assert teks-teks ini APA ADANYA di HTML.
- **Task 7 opsi "Lewati" selalu tersedia** TIDAK boleh mengubah logic `@selected(...)` untuk opsi `naik`/`lulus` yang sudah ada — hanya melonggarkan render opsi `lewati`.
- **4 item SENGAJA TIDAK masuk scope, JANGAN dikerjakan**: notifikasi WhatsApp/email ke siswa/orang tua, halaman pratinjau (preview) terpisah, hard-block validasi tingkat/kurikulum di backend, row lock (`lockForUpdate()`).
- **JANGAN sentuh `app/Domains/Workflow/*`** sama sekali.

## 4. Fakta Operasional

- **Test file modul ini pakai Pest function-style** (`it(...)`, helper function biasa seperti `buatKenaikanAction()`/`actingAsKenaikanKelasManager()`), BUKAN PHPUnit class-based — beda dari modul Pengadaan yang dikerjakan sebelumnya di sesi ini. Perhatikan konvensi ini saat menambah test.
- **4 file test existing yang WAJIB dipakai ulang, JANGAN bikin baru yang fungsinya sama**:
  - `tests/Unit/Domains/Akademik/Actions/KenaikanKelas/ProsesKenaikanKelasActionTest.php` (helper `buatKenaikanAction()`)
  - `tests/Feature/Admin/KenaikanKelasControllerTest.php` (helper `actingAsKenaikanKelasManager(Lembaga $lembaga)`)
  - `tests/Feature/Akademik/KenaikanKelasControllerUxTest.php` (helper `siapkanKenaikanKelasUxUser()`, `htmlSelectByName()`, `selectedOptionValue()`)
  - `tests/Feature/Akademik/KenaikanKelasIndicatorTest.php` (tidak disentuh task manapun, cukup ikut dijalankan sebagai regresi di Task 8)
- **Constructor `ProsesKenaikanKelasAction` bertambah 1 parameter** (`UpdateStatusSiswaAction`) — helper `buatKenaikanAction()` di test Unit WAJIB diupdate bersamaan (sudah ada di Task 1 Step 3), JANGAN lupa atau test lama akan error "too few arguments".
- **Route `admin.kelas.index`** tetap jadi tujuan redirect setelah `store()` sukses — TIDAK berubah di plan ini.

## 5. Instruksi Stop-and-Report

- **Kalau test baru di Task manapun ("harus GAGAL sebelum fix") ternyata sudah PASS** — tanda kode sudah berbeda dari yang didokumentasikan spec/plan. STOP, laporkan detail, jangan asumsikan "berarti sudah aman".
- **Kalau full suite (Task 8 Step 3) menunjukkan kegagalan APA PUN di luar 3 yang sudah dikonfirmasi pre-existing** (`M3DemoDataSeederTest` x2, `SubjekTenantValidationTest`) — STOP TOTAL, laporkan detail SEBELUM melanjutkan.
- **JANGAN jalankan full suite (`php artisan test`) bersamaan dengan proses test lain yang sedang berjalan** — pernah terjadi di sesi ini (audit Pengadaan) 2 proses test paralel ke database test yang sama menghasilkan puluhan kegagalan PALSU akibat rebutan koneksi. Kalau ragu ada proses lain masih jalan, tunggu sampai selesai dulu.
- **Kalau test existing `KenaikanKelasControllerUxTest.php` yang meng-assert isi `x-data` per-baris jadi GAGAL setelah Task 6/7** — STOP, ini tanda getter/state yang seharusnya TIDAK diubah malah ikut ter-modifikasi. Jangan modifikasi test lama supaya lolos — itu tanda bug di implementasi.
- **Kalau Task 1 menemukan `Siswa::factory()` default TIDAK otomatis membuat `user_id`/relasi `User`** (test baru Task 1 butuh `user_id` terisi untuk mengecek `is_active`) — cek dulu factory `Siswa` sebelum menyesuaikan test, kalau ternyata perlu setup tambahan (mis. `User::factory()->create()` eksplisit lalu di-assign), itu penyesuaian wajar, BUKAN alasan mengubah logic Task 1 sendiri.

## 6. Catatan Serah Terima

- Spec ditulis dan direview 5 kali dalam sesi yang sama. Plan ditulis dan direview 5 kali (sesuai permintaan user eksplisit "3-5 kali").
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (Task 8 eksplisit hanya verifikasi + laporan) — kalau user memang menghendaki handoff log terpisah setelah eksekusi, itu permintaan tambahan mengikuti pola project-project sebelumnya di sesi ini.
- **Data siswa lama** (yang sudah terlanjur "lulus" lewat halaman ini SEBELUM perbaikan, akun mungkin masih aktif) TIDAK otomatis diperbaiki oleh plan ini — Task 8 Step 5 cuma menghitung skalanya secara informasional, perbaikan data lama (kalau diperlukan) adalah tindakan operasional TERPISAH yang perlu dikomunikasikan ke user, bukan bagian dari plan ini.
- Setelah semua task selesai, JANGAN merge/push branch — keputusan terpisah milik user.

## 7. Mulai dari mana

Task 1 (root cause, paling kritis) HARUS dikerjakan lebih dulu dari Task 5. Task 2 HARUS lebih dulu dari Task 3, 6, 7 (urutan berantai). Task 4 independen, bisa diselipkan kapan saja. Urutan yang disarankan (memenuhi semua dependency sekaligus): **Task 1 → Task 2 → Task 3 → Task 4 → Task 5 → Task 6 → Task 7 → Task 8**.

Mulai dari **Task 1** di `.agents/plans/2026-09-11-kenaikan-kelas-audit-perbaikan.md`.
