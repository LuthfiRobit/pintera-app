# Spec — Keuangan Sub-project 6c2: Bundling Top-Up Wallet & Verifikasi Admin Transfer Manual

**Status:** Draft, menunggu review.
**Depends on:** Sub-project 04 (payment channels backend, `ManualPaymentRequest`, `PaymentGatewayInterface`), Sub-project 6b (SHIPPED — checkout multi-channel, `PaymentService::createVaPayment`/`createQrisPayment`), Sub-project 6c (SHIPPED — riwayat transaksi, kwitansi PDF).
**Next in sequence:** Sub-project 6d (preferensi notifikasi).

## 1. Ringkasan

Sub-project keempat dari pemecahan Keuangan Sub-project 6, menyelesaikan dua open item yang sengaja ditunda dari Sub-project 6b:

1. **Bundling top-up wallet saat checkout VA BRI/QRIS** — orang tua bisa sekalian top-up wallet dalam satu VA/QRIS yang sama dengan pembayaran tagihan.
2. **Halaman admin verifikasi bukti transfer manual** — backend approve/reject (`Admin\ManualPaymentController@approve/@reject`) **sudah ada dan tidak diubah**; yang dibangun di sini murni halaman listing + UI-nya (tidak ada sebelumnya).

## 2. Keputusan Scope & Arsitektur (dikonfirmasi user)

- **Bundling: 1 `Pembayaran`, bukan 2.** `Pembayaran.amount` = total tagihan + top-up. `PembayaranTagihan` hanya mencatat porsi tagihan. `topup_status='pending'` menandai ada sisa yang harus masuk wallet. Tidak ada migrasi/kolom penghubung baru.
- **Titik split-alokasi dipakai bersama** oleh `BriWebhookController`, `ReconcilePayments::reconcileWaitingPayments()`, dan `ReconcilePayments::retryFailedTopups()` — lewat satu method baru di `PaymentAllocationService`.
- **Bug nyata ditemukan & termasuk scope perbaikan**: `ReconcilePayments::retryFailedTopups()` saat ini me-retry `Wallet::topup()` dengan `$pembayaran->amount` PENUH, bukan sisa setelah alokasi tagihan — untuk pembayaran gabungan ini akan double-count saldo wallet. Diperbaiki sebagai bagian dari sub-project ini (satu-satunya tempat bug ini baru bisa ketrigger, karena sebelum sub-project ini tidak ada `Pembayaran` yang punya `pembayaranTagihan` DAN `topup_status` sekaligus).
- **Halaman admin listing**: mengadopsi pola UI/UX + interaksi AJAX-fragment dari `admin/mata-pelajaran/index.blade.php` (KPI card ringkas + filter card dengan debounced AJAX partial-swap + tabel `_daftar` terpisah + `pagination.tailadmin`) — bukan Alpine tab pattern seperti sub-project Keuangan sebelumnya.
- **Testing**: tidak ada full-suite run di akhir. Regression dibatasi ke `tests/Feature/Keuangan/`, test admin yang baru, dan model/service yang tersentuh (`Wallet`, `PaymentAllocationService`, `ReconcilePayments`).
- Backend approve/reject (`ManualPaymentController::approve()`/`reject()`) **tidak diubah sama sekali** — sudah punya guard konsistensi data, tenant-scoping, idempotency (cek status PENDING), dan notifikasi.

## 3. Arsitektur & Routing

**Bundling top-up** — tidak ada route baru; memperluas method existing di grup `keuangan.*`:

- `POST /keuangan/checkout/va` dan `/qris` (existing, `CheckoutController@va`/`@qris`) menerima input opsional `topup_amount`.

**Verifikasi Admin** — route baru di `routes/admin.php`, ditambahkan ke `Admin\ManualPaymentController` yang sudah ada (bukan controller baru):

| Method | Path | Name |
|---|---|---|
| GET | `/admin/manual-payment` | `admin.manual-payment.index` |
| POST | `/admin/manual-payment/{manualPaymentRequest}/approve` | `admin.manual-payment.approve` (existing, tidak diubah) |
| POST | `/admin/manual-payment/{manualPaymentRequest}/reject` | `admin.manual-payment.reject` (existing, tidak diubah) |

