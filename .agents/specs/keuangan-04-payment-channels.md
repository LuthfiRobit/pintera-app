# Spec: Keuangan 04 — Payment Channels

> Status: Disetujui — siap ke Implementation Plan (dieksekusi setelah Sub-project 3 selesai & diverifikasi).

## Konteks & Dependensi

Bergantung pada:
- **Sub-project 1**: skema `pembayaran` yang telah diperluas (`metode` enum mencakup `transfer_manual`, `va_bri`, `cash`, `qris`, `wallet_auto`, `wallet_saldo`; `sumber` enum mencakup `calon_siswa`, `admin`, `orang_tua`; `wallet_id`, `is_auto_allocation`, `channel_reference`, `identifier_method`), serta tabel pivot `pembayaran_tagihan`.
- **Sub-project 2 (2a, 2b-1, 2b-2, 2b-3)**: tagihan dengan kategori PPDB maupun non-PPDB (`spp`, `tahunan`, `kegiatan`, `lainnya`, `custom`).
- **Sub-project 3**: model `Wallet`, `wallet_mutasi`, dan method `Wallet::topup()` / `debit()`.

Sub-project ini mengisi jalur pembayaran nyata: BRI Virtual Account (VA), QRIS Dinamis, transfer bank manual dengan persetujuan admin, dan pembayaran tunai di loket. Desain menggunakan abstraksi gateway (`PaymentGatewayInterface`) sehingga sub-project dapat diuji dan didemonstrasikan penuh menggunakan `MockPaymentGateway` sebelum kredensial sandbox BRI asli tersedia.

## Tujuan Sub-project 4

Orang tua dapat melunasi tagihan atau melakukan top-up wallet melalui:
1. **Virtual Account BRI (VA)** — nomor VA ter-generate otomatis dengan batas waktu kadaluarsa (atau VA permanen untuk wallet).
2. **QRIS Dinamis** — QR code ter-generate on-demand dengan nominal dinamis.
3. **Transfer Manual** — upload bukti transfer untuk diverifikasi oleh admin.
4. **Tunai (Cash di Loket)** — input langsung oleh admin kasir di loket sekolah.

Status pembayaran otomatis terverifikasi secara real-time melalui webhook callback dan didukung fallback polling terjadwal.

## Abstraksi Payment Gateway

```php
interface PaymentGatewayInterface
{
    public function createVirtualAccount(array $params): VirtualAccountResult; // amount nullable untuk WALLET_PERMANENT
    public function createQris(array $params): QrisResult;
    public function checkStatus(string $reference): PaymentStatusResult;
    public function verifyCallbackSignature(Request $request): bool;
}
```

- **`BriSnapGateway`**: Implementasi protokol BRI SNAP API (`virtual-account-snap`, `payment-transfer-va-snap`, `transaction-status-snap`, `qris-mpm-dinamis`). Kredensial (`client_id`, private key, partner ID) dibaca dari `config('services.bri')` / `.env`.
- **`MockPaymentGateway`**: Simulator lokal untuk environment `local` dan `testing`. Menghasilkan VA number dan QR string dummy yang konsisten, menyediakan endpoint dev `POST /dev/simulate-payment/{reference}` untuk simulasi pelunasan instan, dan `verifyCallbackSignature` selalu bernilai `true`.
- **Binding**: Diatur via `config('services.bri.gateway')` (`mock` sebagai default | `bri_snap`).

## Perubahan Skema

### 1. Tabel Baru — `bri_virtual_accounts`

```
bri_virtual_accounts
+- id
+- pembayaran_id       FK ? pembayaran, cascade delete
+- wallet_id           FK ? wallets, NULLABLE (terisi untuk VA permanen top-up)
+- va_type             ENUM('WALLET_PERMANENT', 'BILL_DIRECT')
+- va_number           string
+- amount              decimal(15,2) NULLABLE (NULL untuk WALLET_PERMANENT)
+- expired_at          timestamp NULLABLE (NULL untuk WALLET_PERMANENT)
+- status              ENUM('PERMANENT', 'WAITING', 'PAID', 'EXPIRED')
+- callback_payload    json NULLABLE
+- timestamps
```

### 2. Tabel Baru — `bri_qris_payments`

```
bri_qris_payments
+- id
+- pembayaran_id       FK ? pembayaran, cascade delete
+- qris_type           ENUM('TOPUP', 'DIRECT')
+- amount              decimal(15,2)
+- qr_code             text
+- expired_at          timestamp
+- status              ENUM('WAITING', 'PAID', 'EXPIRED')
+- callback_payload    json NULLABLE
+- timestamps
```

### 3. Tabel Baru — `manual_payment_requests`

```
manual_payment_requests
+- id
+- pembayaran_id       FK ? pembayaran, cascade delete
+- requested_by        FK ? users
+- amount              decimal(15,2)
+- transfer_proof_path string
+- bank_origin         string NULLABLE
+- transfer_date       date
+- status              ENUM('PENDING', 'APPROVED', 'REJECTED')
+- reviewed_by         FK ? users, NULLABLE
+- reviewed_at         timestamp NULLABLE
+- rejection_reason    text NULLABLE
+- timestamps
```

