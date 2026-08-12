# Handoff Log: Keuangan Sub-project 05 — Notifikasi

**Terkait:**
- Spec: `.agents/specs/keuangan-05-notifikasi.md` (termasuk Addendum 2026-08-11: skip-tracking AutoAllocationEngine + manual-topup gap closure)
- Plan: `.agents/plans/keuangan-05-notifikasi.md`
- Commits: `28bc293..ecd289e` pada branch `demo` (14 commit — 10 fitur + 3 ronde perbaikan + 1 dokumentasi plan)

**Status:** SELESAI. Semua 10 task fitur diimplementasikan via subagent-driven-development, direview per-task, plus whole-plan review di akhir dengan 1 ronde perbaikan besar dan 1 ronde perbaikan kecil. Full suite akhir: **1502 passed**, persis baseline 6 failure pre-existing (tidak terkait Keuangan). **Belum di-push ke remote**, sesuai instruksi user.

---

## Apa yang Dikerjakan

### Infrastruktur inti (Task 1-3)
1. **Migrations** `user_notification_preferences` (per user/module, default WA+Email ON, push OFF) dan `notification_logs` (audit trail per channel per notifikasi, dengan kolom `payload` json).
2. **`NotificationDispatcher`** + **`FinanceNotification`** (abstract base) — setiap notifikasi Finance meng-extend base ini, mengimplementasikan `isUrgent()`, dan `via()` selalu return `$this->baseChannels()`. Notifikasi urgent selalu kirim semua channel terlepas dari preferensi user; notifikasi non-urgent menghormati `channel_wa`/`channel_email` per user.
3. **6 template WhatsApp baru** di-seed (`tagihan_baru`, `pembayaran_berhasil`, `transfer_manual_disetujui`, `transfer_manual_ditolak`, `saldo_tidak_cukup`, `tagihan_jatuh_tempo`) — sudah di-seed ke database dev nyata (`pintera_app`), bukan cuma test DB.

### 6 kelas notifikasi + hook point-nya (Task 4-6, 9-10)
| Notifikasi | Trigger | Pola commit-timing |
|---|---|---|
| `TagihanDiterbitkanNotification` | `TagihanBillingGenerator::generateForSiswa()` | capture-then-fire setelah `DB::transaction()` return (method ini yang punya transaction) |
| `PembayaranBerhasilNotification` | `PaymentAllocationService::allocate()` saat tagihan jadi lunas | `DB::afterCommit()` (method ini TIDAK punya transaction sendiri — dipanggil dari 4 caller berbeda) |
| `SaldoTidakCukupNotification` | `AutoAllocationEngine::run()` saat ada tagihan ter-skip karena saldo kurang | capture-then-fire setelah `DB::transaction()` return; **hanya tagihan skip berprioritas tertinggi** yang memicu (lihat "Temuan Penting" di bawah) |
| `TransferManualDisetujuiNotification` / `TransferManualDitolakNotification` | `ManualPaymentController::approve()`/`reject()` | best-effort try/catch, di luar transaction manapun |
| `DueReminderNotification` | Command terjadwal `billing:kirim-due-reminder` (H-3 non-urgent, H-1 urgent) | best-effort try/catch per-tagihan dalam loop, satu resipien gagal tidak menggagalkan sisa run |

Semua 6 notifikasi menggunakan idiom resolusi kontak-utama yang sama persis di seluruh codebase: `$siswa->orangTua()->wherePivot('is_kontak_utama', true)->first()`.

