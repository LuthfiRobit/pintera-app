# Modul Sistem Keuangan Sekolah Dinamis — Sub-project 2: Billing Engine Admin

> Status: Disetujui — siap ke Implementation Plan (dieksekusi setelah Sub-project 1 selesai & diverifikasi).

## Konteks & Dependensi

Bergantung penuh pada skema yang dibangun di Sub-project 1 (`docs/superpowers/specs/2026-08-10-keuangan-01-fondasi-skema-design.md`): `tagihan` polymorphic, `jenis_tagihan` (mode/tanggal_mulai/selesai/generate/hari_jatuh_tempo), `jenis_tagihan_sasaran_grup`+`jenis_tagihan_sasaran_kriteria` (target & tarif), `jenis_tagihan_keringanan`+`siswa_keringanan`, `nominal_tagihan_siswa`, `kolom paid_amount`. Sub-project ini membangun **service generate tagihan**, **UI admin CRUD jenis_tagihan**, dan **dashboard monitoring** — tidak membangun wallet atau payment channel (nilai `paid_amount` masih 0 sepanjang sub-project ini kecuali diinput manual untuk keperluan testing).

## Tujuan Sub-project 2

Admin bisa: (1) membuat/mengedit `jenis_tagihan` lengkap dengan target sasaran, tarif berdimensi, dan keringanan lewat UI; (2) tagihan ter-generate otomatis (cron harian + 3 event trigger) atau manual (tombol "Proses Tagihan"); (3) memonitor penerimaan tagihan per jenis tagihan (ringkasan, daftar penerima, tunggakan) dan membatalkan tagihan yang keliru.

## Service: `TagihanBillingGenerator`

Analog `TugasBatchGenerator` (Program Pendampingan) — satu service dipakai oleh cron, tombol manual, dan event listener.

**Kandidat `jenis_tagihan` per pemicu:**
- **Cron harian** (`routes/console.php`, `Schedule::command(...)->dailyAt('00:01')`): `WHERE mode='otomatis' AND is_active AND tanggal_generate = DAY(today) AND today BETWEEN tanggal_mulai AND tanggal_selesai (atau tanggal_selesai NULL)`.
- **Manual** (tombol admin "Proses Tagihan" per baris jenis_tagihan): satu `jenis_tagihan_id` dipilih langsung, tidak terikat `tanggal_generate` — untuk backfill/testing.
- **Event `StudentCreated`**: semua `jenis_tagihan` aktif yang sasarannya match siswa baru, generate untuk periode berjalan.
- **Event `StudentUpdatedClass`**: re-evaluate `jenis_tagihan` yang sasarannya match kelas BARU siswa tsb, generate yang belum ada untuk periode berjalan. Tagihan yang sudah terlanjur dibuat berdasarkan kelas lama TIDAK dibatalkan otomatis (snapshot historis).
- **Event `BillTypeActivated`** (`jenis_tagihan.is_active` diubah true): generate untuk semua siswa yang match sasaran, periode berjalan.

**Algoritma per jenis_tagihan kandidat:**
1. Evaluasi `jenis_tagihan_sasaran_grup(tipe='sasaran')` → daftar `siswa_id` target (kosong grup = semua siswa lembaga terkait).
2. Untuk setiap siswa target, **dalam transaction terpisah per siswa** (kegagalan 1 siswa tidak menggagalkan siswa lain):
   a. Idempotency check: `tagihan WHERE tagihable_type=Siswa AND tagihable_id=X AND jenis_tagihan_id=Y AND billing_period=Z AND status != 'dibatalkan'` — jika sudah ada, skip.
   b. Resolve nominal: `nominal_tagihan_siswa` (siswa ini) → jika tidak ada, evaluasi `jenis_tagihan_sasaran_grup(tipe='tarif')` match pertama (`id` ASC) → jika tidak ada, `jenis_tagihan.default_amount`.
   c. Resolve keringanan: cek `siswa_keringanan` aktif (berlaku_dari <= today <= berlaku_sampai OR berlaku_sampai NULL) milik siswa → cari `jenis_tagihan_keringanan` yang match `jenis_tagihan_id` ini → jika lebih dari satu match, ambil nilai potongan terbesar → hitung `discount_amount`/`discount_type`, snapshot ke tagihan.
   d. Hitung `net_amount = nominal - discount`. Hitung `due_date`: `billing_period` awal bulan + `hari_jatuh_tempo` hari (mode otomatis); untuk mode manual tanpa `hari_jatuh_tempo`, `due_date` null atau diisi manual oleh admin saat generate.
   e. Insert `tagihan` (`tagihable_type=Siswa`, `source_trigger` sesuai pemicu, `billing_period` = bulan berjalan untuk mode otomatis / null untuk manual).
   f. Kegagalan (exception apapun) di satu siswa: catat ke array error, lanjut siswa berikutnya — TIDAK menghentikan batch.
3. Setelah semua siswa diproses: insert `billing_job_logs` (`status`: SUCCESS jika 0 error, PARTIAL jika sebagian error, FAILED jika semua gagal; `bills_generated`; `error_log` json `[{siswa_id, message}]` untuk kasus PARTIAL/FAILED).
4. Cron besok otomatis retry siswa yang gagal kemarin (idempotency check akan menganggap mereka belum punya tagihan periode ini).

