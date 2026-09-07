# Handoff Log: Perbaikan Visibilitas Menu & Keamanan Scope Yayasan/Lembaga

> **Dokumen Terkait**:
> - Spec: [`.agents/specs/2026-09-07-scope-yayasan-lembaga-menu-fix.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-07-scope-yayasan-lembaga-menu-fix.md)
> - Implementation Plan: [`.agents/plans/2026-09-07-scope-yayasan-lembaga-menu-fix.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-07-scope-yayasan-lembaga-menu-fix.md)
> - Tanggal Selesai: 7 September 2026
> - Branch: `rbac-v2` (belum di-merge ke `main`)
> - Base commit sebelum plan mulai: `8b949349`

---

## 1. Apa yang Dikerjakan

Menuntaskan 7 task dari plan "Perbaikan Visibilitas Menu & Keamanan Scope Yayasan/Lembaga", mencakup 4 kategori (A: bug keamanan kritis bocor data lintas-yayasan, B: gerbang identitas menu Ruang Guru, C: guard menu Scan QR, D: agregat kartu ringkasan & dropdown saat yayasan mode "Semua Lembaga").

### Ringkasan Per Task & Commit Hash:

1. **Task 1: Kategori A — Tutup Bocor Data Lintas-Yayasan (3 controller)**
   - `85c7ea3d` — perbaiki test existing `KasusAksesLogViewTest` (set `yayasan_id` eksplisit) sebelum fix, mencegah regresi false-negative.
   - `6c6d58f2` — `KasusAksesLogController::index()` dibatasi ke yayasan aktor sendiri untuk viewer scope yayasan.
   - `04c213f9` — `KasusTerhapusController::index()` dibatasi serupa.
   - `c938c205` — `OrangTuaController::index()` dibatasi serupa.
   - `29c4ca2f` — housekeeping Pint (`blank_line_after_opening_tag` pre-existing) di `KasusTerhapusViewTest.php`.
   - Catatan implementer: 2 dari 3 test brief ("does not leak...") ternyata vacuous-pass di kode lama karena accessor `nama_lengkap` di-mask oleh `YayasanScope` pada relasi `person` yang lazy-load — diperkuat dengan assersi tambahan (`assertViewHas('totalAkses'/'totalTerhapus', 1)`) yang benar-benar red→green terhadap bug controller (bukan sekadar masking view). Tidak mengubah scope task, hanya menambah assersi.

2. **Task 2: Kategori B — Gerbang Identitas Menu Ruang Guru** (`cd2e1aa4`)
   - 5 menu sidebar Ruang Guru (Jurnal & Presensi, Rekap Kehadiran, Komponen Penilaian, Asesmen & Nilai, Rapor Wali Kelas) sekarang wajib `hasRole('guru')`, bukan hanya permission mentah.
   - Test: `SidebarPengelompokanTest.php` — 10 passed (53 assertions).

3. **Task 3: Kategori C — Sembunyikan + Guard "Scan QR" saat Mode "Semua Lembaga"**
   - `6a429216` — guard server-side 422 di `AttendanceQrScanController::index()` saat yayasan belum pilih lembaga aktif.
   - `3f47aca4` — sembunyikan menu sidebar "Scan QR" pada kondisi yang sama.
   - Test: `AttendanceQrScanViewTest.php` + `SidebarPengelompokanTest.php` — 15 passed (64 assertions).

4. **Task 4: Kategori D — Virtual Account & Manual Payment (Keuangan)**
   - `89f47b90` — `VirtualAccountController::index()`: 5 query (`query`, `totalVa`, `totalSaldo`, `totalBelumVa`, `kelasList`) dibungkus `when($lembagaId !== null, ...)` agar agregat benar lintas lembaga saat mode "Semua Lembaga".
   - `17a66daa` — `ManualPaymentController::index()`: 3 lokasi query (`query`, `totalMenunggu`, `totalNominalMenunggu`) diperbaiki serupa.
   - Test: 25 passed (VirtualAccount) + 20 passed (ManualPayment, 4 file) — 45 total, 0 regresi.
   - Deviasi kecil dari brief: fixture test `requested_by` diganti dari id `Siswa` ke id `User` (FK constraint), tidak mengubah logika/assersi yang diuji.

