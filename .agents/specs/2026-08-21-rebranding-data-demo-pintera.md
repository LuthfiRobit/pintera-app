# Rebranding Data Demo Pintera — Spec 2: Email, Nama Sekolah, Nama Manusia

## 1. Latar Belakang

Spec independen dari Spec 1 (`.agents/specs/2026-08-21-audit-perbaikan-seeder.md`) — tidak saling bergantung, boleh dieksekusi & ditest terpisah (keputusan eksplisit user 2026-08-21). Menutup 3 permintaan tambahan di luar audit bug murni:

1. Email dummy terlalu panjang untuk demo/testing.
2. Nama yayasan/lembaga (`Yayasan Permata Kraksaan` dkk) diganti ke branding "Pintera".
3. Nama manusia (kepala sekolah, staf, guru) yang masih memakai honorifik keagamaan ("Ustadz"/"Ustadzah") diganti ke pola netral.

Baseline: hasil grep menyeluruh `database/seeders/*.php` untuk pola email (`@sistem.test`, `@example.test`, `@permatakraksaan.sch.id`) dan honorifik (`Ustadz`/`Ustadzah`), dijalankan 2026-08-21. Plan implementasi WAJIB grep ulang sebelum eksekusi kalau ada commit baru — daftar di bawah adalah hasil investigasi nyata, bukan asumsi.

**Catatan ketergantungan dengan Spec 1:** beberapa akun yang berubah emailnya di sini (`admin.yayasan@...`, `bendahara.yayasan@sistem.test`, akun `admin_sarpras` baru) juga disebut di Spec 1 §3.4 dan §5. Kedua spec boleh dieksekusi dalam urutan apapun, tapi kalau Spec 1 dieksekusi lebih dulu, plan Spec 2 WAJIB membaca ulang state file-file itu (bisa saja nama variabel/struktur sedikit berbeda dari saat spec ini ditulis) sebelum melakukan perubahan email.

## 2. Bagian A — Pola Email Baru

**Domain login staff/demo:** `@demo.test` (mengganti `@sistem.test` DAN `@permatakraksaan.sch.id` untuk SEMUA akun login/kredensial).

**Domain kontak institusi resmi** (field `email`/`website` di `Lembaga`/`Yayasan`, BUKAN akun login): `pintera.sch.id` — mengikuti keputusan §3, tanpa embel-embel kota.

**Kode lembaga untuk pembeda antar-lembaga di email:** `kb`, `tk`, `sd`, `smp` (menggantikan `kbit`/`tkit`/`sdit`/`smpit`).

### 2.1 Mapping Role → Pola Email

| Role | Scope | Pola baru |
|---|---|---|
| `yayasan_super_admin` (akun `EssentialUserSeeder`) | yayasan | `superadmin@demo.test` |
| `yayasan_super_admin` (akun `UserSeeder`, pola realistis) | yayasan | `adm.yayasan@demo.test` |
| `bendahara_yayasan` | yayasan | `keuangan.yayasan@demo.test` |
| `admin_sarpras` (akun baru dari Spec 1 §3.4) | yayasan | `sarpras.yayasan@demo.test` |
| `kepala_sekolah` | per-lembaga | `kepsek.{kode}@demo.test` |
| `admin_administrasi` | per-lembaga | `adm.{kode}@demo.test` |
| `admin_keuangan` | per-lembaga | `keuangan.{kode}@demo.test` |
| `admin_akademik` | per-lembaga | `kurikulum.{kode}@demo.test` |
| `guru` | per-lembaga | `guru.{kode}{n}@demo.test` |
| `siswa` (akun demo) | per-lembaga | `siswa.{kode}@demo.test` |
| `orang_tua` (demo) | — | `ortu@demo.test`, `ortu.kb@demo.test`, `ortu.tk@demo.test` |
| `karyawan_pool` (Psikolog) | pool | `psikolog.pool@demo.test` |
| Pendaftar/wali (portal PPDB, bukan staff RBAC) | — | `wali.{status}@demo.test`, `pendaftar.{kode}@demo.test` |

