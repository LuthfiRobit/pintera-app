# Handoff Log — Kartu Digital Siswa & Presensi Siswa via Scan QR (Opsi A3)

**Tanggal**: 6 September 2026  
**Branch**: `akademik-v2`  
**Plan**: `.agents/plans/2026-09-06-kartu-digital-siswa-presensi-scan.md`  
**Spec**: `.agents/specs/2026-09-06-kartu-digital-siswa-presensi-scan.md`  
**Base commit**: `403960d3` (penutup Opsi A2)  

---

## Ringkasan Fitur

Opsi A3 mengimplementasikan fondasi identitas **Kartu Digital Siswa** (kode QR unik dan permanen per siswa) sekaligus alur presensi kelas berbasis scan QR di Jurnal KBM Guru:

1. **Fondasi Kartu Digital (`KartuSiswa`)**:
   - Tabel `kartu_siswa` dengan kolom `tipe` native DB enum (`'qr'`), kolom `kode` (UUID v4 unik), status `is_active`, dan unique constraint `[siswa_id, tipe]`.
   - Resolusi generik multi-domain via `KartuSiswa::resolveSiswa(string $kode): ?Siswa`.
   - Kode bersifat permanen per siswa; aksi "Generate Ulang" mengupdate kolom `kode` pada baris yang sama tanpa melanggar unique constraint `(siswa_id, tipe)`.
2. **Presensi Siswa via Scan QR (Jurnal KBM Guru)**:
   - Tombol "Scan Presensi" membuka modal scanner berbasis kamera (`html5-qrcode`) dengan opsi input manual barcode scanner USB/keyboard.
   - Endpoint AJAX `POST guru/jurnal-kbm/{sesi}/resolve-kartu` dengan validasi 3 lapis bertingkat.
   - Event bus Alpine.js (`$dispatch('presensi-scanned', ...)` & `@presensi-scanned.window`) yang secara instan menandai status baris siswa menjadi **Hadir** tanpa merusak state per-baris tabel presensi manual dan input keterangan izin/sakit.
   - Guru tetap memiliki kontrol penuh untuk meng-override atau mengedit status siswa sebelum submit.
   - **Zero-touch A2**: `RecordJurnalDanPresensiAction`, `PresensiNotificationService`, dan `PresensiPengecualianNotification` sama sekali tidak disentuh.
3. **Portal Mandiri Siswa ("Kartu Digital Saya")**:
   - Halaman `admin.kartu-saya.index` menampilkan kartu digital siswa berdesain modern (gradien indigo-violet, badge status, foto profil/inisial, NISN, kelas, lembaga).
   - Render kode QR secara native server-side SVG (`simplesoftwareio/simple-qrcode`) tanpa ketergantungan API pihak ketiga eksternal.
   - Fitur "Generate Ulang" mandiri untuk siswa dengan konfirmasi modal.
   - Menu baru ber-icon `qr-code` di sidebar "Ruang Siswa".
4. **Administrasi Admin ("Tab Kartu Digital")**:
   - Tab baru "Kartu Digital" di halaman Data Siswa (`admin.siswa.edit`).
   - Menampilkan status aktif, prefix kode, tanggal pembuatan, serta tombol aksi "Generate Ulang" dan "Nonaktifkan".

---

## Yang Dibangun per Task (Task 1–7)

### Task 1 — Migrasi & Model `KartuSiswa` (`c54a7f61`)
- Migrasi: `database/migrations/2026_09_06_000001_create_kartu_siswa_table.php` dengan kolom `siswa_id`, `tipe` (enum 'qr'), `kode` (unique), `is_active`, `metadata` (nullable json), unique compound `[siswa_id, tipe]`.
- Model: `app/Domains/Akademik/Models/KartuSiswa.php` dengan casts, relasi `siswa()` (`withoutGlobalScope(TenantScope::class)`), query scope `scopeAktif()`, dan static method `resolveSiswa(string $kode): ?Siswa`.
- Unit Tests: `tests/Unit/Domains/Akademik/KartuSiswaTest.php` (3 passed).

### Task 2 — Exception Hierarchy Validasi Kartu (`48099184`)
- Base Exception: `app/Domains/Akademik/Exceptions/KartuValidasiException.php`
- Subclasses:
  - `KartuTidakValidException.php` ("Kartu tidak ditemukan atau sudah tidak aktif.")
  - `KartuKelasMismatchException.php` ("Siswa bukan anggota kelas sesi pembelajaran ini.")
  - `KartuLembagaMismatchException.php` ("Siswa bukan dari lembaga yang sama.")

### Task 3 — `ResolveKartuUntukPresensiAction` (`17136c26`)
- Action: `app/Domains/Akademik/Actions/KartuSiswa/ResolveKartuUntukPresensiAction.php`
- Validasi 3 lapis urut:
  1. `KartuSiswa::resolveSiswa($kode)` !== null (Kartu aktif & terdaftar).
  2. `(int) $siswa->kelas_id === (int) $sesi->kelas_id` (Siswa satu kelas).
  3. `(int) $siswa->lembaga_id === $lembagaId` (Siswa satu lembaga dengan guru).
