# 🏦 Brainstorming: Modul Sistem Keuangan Sekolah Dinamis

> Sesi GrillMe — v2.0 | 2026-08-08 | Status: ADR Diperbarui — Siap ke Spec Final

---

## 📋 Architectural Decision Record (ADR)

### Sesi 1 — Keputusan Fondasi (v1)

| # | Topik | Keputusan |
|---|-------|-----------|
| 1 | Logika Auto-Debet | **Priority Score** per jenis tagihan — Admin definisikan angka, terkecil dibayar duluan |
| 2 | Saldo Tidak Cukup | **Skip** — Tagihan yang tidak bisa dilunasi penuh dilewati, coba tagihan berikutnya |
| 3 | Granularitas Priority | **Global per Jenis Tagihan** — berlaku sama untuk semua siswa |
| 4 | Approval Transfer Manual | **Pending Verification Queue** — Admin approve/reject + rejection reason, orang tua dinotifikasi |
| 5 | Aliran Uang | **Hybrid** — BRI VA/QRIS bisa bypass wallet ATAU top-up wallet; Transfer Manual/Cash selalu ke wallet |
| 6 | Overpayment | **Saldo Wallet** — Kelebihan bayar otomatis masuk saldo wallet siswa |
| 7 | Scope Dashboard Ortu | **Full Suite**: Tagihan, Saldo, History, Notifikasi 3-event, PDF Kwitansi, Multi-anak, Info VA |

### Sesi 2 — Keputusan Lanjutan (v2) ⬅️ BARU

| # | Topik | Keputusan |
|---|-------|-----------|
| 8 | Toggle Auto-Debet | **Global Toggle** di `system_settings` — satu saklar untuk semua siswa sekolah |
| 9 | UX Mode Manual (Toggle OFF) | **Multi-select bebas** — Orang tua pilih 1 atau banyak tagihan, lalu pilih metode bayar |
| 10 | Billing Job Scheduler | **Hybrid Cron + Event-Based** — Cron harian untuk tagihan rutin + Event trigger saat siswa baru/kelas aktif; implementasi sinkronus (tanpa Redis queue) untuk MVP |
| 11 | Notifikasi Multi-Channel | **Per-user preference per modul** — Orang tua atur Push/WA/Email per kategori (Keuangan, dll.) + Admin veto `is_urgent` untuk force-send semua channel |
| 12 | VA Wallet (Top-Up) | **VA Permanen / Reusable** — No expire, orang tua simpan di m-banking, top-up kapan saja |
| 13 | VA Tagihan Langsung | **Per Bill Type expire config** — SPP bisa expire 3 hari, Kegiatan Insidental bisa expire 24 jam |
| 14 | Skip Notification + UX | **Banner aktif** di dashboard + tombol "Top-up Sini" dengan nominal prefill = kekurangan tagihan prioritas tertinggi |

---

## 🔄 Perubahan Arsitektur dari v1 ke v2

```
v1                                      v2
─────────────────────────────────────────────────────────────────────
Auto-debet selalu aktif           →  Global Toggle (ON/OFF)
Tidak ada mode manual             →  Multi-select tagihan saat OFF
Admin trigger tagihan manual      →  Cron harian + Event-based otomatis
Push + SMS notifikasi             →  Push + WA + Email, per-user preference
VA expire: global config          →  VA Wallet: permanen | VA Tagihan: per bill type
Skip: silent                      →  Skip: banner aktif + tombol Top-up prefill
```

---

## 🗺️ User Journey v2: Orang Tua — Dashboard ke Pembayaran

