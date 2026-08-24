# Spec: Susutkan Seeder Demo ke 1 Lembaga (SD), Data Volume Realistis

- **Branch**: `rbac-v2`
- **Baseline commit**: `f59a131`
- **Tanggal**: 24 Agustus 2026
- **Konteks**: prasyarat sebelum eksekusi RBAC v2 (`.agents/specs/2026-08-24-rbac-v2-role-taxonomy.md`) — memperkecil dataset demo mempermudah audit `user_has_roles` di RBAC v2 §6.2. Juga menegakkan prinsip baru §5 skill `seeder-standard` ("Skala Data Demo — Minimal, Bukan Realistis" — 1 lembaga per seed run).

## 1. Context & Problem

`DatabaseSeeder.php` memanggil ~53 seeder yang saat ini membuat 4 lembaga (KB, TK, SD, SMP — `LembagaSeeder.php`). User ingin menyusutkan jadi **1 lembaga saja (SD, NPSN 20223333/SDITPTR)**, dengan syarat eksplisit: **data di dalam lembaga itu harus tetap "seperti data real"** — bukan sekadar 1-2 baris token per tabel.

Audit menyeluruh (24 Agustus 2026, baca penuh 17 file yang hardcode identitas lembaga + 10 file pendukung) menemukan masalah lebih besar dari sekadar "hapus 3 blok lembaga di `LembagaSeeder.php`":

1. **5 seeder akan CRASH TOTAL** kalau KB/TK/SMP dihapus — mereka pakai `Lembaga::where('npsn', X)->firstOrFail()` ke lembaga yang mau dihapus, exception akan menghentikan `DatabaseSeeder::run()` sebelum sempat sampai ke bagian SD sekalipun.
2. **3 seeder akan diam-diam menghasilkan NOL baris untuk SD** — pakai fallback `?? Lembaga::first()` (tidak crash) tapi ISI kodenya 100% hardcode identitas SMP/KB (nama guru, nama ruangan, email), sehingga guard-clause di dalamnya gagal cocok dan seluruh logic di-skip.
3. **`KeuanganDemoSeeder`** tidak mencakup orang tua SD sama sekali di daftar `$demoParents` — walau secara struktur generik, hasil nyatanya nol data Keuangan demo untuk SD.
4. **Volume data SD yang sudah ada jauh dari realistis**: `SiswaSeeder` cuma 3 siswa/kelas (18 siswa total 6 kelas), `GuruSeeder` cuma 3 guru literal, sedangkan SMP (yang selama ini jadi lembaga "utama" demo) dapat treatment detail jauh lebih kaya di hampir semua seeder (Asesmen, Jadwal, Nilai, Presensi, Sesi Pembelajaran, Pendampingan/Kasus).

Akar masalah: SMP selama ini jadi lembaga "kelinci percobaan" utama untuk skenario demo detail, sedangkan SD selalu dapat fallback generik/token. Penyusutan ke 1 lembaga bukan cuma soal *menghapus*, tapi **memindahkan kekayaan data dari SMP ke SD** sebelum SMP dihapus.

## 2. Keputusan Desain

1. **Lembaga yang dipertahankan: SD (SDIT PINTERA, NPSN 20223333, kode SDITPTR)** — sesuai contoh eksplisit user.
2. **"Data seperti data real" didefinisikan sebagai volume + variasi**, bukan cuma "tidak error". Target konkret (diturunkan dari pola yang SUDAH ADA di codebase, bukan angka karangan — lihat §4):
   - 12 kelas (2 rombel × 6 tingkat), bukan 6 kelas (1 rombel × 6 tingkat) yang ada sekarang.
   - ~28 siswa/kelas (≈336 siswa total), bukan 3 siswa/kelas (18 total).
   - ~14-15 guru (12 wali kelas + guru mapel spesialis: PJOK, Agama/BTA, B. Inggris), bukan 3 guru literal.
   - Variasi presensi (hadir/sakit/izin/alpa), bukan semua "hadir" flat.
   - Variasi nilai asesmen (skor bervariasi + catatan personal per siswa), bukan skor flat 85 semua.
   - Orang tua dengan pola selengkap SMP (4-6 record dengan akun login + 1 demo-login utama `ortu.sd@demo.test`), bukan cuma 1 record "lintas lembaga".
   - Kasus Pendampingan: replikasi 7 skenario yang tadinya ada di SMP (mencakup semua status siklus: diajukan→menunggu_consent→ditugaskan→berjalan→eskalasi→selesai), bukan cuma 1 kasus "ringan".
   - Sarpras & Kehadiran SDM: identitas ruang/aset SD sendiri (mis. "Ruang Kelas 1-A"), bukan sisa hardcode SMP ("Ruang Kelas VII-A").
