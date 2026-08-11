# Handoff Log: Keuangan 03 — Wallet & Auto-Allocation Engine

**Referensi:**
- Spec: `.agents/specs/keuangan-03-wallet-auto-allocation.md`
- Plan: `.agents/plans/keuangan-03-wallet-auto-allocation.md`

## Update: Final Whole-Plan Review (post-delivery)

Bug kritis ditemukan dan diperbaiki setelah final review lintas-task:

**BUG: Nested transaction + double lockForUpdate di AutoAllocationEngine**  
Ketika `topup()` memanggil `AutoAllocationEngine::run()` (di luar outer transaction topup, benar),
engine membungkus kerjanya dalam `DB::transaction()` + `lockForUpdate(wallet)`. Di dalam transaction
itu, engine memanggil `wallet->debit()` yang membuka `DB::transaction()` **lagi** + `lockForUpdate`
pada row yang sama. Di MySQL production environment, ini membuat nested transaction yang berpotensi
menyebabkan deadlock atau perilaku tidak terduga.

**Fix:** Refactor `Wallet` dengan memisahkan logika ke `debitCore()` (private), lalu expose dua method public:
- `debit()` → untuk caller eksternal (bungkus transaction + lock sendiri)
- `debitWithinTransaction()` → untuk caller yang sudah dalam transaction + lock (AutoAllocationEngine)

AutAllocationEngine kini menggunakan `debitWithinTransaction()`.  
*(Commit: `fix(keuangan): eliminate nested transaction + re-lock in AutoAllocationEngine`)*

## Apa yang dikerjakan
Sub-project 03 telah diselesaikan sepenuhnya. Semua fitur terkait Wallet dan Auto-Allocation Engine telah diimplementasikan:
1. **Schema & Models**: Membuat tabel `system_settings`, `wallets`, dan `wallet_mutasi` dengan model yang terhubung (Wallet <-> Siswa, Wallet -> Mutasi).
2. **Logika Inti Wallet**: Menambahkan mekanisme `topup()` dan `debit()` menggunakan `lockForUpdate()` untuk mencegah race condition, serta implementasi `InsufficientBalanceException`. Mutasi dicatat secara otomatis (Atomic) pada setiap operasi.
3. **Auto-Allocation Engine**: Mengimplementasikan logika Partial Top-Down: pembayaran memprioritaskan `priority_score` (1=Tertinggi, dst) dan melakukan tie-breaker menggunakan tanggal `jatuh_tempo` (terlama). Tagihan dengan status 'dibatalkan' akan dilewati otomatis.
4. **Integrasi System Setting**: Mengimplementasikan resolver untuk toggle `auto_debit_enabled` per lembaga. Engine hanya akan berjalan jika setting ini aktif di lembaga bersangkutan.
5. **Idempotent Listener**: `CreateWalletForNewStudent` yang menangkap event `StudentCreated` untuk auto-create Wallet (menggunakan `firstOrCreate`) secara aman.
6. **Perbaikan Test Suite**: Memperbaiki unit/feature tests terkait (`AutoAllocationEngineTest`, `WalletTest`, `WalletDatabaseTest`) agar menggunakan `wallet` yang di-create otomatis oleh listener, menghindari `UniqueConstraintViolationException`.

## Keputusan penting yang diambil
1. **Concurrency test di SQLite**: Karena SQLite (dalam test memory) tidak menangani table-level/row-level lock (`lockForUpdate()`) sebaik MySQL, test *concurrent race condition* disepakati hanya dicakup dengan verifikasi *logic order* di Unit test dan/atau bergantung pada validasi manual di environment MySQL nantinya. Penguncian sudah disisipkan dalam method `topup()` dan `debit()`.
2. **Auto-Allocation Threshold/Rounding**: Pengalokasian (baik `topup` maupun pembayaran sebagian via auto-engine) menggunakan tipe desimal sebagaimana adanya di DB tanpa logic pembulatan manual untuk menghindari error floating point.
3. **Penyesuaian Tests Existing**: Ditemukan bahwa test `WalletDatabaseTest` yang dibuat pada awal Task 1 bentrok dengan Event Listener `StudentCreated` yang baru dibuat di Task 5. Kami meresolusi ini dengan mengubah instansiasi manual `Wallet::create` menjadi `$siswa->wallet->update` di semua tests tersebut.

## Hal yang masih perlu direview manusia/Claude
- **Environment Concurrency MySQL**: Perilaku `lockForUpdate()` sudah diimplementasikan sesuai standar, namun karena keterbatasan test DB SQLite bawaan, disarankan Claude/developer manusia untuk memvalidasi concurrency di koneksi MySQL sesungguhnya (terutama load testing jika tagihan masif dibayarkan serentak).
- **Payment Channels Dependency (04)**: Spec 04, 05, dan 06 yang sudah disalin di sesi sebelumnya terbukti *sudah* di-commit oleh agen sebelumnya, sehingga tidak diproses ulang di sesi ini. Modul Wallet `auto_debit` siap diintegrasikan sebagai salah satu metode di Payment Channels.
- **Angka Regression Tests Final**: Setelah whole-plan fix, full suite Keuangan: 100 passed / 270 assertions, 0 failed. JadwalPelajaranCrudTest: diverifikasi 3x isolated run (48 passed × 3 = clean), lalu digabung dengan Keuangan suite (148 passed total, 0 failed) — **terbukti bukan test pollution**. Failure di full suite sebelumnya adalah collision random factory data di MySQL yang tidak terkait modul Keuangan.
- **Git State**: Semua commit berada di branch `demo` secara granular dan **belum** di-push ke remote sesuai kesepakatan. Siap untuk di-review secara keseluruhan.
