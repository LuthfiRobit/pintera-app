# Spec: Rombak Manual Book Akademik

**Tanggal**: 2026-09-05
**Branch**: `akademik-v2`
**Base commit**: `221e70ee`
**Sifat**: Rombak total dokumentasi user-facing (bukan developer docs) untuk modul Akademik — dieksekusi via kickoff terpisah untuk agent lain, bukan di sesi ini.

---

## 1. Latar Belakang

Manual book Akademik existing (`docs/manual-book/akademik/`, 8 file: Bab 0 Setup Lembaga s.d. Bab 6 Kenaikan Kelas + Lampiran Lintas Lembaga) terakhir ditulis **30-31 Juli 2026**. Sejak itu modul Akademik sudah melalui banyak perubahan yang tidak tercermin sama sekali di dalamnya:

- RBAC v2 dan berkali-kali putaran audit keamanan (IDOR, `TenantScope`, session-staleness lintas-yayasan).
- **Ruang Orang Tua Akademik** (Nilai Anak, Jadwal Anak, Riwayat Izin/Sakit Anak) dan **Ruang Siswa Akademik** (Nilai & Rapor, Jadwal Pelajaran, Presensi Saya) — dua portal self-service yang baru dibuka dari placeholder tersembunyi, sama sekali belum ada babnya.
- Fix jalur penilaian PAUD (`elemen_cp`) — sebelumnya guru PAUD tidak bisa mengakses form Asesmen/Komponen Penilaian sama sekali (dropdown kelas/semester selalu kosong).
- Field **Keterangan** baru di Jurnal KBM guru (alasan izin/sakit siswa).
- **Peringatan kelengkapan nilai** dua lapis (wali kelas saat mengajukan rapor, Waka Kurikulum saat verifikasi).
- Bug fix nama Wali Kelas/Kepala Sekolah yang sebelumnya salah tercetak di PDF rapor.

Pemicu langsung: pertanyaan client "masing-masing role ini, fitur apa saja yang perlu diisi dan bagaimana urutannya?" — pertanyaan yang tidak terjawab oleh struktur manual book yang ada (disusun per-topik, bukan per-role).

## 2. Tujuan

1. Manual book Akademik mencerminkan 100% fitur yang benar-benar ada di aplikasi hari ini, dibuktikan lewat mencoba tiap fitur secara langsung (bukan ditulis dari membaca kode).
2. Ada satu halaman pembuka yang menjawab langsung pertanyaan client: per role, fitur apa yang harus diisi dan urutannya.
3. Proses & alat regenerasi (screenshot Playwright, build Artifact) tetap dipakai ulang, tidak dibangun ulang dari nol.

## 3. Cakupan

### 3.1 Dihapus lalu ditulis ulang total

Seluruh isi `docs/manual-book/akademik/*.md` (8 file lama) **dihapus lebih dulu**, baru ditulis ulang dari nol — bukan ditimpa/diedit satu-satu. Ini keputusan eksplisit user: dengan drift sebesar ini, menulis ulang lebih aman daripada mencoba mendiff isi lama yang sudah tidak relevan.

### 3.2 Struktur akhir folder `docs/manual-book/akademik/`

| File | Sifat | Catatan |
|---|---|---|
| `README.md` | 🆕 baru | Peta Alur Kerja per Role — lihat §4 |
| `00-setup-lembaga.md` | ditulis ulang | |
| `01-data-master.md` | ditulis ulang | |
| `02-penjadwalan.md` | ditulis ulang | |
| `03-presensi-jurnal.md` | ditulis ulang | + field Keterangan izin/sakit |
| `04-asesmen-nilai.md` | ditulis ulang | + sub-bagian baru "Sisi Guru PAUD" |
| `05-rekap-rapor.md` | ditulis ulang | + efek fix nama Wali Kelas/Kepsek di PDF, + peringatan kelengkapan nilai (wali kelas & Waka) |
| `06-kenaikan-kelas.md` | ditulis ulang | |
| `lampiran-lintas-lembaga.md` | ditulis ulang | |
| `07-ruang-orang-tua.md` | 🆕 baru | Nilai Anak, Jadwal Anak, Riwayat Izin/Sakit Anak |
| `08-ruang-siswa.md` | 🆕 baru | Nilai & Rapor, Jadwal Pelajaran, Presensi Saya |

Nomor bab existing (0-6) **tidak digeser** — bab baru (Ruang Orang Tua/Siswa) ditambahkan sebagai 07 dan 08 di ekor urutan, karena keduanya portal terpisah yang tidak punya ketergantungan prasyarat searah dengan bab-bab admin/guru sebelumnya (mereka baca data yang sudah dihasilkan oleh alur Bab 0-6).

