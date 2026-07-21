# Restrukturisasi Pendaftaran Calon Siswa — Sub-project 3b: Wizard Ter-otentikasi — Design Spec

**Tanggal:** 2026-07-21
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Tujuan

Ini adalah sub-project 3b dari inisiatif "restrukturisasi alur pendaftaran calon siswa" — pecahan dari Sub-project 3 (Dashboard & Wizard Ter-otentikasi) yang aslinya satu, dipecah jadi dua karena scope-nya besar: **3a (Dashboard)** dan **3b (Wizard Ter-otentikasi, spec ini)**. Dikerjakan lebih dulu daripada 3a karena route baru yang dibangun di sini menjadi target redirect dashboard di 3a.

Sub-project 1 & 2 (sudah shipped) menutup langkah pilih-lembaga/jalur dan registrasi-akun/verifikasi. Sub-project ini menutup langkah berikutnya: 4 tahap wizard pengisian pendaftaran (Data Diri → Formulir Tambahan → Dokumen → Review) yang **saat ini masih sepenuhnya anonim/berbasis session**, tidak terhubung ke akun yang sudah login sama sekali. Tujuannya: pindahkan wizard ke bawah autentikasi `auth:portal`, hapus langkah verifikasi-email duplikat yang sudah tidak perlu (karena akun sudah terverifikasi saat registrasi), dan hubungkan `Pendaftaran` ke akun secara langsung sejak dibuat — bukan lewat pencocokan string email pasca-fakta seperti sekarang.

**Temuan penting saat eksplorasi kode:**
- `VerifikasiEmailController` (OTP khusus wizard, terpisah dari OTP registrasi Sub-project 2) jadi sepenuhnya redundan.
- `ReviewSubmitController::submit()` saat ini membuat `Pendaftaran` dulu, baru MENCARI `AkunPendaftar` berdasarkan `email_pendaftaran` string SETELAH itu untuk mengisi `akun_pendaftar_id` — pola "terhubung longgar" yang jadi salah satu alasan utama seluruh inisiatif ini dimulai.
- `DataDiriController`'s pengecekan anti-hijack NIK (`emailCocokDenganCalonMurid`) membandingkan email session vs `Pendaftaran.email_pendaftaran` — bisa jadi lebih akurat kalau dibandingkan lewat `akun_pendaftar_id`.
- **Field wizard (nama_lengkap, dst di Data Diri) adalah data CALON MURID, bukan data WALI di akun** — dua orang yang berpotensi berbeda (untuk jenjang SD ke bawah biasanya orang tua yang mendaftarkan, untuk SMP ke atas bisa jadi calon siswa sendiri yang mendaftar). Karena sistem tidak punya cara mengetahui mana yang terjadi tanpa menambah field baru, konsep "locked field" dari mockup (Nama Lengkap/No.HP dikunci dari akun) **tidak diterapkan** — diganti strip identitas akun yang netral.
- `PendaftaranWizardSession` (key `spmb_wizard.{lembagaId}.{jalurId}`) sudah cukup dan tidak perlu diubah strukturnya — konteksnya sekarang datang dari session `spmb_pilihan.*` (ditulis Sub-project 1), bukan lagi dari parameter URL.

## 2. Referensi Visual

Mockup interaktif yang sama: **https://claude.ai/code/artifact/a1987ae5-0050-440d-af88-08cfe01415af** — layar 6 (Dashboard, untuk pola navbar authenticated) dan layar 7 (Wizard: Data Diri) adalah referensi utama. Layar 7 cuma mendesain penuh 1 dari 4 tahap; 3 tahap lain (Formulir Tambahan, Dokumen, Review) di sub-project ini **didesain penuh dan konsisten**, bukan sekadar deskripsi naratif seperti di mockup aslinya — mengikuti shell yang sama (stepper, sidebar, kartu) dan pola input/kartu yang sudah ditetapkan di Sub-project 1 & 2.

