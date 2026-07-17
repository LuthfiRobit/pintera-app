# Redesign UI/UX (arah TailAdmin) — Design Spec

**Tanggal:** 2026-07-17
**Status:** Fondasi (token + komponen bersama) selesai diimplementasi — lihat docs/superpowers/plans/2026-07-17-redesign-ui-foundation.md. Rollout per halaman menyusul di plan terpisah.
**Referensi visual:** https://claude.ai/code/artifact/3684b529-993e-4198-b528-4ec290ba86d9

## 1. Latar Belakang & Tujuan

Desain visual saat ini (`tailwind.config.js`: warna `ink`/`paper`/`slate`/`brass`, font Plus Jakarta Sans/Inter/IBM Plex Mono, sidebar navy gelap) di-reset total, bukan diiterasi. Setelah eksplorasi 3 arah orisinal (Otoritas & Kepercayaan / Pendidikan Hangat / Akademik Modern), pemilik produk memilih meniru pola visual **TailAdmin** (`demo.tailadmin.com`) sebagai acuan konkret untuk panel admin, dengan portal SPMB publik memakai palet terpisah supaya dua konteks pengguna (staf internal vs. calon wali murid) terasa berbeda tanpa memutus keluarga tipografi.

Semua token warna diambil langsung dari `demo.tailadmin.com/style.css` (bukan tebakan) — skala `gray`/`success`/`error`/`warning` mengikuti konvensi Untitled UI yang dipakai TailAdmin. Font `Outfit` juga dikonfirmasi dari `--font-outfit` di CSS mereka.

## 2. Lingkup

**Termasuk (disepakati lewat mockup di artifact):**
- Design token: warna, tipografi, radius, shadow — dua varian (admin indigo, portal navy).
- Pola layout & komponen: sidebar, topbar, dashboard (stat card/gauge/chart/tab), notifikasi & profil dropdown, data table (filter card + kolom Aksi), form layout (plain & icon input), 6 layar autentikasi (admin: sign in + reset password; portal: sign in, sign up, verifikasi OTP, reset password).
- Berlaku untuk **seluruh area**: panel admin internal, portal publik SPMB, dan portal akun pendaftar/wali murid (memakai palet navy yang sama dengan portal SPMB, karena satu konteks pengguna).

**Tidak termasuk (keputusan terpisah, di luar spec ini):**
- Mode gelap — topbar admin punya toggle mode gelap di mockup, tapi baru dirancang untuk mode terang. Token dark-mode disusun saat implementasi per komponen, bukan di sini.
- Implementasi kode (Blade/Tailwind/Alpine) — menyusul di writing-plans.
- Fitur backend yang *tersirat* dari mockup tapi belum ada datanya:
  - Notification bell dengan isi nyata → perlu tabel notifikasi + trigger event.
  - "Menyimpan draf..." (autosave) di formulir SPMB → perlu endpoint penyimpanan draf per langkah.
  - Progress "X dari 6 Kolom" / progress bar kelengkapan form → perlu logic hitung persentase.
  
  Ketiganya murni ilustrasi pola UI di mockup; apakah benar-benar dibangun adalah keputusan produk terpisah, bukan bagian otomatis dari redesign visual ini.

## 3. Design Tokens

### 3.1 Admin panel (indigo) — dipakai di seluruh `resources/views/admin/**`

```
--brand-50:  #ECF3FF   --brand-100: #DDE9FF   --brand-300: #9CB9FF
--brand-500: #465FFF   (primary)              --brand-600: #3641F5 (hover)

--gray-50:  #F9FAFB  --gray-100: #F2F4F7  --gray-200: #E4E7EC  --gray-300: #D0D5DD
--gray-400: #98A2B3  --gray-500: #667085  --gray-600: #475467  --gray-700: #344054
--gray-800: #1D2939  --gray-900: #101828

--success-50: #ECFDF3  --success-500: #12B76A  --success-600: #039855  --success-700: #027A48
--error-50:   #FEF3F2  --error-500:   #F04438  --error-600: #D92D20  --error-700: #B42318
--warning-50: #FFFAEB  --warning-500: #F79009  --warning-600: #DC6803  --warning-700: #B54708

--shadow-card: 0px 1px 3px 0px rgba(16,24,40,.10)
--shadow-pop:  0 12px 28px rgba(16,24,40,.16)
--radius: input/button 8px (rounded-lg), card 16px (rounded-2xl), pill 999px
```

Font: **Outfit** (400/500/600/700), satu keluarga untuk semua — heading dan body sama-sama Outfit, dibedakan lewat weight.

### 3.2 Portal publik (navy) — SPMB & portal akun pendaftar/wali murid

