# Modul Sistem Keuangan Sekolah Dinamis — Sub-project 3: Wallet & Auto-Allocation Engine

> Status: Disetujui — siap ke Implementation Plan (dieksekusi setelah Sub-project 2 selesai & diverifikasi).

## Konteks & Dependensi

Bergantung pada Sub-project 1 (`tagihan` polymorphic, `paid_amount`, `pembayaran.wallet_id`/`is_auto_allocation`, `pembayaran_tagihan`) dan Sub-project 2 (tagihan sudah bisa digenerate dengan `priority_score` dari `jenis_tagihan`). Sub-project ini membangun mekanisme saldo digital & mesin alokasi otomatis — **belum** mengintegrasikan sumber top-up nyata (BRI VA/QRIS/transfer manual/cash — itu Sub-project 4). Top-up di sub-project ini diasumsikan bisa terjadi lewat entri manual/testing sampai Sub-project 4 menyediakan channel sungguhan.

## Tujuan Sub-project 3

Setiap siswa punya wallet dengan saldo & VA permanen. Saat saldo bertambah (top-up), sistem otomatis melunasi tagihan aktif berdasarkan `priority_score` (kalau toggle ON, per-lembaga). Orang tua juga bisa membayar tagihan pilihan langsung dari saldo yang sudah ada, kapan pun (toggle ON atau OFF).

## Perubahan Skema

### 1. Tabel Baru — `wallets`

```
wallets
├─ id
├─ siswa_id         FK → siswa, cascade delete, UNIQUE (1:1)
├─ balance           decimal(15,2) DEFAULT 0
├─ va_number          string UNIQUE NULLABLE  (VA permanen, no-expire)
├─ total_topup         decimal(15,2) DEFAULT 0  (kumulatif, untuk laporan)
├─ total_deducted       decimal(15,2) DEFAULT 0  (kumulatif)
└─ timestamps
```
Dibuat otomatis saat event `StudentCreated` (reuse listener dari Sub-project 2), `balance=0`, `va_number` digenerate saat itu juga (format/skema penomoran VA didefinisikan di Sub-project 4 bersama integrasi BRI — di sub-project ini `va_number` bisa berupa placeholder/nullable dulu jika integrasi belum siap, tidak memblokir pembuatan wallet).

### 2. Tabel Baru — `wallet_mutasi` (Ledger)

```
wallet_mutasi
├─ id
├─ wallet_id         FK → wallets, cascade delete
├─ pembayaran_id      FK → pembayaran, NULLABLE, set null on delete
├─ tipe                ENUM('topup','debit','refund')
├─ amount              decimal(12,2)
├─ saldo_sebelum        decimal(12,2)
├─ saldo_sesudah        decimal(12,2)
├─ keterangan           text NULLABLE
└─ timestamps
```
Source-of-truth histori & audit saldo. `wallets.balance` didenormalisasi = `saldo_sesudah` mutasi terakhir (untuk performa baca), tapi setiap perubahan balance WAJIB lewat insert `wallet_mutasi` dalam transaction yang sama (tidak pernah update `wallets.balance` langsung tanpa jejak ledger).

### 3. `system_settings` — Scoping per Lembaga

```
system_settings
├─ id
├─ lembaga_id   FK → lembaga, NULLABLE (NULL = default sistem/yayasan-wide)
├─ key           string
├─ value          text/json
├─ description
├─ updated_by     FK → users
└─ timestamps

unique(lembaga_id, key)
```
**Resolusi nilai key**: cek row `(lembaga_id=X, key)` dulu → kalau tidak ada, fallback ke row `(lembaga_id=NULL, key)`. Key utama sub-project ini: `auto_debit_enabled` (boolean).

### 4. Model `Wallet` — Method Utama

- `Wallet::topup(amount, pembayaran)`: insert `wallet_mutasi(tipe=topup)`, update `balance`, `total_topup` — lalu trigger `AutoAllocationEngine::run($wallet)` jika `auto_debit_enabled` resolved TRUE untuk lembaga siswa.
- `Wallet::debit(amount, pembayaran, keterangan)`: dipakai baik oleh Auto-Allocation Engine maupun jalur manual "Bayar dari Saldo" — insert `wallet_mutasi(tipe=debit)`, update `balance`, `total_deducted`. Menolak (exception) jika `amount > balance`.

## Auto-Allocation Engine

Trigger: setiap kali `Wallet::topup()` sukses DAN `auto_debit_enabled` resolved TRUE untuk lembaga siswa pemilik wallet.

