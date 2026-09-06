# Kartu Digital Siswa & Presensi via Scan (Opsi A3)

## 1. Latar Belakang

Roadmap platform sebelumnya menyebut "Notifikasi presensi & penjemputan (tap-in/tap-out)" sebagai 1 item. Setelah digali (sesi 2026-09-06), item itu dipecah jadi 3 proyek konseptual terpisah:

- **Opsi A2** — Notifikasi Presensi Akademik berbasis Jurnal KBM. **SUDAH SELESAI** dibangun & di-review penuh di branch `akademik-v2` (commit `a9cdc631`..`403960d3`), belum di-merge ke `main`.
- **Opsi A3** (spec ini) — evolusi A2: siswa scan kode QR pribadi sendiri untuk presensi, guru cuma validasi/isi manual siswa yang tidak sempat scan. Model data presensi SAMA PERSIS dengan A2.
- **Opsi B** — presensi fisik tap-in/tap-out di gerbang sekolah (kartu QR di titik absen). Proyek terpisah total, berhubungan dengan roadmap item "Kartu Pelajar Digital (QR)" versi fisik, TIDAK dikerjakan di sini.

Spec ini SEKALIGUS membangun fondasi identitas "Kartu Digital Siswa" — sebuah kode QR permanen per siswa yang jadi konsumsi pertama untuk presensi (A3), tapi dirancang cukup generik agar bisa dipakai fitur lain di masa depan (misal RFID sebagai medium kedua, atau lookup identitas di loket pembayaran) tanpa desain ulang — TANPA membangun fitur-fitur masa depan itu sekarang.

## 2. Keputusan Desain

1. **Model data presensi tidak berubah dari A2.** `Presensi` per `SesiPembelajaran` (status di-cast `StatusPresensi`, kolom `keterangan` nullable). `RecordJurnalDanPresensiAction` dan `PresensiNotificationService` (A2) **TIDAK disentuh sama sekali** oleh spec ini — scan hanya mengisi form manual yang sudah ada secara otomatis di browser, bukan jalur simpan baru.
2. **Mekanisme scan**: 1 device milik guru (HP/laptop yang sedang dipakai mengakses Jurnal KBM). Guru membuka modal kamera dari halaman Detail Sesi, mengarahkan kamera ke kode QR tiap siswa. Tidak ada pengadaan device baru.
3. **Sumber kode QR**: ditampilkan di HP siswa sendiri lewat Portal Siswa ("Kartu Digital Saya"). Dirancang supaya nanti bisa dicetak ke kartu fisik tanpa mengubah mekanisme scan-nya sama sekali — kartu fisik hanya medium cetak dari kode yang sama.
4. **Kode permanen per siswa** (bukan rotating/OTP) — konsisten dengan rencana kartu fisik yang butuh kode tetap tidak berubah-ubah.
5. **Model data digeneralisasi dari awal**: tabel `kartu_siswa` dengan kolom `tipe` (untuk sekarang cuma nilai `'qr'`; nanti `'rfid'` bisa ditambah tanpa migrasi ulang struktur) dan `kode` (unik). Satu titik resolusi tunggal: `KartuSiswa::resolveSiswa(string $kode): ?Siswa` — fitur apa pun di masa depan yang butuh identifikasi siswa dari kode fisik/digital cukup panggil method ini.
6. **RFID hanya disiapkan strukturnya, TIDAK dibangun fungsinya sekarang.** Tidak ada reader, tidak ada alur registrasi kartu RFID, tidak ada Action baru untuk RFID. Kolom `tipe` sudah cukup fleksibel untuk menampung ini nanti.
7. **Fitur "scan kartu untuk lihat VA/tagihan di loket Keuangan" TIDAK dibangun sekarang.** Arsitektur (`resolveSiswa()`) tidak menghalangi kalau nanti diminta, tapi tidak ada satu baris kode pun untuk itu di spec ini.
8. **TIDAK ADA toggle Lembaga untuk memilih mode A2 vs A3.** Sama alasan YAGNI seperti keputusan A2 (model bisnis project ini "1-instalasi-custom-per-Yayasan", belum ada infrastruktur feature-gating apa pun). Justru dalam praktiknya guru SELALU butuh kombinasi keduanya berdampingan dalam satu sesi yang sama (siswa yang tidak scan tetap diisi manual) — toggle on/off per Lembaga tidak match realita ini.
9. **Validasi keamanan scan 3 lapis**, meniru persis pola `ScanQrAttendanceAction` (SDM) yang sudah terbukti jalan produksi:
   - Kartu ditemukan & `is_active = true`.
   - Siswa pemilik kartu terdaftar di **kelas yang sama** dengan sesi yang sedang dibuka guru.
   - Siswa berada di **lembaga yang sama** dengan guru yang scan (tenant safety — project ini punya riwayat bug cross-tenant berulang, jadi ini wajib ketat).
   - Kalau salah satu gagal → tampilkan pesan error di modal, TIDAK menandai kehadiran apa pun.
