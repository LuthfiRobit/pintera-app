# Spec — Keuangan Sub-project 6b: Rekap Tagihan Aktif & Checkout Multi-Channel

**Status:** Draft, menunggu review.
**Depends on:** Sub-project 6a (SHIPPED — lihat `.agents/logs/keuangan-06a-fondasi-dashboard.md`), Sub-project 04 (payment channels backend), Sub-project 05 (notifikasi).
**Next in sequence:** Sub-project 6c (riwayat transaksi + kwitansi PDF + admin logo), 6d (preferensi notifikasi).

## 1. Ringkasan

Sub-project kedua dari 4 pemecahan Keuangan Sub-project 6 (Parent Dashboard & Kwitansi). Membangun halaman rekap tagihan aktif dan alur checkout multi-channel (VA BRI, QRIS, Saldo Wallet, Transfer Manual) untuk orang tua (`orang_tua` role, guard `web`), menyambungkan backend payment-channel yang sudah ada dari Sub-project 04/05 ke UI nyata pertama kalinya.

## 2. Keputusan Scope (dikonfirmasi user)

- **Cicilan: di luar scope 6b.** Tidak ada UI/logic untuk `SkemaCicilan`/`Tagihan::cicilan()` di sub-project ini.
- **Auto-debit dual-mode: diimplementasikan sesuai spec asli.** Halaman rekap tagihan menampilkan banner informatif berbeda tergantung `SystemSetting::getResolved('auto_debit_enabled', $lembagaId)` — tapi checkout manual tetap tersedia di kedua mode.
- **Top-up wallet: dibundel dengan checkout tagihan**, bukan alur terpisah. Opsi "Sekalian Top Up Wallet" muncul sebagai field tambahan opsional di halaman checkout, di atas tab channel.

## 3. Arsitektur & Routing

Semua route baru masuk ke grup `keuangan.*` yang sudah ada di `routes/web.php` (middleware `['auth','verified','permission:keuangan.akses','resolve.active.siswa']`, dari 6a):

| Method | Path | Name | Controller@action |
|---|---|---|---|
| GET | `/keuangan/tagihan` | `keuangan.tagihan.index` | `TagihanController@index` |
| GET | `/keuangan/checkout` | `keuangan.checkout.create` | `CheckoutController@create` |
| POST | `/keuangan/checkout/va` | `keuangan.checkout.va` | `CheckoutController@va` |
| POST | `/keuangan/checkout/qris` | `keuangan.checkout.qris` | `CheckoutController@qris` |
| POST | `/keuangan/checkout/wallet` | `keuangan.checkout.wallet` | `CheckoutController@wallet` |
| POST | `/keuangan/checkout/transfer` | `keuangan.checkout.transfer` | `CheckoutController@transfer` |
| GET | `/keuangan/checkout/{pembayaran}` | `keuangan.checkout.show` | `CheckoutController@show` |
| GET | `/keuangan/checkout/{pembayaran}/status` | `keuangan.checkout.status` | `CheckoutController@status` (JSON, untuk polling) |

Backend baru: **satu method baru** di `PaymentService` (existing file, tidak dibuat file service baru):

```php
public function createWalletPayment(Siswa $siswa, Collection $tagihans): Pembayaran
```

Semua channel lain (VA, QRIS, transfer manual) 100% reuse method existing dari Sub-project 04/05 (`createVaPayment`, `createQrisPayment`, `createManualPayment`). **Tidak ada perubahan** pada `AutoAllocationEngine`, `Wallet::topup()`/`debit()` (dipakai apa adanya), atau webhook BRI.

## 4. Halaman & Komponen UI

### 4.1 Rekap Tagihan Aktif (`GET /keuangan/tagihan`)

- Layout `<x-app-layout>`, konsisten topbar/sidebar dari 6a.
- List tagihan `belum_bayar`/`sebagian` milik `active_siswa_id` (session, dari middleware `ResolveActiveSiswa`), urut `jatuh_tempo` ascending, eager-load `jenisTagihan`.
- Tiap baris: checkbox, nama jenis tagihan, sisa jumlah (`net_amount - paid_amount`), status badge, jatuh tempo.
- Banner auto-debit info jika `auto_debit_enabled` true untuk lembaga siswa — tidak menyembunyikan checkbox, hanya informasi tambahan.
- Tombol "Bayar Terpilih" muncul saat ≥1 checkbox dicentang (Alpine), menampilkan total nominal terpilih, submit ke `keuangan.checkout.create` (GET dengan query `tagihan_ids[]`, atau form POST yang redirect — implementasi detail di plan).

### 4.2 Pilih Channel Pembayaran (`GET /keuangan/checkout`)

**Pola: tab horizontal, sama seperti `resources/views/admin/guru/edit.blade.php`** (bukan wizard multi-step, bukan scroll panjang):

