# Design Doc — M2: SPMB Portal Publik
**Tanggal:** 14 Juli 2026
**Status:** Disetujui untuk breakdown ke implementation plan
**Referensi:** `PRD_Sistem_Administrasi_Yayasan.md` (bagian 5, 10.1, 11), `docs/superpowers/specs/2026-07-13-spmb-m1-konfigurasi-design.md` (M1, sudah selesai)

---

## 1. Latar Belakang & Tujuan

M1 (SPMB Konfigurasi) membangun sisi admin: Gelombang PPDB, Jalur PPDB, Formulir Field (dinamis per jalur), Dokumen Syarat, Seleksi, dan Jenis Tes master. Modul itu murni konfigurasi — belum ada satu pun jalur untuk calon murid benar-benar mendaftar.

M2 membangun sisi publik: form pendaftaran calon murid, upload dokumen, dan cek status — sesuai PRD bagian 11 ("Form pendaftaran calon murid, upload dokumen, cek status (tanpa login penuh / pakai token akses)").

### Termasuk
- Halaman publik per lembaga (`/spmb/{lembaga:slug}`) menampilkan jalur PPDB aktif dan gelombang yang sedang buka.
- Wizard multi-step pendaftaran: pilih jalur → verifikasi email (OTP) → data diri lengkap (Dapodik-style, tabel relasional) → formulir tambahan dinamis per jalur → upload dokumen sesuai jalur → review & submit.
- Kode pendaftaran + email sebagai mekanisme "cek status" — tanpa akun/password.
- Reuse data calon murid lintas gelombang via pencocokan NIK + email pendaftaran sebelumnya.
- Skema data relasional untuk data calon murid (bukan satu tabel datar), dirancang agar bisa jadi data siswa aktif tanpa migrasi besar saat M3 menyusul.

### Tidak termasuk (menyusul di modul lain)
- Verifikasi dokumen & keputusan diterima/ditolak — **M3**.
- Generate tagihan pendaftaran & alur pembayaran — **M4-M5**. Status pendaftaran di M2 berhenti di `menunggu_verifikasi`; kolom `status` disiapkan mencakup seluruh siklus (`diterima/ditolak/daftar_ulang/aktif`) tapi M2 tidak pernah menulis nilai selain `menunggu_verifikasi`.
- Pembatasan submit berdasarkan kuota gelombang — murni informasi untuk M3, tidak menutup form di M2.
- Simpan draft formulir sebelum submit final — form harus diselesaikan sekali duduk.
- Landing page tingkat yayasan yang mendaftar semua lembaga — sekolah membagikan link `/spmb/{slug}` masing-masing secara langsung.

---

## 2. Alur Akses & Routing

### 2.1 Entry Point
`/spmb/{lembaga:slug}` (route model binding via slug, sesuai keputusan PRD 10.1 — slug path, bukan subdomain). Menampilkan daftar jalur PPDB aktif milik lembaga tsb.

**Deteksi gelombang otomatis**: sistem mencari `GelombangPpdb` dengan `tanggal_buka <= hari ini <= tanggal_tutup` di bawah `TahunAjaran` yang `status_aktif = true` milik lembaga tsb. Kalau ada lebih dari satu gelombang overlap, ambil yang `tanggal_buka` paling awal. Kalau tidak ada gelombang yang buka, tampilkan halaman "Pendaftaran belum/sudah ditutup" — form tidak bisa diakses.

Kuota gelombang (`GelombangPpdb.kuota`) **tidak** memblokir submit di M2 — murni informasi untuk keputusan M3.

### 2.2 Verifikasi Email (OTP)
Langkah pertama wizard: calon wali murid isi email → sistem generate kode OTP 6 digit, simpan di `verifikasi_email_otp` dengan `expires_at` (mis. 10 menit), kirim via email (Laravel Mail) → calon wali murid input kode untuk lanjut. Email ini menjadi `email_pendaftaran` untuk baris `pendaftaran` yang akan dibuat.

Tujuan ganda: mencegah bot/spam sederhana, dan memastikan email valid (karena kode pendaftaran nanti dikirim ke email yang sama).