### Penutupan celah Sub-project 04 (Task 7-9)
- **`PaymentService::createManualTopupPayment()`** — method baru (sibling dari `createManualPayment()`, tidak menyentuhnya sama sekali) yang menutup gap nyata: spec Sub-project 04 sudah mendesain "top-up wallet via transfer manual" tapi implementasinya tidak pernah dibangun (lihat detail penuh insiden ini di `.agents/logs/keuangan-04-payment-channels.md`, bagian koreksi 2026-08-11).
- **`ManualPaymentController`** (controller baru, terpisah total dari `PembayaranController` legacy yang menangani alur verifikasi PPDB lama) — `approve()`/`reject()` untuk `ManualPaymentRequest`, dengan **guard cross-validasi** eksplisit: `topup_status` vs keberadaan `pembayaran_tagihan` HARUS konsisten (mutually exclusive), kalau tidak → `abort(500)` sebelum mutasi apa pun terjadi. Dua kasus mismatch (punya keduanya, atau tidak punya keduanya) masing-masing punya test dedicated yang assert HTTP 500 (bukan silent pick salah satu cabang), sesuai permintaan eksplisit user.
- Alur bill-payment (di dalam satu transaction, `PaymentAllocationService::allocate()`) dan alur topup (`Wallet::topup()` di luar transaction, try/catch, mirror persis konvensi `BriWebhookController` dari Sub-project 04) punya test terpisah, bukan satu test gabungan.

---

## Temuan Penting Selama Implementasi (bukan bug dalam plan, tapi ditemukan SAAT eksekusi)

Sesuai pola sesi ini — jangan percaya laporan/status hijau tanpa verifikasi independen — proses subagent-driven-development di sub-project ini menemukan **6 bug/gap nyata** yang tidak ada dalam plan awal, semuanya ditemukan dan diperbaiki dalam alur kerja yang sama, dengan verifikasi independen oleh reviewer (bukan cuma dipercaya dari laporan implementer):

1. **Bug retroaktif di Task 4 (ditemukan saat Task 5):** `NotificationDispatcher::logAttempt()` menulis `notification_logs.user_id = $notifiable->id` tanpa memandang tipe notifiable — salah untuk `OrangTua` (yang `id`-nya BUKAN `users.id`, ada kolom `user_id` terpisah). Pada kasus ID kebetulan bertabrakan, log salah dicatat ke user yang SALAH SAMA SEKALI; kalau ID tidak kebetulan cocok, crash (FK violation). Task 4 lolos test karena kebetulan ID cocok. Dibuktikan secara empiris oleh reviewer (revert-lalu-restore fix). Diperbaiki di Task 5, dikunci dengan regression test baru di ronde perbaikan whole-plan (lihat di bawah).
   **Klarifikasi scope dampak (dikonfirmasi 2026-08-12 atas pertanyaan user):** ini MURNI bug data/audit, BUKAN bug pengiriman-ke-pihak-salah. `NotificationDispatcher::send()` memanggil `$notifiable->notify($notification)` LANGSUNG pada objek `OrangTua` yang sudah benar (hasil `wherePivot('is_kontak_utama', true)`) — bug `logAttempt()` ada di method terpisah yang HANYA menulis baris audit ke tabel `notification_logs` milik kita sendiri, dijalankan SETELAH `->notify()` sudah selesai. `OrangTua` implements `Notifiable` langsung dengan `routeNotificationForMail()`/`routeNotificationForWhatsapp()` yang me-return `email`/`no_hp` miliknya sendiri, tidak pernah melalui `user_id` yang salah itu; tabel `notifications` bawaan Laravel juga pakai kolom polymorphic `notifiable_type`/`notifiable_id`, bukan `user_id`. Jadi: tidak ada notifikasi yang pernah terkirim ke orang tua yang salah — dampaknya terbatas pada query audit "notifikasi apa saja yang terkirim ke user X" lewat `notification_logs` yang bisa salah/tidak lengkap untuk kasus `OrangTua`.

2. **Cross-tenant IDOR Kritis di `ManualPaymentController` (ditemukan saat review Task 8):** Endpoint uang ini awalnya TIDAK PUNYA pengecekan lembaga sama sekali — admin_keuangan lembaga A bisa approve/reject permintaan pembayaran manual lembaga B, mengkredit wallet B atau menandai tagihan B lunas. Diperbaiki dengan helper `siswaLembagaId()` yang mem-bypass `TenantScope` secara eksplisit untuk membaca lembaga_id PEMILIK ASLI data (bukan lembaga_id user yang sedang login) — pola bug TenantScope yang sudah berulang kali muncul di proyek ini. Perbaikan diverifikasi via probe test + mutation test oleh reviewer, bukan cuma dipercaya.

