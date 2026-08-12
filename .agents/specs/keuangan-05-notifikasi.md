# Spec: Keuangan 05 — Notifikasi

> Status: Disetujui — siap ke Implementation Plan (dieksekusi setelah Sub-project 4 selesai & diverifikasi).

## Konteks & Dependensi

Bergantung pada:
- **Sub-project 2 (2a, 2b-1, 2b-2, 2b-3)**: pembuatan tagihan oleh `TagihanBillingGenerator` (`net_amount`, `jatuh_tempo`, `billing_period`), `billing_job_logs`.
- **Sub-project 3**: Auto-Allocation Engine dan logika skip tagihan bersaldo kurang.
- **Sub-project 4**: callback pembayaran sukses, persetujuan/penolakan transfer manual.

Memanfaatkan infrastruktur notifikasi yang sudah ada di codebase:
- `app/Notifications/Channels/WhatsAppChannel.php` (Fonnte API).
- Laravel Mail (Mailable / MailChannel).
- Laravel Database Notification (channel `database` bawaan Laravel, untuk in-app notification / badge lonceng).

## Tujuan Sub-project 5

Orang tua menerima pemberitahuan keuangan penting (terbit tagihan baru, pembayaran berhasil, verifikasi transfer manual disetujui/ditolak, saldo wallet tidak cukup, dan pengingat jatuh tempo) melalui channel yang mereka pilih (WhatsApp, Email, In-App).

## Keputusan Desain

**Template Notifikasi Berbasis Class Laravel, Bukan Konfigurasi Dinamis di DB**:
- Mengikuti pola notifikasi existing pada modul lain di codebase (`TugasBatchDibuatNotification`, `SesiReminderNotification`, dll — lihat `app/Notifications/`).
- Setiap event keuangan diwakili oleh satu class `Notification` di namespace `App\Notifications\Finance\`.
- Properti `is_urgent` ditentukan di tingkat class. Jika `is_urgent = true`, notifikasi wajib dikirim ke seluruh channel aktif tanpa dipengaruhi toggle preferensi user.
- Konsekuensi: **tidak ada tabel `notification_events`** — perubahan teks pesan butuh deploy, bukan edit dari UI admin. Pertukaran yang disengaja demi konsistensi dengan seluruh notifikasi lain di aplikasi.

## Perubahan Skema

### 1. Tabel Baru — `user_notification_preferences`

```
user_notification_preferences
├─ id
├─ user_id             FK → users, cascade delete
├─ module              ENUM('finance', ...) (extensible)
├─ channel_push        boolean DEFAULT false (disiapkan, tidak dipakai — push belum ada infra)
├─ channel_wa          boolean DEFAULT true
├─ channel_email       boolean DEFAULT true
└─ timestamps