**Algoritma** (per ADR #1/#2/#3 draft awal, disesuaikan skema baru):
1. Query `tagihan WHERE tagihable_type=Siswa AND tagihable_id={siswa_id} AND status IN ('belum_bayar','sebagian')` JOIN `jenis_tagihan` `ORDER BY jenis_tagihan.priority_score ASC, tagihan.due_date ASC` (tagihan tanpa `priority_score`/NULL diperlakukan sebagai prioritas terendah — diproses paling akhir).
2. `skipped = []`. Loop tiap tagihan:
   - `sisa = net_amount - paid_amount`. Jika `wallet.balance >= sisa`: `Wallet::debit(sisa, ...)`, insert `pembayaran` (`metode='wallet_auto'`, `sumber='admin'` secara sistem, `is_auto_allocation=true`, `status='lunas'`) + `pembayaran_tagihan` (amount_allocated=sisa) + `wallet_mutasi(tipe=debit, pembayaran_id=...)`, update `tagihan.paid_amount += sisa`, `status='lunas'`.
   - Jika `wallet.balance < sisa`: masukkan ke `skipped`, lanjut ke tagihan berikutnya (TIDAK bayar sebagian — kebijakan "skip penuh" dari ADR #2 draft awal tetap dipakai, bukan partial-pay).
3. Selesai loop. Jika `skipped` tidak kosong: catat tagihan dengan `priority_score` terkecil di `skipped` sebagai kandidat banner alert (detail UI/notifikasi di Sub-project 5 & 6 — sub-project ini hanya menyediakan query/endpoint untuk mengambil "skipped bill priority tertinggi milik siswa X", bukan UI banner-nya).
4. Sisa `wallet.balance` setelah loop (baik karena semua tagihan lunas dengan sisa, atau karena skip) tetap di wallet sebagai saldo — tidak ada aksi lanjutan otomatis.

## Pembayaran Manual dari Saldo Wallet

Endpoint baru: orang tua pilih 1+ tagihan (checkbox, sama seperti UI mode-manual di draft awal) → jika `wallet.balance >= SUM(sisa tagihan terpilih)`, tombol "Bayar dari Saldo" aktif → 1 `pembayaran` (`metode='wallet_saldo'`, `is_auto_allocation=false`) mengalokasikan ke semua tagihan terpilih via `pembayaran_tagihan`, 1 `wallet_mutasi(tipe=debit)` untuk total gabungan. Tersedia terlepas dari status toggle `auto_debit_enabled` (toggle hanya mengatur otomatisasi saat top-up, bukan membatasi aksi manual orang tua).

## Toggle Dimatikan — Perilaku Saldo Existing

Mengubah `auto_debit_enabled` ke FALSE (per lembaga) tidak memicu job/efek apapun ke wallet yang sudah bersaldo — murni mengubah perilaku *masa depan* saat top-up berikutnya terjadi. Saldo existing tetap bisa dipakai lewat "Bayar dari Saldo Wallet" (manual) kapan saja.

## Yang TIDAK Termasuk Sub-project 3

- Integrasi BRI API nyata untuk top-up (VA/QRIS) — `Wallet::topup()` di sub-project ini dipanggil dari entri manual/testing/seeder; koneksi ke channel pembayaran sungguhan ada di Sub-project 4.
- Format & penomoran `va_number` yang valid secara BRI (sub-project ini boleh pakai placeholder) — final di Sub-project 4.
- UI banner skip alert & notifikasi (Sub-project 5 & 6) — sub-project ini hanya menyiapkan data/query yang dibutuhkan.
- Refund wallet ke rekening bank orang tua (di luar scope modul untuk saat ini).

## Ambiguitas Terselesaikan

- [x] Scope toggle auto-debet → Per-lembaga (`system_settings.lembaga_id` nullable, resolusi fallback ke default sistem)
- [x] Bayar manual dari saldo wallet → Opsi baru, tersedia terlepas dari status toggle
- [x] Ledger wallet → Tabel terpisah `wallet_mutasi`, source-of-truth saldo
- [x] Efek toggle dimatikan ke saldo existing → Tidak ada aksi otomatis, saldo diam

## Ambiguitas Sisa (untuk sub-project berikutnya)

- [ ] Format `va_number` final (panjang, prefix, cara generate unik) — bergantung spesifikasi sandbox BRI, diselesaikan di Sub-project 4
- [ ] Perilaku "Bayar dari Saldo Wallet" saat toggle ON dan ada tagihan priority lebih tinggi yang BELUM dibayar (siswa/ortu pilih tagihan priority rendah duluan secara manual, melewati urutan priority_score) — apakah perlu warning/konfirmasi ekstra di UI, didetailkan saat sub-project 6 (parent dashboard)
