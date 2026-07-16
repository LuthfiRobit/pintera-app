# Keuangan — Pembayaran Manual & Portal Tagihan — Design Spec

**Tanggal:** 2026-07-16
**Status:** Disetujui, siap masuk writing-plans

## 1. Latar Belakang & Tujuan

Ini adalah **sub-project 3 dari 3** rangkaian modul Keuangan (mengikuti PRD bagian 11, M6-M7 — VA BRI sengaja tidak termasuk, lihat bagian 2), lanjutan dari sub-project 1 (Portal Akun Pendaftar) dan sub-project 2 (Master Tagihan & Mesin Invoicing, keduanya sudah selesai). Sub-project ini adalah **penutup alur pendaftaran SPMB** — setelah ini, seorang calon siswa benar-benar bisa berjalan dari daftar sampai resmi jadi siswa aktif tanpa proses manual paralel, sesuai metrik sukses pilot di PRD.

Sub-project 2 sudah membangun **apa yang harus dibayar** (`tagihan`/`tagihan_item`, dibuat otomatis). Sub-project ini membangun **bagaimana cara membayarnya**: skema cicilan, upload bukti transfer, verifikasi admin, dan tempat calon siswa melihat semua ini di Portal Akun Pendaftar.

## 2. Lingkup

**Termasuk:**
- Skema cicilan (untuk tagihan yang `bisa_dicicil`) — calon siswa atau admin memilih jumlah termin, sistem membagi rata otomatis (dengan penanganan sisa pembulatan yang benar), admin bisa menyunting nominal per termin secara manual.
- Pembayaran transfer manual — calon siswa upload bukti lewat portal, admin keuangan verifikasi (terima/tolak).
- Jalur cadangan admin — pencatatan pembayaran langsung oleh admin (untuk kasus tunai/lapor langsung), tanpa perlu upload dari calon siswa.
- Menu baru "Tagihan & Pembayaran" di Portal Akun Pendaftar.
- Deteksi "siswa aktif" — dihitung murni dari data yang sudah ada (`tagihan`/`cicilan`), tanpa kolom status baru.

**Tidak termasuk (sengaja ditunda):**
- VA BRI — `pembayaran.metode` menyiapkan nilai `'va_bri'` di enum, tidak diimplementasikan (belum ada akses API resmi, sesuai keputusan PRD bagian 10.3).
- Modul Kesiswaan/Akademik penuh — status "aktif" di sub-project ini murni penanda logis (accessor), bukan konversi ke tabel `siswa` terpisah. Itu domain fase berikutnya (di luar pilot Keuangan+SPMB).
- Notifikasi otomatis (pengingat jatuh tempo, dsb) — PRD menaruhnya di M9, terpisah dari pilot ini.
- Penggajian guru — sudah berkali-kali ditegaskan di spec sebelumnya: arah uang berlawanan, tabel terpisah, tidak berbagi apapun dengan modul ini.

**Keputusan desain kunci (dari brainstorming, termasuk 3 koreksi kritikal yang mengubah rancangan awal):**
- `pendaftaran.status` **tidak disentuh sama sekali** oleh sub-project ini — tetap `menunggu_verifikasi`/`diterima`/`ditolak` selamanya begitu ditetapkan. Status "aktif" dihitung on-the-fly dari `tagihan`/`cicilan`, bukan ditulis ke kolom manapun. Ini menghilangkan risiko regresi total ke `SkPpdbController` dan tampilan status M3 yang sempat teridentifikasi saat brainstorming (lihat bagian 4).
- Pembayaran cicilan **wajib berurutan** — termin ke-N cuma bisa dibayar setelah termin ke-(N-1) berstatus lunas.
- Riwayat `pembayaran` **insert-only** — setiap percobaan bayar (termasuk yang ditolak) jadi baris baru, tidak pernah ditimpa. Ini koreksi kritikal atas rancangan awal yang salah (lihat bagian 5.3).
- Pembagian nominal cicilan pakai metode **sisa di termin terakhir**, dikunci di satu service terpusat — mencegah bug pembulatan yang membuat total cicilan tidak persis sama dengan tagihan.
- `bukti_transfer` (dari PRD) digabung langsung ke tabel `pembayaran` (hubungannya selalu 1:1 per transaksi, tidak perlu tabel terpisah).

