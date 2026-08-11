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
- Laravel Database Notification (tabel `notifications` bawaan untuk in-app notification / badge lonceng).

## Tujuan Sub-project 5

Orang tua menerima pemberitahuan keuangan penting (terbit tagihan baru, pembayaran berhasil, verifikasi transfer manual disetujui/ditolak, saldo wallet tidak cukup, dan pengingat jatuh tempo) melalui channel yang mereka pilih (WhatsApp, Email, In-App).

## Keputusan Desain

**Template Notifikasi Berbasis Class Laravel, Bukan Konfigurasi Dinamis di DB**:
- Mengikuti pola notifikasi existing pada modul lain di codebase (`TugasBatchDibuatNotification`, `SesiReminderNotification`, dll).
- Setiap event keuangan diwakili oleh satu class `Notification` di namespace `App\Notifications\Finance\`.
- Properti `is_urgent` ditentukan di tingkat class. Jika `is_urgent = true`, notifikasi wajib dikirim ke seluruh channel aktif tanpa dipengaruhi toggle preferensi user.

## Perubahan Skema

### 1. Tabel Baru — `user_notification_preferences`

```
user_notification_preferences
+- id
+- user_id             FK ? users, cascade delete
+- module              ENUM('finance', ...) (extensible)
+- channel_push        boolean DEFAULT false (disiapkan)
+- channel_wa          boolean DEFAULT true
+- channel_email       boolean DEFAULT true
+- timestamps

UNIQUE(user_id, module)
```
- In-App Notification (Database channel) **selalu terkirim** (tidak dapat dinonaktifkan oleh user).
- Default: WhatsApp dan Email bernilai `true`.

### 2. Tabel Baru — `notification_logs` (Audit & Debugging)

```
notification_logs
+- id
+- user_id             FK ? users, cascade delete
+- event_key           string (nama class Notification)
+- channel             ENUM('wa', 'email', 'database')
+- payload             json
+- status              ENUM('sent', 'failed', 'skipped')
+- error_message       text NULLABLE
+- timestamps
```

## Daftar Event & Notification Class

Namespace: `App\Notifications\Finance\`

| Class | Trigger | is_urgent | Ringkasan Pesan |
|---|---|---|---|
| `TagihanDiterbitkanNotification` | `TagihanBillingGenerator` berhasil membuat tagihan baru | false | "Tagihan {jenis_tagihan} periode {billing_period} sebesar Rp{net_amount} telah diterbitkan, jatuh tempo {jatuh_tempo}." |
| `PembayaranBerhasilNotification` | Pembayaran sukses diverifikasi (BRI callback, top-up, debit wallet, atau kasir loket) | false | "Pembayaran {tagihan} sebesar Rp{amount} berhasil melalui {metode}." |
| `TransferManualDisetujuiNotification` | Admin menyetujui transfer manual (`status ? APPROVED`) | false | "Bukti transfer pembayaran Anda telah diverifikasi dan disetujui." |
| `TransferManualDitolakNotification` | Admin menolak transfer manual (`status ? REJECTED`) | **true** | "Bukti transfer pembayaran Anda ditolak: {rejection_reason}. Silakan ajukan ulang." |
| `SaldoTidakCukupNotification` | Auto-Allocation Engine men-skip tagihan prioritas tertinggi karena saldo kurang | false | "Saldo wallet Anda tidak mencukupi untuk pembayaran {tagihan}. Kekurangan: Rp{selisih}." |
| `DueReminderNotification` | Cron pengingat harian pada H-3 dan H-1 sebelum tanggal `jatuh_tempo` | false (H-3) / **true** (H-1) | "Tagihan {jenis_tagihan} akan jatuh tempo pada {jatuh_tempo}. Segera lakukan pembayaran." |

> *Catatan Desain*: `TagihanDibatalkanNotification` sengaja tidak dibuat karena pembatalan tagihan merupakan tindakan administratif korektif yang cukup terlihat saat orang tua mengakses portal.

## Logika Pengiriman (`NotificationDispatcher`)

1. Cek nilai `$notification->isUrgent()`.
2. Jika `is_urgent == true`: kirim ke semua channel (WhatsApp, Email, dan Database).
3. Jika `is_urgent == false`: periksa `user_notification_preferences` milik user untuk modul `finance`. Kirim WA jika `channel_wa = true`, kirim Email jika `channel_email = true`. Database notification selalu dikirim.
4. Setiap channel dieksekusi secara independen (kegagalan pengiriman WA tidak menggagalkan pengiriman Email atau Database).
5. Catat hasil setiap pengiriman ke `notification_logs`.

## Scheduler Pengingat Jatuh Tempo (H-3 dan H-1)

Command: `billing:kirim-due-reminder` dijadwalkan di `routes/console.php`:
```php
Schedule::command('billing:kirim-due-reminder')->dailyAt('08:00');
```
Algoritma:
- Query tagihan dengan `status IN ('belum_bayar', 'sebagian')` dan tanggal `jatuh_tempo IN (today+3 hari, today+1 hari)`.
- Pastikan idempotensi dengan memeriksa `notification_logs` agar tidak mengirimkan pengingat ganda pada hari yang sama.
- Kirim ke kontak utama orang tua siswa terkait (`siswa_orang_tua.is_kontak_utama = true`).

## Yang TIDAK Termasuk Sub-project 5

- UI pengaturan preferensi notifikasi di sisi portal orang tua (disediakan di Sub-project 6).
- Push notification perangkat mobile (FCM).

## Ambiguitas Terselesaikan

- [x] Template notifikasi ? Menggunakan class `Notification` Laravel standar.
- [x] Kolom jatuh tempo ? Konsisten menggunakan `jatuh_tempo` dari model `Tagihan`.
- [x] Pembatalan tagihan ? Tidak memicu push notification real-time.
