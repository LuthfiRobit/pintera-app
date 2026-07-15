# Portal Akun Pendaftar — Design Spec

**Tanggal:** 2026-07-15
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Tujuan

Ini adalah **sub-project 1 dari 3** rangkaian modul Keuangan (mengikuti PRD bagian 11, M4-M7), yang urutan lengkapnya:

1. **Portal Akun Pendaftar** (spec ini) — sistem akun/login publik untuk calon siswa, plus penautan otomatis ke data `Pendaftaran` yang sudah ada.
2. Keuangan — Master Tagihan & Mesin Invoicing (jenis tagihan generik lintas konteks, generate tagihan otomatis dari event SPMB).
3. Keuangan — Pembayaran Manual & Portal Tagihan (upload bukti transfer, verifikasi admin keuangan, tampilan tagihan/cicilan di portal ini).

Sub-project 1 ini murni fondasi: tanpa akun yang persisten, tidak ada tempat yang stabil untuk menampilkan tagihan/riwayat pembayaran ke calon siswa nantinya (M2 saat ini hanya berbasis sesi/OTP per-kunjungan, cocok untuk wizard sekali jalan tapi tidak untuk pelacakan berulang seperti pembayaran/cicilan).

**Prinsip pemilik akun:** akun dimiliki oleh **calon siswa sendiri** (bukan wali murid) — sesuai kondisi nyata di lapangan saat ini. Satu akun bisa terhubung ke lebih dari satu `Pendaftaran` (misalnya siswa yang sama mendaftar di beberapa jalur/gelombang/lembaga).

## 2. Lingkup

**Termasuk dalam spec ini:**
- Registrasi akun baru (nama, email, password) + verifikasi email via OTP.
- Login/logout, lupa password.
- Penautan otomatis akun ↔ `Pendaftaran` berdasarkan kecocokan email, dua arah.
- Dashboard portal: daftar `Pendaftaran` tertaut + status, unduh bukti pendaftaran PDF.
- Layout portal baru: sidebar + navbar + footer (struktur seperti admin), warna tetap palet biru `spmb-*` yang sudah ada.

**Tidak termasuk (sengaja ditunda):**
- Modul Keuangan itu sendiri (master tagihan, invoicing, pembayaran) — sub-project 2 & 3.
- Penggajian guru — arah uang berlawanan (pengeluaran, bukan piutang), bentuk data berbeda (slip gaji: pokok+tunjangan−potongan, periodik), akan jadi modul terpisah yang meniru pola (master + audit + verifikasi) tanpa berbagi tabel dengan tagihan SPMB.
- Redesain wizard SPMB M2 — **tidak disentuh** kecuali satu penambahan kecil di titik akhir `ReviewSubmitController::submit()` (lihat bagian 4.2), bukan perubahan alur.
- Portal untuk "Wali Murid" pasca-siswa-aktif (lihat SPP, dst per PRD) — di luar cakupan pilot ini.

## 3. Model Data

### 3.1 Tabel `akun_pendaftar`
```
id
nama            (string)
email           (string, unique)
password        (string, hashed)
email_verified_at (timestamp, nullable)
remember_token  (string, nullable)
timestamps
```
Model `App\Models\AkunPendaftar` — `Authenticatable`, TIDAK memakai `BelongsToTenant`/`TenantScope` (akun ini bukan milik satu lembaga, karena satu calon siswa bisa mendaftar ke lembaga manapun dalam yayasan).

### 3.2 Kolom baru di `pendaftaran`
```
akun_pendaftar_id  (FK nullable, nullOnDelete)
```
Diisi oleh logika penautan otomatis (bagian 5), tidak pernah diisi manual oleh pengguna.

### 3.3 Guard baru
`config/auth.php` — guard `portal` (session-based), provider tersendiri menunjuk ke `AkunPendaftar` (bukan `User`). Terpisah total dari guard `web` (staff/admin) — memakai session key yang berbeda, sehingga:
- Akun staff (`User`) tidak bisa dipakai login ke guard `portal`.
- Akun pendaftar (`AkunPendaftar`) tidak bisa dipakai login ke guard `web`.
- **Catatan implementasi penting**: `TenantAwareUserProvider` (dibuat untuk memperbaiki bug pengalih lembaga sebelumnya) khusus menangani `User`/`TenantScope`. Guard `portal` tidak butuh provider ini karena `AkunPendaftar` tidak punya `TenantScope` sama sekali — pastikan provider baru untuk guard ini memakai `EloquentUserProvider` biasa, tidak perlu logika tenant apapun.