5. **Task 5: `AttendanceConfigurationController` — Agregat Yayasan "Semua Lembaga"** (`be2d978a`)
   - 5 query (`konfigurasi`, `kalenderEntriList`, `policyList`, `jenisShiftList`, `kuotaCutiList`) diubah dari filter OR wajib menjadi `when($lembagaId !== null, ...)` — saat mode "Semua Lembaga", seluruh baris nasional+lembaga milik yayasan tampil tanpa syarat tambahan.
   - Test: 11 passed (21 assertions), 0 regresi pada `AttendanceConfigurationKalenderControllerTest`.

6. **Task 6: Kategori D — Kartu Ringkasan Statistik Sarpras & Pengadaan (4 controller)**
   - `9172b6b0` — `GedungController`.
   - `c11d36fb` — `RuanganController` (closure fallback aslinya di-reuse, termasuk cabang `is_shared` lintas lembaga + `withoutGlobalScope(TenantScope::class)` konsisten pada query list dan 4 query stats — bukan pola generic `$statsFilter` sederhana seperti 3 controller lain).
   - `3cb229c2` — `KategoriAsetController`.
   - `071a0a5b` — `PengajuanPengadaanController`.
   - Test gabungan: 32 passed (148 assertions) di `tests/Feature/Sarpras` + `tests/Feature/Pengadaan`, 0 regresi (termasuk logic `is_shared` lama tetap aman).

7. **Task 7: Kategori D.5 — Dropdown `RaporController` + Penutup** (`68915faf`)
   - `TahunAjaranList` di `RaporController::index()` diberi eager-load `with('lembaga')`.
   - View `rapor/index.blade.php` menambahkan label ` — {Nama Lembaga}` pada opsi dropdown Tahun Ajaran, HANYA ketika `Auth::user()->widestScopeLevel() === 'yayasan'` DAN `session('active_lembaga_id')` kosong (mode "Semua Lembaga" tanpa lembaga aktif dipilih). Untuk lembaga-scope atau yayasan yang sudah memilih lembaga aktif, tampilan tidak berubah.
   - Test baru: 2 test ditambahkan ke `RaporControllerTest.php` (label muncul utk yayasan tanpa lembaga aktif; label TIDAK muncul utk viewer lembaga-scope). Total file: 21 passed (42 assertions).
   - Catatan implementasi kecil (bukan deviasi brief): label awalnya dirender lintas-baris (`{{ nama }}\n@if...\n— {{ lembaga }}\n@endif`) sehingga whitespace/newline membuat `assertSee('2026/2027 — SDIT Lembaga X')` gagal walau data benar. Diperbaiki dengan merapatkan seluruh ekspresi ke satu baris blade (`{{ $tahunAjaran->nama }}@if(...) — {{ $tahunAjaran->lembaga->nama }}@endif`) — perilaku/hasil HTML akhir sama persis dengan brief, hanya format sumber blade yang dipadatkan.
   - **Penutup plan**: full test suite dijalankan (`php artisan test --compact`) → **2942 passed, 4 failed (7964 assertions)**. Pint (`vendor/bin/pint --dirty --format agent`) → `{"tool":"pint","result":"passed"}`, tidak ada perubahan style diperlukan.

---

## 2. Keputusan Penting

1. **Assersi tambahan di Task 1 (bukan pengurangan)**: dua test brief untuk `KasusAksesLogController`/`KasusTerhapusController` ternyata vacuous-pass di kode lama (masking `YayasanScope` pada accessor `nama_lengkap`). Assersi `assertViewHas('totalAkses'/'totalTerhapus', 1)` ditambahkan agar test benar-benar red→green terhadap bug yang ditutup, tanpa menghapus assersi asli dari brief.
2. **`RuanganController` (Task 6) sengaja tidak memakai pola generic** `$statsFilter` seperti 3 controller Sarpras/Pengadaan lain — closure fallback aslinya (dengan cabang `is_shared` untuk ruangan lintas lembaga yang dibagi) diekstrak jadi `$scopeFilter` dan dipakai ulang di semua 5 query (list + 4 stats), plus `withoutGlobalScope(TenantScope::class)` konsisten. Ini instruksi eksplisit brief karena perilaku aslinya lebih kompleks dari 3 controller lain.
3. **Fix dropdown `RaporController` (Task 7) dibatasi ketat pada 1 kondisi**: label lembaga hanya tampil saat `widestScopeLevel() === 'yayasan'` DAN `active_lembaga_id` kosong di session — tidak menyentuh tampilan untuk lembaga-scope atau yayasan yang sudah memilih lembaga aktif, sesuai global constraint plan.
4. **Pola perbaikan yang konsisten di seluruh Kategori D**: mengganti filter `where('lembaga_id', $lembagaId)` wajib (yang secara tidak sengaja jadi `WHERE lembaga_id IS NULL` saat `$lembagaId` null) menjadi `when($lembagaId !== null, ...)` — melonggarkan filter saat mode "Semua Lembaga" (bukan mempersempit), tanpa mengubah perilaku lembaga-scope yang `$lembagaId`-nya selalu terisi.

