# Modul Sistem Keuangan Sekolah Dinamis — Sub-project 5: Notifikasi

> Status: Disetujui — siap ke Implementation Plan (dieksekusi setelah Sub-project 4 selesai & diverifikasi).

## Konteks & Dependensi

Bergantung pada Sub-project 2 (billing_job_logs, generate tagihan), Sub-project 3 (Auto-Allocation Engine, skip logic), Sub-project 4 (payment channels, manual approval). Reuse infrastruktur notifikasi yang **sudah ada** di codebase: `app/Notifications/Channels/WhatsAppChannel.php` (Fonnte), Laravel Mail, tabel `notifications` bawaan Laravel (database channel). Push/FCM tetap di luar scope (dikonfirmasi di Sub-project 1).

## Tujuan Sub-project 5

Orang tua menerima notifikasi keuangan (tagihan baru, pembayaran sukses, transfer manual disetujui/ditolak, saldo tidak cukup, pengingat jatuh tempo) lewat channel yang mereka pilih sendiri (WA/Email/In-App), kecuali event yang ditandai urgent (selalu ke semua channel).

## Keputusan Desain

**Template notifikasi hardcoded, bukan admin-configurable** — konsisten dengan pola existing (`TugasBatchDibuatNotification`, `SesiReminderNotification`, dll). Tiap event keuangan = 1 class `Notification` Laravel, `is_urgent` adalah properti/method di class itu (bukan kolom di tabel config), isi pesan didefinisikan di kode (Blade untuk email, string builder untuk WA). Konsekuensi: **tidak ada tabel `notification_events`** — perubahan teks pesan butuh deploy, bukan edit dari UI admin. Ini pertukaran yang disengaja demi konsistensi dengan seluruh notifikasi lain di aplikasi.

## Perubahan Skema

### 1. Tabel Baru — `user_notification_preferences`

```
user_notification_preferences
├─ id
├─ user_id        FK → users, cascade delete
├─ module          ENUM('finance', ...)  (extensible untuk modul lain di masa depan)
├─ channel_push     boolean DEFAULT false  (kolom disiapkan, tidak dipakai — push belum ada infra)
├─ channel_wa       boolean DEFAULT true
├─ channel_email     boolean DEFAULT true
└─ timestamps

unique(user_id, module)
```
Default: WA & Email ON, In-App **selalu terkirim tanpa bisa dimatikan** (bukan opsional — badge lonceng adalah jaring pengaman minimal, tidak butuh preferensi tersendiri, konsisten dengan pola Laravel database notifications yang sudah dipakai modul lain).

### 2. Tabel Baru — `notification_logs`

```
notification_logs
├─ id
├─ user_id         FK → users, cascade delete
├─ event_key         string  (nama class Notification, mis. 'App\Notifications\Finance\PaymentSuccessNotification')
├─ channel            ENUM('wa','email','database')
├─ payload             json
├─ status               ENUM('sent','failed','skipped')  ('skipped' = user matikan preferensi channel ini)
├─ error_message         text NULLABLE
└─ timestamps
```
Log murni untuk audit/debugging (mis. cek kenapa WA tidak sampai) — tidak menggantikan tabel `notifications` bawaan Laravel yang tetap dipakai untuk badge in-app.

## Daftar Event & Notification Class

