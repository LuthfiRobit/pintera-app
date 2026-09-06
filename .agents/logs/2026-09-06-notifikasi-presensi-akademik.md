# Handoff Log — Notifikasi Presensi Akademik (Opsi A2)

**Tanggal**: 6 September 2026
**Branch**: `akademik-v2`
**Plan**: `.agents/plans/2026-09-06-notifikasi-presensi-akademik.md`
**Base commit**: `b3254a75`

## Ringkasan Fitur

Saat guru mencatat presensi siswa di Jurnal KBM dan status presensi berubah menjadi Izin/Sakit/Alpa/Terlambat, sistem otomatis mendeteksi perubahan itu dan mengirim notifikasi ke kontak utama orang tua siswa (database + WhatsApp, mail kondisional). Tidak ada gating/opt-in — semua perubahan ke 4 status tersebut memicu notifikasi. Kegagalan pengiriman tidak menggagalkan proses simpan jurnal (try/catch, best-effort).

## Yang Dibangun (Task 1-4)

- **`PresensiNotificationService::kirimJikaPerluAtasPerubahan(Presensi $presensi, ?string $statusLama): void`** (`app/Domains/Akademik/Services/PresensiNotificationService.php`) — mendeteksi apakah status presensi berubah menjadi izin/sakit/alpa/terlambat, lalu mengirim `PresensiPengecualianNotification` ke kontak utama orang tua siswa. Try/catch di sekeliling pengiriman supaya kegagalan notifikasi tidak menggagalkan alur utama.
- **`PresensiPengecualianNotification`** (`app/Notifications/Akademik/PresensiPengecualianNotification.php`) — notification class dengan channel database + mail (kondisional) + WhatsApp. Pesan WhatsApp dirender lewat `WhatsAppTemplate::renderKode('presensi_pengecualian', [...])`.
- **`RecordJurnalDanPresensiAction`** dimodifikasi — memanggil `PresensiNotificationService` setelah `DB::transaction()` commit (bukan di dalam closure), dengan status lama presensi diambil sebelum loop update.
- **Seeder `WhatsAppTemplateSeeder`** — entri template baru `presensi_pengecualian` dengan placeholder `{nama_siswa}`, `{status}`, `{tanggal}`, `{keterangan}`.

Detail teknis lengkap ada di commit masing-masing task (lihat bagian Commit di bawah). Semua 4 task sudah lulus review terpisah (task-reviewer subagent), 0 temuan Critical/Important.

## Catatan Minor (Non-blocking, dari Ledger `.superpowers/sdd/progress.md`)

1. **Penamaan `PresensiNotificationService`** tidak mengikuti konvensi `.ai/rules/services.md` (suffix Resolver/Generator/Aggregator/Engine). Ini bukan deviasi implementer — nama tersebut ditentukan secara eksplisit oleh plan/brief itu sendiri.
2. **N+1 query minor** di loop post-transaksi `RecordJurnalDanPresensiAction` — presensi di-refetch per siswa, dan snapshot status lama sebelumnya mengambil semua row (bukan hanya yang relevan). Ini murni catatan efisiensi, bukan bug fungsional.

## Task 5 — Penutup

### Full Test Suite

Dijalankan dua kali:
1. **Run pertama** (`php artisan test --compact`): **5 failed, 2853 passed** (7747 assertions), durasi 613.42s.
2. Investigasi menunjukkan 1 dari 5 kegagalan (`Tests\Feature\WhatsAppTemplateSchemaTest`) adalah konsekuensi langsung dan sah dari Task 4 — seeder menambah 1 template baru (`presensi_pengecualian`), sehingga total baris WhatsAppTemplate berubah dari 9 menjadi 10, tapi test lama masih hardcode `toBe(9)`. Test ini diperbarui (`tests/Feature/WhatsAppTemplateSchemaTest.php`) untuk mengasersikan `toBe(10)` dan menambah assertion untuk kode `presensi_pengecualian`.
3. 4 kegagalan sisanya (`M3DemoDataSeederTest`, `PresensiSeederTest`, `SesiPembelajaranSeederTest` x2 assertion) **BUKAN regresi dari plan ini**. Dibuktikan via `git diff b3254a75 HEAD -- database/migrations database/seeders` yang hanya menunjukkan 5 baris tambahan di `WhatsAppTemplateSeeder.php` — tidak ada perubahan lain di seeder/migration manapun. Root cause: `SesiPembelajaranSeeder`/`PresensiSeeder` memakai `Carbon::yesterday()` relatif terhadap tanggal jalan (hari ini 6 September 2026 adalah Minggu, kemarin Sabtu — bukan hari sekolah), sehingga tidak ada sesi/presensi yang di-generate untuk tanggal tsb. Ini bug seeder pre-existing yang bergantung pada tanggal wall-clock saat suite dijalankan, di luar scope plan notifikasi presensi ini.
4. **Run kedua** (setelah fix test WhatsApp): **4 failed, 2854 passed** (7757 assertions), durasi 549.33s. Baseline sebelum plan ini (dari plan audit sebelumnya, commit dasar `2710bf7e`..`b3254a75`) dikonfirmasi 2717 passed 0 failures — namun karena baseline tsb kemungkinan dijalankan pada tanggal berbeda (seeder date-dependent), perbandingan langsung angka failure tidak apple-to-apple. Angka pasti hasil run hari ini: **2854 passed, 4 failed (seluruhnya pre-existing/unrelated)**.

### Pint

`vendor/bin/pint --dirty --format agent` → `{"tool":"pint","result":"passed"}` (tidak ada file yang perlu diformat ulang).

### Update Roadmap

`PETA_PENGEMBANGAN.md` §4 Level Orang Tua/Wali — baris lama "Notifikasi presensi & penjemputan (tap-in/tap-out)" dipecah jadi 2 baris:
- **"Notifikasi Presensi Akademik (Jurnal)"** — ✅ Ada (6 September 2026).
- **"Presensi Fisik/Check-in-Check-out (Kartu Pelajar QR)"** — tetap Belum Ada, dicatat sebagai proyek terpisah.

Baris di §Fase 5 (Quick wins) juga diperbarui untuk mencerminkan status selesai sebagian ini.

## Commit per Task

| Task | Commit | Deskripsi |
| --- | --- | --- |
| Base (sebelum Task 1) | `b3254a75` | docs(akademik): implementation plan notifikasi presensi akademik (opsi A2) |
| Task 1 | `a9cdc631` | feat(akademik): PresensiNotificationService -- deteksi perubahan status & kirim notifikasi ke kontak utama |
| Task 2 | `a6499963` | feat(akademik): lengkapi PresensiPengecualianNotification -- toDatabase, toWhatsApp via WhatsAppTemplate |
| Task 3 | `9856b181` | feat(akademik): RecordJurnalDanPresensiAction kirim notifikasi presensi setelah simpan jurnal |
| Task 4 | `753c294d` | feat(akademik): tambah template WA presensi_pengecualian |
| Task 5 | (commit ini) | docs(akademik): handoff log & update roadmap -- notifikasi presensi akademik selesai |

## Test Baru dari Plan Ini

- Task 1: 8 test baru (parametrik 4 status, kontak utama, no-gating, try/catch kegagalan).
- Task 2: 3 test baru (via/toDatabase/toWhatsApp) + 8 test Task 1 di-rerun tanpa regresi.
- Task 3: 2 test end-to-end baru di `JurnalKbmControllerTest.php`.
- Task 5: 1 test lama diperbarui (bukan test baru) — `WhatsAppTemplateSchemaTest` disesuaikan dengan penambahan template Task 4.

Total bersih test baru dari Task 1-3: 13.