---

## 3. Hal yang Masih Perlu Direview

### Hasil Full Test Suite Final

```
php artisan test --compact
Tests:    4 failed, 2942 passed (7964 assertions)
```

**4 kegagalan — pre-existing, TIDAK terkait plan ini** (tidak satu pun file di bawah ini disentuh oleh commit manapun dari 7 task plan — dikonfirmasi via `git log --oneline 8b949349..HEAD --stat`, nihil hasil untuk kata kunci "seeder"):

1. `Tests\Unit\M3DemoDataSeederTest` — `it seeds a spread of pendaftaran states...` (assert count PPDB `>= 3`, dapat `0`) dan `it is idempotent when the full DatabaseSeeder is run twice` (assert `8` identik `4`). Soal seeding demo PPDB/pendaftaran, sama sekali di luar domain yayasan/lembaga-scope yang disentuh plan ini.
2. `Tests\Unit\PresensiSeederTest` — `it seeds student attendance records...` (assert count presensi `> 0`, dapat `0`). Diketahui dari sesi-sesi sebelumnya sebagai seeder yang day-of-week/tanggal-dependent (lihat catatan historis serupa di `PETA_PENGEMBANGAN.md` soal `SesiPembelajaranSeeder` bergantung `Carbon::yesterday()`).
3. `Tests\Unit\SesiPembelajaranSeederTest` — `it seeds learning sessions...` (assert count sesi `> 0`, dapat `0`). Sudah eksplisit disebut di brief sebagai contoh known pre-existing flaky dari sesi sebelumnya.

Tidak ada perbaikan dilakukan terhadap 4 test ini (di luar scope task 7 / plan ini secara eksplisit).

### Di Luar Scope (dicatat, JANGAN dikerjakan tanpa keputusan produk baru)

1. **3 item global lintas SEMUA yayasan**: `JenisKaryawanMasterController`, `JabatanTambahanMasterController`, `WhatsAppTemplateController` — tabelnya tidak punya kolom `lembaga_id`/`yayasan_id` sama sekali (shared lintas SEMUA yayasan di sistem, bukan cuma lintas lembaga). Beda kelas masalah dari spec ini, butuh keputusan produk dulu (katalog nasional yang disengaja, atau harus dibatasi per-yayasan) — belum ada keputusan.
2. **`JadwalPelajaranController`** — dropdown guru/mapel lintas lembaga sebelum kelas dipilih. Dinilai minor (tervalidasi ulang saat submit, tidak menyebabkan data salah tersimpan), diabaikan untuk plan ini.
3. **Method lain di `VirtualAccountController`/`ManualPaymentController`** selain `index()` (`riwayat()`, `calonGenerate()`, `generate()`, `export()`, dan method non-`index()` lain di `ManualPaymentController`) yang memakai helper `lembagaId()` yang sama — berpotensi bug serupa D.1/D.2 tapi TIDAK diverifikasi/diperbaiki di sini (scope eksplisit dibatasi ke `index()` saja).
4. **Modul SPMB/PPDB** — sengaja dikecualikan total dari audit ini, sedang dibekukan/menunggu rombakan terpisah.

### Status Git Saat Ini

- Branch: `rbac-v2`.
- Belum di-merge ke `main`.
- Working tree bersih setelah commit dokumentasi ini (kecuali direktori `storage/debugbar/` yang untracked, tidak relevan).
