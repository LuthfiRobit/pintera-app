# Integrasi BRI SNAP VA (Virtual Account) — Arah Inbound Design

## Konteks

Sub-project sebelumnya (`2026-08-14-bri-snap-qris-integration-design.md`) membangun integrasi QRIS asli — arahnya **outbound** (aplikasi kita memanggil BRI). VA (Virtual Account) arahnya **kebalik**: nomor VA di-generate lokal oleh partner (bukan diminta ke BRI), dan BRI yang memanggil balik ke sistem kita (**inbound**) untuk dua hal: mengecek validitas nomor VA (Inquiry), dan melaporkan uang sudah masuk (Payment).

Informasi arsitektural berikut sudah **terkonfirmasi resmi** dari form onboarding BRI ("Kebutuhan akses sandboxing SNAP BI BRI", tabel "Briva Online"), bukan lagi dugaan:

- Kita adalah **token issuer** untuk arah inbound ini — BRI meminta token ke endpoint kita (`https://{domain}/snap/v1.0/access-token/b2b`), bukan sebaliknya.
- `client_id` dan `client_secret` untuk arah inbound ini **kita generate sendiri** (alphanumeric, maks 75 karakter) dan diserahkan ke BRI (lewat email terpassword ke PIC BRI) — ini **kredensial baru, terpisah** dari `BRI_SNAP_CLIENT_ID`/`BRI_SNAP_CLIENT_SECRET` yang sudah ada di `.env` (yang itu untuk arah outbound QRIS).
- Dua endpoint inbound lain yang harus kita sediakan: `https://{domain}/snap/v1.0/transfer-va/inquiry` dan `https://{domain}/snap/v1.0/transfer-va/payment`.
- `{domain}` harus HTTPS publik saat didaftarkan ke BRI — belum tersedia saat ini. Scope ini fokus pada **membangun dan menguji secara lokal** (simulasi lewat Postman, seperti pola pengujian QRIS sebelumnya); pendaftaran domain ke form BRI dan pengujian sungguhan dengan BRI adalah langkah terpisah di luar scope ini.

## Tujuan

1. Setiap siswa punya **satu nomor Virtual Account tetap** (bukan nomor berbeda per transaksi) yang bisa dipakai kapan saja untuk top-up saldo maupun membayar tagihan.
2. Sistem kita bisa menjawab pertanyaan BRI ("nomor VA ini punya siapa?") secara instan dan aman, tanpa mengubah data apa pun (read-only).
3. Sistem kita bisa menerima laporan "uang sudah masuk" dari BRI, menambah saldo wallet siswa, lalu mencoba melunasi tagihan jatuh tempo secara otomatis (memakai `AutoAllocationEngine` yang sudah ada) — **tanpa pernah kehilangan uang yang sudah masuk**, bahkan kalau proses pelunasan otomatis gagal.
4. Sistem kita aman dari laporan duplikat (BRI mengirim laporan yang sama dua kali) dan dari pemanggilan oleh pihak yang bukan BRI.

## Keputusan Desain (Hasil Diskusi)

### 1. Satu VA Tetap per Siswa, Tidak Ada VA Sementara per Tagihan

Berbeda dari `MockPaymentGateway` yang punya dua jenis VA (`BILL_DIRECT` sementara per tagihan, `WALLET_PERMANENT` tetap per siswa), implementasi BRI asli **hanya memakai VA tetap** (setara `WALLET_PERMANENT`). Baik masuk dari menu "Top Up" maupun dari "Tagihan → pilih tagihan → VA", nomor VA yang ditampilkan **selalu sama** untuk siswa yang sama. Perbedaannya hanya nominal yang **disarankan** di layar (kosong/bebas untuk Top Up, sejumlah total tagihan terpilih untuk checkout tagihan) — nominal ini murni informasi di layar kita, BUKAN sesuatu yang divalidasi ketat oleh BRI, karena BRI hanya tahu "ada transfer masuk sekian ke VA sekian", tidak tahu itu untuk tagihan yang mana.

