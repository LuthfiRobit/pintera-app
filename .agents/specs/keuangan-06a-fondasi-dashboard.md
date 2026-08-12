# Keuangan Sub-project 6a: Fondasi Portal Orang Tua & Dashboard

> Status: Disetujui — siap ke Implementation Plan. Sub-plan pertama dari 4 (6a/6b/6c/6d) yang memecah Sub-project 6 (Parent Dashboard & Kwitansi). Referensi desain lengkap (proses brainstorming): `docs/superpowers/specs/2026-08-12-keuangan-06a-fondasi-dashboard-design.md`.

## Konteks & Dependensi

Sub-project 6 (per `.agents/specs/keuangan-06-parent-dashboard-kwitansi.md` dan desain asli `docs/superpowers/specs/2026-08-10-keuangan-06-parent-dashboard-kwitansi-design.md`) adalah sub-project terakhir Modul Keuangan Sekolah Dinamis — menyatukan backend Sub-project 1-5 ke dalam satu pengalaman UI untuk orang tua. Karena permukaan UI-nya jauh lebih luas dari sub-project manapun sebelumnya (dashboard, rekap tagihan, checkout 4-channel, riwayat+kwitansi PDF, pengaturan notifikasi, child switcher, admin upload logo), sub-project ini dipecah jadi 4 sub-plan berurutan, mengikuti pola yang sudah terbukti di Sub-project 2b (2b-1/2b-2/2b-3):

- **6a (dokumen ini)**: Fondasi — auth/permission orang tua untuk Keuangan, layout, child switcher, dashboard utama + banner skip-alert + notifikasi.
- **6b**: Rekap tagihan aktif + checkout multi-channel (VA BRI, QRIS, wallet, transfer manual).
- **6c**: Riwayat transaksi + kwitansi PDF + admin upload logo yayasan.
- **6d**: Pengaturan preferensi notifikasi.

Bergantung pada Sub-project 1-5 (skema tagihan, Wallet & Auto-Allocation Engine, Payment Channels, Notifikasi) — semuanya sudah selesai & diverifikasi (lihat `.agents/logs/keuangan-03-wallet-auto-allocation.md`, `keuangan-04-payment-channels.md`, `keuangan-05-notifikasi.md`).

## Temuan Arsitektural Penting (ditemukan saat brainstorming, mengoreksi asumsi spec asli)

Spec asli mengasumsikan "portal orang tua" ada di namespace `Portal/*` (`/portal/keuangan/*`) dengan sesi login `OrangTua`. Investigasi kode langsung (bukan asumsi dari spec) menemukan ini **salah** — dan koreksinya menentukan seluruh arsitektur sub-project ini:

