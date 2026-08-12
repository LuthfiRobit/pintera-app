# Handoff Log: Keuangan 03 — Wallet & Auto-Allocation Engine

**Referensi:**
- Spec: `.agents/specs/keuangan-03-wallet-auto-allocation.md`
- Plan: `.agents/plans/keuangan-03-wallet-auto-allocation.md`

## Update: Final Whole-Plan Review (post-delivery)

### Bug Kritis: Nested Transaction + Double lockForUpdate di AutoAllocationEngine

**Akar masalah — alur eksekusi saat topup dengan auto-debit aktif:**

```
topup()
  └─ DB::transaction()         ← Outer transaction T1, + lockForUpdate(wallet row)
      └─ update balance, create mutasi_topup
      └─ this->refresh()
  // T1 COMMIT — lock dilepas ✓

  // Cek toggle (di luar transaction, benar ✓)
  AutoAllocationEngine::run(wallet)
    └─ DB::transaction()       ← Outer transaction T2, + lockForUpdate(wallet row) ✓
        └─ ... hitung alokasi, buat Pembayaran record ...
        └─ wallet->debit()     ← ‼️ debit() membuka DB::transaction() LAGI (T3, nested dalam T2)
             └─ DB::transaction()   ← savepoint di MySQL — tidak crash, tapi...
                 └─ lockForUpdate(wallet row)  ← re-lock row yang sudah dikunci T2!
```

**Mengapa berbahaya di MySQL:**  
MySQL mengimplementasikan nested `DB::transaction()` via savepoints (Laravel memetakannya otomatis).
Savepoint itu sendiri tidak crash, tapi `lockForUpdate` di dalam savepoint **pada row yang sudah
dikunci oleh transaksi induknya sendiri** dapat menyebabkan:
1. Behavior tidak deterministik — lock re-acquisition dalam nested context implementasinya berbeda
   antar versi MySQL/MariaDB.
2. Jika ada faktor eksternal (misalnya advisory lock, GR replication), ini dapat degrade ke deadlock.
3. Performa buruk — double-locking pada row yang tidak perlu dikunci ulang.

**Fix yang diterapkan** (commit `9b3208a`):
```php
// Sebelum: debit() selalu membuka DB::transaction() + lockForUpdate
class Wallet {
    public function debit(...): void {
        DB::transaction(function() {
            $wallet = self::where('id', ...)->lockForUpdate()->first(); // MASALAH
            // ...
        });
    }
}

// Sesudah: logika dipisah ke debitCore(), dua public entry point
class Wallet {
    public function debit(...): void {              // Untuk caller EKSTERNAL
        DB::transaction(fn() => $this->debitCore(..., lockRow: true));
    }
    public function debitWithinTransaction(...): void { // Untuk caller dalam transaction
        $this->debitCore(..., lockRow: false);      // Tidak bungkus transaction baru
    }
    private function debitCore(..., bool $lockRow): void { /* shared logic */ }
}
```

### Konvensi Wallet::debit() vs Wallet::debitWithinTransaction() — WAJIB DIBACA Agent Sub-project 04

> **Sub-project 04 (Payment Channels) akan memanggil `Wallet::topup()` dari webhook callback.**  
> Perhatikan aturan ini sebelum menulis kode integrasi:

| Situasi | Method yang BENAR | Alasan |
|---------|-------------------|--------|
| Controller, Job, Command, Webhook handler | `wallet->debit()` | Caller tidak dalam transaction — `debit()` aman membuka transaction sendiri + lock |
| Sudah dalam `DB::transaction()` + sudah memanggil `lockForUpdate` pada wallet | `wallet->debitWithinTransaction()` | Mencegah nested transaction + re-lock pada row yang sama |
| Webhook callback yang memproses pembayaran (Sub-project 04) | `wallet->topup()` — **aman**, karena `topup()` membuka dan menutup transaction-nya sendiri sebelum trigger engine | Webhook handler biasanya tidak membungkus dirinya dalam outer transaction |
| Jika Sub-project 04 membuat service layer yang membungkus `topup()` dalam outer `DB::transaction()` | **HATI-HATI** — `topup()` masih aman (dia membuka sub-transaction / savepoint), tapi `AutoAllocationEngine` yang dipicu oleh `topup()` kemudian akan dijalankan **di luar** outer transaction. Ini bisa jadi masalah jika Anda mengharapkan atomicity penuh antara proses pencatatan pembayaran dan alokasi. |