```
┌───────────────────────────────────────────────────────────────────────┐
│                     ORANG TUA BUKA APLIKASI                           │
└─────────────────────────────┬─────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────────────┐
│  DASHBOARD UTAMA                                                       │
│                                                                        │
│  ┌──────────────────────┐  ┌────────────────────────────────────────┐ │
│  │ Saldo Wallet         │  │ Notifikasi (sesuai preferensi user)   │ │
│  │ Rp 250.000           │  │ 🔔 Tagihan SPP Agustus diterbitkan    │ │
│  │ VA Permanen:         │  │ 🔔 Auto-debet berhasil Rp 150.000     │ │
│  │ 0081-XXXXXXXX        │  │ 🔔 Transfer manual disetujui          │ │
│  │ [+ Top Up]           │  └────────────────────────────────────────┘ │
│  └──────────────────────┘                                              │
│                                                                        │
│  ⚠️  BANNER SKIP ALERT (muncul jika ada tagihan prioritas 1 di-skip)  │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │ ⚡ Saldo tidak cukup untuk SPP Agustus (Rp 500.000)            │  │
│  │    Kekurangan: Rp 400.000                                       │  │
│  │    [Top-up Rp 400.000 Sekarang →]  ← nominal prefill           │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                        │
│  [Pilih Profil Anak: ▼ Ahmad | Budi | Citra]  ← multi-anak           │
└─────────────────────────────┬─────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────────────┐
│  REKAP TAGIHAN AKTIF                                                   │
│                                                                        │
│  ═══════════ MODE AUTO-DEBET ON ═══════════                           │
│  Sistem otomatis membayar berdasarkan prioritas                        │
│  ● SPP Agustus 2026     Rp 500.000  [BELUM BAYAR]  Prioritas:1       │
│  ● Uang Gedung          Rp 200.000  [SEBAGIAN]     Prioritas:2       │
│  ● Studi Lapangan Sept  Rp 150.000  [LUNAS ✓]      Prioritas:3       │
│                                                                        │
│  ═══════════ MODE MANUAL (Auto-Debet OFF) ════════════                │
│  Pilih tagihan yang ingin Anda bayar:                                  │
│  ☑ SPP Agustus 2026     Rp 500.000  [BELUM BAYAR]                    │
│  ☐ Uang Gedung          Rp 200.000  [SEBAGIAN]                       │
│  ☑ Studi Lapangan Sept  Rp 150.000  [BELUM BAYAR]                    │
│                                                                        │
│  Total dipilih: Rp 650.000                                             │
│  [Bayar Tagihan Dipilih →]                                             │
│                                                                        │
│  [Riwayat Transaksi]  [Download Kwitansi PDF]                         │
└─────────────────────────────┬─────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────────────┐
│  PILIH METODE PEMBAYARAN                                               │
│                                                                        │
│  ┌────────────────────────────────────────────────────────────────┐   │
│  │ Membayar: SPP Agustus + Studi Lapangan  |  Total: Rp 650.000  │   │
│  └────────────────────────────────────────────────────────────────┘   │
│                                                                        │
│  🅐  BAYAR VIA BRI VIRTUAL ACCOUNT                                    │
│      └─ VA on-demand, expire sesuai konfigurasi Bill Type             │
│         [Generate VA] → tampilkan nomor + timer countdown             │
│         Verifikasi status otomatis via BRI callback + polling         │
│                                                                        │
│  🅑  BAYAR VIA QRIS (BRI MPM Dinamis)                                │
│      └─ [Generate QR] → scan di app/m-banking manapun                │
│                                                                        │
│  🅒  TOP-UP WALLET (jika saldo cukup, auto-debet berjalan)           │
│      └─ Via VA Permanen (sudah tersimpan) atau QRIS baru              │
│         Setelah top-up, Engine auto-alokasi berjalan otomatis         │
│                                                                        │
│  🅓  TRANSFER MANUAL                                                  │
│      └─ [Upload Bukti Transfer] → Pending Queue → Admin verif         │
│         Notif saat disetujui/ditolak (sesuai preferensi channel)     │
│                                                                        │
│  ──────────────────────────────────────────────────────────────────  │
│  ℹ️  Tunai: ke loket sekolah, Admin input di sistem                  │
└─────────────────────────────┬─────────────────────────────────────────┘
                              │
              ┌───────────────┼───────────────┐
              │               │               │
              ▼               ▼               ▼
        [BRI VA/QRIS]   [Transfer Manual]  [Via Wallet]
        Direct Payment   Pending Queue     Auto-Allocation
        ke tagihan       Admin Review      Engine (jika ON)
              │               │               │
              └───────────────┴───────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────────────┐
│  KONFIRMASI & NOTIFIKASI                                               │
│  ✅ SPP Agustus 2026 — LUNAS                                          │
│  ✅ Studi Lapangan Sept — LUNAS                                        │
│  ✅ Saldo Wallet: Rp 250.000 (overpayment masuk wallet)               │
│  [Download Kwitansi PDF]  [Kembali ke Dashboard]                      │
│                                                                        │
│  → Notifikasi dikirim via channel sesuai preferensi user             │
└───────────────────────────────────────────────────────────────────────┘
```