### 3.3 Format tiap bab topik (00-08 + Lampiran) — TIDAK BERUBAH dari pola existing

Lihat `docs/manual-book/akademik/04-asesmen-nilai.md` versi lama sebagai referensi gaya bahasa & tingkat detail sebelum dihapus (baca dulu isinya, catat gaya penulisannya, baru hapus filenya). Struktur wajib 5 bagian, urutan tetap:

1. `# Bab N — <Nama Topik>`
2. **Untuk siapa** — role relevan + pembagian wewenang antar role (kalau lebih dari satu)
3. **Prasyarat** — link ke bab lain yang harus beres duluan, dengan alasan singkat
4. **Langkah-langkah** — instruksi bertahap, dipecah per sub-skenario/role kalau perlu, tiap langkah diikuti **screenshot asli dari aplikasi yang jalan** (Playwright, bukan mockup) — KECUALI sub-bagian "Sisi Guru PAUD" di Bab 4 (lihat §5)
5. **Kesalahan umum** — format gejala → penyebab → cara benerin, diangkat dari kebingungan realistis (bukan FAQ generik)

### 3.4 Definition of Done — WAJIB per bab, bukan opsional

**Setiap fitur yang didokumentasikan harus benar-benar dicoba/diklik langsung di aplikasi yang jalan sebelum prosanya ditulis** — bukan ditulis dari membaca kode controller/view saja. Ini permintaan eksplisit user ("semua feature harus dicoba semua"). Kalau saat mencoba ditemukan perilaku yang tidak sesuai dugaan (termasuk bug), STOP, laporkan ke pengguna (lewat mekanisme stop-and-report task-reviewer/controller sesi, bukan didiamkan), baru lanjut menulis setelah ada arahan. Preseden nyata: manual book pertama dulu menemukan 3 bug produksi justru lewat proses ini (lihat `[[project_manual_book_akademik]]` di memori sesi — permission `admin_akademik` kosong, filter tahun ajaran Kenaikan Kelas, akun demo guru tidak lengkap).

Bab dianggap selesai HANYA kalau:
- Setiap langkah di "Langkah-langkah" sudah dicoba sendiri via browser (bukan diasumsikan dari baca kode) dan screenshot diambil dari hasil percobaan itu sendiri.
- Setiap item di "Kesalahan umum" sudah diverifikasi benar-benar terjadi (direproduksi), bukan ditebak.

## 4. Halaman Indeks — `README.md`

Bukan bab bergaya 5-bagian di atas — format tersendiri, lebih ringkas:

1. Judul (`# Manual Book Akademik — Peta Alur Kerja per Role`)
2. Satu paragraf cara pakai halaman ini
3. **Kelompok "Role yang Mengisi Data"** — tiap role dapat checklist bernomor (urutan pengerjaan), tiap nomor = `[Nama fitur](bab.md#bagian)`:
   - Admin Lembaga/Operator Akademik: Tahun Ajaran & Semester → Kurikulum Assignment → Kelas & Pola Jam → Jadwal Pelajaran → Kalender Akademik
   - Guru: Komponen Penilaian (TP) untuk mapel yang diajar → RPP → Jurnal KBM/Presensi harian → Asesmen & Input Nilai
   - Wali Kelas (guru dengan kelas binaan, melanjutkan checklist Guru di atas): Catatan Wali Kelas per siswa → Ajukan Rapor
   - Waka Kurikulum: Verifikasi Rapor yang diajukan
   - Kepala Sekolah: Persetujuan Akhir Rapor
4. **Kelompok "Role Self-Service (Lihat Saja)"** — bukan checklist urutan, daftar "cek di mana":
   - Orang Tua: Nilai Anak, Jadwal Anak, Riwayat Izin/Sakit Anak
   - Siswa: Nilai & Rapor, Jadwal Pelajaran, Presensi Saya

**Urutan checklist di atas WAJIB diverifikasi ulang ke kode/UI aktual saat menulis** (jangan disalin mentah dari daftar ini) — daftar ini hasil diskusi awal, bukan hasil audit kode final. Kalau ternyata ada urutan yang salah/kurang lengkap, perbaiki dan catat di handoff log kenapa berbeda dari draft spec ini.

## 5. Seeder Baru — Data Demo PAUD

**File baru**: `database/seeders/LembagaPaudDemoSeeder.php`, didaftarkan di `DatabaseSeeder.php` (setelah seeder-seeder Akademik existing, sebelum seeder yang bergantung ke `Lembaga::all()` secara global kalau ada — cek urutan existing di `DatabaseSeeder.php` dulu supaya tidak pecah seeder yang sudah ada).