## 3. Model Data

### 3.1 `skema_cicilan`
```
id
tagihan_id       (FK, unique — satu tagihan hanya boleh punya satu skema cicilan)
jumlah_termin    (int)
dibuat_oleh      (enum: 'calon_siswa', 'admin')
dibuat_oleh_user_id (FK users, nullable — hanya terisi kalau dibuat_oleh='admin')
timestamps
```

### 3.2 `cicilan`
```
id
skema_cicilan_id (FK)
urutan           (int, mulai dari 1)
nominal          (decimal 12,2)
jatuh_tempo      (date)
status           (enum: 'belum_bayar', 'menunggu_verifikasi', 'ditolak', 'lunas', default 'belum_bayar')
timestamps

unique (skema_cicilan_id, urutan)
```

### 3.3 `pembayaran`
```
id
tagihan_id       (FK, nullable — terisi untuk pembayaran lunas langsung tanpa cicilan)
cicilan_id       (FK, nullable — terisi untuk pembayaran satu termin cicilan)
sumber           (enum: 'calon_siswa', 'admin')
metode           (enum: 'transfer_manual', 'va_bri' — 'va_bri' disiapkan di enum, tidak pernah dipakai di sub-project ini)
file_path        (string, nullable — bukti transfer; selalu null kalau sumber='admin')
status           (enum: 'menunggu_verifikasi', 'lunas', 'ditolak')
catatan_verifikasi (text, nullable)
diverifikasi_oleh_user_id (FK users, nullable)
diverifikasi_pada (timestamp, nullable)
timestamps
```
Tepat satu di antara `tagihan_id`/`cicilan_id` harus terisi (divalidasi di level aplikasi, bukan constraint DB — mengikuti pola project ini yang lain).

**Prinsip insert-only**: baris `pembayaran` **tidak pernah di-update** setelah dibuat, KECUALI oleh satu aksi: admin memverifikasinya (mengisi `status`, `catatan_verifikasi`, `diverifikasi_oleh_user_id`, `diverifikasi_pada`). Percobaan bayar baru (termasuk setelah ditolak) selalu jadi baris baru. `file_path` dan waktu unggah baris lama tidak pernah berubah/hilang — inilah yang membuat riwayat transaksi bisa diaudit penuh.

### 3.4 Tidak ada kolom/tabel baru di `pendaftaran`

Status "siswa aktif" dihitung lewat accessor pada model `Pendaftaran`, bukan disimpan:
```php
public function isAktif(): Attribute
{
    return Attribute::make(
        get: fn () => $this->status === 'diterima' && (
            // Jalur A: tagihan daftar ulang dibayar lunas langsung, tanpa cicilan
            $this->tagihan()->where('kategori', 'daftar_ulang')->where('status', 'lunas')->exists()
            // Jalur B: dicicil, cukup termin pertama yang lunas (sisa cicilan tetap jadi tanggungan)
            || $this->tagihan()->where('kategori', 'daftar_ulang')
                ->whereHas('cicilan', fn ($q) => $q->where('urutan', 1)->where('status', 'lunas'))
                ->exists()
        )
    );
}
```
(Catatan implementasi: `cicilan` terhubung ke `tagihan` lewat `skema_cicilan`, bukan langsung — relasi `Tagihan::cicilan()` di kode nanti akan berupa `hasManyThrough` lewat `skema_cicilan`.)

## 4. Kenapa `pendaftaran.status` Tidak Disentuh

Selama brainstorming, rancangan awal sempat mengusulkan status berpindah otomatis `diterima → daftar_ulang → aktif`. Ini ditelusuri lebih jauh dan ditemukan akan **merusak fitur yang sudah berjalan**: `SkPpdbController::store()` (M3) memfilter calon dengan `whereIn('status', ['diterima', 'ditolak'])` untuk menentukan siapa yang masuk SK — begitu status berpindah ke `daftar_ulang` (yang bisa terjadi hampir seketika, karena tagihan daftar ulang dibuat di method yang sama saat keputusan ditetapkan), calon itu tidak akan pernah bisa masuk SK lagi. Tampilan badge status di halaman Verifikasi & Keputusan M3 juga akan perlu diperluas.