3. **Notifikasi tanpa guard di `PaymentAllocationService` (ditemukan saat Task 9):** `PembayaranBerhasilNotification`'s dispatch di dalam `DB::afterCommit()` tidak dibungkus try/catch — kegagalan gateway WhatsApp/email bisa membuat request HTTP `approve()` return 500 padahal uang/status SUDAH commit. Diperbaiki dengan pola try/catch+log yang sama yang sudah dipakai di tempat lain.

4. **Bug Kritis: notifikasi tanpa guard di `AutoAllocationEngine` (ditemukan di whole-plan review):** Ini yang **paling serius** — `SaldoTidakCukupNotification`'s dispatch di `AutoAllocationEngine::run()` tidak dibungkus try/catch, dan `run()` dipanggil TIDAK di-catch oleh `Wallet::topup()`. Rantai kegagalannya: notifikasi gagal kirim → exception lolos keluar dari `topup()` → `ManualPaymentController`'s catch block menandai topup yang **UANGNYA SUDAH MASUK** sebagai `topup_status='failed'` → `ReconcilePayments::retryFailedTopups()` nanti mengkredit ulang wallet yang sama → **duplikasi uang nyata**. Diperbaiki dengan try/catch+log yang sama; pola yang sama juga ditambahkan ke `TagihanBillingGenerator` dan `KirimDueReminderTagihan` (yang punya celah serupa meski konsekuensinya lebih ringan — reminder H-3/H-1 satu resipien gagal tidak lagi menggagalkan sisa run).

5. **`SaldoTidakCukupNotification` over-fire (ditemukan di whole-plan review):** Implementasi awal mengirim notifikasi untuk SETIAP tagihan yang ter-skip, padahal tabel event di spec eksplisit bilang "tagihan prioritas tertinggi" (tunggal). Orang tua dengan banyak tagihan tertunggak yang top-up parsial berkali-kali sehari bisa menerima belasan WhatsApp duplikat tentang tagihan yang sama. Diperbaiki: hanya `$skippedTagihan->first()` (yang sudah terurut priority_score) yang memicu notifikasi. Ditambahkan test baru yang membuktikan hanya tagihan berprioritas tertinggi dari 2 yang ter-skip yang dinotifikasi, tepat sekali (`assertSentToTimes(..., 1)`).

6. **Regression test yang rusak tapi lolos (ditemukan di verifikasi ronde-2):** Test baru untuk temuan #1 di atas ternyata cuma lolos karena kebetulan urutan eksekusi test dalam satu file (`RefreshDatabase` MySQL tidak me-reset `AUTO_INCREMENT`) — gagal total kalau dijalankan sendirian via `--filter`. Root cause: `OrangTuaFactory` membuat `User`-nya sendiri lewat `User::factory()`, jadi trik "buat OrangTua pembuang dulu" yang dimaksudkan untuk membuat `id !== user_id` tidak pernah benar-benar mendesinkronkan kedua sequence. Diperbaiki dengan `User::factory()->create()` polos sebagai pembuang (hanya memajukan sequence `users`, bukan `orang_tua`). Ini persis kelas masalah yang selalu dicari sesi ini: verifikasi full-suite-saja bisa menyembunyikan bug yang baru ketahuan lewat isolasi single-test.

**Konsisten dengan seluruh sesi kerja modul Keuangan ini** (audit sub-project 1-4, form jenis-tagihan): tidak satu pun dari 6 temuan ini diterima begitu saja dari laporan implementer — semuanya diverifikasi independen oleh reviewer (probe test, mutation test, isolasi test, atau pembacaan langsung source code), bukan dari klaim "test hijau" saja.

---

## Item yang Sengaja TIDAK Diperbaiki (didokumentasikan sebagai keputusan produk/backlog, bukan diabaikan diam-diam)