Role name di kode (`admin_keuangan`, dst) **TIDAK diubah** — keputusan eksplisit user, rename role slug ditunda ke fase menjelang production, di luar cakupan spec ini.

### 2.2 Daftar Perubahan per File (hasil grep 2026-08-21)

| File | Email lama | Email baru |
|---|---|---|
| `EssentialUserSeeder.php` | `superadmin@sistem.test` | `superadmin@demo.test` |
| | `kepsek@sistem.test` | `kepsek.kb@demo.test` *(pilih 1 lembaga representatif — lihat §2.3)* |
| | `adm@sistem.test` | `adm.kb@demo.test` |
| | `keuangan@sistem.test` | `keuangan.kb@demo.test` |
| | `akademik@sistem.test` | `kurikulum.kb@demo.test` |
| | `guru@sistem.test` | `guru.kb1@demo.test` |
| `UserSeeder.php` | `admin.yayasan@permatakraksaan.sch.id` | `adm.yayasan@demo.test` |
| | `kepsek.kbit@...` / `adm.kbit@...` / `keuangan.kbit@...` | `kepsek.kb@demo.test` / `adm.kb@demo.test` / `keuangan.kb@demo.test` |
| | `guru.kbit1/2/3@...` | `guru.kb1/2/3@demo.test` |
| | `kepsek.tkit@...` / `adm.tkit@...` / `keuangan.tkit@...` | `kepsek.tk@demo.test` / `adm.tk@demo.test` / `keuangan.tk@demo.test` |
| | `guru.tkit1/2/3@...` | `guru.tk1/2/3@demo.test` |
| | `kepsek.sdit@...` / `adm.sdit@...` / `keuangan.sdit@...` | `kepsek.sd@demo.test` / `adm.sd@demo.test` / `keuangan.sd@demo.test` |
| | `kepsek.smpit@...` / `adm.smpit@...` / `keuangan.smpit@...` | `kepsek.smp@demo.test` / `adm.smp@demo.test` / `keuangan.smp@demo.test` |
| `GuruSeeder.php` | `guru.kbit1/2/3@...`, `guru.tkit1/2/3@...` | `guru.kb1/2/3@demo.test`, `guru.tk1/2/3@demo.test` *(HARUS identik dengan `UserSeeder.php` — file ini query User yang sama)* |
| `SiswaSeeder.php` | `siswa.kbit@...`, `siswa.tkit@...`, `siswa.sdit@...`, `siswa.smpit@...` | `siswa.kb@demo.test`, `siswa.tk@demo.test`, `siswa.sd@demo.test`, `siswa.smp@demo.test` |
| `OrangTuaKaryawanSeeder.php` | `psikolog.pool@permatakraksaan.sch.id` | `psikolog.pool@demo.test` |
| | `ortu.demo@...` (2 kemunculan) | `ortu@demo.test` |
| | `ortu.kb.demo@...` (2 kemunculan) | `ortu.kb@demo.test` |
| | `ortu.tk.demo@...` (2 kemunculan) | `ortu.tk@demo.test` |
| `KeuanganDemoSeeder.php` | `ortu.demo@...`, `ortu.kb.demo@...`, `ortu.tk.demo@...` (baris 30-32, HARUS sinkron dengan `OrangTuaKaryawanSeeder.php` — file ini query User yang sama by `username`=NIK, bukan email, jadi cukup update literal email di komentar/dokumentasi kalau ada) | `ortu@demo.test`, `ortu.kb@demo.test`, `ortu.tk@demo.test` |
| | `keuangan.{prefix}@permatakraksaan.sch.id` (baris 142, `$prefix` dari `kode_lembaga`) | `keuangan.{kode}@demo.test` — **logic `cariAdminKeuangan()` HARUS diupdate** untuk memetakan `kode_lembaga` baru (`KBITPTR` dst, lihat Bagian B) ke kode pendek (`kb`/`tk`/`sd`/`smp`), bukan sekadar strip suffix `PRM` seperti sekarang |
| `SarprasPengadaanDemoSeeder.php` | `superadmin@sistem.test`, `bendahara.yayasan@sistem.test`, `kepsek@sistem.test`, `adm@sistem.test` | `superadmin@demo.test`, `keuangan.yayasan@demo.test`, `kepsek.kb@demo.test`, `adm.kb@demo.test` |
| `PendampinganSeeder.php` | `adm.smpit@permatakraksaan.sch.id` (baris 42, query `$adminUser`) | `adm.smp@demo.test` |
| `AkunPendaftarSeeder.php` | `pendaftar.kbit/tkit/sdit/smpit@example.test` | `pendaftar.kb/tk/sd/smp@demo.test` |
| `PendaftaranSeeder.php`, `PembayaranSeeder.php`, `SkPpdbSeeder.php`, `TagihanSeeder.php`, `SkemaCicilanSeeder.php`, `CicilanSeeder.php`, `DokumenPendaftaranSeeder.php`, `HasilSeleksiSeeder.php` | `wali.menunggu@example.test`, `wali.diterima@example.test`, `wali.ditolak@example.test`, `wali.cicilan-demo@example.test` | `wali.menunggu@demo.test`, `wali.diterima@demo.test`, `wali.ditolak@demo.test`, `wali.cicilan@demo.test` *(drop `-demo`, semua kemunculan di 8 file ini HARUS diganti bersamaan — string literal yang sama dipakai lintas file sebagai kunci pencarian)* |
| `LembagaSeeder.php` | `kbit@...`, `tkit@...`, `sdit@...`, `smpit@permatakraksaan.sch.id` (field institusi, bukan login) | `kbit@pintera.sch.id`, dst — lihat Bagian B |
| `YayasanSeeder.php` | `info@permatakraksaan.sch.id` | `info@pintera.sch.id` |