---

## 🏗️ Billing Job Scheduler — Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                     BILLING JOB SCHEDULER (Hybrid)                          │
└─────────────────────────────────────────────────────────────────────────────┘

  TRIGGER 1: CRON JOB HARIAN
  ─────────────────────────────────────────────────────────
  Jadwal: Setiap hari pukul 00:01 (via Laravel Scheduler)
  
  Algorithm:
  1. Ambil semua bill_types WHERE is_active = true AND frequency IN ('monthly','yearly')
  2. Untuk setiap bill_type, cek apakah sudah ada student_bill untuk periode berjalan
     (idempotency check: jika sudah ada, skip — hindari duplikat)
  3. Tentukan target siswa berdasarkan bill_type.target_scope:
     - ALL_STUDENTS
     - BY_COHORT (angkatan)
     - BY_CLASS
     - BY_STUDENT_ID (list spesifik)
  4. Generate student_bills untuk semua target yang belum punya tagihan periode ini
  5. Kirim notifikasi "Tagihan baru diterbitkan" (via NotificationService)
  6. Log hasil di billing_job_logs

  TRIGGER 2: EVENT-BASED (sinkronus, tanpa Redis Queue)
  ─────────────────────────────────────────────────────────
  Event A: StudentCreated → generate semua tagihan aktif yang berlaku bulan berjalan
  Event B: StudentUpdatedClass → re-evaluate tagihan yang berbasis kelas
  Event C: BillTypeActivated → generate tagihan untuk semua siswa yang sesuai target scope

  Tabel Pendukung:
  ┌─────────────────────────────────────┐
  │  billing_job_logs                   │
  │─────────────────────────────────────│
  │ id                                  │
  │ bill_type_id (FK)                   │
  │ trigger_type (CRON | EVENT)         │
  │ trigger_event (null | EventName)    │
  │ period (YYYY-MM)                    │
  │ bills_generated (int)               │
  │ status (SUCCESS | PARTIAL | FAILED) │
  │ error_log (json, nullable)          │
  │ executed_at                         │
  └─────────────────────────────────────┘
```

---

## 📣 Notification System Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                     NOTIFICATION SYSTEM                                      │
└─────────────────────────────────────────────────────────────────────────────┘

  user_notification_preferences (per orang tua, per modul)
  ┌────────────────────────────────────────┐
  │ id                                     │
  │ user_id (FK)                           │
  │ module   (FINANCE | ATTENDANCE | TASK) │
  │ channel_push   (boolean)               │
  │ channel_wa     (boolean)               │
  │ channel_email  (boolean)               │
  └────────────────────────────────────────┘

  notification_events (konfigurasi per event, oleh Admin)
  ┌──────────────────────────────────────────────────────┐
  │ id                                                    │
  │ event_key  (BILL_CREATED | AUTO_DEBIT_SUCCESS |      │
  │             MANUAL_APPROVED | MANUAL_REJECTED |       │
  │             SKIP_ALERT | DUE_REMINDER | URGENT)       │
  │ module     (FINANCE | ...)                            │
  │ is_urgent  (boolean) ← Admin flag, override user pref│
  │ template_push   (text)                               │
  │ template_wa     (text)                               │
  │ template_email  (text)                               │
  └──────────────────────────────────────────────────────┘

  Logic saat kirim notifikasi:
  1. Cek notification_events.is_urgent
     → TRUE: kirim ke SEMUA channel (push + WA + email) tanpa cek preferensi
     → FALSE: cek user_notification_preferences untuk module terkait
  2. Kirim hanya ke channel yang user aktifkan
  3. Log setiap pengiriman di notification_logs

  notification_logs
  ┌────────────────────────────────────────┐
  │ id                                     │
  │ user_id (FK)                           │
  │ event_key                              │
  │ channel (PUSH | WA | EMAIL)            │
  │ payload (json)                         │
  │ status (SENT | FAILED | PENDING)       │
  │ sent_at                                │
  │ error_message (nullable)               │
  └────────────────────────────────────────┘
```