10. **Render QR pakai package Composer `simplesoftwareio/simple-qrcode`** (`QrCode::size(...)->generate($kode)`, SVG di-render sepenuhnya di server) — pola yang sama seperti QRIS pembayaran di `CheckoutController.php:222`. **BUKAN** pola API eksternal (`api.qrserver.com`) yang dipakai `resources/views/sdm/qr-saya.blade.php` — pola itu mengirim kode ke pihak ketiga, tidak ideal untuk diulang di sini. **Tidak ada dependency npm baru** — `html5-qrcode` (untuk SCAN) sudah terinstall dan reusable apa adanya; `simplesoftwareio/simple-qrcode` (untuk GENERATE gambar QR) sudah terinstall di Composer.
11. **Pengelolaan admin**: 1 tab baru **"Kartu Digital"** di halaman Data Siswa yang sudah ada (`admin/siswa/{siswa}/edit`), mengikuti pola tab existing (`profil`, `orang-tua`, `keringanan`). BUKAN halaman/menu sidebar baru — kebutuhan admin di sini sempit (cuma kasus siswa lapor kartu bermasalah, minta generate ulang), tidak butuh operasi massal. Halaman generate massal / cetak kartu fisik ditunda sampai proyek kartu fisik jadi nyata.
12. **Scan bisa override entri manual.** Kalau guru sudah isi status manual untuk seorang siswa lalu siswa itu discan, status otomatis jadi Hadir (scan = sinyal kehadiran fisik lebih kuat). Field tetap editable setelahnya kalau guru perlu koreksi.
13. **Duplikat scan aman (idempoten).** Scan yang sama berulang kali cuma menandai ulang status jadi Hadir, tidak ada efek samping/writes ganda ke database (karena penandaan cuma di state form browser, bukan write langsung per-scan).
14. **Penamaan**: hindari istilah "check-in"/"tap"/gerbang di kode, nama class, dan pesan — tetap terpisah konsepnya dari Opsi B (fisik).
15. **Tidak pakai worktree**, kerja langsung di branch `akademik-v2` (melanjutkan branch yang sama dengan A2).

## 3. Arsitektur & Komponen

### 3.1 Model Data

**Migrasi baru** `create_kartu_siswa_table`:
- `id`
- `siswa_id` (FK ke `siswa`, `cascadeOnDelete`)
- `tipe` (string, default `'qr'`)
- `kode` (string, unique)
- `is_active` (boolean, default `true`)
- `timestamps()`
- Unique constraint gabungan `(siswa_id, tipe)` — 1 siswa cuma boleh punya 1 kartu aktif per tipe (tapi bisa punya kartu QR dan kartu RFID sekaligus nanti, dua tipe berbeda).

**Model** `App\Domains\Akademik\Models\KartuSiswa`:
- `siswa(): BelongsTo`
- Scope `aktif()` — filter `is_active = true`.
- Static method `resolveSiswa(string $kode): ?Siswa` — cari kartu aktif dengan `kode` itu, kembalikan relasi `siswa` kalau ada, `null` kalau tidak ditemukan/nonaktif. Ini SATU-SATUNYA titik resolusi kode→siswa, dipakai konsumen apa pun (sekarang: presensi; nanti: konsumen lain kalau ada).