3. **Zero-crash adalah syarat KERAS, bukan best-effort** — task terakhir plan WAJIB membuktikan `php artisan migrate:fresh --seed` selesai tanpa exception apapun (bukan cuma "kelihatannya jalan").
4. **6 seeder yang sudah generik (`Lembaga::all()` murni, tanpa hardcode NPSN) TIDAK disentuh**: `KelasSeeder`, `TahunAjaranSeeder`, `SemesterSeeder`, `MataPelajaranSeeder`, `GelombangPpdbSeeder`, `JalurPpdbSeeder`. Mereka otomatis menyusut mengikuti `LembagaSeeder` tanpa perlu diubah.
5. **`WorkflowDefinitionSeeder` TIDAK disentuh** — tidak menyentuh `Lembaga` sama sekali, murni definisi Role/Workflow global.
6. **Data tetap buang-pakai** (sesuai konfirmasi user + skill `seeder-standard` §5) — TIDAK ada migrasi data lama yang dipertahankan, cukup `migrate:fresh --seed` setelah seluruh seeder diperbaiki.
7. **3 lembaga lain (KB/TK/SMP) dan seluruh data turunannya DIHAPUS dari seeder**, bukan cuma "diabaikan" — `LembagaSeeder.php` hanya menyisakan 1 blok `firstOrCreate` untuk SD.

## 3. Cakupan File (26 file total)

### 3.1 Wajib direfactor total (5 file — CRASH tanpa perbaikan)

| File | Masalah | Perbaikan |
|---|---|---|
| `GuruSeeder.php` | 4× `Lembaga::where('npsn', X)->firstOrFail()` di awal method (KB/TK/SD/SMP) | Hapus 3 lookup KB/TK/SMP, sisakan lookup SD. Tambah guru: 12 wali kelas + 2-3 guru mapel spesialis (§4). |
| `UserSeeder.php` | `firstOrFail()` ke KB (baris ~19, untuk admin yayasan) lalu TK/SMP | Admin yayasan JANGAN bergantung ke lembaga spesifik manapun (dia scope yayasan, bukan lembaga) — refactor supaya tidak query `Lembaga` by NPSN sama sekali untuk akun yayasan-level. Sisakan user SD saja untuk akun lembaga-level. |
| `OrangTuaKaryawanSeeder.php` | 4× `firstOrFail()`; fungsi `seedOrangTuaLintasLembaga($sdit, $smpit)` butuh KEDUA lembaga ada | Hapus lookup KB/TK/SMP dan fungsi lintas-lembaga. Ganti dengan pola selengkap `seedOrangTua($smpit)` versi SMP (4-6 orang tua + akun login), diterapkan ke SD. Tambah demo-login utama `ortu.sd@demo.test`. |
| `GelombangJalurSeeder.php` | `Lembaga::where('npsn', '20223344')->firstOrFail()` (SMP) | Ganti NPSN target ke SD (20223333). |
| `PendampinganSeeder.php` | `firstOrFail()` ke SMP di awal method — exception SEBELUM mencapai logic SD | Restrukturisasi total: pindahkan seluruh 7 skenario kasus (bukan cuma versi "ringan") yang tadinya untuk SMP, terapkan ke SD. Hapus `buatSkenarioRinganLintasJenjang` (fungsi lintas-jenjang tidak relevan lagi). |

### 3.2 Wajib direfactor total (3 file — silently menghasilkan 0 baris untuk SD)