---

## 🗄️ High-Level ERD v2

Prinsip: **Double-Entry Bookkeeping** + **Idempotent Billing** + **Dual VA Architecture**

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                           CORE ENTITIES                                       │
└──────────────────────────────────────────────────────────────────────────────┘

  ┌─────────────┐         ┌──────────────────┐         ┌──────────────────┐
  │   users     │         │    students       │         │ parent_student   │
  │─────────────│ 1     N │──────────────────│ N     N │──────────────────│
  │ id          │────────▶│ id               │◀────────│ parent_id (FK)   │
  │ name        │         │ name             │         │ student_id (FK)  │
  │ role        │         │ class_id (FK)    │         │ is_primary       │
  │ email       │         │ cohort_year      │         └──────────────────┘
  │ phone       │         │ student_number   │
  └─────────────┘         └────────┬─────────┘
                                   │
                 ┌─────────────────┼─────────────────┐
                 │                 │                   │
                 ▼                 ▼                   ▼
      ┌──────────────────┐  ┌──────────────────┐  ┌───────────────────────┐
      │   wallets        │  │  student_bills   │  │  bill_types           │
      │──────────────────│  │──────────────────│  │───────────────────────│
      │ id               │  │ id               │  │ id                    │
      │ student_id (FK)  │  │ student_id (FK)  │  │ name                  │
      │ balance          │  │ bill_type_id(FK) │  │ priority_score        │← ADR #1
      │ total_topup      │  │ amount           │  │ default_amount        │
      │ total_deducted   │  │ discount_amount  │  │ discount_type         │
      │ va_number (perm.)│←─┤ discount_type    │  │ frequency             │
      │ va_expired_at    │  │ net_amount       │  │  (once/monthly/yearly)│
      │ last_updated_at  │  │ paid_amount      │  │ target_scope          │
      └────────┬─────────┘  │ status           │  │  (ALL/COHORT/CLASS/   │
               │            │  (unpaid/partial/ │  │   SPECIFIC)           │
               │            │   paid/cancelled) │  │ va_expire_hours       │←ADR#13
               │            │ due_date          │  │ is_active             │
               │            │ billing_period    │  │ last_generated_period │
               │            │ generated_at      │  └───────────────────────┘
               │            │ source_trigger    │
               │            │  (CRON|EVENT)     │
               │            └────────┬──────────┘
               │                     │
               └──────────┬──────────┘
                          │
                          ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                     TRANSACTION LEDGER (Double-Entry Core)                   │
