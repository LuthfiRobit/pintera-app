# Spec: Keuangan 06 — Parent Dashboard & Kwitansi

> Status: Disetujui — siap ke Implementation Plan (dieksekusi setelah Sub-project 5 selesai & diverifikasi). Sub-project terakhir dari Modul Keuangan Dinamis.

## Konteks & Dependensi

Bergantung pada:
- **Sub-project 1 & 2**: skema tagihan, polymorphic `tagihable`, generator tagihan otomatis dan manual, tarif berdimensi, keringanan.
- **Sub-project 3**: Wallet saldo digital, mutasi saldo, dan Auto-Allocation Engine.
- **Sub-project 4**: Payment channels (VA BRI, QRIS, transfer manual, cash).
- **Sub-project 5**: Preferensi notifikasi orang tua dan log pemberitahuan.

Sub-project ini fokus pada antarmuka pengguna (UI/UX) di portal orang tua (`/portal/keuangan/*`), fitur unduh kwitansi pembayaran PDF, dan form upload logo yayasan di panel admin.

## Tujuan Sub-project 6

Orang tua dapat:
1. Melihat dashboard ringkasan keuangan anak (saldo wallet, nomor VA, status tagihan, dan notifikasi).
2. Memilih dan membayar tagihan aktif secara fleksibel (mode auto-debit maupun manual).
3. Melakukan top-up saldo wallet digital secara mandiri.
4. Melihat riwayat transaksi pembayaran dan mengunduh kwitansi resmi berformat PDF.
5. Mengatur preferensi channel notifikasi keuangan (WhatsApp / Email).
6. Berpindah profil anak secara lancar jika memiliki lebih dari satu anak terdaftar.

## Struktur & Komponen Portal

- **Controller**: `app/Http/Controllers/Portal/Keuangan/*`.
- **Konteks Siswa Aktif (Child Switcher)**: Menggunakan `session('active_siswa_id')` dengan dropdown pemilih anak di header/sidebar portal. Default awal: anak dengan `siswa_orang_tua.is_kontak_utama = true`.
- **Kwitansi PDF**: Menggunakan `barryvdh/laravel-dompdf` (sudah terpasang di codebase).

## Halaman & Fitur Utama

### 1. Dashboard Utama Keuangan (`/portal/keuangan`)
- **Card Saldo Wallet**: Menampilkan saldo terkini, nomor VA permanen top-up, dan tombol "+ Top Up".
- **Banner Peringatan Saldo (Skip Alert)**: Muncul jika terdapat tagihan prioritas tertinggi yang terlewati saat proses auto-debit karena saldo tidak mencukupi (menampilkan nominal kekurangan dan tombol cepat top-up).
- **Notifikasi Terbaru**: Ringkasan aktivitas dan pemberitahuan tagihan/pembayaran.

### 2. Rekap Tagihan Aktif (`/portal/keuangan/tagihan`)
Daftar tagihan yang belum lunas (`status IN ('belum_bayar', 'sebagian')`):
- **Jika Auto-Debit Aktif**: Daftar tagihan terurut berdasarkan `priority_score` jenis tagihan dan tanggal `jatuh_tempo`.
- **Jika Auto-Debit Non-Aktif**: Pilihan multi-select checkbox untuk memilih beberapa tagihan sekaligus, dengan kalkulasi total nominal real-time dan tombol "Bayar Tagihan Terpilih".
- *(Tagihan yang berstatus `dibatalkan` atau `lunas` tidak ditampilkan di daftar tagihan aktif, melainkan dapat dilihat pada tab Riwayat Tagihan / Transaksi).*

### 3. Pemilihan Channel Pembayaran & Checkout
- **Virtual Account BRI**: Menampilkan nomor VA unik dengan batas waktu kadaluarsa (*countdown timer*).
- **QRIS Dinamis**: Menampilkan kode QR dinamis untuk dipindai melalui aplikasi m-banking / e-wallet.
- **Bayar dari Saldo Wallet**: Aktif jika saldo mencukupi, memproses pelunasan instan.
- **Transfer Bank Manual**: Form unggah bukti transfer, pilihan bank pengirim, dan tanggal transfer untuk verifikasi admin.

### 4. Riwayat Transaksi & Unduh Kwitansi (`/portal/keuangan/riwayat`)
- Tabel riwayat pembayaran per anak aktif dengan filter rentang tanggal dan metode pembayaran.
- Tombol **Unduh Kwitansi (PDF)** pada setiap transaksi yang berstatus `lunas`.

### 5. Template Kwitansi PDF
Kwitansi resmi dihasilkan per record `pembayaran`:
- **Header**: Kop resmi dengan nama lembaga, alamat lembaga, dan logo yayasan (`yayasan.logo`).
- **Body**: Nomor bukti kwitansi, tanggal pembayaran, identitas siswa (nama, NIS/NISN, kelas), rincian tagihan yang terbayar dari `pembayaran_tagihan`, nominal per item, total terbayar, dan metode pembayaran.
- **Footer**: Tanda tangan digital / stempel administrasi sekolah.

### 6. Pengaturan Preferensi Notifikasi (`/portal/keuangan/pengaturan`)
Form pengaturan toggle channel pemberitahuan (WhatsApp dan Email) yang tersinkronisasi dengan tabel `user_notification_preferences`.

## Fitur Admin — Upload Logo Yayasan

Halaman manajemen Yayasan pada panel admin:
- Form unggah file logo (`jpg`, `png`, `svg`) dengan preview gambar.
- File disimpan ke storage publik dan path-nya disimpan pada kolom `yayasan.logo` (kolom sudah tersedia pada skema tabel `yayasan`).

## Yang TIDAK Termasuk Sub-project 6

- Pembuatan mobile app terpisah (murni web portal responsif).
- Modifikasi logika bisnis backend Sub-project 1-5 (sub-project ini murni layer presentasi dan laporan).

## Ambiguitas Terselesaikan

- [x] Scope riwayat transaksi ? Ditampilkan per anak aktif, konsisten dengan child-switcher session.
- [x] Tagihan dibatalkan ? Tidak muncul di daftar tagihan aktif yang harus dibayar.
- [x] Kolom logo yayasan ? Menggunakan kolom `logo` yang sudah ada pada tabel `yayasan`.
