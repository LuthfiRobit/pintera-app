# Spec: Keuangan 02b3 - Dashboard Monitoring

Disadur dari dokumen utama `docs/superpowers/specs/2026-08-10-keuangan-02-billing-engine-admin-design.md` bagian Dashboard Monitoring.

## Dashboard Monitoring — "Lihat Penerima Tagihan"

Halaman per `jenis_tagihan`, terdiri dari 3 bagian utama:

**1. Ringkasan** (card row): 
- Total siswa penerima (`COUNT DISTINCT tagihable_id`)
- Jumlah per status (lunas / sebagian / belum_bayar / dibatalkan)
- Total tertagih (`SUM(net_amount) WHERE status != 'dibatalkan'`)
- Total masuk (`SUM(paid_amount)`)

**2. Tab Daftar Penerima**: 
- Tabel berisi: siswa × periode × status
- Kolom: nominal / diskon / net / paid
- Aksi **Batalkan**:
  - Hanya aktif jika `status = 'belum_bayar'` (tidak ada pembayaran sama sekali). 
  - Jika dieksekusi, set `status='dibatalkan'`, `cancelled_by`, `cancelled_at`, `cancel_reason` (wajib diisi, via modal konfirmasi). 
  - Tagihan berstatus `sebagian` / `lunas` / `dicicil` tidak bisa dibatalkan dari sini (butuh alur refund yang di luar scope sub-project ini).

**3. Tab Daftar Tunggakan**: 
- `GROUP BY tagihable_type, tagihable_id`
- Menghitung `SUM(net_amount - paid_amount) AS total_tunggakan WHERE status IN ('belum_bayar','sebagian')`
- Join ke nama siswa, urut descending — rekap lintas periode untuk jenis tagihan ini.

## Technical Scope Limits
- `paid_amount` tetap 0 sepanjang sub-project ini kecuali diisi manual untuk testing/QA.
- Tidak ada fitur notifikasi atau pembuatan kwitansi (di luar scope).
