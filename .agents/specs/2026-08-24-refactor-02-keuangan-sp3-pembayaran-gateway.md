# Spec: Migrasi Domain Keuangan — Sub-project 3: Pembayaran & Gateway

**Tanggal:** 24 Agustus 2026
**Branch:** `refactor-v1`
**Terkait:** `.agents/specs/2026-08-24-refactor-02-keuangan-sp1-konfigurasi-tagihan.md`, `.agents/specs/2026-08-24-refactor-02-keuangan-sp2-alur-tagihan-inti.md` (keduanya SELESAI + direview mendalam), `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md`, `.agents/skills/laravel-feature-standard/SKILL.md`

## 1. Latar Belakang

SP1 (Konfigurasi & Generasi Tagihan) dan SP2 (Alur Tagihan Inti) sudah selesai dan direview mendalam. Review SP1 menemukan 1 celah HIGH (guard tenant-isolation hilang saat refactor Action, lolos full suite karena tidak ada test yang menguji jalur itu) + 1 pembalikan arsitektur tak terungkap. Review SP2 menemukan 1 deviasi namespace tak terungkap (`WaliMurid` alih-alih `Portal\Keuangan` yang disepakati). **Kedua pelajaran ini mengikat untuk SP3 dan lebih kritis lagi di sini**: SP3 adalah modul PEMBAYARAN — uang sungguhan benar-benar bergerak (debit wallet, verifikasi transfer, callback bank), bukan sekadar administrasi tagihan.

Eksplorasi ulang (24 Agustus 2026, tidak mempercayai estimasi awal dari roadmap) menemukan skala nyata jauh lebih besar dan lebih kompleks dari SP1/SP2: **6 model, 6 controller (termasuk webhook produksi 257 baris), 9 file service/gateway (~900 baris)** — total >2300 baris kode produksi tersentuh. Temuan penting yang mengubah pemahaman awal:

1. **Dua jalur pencatatan Pembayaran paralel** menulis ke tabel `pembayaran` yang sama tapi lewat kode dan skema data berbeda total: (a) jalur checkout siswa aktif modern (`Keuangan\CheckoutController` + `Services\Finance\PaymentService`, pakai pivot `pembayaran_tagihan` + Gateway), dan (b) jalur verifikasi manual pendaftaran/cicilan PPDB lama (`Admin\PembayaranController` + `app\Services\PembayaranService`, pakai FK `tagihan_id`/`cicilan_id` langsung, tanpa Gateway).
2. **Overlap nyata dengan domain Wallet (SP4)**: `AutoAllocationEngine`, `SkipAlertResolver`, dan `PaymentAllocationService::topupSisaJikaAda()` secara struktural adalah mesin alokasi Wallet (baca-tulis `Wallet` langsung, row-lock, debit/topup) yang kebetulan menghasilkan record `Pembayaran` sebagai efek samping — mirip persis kasus `Keuangan\DashboardController` yang campur domain di SP2.
3. **Webhook BRI SNAP produksi** (`Api\BriVaInboundController`, 3 endpoint: `token`/`inquiry`/`payment`) menangani uang sungguhan dari sistem eksternal, dengan response JSON yang HARUS byte-identical (sistem BRI di luar kendali kita bergantung pada bentuk response persis, bukan cuma nama route) — **user eksplisit menegaskan webhook ini TIDAK BOLEH dikecualikan/ditunda** ("tidak boleh ditunda termasuk webhook").

## 2. Tujuan

Memindahkan seluruh alur Pembayaran (kedua jalur — modern dan legacy) ke `app/Domains/Keuangan/*`, termasuk webhook BRI SNAP dan seluruh Gateway family, mengikuti rigor SP1/SP2: model+controller (Action/DTO)+view sekaligus, zero-behavior-change, grep konsumen nyata scope `app database tests`, deteksi eksplisit SETIAP guard otorisasi/tenant-scope yang wajib dipertahankan persis — dengan kewaspadaan ekstra karena modul ini menggerakkan uang sungguhan.

## 3. Cakupan