**Dampak pada checkout flow yang sudah ada**: jalur "Bayar via VA" dari halaman Tagihan (`POST /keuangan/checkout/va`) TIDAK LAGI membuat baris `Pembayaran`/`BriVirtualAccount` baru per percobaan checkout (berbeda dari sekarang, yang membuat `BILL_DIRECT` VA baru setiap kali). Sebagai gantinya, aksi ini menjadi murni informational: ambil/buat VA tetap siswa (`PaymentService::getOrCreatePermanentVa()`, sudah ada), tampilkan nomor VA + saran nominal (total tagihan terpilih). Tidak ada state "menunggu pembayaran" yang dilacak per percobaan checkout VA — sama seperti alur Top Up yang sudah ada. Halaman status/polling (`keuangan.checkout.status`, dsb.) yang saat ini dipakai VA/QRIS **tetap dipakai untuk QRIS**, tapi TIDAK berlaku untuk VA lagi setelah perubahan ini.

**Keputusan tambahan (dikonfirmasi setelah eksplorasi kode lebih dalam)**: fitur "Top-Up Bundling" (sub-project 6c2 — bayar tagihan + tambah top-up sekaligus dalam satu transfer VA dengan nominal gabungan yang persis dicocokkan BRI) **SENGAJA DIHAPUS untuk jalur VA**, karena fitur itu bergantung pada VA per-transaksi dengan nominal exact-match yang sudah tidak ada lagi. Ini konsekuensi yang disadari dan disetujui, bukan efek samping tak terduga. **Bundling via QRIS TIDAK terpengaruh** — QRIS tetap memakai `BriQrisPayment` per-transaksi dengan nominal exact, arsitekturnya independen dari VA. `PaymentService::createVaPayment()` dan `createVaPaymentWithTopup()` (beserta jalur `va_bri` di `CheckoutController::findPendingPembayaranFor()`) dihapus sebagai bagian dari sub-project ini. Sekitar 10 file test yang menguji perilaku VA lama (bundling, webhook, reconciliation) perlu dihapus atau ditulis ulang — daftar exact & rincian per-file ada di implementation plan, bukan di spec ini.

### 2. Nomor VA Dibuat Lokal (Tidak Ada Panggilan ke BRI)

`BriSnapGateway::createVirtualAccount()` (saat ini masih `throw`) diimplementasikan sebagai fungsi lokal murni — TIDAK memanggil BRI sama sekali. Formatnya mengikuti spesifikasi SNAP: `virtualAccountNo = partnerServiceId (8 digit) + customerNo (kita pilih, sampai 20 digit)`. `partnerServiceId` berasal dari `X-PARTNER-ID` yang BRI berikan saat onboarding (belum kita punya — nilai config kosong/placeholder untuk saat ini, sama seperti `merchant_id`/`terminal_id` di sub-project QRIS). `customerNo` kita tentukan sendiri secara deterministik dari `siswa->id` (zero-padded), sehingga nomor VA siswa selalu bisa dihitung ulang tanpa perlu tabel lookup terpisah.

### 3. Tahap Inquiry — Read-Only Mutlak

Endpoint Inquiry (`/snap/v1.0/transfer-va/inquiry`) **tidak boleh mengubah data apa pun**: tidak menambah saldo, tidak mengubah status tagihan, tidak membuat baris data baru. Hanya: cari `virtualAccountNo` → kalau ketemu, kembalikan nama siswa + saran nominal (kalau ada tagihan jatuh tempo yang jadi acuan); kalau tidak ketemu, kembalikan status "tidak valid". Ini query baca-saja yang harus cepat (target < 1–2 detik, jauh di bawah SLA BRI <10 detik) — tidak boleh ada proses berat/transaksi database di endpoint ini.

### 4. Tahap Payment — Idempotent, Tidak Pernah Kehilangan Uang

Endpoint Payment (`/snap/v1.0/transfer-va/payment`) mengikuti alur:

```
Terima laporan pembayaran dari BRI (bawa nomor unik: paymentRequestId)
        │
        ▼
Sudah pernah diproses paymentRequestId ini? ──Ya──▶ Jawab sukses lagi (idempotent, aman diulang)
        │ Belum
        ▼
Tambah saldo wallet siswa (lewat Wallet::topup() yang sudah ada — row-locked, aman dari race condition)
        │
        ▼
Coba lunasi tagihan jatuh tempo otomatis (AutoAllocationEngine, sudah ada)
        │
   ┌────┴────┐
 Berhasil   Gagal (exception apa pun)
   │          │
Tagihan   Saldo TETAP bertambah (tidak di-rollback),
lunas     error dicatat ke log untuk ditinjau manual
```