UNIQUE(user_id, module)
```
- In-App Notification (Database channel) **selalu terkirim** (tidak dapat dinonaktifkan oleh user) — badge lonceng adalah jaring pengaman minimal, tidak butuh preferensi tersendiri.
- Default: WhatsApp dan Email bernilai `true`.

### 2. Tabel Baru — `notification_logs` (Audit & Debugging)

```
notification_logs
├─ id
├─ user_id             FK → users, cascade delete
├─ event_key           string (nama class Notification, mis. 'App\Notifications\Finance\PembayaranBerhasilNotification')
├─ channel             ENUM('wa', 'email', 'database')
├─ payload             json
├─ status              ENUM('sent', 'failed', 'skipped') ('skipped' = user matikan preferensi channel ini)
├─ error_message       text NULLABLE
└─ timestamps
```
Log murni untuk audit/debugging (mis. cek kenapa WA tidak sampai) — tidak menggantikan tabel `notifications` bawaan Laravel yang tetap dipakai untuk badge in-app.

## Daftar Event & Notification Class

Namespace: `App\Notifications\Finance\`

| Class | Trigger | is_urgent | Ringkasan Pesan |
|---|---|---|---|
| `TagihanDiterbitkanNotification` | `TagihanBillingGenerator` berhasil membuat tagihan baru (Sub-project 2) | false | "Tagihan {jenis_tagihan} periode {billing_period} sebesar Rp{net_amount} telah diterbitkan, jatuh tempo {jatuh_tempo}." |
| `PembayaranBerhasilNotification` | Pembayaran sukses diverifikasi (BRI callback, top-up, debit wallet, atau kasir loket) — SATU event generik lintas metode (Sub-project 3 & 4) | false | "Pembayaran {tagihan} sebesar Rp{amount} berhasil melalui {metode}." |
| `TransferManualDisetujuiNotification` | Admin menyetujui transfer manual (`manual_payment_requests.status → APPROVED`, Sub-project 4) | false | "Bukti transfer pembayaran Anda telah diverifikasi dan disetujui." |
| `TransferManualDitolakNotification` | Admin menolak transfer manual (`manual_payment_requests.status → REJECTED`, Sub-project 4) | **true** | "Bukti transfer pembayaran Anda ditolak: {rejection_reason}. Silakan ajukan ulang." |
| `SaldoTidakCukupNotification` | Auto-Allocation Engine men-skip tagihan prioritas tertinggi karena saldo kurang (Sub-project 3) | false | "Saldo wallet Anda tidak mencukupi untuk pembayaran {tagihan}. Kekurangan: Rp{selisih}." |
| `DueReminderNotification` | Cron pengingat harian pada H-3 dan H-1 sebelum tanggal `jatuh_tempo` | false (H-3) / **true** (H-1) | "Tagihan {jenis_tagihan} akan jatuh tempo pada {jatuh_tempo}. Segera lakukan pembayaran." |

> *Catatan Desain*: `TagihanDibatalkanNotification` sengaja tidak dibuat karena pembatalan tagihan merupakan tindakan administratif korektif yang cukup terlihat saat orang tua mengakses portal (Sub-project 6).

## Logika Pengiriman (`NotificationDispatcher`)

1. Cek nilai `$notification->isUrgent()`.
2. Jika `is_urgent == true`: kirim ke semua channel (WhatsApp, Email, dan Database), tanpa cek preferensi.
3. Jika `is_urgent == false`: periksa `user_notification_preferences` milik user untuk modul `finance`. Kirim WA jika `channel_wa = true`, kirim Email jika `channel_email = true`. Database notification selalu dikirim.
4. Setiap channel dieksekusi secara independen (kegagalan pengiriman WA tidak menggagalkan pengiriman Email atau Database).
5. Catat hasil setiap pengiriman ke `notification_logs`.

## Scheduler Pengingat Jatuh Tempo (H-3 dan H-1)

Command `billing:kirim-due-reminder`, dijadwalkan di `routes/console.php`:
```php
Schedule::command('billing:kirim-due-reminder')->dailyAt('08:00');
```
Algoritma:
- Query tagihan dengan `status IN ('belum_bayar', 'sebagian')` dan `jatuh_tempo IN (today+3 hari, today+1 hari)`.
- Pastikan idempotensi dengan memeriksa `notification_logs` (kombinasi `tagihan_id` + `jatuh_tempo`) agar tidak mengirimkan pengingat ganda pada hari yang sama.
- Kirim ke kontak utama orang tua siswa terkait (`siswa_orang_tua.is_kontak_utama = true`) — **untuk H-3 MAUPUN H-1** (lihat "Ambiguitas Terselesaikan" di bawah untuk konfirmasi keputusan ini).

## Yang TIDAK Termasuk Sub-project 5

- UI pengaturan preferensi notifikasi di sisi portal orang tua (toggle WA/Email per modul) — logic backend (`user_notification_preferences` + `NotificationDispatcher`) siap, UI-nya bagian dari Sub-project 6.
- Push notification perangkat mobile (FCM) — dikonfirmasi drop dari Sub-project 1.
- **Badge/dropdown lonceng notifikasi in-app di header** — Sub-project 5 tetap mengirim ke Laravel `database` channel (mengisi tabel `notifications` bawaan), tapi **TIDAK membangun UI badge/dropdown-nya**. Notifikasi in-app "ada" di database begitu Sub-project 5 selesai, tapi belum terlihat oleh user sampai Sub-project 6 membangun komponennya.

## Ambiguitas Terselesaikan

- [x] Template notifikasi → Menggunakan class `Notification` Laravel standar, hardcoded (bukan admin-configurable).
- [x] Kolom jatuh tempo → Konsisten menggunakan `jatuh_tempo` dari model `Tagihan` (bukan `due_date` — nama kolom asli di skema DB, dikoreksi dari draf awal spec ini yang sempat memakai keduanya secara tidak konsisten).
- [x] Pembatalan tagihan → Tidak memicu notifikasi real-time.
- [x] **Cakupan penerima reminder H-3 vs H-1** (2026-08-11, dikonfirmasi ulang user setelah ditemukan perbedaan dengan draf spec sebelumnya): **kontak utama saja untuk KEDUA reminder** (H-3 dan H-1). Draf spec sebelumnya sempat menyisakan ini sebagai "keputusan sementara" dengan opsi mengirim ke SEMUA kontak orang tua khusus untuk H-1 (karena urgent) — user memutuskan tetap kontak utama saja untuk kesederhanaan implementasi, mengorbankan cakupan ekstra di H-1 demi konsistensi & kesederhanaan.
- [x] **Asumsi badge notifikasi in-app "sudah ada"** (2026-08-11, ditemukan saat audit sebelum implementation plan ditulis): **SALAH — dicek langsung, tidak ada satu pun dari 11 `Notification` class existing (`app/Notifications/`) yang memakai channel `database`.** Tidak ada badge/dropdown lonceng in-app di codebase ini sama sekali saat ini. Dikonfirmasi ke user: Sub-project 5 tetap backend-only (kirim ke channel `database` seperti rencana awal, siap dipakai), UI badge-nya tetap scope Sub-project 6 seperti draf spec awal — cuma asumsi "kemungkinan besar sudah ada, tinggal reuse" yang keliru dan sekarang diluruskan di dokumen ini.

---

## Addendum (2026-08-11): 2 Perluasan Scope + Detail Teknis

Ditemukan saat riset pra-plan bahwa endpoint approve/reject `ManualPaymentRequest` (dari Sub-project 4) belum pernah dibangun — cuma model+migration+pembuatan request `PENDING` yang ada (`PaymentService::createManualPayment()`). Dikonfirmasi ke user: dibangun sebagai bagian Sub-project 5 (bukan ditunda), karena jadi satu-satunya hook point untuk 2 dari 6 notification class di spec ini. Detail kedua perluasan scope ada di bawah.

### A. Skip-Tracking di `AutoAllocationEngine::run()`

**Lokasi persis penyisipan** — dibaca langsung dari `app/Services/Finance/AutoAllocationEngine.php` versi saat ini (baris 14-98):

```php
public function run(Wallet $wallet): void
{
    DB::transaction(function () use ($wallet) {
        $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
        // ... query $tagihans (baris 26-34), tidak berubah ...

        $allocated = [];
        $totalAllocatedAmount = 0;

        foreach ($tagihans as $tagihan) {
            if ($saldo <= 0) { break; }
            $sisaTagihan = $tagihan->net_amount - $tagihan->paid_amount;
            $amountToPay = min($saldo, $sisaTagihan);
            if ($amountToPay > 0) { /* ...isi $allocated seperti sekarang... */ }
        }

        // ... blok if ($totalAllocatedAmount > 0) { ...isi Pembayaran/PembayaranTagihan/Tagihan seperti sekarang... }
    });
}
```

**Fakta penting yang menyederhanakan desain**: query `$tagihans` (baris 26-34) sudah difilter `status IN ('belum_bayar', 'sebagian')`, yang menjamin `net_amount > paid_amount` untuk SETIAP baris — artinya `$sisaTagihan` selalu `> 0` untuk tagihan mana pun yang benar-benar dicapai loop. Kombinasi dengan guard `if ($saldo <= 0) break;` di awal iterasi berarti: **setiap tagihan yang benar-benar diproses loop PASTI masuk `$allocated`** (tidak pernah ada kasus "diproses tapi $amountToPay <= 0"). Jadi tagihan yang "ter-skip" bukan hasil dari cabang logic baru — cukup dihitung SETELAH loop selesai sebagai selisih set:

```php
$skippedTagihan = $tagihans->whereNotIn('id', collect($allocated)->pluck('tagihan.id'));
```

**Di mana persis disisipkan**: baris ini ditambahkan tepat SETELAH `foreach` loop selesai (setelah baris 60 di kode saat ini), MASIH DI DALAM closure `DB::transaction(...)` — karena cuma butuh data yang sudah ada di memori (`$tagihans`, `$allocated`), tidak perlu query tambahan, tidak menyentuh row/lock apa pun yang belum dikunci. Variabel `$skippedTagihan` di-capture keluar transaction lewat `use (&$skippedTagihan)` pada closure — **pola identik** dengan `$walletTopupData` di `BriWebhookController::handlePaymentNotification()` (Sub-project 04, sudah diaudit & disetujui).

**Notifikasi dikirim SETELAH `DB::transaction(...)` selesai** (setelah baris 97/98 kode saat ini), loop `foreach ($skippedTagihan as $tagihan) { ... }` memanggil `SaldoTidakCukupNotification` ke kontak utama siswa pemilik tagihan tsb. **Prinsip yang dijaga**: TIDAK ADA operasi DB baru (query/lock) yang ditambahkan ke dalam transaction — murni pembacaan array yang sudah ada di memori, jadi tidak mengubah sama sekali pola locking yang diperbaiki di audit Sub-project 03 (nested-transaction fix, `debitWithinTransaction()` vs `debit()`). `run()` sendiri TETAP `void` (satu-satunya caller, `Wallet::topup()` baris 71, tidak perlu diubah) — notifikasi dikirim dari DALAM `run()`, bukan di-bubble-up ke caller.

**In-memory atau persist ke DB?** **Murni in-memory** (array lokal, tidak ada tabel/kolom baru). Alasan:
1. `notification_logs` (tabel baru di spec ini) SUDAH mencatat setiap percobaan kirim `SaldoTidakCukupNotification` — audit trail "kapan siswa X diberitahu saldo kurang" sudah tercakup di situ.
2. `wallet_mutasi` + `billing_job_logs` yang sudah ada tetap menyediakan jejak forensik "kenapa tagihan ini belum lunas" (saldo di titik waktu T) kalau suatu saat perlu direkonstruksi manual.
3. Tabel "skipped_tagihan" terpisah adalah scope yang tidak diminta spec manapun — YAGNI.

### B. Endpoint Approve/Reject `ManualPaymentRequest`

**Otorisasi**: permission `pembayaran.verifikasi` — SUDAH ADA di `database/seeders/PermissionSeeder.php` (dikonfirmasi langsung, baris yang sama dengan `pembayaran.view`, `pembayaran.catat-manual`), tidak perlu permission baru.

**Konfirmasi alur `topup()` — TIDAK RELEVAN untuk endpoint ini, dan ini penting untuk dipahami sebelum implementasi:**

Dibaca langsung `PaymentService::createManualPayment(Siswa $siswa, Collection $tagihans, array $data)` — parameter `$tagihans` WAJIB diisi (dipakai `createPembayaranRecord()` untuk membangun baris `pembayaran_tagihan` per tagihan yang dibayar). **Tidak ada jalur kode di mana pun untuk "transfer manual yang ditujukan sebagai top-up wallet murni"** — beda dengan VA/QRIS yang punya tipe eksplisit `WALLET_PERMANENT` di `getOrCreatePermanentVa()`. Manual payment di codebase ini SELALU menyasar tagihan spesifik lewat `pembayaran_tagihan`, tidak pernah wallet secara langsung. `Pembayaran.topup_status` (kolom dari Sub-project 04) tidak pernah di-set oleh `createManualPayment()`/`createPembayaranRecord()` — tetap default `'none'`.

**Konsekuensi desain**: approve action **TIDAK PERNAH memanggil `Wallet::topup()`**, jadi tidak butuh pola "keluarkan side-effect dari transaction" seperti webhook BRI. Approve action mengikuti pola `createCashPayment()` (persis di file yang sama, `PaymentService.php` baris 147-164) — alokasi terjadi LANGSUNG di dalam satu transaction, karena satu-satunya efek samping adalah `PaymentAllocationService::allocate($pembayaran)` (query+update biasa, bukan operasi wallet dengan lock terpisah).

**Desain controller**:
```php
// app/Http/Controllers/Admin/ManualPaymentController.php (baru)