**Alasan**: seed data `akademik-v2` saat ini cuma punya 1 Lembaga (SDIT PINTERA, SD) — nol Lembaga PAUD. Tanpa ini, sub-bagian "Sisi Guru PAUD" di Bab 4 tidak bisa dicoba/discreenshot sama sekali (melanggar §3.4).

**Isi, urutan, dan relasi yang WAJIB tepat** (user eksplisit menekankan ini):

1. **Lembaga**: `bentuk_pendidikan => 'TK'`, `yayasan_id` = Yayasan yang sudah ada (satu-satunya saat ini — pakai `Yayasan::first()` atau sejenis, JANGAN bikin Yayasan baru), `nama_kepala_sekolah` **wajib diisi** (field ini yang dipakai `RaporPdfDataBuilder` untuk cetak PDF sejak fix hari ini).
2. **TahunAjaran**: `lembaga_id` ke Lembaga TK, `status_aktif => true`.
3. **Semester**: `tahun_ajaran_id` ke TahunAjaran di atas, `status_aktif => true`.
4. **Guru** + **User** (login): buat User dulu (`role: guru`), baru `Guru::factory()->create(['user_id' => $user->id, 'lembaga_id' => ...])` — **ikuti pola factory yang benar** (`user_id` sebagai override dibaca di closure `person_id` factory, BUKAN `Guru::create()` lalu `->update(['user_id' => ...])` yang silon-op karena `user_id` bukan kolom asli di tabel `guru`, link sebenarnya lewat `person_id → Person.user_id`). Simpan kredensial (email/password) di komentar kode untuk dipakai `scripts/manual-book-screenshots.mjs`.
5. **Kelas**: `lembaga_id` ke Lembaga TK, `tahun_ajaran_id` ke TahunAjaran, **`wali_kelas_guru_id` wajib diisi** ke Guru di atas, **`pola_jam_id` wajib diisi** (buat/pakai `PolaJam` — tanpa ini `SesiTematikGenerator` tidak akan pernah generate sesi/jadwal untuk kelas PAUD, sudah terbukti jadi prasyarat teknis dari audit hari ini).
6. **Siswa**: beberapa (minimal 2-3), `kelas_id` ke Kelas di atas, `lembaga_id` konsisten.
7. **KomponenPenilaian**: `subjek_type => 'elemen_cp'`, `subjek_id` ke `ElemenCp` yang **sudah ada secara global** (JANGAN bikin `ElemenCp` baru — cek dulu `ElemenCp::all()`, ambil beberapa yang sudah ada), `semester_id` ke Semester di atas, `lembaga_id` **wajib diisi eksplisit** dari `Semester->lembaga_id` (booted hook `KomponenPenilaian` TIDAK auto-isi `lembaga_id` untuk `elemen_cp`, cuma untuk `mata_pelajaran` — lihat `CreateKomponenPenilaianAction` sebagai referensi pola yang benar).
8. **Asesmen**: `kelas_id` ke Kelas, `subjek_type => 'elemen_cp'`, `subjek_id` sama dengan KomponenPenilaian di atas, `semester_id` sama, `guru_id` ke Guru di atas, `jenis` salah satu dari `JenisAsesmen::masukRapor()` (mis. `SumatifLingkupMateri`) supaya muncul di rekap rapor.
9. **Attach komponen ke asesmen lewat pivot** — `$asesmen->komponenPenilaian()->attach($komponen->id)` **WAJIB**, bukan cuma exists di tabel terpisah. Ini persis bug yang ditemukan & diperbaiki di 2 test lama sesi ini (`DashboardStatsServiceAssessmentTypeTest.php`) — komponen yang tidak di-attach ke asesmen manapun tidak pernah dihitung sebagai "harus diisi" di manapun (dashboard maupun rapor), jadi kalau seeder ini melewatkan attach, datanya akan terlihat "kosong tidak wajar" bukan "belum lengkap secara nyata".
10. **NilaiSiswa**: isi lengkap (`catatan` terisi, karena `assessment_type` komponen `elemen_cp` defaultnya `narrative` per `CreateKomponenPenilaianAction` kalau tidak diisi eksplisit) untuk SEMUA siswa KECUALI **satu siswa sengaja dibiarkan kosong** (`catatan => null` atau tidak dibuatkan baris sama sekali) — supaya Bab 5 (peringatan kelengkapan nilai) juga punya skenario nyata untuk discreenshot, bukan cuma teori.