Solusi yang dipilih (dan jauh lebih aman): **`pendaftaran.status` tidak pernah berubah lagi setelah `diterima`/`ditolak` ditetapkan**. "Progres pembayaran/aktif" murni dihitung dari `tagihan`/`cicilan` lewat accessor `isAktif()` di atas — satu sumber kebenaran, tidak ada risiko dua tempat saling tidak sinkron, dan **nol perubahan** ke `SkPpdbController` atau kode M3 manapun.

## 5. Alur Pembayaran

### 5.1 Portal (calon siswa)
- Menu baru "Tagihan & Pembayaran" di sidebar Portal Akun Pendaftar (slot yang sudah disiapkan sejak sub-project 1).
- Tagihan yang `bisa_dicicil` dan belum ada `skema_cicilan`: tampil pilihan "Bayar Lunas" atau "Cicil" (pilih jumlah termin, maksimal `maks_cicilan`). Tagihan yang tidak bisa dicicil: cuma "Bayar Lunas".
- Memilih "Cicil N kali" langsung membuat `skema_cicilan` + N baris `cicilan` (pembagian rata + sisa di termin terakhir, lihat bagian 6.1).
- Tombol "Kirim Bukti" untuk termin/tagihan yang sedang aktif dibayar — **hanya muncul** kalau tidak ada baris `pembayaran` berstatus `menunggu_verifikasi` atau `lunas` untuk termin/tagihan itu (lihat bagian 6.3).
- "Riwayat Transaksi" menampilkan semua percobaan pembayaran (termasuk yang `ditolak`, lengkap dengan catatan penolakannya) sebagai arsip, tidak bisa dihapus/diubah.

### 5.2 Admin
- Halaman baru **"Verifikasi Pembayaran"** — antrian semua `pembayaran` berstatus `menunggu_verifikasi` lintas pendaftaran (server-side datatable, pola yang sudah mapan).
- Klik satu baris → menuju panel "Tagihan" di halaman detail pendaftaran (M3, sudah ada sejak sub-project 2, sekarang diperluas menampilkan progres per termin cicilan) — admin terima/tolak di situ.
- Jalur cadangan: dari panel yang sama, admin bisa langsung menandai satu termin/tagihan lunas tanpa menunggu upload calon siswa (`sumber='admin'`, langsung `status='lunas'`, tanpa `file_path`).
- Admin bisa membuatkan skema cicilan untuk calon siswa (bukan cuma calon siswa sendiri), dan menyunting nominal per termin secara manual — dengan validasi wajib di bagian 6.2.

## 6. Aturan Bisnis Kritis

### 6.1 Pembagian Nominal — Sisa di Termin Terakhir
Dikunci di satu service terpusat (mengikuti pola `TagihanGenerator`), tidak pernah dihitung ulang di tempat lain:
```php
$perTermin = intdiv((int) $totalTagihan, $jumlahTermin);
foreach (range(1, $jumlahTermin) as $urutan) {
    $nominal = $urutan < $jumlahTermin
        ? $perTermin
        : $totalTagihan - ($perTermin * ($jumlahTermin - 1));
    // buat baris cicilan dengan nominal ini
}
```
Menjamin jumlah seluruh termin **selalu** persis sama dengan `total_tagihan`.

### 6.2 Validasi Total Saat Sunting Manual
Endpoint penyuntingan nominal cicilan oleh admin wajib menghitung ulang jumlah seluruh termin (termasuk yang baru disunting) sebelum menyimpan. Kalau totalnya tidak sama persis dengan `tagihan.total_tagihan`, tolak dengan pesan error — tidak boleh tersimpan sebagian.