- Unit Tests: `tests/Unit/Domains/Akademik/ResolveKartuUntukPresensiActionTest.php` (4 passed).

### Task 4 — Sisi Siswa: "Kartu Digital Saya" (`4ef380ab`)
- Actions:
  - `app/Domains/Akademik/Actions/KartuSiswa/GetOrCreateKartuQrSiswaAction.php`
  - `app/Domains/Akademik/Actions/KartuSiswa/GenerateUlangKartuQrSiswaAction.php`
- Controller: `app/Http/Controllers/Admin/KartuSayaController.php` (`index`, `generateUlang`)
- View: `resources/views/admin/siswa-akademik/kartu-saya.blade.php` (kartu identitas siswa + QR SVG server-side)
- Route: `routes/admin/siswa-akademik.php` (`admin.kartu-saya.*`)
- Sidebar: `resources/views/layouts/sidebar.blade.php` (menu baru "Kartu Digital Saya" under Ruang Siswa)
- Feature Tests: `tests/Feature/Akademik/KartuSayaControllerTest.php` (3 passed).

### Task 5 — Sisi Guru: Endpoint AJAX `resolve-kartu` (`b56669ce`)
- Endpoint: `POST guru/jurnal-kbm/{sesi}/resolve-kartu` di `app/Http/Controllers/Guru/JurnalKbmController.php`
- Form Request: `app/Http/Requests/Guru/ResolveKartuPresensiRequest.php`
- Response standard JSON:
  - 200 OK: `{ success: true, siswa: { id, nama, nis, nisn } }`
  - 422 Unprocessable: `{ success: false, message: ... }`
- Feature Tests: `tests/Feature/Guru/JurnalKbmResolveKartuTest.php` (4 passed).
- Regresi Controller: `tests/Feature/Guru/JurnalKbmControllerTest.php` (11 passed).

### Task 6 — Sisi Guru Frontend: Tombol & Modal Scanner Presensi (`f69efac6`)
- View: `resources/views/portals/guru/akademik/jurnal-kbm/show.blade.php`
  - Tombol "Scan Presensi" dengan icon kamera di header tabel presensi.
  - Modal Alpine.js dengan container kamera `#presensi-qr-reader`, fallback input teks manual barcode, feedback alert berhasil/gagal, dan synthesized Web Audio beep.
  - Listener event bus Alpine pada baris siswa: `@presensi-scanned.window="if ($event.detail.siswaId === {{ $siswa->id }}) { status = 'hadir'; }"`.
- Asset Compilation: `npm.cmd run build` via Vite (manifest updated).
- Tests: `JurnalKbmControllerTest.php` (12 passed).
- Manual Browser Verification: Sukses penuh (lihat rincian di bawah).

### Task 7 — Sisi Admin: Tab Kartu Digital di Data Siswa (`87c564ae`)
- Action: `app/Domains/Akademik/Actions/KartuSiswa/NonaktifkanKartuQrSiswaAction.php`
- Relasi Model: `kartuSiswa()` di `app/Models/Siswa.php`.
- View Tab: `resources/views/admin/siswa/tabs/kartu-digital.blade.php`.
- View Edit Siswa: `resources/views/admin/siswa/edit.blade.php` (tombol tab + include).
- Icon Component: Case `qr_code` di `resources/views/components/icon.blade.php`.
- Controller: Method `generateUlangKartu()` & `nonaktifkanKartu()` di `Admin\SiswaController.php`.
- Routes: `routes/admin/siswa.php` (`admin.siswa.kartu-digital.*`).
- Feature Tests: `tests/Feature/Admin/SiswaKartuDigitalTabTest.php` (3 passed).
- Regresi Siswa Admin: `tests/Feature/Admin --filter=Siswa` (104 passed).

---

## Hasil Verifikasi Manual Browser (Task 6 — WAJIB)

Verifikasi manual dilakukan di browser sungguhan pada dev server lokal `http://127.0.0.1:8000`:
- **Akun Guru**: Hendra Gunawan (`hendra.gunawan@demo.test`), SD Permata.
- **Sesi KBM**: Sesi Pembelajaran ID 1 (Kelas 1 SD Permata).
- **Akun Siswa Sah**: Ahmad Dahlan (Siswa ID 2, Kelas 1, Kartu UUID `f0fe544c-c1a7-47b2-a44d-587ff78e12f6`).

### Hasil Pengujian Fungsional:
1. **Render & Modal**:
   - Tombol "Scan Presensi" tampil dengan warna indigo elegan di atas tabel presensi.
   - Klik tombol membuka modal scanner dengan judul "Scan Kartu Digital Siswa" dan container `#presensi-qr-reader`.
   - Tombol tutup (×) dan klik di luar backdrop menutup modal secara mulus.
   - *Bukti*: `scan_modal_open_1788673209712.png`.
2. **Skenario Gagal (Kartu Salah)**:
   - Memasukkan kode acak `INVALID_KODE_TEST` -> respon error 422 tertangkap rapi.
   - Alert amber muncul dengan pesan tepat: *"Kartu tidak ditemukan atau sudah tidak aktif."*
   - Audio error tone berbunyi dan UI tidak mengalami crash.
   - *Bukti*: `scan_kode_salah_result_1788673260959.png`.
