# Modul Sistem Keuangan Sekolah Dinamis — Sub-project 4: Payment Channels

> Status: Disetujui — siap ke Implementation Plan (dieksekusi setelah Sub-project 3 selesai & diverifikasi).

## Konteks & Dependensi

Bergantung pada Sub-project 1 (`pembayaran` extended, `pembayaran_tagihan`), Sub-project 3 (`wallets`, `wallet_mutasi`, `Wallet::topup()`/`debit()`). Sub-project ini mengisi jalur pembayaran nyata: BRI VA/QRIS, transfer manual + approval, cash. **Belum ada kredensial sandbox BRI** saat spec ini ditulis — desain memakai abstraksi gateway supaya sub-project bisa selesai & didemo penuh dengan mock, tanpa menulis ulang kode saat kredensial asli tersedia.

## Tujuan Sub-project 4

Orang tua bisa membayar tagihan/top-up wallet lewat VA BRI, QRIS, transfer manual (dengan verifikasi admin), atau ditangani admin sebagai cash di loket. Status pembayaran terverifikasi otomatis (callback + polling fallback untuk channel BRI).

## Abstraksi Payment Gateway

```php
interface PaymentGatewayInterface
{
    public function createVirtualAccount(array $params): VirtualAccountResult; // amount nullable utk WALLET_PERMANENT
    public function createQris(array $params): QrisResult;
    public function checkStatus(string $reference): PaymentStatusResult;
    public function verifyCallbackSignature(Request $request): bool;
}
```

- **`BriSnapGateway`**: implementasi asli memakai 4 endpoint sandbox yang disebut di PRD awal (`virtual-account-snap-simulation-sandbox-v1.0`, `payment-transfer-va-snap-sandbox-v1.1`, `transaction-status-snap-simulation-sandbox-v1.0`, `qris-mpm-dinamis-sandbox-v1.1`). Kredensial (`client_id`, private key untuk signature SNAP) dibaca dari `config('services.bri')` / `.env` — **belum diisi**, kelas ini ditulis siap pakai tapi tidak divalidasi end-to-end sampai kredensial tersedia.
- **`MockPaymentGateway`**: generate VA number/QR code dummy lokal (format konsisten tapi bukan dari BRI), `checkStatus` selalu WAITING sampai disimulasikan lunas lewat endpoint dev `POST /dev/simulate-payment/{reference}` (hanya aktif di env `local`/`testing`), `verifyCallbackSignature` selalu `true`.
- Binding: `config('services.bri.gateway')` = `mock` (default semua environment) | `bri_snap`. Diganti ke `bri_snap` + isi `.env` begitu kredensial BRI didapat — tidak ada perubahan kode lain yang dibutuhkan karena seluruh call site bergantung ke `PaymentGatewayInterface`, bukan implementasi konkret.

## Perubahan Skema

### 1. Tabel Baru — `bri_virtual_accounts`

```
bri_virtual_accounts
├─ id
├─ pembayaran_id      FK → pembayaran, cascade delete
├─ wallet_id           FK → wallets, NULLABLE (diisi utk VA permanen top-up)
├─ va_type              ENUM('WALLET_PERMANENT','BILL_DIRECT')
├─ va_number
├─ amount               decimal(12,2) NULLABLE  (NULL untuk WALLET_PERMANENT — nominal top-up bebas)
├─ expired_at            timestamp NULLABLE  (NULL untuk WALLET_PERMANENT)
├─ status                 ENUM('PERMANENT','WAITING','PAID','EXPIRED')
├─ callback_payload        json NULLABLE
└─ timestamps
```

### 2. Tabel Baru — `bri_qris_payments`

```
bri_qris_payments
├─ id
├─ pembayaran_id      FK → pembayaran, cascade delete
├─ qris_type            ENUM('TOPUP','DIRECT')
├─ amount
├─ qr_code
├─ expired_at
├─ status                ENUM('WAITING','PAID','EXPIRED')
├─ callback_payload        json NULLABLE
└─ timestamps
```

### 3. Tabel Baru — `manual_payment_requests`

```
manual_payment_requests
├─ id
├─ pembayaran_id       FK → pembayaran, cascade delete
├─ requested_by          FK → users
├─ amount
├─ transfer_proof_path
├─ bank_origin           string NULLABLE
├─ transfer_date          date
├─ status                  ENUM('PENDING','APPROVED','REJECTED')
├─ reviewed_by             FK → users, NULLABLE
├─ reviewed_at
├─ rejection_reason         text NULLABLE
└─ timestamps
```

### 4. `pembayaran` — Status Tambahan

`status` existing (`menunggu_verifikasi|lunas|ditolak`) ditambah `menunggu_pembayaran` (VA/QRIS dibuat, belum ada uang masuk — beda dari `menunggu_verifikasi` yang berarti "uang sudah ditransfer, tunggu admin cek bukti").

## Alur per Channel

### BRI VA / QRIS (single atau batch multi-select)

1. Orang tua pilih tagihan (1 atau banyak, hasil generate 1 VA/QRIS gabungan sesuai keputusan awal) atau pilih top-up wallet.
2. Buat `pembayaran` (`metode='va_bri'` atau `'qris'`, `status='menunggu_pembayaran'`, `sumber='orang_tua'` — nilai enum baru yang ditambahkan di Sub-project 1 khusus konteks siswa aktif, berbeda dari `'calon_siswa'` yang PPDB-only), `pembayaran_tagihan` untuk tiap tagihan terpilih (kosong jika top-up murni).
3. Panggil `Gateway::createVirtualAccount()`/`createQris()` — `amount` = total tagihan terpilih (atau nominal bebas untuk top-up), `expired_at` = `now() + jenis_tagihan.va_expire_hours` (ambil dari tagihan dengan `va_expire_hours` terkecil jika campuran beberapa jenis tagihan; NULL/no-expire untuk top-up wallet karena pakai VA permanen).
4. Simpan hasil ke `bri_virtual_accounts`/`bri_qris_payments` (`status=WAITING`).
5. Tampilkan nomor VA/QR + timer countdown ke orang tua (detail UI di Sub-project 6).