Kunci desain: penambahan saldo dan pencatatan "paymentRequestId sudah diproses" harus terjadi dalam SATU transaksi database yang sukses/gagal bersama-sama (supaya tidak ada kondisi "tercatat sudah diproses tapi saldo belum bertambah"). Percobaan pelunasan otomatis (`AutoAllocationEngine`) dijalankan SETELAH transaksi itu commit, sebagai langkah terpisah yang dibungkus `try/catch` sendiri — persis pola yang sudah dipakai `BriWebhookController` untuk `WALLET_PERMANENT` saat ini (lihat `app/Http/Controllers/Api/BriWebhookController.php:135-148`) dan `ReconcilePayments::retryFailedTopups()` untuk retry kalau gagal.

**Duplikasi/nomor unik**: perlu tabel/kolom baru untuk menyimpan `paymentRequestId` yang sudah diproses (unique constraint di database, bukan sekadar cek aplikasi, supaya aman dari race condition kalau BRI kebetulan mengirim ulang nyaris bersamaan).

### 5. Penanganan Timeout — Tidak Ada Mekanisme Pembatalan di Sisi Kita

Kalau proses Payment di sisi kita memakan waktu lebih dari SLA BRI (<10 detik) atau gagal merespons, kita **tidak membangun mekanisme pembatalan sendiri**. Uang yang sudah didebit dari rekening pengirim tidak otomatis kembali seketika di level bank — BRI akan retry pengiriman laporan, atau baru terselesaikan lewat proses rekonsiliasi H+1 (sesuai catatan resmi BRI: *"Transaksi yang timeout perlu proses rekonsiliasi menggunakan API Account Statement/CMS Account Statement/MT940 File"*). Karena proses Payment kita sudah didesain idempotent (poin 4), retry atau pengecekan ulang dari BRI kapan pun akan otomatis dijawab benar tanpa proses dobel — jadi kita tidak perlu membangun apa pun tambahan untuk skenario ini.

### 6. Keamanan — Sederhana Dulu, Didesain Gampang Di-upgrade

**Diputuskan eksplisit**: mulai dari skema validasi sederhana (cocokkan `client_id`/`client_secret` yang kita generate dan berikan ke BRI), BUKAN asymmetric signature (SHA256withRSA) penuh — karena form onboarding resmi BRI untuk "Briva Online" hanya meminta kolom `client ID`/`client Secret`, TIDAK ada kolom submit public key (berbeda dari proses SNAP Key untuk QRIS yang eksplisit ada langkah itu). Ini kontradiksi dengan asumsi awal bahwa arah inbound wajib asymmetric — dicatat sebagai **pertanyaan terbuka ke BRI** (lihat bagian Batasan di bawah), bukan diasumsikan sepihak.

Supaya tidak jadi kerja dua kali kalau BRI konfirmasi ternyata wajib asymmetric, komponen "verifikasi identitas pemanggil" harus **dipisahkan dengan jelas** dari logic bisnis Inquiry/Payment — sebuah unit tersendiri yang bisa diganti isinya (dari "cocokkan client_secret" menjadi "verifikasi signature RSA") tanpa mengubah endpoint Inquiry/Payment itu sendiri.

Alur token sederhana (versi awal):
```
BRI kirim client_id + client_secret ke endpoint token kita
        │
        ▼
Cocok dengan yang tersimpan di config kita? ──Tidak──▶ Tolak (401)
        │ Ya
        ▼
Terbitkan token acak, simpan (cache, ~15 menit), kembalikan ke BRI
        │
        ▼
BRI pakai token itu di setiap panggilan Inquiry/Payment berikutnya
        │
        ▼
Kita cek: token ini pernah kita terbitkan & belum kadaluarsa? ──Tidak──▶ Tolak (401)
```

### 7. IP Whitelisting — Dicatat, Bukan Bagian dari Kode Aplikasi

Pembatasan supaya server kita hanya menerima koneksi dari IP resmi BRI adalah pengaturan infrastruktur (firewall/reverse proxy), bukan kode Laravel. Dicatat sebagai **item checklist deployment** terpisah, bukan bagian dari implementasi sub-project ini — kita belum punya daftar IP resmi BRI (bukan di `bri-api.md`, bukan di form onboarding yang sudah dilihat).