3. **Skenario Berhasil & Sinkronisasi Baris**:
   - Kode sah siswa dikirim -> mengembalikan HTTP 200 `{ success: true, siswa: { id: 2, nama: "Ahmad Dahlan", ... } }`.
   - Event bus Alpine mentrigger baris Ahmad Dahlan: radio button otomatis terpilih ke **Hadir**.
   - Input barcode kembali autofocus siap untuk scan berikutnya.
   - *Bukti*: `check_console_eval_result_1788673294173.png`.
4. **Interaktivitas Manual (Tidak Terkunci)**:
   - Pilihan radio button Hadir/Izin/Sakit/Alpa/Terlambat tetap interaktif dan dapat diklik ulang secara manual oleh guru.
   - Kolom catatan keterangan izin/sakit tetap berfungsi normal.
5. **Submit & Persistensi**:
   - Form Jurnal dan Presensi di-submit -> tersimpan ke database tanpa error.
   - Redirect ke index Jurnal KBM dengan flash alert *"Jurnal dan presensi berhasil disimpan."*
   - *Bukti*: `jurnal_presensi_saved_1788673465224.png`.

---

## Keputusan Kritis & Penyesuaian Arsitektural

1. **Isolasi Penuh Fitur A2**:
   - `RecordJurnalDanPresensiAction`, `PresensiNotificationService`, dan `PresensiPengecualianNotification` tidak mengalami perubahan 1 karakter pun (`git diff` membuktikan 0 perubahan).
2. **TenantScope pada Relasi `KartuSiswa::siswa()`**:
   - Model `Siswa` menggunakan trait `BelongsToTenant`. Agar query validasi kelas dan lembaga pada layer 2 dan 3 di `ResolveKartuUntukPresensiAction` tidak menghasilkan `null` palsu (yang akan salah melempar Layer 1 `KartuTidakValidException`), relasi `siswa()` pada `KartuSiswa` secara eksplisit ditambahkan `->withoutGlobalScope(TenantScope::class)`.
3. **Pencegahan Redeklarasi Helper Test**:
   - Helper `siapkanGuruDenganJadwalHariIni()` dibungkus dalam guard `if (! function_exists(...))` agar tidak terjadi fatal error saat dieksekusi bersamaan dalam test suite skala besar.
4. **Komponen Ikon `qr_code`**:
   - Menambahkan SVG path `qr_code` ke dalam `resources/views/components/icon.blade.php` sehingga tombol tab admin dan menu sidebar memiliki representasi visual yang tajam dan seragam.

---

## Full Test Suite & Pint (Task 8)

- **Deadlock Check**: Bersih (tidak ada proses `php.exe` konkuren).
- **Full Test Suite (`php artisan test --compact`)**:
  - **2,872 passed** (7,801 assertions)
  - **4 failed** (seluruhnya merupakan pre-existing seeder tests yang bergantung pada hari kalender: `M3DemoDataSeederTest`, `PresensiSeederTest`, `SesiPembelajaranSeederTest`).
  - **0 new regressions**.
- **Laravel Pint**:
  - `vendor/bin/pint --dirty --format agent` → Lulus tanpa peringatan format.

---

## Daftar Commit

| Task | Commit Hash | Ringkasan Commit |
|---|---|---|
| Task 1 | `c54a7f61` | `feat(akademik): tabel kartu_siswa & model KartuSiswa -- identitas generik digital siswa` |
| Task 2 | `48099184` | `feat(akademik): exception hierarchy validasi kartu siswa -- KartuValidasiException` |
| Task 3 | `17136c26` | `feat(akademik): ResolveKartuUntukPresensiAction -- validasi 3 lapis (kartu, kelas, lembaga)` |
| Task 4 | `4ef380ab` | `feat(akademik): halaman Kartu Digital Saya di Ruang Siswa -- SVG QR & generate ulang` |
| Task 5 | `b56669ce` | `feat(akademik): endpoint resolve-kartu presensi di Jurnal KBM Guru` |
| Task 6 | `f69efac6` | `feat(akademik): modal & tombol scan presensi di Jurnal KBM Guru` |
| Task 7 | `87c564ae` | `feat(akademik): tab Kartu Digital di halaman Data Siswa admin -- generate ulang & nonaktifkan` |
| Task 8 | *(commit ini)* | `docs(akademik): handoff log & update roadmap -- kartu digital siswa & presensi via scan selesai` |

---

## Hal yang Perlu Direview Manusia / Tim

1. **Branch Git**: Seluruh pengerjaan berada di branch `akademik-v2`. Sesuai instruksi kickoff, branch ini **TIDAK di-merge ke `main`** secara otomatis dan menunggu keputusan merge manual dari user/maintainer.
2. **Kamera Fisik di Lingkungan Produksi**: Fitur scan QR menggunakan `html5-qrcode` yang membutuhkan konteks aman (HTTPS) di lingkungan produksi atau `localhost` di lingkungan dev agar browser memberikan izin akses webcam/kamera perangkat.