1. **`Portal/*` + guard `portal` = `AkunPendaftar`** (akun pendaftar PPDB, identitas PRA-pendaftaran) — bukan orang tua siswa aktif. `AkunPendaftar` hanya berelasi ke `Pendaftaran`, tidak ada jalur ke `Siswa`/`OrangTua`.
2. **`OrangTua` tidak punya login/password sendiri** — dia model `Notifiable` murni, terhubung ke `users.id` lewat `orang_tua.user_id` (FK unique).
3. **Preseden yang sudah jalan & terbukti**: role `orang_tua` sudah ada (Spatie Permission, guard `web` yang sama dengan staff/admin), dipakai modul Kasus/Pendampingan di `/kasus/*`. Orang tua login lewat halaman login staff biasa, memakai shell `<x-app-layout>` yang sama, menu di-gate oleh permission.
4. **Pola scoping data untuk orang tua sudah established** di `KasusController`: karena `orang_tua`-role `User` tidak punya `lembaga_id` sendiri (nullable, sering `null`), `TenantScope` default akan fail-closed (0 baris). Solusinya: `withoutGlobalScope(TenantScope::class)` eksplisit pada setiap query + otorisasi lewat **keanggotaan relasi** (`$user->orangTua?->siswa()->...`), BUKAN pencocokan `lembaga_id` seperti pola admin (mis. `ManualPaymentController`'s `siswaLembagaId()` dari Sub-project 05).

**Keputusan final**: Sub-project 6 dibangun di atas pola #3 dan #4 di atas — reuse infrastruktur yang sudah battle-tested, bukan membangun sistem auth Portal baru dari nol (yang mana eksplisit di luar scope spec asli: "tidak ada logic bisnis baru").

## Arsitektur & Routing (6a)

- **Guard**: `web` (bukan `portal`).
- **Identitas**: `User` dengan role `orang_tua`, terhubung ke profil `OrangTua` lewat `orang_tua.user_id`.
- **Permission baru**: `keuangan.akses` — satu permission tunggal meng-gate seluruh `/keuangan/*` (tidak dipecah per-aksi; tidak ada skenario realistis "boleh lihat tapi tidak boleh bayar tagihan sendiri"). Ditambahkan ke role `orang_tua` di `RoleSeeder.php`.
- **Routing**: `Route::middleware(['auth', 'verified', 'permission:keuangan.akses'])->prefix('keuangan')->name('keuangan.')->group(...)` di `routes/web.php`, sejajar dengan grup `kasus.*` yang sudah ada (bukan di `routes/admin.php`, bukan di `routes/portal.php`).
- **Layout**: reuse `<x-app-layout>` — TIDAK membuat layout Portal baru.
- **Akun uji browser verification**: `ortu.demo@permatakraksaan.sch.id` / password `password` (sudah ada di `OrangTuaKaryawanSeeder`).

## Komponen & Halaman (6a)

### Middleware & Session — Child Switcher
- Session key baru: `active_siswa_id`.
- Mekanisme: link GET `?switch_siswa={id}`, ditangkap middleware baru `ResolveActiveSiswa` (pola serupa `ResolveTenant`'s `active_lembaga_id`, tapi validasi keanggotaan lewat `$user->orangTua?->siswa()->whereKey($value)->exists()`, bukan lewat tabel `Lembaga`).
- Default saat belum ada session: `siswa_orang_tua.is_kontak_utama = true` untuk `OrangTua` user tsb; fallback anak pertama kalau tidak ada yang ditandai utama; kosong (redirect ke halaman "belum ada anak terdaftar") kalau `OrangTua` tidak punya `siswa()` sama sekali.
- Dropdown "Pilih Profil Anak" di navbar `<x-app-layout>` — **hanya render jika `auth()->user()->orangTua !== null && $user->orangTua->siswa()->count() > 1`**. Untuk 1 anak, tidak ada dropdown, `active_siswa_id` di-set otomatis ke satu-satunya anak tanpa UI switcher.

### `GET /keuangan` — Dashboard Utama
- **Card Saldo Wallet**: saldo (`wallet.balance`), nomor VA permanen (`wallet.va_number` — cek nama kolom sebenarnya saat implementasi, kemungkinan besar disimpan di `bri_virtual_accounts` dengan `va_type=WALLET_PERMANENT`, bukan langsung di tabel `wallets`), tombol "+ Top Up" yang LINK ke halaman checkout 6b (6a tidak mengimplementasikan form top-up itu sendiri).
- **Banner Skip Alert**: **query live**, dihitung ulang setiap load (bukan baca dari `notification_logs`) — cari tagihan `priority_score` tertinggi milik siswa aktif dengan `status IN ('belum_bayar','sebagian')` dan `wallet.balance < (net_amount - paid_amount)`. Tampilkan nominal kekurangan + tombol "Top-up Rp{selisih} Sekarang" (prefill nominal ke form top-up 6b). Query ini MEREPLIKASI logika prioritas `AutoAllocationEngine` (urutan `priority_score`, tie-break `jatuh_tempo`) tanpa menjalankan alokasi sungguhan — read-only.
- **Bell Notifikasi (topbar `<x-app-layout>`, dipakai SEMUA role)**: mengganti placeholder statis "Belum ada notifikasi" jadi fungsional. Data lewat service baru `NotificationFeedResolver` yang menggabungkan `$user->notifications` (untuk notifikasi yang menyasar `User` langsung — Kasus punya beberapa yang begini: admin, konselor, wali kelas) DAN `$user->orangTua?->notifications` (untuk notifikasi Finance yang selalu menyasar `OrangTua`), digabung dan diurutkan `created_at` desc, **dibatasi 10 terbaru** (angka tetap, tanpa paginasi — cukup untuk dropdown bell; halaman "lihat semua notifikasi" eksplisit di luar scope 6a). **Scope ini lebih besar dari sekadar Keuangan** — bell ini dipakai lintas modul (Kasus + Keuangan), tapi perbaikannya jadi bagian 6a karena dashboard Keuangan butuh "Notifikasi Terbaru" yang harusnya konsisten dengan bell yang sama, bukan sumber data terpisah.
- **Panel "Notifikasi Terbaru"** di body halaman dashboard: sumber data SAMA dengan bell (`NotificationFeedResolver`), difilter ke siswa aktif untuk notifikasi yang relevan ke anak tertentu (Finance notifications) — notifikasi non-Finance (mis. Kasus) tetap muncul apa adanya karena tidak terikat siswa tertentu bagi user ybs secara langsung.

## Data Flow

1. User login (`web` guard) → redirect `/dashboard` (default Laravel) atau langsung `/keuangan` kalau route login diarahkan sesuai role — detail exact redirect diputuskan di implementation plan, tidak mengubah flow login existing.
2. Middleware `ResolveActiveSiswa` (baru, terdaftar di grup route `/keuangan/*` saja, TIDAK global) resolve `active_siswa_id` dari session atau default, inject `Siswa $activeSiswa` ke request/view sebagai shared data (`View::share` atau service singleton per-request).
3. `DashboardController::index()` — semua query (`Wallet`, `Tagihan`, `notifications`) di-scope ke `$activeSiswa`, dengan `withoutGlobalScope(TenantScope::class)` eksplisit karena `$activeSiswa` bukan milik tenant si acting user.
4. View render `<x-app-layout>` dengan komponen dashboard baru (`resources/views/keuangan/dashboard.blade.php` atau serupa) + bell topbar yang sudah diupdate.

## Error Handling

- **`OrangTua` tanpa anak terdaftar** (`$user->orangTua->siswa()->count() === 0`): halaman dedicated minimal (bukan flash message di atas dashboard kosong) — `resources/views/keuangan/tanpa-anak.blade.php`, pesan jelas + kontak admin, tanpa card/banner/bell yang tidak relevan.
- **User dengan role `orang_tua` tapi TIDAK punya profil `OrangTua`** (`$user->orangTua === null` — data tidak konsisten): `abort(403)` dengan pesan jelas, dicatat log — skenario ini seharusnya tidak mungkin terjadi by design (role `orang_tua` seharusnya selalu dibuat berpasangan dengan `OrangTua`), tapi wajib di-guard defensif mengingat pola bug data-integrity yang sudah beberapa kali muncul di sesi ini.
- **`?switch_siswa={id}` dengan id yang BUKAN anak user ybs**: middleware diam-diam mengabaikan (tidak update session), TIDAK mengembalikan error — pola sama seperti `ResolveTenant`'s `active_lembaga_id` (silent ignore untuk id yang tidak valid/`Lembaga::whereKey($value)->exists()` gagal).
- **Wallet belum ter-create untuk siswa aktif** (seharusnya tidak mungkin — `CreateWalletForNewStudent` listener dari Sub-project 03 auto-create — tapi tetap di-null-safe di view, `$wallet?->balance ?? 0`).

## Catatan Domain Terkait (dari 6 open item Sub-project 05, relevan langsung ke 6a)

- **Item 2 (partial allocation tidak memicu notifikasi)** — dikonfirmasi sesuai scope, dashboard 6a TIDAK perlu menampilkan notifikasi khusus untuk pembayaran parsial; status `sebagian` cukup ditampilkan sebagai badge status tagihan biasa.
- **Item 1 (`PaymentAllocationService::allocate()` double-count `paid_amount` pada re-call)** — bug pre-existing di luar scope Sub-project 05, TIDAK diperbaiki di 6a juga (6a murni presentasi, tidak menyentuh `PaymentAllocationService`). Kalau dashboard menampilkan `paid_amount` yang sudah kadung salah akibat bug ini pada data lama, itu bug Sub-project 3, bukan bug 6a — dicatat sebagai risiko yang sudah diketahui, bukan hal baru untuk sub-project ini menutupnya.

## Testing Strategy

- **Feature test (Pest)**: standar — permission gate, scoping (siswa lain tidak bisa diakses), skip-alert query correctness, `NotificationFeedResolver` merge logic.
- **Browser verification (Playwright, WAJIB per-task, bukan langkah tambahan di akhir)**: reuse & extend tooling dari `scripts/manual-book-screenshots.mjs` (Playwright sudah terpasang) jadi script verifikasi INTERAKTIF — klik nyata, isi form, switch child, cek DOM state — bukan sekadar screenshot statis. Setiap task di implementation plan yang menyentuh Alpine/JS interaktif (child switcher dropdown, bell dropdown) WAJIB punya langkah verifikasi Playwright sendiri sebelum task ditandai selesai, mengikuti pelajaran dari blind spot Alpine JS di Sub-project 2b-1/2b-2/2b-3 (bug `@js()` di dalam `@click` yang lolos test HTTP karena Alpine gagal parsing, cuma ketahuan lewat browser sungguhan).
- Login sebagai `ortu.demo@permatakraksaan.sch.id` untuk semua verifikasi browser di 6a.

## Yang TIDAK Termasuk 6a

- Form/proses top-up dan checkout (6b).
- Rekap tagihan detail dengan multi-select (6b).
- Riwayat transaksi & kwitansi PDF (6c).
- Upload logo yayasan (6c).
- Toggle preferensi notifikasi (6d).
- Perubahan logic bisnis `PaymentAllocationService`/`AutoAllocationEngine`/`Wallet` (tetap dipakai apa adanya dari Sub-project 3).

## Ambiguitas Terselesaikan

- [x] Routing/guard portal orang tua → `web` guard + role `orang_tua`, BUKAN `Portal/*`/`AkunPendaftar` seperti asumsi spec asli.
- [x] Sumber data banner skip-alert → query live, bukan baca `notification_logs`.
- [x] Granularitas permission → satu `keuangan.akses`, bukan dipecah per-aksi.
- [x] Scope bell notifikasi topbar → diaktifkan penuh (lintas semua role, bukan cuma Keuangan).
- [x] Lokasi halaman pengaturan → sudah ada `profile.edit` (generic, semua role), dipakai untuk 6d nanti, bukan halaman baru.

## Ambiguitas Sisa (untuk implementation plan 6a)

- [ ] Nama kolom/tabel exact untuk `va_number` permanen wallet (kemungkinan besar di `bri_virtual_accounts` dengan `va_type='WALLET_PERMANENT'`, bukan kolom langsung di `wallets`) — verifikasi skema saat menulis plan.
- [ ] Redirect setelah login untuk role `orang_tua` — apakah default `/dashboard` di-override jadi `/keuangan` untuk role ini, atau dashboard umum tetap jadi landing page dengan link ke Keuangan — detail teknis, tidak memengaruhi arsitektur.