Token visual identik dengan sub-project sebelumnya (`portal-50/500/600`, `gray-*`, `success-*`/`warning-*`/`error-*`, font Outfit). **Konsep "locked field" (Nama Lengkap/No.HP dikunci dari akun) dari mockup TIDAK diterapkan** — diganti strip identitas akun netral (lihat §3.1).

## 3. Lingkup

### 3.1 Shell Wizard (dipakai 4 tahap + halaman Berhasil)

- **Layout baru** `<x-layouts.portal-wizard>` — navbar authenticated (logo, menu Dashboard/Riwayat/Bantuan, avatar+nama akun sebagai trigger dropdown Keluar), reuse `<x-portal-footer>`.
- **Strip identitas akun**: baris kecil di bawah navbar — "Mendaftar sebagai: [nama akun] · [email]", dari `Auth::guard('portal')->user()`. Bukan locked-field, murni informasi konteks, tidak menggantikan/mengunci field manapun di form Data Diri.
- **Komponen `<x-portal-wizard-stepper current="data-diri|formulir-tambahan|dokumen|review">`** — 4 tahap horizontal, state `done`/`active`/upcoming, scroll-safe di layar sempit (pola yang sama sudah divalidasi di mockup: `justify-content: safe center`, breakpoint `≤560px` ke `flex-start`).
- **Komponen `<x-portal-wizard-sidebar>`** — kartu "Pilihan Jalur" (nama lembaga+jalur dari session, biaya pendaftaran) + kartu tip "Butuh Bantuan?" (catatan bahwa data tersimpan otomatis di session selama proses berjalan, link balik ke dashboard).
- **Layout 2 kolom** (konten utama + sidebar), kolaps 1 kolom di layar sempit — breakpoint persis sesuai mockup (`≤900px`, dicek ulang saat menulis plan, bukan ditebak).

### 3.2 Resolusi Konteks Wizard (pengganti route-model-binding lama)

Trait/helper baru (mis. `ResolvesWizardContext`) dipakai di kelima controller wizard (`DataDiriController`, `FormulirTambahanController`, `UploadDokumenController`, `ReviewSubmitController`, ditambah endpoint Berhasil): baca `session('spmb_pilihan.lembaga_id')`/`session('spmb_pilihan.jalur_id')`, resolve `Lembaga`+`JalurPpdb`, validasi jalur memang milik lembaga itu (reuse pola `assertJalurBelongsToLembaga` yang sudah ada di `ResolvesSpmbTenant`, bukan reimplementasi). Kalau session kosong atau resolusi gagal, redirect ke `/portal/dashboard` (yang lalu memutuskan langkah berikutnya sesuai spec 3a — termasuk redirect lanjutan ke `/spmb` kalau memang tidak ada konteks sama sekali).

### 3.3 Route Baru (menggantikan seluruhnya route lama di `routes/spmb.php`)

Route baru **dinest di dalam** grup `Route::prefix('portal')->name('portal.')->group(...)` yang sudah ada di `routes/portal.php` (bukan grup baru di level atas) — supaya nama route final jadi `portal.wizard.*` dan URL jadi `/portal/wizard/*`, konsisten dengan seluruh route portal lain yang sudah ada:

```php
// di dalam Route::prefix('portal')->name('portal.')->group(function () { ... }) yang sudah ada:
Route::prefix('wizard')->name('wizard.')->middleware(['auth:portal', 'portal.verified'])->group(function () {
    Route::get('data-diri', [DataDiriController::class, 'create'])->name('data-diri');
    Route::post('data-diri/cek-nik', [DataDiriController::class, 'cekNik'])->name('data-diri.cek-nik');
    Route::post('data-diri', [DataDiriController::class, 'store'])->name('data-diri.store');
    Route::get('formulir-tambahan', [FormulirTambahanController::class, 'create'])->name('formulir-tambahan');
    Route::post('formulir-tambahan', [FormulirTambahanController::class, 'store'])->name('formulir-tambahan.store');
    Route::get('dokumen', [UploadDokumenController::class, 'create'])->name('dokumen');
    Route::post('dokumen', [UploadDokumenController::class, 'store'])->name('dokumen.store');
    Route::get('review', [ReviewSubmitController::class, 'show'])->name('review');
    Route::post('submit', [ReviewSubmitController::class, 'submit'])->middleware('throttle:10,1')->name('submit');
    Route::get('berhasil/{pendaftaran}', [ReviewSubmitController::class, 'berhasil'])->name('berhasil');
});
```

