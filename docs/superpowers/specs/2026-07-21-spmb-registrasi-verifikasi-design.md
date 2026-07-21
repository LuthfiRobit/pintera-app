# Restrukturisasi Pendaftaran Calon Siswa — Sub-project 2: Registrasi Akun Baru + Verifikasi — Design Spec

**Tanggal:** 2026-07-21
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Tujuan

Ini adalah sub-project 2 dari 4 dalam inisiatif "restrukturisasi alur pendaftaran calon siswa". Sub-project 1 (Welcome & Katalog Lembaga/Jalur, sudah shipped ke `main`) menutup langkah pertama alur baru: memilih lembaga dan jalur. Tombol "Daftar Jalur Ini" di halaman Detail Lembaga menyimpan pilihan itu ke session (`spmb_pilihan.lembaga_id`/`spmb_pilihan.jalur_id`) lalu mengecek `Route::has('spmb.register')` — kalau belum ada, redirect balik dengan pesan "Fitur pendaftaran akan segera hadir." Sub-project ini menutup gap itu.

**Temuan penting saat eksplorasi kode:** backend untuk register, verifikasi OTP, login, lupa password, dan reset password **sudah ada dan berfungsi** di `App\Http\Controllers\Portal\Auth\*` (route prefix `portal.*`), memakai `OtpService`+`VerifikasiEmailOtp` yang sama dengan yang sudah dipakai wizard SPMB lama. Sub-project ini BUKAN membangun sistem auth dari nol, melainkan menutup gap spesifik di sistem yang sudah ada:

1. Kolom `no_hp_wa` belum ada di `akun_pendaftar`.
2. Aturan password saat ini cuma `Password::defaults()` (minimal 8 karakter, tanpa syarat tambahan) — belum benar-benar "strong".
3. Alur registrasi tidak membaca `spmb_pilihan.*` sama sekali — form register tidak tahu lembaga/jalur mana yang sedang dipilih pengunjung.
4. Tidak ada aksi kirim-ulang OTP eksplisit (padahal diminta di proposal awal inisiatif ini).
5. Kelima view auth (`resources/views/portal/auth/*`) masih pakai desain lama (`x-spmb-public-layout`, token `spmb-primary`/`slate`).
6. Route registrasi bernama `portal.register`, bukan `spmb.register` yang dicek Sub-project 1.

## 2. Referensi Visual

Mockup interaktif yang sama dengan Sub-project 1: **https://claude.ai/code/artifact/a1987ae5-0050-440d-af88-08cfe01415af** — layar 3 (Register Akun), 4 (Verifikasi OTP), 5 (Masuk) adalah cakupan sub-project ini secara spesifik.

Token visual identik dengan Sub-project 1 (`portal-50/500/600`, `gray-*`, `success-*`/`warning-*`/`error-*`, font Outfit, `shadow-card`/`shadow-elevated`/`shadow-panel`). Wajib responsif di semua ukuran layar mengikuti pola yang sudah divalidasi di Sub-project 1 (breakpoint navbar/top-bar ≤720px, grid 2-kolom kolaps ke 1 kolom, dst).

**Beda struktural dari Welcome/Detail Lembaga:** kelima halaman auth di sub-project ini TIDAK memakai `<x-portal-navbar>` penuh — mockup memakai top-bar sederhana (`.pt-auth-top`: logo + satu link kontekstual saja, tanpa menu Beranda/Lembaga/Alur/Bantuan). Ini komponen baru (`<x-portal-auth-top>`), dipakai lewat layout baru (`<x-layouts.portal-auth>`), bukan `layouts.portal-public` milik Sub-project 1. Footer tetap `<x-portal-footer>` yang sama.

