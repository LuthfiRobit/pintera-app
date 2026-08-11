# Plan: Keuangan 03 — Wallet & Auto-Allocation Engine

Berdasarkan spesifikasi di `.agents/specs/keuangan-03-wallet-auto-allocation.md`.
Semua tahap harus menyertakan TDD (Test-Driven Development) dengan unit/feature test yang ketat untuk menjamin integritas finansial.

- [ ] **Task 1: Fondasi Skema Database & Model (Migrasi Berurutan)**
  - Pastikan urutan migrasi logis dan tidak menyebabkan Foreign Key constraint error:
    1. Buat migrasi tabel `system_settings` (`lembaga_id`, `key`, `value`, `description`, `updated_by`).
    2. Buat migrasi tabel `wallets` (`siswa_id`, `balance`, `va_number`, `total_topup`, `total_deducted`).
    3. Buat migrasi tabel `wallet_mutasi` (`wallet_id`, `pembayaran_id`, `tipe`, `amount`, `saldo_sebelum`, `saldo_sesudah`, `keterangan`).
  - Buat model `Wallet`, `WalletMutasi`, `SystemSetting` dengan relasi Eloquent yang tepat (`Wallet` belongsTo `Siswa`, `Siswa` hasOne `Wallet`, dll).
  - *Testing*: Pastikan struktur tabel valid dan relasi Eloquent berfungsi tanpa error.

- [ ] **Task 2: Logika Inti Saldo (Top-up & Debit) & Integritas Data**
  - Implementasikan Exception khusus `InsufficientBalanceException`.
  - Implementasikan method `Wallet::topup(float $amount, ?Pembayaran $pembayaran = null, ?string $keterangan = null)`. Wajib dalam `DB::transaction` dan menggunakan `lockForUpdate()`.
  - Implementasikan method `Wallet::debit(float $amount, ?Pembayaran $pembayaran = null, ?string $keterangan = null)`. Wajib mengecek saldo (`throw InsufficientBalanceException` jika kurang), menggunakan `lockForUpdate()`.
  - Pastikan setiap operasi `topup`/`debit` meng-insert record ke `wallet_mutasi` dengan nilai `saldo_sebelum` dan `saldo_sesudah` yang akurat.
  - *Testing* (`WalletTest`):
    - **Test concurrent locking (Known Limitation)**: SQLite test environment tidak bisa mensimulasikan *race condition* multi-thread secara nyata. Test akan difokuskan untuk memverifikasi bahwa *query builder* secara eksplisit menginjeksi klausa `FOR UPDATE` (memanggil `lockForUpdate()`). Mitigasi: Verifikasi konkurensi nyata harus dicatat sebagai kewajiban QA manual di environment staging (MySQL).
    - **Test InsufficientBalanceException dengan rollback**: Pastikan exception dilempar saat saldo kurang, dan **pastikan rollback penuh berhasil** (saldo mutasi maupun wallet sama sekali tidak berubah di database).
    - **Test konsistensi saldo (balance vs mutasi)**: Lakukan serangkaian top-up dan debit acak, assert di akhir bahwa `$wallet->balance === SUM(topup) - SUM(debit)`.

- [ ] **Task 3: Auto-Allocation Engine (Partial Top-Down + Tie-Breaker)**
  - Buat class/service `AutoAllocationEngine` (atau method setara) yang menerima instans `Wallet`.
  - Tulis logika query untuk mengambil tagihan aktif (`status IN ('belum_bayar', 'sebagian')`) yang diurutkan ketat dengan `ORDER BY priority_score ASC, jatuh_tempo ASC, id ASC`. Filter tagihan `dibatalkan`.
  - Tulis logika iterasi alokasi *Partial Top-Down*:
    - Cek saldo wallet, bayarkan ke tagihan sebanyak `min($saldo_tersedia, $sisa_tagihan)`.
    - Lakukan `Wallet::debit()`.
    - Buat `pembayaran` (`metode='wallet_auto'`, `is_auto_allocation=true`, `status='lunas'`) dan `pembayaran_tagihan` sebesar nominal yang dialokasikan.
    - Update `tagihan.paid_amount` dan status (`lunas` atau `sebagian`).
    - Hentikan loop sepenuhnya (break) jika saldo wallet habis. Sisa tagihan dibiarkan.
  - *Testing* (`AutoAllocationEngineTest`): Feature test dengan skenario kompleks:
    - Skenario saldo cukup melunasi semua tagihan.
    - **Skenario Partial Top-Down**: Saldo hanya cukup untuk 1.5 tagihan. Pastikan tagihan prioritas 1 lunas, prioritas 2 terbayar sebagian, prioritas 3 tidak terbayar, dan iterasi loop terhenti saat saldo habis.
    - **Skenario Tie-Breaker**: Buat 2 tagihan dengan priority score sama. Set satu jatuh tempo bulan lalu, satu bulan ini. Pastikan sistem memotong tagihan jatuh tempo terlama lebih dahulu.

- [ ] **Task 4: Integrasi System Settings (Toggle Auto Debit)**
  - Tulis helper/fungsi resolusi nilai `SystemSetting::getResolved('auto_debit_enabled', $lembaga_id, true)` menggunakan pola fallback (lembaga -> global). Manfaatkan `Cache::rememberForever`.
  - Suntikkan pengecekan toggle ini ke dalam `Wallet::topup()`: `AutoAllocationEngine` **hanya** dipanggil jika `auto_debit_enabled` bernilai `true` untuk lembaga siswa.
  - *Testing*: Uji proses top-up saat toggle ON (memotong tagihan otomatis) vs OFF (saldo mengendap utuh di wallet).

- [ ] **Task 5: Pembuatan Wallet Otomatis (Idempotent Listener)**
  - Buat event listener `CreateWalletForNewStudent`.
  - Kaitkan listener ke event `App\Events\StudentCreated`.
  - Gunakan `Wallet::firstOrCreate()` agar pembuatan bersifat idempotent (tidak error duplikat walau dipanggil berulang).
  - *Testing* (`CreateWalletListenerTest`):
    - **Test idempotency**: Eksekusi event `StudentCreated` dua kali berturut-turut pada siswa yang sama, assert wallet hanya ada tepat 1 record di database.