**File baru/diubah:**
- Modify: `app/Services/Finance/PaymentService.php` — tambah `createVaPaymentWithTopup()` dan `createQrisPaymentWithTopup()`.
- Modify: `app/Services/Finance/PaymentAllocationService.php` — tambah `topupSisaJikaAda(Pembayaran $pembayaran): void`.
- Modify: `app/Http/Controllers/Api/BriWebhookController.php` — panggil `topupSisaJikaAda()` setelah `allocate()` di kedua cabang (VA `BILL_DIRECT`, QRIS).
- Modify: `app/Console/Commands/ReconcilePayments.php` — `reconcileWaitingPayments()` panggil `topupSisaJikaAda()` setelah `allocate()`; `retryFailedTopups()` diganti untuk pakai method yang sama (bukan `$pembayaran->amount` mentah).
- Modify: `app/Http/Controllers/Keuangan/CheckoutController.php` — `va()`/`qris()` terima `topup_amount`.
- Modify: `resources/views/keuangan/checkout/tabs/va.blade.php`, `tabs/qris.blade.php` — kembalikan input "Sekalian Top Up Wallet" (dihapus di 6b).
- Modify: `resources/views/pdf/kwitansi.blade.php`, `resources/views/keuangan/riwayat/index.blade.php` — tampilkan baris top-up terpisah untuk pembayaran gabungan.
- Modify: `app/Http/Controllers/Admin/ManualPaymentController.php` — tambah method `index()`.
- Create: `resources/views/admin/manual-payment/index.blade.php`, `_daftar.blade.php`.
- Create: `resources/js/manual-payment-filter.js` (Alpine component, pola sama `mata-pelajaran-filter.js`), daftarkan di `resources/js/app.js`.
- Modify: `resources/views/layouts/sidebar.blade.php` — tambah menu "Verifikasi Transfer Manual".

## 4. Halaman & Komponen

### 4.1 Checkout — input top-up (VA & QRIS tab)
- Field "Sekalian Top Up Wallet (opsional)" muncul kembali di kedua tab (tidak di tab Wallet/Transfer — top-up tidak relevan di sana, sudah diputuskan di 6b).
- Ringkasan di atas tab menampilkan total gabungan (tagihan + top-up) kalau field diisi.

### 4.2 Halaman "Menunggu Pembayaran" — tampilkan rincian gabungan
- Kalau `Pembayaran` punya `topup_status !== 'none'` DAN ada `pembayaranTagihan`, tampilkan 2 baris ringkasan: "Tagihan: Rp X" + "Top Up Wallet: Rp Y" di atas nomor VA/QR — supaya orang tua paham nominal yang diminta bukan cuma untuk tagihan.

### 4.3 Kwitansi PDF & Riwayat Transaksi — baris top-up terpisah
- `pdf/kwitansi.blade.php`: tabel rincian tambah baris "Top Up Saldo Wallet — Rp Y" setelah baris-baris tagihan, kalau `topup_status !== 'none'`.
- `riwayat/index.blade.php`: kolom "Rincian" untuk baris gabungan menampilkan label gabungan (misal "SPP Bulanan + Top Up Wallet"), total tetap `pembayaran->amount` penuh (bukan cuma porsi tagihan).

### 4.4 Halaman Admin "Verifikasi Transfer Manual" (`GET /admin/manual-payment`)
- Mengikuti pola visual `admin/mata-pelajaran/index.blade.php` persis:
  - Header + breadcrumb.
  - Baris KPI card (2 card): "Menunggu Verifikasi" (jumlah request PENDING), "Total Nominal Menunggu" (sum amount).
  - Card filter: search nama siswa (debounced AJAX), filter tanggal transfer (dari/sampai).
  - Tabel `_daftar.blade.php` di dalam `x-ref`, di-swap via `fetch()` + `X-Requested-With: XMLHttpRequest`, kolom: Aksi (sticky-left, tombol Approve + Reject), Nama Siswa, Nominal, Jenis (badge "Bayar Tagihan" / "Top Up Wallet"), Tanggal Transfer, Link Bukti (buka file di tab baru), Diajukan Oleh.
  - Tombol **Approve**: submit langsung (form POST ke `admin.manual-payment.approve`, konfirmasi via `confirm()` browser sebelum submit — nilai uang, butuh konfirmasi eksplisit).
  - Tombol **Reject**: buka modal kecil (Alpine `x-data` lokal per-baris atau modal terpusat) berisi textarea alasan penolakan (required, sesuai validasi backend `rejection_reason`), submit ke `admin.manual-payment.reject`.
  - Pagination `pagination.tailadmin`, per-page selector sama gaya mata-pelajaran.
