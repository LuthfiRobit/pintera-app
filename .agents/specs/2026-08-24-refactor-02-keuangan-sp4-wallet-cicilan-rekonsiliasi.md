# Spec: Migrasi Domain Keuangan — Sub-project 4 (TERAKHIR): Wallet & Cicilan + Rekonsiliasi

**Tanggal:** 24 Agustus 2026
**Branch:** `refactor-v1`
**Terkait:** `.agents/specs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md`, `-sp2-alur-tagihan-inti.md`, `-sp3-pembayaran-gateway.md` (SEMUA SELESAI + direview mendalam), `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md`, `.agents/skills/laravel-feature-standard/SKILL.md`

## 1. Latar Belakang

SP1, SP2, SP3 sudah selesai dan direview mendalam. Review SP1 menemukan 1 celah HIGH (guard tenant-isolation hilang) + 1 pembalikan arsitektur tak terungkap. Review SP2 menemukan 1 deviasi namespace tak terungkap (`WaliMurid`). **Review SP3 BERSIH TOTAL** — tidak ada temuan HIGH/MEDIUM, bukti bahwa disiplin plan+kickoff yang rinci bekerja.

**SP4 ini adalah sub-project PENUTUP** — tidak ada sub-project berikutnya untuk menunda apa pun. Semua yang tersisa di luar `app/Domains/Keuangan/` yang benar-benar milik domain Keuangan WAJIB pindah sekarang.