```
billing_job_logs
├─ id
├─ jenis_tagihan_id  FK → jenis_tagihan, cascade delete
├─ trigger_type       ENUM('cron','manual','event')
├─ trigger_event      string NULLABLE ('StudentCreated'|'StudentUpdatedClass'|'BillTypeActivated')
├─ period              string(7) NULLABLE
├─ bills_generated     unsignedInteger
├─ status               ENUM('success','partial','failed')
├─ error_log            json NULLABLE
├─ executed_at
└─ timestamps
```

## UI Admin — Form Jenis Tagihan

Mengikuti struktur referensi Pusdatren (lihat spec Sub-project 1 §3/§4/§5 untuk bentuk data), diimplementasikan sebagai modal/halaman dengan 4 section:
1. **Informasi Dasar**: nama, kategori, mode (toggle Manual/Otomatis — mengubah field yang tampil), default_amount, bisa_dicicil (existing, untuk kompatibilitas PPDB path), status aktif. Mode Otomatis menampilkan tambahan: tanggal_mulai, tanggal_selesai, tanggal_generate, hari_jatuh_tempo.
2. **Target Sasaran**: radio "Semua Siswa" / "Berdasarkan Kriteria" → kalau kriteria, list card "Sasaran #N" (masing-masing = 1 `jenis_tagihan_sasaran_grup(tipe=sasaran)`) dengan field lembaga/tahun_ajaran/tingkat/kelas/jenis_kelamin/status_siswa, tombol "+ Tambah Sasaran".
3. **Tarif Berdimensi** (opsional): list card "Tarif #N" (`tipe=tarif`) dengan field sama + input Nominal, tombol "+ Tambah Tarif".
4. **Keringanan** (opsional): list rule — pilih `kategori_keringanan` (multi-select dari master, dengan opsi tambah kategori baru inline), tipe potongan (fixed/persen), nilai, keterangan, tombol "+ Tambah Keringanan".

Tombol **"Proses Tagihan"** di halaman index jenis_tagihan memicu generator manual untuk baris tsb, menampilkan hasil ringkas (X tagihan dibuat, Y dilewati karena sudah ada, Z gagal) setelah selesai (sinkron, bukan job queue — sesuai keputusan awal MVP tanpa Redis).

## Dashboard Monitoring — "Lihat Penerima Tagihan"

Halaman per `jenis_tagihan`, 3 bagian:

**Ringkasan** (card row): total siswa penerima (`COUNT DISTINCT tagihable_id`), jumlah per status (lunas/sebagian/belum_bayar/dibatalkan), total tertagih (`SUM(net_amount) WHERE status != 'dibatalkan'`), total masuk (`SUM(paid_amount)`).

**Tab Daftar Penerima**: tabel siswa × periode × status, kolom nominal/diskon/net/paid, aksi:
- **Batalkan** — hanya aktif jika `status = 'belum_bayar'` (tidak ada pembayaran sama sekali). Set `status='dibatalkan'`, `cancelled_by`, `cancelled_at`, `cancel_reason` (wajib diisi, modal konfirmasi). Tagihan berstatus `sebagian`/`lunas`/`dicicil` tidak bisa dibatalkan dari sini (butuh alur refund yang di luar scope sub-project ini).

**Tab Daftar Tunggakan**: `GROUP BY tagihable_type, tagihable_id`, `SUM(net_amount - paid_amount) AS total_tunggakan WHERE status IN ('belum_bayar','sebagian')`, join ke nama siswa, urut descending — rekap lintas periode untuk jenis tagihan ini.

## Yang TIDAK Termasuk Sub-project 2

- Wallet, VA, integrasi BRI, transfer manual, cash (→ sub-project 3 & 4) — `paid_amount` tetap 0 sepanjang sub-project ini kecuali diisi manual untuk testing/QA.
- Notifikasi otomatis saat tagihan diterbitkan (→ sub-project 5) — cukup log di `billing_job_logs`, belum ada pengiriman WA/Email/In-App.
- Dashboard/portal orang tua (→ sub-project 6).
- Refund flow untuk tagihan yang sudah ada pembayaran (di luar scope modul secara keseluruhan untuk saat ini, dicatat sebagai ambiguitas sisa).

## Ambiguitas Terselesaikan

- [x] Event-based trigger (StudentCreated/StudentUpdatedClass/BillTypeActivated) → Masuk scope, sinkron tanpa queue
- [x] Edit jenis_tagihan retroaktif → Snapshot historis, tagihan lama tidak berubah
- [x] Recovery job gagal di tengah → Transaction per-siswa, log PARTIAL, retry otomatis oleh idempotency check hari berikutnya
- [x] Batalkan tagihan → Hanya untuk status belum_bayar, wajib alasan, audit trail lengkap

## Ambiguitas Sisa (untuk sub-project berikutnya / catatan operasional)

- [ ] Refund untuk tagihan yang sudah ada pembayaran (sebagian/lunas) tapi ternyata keliru digenerate — perlu didesain terpisah, kemungkinan di sub-project 4 (bersamaan payment channel) atau modul tersendiri
- [ ] Volume besar (mis. 1 lembaga 1000+ siswa, banyak jenis_tagihan otomatis jatuh di tanggal_generate yang sama) — apakah proses sinkron cron harian cukup cepat atau perlu batching/queue; keputusan awal "tanpa Redis untuk MVP" perlu ditinjau ulang jika data riil membuktikan lambat