└──────────────────────────────────────────────────────────────────────────────┘

  ┌────────────────────────────────────────────────────────────────────────┐
  │  transaction_ledger                                                     │
  │────────────────────────────────────────────────────────────────────────│
  │  id                    UUID / ULID                                     │
  │  reference_code        string unik (untuk kwitansi PDF)               │
  │  transaction_type      enum:                                           │
  │                          TOPUP_BRI_VA      → wallet balance naik      │
  │                          TOPUP_BRI_QRIS    → wallet balance naik      │
  │                          TOPUP_MANUAL      → wallet (post-approval)   │
  │                          TOPUP_CASH        → wallet (admin input)     │
  │                          DEBIT_DIRECT_VA   → bayar tagihan via VA     │
  │                          DEBIT_DIRECT_QRIS → bayar tagihan via QRIS   │
  │                          DEBIT_AUTO_ALLOC  → auto-debet dari wallet   │
  │                          DEBIT_MANUAL_SEL  → bayar pilihan manual     │← BARU
  │                          OVERPAY_REFUND    → kelebihan → wallet       │
  │  amount                decimal(15,2)                                  │
  │  wallet_id (FK)        nullable (null jika bypass wallet)             │
  │  student_bill_id (FK)  nullable (null jika pure top-up)               │
  │  payment_channel       enum: BRI_VA | BRI_QRIS | MANUAL_TRANSFER | CASH│
  │  channel_reference     string (VA number, QR ID, BRI ref, dll.)       │
  │  status                enum: PENDING | SUCCESS | FAILED | CANCELLED   │
  │  initiated_by          user_id (ortu/admin)                           │
  │  verified_by           user_id admin (untuk MANUAL, CASH)             │
  │  is_auto_allocation    boolean (true jika dari Engine)                │← BARU
  │  notes                 text                                           │
  │  created_at / updated_at                                               │
  └────────────────────────────────────────────────────────────────────────┘

  Double-Entry Logic:
  ● Top-Up Wallet:       DEBIT = kas_masuk_sekolah, CREDIT = wallet_siswa
  ● Bayar Langsung:      DEBIT = kas_masuk_sekolah, CREDIT = student_bill
  ● Auto-Debet:          DEBIT = wallet_siswa, CREDIT = student_bill
  ● Pilih Manual Ortu:   DEBIT = kas_masuk_sekolah, CREDIT = student_bill(s)
  ● Overpayment:         DEBIT = kas_masuk_sekolah, CREDIT = student_bill + wallet_sisa

┌──────────────────────────────────────────────────────────────────────────────┐
│                     PAYMENT CHANNEL TABLES                                   │
└──────────────────────────────────────────────────────────────────────────────┘

  ┌──────────────────────────────┐    ┌──────────────────────────────────┐
  │  bri_virtual_accounts        │    │  manual_payment_requests         │
  │──────────────────────────────│    │──────────────────────────────────│
  │ id                           │    │ id                               │
  │ ledger_id (FK)               │    │ ledger_id (FK)                   │
  │ va_number                    │    │ requested_by (FK → users)        │
  │ va_type:                     │    │ amount                           │
  │  WALLET_PERMANENT            │←── │ transfer_proof_path              │
  │  BILL_DIRECT                 │    │ bank_origin                      │
  │ student_id (FK)              │    │ transfer_date                    │
  │ student_bill_id (FK, null)   │    │ status                           │
  │ amount                       │    │  (PENDING|APPROVED|REJECTED)     │
  │ expired_at (null if PERM.)   │    │ reviewed_by (FK → users, null)   │
  │ status                       │    │ reviewed_at                      │
  │  (PERMANENT|WAITING|PAID|    │    │ rejection_reason (text)          │
  │   EXPIRED)                   │    └──────────────────────────────────┘
  │ bri_callback_payload (json)  │
  └──────────────────────────────┘    ┌──────────────────────────────────┐
                                      │  cash_payments                   │
  ┌──────────────────────────────┐    │──────────────────────────────────│
  │  bri_qris_payments           │    │ id                               │
  │──────────────────────────────│    │ ledger_id (FK)                   │
  │ id                           │    │ received_by (FK → users/admin)   │
  │ ledger_id (FK)               │    │ amount                           │
  │ qris_type (TOPUP | DIRECT)   │    │ received_at                      │
  │ student_id (FK)              │    │ notes                            │
  │ student_bill_id (FK, null)   │    └──────────────────────────────────┘
  │ amount                       │
  │ qr_code                      │
  │ expired_at                   │
  │ status                       │
  │ bri_callback_payload (json)  │
  └──────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────┐