1. **`PaymentAllocationService::allocate()` double-counting `paid_amount` bila dipanggil ulang pada tagihan yang sudah lunas** — bug pre-existing, ditemukan tidak sengaja, dikonfirmasi TIDAK terkait sub-project ini (diff-nya membuktikan baris tersebut tidak tersentuh task manapun di sini). Perlu tiket terpisah.
2. **Tagihan yang hanya menerima alokasi PARSIAL (status jadi `sebagian`) via `AutoAllocationEngine` tidak memicu notifikasi apa pun** — ini SESUAI cakupan spec (hanya 6 tipe event yang direncanakan, "pembayaran sebagian" tidak pernah salah satunya), bukan requirement yang diam-diam terlewat.
3. **`TransferManualDisetujuiNotification` mengirim pesan yang sama persis meskipun topup ternyata gagal** (wallet tidak ditemukan, atau `topup()` throw) — pesan tidak berubah untuk mencerminkan hasil akhir sebenarnya. Keputusan produk/UX, bukan bug kode.
4. **Approval bill-payment manual mengirim 2 notifikasi terpisah** (`PembayaranBerhasilNotification` dari `allocate()`'s afterCommit, plus `TransferManualDisetujuiNotification` dari controller) — bisa dianggap 2 event yang memang berbeda, atau redundan. Keputusan produk, belum diputuskan.
5. **`ManualPaymentController`'s cross-tenant test fixture agak lemah** — hanya membuktikan tenant-check lewat jalur guard-500 konsistensi, bukan lewat skenario approval sukses yang benar-benar diblokir. Memperkuat fixture-nya (memberi tagihan/pembayaran_tagihan nyata pada skenario cross-tenant) akan lebih meyakinkan, tapi tidak blocking.
6. **Belum ada test untuk yayasan-level admin yang approve SUKSES**, atau untuk cabang `Log::critical()`/wallet-missing yang ditambahkan di ronde perbaikan Task 8.

---

## Verifikasi Akhir

**Full suite** (`php artisan test`, dijalankan langsung foreground oleh controller, bukan dipercaya dari laporan subagent):
```
Tests: 1502 passed, 6 failed (4701 assertions)
```
6 failure persis baseline pre-existing yang sudah dikonfirmasi berulang kali sepanjang sesi ini (`LembagaCrudTest`, `RoleBuilderTest` x4, `RoleFormAuditBannerTest`) — semuanya di modul Lembaga/Role admin, tidak tersentuh satu pun oleh 14 commit sub-project ini (dikonfirmasi via `git diff --stat` scope check oleh reviewer whole-plan).

Selama proses ini juga ditemukan dan diperbaiki 1 stale test (`WhatsAppTemplateSchemaTest` yang meng-assert tepat 2 baris template — jadi 8 setelah Task 3), dan 1 flaky test tidak terkait (`KenaikanKelasControllerTest`, pola factory-unique-collision yang sudah dikenal, dikonfirmasi lolos 9/9 saat diisolasi).

**Lingkungan test — MySQL, bukan SQLite:** `.env.testing` mengkonfirmasi `DB_CONNECTION=mysql`, database `pintera_app_test`, sama persis dengan driver MySQL yang dipakai dev (`pintera_app`). Tidak ada isu lintas-driver di proyek ini. Query idempotensi Task 10 (`where('payload->tagihan_id', ...)`) sudah diverifikasi jalan benar di MySQL ini secara langsung (test `does not send a duplicate reminder...` re-run terisolasi via `--filter`, 2026-08-12, PASS) — bukan cuma diasumsikan portable dari test framework Laravel pada umumnya.

## Git State

Semua commit ada di `demo` langsung: `28bc293..3fb5ba1` (15 commit: 1 plan doc + 10 fitur + 3 ronde perbaikan + 1 handoff log). **Belum di-push ke remote**, sesuai instruksi eksplisit user di awal eksekusi sub-project ini.