- `x-data="{ activeTab: 'va' }"`.
- Header tabs: **VA BRI** | **QRIS** | **Saldo Wallet** | **Transfer Manual**, style identik guru edit (`border-brand-600 text-brand-600` aktif vs `border-transparent text-gray-500` non-aktif).
- Ringkasan tagihan terpilih + opsi "Sekalian Top Up Wallet" (input nominal opsional) ditampilkan **di atas tab**, berlaku untuk semua channel kecuali tab Wallet (top-up tidak relevan saat sumber dananya wallet itu sendiri — field top-up disembunyikan/disabled saat tab Wallet aktif).
- Tab "Saldo Wallet": tombol submit di dalam tab-nya sendiri `disabled` + pesan "Saldo tidak cukup, kurang Rp X" jika saldo < total (tab tetap bisa dibuka untuk dilihat, hanya submit yang diblok).
- Content per tab via `@include('keuangan.checkout.tabs.va')`, `.qris`, `.wallet`, `.transfer` — tiap partial punya form/submit sendiri ke route channel masing-masing.

### 4.3 Menunggu Pembayaran (`GET /keuangan/checkout/{pembayaran}`) — hanya untuk VA & QRIS

- VA: nomor VA + nominal + tombol copy + instruksi.
- QRIS: gambar QR (dari mock/real gateway) + nominal.
- Polling status via `GET .../status` (JSON `{status: "..."}`, `select('status')` saja — ringan) tiap 5 detik, Alpine `setInterval`.
- Countdown timer client-side dari `expired_at` — kosmetik saja. Saat habis: tampilkan state "kadaluarsa" + tombol "Buat Ulang" kembali ke `keuangan.checkout.create`. Transisi status sebenarnya tetap dari backend (`ReconcilePayments`, existing scheduled command), UI tidak mengubah status apa pun sendiri.

### 4.4 Alur Wallet & Transfer Manual (tanpa halaman menunggu)

- **Wallet**: submit langsung proses (instant settle) → redirect ke halaman sukses/kwitansi ringkas.
- **Transfer Manual**: submit dengan upload bukti (`transfer_proof`, image/pdf, max 2MB, `Storage::disk('public')` — pola dari modul SPMB) → redirect ke halaman "menunggu verifikasi admin" (beda dari halaman VA/QRIS, tidak ada countdown karena verifikasi manual, bukan otomatis via webhook).

## 5. Data Flow & Backend Detail

### 5.1 `TagihanController@index`
- Query tagihan aktif milik `active_siswa_id`, dengan `withoutGlobalScope(TenantScope::class)` + otorisasi eksplisit lewat relasi `Auth::user()->orangTua->siswa()->where('id', $activeSiswaId)->exists()` sebelum query tagihan — pola identik `KasusController`/`DashboardController` 6a. Wajib karena acting user `orang_tua` punya `lembaga_id = null`, sehingga `TenantScope` bawaan akan mengembalikan hasil kosong/salah tanpa override ini.
- Index composite `(siswa_id, status, jatuh_tempo)` pada tabel `tagihan` — tambahkan migrasi index-only jika belum ada.

### 5.2 `CheckoutController@va` / `@qris`
- Terima `tagihan_ids[]` + optional `topup_amount`.
- **Re-fetch & re-validasi** tagihan dari DB by ID (bukan percaya total dari form): pastikan semua `tagihan_id` milik `active_siswa_id` DAN masih berstatus `belum_bayar`/`sebagian` saat submit (guard ulang, bukan hanya saat load awal — mencegah race dengan channel lain).
- Panggil `PaymentService::createVaPayment($siswa, $tagihans)` / `createQrisPayment(...)` (existing, tidak diubah).
- Jika ada `topup_amount`: buat **`Pembayaran` kedua terpisah** via jalur top-up existing (bukan digabung ke satu record) — karena `createVaPayment`/`createQrisPayment` mengasumsikan Collection tagihan murni; memaksakan gabung akan mengubah kontrak method existing (dihindari sesuai constraint 6a: tidak mengubah service existing).
- **Idempotency guard**: sebelum membuat VA/QRIS baru, cek apakah sudah ada `Pembayaran` `status='menunggu'` belum-expired untuk kombinasi tagihan yang sama — cegah duplikasi dari klik ganda/refresh.
- Redirect ke `keuangan.checkout.show`.

### 5.3 `CheckoutController@wallet` (baru)
- `PaymentService::createWalletPayment()`:
  - `DB::transaction()` + `lockForUpdate()` pada row `Wallet` (pola sama persis `BriWebhookController`, Sub-project 04) — cegah race condition double-debit dari dua request paralel.
  - Validasi saldo cukup; jika tidak, throw/return error terkontrol (bukan biarkan saldo negatif).
  - `Wallet::debit()` (existing, dipakai apa adanya).
  - Buat `Pembayaran` dengan `metode='wallet_saldo'` (enum ini **sudah ada di skema** sejak migrasi `2026_08_10_160000_add_wallet_columns_to_pembayaran_table.php`, belum pernah dipakai), `status='berhasil'` langsung (instant, tanpa gateway/webhook).
  - Panggil `PaymentAllocationService::allocate($pembayaran)` (existing, pola sama jalur cash) untuk update status tagihan + fire notifikasi lunas.