│                     SYSTEM SETTINGS & CONFIG                                 │
└──────────────────────────────────────────────────────────────────────────────┘

  ┌─────────────────────────────────────────────┐
  │  system_settings                            │
  │─────────────────────────────────────────────│
  │ key (string, unique)                        │
  │ value (text/json)                           │
  │ description                                 │
  │ updated_by (FK → users)                     │
  │ updated_at                                  │
  │─────────────────────────────────────────────│
  │ Contoh keys:                                │
  │  auto_debit_enabled          = true/false   │← ADR #8
  │  wa_api_provider             = fonnte/cloud │
  │  email_provider              = mailgun/etc  │
  │  default_wallet_va_bank      = BRI          │
  └─────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────────────┐
│                     AUTO-ALLOCATION ENGINE LOGIC v2                          │
└──────────────────────────────────────────────────────────────────────────────┘

  Trigger: Setiap kali wallet.balance berubah naik

  Pre-check:
  0. Cek system_settings WHERE key = 'auto_debit_enabled'
     → FALSE: Engine berhenti, tidak ada auto-debet

  Algorithm (ADR #1, #2, #3, #8):
  1. Ambil semua student_bills WHERE student_id = X AND status IN ('unpaid', 'partial')
  2. ORDER BY bill_types.priority_score ASC, due_date ASC
  3. skipped_bills = [] (track tagihan yang di-skip)
  4. Loop per tagihan:
     a. Jika wallet.balance >= sisa tagihan (net_amount - paid_amount):
        → Lunasi penuh, kurangi balance, buat ledger DEBIT_AUTO_ALLOC, status='paid'
        → Kirim notif AUTO_DEBIT_SUCCESS
     b. Jika wallet.balance < sisa tagihan:
        → SKIP, tambahkan ke skipped_bills
  5. Jika skipped_bills berisi tagihan dengan priority_score terendah (tertinggi prioritas):
     → Tampilkan banner SKIP_ALERT di dashboard ortu
     → Pre-fill nominal Top-up = sisa tagihan prioritas 1 yang di-skip
     → Kirim notif SKIP_ALERT (sesuai preferensi user)

---

## 🔑 Key Architecture Observations v2

1. **Toggle Global**: `auto_debit_enabled` di `system_settings` → Engine mati total jika FALSE
2. **Dual VA Pattern**: `wallet.va_number` untuk top-up permanen; `bri_virtual_accounts.va_type=BILL_DIRECT` untuk per-tagihan dengan expire konfigurasi per `bill_types.va_expire_hours`
3. **Billing Idempotency**: `bill_types.last_generated_period` + cek duplikat sebelum generate mencegah tagihan ganda saat cron atau event terpicu berulang
4. **Notification Veto**: `notification_events.is_urgent = true` mengabaikan `user_notification_preferences` — Admin bisa paksa kirim semua channel untuk darurat
5. **Skip UX**: Banner + prefill top-up menyelesaikan confusion orang tua saat saldo ada tapi tagihan belum lunas
6. **Multi-select Manual**: Saat mode manual, payload ke `transaction_ledger` bisa berisi referensi ke banyak `student_bill_id` via junction table atau JSON array
7. **Audit Trail**: `billing_job_logs` mencatat setiap eksekusi cron/event untuk debuggability

---

## ✅ Ambiguitas Terselesaikan

- [x] Recurring Billing → Hybrid Cron + Event-based (sinkronus)
- [x] Notifikasi Medium → Per-user preference per modul + Admin veto
- [x] Expire VA → Dual: Wallet permanen, Tagihan per bill_type
- [x] Skip Notification → Banner aktif + prefill top-up shortcut
- [x] Auto-debet toggle → Global di system_settings
- [x] Mode manual → Multi-select tagihan bebas

## ⚠️ Ambiguitas Sisa (untuk Spec Final)

- [ ] **Multi-select batch payment**: Jika mode manual pilih 3 tagihan, apakah dibuat 3 VA terpisah atau 1 VA gabungan dengan total nominal?
- [ ] **WA Provider**: Fonnte atau WhatsApp Cloud API Official? (memengaruhi format template & biaya)
- [ ] **Kwitansi PDF**: Template per sekolah (logo, nama sekolah) atau standar sistem? Siapa yang upload template?
- [ ] **Due Date Reminder**: Apakah ada notifikasi pengingat H-3 atau H-1 sebelum jatuh tempo?
