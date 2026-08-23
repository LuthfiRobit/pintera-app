# Spec: Scanner QR Kamera untuk Kehadiran SDM

**Tanggal:** 23 Agustus 2026
**Branch:** `sdm-v1`
**Modul terkait:** Kehadiran SDM (Sub-project 1 — Fondasi Kehadiran Admin & QR)

## 1. Latar Belakang & Masalah

Halaman `resources/views/admin/kehadiran-sdm/scan.blade.php` (`admin.kehadiran-sdm.scan.index`) saat ini hanya menyediakan satu cara memasukkan token QR pegawai: sebuah `<input type="text">` yang di-autofocus, didesain untuk dipakai bersama scanner barcode fisik (USB/Bluetooth) yang bertindak sebagai keyboard — perangkat semacam itu "mengetikkan" token lalu Enter, memicu submit form.

Tidak ada cara untuk memindai QR code pegawai memakai **kamera device** (HP/tablet/laptop) secara visual — padahal QR kehadiran pegawai (`resources/views/sdm/qr-saya.blade.php`) memang dirender sebagai gambar QR yang seharusnya bisa dipindai kamera. Ini yang dimaksud user dengan "scanner QR belum ada" — fitur pemindaian visual sungguhan belum dibangun, hanya jalur input teks yang mengasumsikan ada perangkat scanner fisik terpisah.

## 2. Tujuan

Menambahkan mode pemindaian QR berbasis kamera browser ke halaman scan yang sudah ada, sebagai **opsi tambahan berdampingan** dengan input manual — bukan pengganti — dengan fallback otomatis ke input manual kalau kamera tidak tersedia/gagal diakses.

## 3. Konteks Teknis yang Sudah Ada (Tidak Berubah)

- **Format token QR:** gambar QR di `qr-saya.blade.php` meng-encode string token mentah (`$qrCode->token`), bukan URL/JSON. Hasil decode kamera = string token itu sendiri, siap dipakai langsung.
- **Endpoint submit (tidak berubah sama sekali):** `POST route('admin.kehadiran-sdm.scan.store')` → `AttendanceQrScanController::store()`. Body JSON: `{ token: string, arah: 'masuk'|'pulang', attendance_point_id: int|null }`. Respons sukses: `200 { message: string }`. Respons gagal (token invalid, lembaga mismatch, hari libur): `422 { message: string }`.
- **State Alpine yang sudah ada** di `scan.blade.php` (`arah`, `attendancePointId`, `token`, `loading`, `message`, `messageType`, `scanHistory`, method `submitScan()`) — dipakai ulang persis, tidak diubah kontraknya. Mode kamera hanya perlu cara BARU mengisi `token` lalu memanggil `submitScan()` yang sudah ada.
- Halaman ini dijaga permission `kehadiran-sdm.catat` (tidak berubah).

## 4. Keputusan Desain (hasil brainstorming dengan user)