- Menu sidebar baru, gated `@can('pembayaran.verifikasi')` (permission existing, tidak perlu permission baru).

## 5. Data Flow & Validasi

### 5.1 `PaymentService::createVaPaymentWithTopup(Siswa $siswa, Collection $tagihans, float $topupAmount): Pembayaran`
- Guard: `$topupAmount > 0` dan `$tagihans->isNotEmpty()` (top-up murni tanpa tagihan sudah ada jalur terpisah, tidak lewat sini).
- Hitung `$totalTagihan` dari `$tagihans` (pola sama seperti `createVaPayment` existing), `$totalAmount = $totalTagihan + $topupAmount`.
- Buat `Pembayaran` dengan `amount = $totalAmount`, `topup_status = 'pending'`, `metode = 'va_bri'`, `status = 'menunggu_pembayaran'`.
- `PembayaranTagihan` dibuat HANYA untuk porsi tagihan (persis logic `createPembayaranRecord()` existing — reuse, jangan duplikasi).
- Panggil gateway `createVirtualAccount($pembayaran, 'BILL_DIRECT')` dengan `$totalAmount` sebagai nominal VA (bukan cuma `$totalTagihan`).
- `createQrisPaymentWithTopup()` — sama persis, pakai `createQris()`.

### 5.2 `PaymentAllocationService::topupSisaJikaAda(Pembayaran $pembayaran): void`
- Guard: kalau `$pembayaran->topup_status === 'none'`, return langsung (no-op untuk pembayaran murni-tagihan, mayoritas kasus).
- Hitung `$sisa = $pembayaran->amount - $pembayaran->pembayaranTagihan->sum('amount_allocated')`.
- Kalau `$sisa <= 0`: log warning (seharusnya tidak terjadi kalau alokasi benar), return.
- Cari `Wallet::where('siswa_id', $pembayaran->siswa_id)->first()`. Kalau tidak ada: log error, `topup_status = 'failed'`, return.
- `try { $wallet->topup($sisa, $pembayaran, 'Topup sisa dari pembayaran gabungan'); $pembayaran->update(['topup_status' => 'completed']); } catch (\Throwable $e) { log error; $pembayaran->update(['topup_status' => 'failed']); }` — pola identik `ManualPaymentController::approve()`, dipanggil DI LUAR transaction pemanggil (webhook/reconcile harus panggil method ini setelah transaction `allocate()` selesai, bukan di dalamnya).

### 5.3 `BriWebhookController` — titik pemanggilan
- Setelah `$this->allocationService->allocate($pembayaran)` di cabang `BILL_DIRECT` (VA) dan cabang QRIS: tambah `app(PaymentAllocationService::class)->topupSisaJikaAda($pembayaran);` — di luar `DB::transaction()` yang membungkus alokasi (constraint sama seperti `Wallet::topup()` existing — commit dulu baru topup, mengikuti pola `WALLET_PERMANENT` yang sudah ada di file yang sama).

### 5.4 `ReconcilePayments`
- `reconcileWaitingPayments()`: tambah pemanggilan `topupSisaJikaAda()` setelah `allocate()`, di luar `DB::transaction` yang sama (pola sama seperti 5.3).
- `retryFailedTopups()`: ganti isi loop — bukan lagi `$wallet->topup($pembayaran->amount, ...)` langsung, tapi panggil `topupSisaJikaAda($pembayaran)` (method ini sudah punya guard `topup_status` sendiri, jadi query filter `where('topup_status', 'failed')` di command tetap dipakai untuk memilih kandidat, tapi eksekusinya lewat method bersama supaya hitungannya benar untuk kasus gabungan maupun murni-topup).