### 3.1 Model (6, ke `app/Domains/Keuangan/Models/`)
- `Pembayaran` (87 baris) — dua skema koeksis: FK langsung (`tagihan_id`/`cicilan_id`, jalur legacy) DAN pivot `pembayaranTagihan` (jalur modern). Relasi `tagihan()` sudah FQCN ke domain baru. `cicilan()` tetap `\App\Models\Cicilan` (SP4, FQCN inline setelah pindah).
- `PembayaranTagihan` (30 baris) — pivot murni.
- `BriVirtualAccount` (30 baris) — relasi `wallet()` ke `\App\Models\Wallet` (SP4, FQCN inline setelah pindah).
- `BriQrisPayment` (25 baris).
- `BriInboundPaymentLog` (23 baris).
- `ManualPaymentRequest` (35 baris).

Keenamnya `$guarded = ['id']`, murni fillable/casts/relationship — tidak ada business logic yang perlu diekstrak (dikonfirmasi baca penuh, beda dari kasus `JenisTagihan::booted()`/`Tagihan::bisaDicicil()` di SP1/SP2).

### 3.2 Service & Gateway (ke `app/Domains/Keuangan/Services/`)
- `PaymentService` (Finance, 294 baris) — jalur checkout modern: `createQrisPayment`, `createQrisPaymentWithTopup`, `getOrCreatePermanentVa`, `createManualPayment`, `createManualTopupPayment`, `createWalletPayment`, `createCashPayment`.
- `PembayaranService` (legacy, `app/Services`, 210 baris) — jalur verifikasi manual PPDB: `buatSkemaCicilan`, `simpanNominalManual`, `catatPembayaran`, `verifikasiPembayaran`. **Sudah dipakai SP1/SP2** (`Lembaga\Keuangan\TagihanController` 4 Action, `Portal\TagihanController` yang TIDAK dimigrasi) — pindah namespace berarti SEMUA consumer lama itu jadi cross-scope touch (lihat §5).
- Gateway family → `Domains\Keuangan\Services\Gateway\`: `BriSnapGateway` (95 baris), `MockPaymentGateway` (~50 baris), `HybridPaymentGateway` (~40 baris), `Gateway\BriSnap\BriSnapClient` (128 baris).
- `Domains\Keuangan\Services\BriInbound\SimpleBriInboundAuthenticator` (39 baris).

**TIDAK pindah** (keputusan brainstorming §6.2): `NotificationDispatcher` (`app/Services/Finance/`) — didesain generik lintas-modul (parameter `$module` bukan hardcode `'finance'`), tetap infrastruktur bersama, hanya dipanggil via DI seperti biasa.

**TIDAK pindah, DIINJECT dari luar** (keputusan brainstorming §6.3, ditunda ke SP4): `AutoAllocationEngine`, `SkipAlertResolver`, `PaymentAllocationService` (SATU KELAS UTUH, termasuk method `allocate()` yang sebenarnya murni Pembayaran/Tagihan) — subjek utamanya Wallet, dan class ini dipanggil LANGSUNG oleh `PaymentService::createWalletPayment()`/`createCashPayment()` dan `Admin\ManualPaymentController::approve()`. Memisah method per-method di kelas yang sama berisiko tinggi mengingat kelas ini aktif dipanggil dari 2 sisi — biarkan utuh di `app/Services/Finance/`, SP3 tetap inject dari sana, persis pola SP1 memanggil `TagihanBillingGenerator` sebelum SP2 memindahkannya.

### 3.3 Contract & DTO (ke `app/Domains/Keuangan/Contracts/` dan `.../DataTransferObjects/`)
- `PaymentGatewayInterface`, `BriInboundAuthenticatorInterface` (dari `app/Contracts/`).
- `PaymentStatusResult`, `QrisResult`, `VirtualAccountResult` (dari `app/DTO/`).
- Binding di `app/Providers/AppServiceProvider::register()` (baris yang bind `PaymentGatewayInterface`/`BriInboundAuthenticatorInterface`) WAJIB diupdate mengikuti namespace baru.

### 3.4 Controller (6, direfactor Action/DTO + pindah namespace)

| Controller lama | Baris | Controller baru |
|---|---|---|
| `Admin\VirtualAccountController` | 219 | `Lembaga\Keuangan\VirtualAccountController` |
| `Admin\ManualPaymentController` | 226 | `Lembaga\Keuangan\ManualPaymentController` |
| `Admin\PembayaranController` | 117 | `Lembaga\Keuangan\PembayaranController` |
| `Keuangan\CheckoutController` | 269 | `Portal\Keuangan\CheckoutController` |
| `Keuangan\RiwayatController` | 93 | `Portal\Keuangan\RiwayatController` |
| `Api\BriVaInboundController` | 257 | `Api\Keuangan\BriVaInboundController` |

Trait `Keuangan\Concerns\AuthorizesPembayaran` → `Domains\Keuangan\Concerns\AuthorizesPembayaran` (dipakai `CheckoutController` dan `RiwayatController`, keduanya pindah bareng).

### 3.5 View

| View lama | View baru |
|---|---|
| `resources/views/admin/virtual-account/*` (4 file) | `resources/views/portals/lembaga/keuangan/virtual-account/` |
| `resources/views/admin/manual-payment/*` (2 file) | `resources/views/portals/lembaga/keuangan/manual-payment/` |
| `resources/views/admin/pembayaran/*` | `resources/views/portals/lembaga/keuangan/pembayaran/` |
| `resources/views/keuangan/checkout/*` | `resources/views/portals/portal/keuangan/checkout/` |
| `resources/views/keuangan/riwayat/*` | `resources/views/portals/portal/keuangan/riwayat/` |

`resources/views/pdf/kwitansi.blade.php` — TIDAK pindah (folder `pdf/` bukan bagian konvensi `portals/`, dipakai lintas-fitur untuk generate PDF, tetap di lokasi lama, hanya isi datanya kalau ada referensi model lama yang perlu di-cross-scope-touch).
`resources/views/keuangan/tanpa-anak.blade.php` — TETAP di lokasi lama (shared fallback lintas-SP, sama seperti keputusan SP2, dipindah nanti saat SP4 selesai).

### 3.6 Console Command (TETAP di `app/Console/Commands/`, hanya `use` diupdate)
`ReconcilePayments` (`finance:reconcile-payments`, `->hourly()`) — subjek utama proses QRIS/Pembayaran (`reconcileWaitingPayments()`), memanggil `PaymentAllocationService::retryFailedTopups()` untuk cabang topup (kelas itu sendiri TIDAK pindah, lihat §3.2). Hanya `use` ke `Pembayaran`/`BriQrisPayment`/Gateway interface yang diupdate.

## 4. Webhook — Constraint Byte-Identical (WAJIB, tidak bisa dinegosiasi)

`routes/web.php` baris 7-9 mendaftarkan 3 route ke `Api\BriVaInboundController`:
```php
Route::post('/snap/v1.0/access-token/b2b', [BriVaInboundController::class, 'token']);
Route::post('/snap/v1.0/transfer-va/inquiry', [BriVaInboundController::class, 'inquiry']);
Route::post('/snap/v1.0/transfer-va/payment', [BriVaInboundController::class, 'payment']);
```

URL path (`/snap/v1.0/...`) TIDAK BOLEH berubah sama sekali — ini string literal di `Route::post()`, tidak terikat namespace controller, jadi aman terhadap migrasi asal barisnya sendiri tidak disentuh isinya. **Yang WAJIB byte-identical adalah bentuk JSON respons untuk SETIAP cabang** (field names, urutan tidak masalah karena JSON object, tapi TIPE dan NILAI harus persis — termasuk `number_format($x, 2, '.', '')` yang menghasilkan string desimal 2 digit, BUKAN angka):

| Method | Kondisi | responseCode | HTTP status |
|---|---|---|---|
| `token` | client_id/secret salah | `4017300` | 401 |
| `token` | sukses | (return `accessToken`/`tokenType`/`expiresIn`, TANPA responseCode) | 200 |
| `inquiry` | token tidak valid | `4012400` | 401 |
| `inquiry` | VA tidak ditemukan | `4042412` | 404 |
| `inquiry` | sukses | `2002400` | 200 |
| `payment` | token tidak valid | `4012500` | 401 |
| `payment` | field wajib kosong | `4002500` | 400 |
| `payment` | amount ≤ 0 | `4042513` | 404 |
| `payment` | VA tidak ditemukan | `4042512` | 404 |
| `payment` | gagal tulis log (bukan duplikat) | `5002500` | 500 |
| `payment` | sukses / idempotent replay / race-duplicate | `2002500` | 200 |

Struktur lengkap tiap respons dikutip di plan implementasi. **Business logic method `payment()` (idempotency check, disambiguasi genuine-duplicate vs real-failure, 3-cabang exception handling topup) WAJIB diekstrak ke Action** (keputusan brainstorming §6.4, sesuai SKILL.md tanpa kecuali) — TAPI controller HARUS merakit `response()->json()` dari hasil Action persis seperti kode asli, tiap baris JSON disalin verbatim, tidak diparafrase.

## 5. Cross-Scope Touch (file/consumer TIDAK migrasi tapi WAJIB disentuh)

Karena `PembayaranService` (legacy) pindah namespace, SEMUA consumer-nya dari SP1/SP2 yang TIDAK dimigrasi di SP3 ini WAJIB diupdate `use`-nya:

| File | Alasan |
|---|---|
| `app/Domains/Keuangan/Actions/Tagihan/BuatSkemaCicilanAction.php` (SP2) | `use App\Services\PembayaranService;` |
| `app/Domains/Keuangan/Actions/Tagihan/SimpanNominalCicilanAction.php` (SP2) | idem |
| `app/Domains/Keuangan/Actions/Tagihan/CatatManualTagihanAction.php` (SP2) | idem |
| `app/Domains/Keuangan/Actions/Tagihan/CatatManualCicilanAction.php` (SP2) | idem |
| `app/Http/Controllers/Portal/TagihanController.php` (portal pendaftar PPDB, TIDAK dimigrasi sejak SP2) | `use App\Services\PembayaranService;` |

Karena model `Pembayaran`/`PembayaranTagihan`/dst pindah namespace, seluruh file hasil grep `use App\Models\Pembayaran;`/`use App\Models\PembayaranTagihan;` yang BUKAN bagian migrasi controller/service di atas WAJIB diupdate (grep ulang WAJIB saat plan-writing, per riset 24 Agustus 2026 ada ±58 file untuk `Pembayaran` dan ±16 file untuk `PembayaranTagihan`, termasuk `app/Services/DashboardStatsService.php`, `app/Console/Commands/BriTestQris.php`, `app/Domains/Keuangan/Models/Tagihan.php` — yang terakhir ini SUDAH punya relasi `pembayaran()`/`pembayaranTagihan()` dari SP2 dengan `use App\Models\Pembayaran;`/`use App\Models\PembayaranTagihan;`, WAJIB diupdate).

`app/Providers/AppServiceProvider.php` — binding `PaymentGatewayInterface`/`BriInboundAuthenticatorInterface` di `register()` WAJIB diupdate ke namespace `Domains\Keuangan\Contracts\*`.

## 6. Keputusan Desain (hasil brainstorming)

1. **Jalur `PembayaranService` legacy (PPDB/cicilan) IKUT masuk SP3** — subjek datanya tetap model `Pembayaran` yang sama, konsisten prinsip "kepemilikan ikut subjek data" dari SP1/SP2.
2. **`NotificationDispatcher` TIDAK pindah** — didesain generik lintas-modul, tetap di `app/Services/Finance/`.
3. **`AutoAllocationEngine`, `SkipAlertResolver`, `PaymentAllocationService` (utuh) DITUNDA ke SP4** — subjek utamanya Wallet, dipanggil eksternal dari SP3 lewat DI tanpa perubahan, sama seperti SP1 memanggil `TagihanBillingGenerator` sebelum SP2 memindahkannya.
4. **Webhook full Action extraction**, dengan constraint byte-identical response JSON yang eksplisit didokumentasikan (§4) — tidak dikecualikan sesuai instruksi user.
5. **Contracts/DTO pindah ke `Domains\Keuangan\Contracts`/`DataTransferObjects`** — perluasan wajar pola Domain yang sudah ada, meski SKILL.md tidak menyebut folder Contracts secara eksplisit.
6. **SP3 tetap SATU sub-project** (tidak dipecah lagi) meski skalanya >2300 baris — bagian-bagiannya saling terkait erat lewat guard/comment yang saling mereferensikan, plan dipecah jadi banyak task kecil untuk tetap bisa direview granular.
7. **Namespace scope**: `Lembaga\Keuangan\` untuk 3 controller admin, `Portal\Keuangan\` untuk 2 controller portal siswa/ortu (konsisten pola `Portal\Keuangan\TagihanController` dari SP2 pasca-perbaikan `WaliMurid`), `Api\Keuangan\` untuk webhook (perluasan pola `[Scope]\[Domain]` yang sama, `Api` sudah jadi scope resmi di `app/Http/Controllers/Api/`).

## 7. Guard Otorisasi/Tenant-Scope yang WAJIB Dipertahankan Persis

**Pelajaran SP1 (celah HIGH lolos full suite) dan SP2 (deviasi namespace tak terungkap) berlaku penuh di sini — modul ini uang sungguhan, risikonya lebih tinggi.**

1. **`Admin\PembayaranController::verifikasi()` baris 99-101**: `$pendaftaranLembagaId = $pembayaran->tagihan?->pendaftaran->lembaga_id ?? $pembayaran->cicilan->skemaCicilan->tagihan->pendaftaran->lembaga_id; abort_unless($pendaftaranLembagaId === $this->lembagaId($request), 404);` — dua jalur resolusi lembaga (via tagihan ATAU via cicilan) WAJIB tetap ada, urutan null-coalesce tidak boleh diubah.
2. **`Admin\ManualPaymentController::approve()`/`reject()` baris 100/192**: `abort_unless($siswaLembagaId !== null && $siswaLembagaId === $this->lembagaId($request), 404)`, memakai helper `siswaLembagaId()` yang SENGAJA bypass `TenantScope` (komentar eksplisit di kode asli menjelaskan alasannya) — WAJIB dipertahankan persis termasuk bypass-nya.
3. **`Admin\ManualPaymentController::approve()` baris 115-125 — GUARD DATA-CONSISTENCY KRITIS**: cek `hasTagihanTargets` vs `isTopup` harus mutually exclusive, `Log::critical()` + `abort(500)` kalau drift terdeteksi di kedua arah (keduanya true ATAU keduanya false). Komentar asli eksplisit: *"Uang nyata terlibat — lebih baik gagal keras & jelas daripada salah diam-diam."* Guard ini PALING KRITIS di seluruh SP3 — kalau hilang, approve() bisa diam-diam skip topup yang seharusnya jalan, atau skip alokasi tagihan sambil tetap menandai lunas.
4. **`Admin\VirtualAccountController::riwayat()` baris 98-99**: pola `siswaLembagaId()` yang identik dengan ManualPaymentController (komentar aslinya eksplisit saling mereferensikan) — WAJIB dipertahankan persis di kedua tempat, TIDAK boleh disatukan jadi 1 helper bersama tanpa didiskusikan dulu (itu perubahan struktural di luar scope zero-behavior-change, meski secara teori aman — kalau plan-writer melihat peluang konsolidasi ini, WAJIB dicatat sebagai usulan terpisah ke user, bukan dieksekusi diam-diam).
5. **`Domains\Keuangan\Concerns\AuthorizesPembayaran::authorizePembayaran()`**: `abort_unless($ownsChild, 403)` — cek kepemilikan orangTua-siswa via `TenantScope` bypass. Dipakai 4× di `CheckoutController` (baris 196/203/212/232) dan 1× di `RiwayatController` (baris 72/kwitansi baris 72-ish) — SEMUA titik panggil WAJIB tetap memanggil guard ini sebelum logic lain, TIDAK ADA yang boleh terlewat saat direfactor jadi Action.
6. **Webhook `payment()` — idempotency & genuine-duplicate disambiguation (baris 113-191 baseline)**: urutan cek (idempotent-replay dulu via `BriInboundPaymentLog::payment_request_id`, baru validasi amount, baru VA lookup, baru insert log dengan try/catch yang membedakan genuine-duplicate-race vs real-failure) WAJIB dipertahankan PERSIS — ini bukan sekadar guard otorisasi tapi correctness-critical untuk mencegah double-charge/double-credit uang sungguhan.
7. **`PembayaranService::catatPembayaran()`** — guard mutual-exclusivity (`tagihan` XOR `cicilan`), row-lock (`lockForUpdate()`), cek "sudah ada pembayaran aktif" SEBELUM insert, dan `pastikanUrutanBoleh()` (cicilan harus dibayar berurutan) — semua WAJIB dipertahankan persis saat dipindah ke Action/Service baru.

## 8. Zero-Behavior-Change Lain

- `PaymentService::createWalletPayment()` — komentar eksplisit soal TIDAK melakukan `lockForUpdate()` pada baris `tagihan` karena `PaymentAllocationService::allocate()` sudah melakukannya sendiri lebih jauh di alur — jangan "diperbaiki" dengan menambah lock ganda.
- `PaymentAllocationService::topupSisaJikaAda()`/webhook `payment()`/`ManualPaymentController::approve()` — pola exception 3-cabang (`AutoAllocationFailedException` = saldo AMAN cuma alokasi gagal, set `topup_status='completed'`; `Throwable` lain = saldo TIDAK ter-kredit, set `topup_status='failed'`) — SAMA PERSIS di ketiga tempat, jangan disatukan jadi helper bersama tanpa didiskusikan (out of scope zero-behavior-change default, sama seperti poin guard #4 di atas).
- `BriSnapGateway::createQris()` — komentar eksplisit soal kenapa `partnerReferenceNo` pakai `pembayaran->id` zero-padded (bukan UUID `channel_reference`) — dipertahankan persis.
- Response `paymentSuccessResponse()` helper dipakai di 3 tempat berbeda dalam `payment()` (idempotent-replay, race-duplicate, sukses-normal) — WAJIB tetap 1 helper yang sama dipanggil 3×, bukan diduplikasi.

## 9. Testing

- Test scoped per task, full suite HANYA di task terakhir dengan izin eksplisit user.
- Grep verifikasi WAJIB scope `app database tests`.
- **Test baru WAJIB ditambahkan** untuk SETIAP guard di §7 yang belum punya test eksplisit menyerang jalur itu (bukan cuma "test lama masih lulus") — terutama guard #3 (data-consistency kritis) dan #6 (webhook idempotency/genuine-duplicate) yang paling berisiko kalau hilang diam-diam saat ekstraksi Action. Plan implementasi WAJIB mendata test mana yang sudah ada vs perlu ditulis baru, per guard.
- Test webhook WAJIB menguji SETIAP baris di tabel §4 (11 kombinasi kondisi × response code/status) — bukan cuma jalur sukses.

## 10. Di Luar Cakupan (Sub-project Lain)

- `Wallet`, `Cicilan`, `AutoAllocationEngine`, `SkipAlertResolver`, `PaymentAllocationService` (utuh), rekonsiliasi Wallet-side — SP4.
- `Keuangan\DashboardController` — ditunda ke SP4 (keputusan SP2, campur 3 domain).
- `Keuangan\NotifikasiController` — di luar SP manapun (keputusan SP2, generic).
- `TagihanGenerator` (PPDB/SPMB), `Portal\TagihanController`, `Admin\TagihanSusulanController` — SPMB, ditunda indefinitely.
- `NotificationDispatcher` — infrastruktur bersama, tetap di `app/Services/Finance/`.