**Aturan sederhana untuk Sub-project 04:**
- Panggil `topup()` dari webhook handler **tanpa** membungkusnya dalam `DB::transaction()` tambahan di level controller/service.
- Jika ada kebutuhan atomicity tambahan (misalnya update status webhook + topup harus satu atomic unit), bungkus hanya logika webhook-nya saja, dan panggil `topup()` **setelah** outer transaction commit.
- Jangan pernah memanggl `debitWithinTransaction()` dari luar konteks yang sudah `lockForUpdate` pada wallet — ini akan membuat read tanpa lock yang berisiko race condition.

### Audit Seluruh Caller Wallet::debit() dan Wallet::topup() (per Sub-project 03)

**Hasil grep seluruh codebase (`app/` + `tests/`):**

| File | Method | Konteks | Status |
|------|--------|---------|--------|
| `AutoAllocationEngine.php` | `debitWithinTransaction()` | Dalam `DB::transaction()` + lockForUpdate | ✅ Sudah diperbaiki |
| `WalletTest.php` (5 call) | `debit()` / `topup()` | Test feature, tidak dalam transaction | ✅ Aman |
| `SystemSettingTest.php` (2 call) | `topup()` | Test feature, tidak dalam transaction | ✅ Aman |

**Tidak ada caller lain di `app/` selain AutoAllocationEngine.** `debit()` dan `topup()` belum dipakai
oleh Controller, Job, atau Service lain — area ini akan pertama kali disentuh Sub-project 04.

*(Commit fix: `9b3208a fix(keuangan): eliminate nested transaction + re-lock in AutoAllocationEngine`)*

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

## Update: Audit Lintas Sub-Project 1-4 (2026-08-11, sesi terpisah)

Setelah sub-project 03 selesai, dilakukan audit independen lintas sub-project 1-4 (spec/kode/log dibaca ulang dan diverifikasi langsung, bukan sekadar percaya laporan) untuk memastikan fondasi bersih sebelum lanjut ke sub-project 5. Hasil khusus untuk sub-project 03 ini:

- **Kontradiksi kebijakan Auto-Allocation ditemukan dan DIRESOLUSI (bukan bug, tidak ada perubahan kode):** spec asli di `docs/superpowers/specs/2026-08-10-keuangan-03-wallet-auto-allocation-design.md` sempat menyebut kebijakan "skip penuh" (lewati tagihan yang tak bisa dilunasi penuh, coba tagihan berikutnya), sementara spec refresh di `.agents/specs/keuangan-03-wallet-auto-allocation.md` diam-diam menggantinya jadi "Partial Top-Down" tanpa penanda eksplisit bahwa ini pembalikan keputusan. **Dikonfirmasi ulang ke user: Partial Top-Down (kode `AutoAllocationEngine.php` yang sudah shipped di sub-project ini) adalah kebijakan yang benar dan final.** Detail lengkap ada di catatan "Ambiguitas Terselesaikan" pada `.agents/specs/keuangan-03-wallet-auto-allocation.md`.
- **`AutoAllocationEngine.php` sendiri tidak ditemukan bug baru** dalam audit ini — nested-transaction fix yang sudah didokumentasikan di atas (commit `9b3208a`) tetap valid dan terverifikasi.
- **3 bug nyata ditemukan & diperbaiki di modul lain** (bukan di sub-project 03 ini) sebagai bagian dari audit yang sama: lihat `.agents/logs/keuangan-audit-fixes-01-04.md` untuk detail lengkap (commit range `440fcbb..ad18d46` di `demo`) — ownership-check ordering di `JenisTagihanMonitoringController` (2b-3), webhook `BriWebhookController` yang selalu return 200 walau gagal, dan validasi amount webhook untuk `BILL_DIRECT` (keduanya di sub-project 04, lihat catatan di `.agents/logs/keuangan-04-payment-channels.md`).
- **3 test form jenis-tagihan yang basi** (akibat rework UI/UX terpisah, tidak terkait sub-project 03) juga ditemukan & diperbaiki di audit yang sama — lihat `.agents/logs/2026-08-11-jenis-tagihan-ui-ux.md` bagian "Koreksi" untuk detail.

**Untuk sesi/agent berikutnya:** modul Wallet & Auto-Allocation (sub-project 03) dinyatakan bersih setelah audit ini — tidak ada item terbuka baru dari sub-project 03 sendiri selain yang sudah tercatat di atas (validasi concurrency MySQL nyata, masih pending karena keterbatasan SQLite test DB).