### 2.3 Akun `EssentialUserSeeder` yang cuma 1 lembaga

`EssentialUserSeeder.php` sekarang membuat 1 set akun per-role (`kepsek@sistem.test` dst) TANPA menyebut lembaga spesifik — secara implisit merepresentasikan "lembaga pertama". Karena pola baru mewajibkan kode lembaga di email, plan implementasi HARUS menentukan lembaga mana yang dipakai sebagai representasi (disarankan: lembaga pertama yang di-seed, `kb`, konsisten dengan urutan `LembagaSeeder.php`). Ini murni penamaan, tidak mengubah jumlah/cakupan akun yang sudah ada.

## 3. Bagian B — Rebranding Yayasan & Lembaga ke "Pintera"

**Yayasan:** `Yayasan Permata Kraksaan` → `Yayasan Pintera` (`YayasanSeeder.php`).

**4 Lembaga** (`LembagaSeeder.php`) — nama dipendekkan TANPA "KRAKSAAN" (keputusan eksplisit user), alamat administratif (kecamatan Kraksaan, kabupaten Probolinggo, dll) **TETAP** karena itu data lokasi asli, bukan bagian dari brand:

| Bentuk | Nama lama | Nama baru | `kode_lembaga` lama → baru |
|---|---|---|---|
| KB | KBIT PERMATA KRAKSAAN | KBIT PINTERA | `KBITPRM` → `KBITPTR` |
| TK | TKIT PERMATA KRAKSAAN | TKIT PINTERA | `TKITPRM` → `TKITPTR` |
| SD | SDIT PERMATA KRAKSAAN | SDIT PINTERA | `SDITPRM` → `SDITPTR` |
| SMP | SMPIT PERMATA KRAKSAAN | SMPIT PINTERA | `SMPITPRM` → `SMPITPTR` |

**Domain institusi:** `pintera.sch.id` (tanpa kota) — lihat Bagian A untuk daftar email/website lengkap.

**Tidak berubah:** NPSN, NSS, alamat jalan, RT/RW, desa/kecamatan/kabupaten/provinsi, kode pos, koordinat, telepon, data bank, NPWP — seluruhnya tetap seperti sekarang, karena tidak menyebut kata "Permata" dan sudah berupa data administratif fiktif yang masuk akal.