Semua di namespace `App\Notifications\Finance\`:

| Class | Trigger | is_urgent | Ringkasan pesan |
|---|---|---|---|
| `TagihanDiterbitkanNotification` | `TagihanBillingGenerator` sukses generate 1 tagihan (Sub-project 2) | false | "Tagihan {jenis_tagihan} periode {billing_period} sebesar Rp{net_amount} telah diterbitkan, jatuh tempo {due_date}" |
| `PembayaranBerhasilNotification` | Callback/polling BRI sukses, `Wallet::debit()` sukses (auto/manual), atau cash diinput admin (Sub-project 3 & 4) — SATU event generik lintas metode | false | "Pembayaran {tagihan/gabungan} sebesar Rp{amount} berhasil via {metode}" |
| `TransferManualDisetujuiNotification` | `manual_payment_requests.status → APPROVED` (Sub-project 4) | false | "Bukti transfer Anda telah diverifikasi, {tagihan} kini lunas" |
| `TransferManualDitolakNotification` | `manual_payment_requests.status → REJECTED` (Sub-project 4) | true | "Bukti transfer Anda ditolak: {rejection_reason}. Silakan ajukan ulang." |
| `SaldoTidakCukupNotification` | Auto-Allocation Engine skip tagihan priority tertinggi (Sub-project 3) | false | "Saldo wallet tidak cukup untuk {tagihan}, kekurangan Rp{selisih}" |
| `DueReminderNotification` | Cron harian, `due_date` = today+3 atau today+1 (dijadwalkan di sub-project ini) | false (H-3) / **true** (H-1) | "Tagihan {jenis_tagihan} jatuh tempo {due_date_label}, segera lakukan pembayaran" |

`TagihanDibatalkanNotification` **sengaja tidak dibuat** — pembatalan tagihan adalah perubahan administratif, cukup terlihat saat orang tua membuka dashboard (Sub-project 6), tidak perlu push notifikasi real-time.

## Logika Pengiriman

```
NotificationDispatcher::send(User $user, Notification $notification, string $module = 'finance'):
1. is_urgent = $notification->isUrgent()
2. Jika is_urgent: kirim ke SEMUA channel (WA + Email + Database), tanpa cek preferensi
3. Jika tidak: ambil user_notification_preferences (module) → kirim WA jika channel_wa=true,
   Email jika channel_email=true. Database SELALU dikirim (tidak opsional).
4. Tiap channel dicoba independen (WA gagal tidak menghentikan Email/Database).
5. Log setiap percobaan (sent/failed/skipped) ke notification_logs.
```

## Reminder H-3/H-1

Command baru (pola sama `TandaiTugasTerlewat`/`KirimReminderSesi`), didaftarkan di `routes/console.php`:
```php
Schedule::command(KirimDueReminderTagihan::class)->dailyAt('08:00');
```
Algoritma: `tagihan WHERE status IN (belum_bayar, sebagian) AND due_date IN (today+3, today+1)` → per tagihan, cek `notification_logs` belum ada log `DueReminderNotification` untuk `tagihan_id + due_date` kombinasi ini (idempotency, hindari kirim dobel kalau cron re-run) → kirim ke kontak utama (`siswa_orang_tua.is_kontak_utama=true`) atau semua orang tua terkait siswa (perlu diputuskan di implementation plan berdasarkan konvensi UI Sub-project 6 — default aman: kontak utama saja untuk H-3, semua kontak untuk H-1 karena urgent).

## Yang TIDAK Termasuk Sub-project 5

- Push notification/FCM (dikonfirmasi drop dari Sub-project 1).
- UI pengaturan preferensi notifikasi orang tua (toggle WA/Email per modul) — logic backend siap, UI-nya bagian dari Sub-project 6 (parent dashboard) atau halaman profil umum yang sudah ada (bisa reuse pola pengaturan profil existing kalau ada).
- Badge/dropdown lonceng notifikasi in-app di header — kemungkinan besar SUDAH ADA (tabel `notifications` sudah dipakai modul lain), tinggal pastikan notifikasi keuangan muncul di situ; bukan pekerjaan baru kalau komponennya reusable.

## Ambiguitas Terselesaikan

- [x] Template admin-configurable vs hardcoded → Hardcoded Notification class, konsisten pola existing
- [x] Daftar event → Disesuaikan (PAYMENT_SUCCESS generik lintas metode, tanpa notif untuk pembatalan tagihan)

## Ambiguitas Sisa (untuk sub-project berikutnya)

- [ ] H-3 kirim ke kontak utama saja vs semua orang tua terkait siswa — keputusan sementara "kontak utama utk H-3, semua untuk H-1", perlu dikonfirmasi ulang saat implementation plan sub-project ini ditulis
- [ ] Apakah badge notifikasi in-app existing (kalau ada) perlu dikelompokkan per modul (Keuangan terpisah dari Program Pendampingan, dst) — cek komponen UI existing dulu sebelum implementation plan