1. **Dua mode berdampingan via toggle**, bukan pengganti: "Scan Kamera" dan "Input Manual". Kalau kamera error, otomatis fallback ke Input Manual.
2. **Library:** [`html5-qrcode`](https://www.npmjs.com/package/html5-qrcode) (npm). Dipilih karena sudah menangani permintaan izin kamera, decode loop, dan pesan error kamera secara built-in, serta paling stabil lintas browser termasuk Safari/iOS — dibanding menulis manual dengan `jsQR` atau bergantung pada `BarcodeDetector` API native (belum didukung Safari).
3. **Mode default saat halaman dibuka: Kamera.** Halaman langsung mencoba mengaktifkan kamera saat dimuat. Kalau gagal (izin ditolak/tidak ada kamera/browser tak didukung/dll), otomatis pindah ke mode Input Manual dan tampilkan pesan error yang menjelaskan sebabnya. Admin tetap bisa tap toggle "Scan Kamera" kapan saja untuk mencoba lagi secara manual.
4. **Kamera:** `facingMode: 'environment'` (preferensi kamera belakang). Ini aman dipakai di laptop/PC ber-kamera tunggal (browser otomatis fallback ke kamera satu-satunya tanpa error) maupun HP/tablet multi-kamera. **Tidak ada dropdown pemilih kamera** — dijaga simpel.
5. **Setelah QR terbaca: auto-submit langsung** (bukan mengisi field lalu menunggu tap tombol) — memakai nilai `arah`/`attendancePointId` yang sedang aktif dipilih di UI, lalu memanggil `submitScan()` yang sudah ada persis seperti alur manual.
6. **Cegah submit ganda:** setelah 1 scan berhasil diproses, kamera **dijeda** (`html5-qrcode` di-pause, bukan di-stop) selama kurang lebih 2-3 detik, dengan overlay visual "Memproses..." di atas preview kamera, sebelum otomatis kembali siap memindai QR berikutnya.
7. **Field Arah (Masuk/Pulang) dan Titik Absen tetap selalu terlihat** di atas area scan/input, berlaku untuk kedua mode — tidak berubah dari struktur halaman saat ini.
8. **Tidak ada perubahan backend.** `AttendanceQrScanController`, `ScanQrAttendanceAction`, route, dan permission gate sama sekali tidak disentuh.

## 5. Arsitektur & File yang Terlibat

- **Baru:** `resources/js/qr-camera-scanner.js` — modul Alpine data component (pola sama seperti `resources/js/tom-select-pegawai.js`), membungkus instance `Html5Qrcode` dari library `html5-qrcode`. Bertanggung jawab atas: request kamera, render preview ke elemen target, loop decode, pause/resume, dan pelaporan sukses/error ke pemanggil lewat callback.
- **Modifikasi:** `resources/js/app.js` — mendaftarkan komponen Alpine baru (`Alpine.data('qrCameraScanner', qrCameraScanner)`), pola identik dengan pendaftaran `tomSelectPegawai`.
- **Modifikasi:** `resources/views/admin/kehadiran-sdm/scan.blade.php` — menambah toggle mode, container preview kamera, wiring `x-data` antara state form yang sudah ada dengan komponen `qrCameraScanner` baru.
- **Modifikasi:** `package.json` / `package-lock.json` — tambah dependency `html5-qrcode`.
- **Tidak disentuh:** semua file PHP (controller, Action, route, model, permission).

## 6. Alur Interaksi Detail

### 6a. Saat halaman dimuat (mode default: Kamera)
1. Komponen `qrCameraScanner` diinisialisasi, langsung memanggil `Html5Qrcode.start()` dengan `facingMode: 'environment'`.
2. **Berhasil:** preview video kamera tampil di container, siap memindai. Toggle menunjukkan "Scan Kamera" aktif.
3. **Gagal** (`NotAllowedError`, `NotFoundError`, exception lain dari library): tampilkan pesan error singkat (mis. "Kamera tidak dapat diakses: izin ditolak oleh browser." / "Tidak ada kamera yang terdeteksi pada perangkat ini."), toggle otomatis berpindah ke "Input Manual", fokus otomatis ke field token teks (perilaku lama tetap jalan).

### 6b. Saat QR berhasil terbaca (mode Kamera)
1. Callback sukses dari `html5-qrcode` memberi string hasil decode.
2. Isi Alpine state `token` dengan string tersebut, panggil `submitScan()` yang sudah ada (tidak diubah) — otomatis, tanpa interaksi admin.
3. Pause kamera (`Html5Qrcode.pause()`), tampilkan overlay "Memproses..." di atas preview.
4. Setelah `submitScan()` selesai (baik sukses maupun gagal) DAN jeda minimal habis (±2-3 detik total, mana yang lebih lama), `Html5Qrcode.resume()` dipanggil, overlay hilang, siap scan berikutnya.
5. Hasil (`message`/`messageType`) tetap dirender lewat card feedback yang sudah ada, dan tetap masuk ke `scanHistory` (kolom kanan) — tidak berubah dari alur manual.

### 6c. Toggle mode manual
- Admin tap "Input Manual" kapan saja (baik kamera sedang aktif normal, atau sedang dalam kondisi error) → kamera di-stop sepenuhnya (`Html5Qrcode.stop()`, melepas stream kamera dari browser — bukan sekadar disembunyikan, supaya lampu indikator kamera device benar-benar mati), tampilan berpindah ke field token teks seperti sekarang, fokus otomatis ke field tsb.
- Admin tap "Scan Kamera" dari mode manual → coba `Html5Qrcode.start()` lagi dari awal (mengulangi alur 6a).

## 7. Penanganan Error

| Kondisi | Penanganan |
|---|---|
| Izin kamera ditolak browser | Fallback otomatis ke manual + pesan error jelas |
| Tidak ada kamera terdeteksi | Fallback otomatis ke manual + pesan error jelas |
| Context bukan HTTPS/localhost (`getUserMedia` butuh secure context) | Fallback otomatis ke manual + pesan error jelas (disebutkan sebagai kemungkinan penyebab di pesan) |
| QR yang discan bukan format valid/rusak | Library `html5-qrcode` tidak akan memanggil callback sukses untuk QR yang tak terbaca — tidak ada perubahan perilaku, kamera terus mencoba |
| Token valid ter-decode tapi ditolak backend (422: token invalid/expired/lembaga mismatch/hari libur) | Pesan error dari server tetap tampil di card feedback yang sudah ada (perilaku manual yang sudah ada, tidak berubah), kamera tetap resume otomatis untuk scan berikutnya |

## 8. Testing

- **Otomatis (Pest/PHP):** tidak memungkinkan menguji perilaku kamera browser sungguhan di CI (tidak ada kamera). Test yang ada (`assertOk()` untuk render halaman `admin.kehadiran-sdm.scan.index`) harus tetap hijau — memastikan tidak ada error Blade/JS syntax yang menyebabkan halaman gagal render.
- **Manual (wajib sebelum dianggap selesai):** verifikasi langsung di browser sungguhan — minimal 1 device desktop (webcam) dan 1 device HP — mencakup: kamera aktif otomatis saat halaman dibuka, scan QR sungguhan (dari halaman `qr-saya` pegawai lain di device kedua) berhasil auto-submit dan tercatat, toggle ke manual mematikan kamera (lampu indikator device mati), toggle balik ke kamera menyalakan ulang, simulasi izin kamera ditolak menunjukkan fallback ke manual dengan pesan yang jelas.

## 9. Di Luar Cakupan (Tidak Dikerjakan di Spec Ini)

- Dropdown pemilih kamera untuk device multi-kamera.
- Perubahan cara generate gambar QR di `qr-saya.blade.php` (masih pakai layanan eksternal `api.qrserver.com` seperti sekarang — sudah di luar topik pemindaian).
- Perubahan apapun di backend/Action/route/permission.
