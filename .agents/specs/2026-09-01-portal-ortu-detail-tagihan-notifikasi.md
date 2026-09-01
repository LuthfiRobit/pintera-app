# Spec: Halaman Detail Tagihan & Deep-Link Notifikasi di Portal Orang Tua

**Tanggal**: 2026-09-01
**Branch**: `keuangan-v2`
**Konteks**: Lanjutan dari paket "Konsolidasi Jenis Tagihan & Engine Recalculate" (`.agents/specs/2026-09-01-jenis-tagihan-konsolidasi-sasaran-tarif-keringanan.md`). Setelah risiko finansial di portal orang tua ditutup (tagihan `perlu_ditinjau_ulang` tidak bisa dibayar), tersisa 2 gap transparansi UX yang disepakati untuk dikerjakan bersama karena saling bergantung.

## 1. Latar Belakang

Analisa terhadap portal `/keuangan` (self-service orang tua) menemukan:
1. Orang tua tidak pernah melihat breakdown nominal (tarif vs potongan) — cuma angka akhir. Tidak ada halaman detail per-tagihan sama sekali, hanya list.
2. Notifikasi finance (`TagihanDirevisiNotification`, `TagihanDiterbitkanNotification`, dst) sudah membawa `tagihan_id` di payload `toDatabase()`, tapi bell notifikasi (`resources/views/layouts/topbar.blade.php`) tidak pernah memakainya untuk membuat link — notifikasi cuma tampil sebagai teks, tenggelam di antara notifikasi lain.

## 2. Non-Goals

- Riwayat revisi historis (before/after dari waktu ke waktu) — Activitylog trail tetap admin-only. Halaman detail ini hanya menampilkan komposisi SAAT INI.
- Alasan teknis `alasan_perlu_ditinjau` tetap tidak pernah diekspos ke orang tua (pola yang sudah dikunci di paket sebelumnya).
- Tidak menyentuh notifikasi transfer manual (`TransferManualDisetujuiNotification`/`TransferManualDitolakNotification`) — keduanya tidak membawa `tagihan_id` dan tidak terkait ke satu tagihan spesifik.
- Tidak ada perubahan pada logic pembayaran/nominal apapun — murni tampilan baca.

## 3. Desain

### 3.1 Halaman Detail Tagihan (baru)

- Route: `GET keuangan/tagihan/{tagihan}` → nama `keuangan.tagihan.show`, didaftarkan di grup route `/keuangan` yang sudah ada (`routes/web.php`), setelah `tagihan.index`.
- Controller: method baru `TagihanController::show(Tagihan $tagihan)` di `app/Http/Controllers/Portal/Keuangan/TagihanController.php`.
- **Guard kepemilikan** (wajib, bukan cuma siswa aktif): tagihan harus `tagihable_type === Siswa::class` DAN `tagihable_id` adalah salah satu siswa milik orang tua yang login (bukan cuma `activeSiswa` yang sedang dipilih di session) — supaya link dari notifikasi lama tetap valid meski orang tua sudah beralih ke anak lain. Diimplementasikan sebagai trait baru `App\Domains\Keuangan\Concerns\AuthorizesTagihanAccess`, meniru persis pola `AuthorizesPembayaran` yang sudah ada:
  ```php
  trait AuthorizesTagihanAccess
  {
      private function authorizeTagihanAccess(Tagihan $tagihan): void
      {
          $orangTua = Auth::user()->orangTua;
          $ownsChild = $orangTua !== null
              && $tagihan->tagihable_type === Siswa::class
              && $orangTua->siswa()->withoutGlobalScope(TenantScope::class)->whereKey($tagihan->tagihable_id)->exists();

          abort_unless($ownsChild, 403);
      }
  }
  ```
- **Isi halaman** (view baru `resources/views/portals/portal/keuangan/tagihan/show.blade.php`):
  - Nama Jenis Tagihan, jatuh tempo, status.
  - Breakdown 3 baris terpisah: **Nominal Awal** (`total_tagihan`), **Potongan** (`discount_amount`, dengan label jenis potongan manusiawi: `fixed` → "Potongan Tetap", `persen` → "Potongan Persentase", `gabungan` → "Potongan Gabungan", tampilkan baris ini HANYA jika `discount_amount > 0`), **Nominal Akhir** (`net_amount`).
  - Sudah Dibayar (`paid_amount`) & Sisa (`net_amount - paid_amount`).
  - Kalau `perlu_ditinjau_ulang === true`: banner sama persis dengan yang sudah ada di list/dashboard ("Nominal sedang ditinjau ulang oleh admin, sementara belum bisa dibayar.") — reuse copy yang sudah dikunci, jangan buat kalimat baru.
  - Tombol "Kembali ke Daftar Tagihan" ke `keuangan.tagihan.index`.
- Baris tagihan di `tagihan/index.blade.php` dan `dashboard.blade.php` menjadi link/clickable menuju halaman ini (kecuali kolom checkbox-nya sendiri, yang tetap untuk seleksi pembayaran).

### 3.2 Deep-Link di Bell Notifikasi

- File: `resources/views/layouts/topbar.blade.php`, bagian item notifikasi (di dalam `@foreach ($notificationFeed as $notification)`).
- Tambahkan link "Lihat Detail →" di bawah teks pesan, HANYA muncul kalau:
  1. `$notification->data['tagihan_id']` ada, DAN
  2. User yang login punya profil OrangTua (`Auth::user()->orangTua !== null`) — bell ini dipakai bersama oleh admin & orang tua, link ke `keuangan.tagihan.show` akan 403 kalau diklik admin, jadi harus digerbangi supaya tidak muncul sebagai link mati untuk mereka.
- Klik link tetap memicu `tandaiSatu(id)` (mark-as-read) sebelum navigasi — pola yang sudah ada dipertahankan, cuma menambahkan `href` pada elemen yang sudah ada mekanisme klik-nya.
- Tidak ada perubahan pada notifikasi transfer manual (tidak membawa `tagihan_id`, link section otomatis tidak muncul untuk keduanya lewat kondisi #1 di atas).

## 4. Urutan Implementasi

1. §3.1 (halaman detail) — mandiri, tidak bergantung pada §3.2.
2. §3.2 (deep-link notifikasi) — bergantung pada §3.1 karena butuh route `keuangan.tagihan.show` sudah ada sebagai tujuan link.

## 5. Test Requirements

- Guard kepemilikan: orang tua bisa akses detail tagihan MILIK SALAH SATU anaknya meski bukan `activeSiswa` yang sedang dipilih; orang tua LAIN yang bukan pemilik mendapat 403.
- Breakdown menampilkan `total_tagihan`, `discount_amount` (kalau ada), `net_amount` dengan benar; baris potongan tidak muncul kalau `discount_amount === 0`.
- Banner "sedang ditinjau" muncul di halaman detail untuk tagihan yang `perlu_ditinjau_ulang = true`, tanpa membocorkan `alasan_perlu_ditinjau`.
- Link "Lihat Detail" di bell HANYA muncul untuk notifikasi yang punya `tagihan_id` DAN user yang login adalah orang tua (bukan admin/user tanpa profil OrangTua).
- Link mengarah ke URL yang benar (`route('keuangan.tagihan.show', $tagihanId)`).
