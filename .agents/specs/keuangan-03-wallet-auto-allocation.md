# Spec: Keuangan 03 — Wallet & Auto-Allocation Engine

> Status: Disetujui — siap ke Implementation Plan (dieksekusi setelah Sub-project 2 selesai & diverifikasi).

## Konteks & Dependensi

Bergantung pada:
- **Sub-project 1**: skema `tagihan` polymorphic (`tagihable_type`, `tagihable_id`), `net_amount`, `paid_amount`, `status` (`belum_bayar`, `sebagian`, `lunas`, `dibatalkan`), `pembayaran.wallet_id`/`is_auto_allocation`/`channel_reference`/`identifier_method`, tabel pivot `pembayaran_tagihan`.
- **Sub-project 2 (2a, 2b-1, 2b-2, 2b-3)**: generator tagihan (`TagihanBillingGenerator`), resolver nominal & keringanan (`TagihanNominalResolver`), form konfigurasi jenis tagihan, tombol proses tagihan, dan dashboard monitoring. Tagihan sudah memiliki `jenis_tagihan_id` dengan `priority_score` (1-100) dan `billing_period`.

Sub-project ini membangun mekanisme saldo digital (Wallet) & mesin alokasi otomatis (Auto-Allocation Engine) — **belum** mengintegrasikan gateway top-up pihak ketiga (BRI VA/QRIS — itu Sub-project 4). Top-up di sub-project ini terjadi lewat entri manual/testing/seeder sampai Sub-project 4 menyediakan channel payment gateway.

## Tujuan Sub-project 3

Setiap siswa memiliki satu `Wallet` dengan saldo digital.
1. Saat saldo bertambah (top-up), sistem otomatis melunasi tagihan aktif siswa bersangkutan berdasarkan `priority_score` jenis tagihan (jika toggle `auto_debit_enabled` aktif pada lembaga siswa).
2. Orang tua juga dapat membayar tagihan pilihan langsung dari saldo yang tersedia ("Bayar dari Saldo") kapan saja, terlepas dari status toggle auto-debit.

## Perubahan Skema

### 1. Tabel Baru — `wallets`

```
wallets
+- id
+- siswa_id            FK ? siswa, cascade delete, UNIQUE (1:1)
+- balance             decimal(15,2) DEFAULT 0
+- va_number           string UNIQUE NULLABLE  (VA permanen, no-expire)
+- total_topup         decimal(15,2) DEFAULT 0  (kumulatif, untuk laporan)
+- total_deducted      decimal(15,2) DEFAULT 0  (kumulatif)
+- timestamps
```
- Dibuat otomatis saat event `StudentCreated` (reuse event dari Sub-project 2a), `balance=0`.
- `va_number` dapat berupa placeholder/null pada sub-project ini (skema penomoran resmi dan integrasi gateway BRI diselesaikan di Sub-project 4).

### 2. Tabel Baru — `wallet_mutasi` (Ledger)

```
wallet_mutasi
+- id
+- wallet_id           FK ? wallets, cascade delete
+- pembayaran_id       FK ? pembayaran, NULLABLE, set null on delete
+- tipe                ENUM('topup', 'debit', 'refund')
+- amount              decimal(15,2)
+- saldo_sebelum       decimal(15,2)
+- saldo_sesudah       decimal(15,2)
+- keterangan          text NULLABLE
+- timestamps
```
- Menjadi single source-of-truth mutasi & audit saldo.
- `wallets.balance` didenormalisasi sama dengan `saldo_sesudah` mutasi terakhir untuk efisiensi query baca.
- Setiap perubahan saldo WAJIB mencatat record di `wallet_mutasi` dalam `DB::transaction`.

### 3. Tabel Baru — `system_settings` (Scoping per Lembaga)

```
system_settings
+- id
+- lembaga_id          FK ? lembaga, NULLABLE (NULL = default sistem/yayasan-wide)
+- key                 string
+- value               text/json
+- description         string NULLABLE
+- updated_by          FK ? users, NULLABLE
+- timestamps

UNIQUE(lembaga_id, key)
```
- **Resolusi nilai key**: Cek baris `(lembaga_id=X, key)` terlebih dahulu ? jika tidak ditemukan, fallback ke baris `(lembaga_id=NULL, key)`.
- Key utama sub-project ini: `auto_debit_enabled` (boolean, default: `true` atau `false` sesuai kebijakan institusi).

### 4. Model `Wallet` — Method Utama

- `Wallet::topup(float $amount, ?Pembayaran $pembayaran = null, ?string $keterangan = null)`:
  - Validasi amount > 0.
  - Insert `wallet_mutasi(tipe='topup')`, update `balance += amount`, `total_topup += amount`.
  - Jika toggle `auto_debit_enabled` pada lembaga siswa bernilai `true`, trigger `AutoAllocationEngine::run($wallet)`.
