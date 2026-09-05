# Kickoff: Rombak Manual Book Akademik

**Base commit**: `b7c4dcfd` (`docs(akademik): plan rombak manual book akademik -- 14 task, seeder PAUD lengkap dgn kode`)
**Branch**: `akademik-v2` (tetap di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Manual book Akademik (`docs/manual-book/akademik/`) terakhir ditulis 30-31 Juli 2026. Sejak itu modul Akademik lewat banyak perubahan (RBAC v2, Ruang Orang Tua/Siswa dibuka, fix jalur penilaian PAUD, field Keterangan Jurnal KBM, peringatan kelengkapan nilai, fix nama Wali Kelas/Kepala Sekolah di PDF rapor) — tidak satu pun tercermin di manual book. Client bertanya "masing-masing role ini, fitur apa yang perlu diisi dan bagaimana urutannya?" — pertanyaan yang tidak terjawab struktur manual book yang ada (per-topik, bukan per-role).

Keputusan: rombak total — hapus 8 bab lama, tulis ulang dari nol, tambah 2 bab baru (Ruang Orang Tua, Ruang Siswa) dan 1 halaman indeks per-role.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-05-rombak-manual-book-akademik.md` — keputusan desain lengkap, termasuk alasan tiap keputusan.
2. `.agents/plans/2026-09-05-rombak-manual-book-akademik.md` — 14 task, termasuk kode lengkap seeder di Task 1.
3. `.agents/logs/2026-09-05-audit-alur-nilai-rapor-fix-elemen-cp-paud.md` — sumber konten untuk Bab 4 sub-bagian PAUD.
4. `.agents/logs/2026-09-05-kelengkapan-nilai-sebelum-pengajuan-rapor.md` — sumber konten untuk Bab 5 (peringatan kelengkapan nilai).
5. Kalau tersedia di memori sesi (bukan wajib kalau tidak ada): memory `project_manual_book_akademik`, `reference_manual_book_pintera`, `reference_documentation_hierarchy` — proses & konvensi manual book secara umum.

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Format 5-bagian bab topik tetap**: Judul → Untuk siapa → Prasyarat → Langkah-langkah (screenshot asli) → Kesalahan umum. Halaman `README.md` (indeks per-role) format BEDA (bukan 5-bagian) — lihat spec §4.
- **Definition of Done**: setiap fitur WAJIB dicoba langsung di browser sebelum ditulis, screenshot diambil dari hasil percobaan itu sendiri (bukan retroactive/dari kode). Satu-satunya pengecualian: sub-bagian "Sisi Guru PAUD" di Bab 4 boleh skip screenshot BARU (tetap wajib dicoba) — lihat spec §6.
- **Relasi seeder PAUD harus persis seperti kode di Task 1** — terutama: Guru dibuat via `Guru::factory(['user_id' => $user->id, ...])` (User dibuat DULU), bukan create-lalu-update; `KomponenPenilaian` untuk `elemen_cp` WAJIB `lembaga_id` eksplisit; `Asesmen` WAJIB `komponenPenilaian()->attach()` (bukan cuma dua baris terpisah di tabel berbeda tanpa pivot) — ini pola yang sudah 2x jadi sumber bug tersembunyi di sesi-sesi sebelumnya (KomponenPenilaian tidak ke-attach = tidak pernah dihitung "harus diisi" di manapun).
- **Republish Artifact ke URL yang SAMA**: `https://claude.ai/code/artifact/92e6b639-d846-48ad-9e42-2a270abc5e03` — parameter `url` WAJIB dipakai saat publish. Publish tanpa `url` akan bikin link baru — itu salah, jangan lakukan.
- **Manual book Keuangan (`docs/manual-book/keuangan/`) TIDAK disentuh sama sekali.**
- **Tidak pakai worktree, tidak pindah branch.**

## 4. Fakta Operasional

- **Dev server harus jalan** untuk screenshot Playwright (`php artisan serve` atau `composer run dev`) — set `MANUAL_BOOK_BASE_URL` env var sesuai.
- **`docs/**` gitignored** (commit `a7e3a517`) — `git status` TIDAK akan menampilkan perubahan file manual book sama sekali, itu MEMANG BEGITU. Jangan panik, jangan coba `git add -f`. Satu-satunya perubahan yang masuk git di seluruh plan ini adalah Task 1 (seeder) dan Task 14 (handoff log + `PETA_PENGEMBANGAN.md`).
- **Seed data sebelum Task 1 dijalankan**: cuma 1 Lembaga (SDIT PINTERA, SD), NOL Lembaga PAUD. Task 1 menambah 1 Lembaga TK.
- **Akun demo yang perlu dicari dulu** (jangan menebak email/password): admin lembaga/yayasan SD (di `EssentialUserSeeder.php`/`UserSeeder.php`), Orang Tua (di `OrangTuaKaryawanSeeder.php`), Siswa SD (email `siswa.sd@demo.test`, dikonfirmasi bisa login — cari passwordnya di seeder yang sama). Akun guru PAUD BARU dari Task 1: `guru.tk@demo.test` / `password`.
- **Script screenshot & build sudah ada, JANGAN dibuat ulang**: `scripts/manual-book-screenshots.mjs` (Playwright, `--bab=<id>`, pola fresh-browser-context-per-account-switch WAJIB dipertahankan kalau nambah skenario multi-akun), `scripts/manual-book-artifact/build.mjs`.

## 5. Instruksi Stop-and-Report

- **Kalau menemukan bug/perilaku aneh saat mencoba fitur** (Definition of Done): STOP task itu, jangan lanjut menulis prosa seolah normal. Laporkan detail temuan (langkah reproduksi, ekspektasi vs kenyataan) sebagai status BLOCKED. Preseden: manual book pertama dulu menemukan 3 bug produksi nyata lewat proses ini — bukan hal yang aneh kalau terulang.
- **Kalau urutan checklist per-role di Task 13 ternyata salah/tidak lengkap** dari yang didraft di spec §4: perbaiki, JANGAN diam-diam ikuti draft yang salah — catat di laporan task kenapa berbeda.
- **Kalau ragu soal cakupan** (mis. apakah suatu halaman masuk bab mana): tanyakan, jangan menebak sepihak — terutama untuk hal yang tidak disebutkan eksplisit di spec.

## 6. Catatan Serah Terima

- Seluruh keputusan desain (scope refresh total, seeder PAUD dibuat, tanpa worktree, README non-numbered, dll.) sudah lewat diskusi panjang dengan user — JANGAN direvisi ulang dari nol tanpa alasan kuat, itu akan mengulang kerja yang sudah selesai.
- User secara eksplisit menekankan **relasi seeder PAUD harus tepat** — ini bukan detail sepele, sudah 2x jadi sumber bug tersembunyi di audit-audit sebelumnya (lihat referensi log di §2).
- User juga eksplisit: **"semua feature harus dicoba semua"** — jangan menulis bab manapun dari asumsi/baca kode saja, walau tergoda karena lebih cepat.

## 7. Mulai dari mana

Mulai dari **Task 1** (seeder) di `.agents/plans/2026-09-05-rombak-manual-book-akademik.md` — kerjakan berurutan, Task 2 (hapus bab lama) sebelum mulai menulis bab manapun (Task 3-13), Task 13 (README) di akhir setelah semua bab ada (butuh link valid ke semuanya), Task 14 penutup.