## 4. Alur Registrasi & Verifikasi

### 4.1 Registrasi
- `GET /portal/register` — form nama, email, password, konfirmasi password.
- `POST /portal/register` — validasi (email unik di `akun_pendaftar`, password kuat standar), buat `AkunPendaftar` (`email_verified_at` masih null), panggil `OtpService::kirim($email)` (service yang sudah ada dari M2, generik berbasis email saja — dipakai ulang tanpa modifikasi), redirect ke halaman verifikasi OTP.
- Rate limit `throttle:6,1` pada endpoint ini, konsisten dengan pola M2.

### 4.2 Verifikasi OTP & Auto-Login
- `GET /portal/verifikasi-otp` — pakai ulang komponen 6-kotak (`otp-input.js`) dari M2.
- `POST /portal/verifikasi-otp` — verifikasi via `OtpService::verifikasi()`. Kode benar →
  1. Set `akun_pendaftar.email_verified_at = now()`.
  2. Jalankan **auto-link arah 1**: `Pendaftaran::where('email_pendaftaran', $akun->email)->whereNull('akun_pendaftar_id')->update(['akun_pendaftar_id' => $akun->id])`.
  3. `Auth::guard('portal')->login($akun)`.
  4. Redirect ke `/portal/dashboard`.

### 4.3 Auto-link arah 2 (dari M2)
Satu-satunya sentuhan ke kode M2 yang sudah shipped: di titik akhir `ReviewSubmitController::submit()` (setelah `Pendaftaran` berhasil dibuat, di luar transaksi DB yang sudah ada — tidak mengubah logika transaksi itu sendiri), tambahkan satu langkah:
```php
$akun = AkunPendaftar::where('email', $pendaftaran->email_pendaftaran)
    ->whereNotNull('email_verified_at')
    ->first();

if ($akun) {
    $pendaftaran->update(['akun_pendaftar_id' => $akun->id]);
}
```
Ini murni penambahan (additive), tidak mengubah satu pun langkah/validasi/urutan yang sudah ada dan sudah direview di M2.

### 4.4 Login & Lupa Password
- `GET/POST /portal/login` — email+password via guard `portal`. Akun dengan `email_verified_at` masih null ditolak login, diarahkan ke `/portal/verifikasi-otp` (OTP baru otomatis dikirim ulang).
- Lupa password — alur reset standar Laravel (link via email), memakai tabel dedicated `akun_pendaftar_password_reset_tokens` (bukan `password_reset_tokens` bawaan yang dipakai staff) — mencegah tabrakan baris kalau suatu saat email staff dan email calon siswa kebetulan sama.

## 5. Auto-Link — Prinsip Inti

Logika penautan berjalan **dua arah**, supaya konsisten tidak peduli urutan kejadian:

| Urutan | Yang terjadi lebih dulu | Yang terjadi belakangan | Penautan terjadi di |
|---|---|---|---|
| A | Sudah pernah daftar SPMB (M2) | Baru bikin akun sekarang | Saat OTP registrasi diverifikasi (4.2 langkah 2) |
| B | Bikin akun dulu | Daftar SPMB (M2) belakangan | Saat `ReviewSubmitController::submit()` (4.3) |

Kedua arah dites eksplisit (lihat bagian 7). Penautan **hanya berdasarkan kecocokan email**, tidak ada langkah "klaim" manual — dan hanya menautkan ke akun yang `email_verified_at`-nya sudah terisi (mencegah seseorang membuat akun dengan email yang bukan miliknya untuk mengklaim pendaftaran orang lain sebelum verifikasi kepemilikan email selesai).

## 6. Dashboard & Layout Portal

### 6.1 Layout baru
`resources/views/layouts/portal.blade.php` (atau komponen `x-portal-layout`) — struktur meniru `x-app-layout` admin: sidebar kiri (daftar menu), navbar/topbar atas (nama akun + tombol keluar), footer bawah. **Warna tetap memakai token `spmb-*`** (biru) yang sudah dibangun untuk wizard M2 — bukan ink/brass/paper milik admin. Ini keputusan sadar: strukturnya seperti admin (portal sungguhan, bukan kartu tunggal di tengah halaman), tapi identitas visualnya tetap bagian dari SPMB publik.