### 2.3 Reuse Data via NIK + Email
Setelah OTP terverifikasi, langkah berikutnya minta NIK. Sistem cari `CalonMurid` via `nik_hash`:
- **Tidak ditemukan** → buat `CalonMurid` baru, lanjut isi form dari nol.
- **Ditemukan, dan `email_pendaftaran` yang baru cocok dengan email pendaftaran manapun milik NIK ini sebelumnya** → tampilkan data pribadi/alamat/keluarga yang sudah ada untuk direview dan diedit, lanjut ke langkah berikutnya.
- **Ditemukan, tapi email tidak cocok dengan pendaftaran manapun sebelumnya** → **tolak melanjutkan** dengan pesan "NIK ini sudah pernah terdaftar. Gunakan email yang sama dengan pendaftaran sebelumnya, atau hubungi admin sekolah untuk bantuan." Karena `nik_hash` unique di `calon_murid`, satu NIK hanya boleh punya satu baris identitas — membiarkan form kosong lanjut dengan NIK yang sama akan **menimpa data pribadi/keluarga milik pemilik NIK asli** saat submit (bukan membuat baris baru), bukan sekadar gagal menampilkan data lama. NIK secara definisi unik per orang, jadi "NIK sama, orang beda" bukan skenario yang valid untuk diakomodasi — baik itu typo NIK sendiri atau NIK orang lain, keduanya harus diblokir di titik ini, bukan dibiarkan lanjut.

### 2.4 Kode Pendaftaran & Cek Status
Setelah submit final (langkah terakhir wizard), sistem:
1. Generate `kode_pendaftaran` unik, format `REG-{tahun}-{nomor urut 5 digit}` (mis. `REG-2026-00123`), di-scope per lembaga per tahun (bukan global) supaya nomor urut tidak melompat besar untuk lembaga kecil.
2. Simpan baris `Pendaftaran` dengan `status = menunggu_verifikasi`.
3. Kirim email berisi kode pendaftaran + link langsung ke halaman cek status.
4. Tampilkan halaman "Pendaftaran Berhasil" dengan kode tsb ditampilkan besar + instruksi cek status.

Halaman cek status: `/spmb/{lembaga:slug}/status` — form input kode pendaftaran + email. Kalau cocok, tampilkan:
- Ringkasan (nama calon murid, jalur, gelombang, tanggal submit).
- Status saat ini (label Indonesia untuk tiap nilai enum — di M2 akan selalu tampil "Menunggu Verifikasi").
- Tombol unduh bukti pendaftaran (PDF sederhana: kode pendaftaran + ringkasan data, dibuat dengan `barryvdh/laravel-dompdf` atau setara — perlu cek ketersediaan paket saat implementasi).

---

## 3. Skema Data

Prinsip: data calon murid dimodelkan relasional per kategori (bukan satu tabel datar), supaya siap dipakai sebagai data siswa aktif tanpa migrasi besar ketika modul Akademik (Fase 2 PRD) menyusul. Mengikuti pola satelit yang sudah ada untuk `Guru` (`RiwayatPendidikanGuru`, `SertifikasiGuru`) dan enkripsi NIK (`nik` + `nik_hash` sidecar, pola sama persis dengan `app/Models/Guru.php`).

### 3.1 `calon_murid` (akar entitas — Data Pribadi + Kontak)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `yayasan_id` | FK | **Bukan** `lembaga_id` — satu NIK boleh daftar ke lembaga lain di yayasan yang sama, jadi identitas discope ke yayasan, bukan lembaga. Tidak memakai trait `BelongsToTenant` (yang scope ke `lembaga_id`); scoping manual ke `yayasan_id` di query. |
| `nik` | text, encrypted cast | Pola sama seperti `Guru.nik`. |
| `nik_hash` | string(64), **unique** | `sha256(nik)`, di-set di `saving` hook (pola sama seperti `Guru`). Basis pencarian find-or-create. |
| `no_kk` | text, encrypted cast, nullable | |
| `nisn` | string, nullable | Opsional — calon murid TK/SD baru biasanya belum punya. |
| `nama_lengkap` | string | Sesuai akta. |
| `jenis_kelamin` | enum: L,P | |
| `tempat_lahir` | string | |
| `tanggal_lahir` | date | |
| `agama` | string | |
| `golongan_darah` | string, nullable | |
| `no_telepon` | string, nullable | |
| `email_kontak` | string, nullable | Email "utama" calon murid/wali (beda dari `pendaftaran.email_pendaftaran` yang per-submission). |