| File | Masalah | Perbaikan |
|---|---|---|
| `EssentialUserSeeder.php` | Default `Lembaga::where('npsn', '20223311')->first() ?? Lembaga::first()` (KB), 6 akun esensial berlabel email `*.kb@demo.test` | Ganti target ke SD, rename SEMUA email dari pola `*.kb@demo.test` → `*.sd@demo.test` (kepsek.sd, adm.sd, keuangan.sd, kurikulum.sd, guru.sd, sarpras.sd). |
| `KehadiranSdmDemoSeeder.php` | 100% hardcode identitas guru & admin SMP (nama, email) — guard-clause skip diam-diam kalau guru itu tak ada | Tulis ulang total memakai 3 guru SD hasil `GuruSeeder` (hendra/maya/taufik, atau guru baru hasil §3.1 refactor) dan admin `adm.sd@demo.test`. |
| `SarprasPengadaanDemoSeeder.php` | 100% hardcode nama gedung/ruang/aset SMP ("Ruang Kelas VII-A/B"), bisa bahkan auto-create Lembaga SMP baru kalau kosong | Tulis ulang total: identitas ruang bergaya SD ("Ruang Kelas 1-A" s.d. "Ruang Kelas 6-B" mengikuti 12 rombel §4), kapasitas siswa disesuaikan §4 (28/kelas). Hapus logic auto-create Lembaga SMP fallback. |

### 3.3 Perlu penambahan data SD (1 file — struktur generik, tapi sumber datanya hardcode token lain)

| File | Masalah | Perbaikan |
|---|---|---|
| `KeuanganDemoSeeder.php` | `$demoParents` array hardcode 3 NIK (`ortu@demo.test` SMP, `ortu.kb@demo.test`, `ortu.tk@demo.test`) — TIDAK ADA entri SD | Hapus 3 entri KB/TK/SMP, tambah entri SD (`ortu.sd@demo.test`, tautkan ke siswa SD hasil §3.1 `OrangTuaKaryawanSeeder` yang baru). |

### 3.4 Perlu penambahan volume data (7 file — tidak crash, tapi kualitas/volume SD di bawah standar §2.2 dibanding treatment SMP)

| File | Kondisi SD saat ini | Target |
|---|---|---|
| `AsesmenSeeder.php` | Fallback generik: 1 asesmen/kelas, guru & mapel pertama yang ditemukan (6 baris total) | Terapkan pola detail yang tadinya khusus SMP (`seedSmpAsesmen`) ke SD: asesmen per-mapel dengan guru pengampu yang sesuai, bukan guru/mapel pertama yang ditemukan. |
| `JadwalPelajaranSeeder.php` | Fallback generik: 1 guru & 1 mapel yang sama dipakai berulang di semua slot jadwal | Terapkan pola per-mapel-per-guru yang tadinya khusus SMP, disesuaikan 9 mapel SD (§2.2 tidak perlu ubah `MataPelajaranSeeder`). |
| `KomponenPenilaianSeeder.php` | 9 baris (1 komponen/mapel) — sebenarnya sudah proporsional terhadap 9 mapel SD | **Tidak perlu diubah**, cukup diverifikasi tetap jalan setelah refactor lembaga lain. |
| `NilaiSiswaSeeder.php` | Skor flat 85 semua, catatan template sama | Variasikan skor (rentang realistis, bukan konstanta), catatan personal per siswa mengikuti pola yang tadinya khusus SMP. |
| `PresensiSeeder.php` | Semua status "hadir", tidak ada variasi | Tambahkan variasi (sakit/izin/alpa) mengikuti pola yang tadinya khusus SMP (npsn 20223344 + nis tertentu), diterapkan ke NIS siswa SD baru (§4 volume 336 siswa). |
| `SesiPembelajaranSeeder.php` | Materi generik "Kegiatan Belajar Mengajar Rutin dan Interaktif" semua sesi | Materi spesifik per-mapel mengikuti pola yang tadinya khusus SMP. |
| `AkunPendaftarSeeder.php` | 1 akun pendaftar SD sudah ada (`pendaftar.sd@demo.test`) + 3 baris array sampah untuk KB/TK/SMP | Hapus 3 baris array KB/TK/SMP dari `$emailPerNpsn`, sisakan SD. Volume 1 akun sudah cukup (fitur PPDB tidak butuh banyak pendaftar demo). |