Sidebar untuk sekarang hanya berisi menu "Dashboard" — disiapkan strukturnya agar sub-project 3 (Portal Tagihan) tinggal menambah item menu baru tanpa membangun ulang layout.

### 6.2 Dashboard (`GET /portal/dashboard`)
Dilindungi guard `portal` + wajib `email_verified_at` terisi. Menampilkan seluruh `Pendaftaran` dengan `akun_pendaftar_id` = akun yang login:
- Nama calon murid, lembaga, jalur, gelombang, kode pendaftaran, status (badge sama seperti admin: Menunggu Verifikasi/Diterima/Ditolak), tanggal submit.
- Tombol "Unduh Bukti Pendaftaran (PDF)" per baris — pakai ulang generator PDF M2 (`bukti-pendaftaran.blade.php`) lewat route baru yang diautentikasi guard `portal` (bukan lewat form kode+email manual seperti `CekStatusController` yang sudah ada), dengan pengecekan `pendaftaran->akun_pendaftar_id === auth('portal')->id()`.
- State kosong: pesan ramah kalau belum ada Pendaftaran tertaut sama sekali (belum ada link "mulai SPMB" otomatis, karena setiap lembaga punya slug URL sendiri).

## 7. Keamanan & Rencana Pengujian

- **Isolasi antar akun**: dashboard dan unduh PDF hanya boleh menampilkan/mengizinkan data milik akun yang login — diuji dengan dua akun berbeda saling mencoba akses data satu sama lain (termasuk menebak ID pendaftaran langsung lewat URL), mengikuti standar kekakuan yang sama seperti pengujian tenant-isolation M2/M3.
- **Auto-link, kedua arah** (paling kritis — ini prinsip inti fitur):
  - Arah A (4.2): akun diverifikasi setelah ada Pendaftaran lama dengan email sama → tertaut.
  - Arah B (4.3): Pendaftaran baru disubmit setelah akun sudah terverifikasi → tertaut saat itu juga.
  - Email berbeda → tidak pernah tertaut (bukti isolasi, bukan cuma "tidak error").
  - Satu akun, banyak Pendaftaran dengan email sama → semuanya tertaut, bukan cuma yang pertama.
  - Akun yang **belum** terverifikasi tidak pernah dipakai sebagai target auto-link.
- **Pemisahan guard**: akun `User` (staff, guard `web`) tidak bisa dipakai login ke guard `portal`, dan sebaliknya.
- **Login**: kredensial salah ditolak dengan pesan generik; akun belum terverifikasi diblokir & diarahkan kirim ulang OTP.
- **Rate limiting**: endpoint registrasi & kirim-ulang OTP memakai throttle yang sama seperti pola M2.

## 8. Non-Tujuan / Catatan untuk Sub-Project Berikutnya

- Sub-project 2 (Master Tagihan & Invoicing) akan menambahkan referensi dari `tagihan` ke `akun_pendaftar_id` (bukan ke `pendaftaran` secara langsung untuk tampilan tagihan-per-akun) — tabel `jenis_tagihan` harus dirancang generik (tidak terikat kata "SPMB" di struktur/nama kolomnya) supaya jenis tagihan lain (SPP, uang pangkal, dst) bisa dipakai lewat master yang sama tanpa migrasi ulang.
- Sub-project 3 (Portal Pembayaran) akan menambah menu baru ke sidebar layout ini (`layouts/portal.blade.php`), dan halaman detail tagihan/cicilan per Pendaftaran.
- Modul penggajian guru (kalau/ketika dikerjakan) akan meniru pola (tabel master + audit trail + verifikasi manual) dari modul Keuangan ini, tapi lewat tabel-tabel terpisah (`komponen_gaji`, `slip_gaji`, dst) — tidak berbagi tabel `tagihan`/`tagihan_item` sama sekali, karena arah uangnya berlawanan (pengeluaran, bukan piutang) dan bentuk datanya berbeda (periodik + pokok/tunjangan/potongan, bukan invoice+item+cicilan berbasis event).