### 6.3 Urutan Cicilan Wajib
Termin ke-N hanya bisa mulai dibayar (baik oleh calon siswa maupun admin) kalau termin ke-(N-1) sudah berstatus `lunas`. Percobaan membayar termin di luar urutan ditolak di level server, bukan cuma disembunyikan di tampilan.

### 6.4 Mengisi `tagihan.jatuh_tempo`

Sub-project 2 sengaja membiarkan kolom ini selalu `null` dan menjanjikan sub-project ini yang mengisinya. Resolusinya: begitu `skema_cicilan` dibuat, `tagihan.jatuh_tempo` diisi dari `jatuh_tempo` termin **terakhir** (menandakan "batas akhir seluruh tagihan harus lunas"). Untuk tagihan yang dibayar lunas langsung tanpa cicilan, kolom ini tetap `null` — belum ada aturan bisnis "berapa hari tenggat" untuk kasus itu, dan tidak ada jadwal cicilan yang bisa dijadikan rujukan tanggal.

### 6.5 Konsistensi `tagihan.status`
Kolom ini (sudah ada sejak sub-project 2) tetap ditulis, tapi **hanya** lewat service terpusat yang sama yang mengubah `cicilan`/`pembayaran`, dalam transaksi yang sama:
- `skema_cicilan` dibuat → `tagihan.status = 'dicicil'`.
- Semua `cicilan` di skema itu `lunas`, ATAU `pembayaran` lunas-langsung (tanpa cicilan) terverifikasi → `tagihan.status = 'lunas'`.
- Penolakan pembayaran **tidak** mengubah `tagihan.status` (tetap `belum_bayar`/`dicicil` seperti sebelumnya).

## 7. Hak Akses

Permission baru di bawah modul `pembayaran`:
- `pembayaran.view` — lihat antrian & riwayat.
- `pembayaran.verifikasi` — terima/tolak bukti transfer.
- `pembayaran.catat-manual` — jalur cadangan (tandai lunas langsung tanpa verifikasi).
- `cicilan.kelola` — buatkan skema cicilan untuk calon siswa, sunting nominal manual.

`admin_keuangan` mendapat semua 4. `kepala_sekolah` tetap seperti sebelumnya (cuma `tagihan.view`, read-only) — tidak mendapat akses pembayaran.

## 8. Rencana Pengujian

- **Pembagian cicilan**: total seluruh termin (termasuk kasus sisa pembulatan ganjil) selalu persis sama dengan `total_tagihan`.
- **Riwayat insert-only**: upload → ditolak → upload ulang menghasilkan 2 baris `pembayaran`, baris pertama (`ditolak`) tidak berubah sedikit pun.
- **Urutan cicilan wajib**: membayar termin ke-2 sebelum termin ke-1 lunas ditolak di server.
- **`isAktif()` — dua jalur diuji terpisah**: lunas langsung tanpa cicilan → aktif; dicicil, baru termin 1 lunas (termin lain belum) → tetap aktif; belum ada pembayaran/masih menunggu/ditolak → tidak aktif.
- **Validasi total saat sunting manual**: total yang salah ditolak, data lama tidak berubah.
- **Jalur cadangan admin**: langsung lunas tanpa tahap menunggu, tercatat siapa yang mencatat.
- **Hak akses**: keempat permission diuji independen ke controller.
- **Isolasi tenant**: pembayaran/cicilan lembaga lain tidak terlihat/terpengaruh.
- **Regresi M2/M3/sub-project 1&2**: karena `pendaftaran.status` sengaja tidak disentuh, seharusnya nol perubahan diperlukan ke `SkPpdbController`/tampilan status M3 — tetap dijalankan penuh untuk memastikan.

## 9. Non-Tujuan / Catatan

- VA BRI: begitu akses API tersedia, tinggal menambahkan implementasi untuk `metode='va_bri'` di service pembayaran yang sama — struktur data sudah siap menampungnya.
- Modul Kesiswaan (fase berikutnya, di luar pilot ini) kemungkinan akan butuh tabel `siswa` sungguhan suatu saat — `isAktif()` accessor ini bisa jadi rujukan logika migrasi data saat itu terjadi, tapi tidak perlu diantisipasi sekarang.