**Tidak perlu**: akun Orang Tua/Siswa versi PAUD terpisah (Bab 07/08 pakai akun demo SD yang sudah ada — 5 pasang Orang Tua↔Siswa sudah tersedia dan terverifikasi bisa login), tidak perlu menyentuh `RoleSeeder`/`PermissionSeeder` (akses Ruang Orang Tua/Siswa tidak pakai permission Spatie sama sekali, murni gate identitas — sudah diverifikasi langsung ke kode hari ini).

## 6. Sub-bagian "Sisi Guru PAUD" di Bab 4 — Pengecualian Screenshot

Karena keterbatasan waktu (keputusan eksplisit user demi kecepatan), sub-bagian ini **boleh ditulis tanpa screenshot baru** — cukup teks penjelasan + referensi visual ke screenshot "Sisi Guru" (mata pelajaran) yang sudah ada di bab yang sama, dengan penjelasan eksplisit bagian mana yang beda (dropdown Elemen CP menggantikan dropdown Mata Pelajaran, field nilai jadi teks naratif bukan angka). **Ini SATU-SATUNYA pengecualian terhadap aturan §3.4** (semua fitur lain tetap wajib dicoba+screenshot asli) — kecuali seeder di §5 sudah jadi dan agen pelaksana punya waktu lebih, dalam hal ini screenshot asli tetap lebih diutamakan daripada pengecualian ini.

## 7. Proses Eksekusi

- **Tidak pakai worktree** — langsung di branch kerja saat ini (`akademik-v2`). Alasan: `docs/**` gitignored (tidak akan pernah masuk diff/commit apa pun, jadi tidak berisiko campur dengan kerja kode lain di branch ini), dan satu-satunya perubahan kode nyata (seeder PHP) berisiko rendah (1 file baru + 1 baris registrasi).
- Eksekusi via `superpowers:subagent-driven-development`, mirror preseden pembangunan manual book original (spec `docs/superpowers/specs/2026-07-30-manual-book-akademik-design.md` — arsip lama, sudah gitignored, cukup jadi referensi gaya kalau masih ada di disk, jangan dicari kalau sudah tidak ada, tidak fatal).
- Alat yang di-reuse, TIDAK dibangun ulang: `scripts/manual-book-screenshots.mjs` (Playwright, `--bab=<id>`, **pola fresh-browser-context-per-account-switch wajib dipertahankan** kalau butuh ganti akun dalam satu run — reuse 1 context lintas akun bikin `login()` hang), `scripts/manual-book-artifact/build.mjs` (assemble ke HTML self-contained, output `dist/` gitignored).
- **Republish ke Artifact URL yang SAMA**: `https://claude.ai/code/artifact/92e6b639-d846-48ad-9e42-2a270abc5e03` (parameter `url` saat publish, JANGAN bikin link baru).
- Commit hanya untuk perubahan kode (seeder + registrasi `DatabaseSeeder.php`) — commit terpisah dari kerja `docs/` yang gitignored (tidak akan ter-commit sama sekali, itu wajar).
- Tutup dengan handoff log di `.agents/logs/` (pola sama seperti seluruh sesi ini) dan update `PETA_PENGEMBANGAN.md`.

## 8. Di Luar Cakupan

- Manual book Keuangan (`docs/manual-book/keuangan/`) — modul terpisah, tidak disentuh. Kalau ada singgungan lintas-modul (mis. Ruang Orang Tua ada tab Tagihan), cukup cross-reference link ke bab Keuangan yang relevan, JANGAN duplikasi isinya.
- Akun/lembaga PAUD lengkap (Waka/Kepsek/Ortu/Siswa versi PAUD) — cukup 1 Guru wali kelas PAUD (lihat §5), bab lain pakai akun SD existing.
- Terjemahan bahasa Inggris atau format video — tidak diminta.

## 9. Referensi

- Bab existing (baca dulu sebelum hapus, untuk referensi gaya): `docs/manual-book/akademik/*.md`
- Contoh format 5-bagian yang sudah terbukti: `docs/manual-book/akademik/04-asesmen-nilai.md` (versi lama)
- Handoff log audit yang jadi sumber konten baru: `.agents/logs/2026-09-05-audit-alur-nilai-rapor-fix-elemen-cp-paud.md`, `.agents/logs/2026-09-05-kelengkapan-nilai-sebelum-pengajuan-rapor.md`
- Memori proses (sesi lain, harus dibaca agen pelaksana kalau tersedia): `project_manual_book_akademik`, `reference_manual_book_pintera`, `reference_documentation_hierarchy`