- Controller re-fetch & re-validasi tagihan sama seperti 5.2 sebelum memanggil service.
- Redirect langsung ke halaman sukses (skip halaman menunggu).

### 5.4 `CheckoutController@transfer`
- Validasi upload via `FormRequest` (image/pdf, max 2MB).
- Re-fetch & re-validasi tagihan sama seperti 5.2.
- Panggil `PaymentService::createManualPayment($siswa, $tagihans, $data)` (existing, controller ini adalah caller nyata pertamanya) → status `menunggu_verifikasi`, buat `ManualPaymentRequest`.
- Redirect ke halaman "menunggu verifikasi admin".

### 5.5 `CheckoutController@show` / `@status`
- Authorize: `$pembayaran->siswa_id` termasuk salah satu anak `Auth::user()->orangTua` (cross-tenant IDOR guard — **wajib test eksplisit di setiap action**, pola paling sering berulang di proyek ini).
- `@status` return JSON minimal (`select('status')` saja, tanpa full model load) — dipanggil tiap 5 detik per user yang checkout, harus seringan mungkin untuk skala 700+ orang tua.

## 6. Error Handling

| Skenario | Penanganan |
|---|---|
| Saldo wallet tidak cukup | Validasi server-side sebelum service call, redirect back dengan error. Tombol submit tab Wallet juga sudah disabled di UI (jaring pengaman ganda). |
| Tagihan sudah lunas di channel lain saat submit (race) | Re-validasi status saat re-fetch → redirect ke rekap tagihan dengan pesan "Sebagian tagihan yang dipilih sudah lunas, silakan cek kembali". |
| Gateway VA/QRIS gagal | Try/catch di controller, redirect back dengan pesan generik + log exception (tidak expose error mentah gateway). |
| Upload bukti transfer gagal validasi | Standar `FormRequest` validation, error tampil di form yang sama. |
| Akses tagihan/pembayaran milik siswa/orang tua lain | 403 abort — cross-tenant/cross-parent authorization check wajib di setiap controller action, dengan test eksplisit dua-pihak (bukan fixture satu-pihak). |
| VA/QRIS expired | UI: state "kadaluarsa" + tombol "Buat Ulang". Backend: `ReconcilePayments` (existing, scheduled hourly) tetap source-of-truth transisi status. |

## 7. Performa (skala 700+ orang tua)

- Composite index `(siswa_id, status, jatuh_tempo)` pada `tagihan` (tambah migrasi jika belum ada).
- Eager-load relasi di semua list query (`->with('jenisTagihan')`), hindari N+1.
- Endpoint polling status hanya select kolom `status`, tidak load full model.
- `lockForUpdate()` pada `Wallet` dibatasi ke transaksi checkout wallet saja (bukan lock berlebihan), pola sama seperti webhook BRI existing.
- Tidak ada perubahan pada topbar/`NotificationFeedResolver` — beban query per-request yang sudah ada dari 6a tidak bertambah.

## 8. Testing Strategy

- **Feature tests** per controller action, happy path + validasi + **cross-parent/cross-siswa authorization test wajib** di setiap action (pola dua-pihak).
- **Concurrency test** untuk `createWalletPayment()`: buktikan `lockForUpdate()` mencegah double-debit pada request paralel.
- **Idempotency test** VA/QRIS: submit checkout 2x untuk tagihan sama → assert tidak ada 2 VA aktif dobel.
- **Manual browser verification (Playwright)**: extend `scripts/keuangan-6a-browser-check.mjs` dengan check minimal — tab channel bisa diklik/berpindah, dan satu jalur checkout (wallet, karena paling sederhana/instant) benar-benar berhasil end-to-end. Tidak perlu cover semua kombinasi channel di level Playwright (channel lain sudah tercover feature test PHP).
- **Regression test**: selama proses, jalankan `tests/Feature/Keuangan/` + test model terkait langsung (`Wallet`, `Pembayaran`, `Tagihan`, notifikasi) — bukan full-suite tiap task. **Full-suite dijalankan sekali di akhir**, terisolasi (tidak concurrent dengan proses test lain — pelajaran dari false-alarm 157/15-failure di sesi 6a), sebagai gerbang terakhir sebelum selesai.

## 9. Eksplisit di Luar Scope 6b

- Cicilan (`SkemaCicilan`).
- Riwayat transaksi & kwitansi PDF (→ 6c).
- Admin upload logo yayasan (→ 6c).
- Preferensi/pengaturan notifikasi, mark-as-read (→ 6d).
- Perubahan pada `AutoAllocationEngine`, `Wallet::topup()`/`debit()`, webhook BRI.
- Filter panel "Notifikasi Terbaru" ke anak aktif (item terbuka dari 6a, belum diambil di sini — masih butuh `siswa_id` di payload notifikasi Finance).