Nama route lengkap hasilnya: `portal.wizard.data-diri`, `portal.wizard.data-diri.cek-nik`, `portal.wizard.data-diri.store`, dst — dipakai konsisten di §3.1-3.7 dan rencana pengujian (disebut "wizard.*" secara singkat di bagian lain spec ini, maksudnya selalu `portal.wizard.*`).

**Route lama di `routes/spmb.php` dihapus sepenuhnya** (bukan dibiarkan sebagai kode mati): `spmb.mulai`, `spmb.mulai.store`, `spmb.verifikasi-otp`(+`.store`), `spmb.data-diri`(+`.cek-nik`/`.store`), `spmb.formulir-tambahan`(+`.store`), `spmb.dokumen`(+`.store`), `spmb.review`, `spmb.submit`, `spmb.berhasil`. `VerifikasiEmailController` dan view `spmb/verifikasi-email.blade.php`/`spmb/verifikasi-otp.blade.php` (versi lama, bukan punya Sub-project 2 yang di `portal.auth.verifikasi-otp`) dihapus. `PendaftaranWizardSession` TIDAK dihapus/diubah, dipakai ulang apa adanya.

### 3.4 Data Diri (`DataDiriController`)

Field & validasi TIDAK berubah (semua tentang calon murid: NIK, data pribadi, alamat, keluarga — diisi manual, tidak ada locking dari akun). Yang berubah:
- `cekNik`/`store`: pengecekan anti-hijack NIK diganti dari cocok-string-email jadi cocok `akun_pendaftar_id`:
  ```php
  Pendaftaran::where('calon_murid_id', $calonMurid->id)
      ->where('akun_pendaftar_id', Auth::guard('portal')->user()->id)
      ->exists();
  ```
  NIK yang sudah pernah didaftarkan boleh dipakai lagi HANYA kalau `Pendaftaran` sebelumnya untuk NIK itu memang tercatat milik akun yang sedang login. Kalau tidak ada `Pendaftaran` sama sekali untuk NIK itu (murni CalonMurid baru dari cabang lembaga lain, dst — kasus yang sudah ditangani existing code), tetap boleh lanjut seperti sekarang.
- Sisa logic (`CalonMurid::findByNik`, `updateOrCreate` alamat/keluarga saat submit) tidak berubah.

### 3.5 Formulir Tambahan & Dokumen (`FormulirTambahanController`, `UploadDokumenController`)

Logic inti (field dinamis per `FormulirField`/`DokumenSyaratPpdb`, validasi upload tipe+ukuran) **tidak berubah** — hanya konteks lembaga/jalur sekarang dari §3.2, bukan dari parameter URL. Visual: form field mengikuti pola input ikon+label Sub-project 2; upload dokumen jadi baris per syarat dengan indikator status (sudah/belum terupload, ikon+teks bukan warna saja).

### 3.6 Review & Submit (`ReviewSubmitController`)

- `show()`: ringkasan read-only semua data yang sudah diisi (data diri, alamat, keluarga, jawaban formulir, dokumen) — logic pengecekan sesi-belum-lengkap (`redirectJikaSesiBelumLengkap`) dipertahankan apa adanya.
- `submit()`: `Pendaftaran::create()` langsung menyertakan:
  ```php
  'akun_pendaftar_id' => Auth::guard('portal')->user()->id,
  'email_pendaftaran' => Auth::guard('portal')->user()->email,
  ```
  menggantikan logic lookup-by-email pasca-fakta yang ada sekarang (blok kode yang mencari `AkunPendaftar::where('email', $pendaftaran->email_pendaftaran)->whereNotNull('email_verified_at')->first()` dihapus, karena hubungan sudah pasti sejak `create()`). Sisa logic (retry kode pendaftaran, pemindahan dokumen ke lokasi final, `TagihanGenerator::generate()`, email `PendaftaranBerhasilMail`, `$wizardSession->clear()`) **tidak berubah**.