### 4. Modifikasi Tabel `pembayaran` — Penambahan Status

Modifikasi enum `pembayaran.status`:
```sql
ALTER TABLE pembayaran MODIFY status ENUM('menunggu_pembayaran', 'menunggu_verifikasi', 'lunas', 'ditolak') NOT NULL;
```
- `menunggu_pembayaran`: VA atau QRIS telah diterbitkan, menunggu pembayaran dari pihak orang tua.
- `menunggu_verifikasi`: Bukti transfer manual telah diunggah, menunggu persetujuan admin.
- `lunas`: Dana telah diterima dan dialokasikan.
- `ditolak`: Pembayaran transfer manual ditolak admin.

## Alur per Channel Pembayaran

### 1. BRI VA / QRIS (Single atau Multi-Tagihan)
1. Orang tua memilih 1 atau lebih tagihan aktif (atau memilih top-up saldo wallet).
2. Sistem membuat record `pembayaran` (`metode='va_bri'` atau `'qris'`, `status='menunggu_pembayaran'`, `sumber='orang_tua'`) dan record `pembayaran_tagihan` untuk setiap tagihan yang dipilih.
3. Gateway dipanggil (`createVirtualAccount` atau `createQris`):
   - Tagihan berstatus `dibatalkan` ditolak dari proses pembuatan pembayaran.
   - `amount` = total nominal yang ditagihkan.
   - `expired_at` = `now() + jenis_tagihan.va_expire_hours` (jika multi-jenis, ambil `va_expire_hours` terpendek).
4. Hasil disimpan di `bri_virtual_accounts` atau `bri_qris_payments` dengan `status='WAITING'`.

### 2. Webhook Callback (`POST /webhook/bri/payment-notification`)
1. Verifikasi tanda tangan digital: `$gateway->verifyCallbackSignature($request)`. Jika gagal, return 401.
2. Cari record `bri_virtual_accounts` / `bri_qris_payments` berdasarkan referensi.
3. Update `status = 'PAID'`, simpan `callback_payload`.
4. Update `pembayaran.status = 'lunas'`.
5. Alokasikan pembayaran:
   - Untuk setiap `pembayaran_tagihan`: update tagihan terkait `$tagihan->paid_amount += $amount_allocated`, set `$tagihan->status = ($tagihan->paid_amount >= $tagihan->net_amount) ? 'lunas' : 'sebagian'`. Tagihan berstatus `dibatalkan` dilewati/dicegah dari alokasi.
   - Jika pembayaran memiliki `wallet_id` (top-up wallet): eksekusi `$wallet->topup($amount, $pembayaran)`.
6. **Idempotensi**: Jika status pembayaran sudah `lunas` / `PAID`, callback diabaikan dengan response sukses 200 tanpa mengeksekusi alokasi ganda.

### 3. Polling Fallback
Cron job `billing:poll-bri-status` dijalankan berkala (misal tiap 15 menit):
- Memeriksa transaksi `bri_virtual_accounts` / `bri_qris_payments` dengan `status='WAITING'` dan `expired_at > now()`.
- Memanggil `$gateway->checkStatus($reference)` untuk memastikan status terbaru di sistem bank jika webhook callback mengalami keterlambatan.

### 4. Transfer Manual
1. Orang tua mengunggah bukti transfer, menginput bank asal dan tanggal transfer.
2. Dibuat record `pembayaran` (`metode='transfer_manual'`, `status='menunggu_verifikasi'`) dan `manual_payment_requests` (`status='PENDING'`).
3. Admin memverifikasi di menu antrean:
   - **Approve**: Set `manual_payment_requests.status = 'APPROVED'`, catat reviewer & waktu, set `pembayaran.status = 'lunas'`, dan jalankan alokasi saldo/tagihan.
   - **Reject**: Set `manual_payment_requests.status = 'REJECTED'`, input alasan penolakan, set `pembayaran.status = 'ditolak'`.

### 5. Tunai (Cash di Loket)
Admin kasir menginput pembayaran langsung di loket:
- Input siswa, pilih tagihan yang dibayar, input nominal yang diterima.
- Sistem membuat record `pembayaran` (`metode='cash'`, `sumber='admin'`, `status='lunas'`) langsung tanpa melalui antrean verifikasi, lalu mengeksekusi alokasi tagihan secara instan.

## Yang TIDAK Termasuk Sub-project 4

- Kredensial production/live sandbox BRI asli (digunakan Mock gateway hingga kredensial siap).
- UI Parent Portal untuk pemilihan channel (dibangun di Sub-project 6).
- UI Admin untuk loket kasir & antrean verifikasi manual (dibangun bersamaan/setelah fondasi service selesai).

## Ambiguitas Terselesaikan

- [x] Kredensial gateway belum tersedia ? Menggunakan abstraksi `PaymentGatewayInterface` + `MockPaymentGateway`.
- [x] Idempotensi alokasi webhook ? Cek status `PAID`/`lunas` sebelum mutasi data.
- [x] Guard tagihan dibatalkan ? Tagihan `dibatalkan` tidak dapat dialokasikan pembayaran.