### 3.5 Tidak disentuh (9 file — generik penuh atau tidak menyentuh Lembaga sama sekali)

`KelasSeeder.php`, `TahunAjaranSeeder.php`, `SemesterSeeder.php`, `MataPelajaranSeeder.php`, `GelombangPpdbSeeder.php`, `JalurPpdbSeeder.php`, `PendaftaranSeeder.php`, `CalonMuridSeeder.php`, `WorkflowDefinitionSeeder.php`.

### 3.6 File inti (1 file — sumber lembaga)

`LembagaSeeder.php` — hapus 3 blok `firstOrCreate` (KB/TK/SMP), sisakan 1 blok SD. **Ini WAJIB dikerjakan PERTAMA** sebelum file lain, karena file lain bergantung pada lembaga apa yang tersedia untuk mendeteksi crash sejak dini.

## 4. Volume Data Target (Ringkasan Angka)

| Entitas | Sebelum (SD saja) | Sesudah |
|---|---|---|
| Lembaga | 4 (KB, TK, SD, SMP) | 1 (SD) |
| Kelas/rombel | 6 (1 rombel × 6 tingkat) | 12 (2 rombel × 6 tingkat, meniru pola SMP) |
| Siswa | 18 (3/kelas) | ~336 (28/kelas × 12 kelas) |
| Guru | 3 (literal token) | ~14-15 (12 wali kelas + 2-3 guru mapel spesialis: PJOK, Agama/BTA, B. Inggris) |
| Orang tua dengan akun login | 1 (record lintas-lembaga, akan dihapus) | 4-6 (pola selengkap eks-SMP) + 1 demo-login utama |
| Kasus Pendampingan | 1 (skenario "ringan") | 7 (replikasi skenario eks-SMP, cakup semua status siklus) |
| Mata pelajaran | 9 (SUDAH realistis, tidak berubah) | 9 |

## 5. Definisi Selesai

1. `php artisan migrate:fresh --seed` selesai tanpa exception — dijalankan dan dibuktikan hasilnya (bukan diasumsikan).
2. `Lembaga::count()` = 1, `Lembaga::first()->kode_lembaga` = `SDITPTR`.
3. Tidak ada sisa string literal `20223311`/`20223322`/`20223344`/`KBITPTR`/`TKITPTR`/`SMPITPTR` di `database/seeders/*.php` (grep verifikasi, kecuali kalau ada alasan sah dicatat eksplisit).
4. Volume data sesuai target §4 (Siswa::count(), Guru::count(), Kelas::count(), dll diverifikasi lewat tinker/test).
5. Tidak ada email demo yang salah label (`*.kb@demo.test`/`*.smp@demo.test` tersisa untuk data yang sebenarnya milik SD).
6. Full suite `php artisan test` tetap hijau (izin user dulu) — perubahan ini murni seeder demo, TIDAK mengubah kode aplikasi, jadi risiko regresi ke test existing rendah, tapi tetap WAJIB diverifikasi (test yang kebetulan bergantung ke `DatabaseSeeder` — kalau ada — perlu diperiksa khusus).
7. Skill `seeder-standard` §5 sudah menjadi acuan tertulis (sudah diupdate sebelum spec ini).

## 6. Non-Goals

1. **TIDAK mengubah `RoleSeeder.php`/`RolePermissionAssignmentSeeder.php`** — itu scope RBAC v2 terpisah (`.agents/specs/2026-08-24-rbac-v2-role-taxonomy.md`), dikerjakan SETELAH spec ini.
2. **TIDAK menambah lembaga baru selain SD** — kalau nanti ada kebutuhan demo multi-lembaga (dashboard rekap yayasan, mutasi siswa), itu keputusan terpisah, bukan bagian dari penyusutan ini.
3. **TIDAK mengubah struktur tabel/migration** — murni perubahan isi seeder (data), bukan skema database.
4. **TIDAK memperbaiki bug aplikasi yang mungkin ditemukan selama proses** (kalau ada) — dicatat sebagai temuan terpisah untuk dilaporkan ke user, bukan diperbaiki diam-diam di tengah kerja seeder.
