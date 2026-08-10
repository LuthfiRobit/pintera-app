# Modul Sistem Keuangan Sekolah Dinamis — Sub-project 6: Parent Dashboard & Kwitansi

> Status: Disetujui — siap ke Implementation Plan (dieksekusi setelah Sub-project 5 selesai & diverifikasi). Sub-project terakhir dari modul ini.

## Konteks & Dependensi

Sub-project ini murni UI/UX portal orang tua — menyatukan seluruh backend yang dibangun Sub-project 1-5 (skema tagihan, billing engine, wallet & auto-allocation, payment channels, notifikasi) jadi satu pengalaman. Tidak ada logic bisnis baru selain kwitansi PDF & upload logo yayasan.

## Tujuan Sub-project 6

Orang tua bisa: melihat dashboard keuangan (saldo, tagihan aktif, notifikasi, banner skip alert), memilih/membayar tagihan sesuai mode (auto/manual), melihat riwayat transaksi, download kwitansi PDF, atur preferensi notifikasi, dan switch antar anak jika punya lebih dari satu.

## Struktur & Reuse

- **Controller**: `app/Http/Controllers/Portal/Keuangan/*` — namespace baru, konsisten pola `Portal/TagihanController` (PPDB) yang sudah ada tapi untuk siswa aktif.
- **Active child context**: adaptasi pola switcher yang sudah ada (`YayasanLembagaSwitcherAuthTest` menunjukkan mekanisme switcher session serupa sudah dipakai) — `siswa_id` aktif disimpan di session, dropdown "Pilih Profil Anak" di semua halaman Portal/Keuangan. Default: siswa dengan `siswa_orang_tua.is_kontak_utama=true` untuk user tsb, atau anak pertama kalau tidak ada yang ditandai utama.
- **Kwitansi PDF**: reuse `barryvdh/laravel-dompdf` (sudah dipakai `Portal/BuktiPendaftaranController`, `Admin/SkPpdbController`) — bukan library baru.

## Halaman & Komponen

### 1. Dashboard Utama
- Card saldo wallet + `va_number` permanen + tombol "+ Top Up".
- Panel notifikasi terbaru (in-app, dari Sub-project 5, badge lonceng existing kalau ada di header).
- **Banner skip alert**: tampil jika ada tagihan priority tertinggi ter-skip (query dari Auto-Allocation Engine, Sub-project 3) — "Saldo tidak cukup untuk {jenis_tagihan} (Rp{total}), kekurangan Rp{selisih}" + tombol "Top-up Rp{selisih} Sekarang" (nominal prefill ke form top-up).
- Dropdown "Pilih Profil Anak" (hanya tampil jika user punya >1 anak terhubung).

### 2. Rekap Tagihan Aktif
Tampilan bercabang berdasarkan `system_settings.auto_debit_enabled` resolved untuk lembaga siswa aktif:
- **Mode ON**: daftar tagihan urut `priority_score`, status masing-masing, tanpa checkbox (sistem yang atur otomatis via top-up).
- **Mode OFF**: checkbox multi-select per tagihan + total nominal terpilih (live-update) + tombol "Bayar Tagihan Dipilih" (disabled jika tidak ada yang dipilih).
- Tombol "Riwayat Transaksi" & "Download Kwitansi" (index kwitansi, bukan generate baru).

### 3. Pilih Metode Pembayaran
Muncul setelah "Bayar Tagihan Dipilih" (atau dari tombol top-up terpisah untuk isi saldo tanpa pilih tagihan dulu):
- **BRI Virtual Account**: generate on-demand (Sub-project 4), tampilkan nomor VA + countdown expire (dari `expired_at`), auto-refresh status (polling frontend ringan atau instruksi refresh manual — detail teknis di implementation plan).
- **QRIS**: generate on-demand, tampilkan QR code + countdown.
- **Bayar dari Saldo Wallet**: aktif hanya jika `wallet.balance >= total`, langsung proses tanpa redirect (Sub-project 3).
- **Transfer Manual**: form upload bukti + pilih bank asal + tanggal transfer → masuk antrean (Sub-project 4), status "Menunggu Verifikasi" ditampilkan setelah submit.
- Info non-aktif: "Tunai — bayar langsung di loket sekolah, admin akan input pelunasan."

### 4. Riwayat Transaksi
Per-anak-aktif (konsisten dengan dashboard/tagihan, bukan gabungan lintas anak) — tabel `pembayaran` (join `pembayaran_tagihan`→`tagihan`, dan `wallet_mutasi` untuk top-up murni) milik siswa aktif, filter tanggal/status, tombol download kwitansi per baris.

### 5. Kwitansi PDF
1 kwitansi per `pembayaran` (bukan per tagihan) — header pakai nama+alamat `lembaga` siswa + `yayasan.logo`, badan berisi daftar `tagihan` yang dialokasikan (dari `pembayaran_tagihan`) dengan nominal masing-masing, total, metode bayar, `reference_code`/nomor unik, tanggal.

### 6. Pengaturan Notifikasi
Toggle WA/Email untuk modul Keuangan (`user_notification_preferences`, Sub-project 5) — ditambahkan ke halaman profil/pengaturan akun orang tua yang sudah ada (lokasi persis dicek saat implementation plan; kalau belum ada halaman pengaturan portal sama sekali, dibuat minimal di sub-project ini).

## Admin — Upload Logo Yayasan (bagian dari sub-project ini, dipakai kwitansi)

Halaman admin baru (belum ada controller untuk ini sebelumnya): form upload image di halaman edit Yayasan, validasi tipe (`jpg,png,svg`) & ukuran maks, simpan ke storage + update `yayasan.logo`. Preview logo current di form.

## Yang TIDAK Termasuk Sub-project 6

- Logic bisnis baru (semua backend dari Sub-project 1-5 dipakai apa adanya, sub-project ini murni presentasi).
- Aplikasi mobile/PWA (di luar scope modul secara keseluruhan).

## Ambiguitas Terselesaikan

- [x] Riwayat transaksi multi-anak → Per-anak-aktif, konsisten dengan halaman lain (bukan gabungan)

## Ambiguitas Sisa (untuk implementation plan)

- [ ] Lokasi tepat halaman "Pengaturan Akun" portal existing (kalau belum ada, perlu diputuskan dibuat baru vs bagian dari halaman profil)
- [ ] Mekanisme refresh status VA/QRIS di frontend (polling interval vs manual refresh vs WebSocket) — detail teknis, tidak memengaruhi skema data, diputuskan saat implementation plan