### Callback

`POST /webhook/bri/payment-notification`:
1. `Gateway::verifyCallbackSignature($request)` — gagal → response 401, log percobaan mencurigakan.
2. Cari `bri_virtual_accounts`/`bri_qris_payments` by `va_number`/reference dari payload.
3. Update `status=PAID`, `callback_payload` = raw payload.
4. Update `pembayaran.status='lunas'`.
5. Jika ada `pembayaran_tagihan` → untuk tiap `tagihan` terkait: `paid_amount += amount_allocated`, update `status` (`lunas` jika `paid_amount >= net_amount`, `sebagian` jika kurang).
6. Jika `wallet_id` terisi (top-up wallet) → panggil `Wallet::topup(amount, pembayaran)` (Sub-project 3) — otomatis memicu Auto-Allocation Engine jika toggle lembaga ON.
7. Idempotent: callback yang sama (reference sama, sudah `PAID`) di-skip tanpa efek ganda (cek status dulu sebelum proses).

### Polling Fallback

Cron `Schedule::command(PollBriPaymentStatus::class)->everyFifteenMinutes()`: ambil semua `bri_virtual_accounts`/`bri_qris_payments` `status=WAITING AND expired_at > now()`, panggil `Gateway::checkStatus()` — kalau ternyata PAID di sisi BRI tapi callback belum masuk, jalankan proses yang sama seperti langkah callback di atas (reuse service yang sama, bukan duplikasi logic).

### Transfer Manual

1. Orang tua upload bukti transfer (`transfer_proof_path`), isi `bank_origin`, `transfer_date`.
2. `pembayaran` (`metode='transfer_manual'`, `status='menunggu_verifikasi'`) + `manual_payment_requests` (`status='PENDING'`) + `pembayaran_tagihan`.
3. Admin buka antrean verifikasi → **Approve**: `manual_payment_requests.status='APPROVED'`, `reviewed_by`/`reviewed_at` terisi, lalu jalankan proses alokasi yang sama seperti callback sukses (poin 4-6 di atas, reuse service). **Reject**: `status='REJECTED'`, wajib isi `rejection_reason`, `pembayaran.status='ditolak'` — tagihan tetap belum lunas, orang tua bisa coba lagi.

### Cash

Admin input langsung di halaman loket: cari siswa (`identifier_method='manual'` — lookup nama/NIS; kolom `nfc` disiapkan tapi belum ada implementasi hardware), pilih tagihan yang dilunasi, input nominal diterima → `pembayaran` (`metode='cash'`, `sumber='admin'`, `status='lunas'` langsung — tanpa antrean verifikasi karena uang sudah diterima fisik saat itu) + `pembayaran_tagihan` + alokasi langsung (poin 4-5 alur callback, tanpa langkah wallet kecuali admin secara eksplisit pilih "setor ke wallet" alih-alih "bayar tagihan langsung").

## Konfigurasi

`config/services.php` tambah:
```php
'bri' => [
    'gateway' => env('BRI_GATEWAY', 'mock'), // mock | bri_snap
    'client_id' => env('BRI_CLIENT_ID'),
    'private_key' => env('BRI_PRIVATE_KEY'),
    'base_url' => env('BRI_BASE_URL', 'https://sandbox.partner.api.bri.co.id'),
],
```

## Yang TIDAK Termasuk Sub-project 4

- Kredensial BRI asli & validasi end-to-end ke sandbox sungguhan — menunggu akses didapat, ditangani sebagai follow-up terpisah begitu tersedia (ganti config, tidak perlu re-desain).
- UI tampilan VA/QR/timer countdown untuk orang tua, halaman antrean verifikasi admin, halaman loket cash — struktur data & service sudah siap, UI-nya di Sub-project 6 (parent-facing) & sebagian di Sub-project 2/dashboard admin (verifikasi & loket adalah UI admin, bisa dikerjakan paralel dengan Sub-project 6 karena tidak saling bergantung).
- Kartu siswa/NFC fisik di loket (kolom disiapkan Sub-project 1, implementasi ditunda).

## Ambiguitas Terselesaikan

- [x] Kredensial BRI belum ada → Desain dengan `PaymentGatewayInterface` + `MockPaymentGateway`, siap ganti binding tanpa rewrite
- [x] Verifikasi status → Callback utama + polling fallback tiap 15 menit
- [x] Idempotency callback → Cek status existing sebelum proses, reference sama diproses sekali

## Ambiguitas Sisa (untuk sub-project berikutnya)

- [ ] `va_expire_hours` untuk batch berisi campuran beberapa `jenis_tagihan` dengan expire berbeda → sub-project ini pakai "ambil yang terkecil", perlu dikonfirmasi ulang saat UI Sub-project 6 dibangun apakah ini yang diinginkan atau harus dipisah jadi beberapa VA
- [ ] Halaman/permission spesifik untuk antrean verifikasi transfer manual & loket cash (role apa yang boleh akses) — didetailkan bersamaan UI admin di Sub-project 2 lanjutan atau sub-project ini sendiri saat masuk implementation plan