- `Wallet::debit(float $amount, ?Pembayaran $pembayaran = null, ?string $keterangan = null)`:
  - Validasi `amount <= balance`, throw exception jika saldo tidak mencukupi.
  - Insert `wallet_mutasi(tipe='debit')`, update `balance -= amount`, `total_deducted += amount`.

## Auto-Allocation Engine

Trigger: Setiap kali `Wallet::topup()` berhasil dieksekusi DAN `auto_debit_enabled` resolved `true` untuk lembaga siswa pemilik wallet.

### Algoritma Alokasi:
1. Query tagihan aktif siswa:
   ```sql
   SELECT tagihan.* 
   FROM tagihan 
   JOIN jenis_tagihan ON jenis_tagihan.id = tagihan.jenis_tagihan_id
   WHERE tagihan.tagihable_type = 'App\\Models\\Siswa' 
     AND tagihan.tagihable_id = {siswa_id}
     AND tagihan.status IN ('belum_bayar', 'sebagian')
   ORDER BY 
     COALESCE(jenis_tagihan.priority_score, 9999) ASC, 
     tagihan.jatuh_tempo ASC, 
     tagihan.id ASC;
   ```
   *(Tagihan dengan status `dibatalkan` atau `lunas` otomatis diabaikan).*

2. Loop setiap tagihan dalam transaction:
   - Hitung sisa tagihan: `$sisa = $tagihan->net_amount - $tagihan->paid_amount`.
   - **Jika `$wallet->balance >= $sisa`**:
     - Panggil `$wallet->debit($sisa, $pembayaran, 'Auto-debit tagihan ' . $tagihan->jenisTagihan->nama)`.
     - Buat record `pembayaran` (`metode='wallet_auto'`, `sumber='admin'` atau `'orang_tua'`, `is_auto_allocation=true`, `status='lunas'`).
     - Buat record `pembayaran_tagihan` (`pembayaran_id`, `tagihan_id`, `amount_allocated=$sisa`).
     - Update `$tagihan->paid_amount += $sisa` dan set `$tagihan->status = 'lunas'`.
   - **Jika `$wallet->balance < $sisa`**:
     - Masukkan tagihan ke daftar `$skipped`.
     - Lanjut ke tagihan berikutnya (kebijakan *Full Skip* — tidak melakukan pembayaran parsial otomatis jika saldo tidak menutup sisa penuh).

3. Hasil loop:
   - Jika `$skipped` tidak kosong: sediakan data/helper untuk mengambil tagihan dengan prioritas tertinggi yang ter-skip sebagai referensi banner alert di portal orang tua (Sub-project 5 & 6).
   - Sisa `$wallet->balance` tetap tersimpan aman di wallet.

## Pembayaran Manual dari Saldo Wallet

Endpoint & flow:
- Orang tua memilih 1 atau lebih tagihan aktif (`status IN ('belum_bayar', 'sebagian')`).
- Total tagihan dihitung: `$total = SUM(net_amount - paid_amount)`.
- Jika `$wallet->balance >= $total`:
  - Tombol "Bayar dari Saldo" dapat diklik.
  - Sistem membuat 1 record `pembayaran` (`metode='wallet_saldo'`, `sumber='orang_tua'`, `is_auto_allocation=false`, `status='lunas'`).
  - Mengurangi saldo wallet via `$wallet->debit($total, $pembayaran, ...)`.
  - Mengalokasikan dana ke masing-masing tagihan terpilih via `pembayaran_tagihan` dan mengupdate `paid_amount` serta `status` tagihan terkait.
- Aksi ini selalu tersedia terlepas dari status toggle `auto_debit_enabled`.

## Toggle Dimatikan — Perilaku Saldo Existing

Mengubah setting `auto_debit_enabled` menjadi `false` pada suatu lembaga murni mengubah perilaku otomatisasi pada *top-up berikutnya*. Saldo yang sudah ada di wallet tetap diam dan tetap dapat digunakan oleh orang tua untuk pembayaran manual kapan saja.

## Yang TIDAK Termasuk Sub-project 3

- Integrasi API Payment Gateway BRI (VA/QRIS) — diselesaikan di Sub-project 4.
- Format resmi dan verifikasi keunikan nomor VA BRI live — diselesaikan di Sub-project 4.
- UI Banner Skip Alert & Notifikasi WhatsApp/Email — diselesaikan di Sub-project 5 & 6.
- Fitur penarikan/refund saldo wallet ke rekening bank eksternal.

## Ambiguitas Terselesaikan

- [x] Scope toggle auto-debet ? Per-lembaga via `system_settings` dengan fallback ke default yayasan/sistem.
- [x] Bayar manual dari saldo wallet ? Didukung terlepas dari status toggle auto-debit.
- [x] Ledger wallet ? `wallet_mutasi` sebagai audit trail transaksi saldo.
- [x] Kolom jatuh tempo tagihan ? Menggunakan `jatuh_tempo` sesuai skema database aktual.
- [x] Guard status tagihan ? Hanya tagihan `belum_bayar` dan `sebagian` yang diproses alokasi. Tagihan `dibatalkan` tidak tersentuh.