### 3.2 `alamat_calon_murid` (1:1 — Data Tempat Tinggal)
`calon_murid_id`, `alamat_jalan`, `rt`, `rw`, `dusun`, `desa_kelurahan`, `kecamatan`, `kabupaten_kota`, `provinsi`, `kode_pos`.

### 3.3 `keluarga_calon_murid` (1:banyak, 1-3 baris — Data Keluarga)
`calon_murid_id`, `jenis` (enum: ayah, ibu, wali), `nama`, `nik` (text, encrypted, nullable — orang tua mungkin tidak selalu mau isi), `tahun_lahir` (nullable), `pendidikan_terakhir` (nullable), `pekerjaan` (nullable), `penghasilan` (nullable, string — rentang seperti "< 1 juta", bukan angka presisi).

### 3.4 `data_periodik_calon_murid` (1:1, nullable — Data Periodik, opsional)
`calon_murid_id`, `tinggi_badan_cm` (nullable), `berat_badan_kg` (nullable), `jarak_tempuh_km` (nullable), `waktu_tempuh_menit` (nullable), `jumlah_saudara_kandung` (nullable), `alat_transportasi` (nullable, string).

### 3.5 `data_khusus_calon_murid` (1:1, nullable — Data Rinci Khusus, opsional)
`calon_murid_id`, `kepemilikan_kip` (boolean, default false), `nomor_kip` (nullable), `riwayat_beasiswa` (text, nullable), `kebutuhan_khusus` (text, nullable).

### 3.6 `pendaftaran`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `calon_murid_id` | FK | |
| `lembaga_id` | FK | Diturunkan dari lembaga di URL saat submit. |
| `tahun_ajaran_id` | FK | Tahun ajaran aktif lembaga saat submit. |
| `jalur_ppdb_id` | FK | |
| `gelombang_ppdb_id` | FK | Hasil auto-deteksi gelombang buka. |
| `kode_pendaftaran` | string, unique | `REG-{tahun}-{nomor urut}`. |
| `email_pendaftaran` | string | Email yang dipakai submit ini (lihat 2.3). |
| `status` | enum: menunggu_verifikasi, diterima, ditolak, daftar_ulang, aktif | M2 hanya pernah menulis `menunggu_verifikasi`. |
| `submitted_at` | timestamp | |

**Unique constraint**: `(calon_murid_id, gelombang_ppdb_id)` — NIK tidak bisa daftar dobel ke gelombang yang sama, boleh coba lagi di gelombang berikutnya.

### 3.7 `jawaban_formulir_pendaftaran`
`pendaftaran_id`, `formulir_field_id` (FK ke `FormulirField` milik jalur), `nilai` (text — untuk `field_type=file`, simpan path file, bukan isi upload di kolom ini langsung; upload file field ini pakai mekanisme sama seperti `dokumen_pendaftaran`).

### 3.8 `dokumen_pendaftaran`
`pendaftaran_id`, `dokumen_syarat_ppdb_id` (FK), `file_path`, `nama_file_asli`, `mime_type`, `ukuran_bytes`, `status_verifikasi` (enum: belum_diverifikasi, diterima, ditolak — default `belum_diverifikasi`, disiapkan untuk M3, tidak dipakai logic-nya di M2).

### 3.9 `verifikasi_email_otp` (ephemeral)
`email`, `kode_otp`, `expires_at`, `verified_at` (nullable). Tidak terikat `pendaftaran` (terjadi sebelum baris itu ada). Baris kedaluwarsa boleh dibersihkan berkala (out of scope M2 — cron sederhana bisa menyusul).

---

## 4. Upload Dokumen

- Tipe file diterima: PDF, JPG, PNG.
- Ukuran maksimum: 2MB per file.
- Validasi tipe & ukuran di server-side (Laravel `file` validation rule dengan `mimes:pdf,jpg,jpeg,png|max:2048`), bukan hanya client-side.
- Storage: local disk (`storage/app/public`, symlink standar Laravel), path terisolasi per pendaftaran (mis. `pendaftaran/{pendaftaran_id}/{dokumen_syarat_ppdb_id}.{ext}`) untuk mencegah tabrakan nama file antar calon murid.