### 3.2 Sisi Siswa — "Kartu Digital Saya"

**Action** `App\Domains\Akademik\Actions\KartuSiswa\GetOrCreateKartuQrSiswaAction` — pola `getOrCreate` sama seperti `PaymentService::getOrCreatePermanentVa()`: kalau siswa sudah punya kartu tipe `'qr'` aktif, kembalikan itu; kalau belum, buat baru dengan `kode` acak unik (`Str::random(32)` atau serupa).

**Controller** `App\Http\Controllers\Admin\KartuSayaController` (pola sama persis `PresensiSayaController` — `$request->user()->siswa`, `abort_unless` kalau tidak terhubung):
- `index()` — panggil `GetOrCreateKartuQrSiswaAction`, render halaman dengan kode QR (`QrCode::size(220)->generate($kartu->kode)`, di-echo langsung sebagai SVG inline di Blade, `{!! !!}` — bukan tag `<img>`).
- `generateUlang()` — nonaktifkan kartu lama, buat baru (untuk kasus kode lama disalahgunakan/bocor).

**Route** baru di `routes/admin/siswa-akademik.php`:
```
Route::get('kartu-saya', [KartuSayaController::class, 'index'])->name('kartu-saya.index');
Route::post('kartu-saya/generate-ulang', [KartuSayaController::class, 'generateUlang'])->name('kartu-saya.generate-ulang');
```

**View** `resources/views/admin/siswa-akademik/kartu-saya.blade.php` — kartu QR besar di tengah, tombol "Generate Ulang" dengan dialog konfirmasi (pola sama seperti `sdm/qr-saya.blade.php`, tapi render SVG server-side, bukan `<img src="...api eksternal...">`).

**Menu**: tambah 1 entri `MODULE_LABELS` di `routes/web.php` (`'kartu-saya' => 'Kartu Digital Saya'`) dan 1 item baru di sidebar partial untuk role `siswa`.

### 3.3 Sisi Guru — Scan di Jurnal KBM

**Action** `App\Domains\Akademik\Actions\KartuSiswa\ResolveKartuUntukPresensiAction` — terima `kode`, `sesiPembelajaranId` (untuk tahu kelas), `lembagaId` (guru yang scan). Alur:
1. `KartuSiswa::resolveSiswa($kode)` — kalau `null`, lempar `KartuTidakValidException`.
2. Cek siswa terdaftar di kelas yang sama dengan sesi — kalau tidak, lempar `KartuKelasMismatchException`.
3. Cek `siswa->lembaga_id === $lembagaId` — kalau tidak, lempar `KartuLembagaMismatchException`.
4. Kembalikan `Siswa` yang valid.