**Field turunan yang ikut berubah otomatis** (isinya = nama lembaga):
- `rekening_atas_nama` (mis. `'KBIT PERMATA KRAKSAAN'` → `'KBIT PINTERA'`)
- `nama_wajib_pajak` (sama pola)

**Konsekuensi ke `KeuanganDemoSeeder::cariAdminKeuangan()`:** method ini saat ini mengambil kode lembaga dengan `preg_replace('/PRM$/', '', $lembaga->kode_lembaga)` lalu lowercase (`kbit`, `tkit`, dst) untuk membentuk email `keuangan.{prefix}@...`. Karena `kode_lembaga` baru berakhiran `PTR` (bukan `PRM`) DAN pola email baru pakai kode pendek (`kb`/`tk`/`sd`/`smp`, bukan `kbit`/`tkit`), regex ini HARUS diganti total — bukan sekadar ganti suffix `PRM`→`PTR`. Solusi: ganti jadi mapping eksplisit (`match($lembaga->bentuk_pendidikan) { 'KB' => 'kb', 'TK' => 'tk', 'SD' => 'sd', 'SMP' => 'smp' }`) — lebih robust daripada regex terhadap `kode_lembaga`.

## 4. Bagian C — Nama Manusia Netral

Hapus honorifik keagamaan ("Ustadz"/"Ustadzah"), pertahankan nama depan + gelar akademik apa adanya. Nama tunggal (mis. "Aisyah, S.Psi.") valid dan lazim di Indonesia — tidak ditambah nama keluarga, sesuai keputusan user untuk tetap ringkas.

**Sinkronisasi wajib (existing, bukan desain baru):** nama Kepala Sekolah & Bendahara BOSP di `LembagaSeeder.php` (field `nama_kepala_sekolah`/`nama_bendahara_bosp`) HARUS tetap identik dengan nama User login untuk peran yang sama di `UserSeeder.php`/`GuruSeeder.php` — pola sinkronisasi ini sudah ada di kode saat ini (kebetulan/sengaja sama persis) dan wajib dipertahankan.

| Peran & Lembaga | Lama | Baru |
|---|---|---|
| Kepsek KB (`UserSeeder`, `LembagaSeeder.nama_kepala_sekolah`) | Ustadzah Aisyah, S.Psi. | Aisyah, S.Psi. |
| Adm KB (`UserSeeder`) | Ustadzah Nurul, S.Pd. | Nurul, S.Pd. |
| Keuangan KB (`UserSeeder`) / Bendahara BOSP KB (`LembagaSeeder`) | Ustadzah Halimah, S.E. | Halimah, S.E. |
| Guru KB 1 (`UserSeeder`, `GuruSeeder`) | Ustadzah Fatimah, S.Psi. | Fatimah, S.Psi. |
| Guru KB 2 (`UserSeeder`, `GuruSeeder`) | Ustadzah Zahra, S.Pd. | Zahra, S.Pd. |
| Guru KB 3 (`UserSeeder`, `GuruSeeder`) | Ustadzah Rini, S.Pd. | Rini, S.Pd. |
| Kepsek TK (`UserSeeder`, `LembagaSeeder`) | Ustadzah Maryam, S.Pd.I. | Maryam, S.Pd.I. |
| Adm TK (`UserSeeder`) | Ustadzah Indria, S.Pd. | Indria, S.Pd. |
| Keuangan TK (`UserSeeder`) / Bendahara BOSP TK (`LembagaSeeder`) | Ustadzah Khadijah, S.E. | Khadijah, S.E. |
| Guru TK 1 (`UserSeeder`, `GuruSeeder`) | Ustadzah Dewi, S.Pd.I. | Dewi, S.Pd.I. |
| Guru TK 2 (`UserSeeder`, `GuruSeeder`) | Ustadzah Latifah, S.Pd. | Latifah, S.Pd. |
| Guru TK 3 (`UserSeeder`, `GuruSeeder`) | Ustadzah Amel, S.Psi. | Amel, S.Psi. |
| Kepsek SD (`UserSeeder`, `LembagaSeeder`) | Ustadz Abdullah, M.Pd. | Abdullah, M.Pd. |
| Adm SD (`UserSeeder`) | Ustadz Lukman, S.Kom. | Lukman, S.Kom. |
| Keuangan SD (`UserSeeder`) / Bendahara BOSP SD (`LembagaSeeder`) | Ustadz Hasan, S.E. | Hasan, S.E. |
| Kepsek SMP (`UserSeeder`, `LembagaSeeder`) | Ustadz Bambang Suryadi, M.Pd. | Bambang Suryadi, M.Pd. |
| Adm SMP (`UserSeeder`) | Dewi Lestari, S.Pd. | *(tidak berubah, sudah netral)* |
| Keuangan SMP (`UserSeeder`) | Nur Aisyah, S.Pd. | *(tidak berubah, sudah netral)* |
| Bendahara BOSP SMP (`LembagaSeeder`) | Ustadz Fajar Ramadhan, S.E. | Fajar Ramadhan, S.E. |
| Bendahara Yayasan (`SarprasPengadaanDemoSeeder.php` baris 81) | Ustadz Farid (Bendahara Yayasan) | Farid |

