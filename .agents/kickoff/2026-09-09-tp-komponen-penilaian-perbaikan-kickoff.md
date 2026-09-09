# Kickoff: Perbaikan TP (Komponen Penilaian)

**Base commit**: `314fb843` (`docs(komponen-penilaian): implementation plan perbaikan TP (4 task)`)
**Branch**: `akademik-v2` (SETARA `rbac-v2`, tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit mendalam menu TP (Tujuan Pembelajaran, dalam modul Komponen Penilaian) menemukan **1 bug kritis** dan 3 item minor. Topik ini SENSITIF (menyangkut integritas data nilai siswa) — audit dilakukan 2x atas permintaan eksplisit user, dan kesimpulan berubah signifikan di putaran kedua.

**Kronologi temuan Item A (WAJIB dipahami sebelum mulai kerja)**:
1. Audit pertama: dibuktikan empiris (test HTTP sementara) bahwa Edit TP sisi Admin SELALU gagal validasi untuk TP yang belum dipakai (`!$dipakai`) — kesimpulan awal: "view kehilangan field, harus dikembalikan".
2. User minta audit ulang ("sangat sensitif"). Perbandingan dengan jalur Guru (`Guru\KomponenPenilaianController`, terpisah total dari Admin) membuktikan desain "Subjek/Semester dikunci permanen, tidak bisa diubah" SUDAH BENAR DAN DISENGAJA di sisi Guru (lengkap dengan wording penjelasan) — kesimpulan berubah: "bukan field yang hilang, tapi validasi Admin yang belum diselaraskan".
3. User konfirmasi **Opsi A**: selaraskan Admin dengan Guru (subjek/semester terkunci permanen di kedua sisi), BUKAN kembalikan kemampuan reassignment.
4. Saat menyisir test file sebelum menulis plan (bagian dari audit ulang), ditemukan 2 test LAGI (bukan cuma 1 yang ditemukan pertama kali) yang menguji kemampuan reassignment yang sama — termasuk skenario "yayasan actor moves TP across lembaga" yang sempat dikira bukti UI terpisah, TERNYATA JUGA tidak pernah punya jalur UI nyata (dikonfirmasi ulang, `edit.blade.php` tidak punya percabangan yayasan-scope sama sekali).

**Item B-D**: badge scope (konsisten dengan semua menu lain di rangkaian audit ini), hardcode daftar bentuk pendidikan PAUD yang seharusnya pakai enum `BentukPendidikan::isPaud()`, dan konsistensi kecil default nilai "bobot".

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-09-tp-komponen-penilaian-perbaikan.md` — spec lengkap 4 item. BACA BAIK-BAIK bagian "Ringkasan" dan "Keputusan Bisnis yang Ditegakkan" di Item A — ini fondasi kenapa fix-nya seperti ini, bukan sebaliknya.
2. `.agents/plans/2026-09-09-tp-komponen-penilaian-perbaikan.md` — 5 task TDD, kode lengkap tiap step, sudah self-review.

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Keputusan bisnis Opsi A SUDAH FINAL, dikonfirmasi eksplisit oleh user** — "Subjek Penilaian dan Semester TIDAK BISA diubah setelah TP dibuat, SELAMANYA, baik sudah dipakai di asesmen/nilai ATAUPUN BELUM." Satu-satunya cara ganti Subjek/Semester adalah hapus lalu buat TP baru. JANGAN mengembalikan kemampuan reassignment dalam bentuk apa pun (termasuk kalau menemukan test lama yang "kelihatannya" menguji kemampuan itu — itu JUSTRU yang harus diubah, bukan dijadikan alasan mengembalikan fitur).
- **Field yang TETAP bisa diedit kapan saja** (bahkan setelah `$dipakai`): `kode`, `deskripsi`, `bobot` (dengan guard 100%), `kktp`, `kktp_minimal`. Field `assessment_type` HANYA bisa diedit selama `!$dipakai`.
- **Jalur Guru (`Guru\KomponenPenilaianController`, `UpdateKomponenPenilaianSendiriRequest`, `portals/guru/...`) TIDAK DISENTUH SAMA SEKALI** — ini SUDAH BENAR, jadi rujukan/pembanding, bukan target perubahan. Task 1 Step 12 menjalankan regresinya justru untuk MEMBUKTIKAN tidak ada efek samping, bukan untuk mengubahnya.
- **Task 1 WAJIB mengubah 3 test existing** (baris ±258, ±592, ±632 di `KomponenPenilaianCrudTest.php`) — SEMUANYA menguji kemampuan reassignment yang sengaja dihapus. Test baris ±674 ("does not touch lembaga_id...") TIDAK diubah. Kalau saat mengerjakan ternyata nomor baris berbeda, WAJIB dicari ulang berdasarkan NAMA test (dikutip lengkap di plan), bukan diasumsikan dari nomor baris semata.
- **Backend inti (`lockForUpdate()`, guard bobot 100%, proteksi hapus/ubah TP yang sudah dipakai, cross-tenant check Store) TIDAK diubah** — Task 1 HANYA menghapus blok reassignment subjek/semester, TIDAK menyentuh logic lain di `UpdateKomponenPenilaianAction`.
- **Task 2 SENGAJA HANYA memanggil `scopeHeaderData()` di cabang halaman penuh `index()`, BUKAN di cabang ajax** — beda dari pola RPP/Jadwal Pelajaran (di sana perlu di kedua cabang karena ada item lain yang butuh badge data di dalam partial ajax; di sini TIDAK ADA kebutuhan itu, dicek langsung `_daftar.blade.php` Komponen Penilaian tidak menyebut `isYayasan`/`activeLembaga` sama sekali). JANGAN "menyamakan" dengan pola RPP tanpa alasan — itu sudah pernah jadi over-engineering di draf spec ini sebelum dikoreksi.
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **Test home**: SEMUA test baru masuk ke `tests/Feature/Admin/KomponenPenilaianCrudTest.php`. Helper existing: `actingAsKomponenManager(Lembaga $lembaga)` (lembaga-scope) dan `actingAsYayasanKomponenManager(Yayasan $yayasan)` (yayasan-scope) — JANGAN bikin helper baru.
- **`UpdateKomponenPenilaianData` constructor berubah dari 9 jadi 6 parameter** — SUDAH dicek TIDAK ADA pemanggilan `new UpdateKomponenPenilaianData(...)` di luar `fromArray()` di seluruh codebase (Task 1 Step 5), aman untuk diubah.
- **`komponenPenilaianEditForm()` (JS) TETAP ADA sebagai fungsi, JANGAN dihapus totalnya** — `edit.blade.php` masih memanggilnya lewat `x-data="komponenPenilaianEditForm()"`, cuma ISINYA yang dikosongkan (Task 1 Step 10).
- **MySQL deadlock risk**: cek proses PHP lain (`Get-CimInstance Win32_Process -Filter "Name='php.exe'"` PowerShell) sebelum full suite di Task 5.

## 5. Instruksi Stop-and-Report

- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode (cari via nama test/method, BUKAN nomor baris), catat perbedaannya di laporan task.
- **Kalau Task 1 Step 11 atau Step 12 (regresi) menunjukkan KEGAGALAN APA PUN** — STOP TOTAL, laporkan detail ke user SEBELUM melanjutkan (topik sensitif, data nilai siswa — jangan lanjut kalau ada tanda-tanda regresi tak terduga).
- **Kalau saat mengerjakan Task 1 menemukan test/kode LAIN (di luar 4 yang sudah diidentifikasi: 258, 592, 632, 674) yang JUGA menguji kemampuan reassignment subjek/semester** — STOP, laporkan ke user dulu sebelum mengubahnya sendiri (spec sudah 2x disisir tapi kemungkinan ada yang terlewat tetap harus dilaporkan, bukan diputuskan sendiri, mengingat sensitivitas topik).
- **Kalau Task 3 Step 2 (baseline test SEBELUM refactor) GAGAL** — STOP TOTAL, laporkan ke user (berarti pemahaman kode saat ini salah).

## 6. Catatan Serah Terima

- Spec ini SUDAH melalui 2 putaran audit-ulang eksplisit atas permintaan user karena topik sensitif — 1 kesimpulan besar terbalik total (dari "kembalikan field" jadi "selaraskan validasi ke desain yang sudah benar"), dan cakupan Task 1 diperluas dari 1 test jadi 3 test SETELAH plan mulai ditulis (ditemukan lewat penyisiran menyeluruh). Plan yang ada SEKARANG sudah mengandung versi final yang benar — TIDAK perlu dicurigai ulang dari nol, tapi TETAP ikuti instruksi Stop-and-Report kalau menemukan sesuatu yang tidak cocok dengan kode aktual.
- Perlakukan Task 1 dengan kehati-hatian ekstra dibanding task-task lain di rangkaian audit sesi ini — ini satu-satunya spec yang secara eksplisit diminta user untuk diaudit ulang karena sensitivitasnya (data nilai siswa).
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (lihat Task 5 Step 4) — kalau user memang menghendaki di sesi ini, itu permintaan tambahan terpisah.
- Setelah Task 5 selesai, JANGAN merge branch `akademik-v2` ke branch manapun — keputusan terpisah milik user.

## 7. Mulai dari mana

Mulai dari **Task 1** (selaraskan validasi Edit Admin — kritis, sensitif) di `.agents/plans/2026-09-09-tp-komponen-penilaian-perbaikan.md`, kerjakan berurutan Task 1 → 5. Task 2-4 boleh ditukar urutan kalau perlu (independen satu sama lain), TAPI Task 1 harus paling awal dan diselesaikan dengan hati-hati penuh sebelum lanjut ke task lain.