```
--rg-primary: #1E3A5F     --rg-primary-tint: #E7EEF5   --rg-primary-hover: #16324F
--rg-bg: #F4F5F9          --rg-card: #FFFFFF
--rg-ink: #101828         --rg-ink-soft: #667085   --rg-border: #E4E7EC
--rg-info-bg: #EAF4FC     --rg-info-ink: #1D4ED8   --rg-info-icon: #2F86D6
--rg-required: #F04438    --rg-track: #E7E9F0
```

`--rg-primary-hover` (`portal-600` di Tailwind config) **bukan nilai dari `demo.tailadmin.com`** — TailAdmin tidak punya palet navy sama sekali (portal publik sengaja beda dari indigo admin, lihat §3.1). Ini warna hover/pressed yang dibuat manual sebagai versi lebih gelap dari `--rg-primary`, mengikuti pola yang sama seperti `brand-500`→`brand-600` di §3.1.

Font tetap **Outfit** — satu keluarga tipografi lintas admin & portal, hanya token warna primary yang beda (indigo vs navy), supaya sistemnya tetap terasa satu produk.

Badge status (`Lunas`/`Dicicil`/`Belum Bayar`, dsb) memakai skala `success`/`warning`/`error` di atas — **sama di admin maupun portal**, tidak ikut warna primary masing-masing (semantic color terpisah dari brand color).

## 4. Pola Layout & Komponen

**Sidebar** — putih (bukan gelap seperti desain lama), grup menu berlabel kecil uppercase abu, item aktif: background `brand-50`, teks `brand-600`, ikon `brand-500`.

**Topbar** — tombol hamburger, search bar (placeholder + shortcut `⌘K`), toggle mode gelap, bell notifikasi (dot merah kalau ada yang baru), avatar + nama + chevron.

**Dashboard** — baris stat card (ikon abu di kotak rounded + label + angka besar + pill tren hijau/merah) berdampingan dengan gauge card (radial progress + ringkasan target), lalu chart card (bar chart) dan tab card (filter Semua/Menunggu/Selesai + date-range button).

**Notifikasi & profil** — dropdown popover rounded-2xl, item notifikasi = avatar + status dot + teks + meta waktu; dropdown profil = nama/email di header, item beraksi (ikon + label), item destruktif (`Keluar`) dipisah divider di bawah.

**Data table (index page)** — pola wajib untuk semua halaman index:
1. **Filter card** di atas card tabel — field bergaya *searchable select* (border rounded, ikon kaca pembesar, chevron kanan), bukan native `<select>` biasa.
2. **Kolom Aksi di paling kiri** (bukan kanan) — supaya tidak perlu scroll horizontal saat kolom banyak. Isinya tombol pill indigo kecil (ikon gear + "Aksi" + chevron).
3. Dropdown menu Aksi: dikelompokkan — lihat/edit di atas, aksi proses di tengah, aksi destruktif (merah) dipisah divider paling bawah.
4. **Catatan implementasi:** kolom Aksi harus `position: sticky; left: 0` saat tabel di-scroll horizontal, supaya tombolnya tetap terlihat — belum di-mockup-kan (mockup statis), tapi wajib di implementasi nyata.
5. Pagination: nomor halaman aktif = pill `brand-500` solid, sisanya outline abu.

**Form layout** — dua pola: form polos (label + input rounded-lg) dan form dengan ikon prefix di dalam input (untuk field seperti nama/email/password), keduanya dengan tombol submit full-width `brand-500`.

**Autentikasi** — pola split-screen (form kiri, panel merek kanan bergambar pattern titik + tagline, disembunyikan di mobile) untuk layar utama (Sign In); layar sekunder (Reset Password, Sign Up, Verifikasi OTP) memakai card mandiri tanpa mengulang panel merek. Admin tidak punya halaman Sign Up (akun staf dibuat oleh Yayasan lewat form "Tambah Pengguna", bukan pendaftaran mandiri) — portal SPMB punya keempatnya karena semua alur itu nyata dipakai calon wali murid.

## 5. Cakupan Halaman

Token & pola di atas berlaku lintas seluruh `resources/views/admin/**` (indigo) dan `resources/views/spmb/**` + `resources/views/portal/**` (navy) — termasuk halaman yang belum sempat di-mockup-kan satu-satu (mis. Lembaga, Guru, Gelombang PPDB, Roles). Halaman yang sudah dicontohkan di artifact (Dashboard, Tagihan/data table, Form Layout, 6 layar auth) jadi acuan pola untuk seluruh halaman sejenis, bukan daftar lengkap yang perlu direplikasi satu-satu di spec ini.

## 6. Langkah Berikutnya

Spec ini murni arah visual (token + pola komponen). Urutan & rincian penerapan ke kode Blade/Tailwind (`tailwind.config.js`, komponen `x-*` yang ada, halaman per halaman) disusun lewat `writing-plans` setelah spec ini di-review.