### 5.5 `Admin\ManualPaymentController@index`
- Query: `ManualPaymentRequest::where('status', 'PENDING')->whereHas('pembayaran', fn ($q) => $q->whereHas('siswa', fn ($q2) => $q2->where('lembaga_id', $lembagaId)))` — scoped ke lembaga admin (reuse helper `lembagaId()` yang sudah ada persis sama di controller ini).
- Filter: search nama siswa (`whereHas('pembayaran.siswa', fn ($q) => $q->where('nama_lengkap', 'like', "%{$search}%"))`), filter tanggal transfer (`transfer_date` between).
- Response: kalau request AJAX (`$request->ajax()` atau header `X-Requested-With`), return `view('admin.manual-payment._daftar', [...])` saja (tanpa layout). Kalau tidak, return full `view('admin.manual-payment.index', [...])`.
- Authorize: `$this->authorize('pembayaran.verifikasi')` (permission existing, sama yang dipakai `approve()`/`reject()`) — tidak perlu permission baru.

## 6. Error Handling

| Skenario | Penanganan |
|---|---|
| Topup gagal setelah tagihan berhasil dialokasikan (VA/QRIS) | `topup_status='failed'`, tagihan tetap lunas (tidak di-rollback), `ReconcilePayments` retry via `topupSisaJikaAda()` |
| Wallet tidak ditemukan saat split | Log error + `topup_status='failed'`, sama pola `ManualPaymentController::approve()` |
| `topup_amount` diisi tanpa tagihan dipilih | Ditolak di `CheckoutController` sebelum panggil service — redirect back dengan error |
| Admin approve/reject request yang sudah diproses | Ditangani backend existing (422) — listing tinggal exclude non-PENDING dari query dasarnya |
| Admin approve/reject lintas lembaga | Ditangani backend existing (404) — listing scoped ke lembaga admin sejak query awal |
| Race webhook vs reconcile command | Sudah ada `lockForUpdate()` di kedua sisi (pola existing); `topupSisaJikaAda()` idempoten via guard `topup_status` |
| `retryFailedTopups()` untuk pembayaran gabungan | Diperbaiki pakai sisa yang benar (lihat §2), bukan `amount` penuh — mencegah double-count |

## 7. Testing Strategy

- **Feature tests**: `PaymentServiceBundledTopupTest` (VA & QRIS, `amount` gabungan benar, `PembayaranTagihan` cuma porsi tagihan, guard tagihan-kosong ditolak), `PaymentAllocationServiceTopupRemainderTest` (unit: sisa>0 sukses, sisa=0/topup_status=none no-op, wallet tidak ada → failed), `ReconcilePaymentsRetryTest` (regresi eksplisit bug double-count — assert saldo bertambah sebesar SISA bukan `amount` penuh, untuk kasus gabungan), `ManualPaymentIndexControllerTest` (scoped lembaga, filter status PENDING, search, AJAX vs full-page response), `ManualPaymentIndexAuthorizationTest` (cross-lembaga tidak bisa lihat/approve/reject punya lembaga lain — pola dua-pihak).
- **Kwitansi/riwayat test**: pembayaran gabungan menampilkan baris top-up terpisah dengan total benar.
- **Manual Playwright**: 1 check — checkout VA dengan `topup_amount` terisi → (mock gateway auto-settle) tagihan lunas DAN saldo wallet bertambah sesuai sisa.
- **Regression selama proses**: `tests/Feature/Keuangan/` + test admin baru + test model/service tersentuh (`Wallet`, `PaymentAllocationService`, `ReconcilePayments`) — **tidak ada full-suite run di akhir**, sesuai keputusan user (hemat token).

## 8. Eksplisit di Luar Scope 6c2

- Perubahan pada `PaymentService::createVaPayment`/`createQrisPayment` yang existing (tanpa topup) — tetap dipakai apa adanya untuk checkout tanpa top-up.
- Perubahan pada `Wallet::topup()`/`debit()`, `AutoAllocationEngine` — tidak disentuh.
- Top-up murni tanpa tagihan (jalur `createManualTopupPayment` untuk transfer manual, atau VA/QRIS permanen wallet) — sudah ada, tidak diubah.
- Preferensi/pengaturan notifikasi (→ 6d).
- UI riwayat/kwitansi untuk sub-project 6c yang sudah shipped — hanya baris tambahan untuk kasus gabungan, tidak ada perombakan struktur.