**Exceptions baru** di `App\Domains\Akademik\Exceptions\`: `KartuTidakValidException`, `KartuKelasMismatchException`, `KartuLembagaMismatchException` — masing-masing punya pesan default berbahasa Indonesia yang jelas untuk ditampilkan di modal scan.

**Endpoint baru** — method baru `resolveKartu()` di `Guru\JurnalKbmController` (bukan controller terpisah — satu domain concern, controller yang sama sudah inject dependency terkait sesi):
- `POST guru/jurnal-kbm/{sesi}/resolve-kartu` — body `{ kode }`, panggil `ResolveKartuUntukPresensiAction`, balas JSON `{ siswa_id, nama_lengkap }` (200) atau `{ message }` (422) kalau exception dilempar.

**Frontend** — di `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php`:
- Tombol baru "Scan Presensi" membuka modal.
- Modal berisi `x-data="qrCameraScanner({...})"` — REUSE `resources/js/qr-camera-scanner.js` apa adanya (component sudah generik, tidak spesifik SDM).
- `onScanSuccess` → `fetch()` ke endpoint `resolve-kartu` → kalau sukses, set state Alpine form presensi manual yang SUDAH ADA untuk `siswa_id` itu jadi `'hadir'` (reaktif, radio/select yang sama, TIDAK ada tabel/state terpisah) → tampilkan toast singkat nama siswa yang berhasil discan. Kalau gagal, tampilkan pesan error dari response tanpa mengubah state form.
- Siswa yang tidak discan tetap tampil di tabel manual yang sama seperti hari ini (A2), menunggu guru isi.
- Simpan tetap lewat tombol **"Simpan Jurnal & Presensi"** yang sudah ada — `RecordJurnalDanPresensiAction` dan `PresensiNotificationService` dipanggil identik dengan alur A2, tidak ada perubahan kode di kedua file itu.

### 3.4 Sisi Admin — Tab "Kartu Digital"

**View baru** `resources/views/admin/siswa/tabs/kartu-digital.blade.php` (ikut pola `tabs/profil.blade.php`, `tabs/orang-tua.blade.php`) — tampilkan status kartu siswa itu (ada/tidak ada, kode terpotong, tanggal dibuat), tombol "Nonaktifkan" dan "Generate Ulang".

**Modifikasi** `resources/views/admin/siswa/edit.blade.php` — tambah 1 tombol tab `kartu-digital` di header tabs, 1 `@include('admin.siswa.tabs.kartu-digital')` di content area — ikut pola persis yang sudah ada untuk 3 tab lain.

**Route** baru di `routes/admin/siswa.php` — endpoint POST untuk generate-ulang/nonaktifkan dari tab admin (memanggil Action yang sama dengan sisi siswa, `GetOrCreateKartuQrSiswaAction` untuk generate ulang, atau method baru sederhana untuk nonaktifkan).

## 4. Skenario Test

1. `KartuSiswa::resolveSiswa()` — kode valid & aktif → kembalikan Siswa yang benar; kode tidak ditemukan → `null`; kode ditemukan tapi `is_active = false` → `null`.
2. `GetOrCreateKartuQrSiswaAction` — siswa belum punya kartu → dibuat baru; siswa sudah punya kartu aktif → kembalikan yang sama (tidak duplikat, tidak generate ulang kode).
3. `ResolveKartuUntukPresensiAction` — 4 skenario: kartu valid & satu kelas & satu lembaga → sukses; kartu tidak ditemukan/nonaktif → `KartuTidakValidException`; siswa valid tapi beda kelas dari sesi → `KartuKelasMismatchException`; siswa valid tapi beda lembaga dari guru → `KartuLembagaMismatchException`.
4. Endpoint `resolve-kartu` (feature test HTTP) — request valid → 200 + data siswa benar; request dengan kode salah/kelas salah/lembaga salah → 422 + pesan sesuai.
5. `KartuSayaController::index()` — siswa pertama kali buka → kartu otomatis dibuat (get-or-create); siswa buka lagi → kartu yang sama (kode tidak berubah).
6. `KartuSayaController::generateUlang()` — kartu lama jadi `is_active = false`, kartu baru dibuat dengan kode berbeda.
7. Regresi — `JurnalKbmControllerTest` (existing, dari A2) tetap 100% lulus tanpa modifikasi apa pun ke file test itu (spec ini tidak menyentuh `RecordJurnalDanPresensiAction`).
8. Regresi — `Admin\SiswaController` edit page test (kalau ada) tetap lulus setelah tab baru ditambahkan.

## 5. Di Luar Cakupan

- **RFID fungsional** (reader fisik, alur registrasi kartu RFID per siswa, pembacaan RFID di kelas) — struktur data (`kartu_siswa.tipe`) sudah siap menampungnya, tidak ada satu baris kode fungsional RFID di spec ini.
- **Fitur lookup VA/tagihan via scan kartu di loket Keuangan** — arsitektur (`KartuSiswa::resolveSiswa()`) tidak menghalangi, tapi tidak dibangun sekarang.
- **Halaman admin generate massal / persiapan cetak kartu fisik** — ditunda sampai proyek kartu fisik (Opsi B / Kartu Pelajar Digital versi cetak) jadi nyata.
- **Toggle Lembaga untuk memilih mode A2 vs A3** — sengaja tidak dibangun (YAGNI, dikonfirmasi eksplisit).
- **Opsi B** (presensi fisik tap-in/tap-out di gerbang sekolah) — proyek terpisah total, tidak disentuh spec ini.
- **Merge branch `akademik-v2` ke `main`** — keputusan terpisah, tidak bagian dari spec ini.