- Perilaku gelombang-tertutup-di-tengah-wizard (submit gagal 404 kalau `resolveGelombangAktifUntukJalur` tidak menemukan gelombang aktif saat submit) **dipertahankan apa adanya** — bukan perbaikan/perubahan baru di sub-project ini.
- Redirect setelah sukses: ke halaman Berhasil (§3.7), bukan langsung ke dashboard.

### 3.7 Halaman Berhasil

Halaman konfirmasi navy tersendiri (bukan langsung redirect ke dashboard) — kode pendaftaran, ringkasan singkat, tombol "Lihat Dashboard". Akses dicek lewat kepemilikan langsung, bukan lagi query-string email:
```php
abort_unless($pendaftaran->akun_pendaftar_id === Auth::guard('portal')->user()->id, 404);
```

## 4. Non-Tujuan

- Halaman Dashboard & logic percabangannya (kapan redirect ke wizard vs tampilkan list vs redirect ke Welcome) — itu Sub-project 3a, spec terpisah. Sub-project ini hanya menyediakan route/halaman TUJUAN dari redirect dashboard, tidak membangun dashboard itu sendiri.
- Pembayaran biaya pendaftaran & info jadwal tes — Sub-project 4.
- Redesign visual `CekStatusController` — sudah dikonfirmasi sebagai tugas terpisah sejak Sub-project 1.
- Perbaikan perilaku gelombang-tertutup-saat-submit (404) — dipertahankan apa adanya, bukan diperhalus di sini.
- Field/UI baru untuk membedakan "wali mendaftarkan anak" vs "calon siswa mendaftar sendiri" — sengaja tidak dibangun (lihat §1), diganti strip identitas netral.
- Perubahan struktur `PendaftaranWizardSession`, `KodePendaftaranGenerator`, `TagihanGenerator`, `CalonMurid`/`AlamatCalonMurid`/`KeluargaCalonMurid` dan model terkait lainnya — dipakai ulang apa adanya.

## 5. Rencana Pengujian

- **Konteks wizard**: setiap tahap redirect ke dashboard kalau session `spmb_pilihan.*` kosong/tidak valid; jalur di session yang tidak cocok dengan lembaga di session ditolak (reuse `assertJalurBelongsToLembaga`).
- **Data Diri**: NIK yang sudah terdaftar dengan `Pendaftaran` milik akun YANG SAMA boleh lanjut; NIK yang sudah terdaftar dengan `Pendaftaran` milik akun LAIN diblokir dengan pesan yang sama seperti sekarang; NIK baru (belum pernah terdaftar) boleh lanjut seperti biasa.
- **Formulir Tambahan & Dokumen**: perilaku existing (field dinamis, validasi upload) tetap diuji ulang terhadap route baru, tidak ada regresi.
- **Review & Submit**: `Pendaftaran` yang dibuat langsung punya `akun_pendaftar_id` terisi benar (bukan null menunggu proses pasca-fakta); email pendaftaran terisi dari akun yang login; retry-kode dan rollback dokumen pada kegagalan tetap berfungsi seperti sekarang; submit gagal 404 kalau gelombang sudah tertutup (regresi-check, bukan fitur baru).
- **Halaman Berhasil**: akun lain (bukan pemilik) yang mencoba akses `wizard.berhasil` untuk `Pendaftaran` yang bukan miliknya mendapat 404.
- **Route lama**: semua nama route lama (`spmb.mulai`, `spmb.data-diri`, dst) benar-benar tidak terdaftar lagi (`Route::has()` bernilai `false`) setelah sub-project ini — bukti bahwa penghapusan memang tuntas, bukan cuma ditambah yang baru.
- **Responsivitas**: shell wizard (stepper, layout 2-kolom) kolaps dengan benar di breakpoint yang sesuai mockup — dicek manual, dicatat.