**Aset yang sudah ada dan WAJIB dipakai ulang, bukan dibangun ulang:**
- `resources/js/otp-input.js` — Alpine component `otpInput()` untuk 6 kotak digit auto-advance + auto-focus + paste, sudah terdaftar di `app.js` (`Alpine.data('otpInput', otpInput)`). Redesign visual saja, JS-nya dipakai apa adanya.
- `App\Services\OtpService` + `App\Models\VerifikasiEmailOtp` (kolom `email`, `kode_otp`, `expires_at`, `verified_at`, timestamps standar) — dipakai ulang untuk kirim/verifikasi/kirim-ulang, TIDAK diubah strukturnya.
- `App\Http\Controllers\Portal\Auth\{RegisteredAkunController,VerifikasiOtpController,AuthenticatedSessionController,PasswordResetLinkController,NewPasswordController}` — dimodifikasi in-place, bukan diganti dengan controller baru.

## 3. Lingkup

### 3.1 Perubahan Skema

Migration baru: tambah kolom `no_hp_wa` (string, **nullable** di level DB — supaya tidak memaksa migrasi data lama — validasi "wajib diisi" cukup di level `RegisteredAkunController::store()`'s form request) ke tabel `akun_pendaftar`. Tambahkan ke `$fillable` model `AkunPendaftar`.

### 3.2 Aturan Password

Di `RegisteredAkunController::store()` (dan `NewPasswordController::store()` untuk reset password, supaya konsisten), ganti `Password::defaults()` jadi eksplisit:
```php
Password::min(8)->letters()->mixedCase()->numbers()
```
(minimal 8 karakter, harus ada huruf, kombinasi besar-kecil, dan angka — tanpa mewajibkan simbol khusus, sesuai keputusan yang sudah dikunci).

### 3.3 Route `spmb.register`

Route baru di `routes/spmb.php`, mengarah ke controller yang sama persis dengan `portal.register` (tidak ada controller/logic baru — cuma pendaftaran route kedua dengan nama berbeda). Dibungkus middleware `guest:portal` yang sama seperti `portal.register`, supaya pengunjung yang sudah login tidak bisa mengakses form register lagi (konsisten dengan perilaku existing):
```php
Route::middleware('guest:portal')->group(function () {
    Route::get('register', [RegisteredAkunController::class, 'create'])->name('register');
    Route::post('register', [RegisteredAkunController::class, 'store'])->middleware('throttle:6,1');
});
```
Begitu route ini terdaftar, `Route::has('spmb.register')` di `PortalController::daftarJalur()` (Sub-project 1, kode tidak diubah) otomatis bernilai `true` dan tombol "Daftar Jalur Ini" langsung redirect ke sini alih-alih flash "segera hadir".

`routes/portal.php`'s `portal.register` TETAP ada apa adanya (tidak dihapus/di-rename) — dua nama route menunjuk ke controller action yang sama, konsisten dengan keputusan "tambah route baru, jangan ganti nama yang sudah ada".

### 3.4 Halaman Register (`GET`/`POST spmb.register` atau `portal.register`)

Layout: `<x-layouts.portal-auth>` dengan `<x-portal-auth-top>` (link kanan: "Sudah punya akun? Masuk" → `portal.login`).

Struktur 2 kolom di layar besar (form kiri `flex-1`/`max-w-[480px]`, sidebar kanan `sticky`), kolaps ke 1 kolom (form dulu, sidebar di bawah) di layar kecil — sama seperti pola yang sudah disepakati untuk halaman ini sebelumnya:

**Kolom form:**
1. Judul "Buat Akun Pendaftar" + subjudul.
2. **Chip konteks** (kondisional — hanya tampil kalau `session('spmb_pilihan.lembaga_id')` ada): ikon lembaga + "Mendaftar untuk" + "[Nama Lembaga] — Jalur [Nama Jalur]" + link "Ganti" (kembali ke `spmb.index` lembaga terkait). Kalau session kosong (akses langsung tanpa lewat "Daftar Jalur Ini"), chip ini disembunyikan seluruhnya, form tetap berfungsi normal.
3. Field: Nama Lengkap (full width), Email + No. HP/WhatsApp (2 kolom, kolaps ke 1 kolom ≤480px), Kata Sandi (dengan meter kekuatan live — Alpine component baru yang menghitung kekuatan dari regex saat `@input`, 4-bar visual + label teks seperti mockup), Konfirmasi Kata Sandi.
4. Checkbox "Saya menyetujui Syarat & Ketentuan serta Kebijakan Privasi Pintera." — wajib dicentang (validasi `required`, `accepted`), link mengarah ke `#` placeholder untuk sekarang.
5. Tombol submit "Buat Akun".
6. Footer link "Sudah punya akun? Masuk di sini".

**Kolom sidebar "Jalur Lain yang Tersedia"** (kondisional — sama seperti chip, hanya tampil kalau ada `lembaga_id` di session): label lembaga, judul, deskripsi singkat. Daftar kartu jalur lain aktif di lembaga yang sama (query serupa `PortalController::index()`'s jalur list, tanpa filter gelombang-jalur restriction karena ini sekadar preview perbandingan, bukan aksi daftar): jalur yang sedang dipilih ditandai "Dipilih" (tag hijau + centang), jalur lain masing-masing punya harga (3 kondisi yang sama: nominal asli/Gratis/Menunggu Konfirmasi Admin) dan tombol "Pilih Jalur Ini" yang menulis ulang session (`spmb_pilihan.jalur_id`) lalu redirect balik ke halaman ini (refresh chip+sidebar dengan pilihan baru) — TIDAK ke halaman Detail Lembaga, supaya pengunjung tidak kehilangan progres form yang sudah diisi. Ini artinya perlu satu route/aksi kecil baru: `POST spmb/register/ganti-jalur` yang menulis session dan redirect balik.

### 3.5 Halaman Verifikasi OTP (`GET`/`POST portal.verifikasi-otp`)

Layout sama (`x-layouts.portal-auth`), top-bar link "Salah data? Kembali ke form daftar" → `portal.register`. Kartu tunggal center (`max-width` lebih kecil dari form register): ikon amplop, judul "Verifikasi Email Kamu" + email tujuan (dari `session('portal_register_email_pending')`), 6 kotak digit (reuse `x-data="otpInput()"`), tombol "Verifikasi & Masuk", footer "Salah alamat email? Ubah di sini" (→ `portal.register`).

**Kirim ulang OTP** (gap fungsional baru, diminta eksplisit di proposal awal): teks "Belum menerima kode? Kirim ulang dalam **00:38**" — countdown 60 detik dihitung dari `created_at` baris `VerifikasiEmailOtp` terbaru untuk email yang sama (bukan dari `expires_at` yang 10 menit — cooldown kirim-ulang beda dari masa berlaku kode). Setelah countdown habis, teks berubah jadi link aktif "Kirim ulang kode" yang men-submit ke route baru:
```php
Route::post('verifikasi-otp/kirim-ulang', [VerifikasiOtpController::class, 'kirimUlang'])
    ->middleware('throttle:3,1')->name('verifikasi-otp.kirim-ulang');
```
`VerifikasiOtpController::kirimUlang()`: ambil email dari session, panggil `$otpService->kirim($email)` lagi (method ini sudah menghapus baris OTP lama yang belum diverifikasi sebelum membuat yang baru — jadi tidak ada duplikasi), redirect balik ke halaman OTP dengan flash `status`.

### 3.6 Halaman Masuk (`GET`/`POST portal.login`)

Layout sama, top-bar link "Belum punya akun? Daftar Akun" → `spmb.welcome` (konsisten dengan navbar Sub-project 1, bukan langsung ke register — karena akun baru harus mulai dari pilih lembaga/jalur, prinsip yang sama seperti navbar). Kartu tunggal center: field Email + Kata Sandi, baris "Ingat saya" (checkbox) + "Lupa kata sandi?" (→ `portal.password.request`), tombol "Masuk", footer "Belum punya akun? Daftar di sini" (→ `spmb.welcome`).

**Konfirmasi eksplisit: layout kartu tunggal center, BUKAN split-screen** — menyimpang secara sadar dari pola Sign In split-screen yang disebutkan spec redesign TailAdmin (`docs/superpowers/specs/2026-07-17-redesign-ui-tailadmin-design.md` §4), demi konsistensi visual dengan Register/OTP dan karena sudah divalidasi lewat mockup. Dicatat di sini supaya tidak dianggap terlewat saat spec itu dibaca ulang nanti.

### 3.7 Halaman Lupa Password & Reset Password

Redesign visual saja (layout `x-layouts.portal-auth`, kartu tunggal center, token sama), logika `PasswordResetLinkController`/`NewPasswordController` tidak diubah kecuali reset-password ikut memakai aturan strong-password yang sama (§3.2) dan meter kekuatan yang sama dengan register.

## 4. Non-Tujuan

- Halaman Syarat & Ketentuan/Kebijakan Privasi sungguhan — checkbox tetap ada, link `#` placeholder, halaman aslinya tugas terpisah.
- Penggunaan `spmb_pilihan.*` untuk membuat baris `Pendaftaran` atau menentukan redirect awal (langsung ke wizard vs pilih jalur) setelah login — itu tanggung jawab Sub-project 3 (Dashboard & Wizard Ter-otentikasi), bukan sub-project ini. Sub-project 2 hanya MEMBACA session itu untuk keperluan tampilan (chip + sidebar), tidak menulis ke tabel `Pendaftaran`.
- Dashboard, wizard, dan semua yang mengikutinya — Sub-project 3.
- Pembayaran biaya pendaftaran & info jadwal tes — Sub-project 4.
- Redesign visual `CekStatusController` — sudah dikonfirmasi sebagai tugas terpisah sejak Sub-project 1.
- Perubahan struktur tabel `verifikasi_email_otp` atau logika inti `OtpService` — dipakai ulang apa adanya.
- Login/registrasi via provider eksternal (Google, dll) — tidak diminta, tidak dalam scope.

## 5. Rencana Pengujian

- **Register**: berhasil dengan session (`spmb_pilihan.*` terisi → chip & sidebar tampil benar) dan tanpa session (chip & sidebar disembunyikan, form tetap jalan); gagal kalau email sudah terdaftar; gagal kalau password tidak memenuhi aturan strong-password (kurang panjang, tanpa huruf besar/kecil, tanpa angka — masing-masing kondisi); gagal kalau checkbox T&C tidak dicentang; kolom `no_hp_wa` tersimpan dengan benar; tombol "Pilih Jalur Ini" di sidebar menulis ulang session dan me-refresh chip.
- **Verifikasi OTP**: kode benar → akun terverifikasi + login otomatis + redirect dashboard + `Pendaftaran` lama (kalau ada, dari alur lama berbasis email) ikut terhubung ke akun (perilaku existing, dites ulang pasca redesign); kode salah/kedaluwarsa → pesan error, tidak login; kirim-ulang → baris OTP lama terhapus, baris baru dibuat, throttle `3,1` mencegah spam.
- **Masuk**: kredensial benar & email terverifikasi → login sukses; kredensial salah → error; email belum terverifikasi → OTP baru terkirim otomatis + redirect ke halaman verifikasi (perilaku existing, dites ulang).
- **Integrasi lintas sub-project**: `Route::has('spmb.register')` bernilai `true` setelah route baru terdaftar; test feature baru di Sub-project 1's `JalurDaftarActionTest` (atau test baru di sub-project ini) yang membuktikan `daftarJalur()` benar-benar redirect ke `spmb.register` sekarang, bukan lagi flash "segera hadir".
- **Reset password**: aturan strong-password yang sama berlaku di halaman ini juga.
- **Responsivitas**: form register 2-kolom kolaps ke 1 kolom, field Email+No.HP kolaps ke 1 kolom di layar sangat kecil — dicek manual di beberapa lebar viewport seperti sub-project sebelumnya.