## Komponen

- **`BriInboundTokenService`** (baru) — generate & validasi token untuk BRI, mengimplementasikan skema sederhana dari poin 6. Disusun sebagai interface/kelas yang bisa diganti nanti.
- **`BriVaInboundController`** (baru) — 3 route: token, inquiry, payment. Endpoint Inquiry murni baca (poin 3). Endpoint Payment idempotent + resilient (poin 4).
- **`BriSnapGateway::createVirtualAccount()`** — diimplementasikan (poin 2), generate lokal, tidak ada panggilan HTTP ke BRI.
- **`CheckoutController@va`** — disederhanakan mengikuti poin 1: tidak lagi membuat `Pembayaran`/`BriVirtualAccount` baru per percobaan, murni menampilkan VA tetap + saran nominal.
- **Migration baru**: tabel/kolom untuk menyimpan `paymentRequestId` yang sudah diproses (idempotency ledger, poin 4).
- **`app/Http/Controllers/Api/BriWebhookController.php`** — ini placeholder API BRI generasi lama (Non-SNAP, ditemukan lewat analisis sebelumnya bentuknya `BRI-Signature`/`BrivaNo`/`CustCode`, bukan SNAP BI) dan sekarang jadi dead code sepenuhnya setelah endpoint Payment SNAP yang baru menggantikan fungsinya untuk VA. **Dihapus** sebagai bagian dari sub-project ini (bukan dibiarkan menggantung), termasuk route-nya di `routes/web.php:8`.

## Testing

1. **Test otomatis** (masuk suite `php artisan test`, tidak menghubungi BRI asli):
   - Inquiry tidak mengubah data apa pun (assert tidak ada perubahan di database sebelum/sesudah).
   - Payment jalur normal: saldo bertambah, tagihan jatuh tempo otomatis lunas.
   - Payment dengan `paymentRequestId` yang sama dikirim dua kali → saldo hanya bertambah sekali.
   - Payment saat `AutoAllocationEngine` gagal (di-mock throw) → saldo tetap bertambah, tagihan tetap tertunggak, tidak ada exception yang bocor ke response BRI.
   - Token/Inquiry/Payment ditolak (401) kalau `client_id`/`client_secret`/token salah atau kadaluarsa.
2. **Simulasi manual lewat Postman**: berperan sebagai BRI — minta token ke endpoint kita sendiri, lalu panggil Inquiry dan Payment kita sendiri pakai token itu. Pengganti pengujian sungguhan dengan BRI selama domain publik belum ada.
3. **Di luar scope**: pengujian dengan BRI sungguhan (butuh domain publik terdaftar di form onboarding) — langkah terpisah setelah sub-project ini selesai.

## Batasan & Yang Sengaja Tidak Dikerjakan

- **Pendaftaran domain publik ke form BRI** dan pengujian end-to-end dengan BRI sungguhan — di luar scope, langkah manual terpisah setelah kode ini siap.
- **IP Whitelisting** — dicatat sebagai checklist deployment, bukan kode aplikasi; daftar IP resmi BRI belum kita punya.
- **Asymmetric signature (SHA256withRSA) untuk endpoint token inbound** — TIDAK diimplementasikan di sub-project ini karena kontradiksi dengan form onboarding resmi (lihat poin 6). Desain dibuat gampang di-upgrade, tapi implementasi RSA menunggu konfirmasi BRI.
- **Base URL / IP Private untuk koneksi leased line** — baris di form onboarding BRI yang belum jelas wajib atau tidaknya untuk sandbox; tidak diasumsikan wajib di sub-project ini.

### Pertanyaan Terbuka ke BRI (Konsolidasi)

1. Untuk endpoint token B2B arah inbound: wajib asymmetric signature (butuh kami submit public key), atau cukup `client_id`/`client_secret` seperti tertera di form onboarding?
2. Daftar IP resmi API Gateway BRI, untuk kami whitelist di server kami.
3. Apakah "IP Private (untuk koneksi leased line)" wajib diisi untuk akses sandbox, atau hanya relevan untuk production/volume tinggi?
4. Nilai `X-PARTNER-ID` kami untuk sandbox (dasar `partnerServiceId` nomor VA).