---

## 5. Keamanan & Validasi

- **Enkripsi PII**: NIK calon murid dan NIK orang tua/wali dienkripsi (`encrypted` cast) dengan sidecar hash untuk NIK calon murid (pola sama seperti `Guru`). NIK orang tua tidak perlu sidecar hash karena tidak dipakai untuk pencarian/uniqueness.
- **Reuse hanya dengan verifikasi NIK+email** — mencegah pihak lain melihat/reuse data calon murid hanya dengan menebak NIK (lihat 2.3).
- **Rate limiting** (Laravel `throttle` middleware) di endpoint kirim-OTP dan submit-pendaftaran untuk mengurangi risiko spam/bot pada endpoint publik tanpa autentikasi.
- **OTP kedaluwarsa** (mis. 10 menit) dan sekali pakai (`verified_at` di-set setelah dipakai, tidak bisa dipakai ulang).
- **Unique constraint database-level** `(calon_murid_id, gelombang_ppdb_id)` pada `pendaftaran`, bukan hanya validasi aplikasi — mencegah race condition submit ganda.

---

## 6. Keputusan Arsitektur

| Area | Keputusan |
|---|---|
| Mekanisme akses | Kode pendaftaran + email, tanpa akun/password — sesuai PRD "token akses" |
| Draft formulir | Tidak ada — form diselesaikan sekali duduk |
| Struktur formulir | Wizard multi-step, bukan satu halaman panjang |
| Skema data calon murid | Relasional per kategori (5 tabel), bukan satu tabel datar — disiapkan untuk jadi data siswa aktif tanpa migrasi besar |
| Reuse data lintas gelombang | Ya, dicari via `nik_hash`. Email juga harus cocok dengan pendaftaran sebelumnya untuk lanjut — kalau NIK ditemukan tapi email tidak cocok, alur **diblokir** (bukan dibiarkan lanjut dengan form kosong), karena NIK unik per orang dan form kosong lanjut akan menimpa data pemilik NIK asli |
| Gelombang | Auto-terdeteksi (tanggal_buka paling awal yang overlap hari ini), tidak dipilih manual |
| Kuota gelombang | Tidak membatasi submit di M2 — informasi untuk M3 |
| Tagihan pendaftaran | Dilewati sepenuhnya di M2, menyusul M4-M5 |
| Data wajib vs opsional | Pribadi+Alamat+Keluarga+Kontak wajib; Data Periodik+Data Khusus opsional |
| Proteksi form publik | Verifikasi OTP email sebelum lanjut + rate limiting |
| Isi halaman cek status | Ringkasan + status + unduh bukti pendaftaran (PDF) |

---

## 7. Rencana Pengujian

- **Routing & deteksi gelombang**: test lembaga dengan gelombang buka menampilkan form; lembaga tanpa gelombang buka menampilkan halaman tertutup; dua gelombang overlap memilih yang `tanggal_buka` paling awal.
- **OTP**: kode benar & belum kedaluwarsa meloloskan; kode salah/kedaluwarsa/sudah dipakai ditolak.
- **Reuse NIK**: NIK+email cocok → data lama ter-load; NIK cocok tapi email beda → alur diblokir dengan pesan jelas, TIDAK lanjut ke form kosong (test eksplisit bahwa data `CalonMurid` asli tidak ter-overwrite oleh percobaan ini); NIK baru → `CalonMurid` baru dibuat.
- **Unique constraint gelombang**: submit kedua ke gelombang yang sama dengan NIK yang sama ditolak; submit ke gelombang berbeda dengan NIK yang sama diterima.
- **Upload dokumen**: file tipe/ukuran valid diterima; tipe/ukuran invalid ditolak dengan pesan jelas (bukan crash).
- **Kode pendaftaran**: unik, format benar, ter-generate tepat sekali per submit (idempotent terhadap double-submit/retry).
- **Cek status**: kode+email benar menampilkan data; kombinasi salah menampilkan pesan tidak ditemukan (bukan bocor info calon murid lain).
- **Enkripsi**: NIK calon murid & orang tua tersimpan terenkripsi di database (test langsung baca kolom raw, bukan lewat model cast, mengikuti pola test `Guru`/`Lembaga` yang sudah ada).
