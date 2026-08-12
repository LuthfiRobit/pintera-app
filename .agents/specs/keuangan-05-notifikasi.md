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