**Verifikasi wajib saat plan ditulis:** grep ulang `Ustadz\|Ustadzah` di seluruh `database/seeders/*.php` untuk memastikan tidak ada kemunculan lain di luar 19 baris yang teridentifikasi di atas (mis. guru SD/SMP kalau ternyata ada baris yang tidak tertangkap grep awal), dan grep nama-nama di atas satu-persatu di `tests/` untuk memastikan tidak ada test yang meng-assert nama lama secara hardcode (kalau ada, harus diupdate bersamaan).

## 5. Testing

- Setelah `migrate:fresh --seed`, login manual (atau test otomatis) dengan beberapa akun representatif dari tabel §2.2 (`superadmin@demo.test`, `kepsek.kb@demo.test`, `guru.kb1@demo.test`) memakai password `password` — harus berhasil.
- Test yang meng-assert email/nama secara hardcode (kalau ditemukan lewat grep §4) HARUS diupdate mengikuti nilai baru, bukan dibiarkan gagal atau di-skip.
- `KeuanganDemoSeeder::cariAdminKeuangan()` — tambah/pastikan test yang membuktikan mapping `bentuk_pendidikan` → kode email baru bekerja untuk keempat lembaga.
- Scoped test per task; full suite HANYA di task terakhir dan HARUS minta izin user dulu.

## 6. Di Luar Cakupan

- Rename role slug di kode (`admin_keuangan` dkk) — ditunda ke fase menjelang production, keputusan eksplisit user 2026-08-21.
- Perbaikan bug RBAC/idempotency/password-guard — itu Spec 1, terpisah.
- Variasi/keberagaman nama lewat Faker atau library eksternal — ditolak eksplisit oleh user, cukup ganti pola nama yang sudah ada ke netral tanpa menambah jumlah.
- Data siswa/orang tua/calon murid yang namanya sudah netral (di luar 19 baris Ustadz/Ustadzah yang teridentifikasi) — tidak disentuh.

## 7. Asumsi

- Baseline: hasil grep 2026-08-21 sebagaimana didokumentasikan di §2.2 dan §4. Plan implementasi WAJIB grep ulang sebelum eksekusi untuk menangkap kemungkinan commit baru (termasuk kemungkinan hasil eksekusi Spec 1 yang sudah lebih dulu berjalan).
- Tidak ada file test yang secara spesifik menguji format email (`@permatakraksaan.sch.id`) atau nama honorifik ("Ustadz") sebagai bagian dari assertion bisnis (mis. validasi format email institusi) — kalau ternyata ada, plan implementasi harus menyesuaikan test tersebut juga, bukan mengabaikannya.