public function approve(Request $request, ManualPaymentRequest $manualPaymentRequest): RedirectResponse
{
    $this->authorize('pembayaran.verifikasi');

    if ($manualPaymentRequest->status !== 'PENDING') {
        // 422 — sudah diproses sebelumnya, cegah approve/reject ganda
    }

    DB::transaction(function () use ($manualPaymentRequest, $request) {
        $manualPaymentRequest->update([
            'status' => 'APPROVED',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $pembayaran = $manualPaymentRequest->pembayaran;
        $pembayaran->update(['status' => 'lunas']);

        app(PaymentAllocationService::class)->allocate($pembayaran);
    });

    // Notifikasi TransferManualDisetujuiNotification dikirim di sini, SETELAH transaction —
    // konsisten dengan prinsip "side-effect eksternal (termasuk notifikasi) di luar transaction",
    // meski di kasus ini tidak ada risiko nested-lock karena tidak ada Wallet::topup() terlibat.
}

public function reject(Request $request, ManualPaymentRequest $manualPaymentRequest): RedirectResponse
{
    $this->authorize('pembayaran.verifikasi');
    $request->validate(['rejection_reason' => 'required|string|max:255']);

    if ($manualPaymentRequest->status !== 'PENDING') {
        // 422 — sudah diproses sebelumnya
    }

    DB::transaction(function () use ($manualPaymentRequest, $request) {
        $manualPaymentRequest->update([
            'status' => 'REJECTED',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        $manualPaymentRequest->pembayaran->update(['status' => 'ditolak']);
    });

    // Notifikasi TransferManualDitolakNotification (is_urgent=true) dikirim di sini.
}
```

**Guard idempotency eksplisit**: cek `status !== 'PENDING'` sebelum memproses APPROVE atau REJECT — mencegah approve/reject ganda (mis. admin klik approve dua kali, atau approve setelah reject) menghasilkan notifikasi/alokasi dobel. Pola ini konsisten dengan idempotency check yang sudah ada di `BriWebhookController` (cek `status === 'PAID'` sebelum proses) dan `batalTagihan()` (cek `status !== 'belum_bayar'`).

**Yang TIDAK termasuk endpoint ini** (scope minimal, cukup untuk membuka hook notifikasi):
- UI admin untuk daftar `ManualPaymentRequest` pending + tombol approve/reject — di luar scope sub-project ini (backend-only, sama seperti keputusan Sub-project 04 untuk seluruh Payment Channels). Endpoint POST siap dipakai kapan pun UI-nya dibangun.
- Preview/download bukti transfer (`transfer_proof_path`) — sudah ada kolomnya dari Sub-project 04, tidak perlu logic baru di sub-project ini.