Eksplorasi ulang (24 Agustus 2026, audit menyeluruh) menemukan:
1. **Cakupan jelas dan sudah sesuai perkiraan**: `Wallet`, `WalletMutasi`, `Cicilan`, `AutoAllocationEngine`, `SkipAlertResolver`, `PaymentAllocationService`, `Keuangan\DashboardController`.
2. **Audit final bersih** — tidak ada sisa kode Keuangan lain yang tertinggal di luar rencana. `TagihanGenerator`/`Admin\TagihanSusulanController` dikonfirmasi memang milik domain PPDB. `Keuangan\NotifikasiController` dikonfirmasi generic, bukan scope Keuangan. **Koreksi asumsi lama**: `Portal\Keuangan\RiwayatController`/`CheckoutController` yang sempat disebut "seharusnya sudah pindah di SP3" ternyata memang SUDAH benar di `Portal\Keuangan\` sejak awal SP3 — tidak ada yang salah tempat.
3. **🐛 Bug nyata ditemukan, sudah hidup di production sejak SP3**: `app/Models/WalletMutasi.php` baris 32 — relasi `pembayaran(): belongsTo(Pembayaran::class)` tanpa `use` statement, referensi implisit same-namespace yang RUSAK sejak `Pembayaran` pindah ke `Domains\Keuangan\Models` di SP3 (commit `830d637`). Tidak ada test yang menyentuh relasi ini sehingga lolos tak terdeteksi. Ini gotcha yang seharusnya ditangani di SP3 tapi terlewat dari plan.
4. **`PaymentAllocationService`** (122 baris) berisi 2 method dengan subjek data berbeda total (`allocate()` = Tagihan/Pembayaran, `topupSisaJikaAda()` = Wallet) tapi selalu dipanggil bareng dari instance yang sama oleh `ReconcilePayments`.
5. **View fallback bersama** `keuangan.tanpa-anak.blade.php` dipakai oleh 4 controller (`Portal\Keuangan\TagihanController` [SP2], `RiwayatController`+`CheckoutController` [SP3], `Keuangan\DashboardController` [SP4]) — sengaja ditunda pindah sampai SP4 (SP terakhir yang menyentuhnya).

## 2. Tujuan

Memindahkan `Wallet`, `WalletMutasi`, `Cicilan`, `AutoAllocationEngine`, `SkipAlertResolver`, `PaymentAllocationService`, dan `Keuangan\DashboardController` ke `app/Domains/Keuangan/*` — **menuntaskan seluruh migrasi domain Keuangan**. Memperbaiki bug pre-existing di `WalletMutasi::pembayaran()`. Memindahkan view fallback bersama `tanpa-anak` yang sengaja ditunda 2 sub-project. Mengakhiri dengan audit final yang membuktikan TIDAK ADA lagi kode Keuangan tersisa di luar `app/Domains/Keuangan/`.

## 3. Cakupan

### 3.1 Model (3, ke `app/Domains/Keuangan/Models/`)
- `Wallet` (155 baris) — `topup()`/`debit()`/`debitWithinTransaction()`/`debitCore()` sudah pakai FQCN `\App\Domains\Keuangan\Models\Pembayaran` di 4 signature (peninggalan SP1-3). Dependency ke `App\Services\Finance\AutoAllocationEngine` (baris 12, akan ikut pindah sekaligus di SP4 ini — jadi `use` biasa, bukan FQCN, setelah keduanya sama-sama di domain baru). **Gotcha arah-terbalik ditemukan**: `topup()` baris 76 memanggil `SystemSetting::getResolved(...)` TANPA `use` statement — saat ini valid karena `SystemSetting` sama-namespace (`App\Models`), begitu `Wallet` pindah ke domain baru referensi ini WAJIB ditambah `use App\Models\SystemSetting;` eksplisit (SystemSetting TIDAK ikut pindah, tetap generic app-level model).
- `WalletMutasi` (34 baris) — **bug fix**: relasi `pembayaran()` yang rusak (§1 poin 3) diperbaiki jadi merujuk `Pembayaran::class` yang benar (sama-namespace setelah pindah, tidak perlu FQCN lagi).
- `Cicilan` (34 baris) — relasi `skemaCicilan()`/`pembayaran()` sudah FQCN ke `Domains\Keuangan\Models\*` (peninggalan SP1-3), jadi biasa setelah sama-namespace.

### 3.2 Service (3, ke `app/Domains/Keuangan/Services/`)
- `AutoAllocationEngine` (128 baris) — inject `NotificationDispatcher` (TETAP di `app/Services/Finance/`, generik lintas-modul, keputusan final SP3). **Gotcha arah-terbalik**: constructor baris 16 (`private readonly NotificationDispatcher $dispatcher`) TANPA `use` statement — valid sekarang karena sama-namespace `App\Services\Finance`, begitu file ini pindah domain WAJIB ditambah `use App\Services\Finance\NotificationDispatcher;` eksplisit.
- `SkipAlertResolver` (85 baris) — **guard eksplisit**: `withoutGlobalScope(TenantScope::class)` di baris 39 dan 80, dengan komentar panjang alasan divergensi dari `AutoAllocationEngine` — WAJIB dipertahankan persis. Tidak ada gotcha arah-terbalik (tidak ada dependency ke sibling class yang tinggal).
- `PaymentAllocationService` (122 baris, UTUH satu file) — keputusan brainstorming §6.1: pindah UTUH sekarang meski berisi 2 method beda subjek data, karena SP4 adalah sub-project terakhir dan kedua method dipanggil dari instance yang sama oleh `ReconcilePayments`. **Gotcha arah-terbalik yang SAMA**: constructor baris 16 inject `NotificationDispatcher` tanpa `use` — WAJIB ditambah `use App\Services\Finance\NotificationDispatcher;` eksplisit begitu pindah.

### 3.3 Controller (1, direfactor + pindah namespace)
`Keuangan\DashboardController` (65 baris, murni read-only, TIDAK ada Action baru — konsisten pola SP1-3 untuk controller read-only) → `Portal\Keuangan\DashboardController`. Guard route-level (`permission:keuangan.akses`, `resolve.active.siswa`) TETAP di `routes/web.php`, TIDAK ada `authorize()` di dalam class itu sendiri (sesuai kode asli).

### 3.4 Console Command (TETAP di `app/Console/Commands/`, hanya `use` diupdate)
`ReconcilePayments` — inject `PaymentAllocationService` yang sekarang pindah namespace; `use`-nya diupdate, isi logic tidak disentuh.

### 3.5 View
| View lama | View baru |
|---|---|
| `resources/views/keuangan/dashboard.blade.php` | `resources/views/portals/portal/keuangan/dashboard.blade.php` |
| `resources/views/keuangan/tanpa-anak.blade.php` | `resources/views/portals/portal/keuangan/tanpa-anak.blade.php` |

**`tanpa-anak.blade.php` dipakai 4 controller** — SEMUA baris `view('keuangan.tanpa-anak')` WAJIB diupdate ke `view('portals.portal.keuangan.tanpa-anak')`: `Portal\Keuangan\TagihanController.php:19`, `RiwayatController.php:22`, `CheckoutController.php:36` (ketiganya TIDAK migrasi controller-nya lagi di SP4, HANYA baris view-string ini yang disentuh — cross-scope touch), dan `Portal\Keuangan\DashboardController.php` (baru, bagian migrasi SP4 sendiri).

## 4. Gotcha Referensi Implisit — DUA ARAH (Temuan Baru di SP4)

Sub-project sebelumnya (SP1-3) hanya menemukan gotcha SATU ARAH: file yang TETAP di `app/Models`/`app/Services` mereferensikan class yang PINDAH tanpa `use` (perlu FQCN). SP4 menemukan gotcha ARAH SEBALIKNYA juga terjadi: file yang PINDAH (`Wallet.php`, `AutoAllocationEngine.php`, `PaymentAllocationService.php`) mereferensikan sibling class yang TETAP tinggal (`SystemSetting`, `NotificationDispatcher`) tanpa `use` — karena dulu sama-namespace, sekarang harus ditambah `use` eksplisit. **WAJIB dicek KEDUA ARAH untuk setiap file yang pindah di SP4** (baca isi lengkap file SEBELUM pindah, cari SEMUA class name yang dipakai tanpa `use` statement yang jelas, bukan cuma yang sudah diketahui di tabel §4 ini).

### 4.1 Gotcha arah biasa (FQCN WAJIB, file TIDAK pindah)
| File | Referensi implisit |
|---|---|
| `app/Models/Siswa.php:78` | `hasOne(Wallet::class)` → `\App\Domains\Keuangan\Models\Wallet::class` |

## 5. Cross-Scope Touch (file TIDAK migrasi tapi WAJIB disentuh)

**Update `use App\Models\Wallet;` → `use App\Domains\Keuangan\Models\Wallet;`** (grep `App\Models\Wallet\b` per 24 Agustus 2026, WAJIB grep ulang saat plan-writing):
- `app/Domains/Keuangan/Actions/Pembayaran/ApproveManualPaymentAction.php` (SP3)
- `app/Domains/Keuangan/Models/BriVirtualAccount.php` (SP1/SP3)
- `app/Listeners/CreateWalletForNewStudent.php` (event `StudentCreated`, TETAP di luar domain — listener ini bereaksi ke event Siswa, bukan bagian Keuangan, tapi `use`-nya perlu diupdate karena mengimpor `Wallet`)

**Update `use App\Models\Cicilan;` → `use App\Domains\Keuangan\Models\Cicilan;`** (grep `App\Models\Cicilan\b` per 24 Agustus 2026):
- `app/Http/Controllers/Portal/TagihanController.php` (portal pendaftar PPDB, TIDAK migrasi controller-nya)
- `app/Domains/Keuangan/Actions/Tagihan/CatatManualCicilanAction.php` (SP2)
- `app/Domains/Keuangan/Services/PembayaranService.php` (SP3)
- `app/Domains/Keuangan/Models/Tagihan.php` (SP2)
- `app/Domains/Keuangan/Models/Pembayaran.php` (SP3)
- `app/Http/Controllers/Lembaga/Keuangan/TagihanController.php` (SP2)
- `app/Domains/Keuangan/Models/SkemaCicilan.php` (SP1)

**Update view-string `keuangan.tanpa-anak` → `portals.portal.keuangan.tanpa-anak`** (§3.5): `Portal\Keuangan\TagihanController.php`, `RiwayatController.php`, `CheckoutController.php`.

## 6. Keputusan Desain (hasil brainstorming)

1. **`PaymentAllocationService` pindah UTUH satu file** ke `Domains\Keuangan\Services`, TIDAK dipecah jadi 2 service meski berisi 2 method beda subjek data. Prioritas kepraktisan (satu dependency untuk `ReconcilePayments`) di atas kemurnian "satu class satu subjek", karena SP4 sudah sub-project terakhir — tidak ada lagi alasan menunda salah satu method.
2. **Bug `WalletMutasi::pembayaran()` diperbaiki sebagai bagian normal migrasi**, BUKAN "zero-behavior-change" murni — perilaku SEBELUM perbaikan ini memang rusak (error kalau dipanggil), jadi memperbaikinya mengembalikan perilaku yang SEHARUSNYA. Dicatat eksplisit di plan supaya tidak dianggap pelanggaran zero-behavior-change.
3. **`Keuangan\DashboardController` → `Portal\Keuangan\DashboardController`** — konsisten pola scope `Portal\Keuangan\` yang sudah dipakai `TagihanController`/`RiwayatController`/`CheckoutController` (audiens sama: siswa/ortu aktif, middleware group sama `prefix('keuangan')`).
4. **View `tanpa-anak.blade.php` akhirnya dipindah** — SP2/SP3 sengaja menunda karena bukan mereka SP terakhir yang menyentuhnya; SP4 dikonfirmasi sebagai SP terakhir, jadi dipindah sekarang, 4 titik panggil (3 controller existing + 1 baru) semua diupdate.

## 7. Zero-Behavior-Change

- `Wallet::topup()`'s pola lock+dispatch `AutoAllocationEngine::run()` DI LUAR transaction (dengan `AutoAllocationFailedException` handling) — dipertahankan persis, TIDAK diubah urutannya.
- `SkipAlertResolver`'s 2 `withoutGlobalScope(TenantScope::class)` (baris 39, 80) — WAJIB dipertahankan persis, ini bukan bug seperti WalletMutasi, ini guard yang disengaja.
- `AutoAllocationEngine::run()`'s urutan alokasi (`priority_score`→`jatuh_tempo`→`id`, `lockForUpdate()`), notifikasi `SaldoTidakCukupNotification` di luar transaction untuk tagihan skip prioritas tertinggi — dipertahankan persis.
- `PaymentAllocationService::allocate()`/`topupSisaJikaAda()` — isi KEDUA method dipertahankan persis, TIDAK digabung/disederhanakan jadi 1 method meski sekarang di file yang sama seperti sebelumnya.
- `ReconcilePayments::reconcileWaitingPayments()`/`retryFailedTopups()` — logic tidak disentuh, hanya `use` `PaymentAllocationService` yang diupdate.
- `Keuangan\DashboardController::index()` — urutan try/catch VA-creation (gagal VA TIDAK menggagalkan render dashboard, cuma log error) dipertahankan persis.
- **KECUALI** `WalletMutasi::pembayaran()` — ini SATU-SATUNYA perubahan perilaku yang disengaja di SP4 (§6 poin 2), bug fix bukan regresi.

## 8. Testing

- Test scoped per task, full suite HANYA di task terakhir dengan izin eksplisit user.
- Grep verifikasi WAJIB scope `app database tests`.
- **Test baru WAJIB ditambahkan** untuk relasi `WalletMutasi::pembayaran()` yang diperbaiki — verifikasi eksplisit relasi itu sekarang resolve dengan benar (test yang akan GAGAL di kode lama, PASS setelah fix — bukti nyata bug-nya sungguhan).
- Test WAJIB tetap lulus: semua yang namanya mengandung `Wallet`, `Cicilan`, `AutoAllocationEngine`, `SkipAlert`, `PaymentAllocationService`, `ReconcilePayments`, `DashboardController` (di `tests/Feature/Keuangan`).

## 9. Audit Final Wajib (Task Terakhir)

Setelah SP4 selesai, jalankan grep menyeluruh di `app/Models`, `app/Services` (level atas, BUKAN `app/Services/Finance/*` yang harusnya sudah kosong), `app/Http/Controllers/Admin`, `app/Http/Controllers/Keuangan` (harus KOSONG TOTAL kecuali `NotifikasiController.php` yang memang generic), `app/Contracts`, `app/DTO` untuk memastikan TIDAK ADA lagi kelas bernama Tagihan/Pembayaran/Wallet/Cicilan/Keuangan/BRI/Va/Qris/Payment tersisa di luar `app/Domains/Keuangan/`, KECUALI yang sudah dikonfirmasi eksplisit milik domain lain: `TagihanGenerator` (PPDB), `Admin\TagihanSusulanController` (PPDB), `Portal\TagihanController` (portal pendaftar PPDB), `NotificationDispatcher` + `Notifications/Finance/*` (generik lintas-modul, keputusan final SP3), `Keuangan\NotifikasiController` (generic). Hasil audit ini WAJIB dilampirkan penuh di handoff log sebagai bukti penutup migrasi domain Keuangan.

## 10. Di Luar Cakupan (Selamanya, Bukan Ditunda)

- `TagihanGenerator`, `Admin\TagihanSusulanController`, `Portal\TagihanController`, wizard SPMB — domain PPDB/SPMB, ditunda indefinitely per keputusan terpisah sebelumnya, TIDAK terkait migrasi Keuangan.
- `NotificationDispatcher`, `Notifications/Finance/*` — infrastruktur generik lintas-modul, keputusan final SP3, TIDAK akan pindah ke domain manapun.
- `Keuangan\NotifikasiController` — generic inbox notifikasi, tidak pernah terkait migrasi domain Keuangan.
