# Handoff Log: Perbaikan Lintas-Yayasan Modul Karyawan & Guru

> **Tanggal**: 7 September 2026  
> **Branch**: `rbac-v2`  
> **Spec**: `.agents/specs/2026-09-07-karyawan-guru-lintas-yayasan-audit.md`  
> **Plan**: `.agents/plans/2026-09-07-karyawan-guru-lintas-yayasan-audit.md`  
> **Base commit sebelum kickoff**: `662a8321`  
> **Commit range**: `50eedb01..5db68559` (6 commit, perbaikan lintas-yayasan/IDOR) + `3e62cb79` (susulan 8 September 2026, perbaikan wording & aturan bisnis UI — lihat bagian 4)  
> **Status**: Selesai & Terverifikasi (Full Suite: 2.973 test lulus, 0 regresi baru)

---

## 1. Apa yang Dikerjakan

Menyelesaikan audit menyeluruh pada modul Karyawan & Guru yang menemukan 6 bug (termasuk 2 kebocoran data riil lintas yayasan, 4 titik IDOR validasi `Rule::exists`, penanganan karyawan pool pada sistem presensi & alpa otomatis, error 500 race condition Guru, dan fitur pencarian NIK di index Karyawan).

### Rincian Commit:

1. **Commit `50eedb01` — Task 1: Kelompok A — 4 Titik Validasi `Rule::exists` Scoped ke Yayasan (IDOR)**
   - `app/Http/Controllers/Admin/KaryawanController.php`:
     - `store()`: `Rule::exists('jenis_karyawan_master', 'id')->where('yayasan_id', $yayasanId)` (menggunakan yayasan aktor login).
     - `update()`: `Rule::exists('jenis_karyawan_master', 'id')->where('yayasan_id', $karyawan->yayasan_id)` (menggunakan yayasan pemilik row karyawan).
   - `app/Http/Controllers/Admin/AttendanceConfigurationController.php`:
     - `storePolicy()`: Menghitung `$yayasanIdUntukValidasi` dari raw input request (`lembaga_id` atau fallback aktor login) sebelum validasi dijalankan, lalu menerapkan `Rule::exists('jenis_karyawan_master', 'id')->where('yayasan_id', $yayasanIdUntukValidasi)`.
   - `app/Http/Controllers/Admin/Guru/JabatanTambahanController.php`:
     - `store()`: Mengambil yayasan guru pemilik row via `$guru->lembaga->yayasan_id`, lalu menerapkan `Rule::exists('jabatan_tambahan_master', 'id')->where('yayasan_id', $yayasanId)`.
   - Pengujian: Menambahkan 5 test isolasi tenant IDOR di `KaryawanCrudTest.php`, `AttendancePolicyControllerTest.php`, dan `GuruRelationalProfileTest.php`.

2. **Commit `eec40563` — Task 2: Kelompok B — Tutup Kebocoran Lintas-Yayasan pada 2 Resolver SDM**
   - `app/Domains/Sdm/Services/AttendancePolicyResolver.php`:
     - `resolvePolicy()`: Tier "per-lembaga spesifik" (yang sebelumnya diam-diam collapse jadi `whereNull('lembaga_id')` TANPA filter yayasan untuk karyawan pool) dibungkus `if ($pegawai->lembaga_id !== null) { ... }` sehingga TIDAK PERNAH dieksekusi sama sekali untuk pool — pool langsung jatuh ke tier nasional/yayasan yang sudah benar difilter `where('yayasan_id', $yayasanId)`.
     - `resolveLiburPool()`: Helper baru khusus karyawan pool yang mengecek libur nasional/eksplisit via `KalenderKerjaSdm` scoped ke yayasan pool, dengan fallback hari kerja (default hari kerja, tanpa kolom mingguan yayasan).
   - `app/Domains/Sdm/Services/KuotaCutiResolver.php`:
     - `resolveConfig()`: Pola identik `AttendancePolicyResolver` — tier "per-lembaga" (spesifik & flat) dibungkus `if ($pegawai->lembaga_id !== null) { ... }`, dilewati total untuk pool, langsung ke tier nasional/yayasan.
   - Pengujian: Menambahkan 2 test di `AttendancePolicyTenantIsolationTest.php` dan 1 test di `KuotaCutiResolverTest.php`. Verifikasi grep memastikan tidak ada resolver SDM lain yang memiliki pola un-scoped serupa.

3. **Commit `2599a731` — Task 3: Kelompok C — Sertakan Karyawan Pool di Alpa Otomatis & Dropdown Pemilih Karyawan**
   - Migrasi `2026_09_07_132218_make_lembaga_id_nullable_on_attendance_events_table.php`:
     - Mengubah kolom `lembaga_id` menjadi `nullable()` pada tabel `attendance_events` DAN `attendance_records` (Foreign Key `ON DELETE CASCADE` tetap dipertahankan).
   - `app/Console/Commands/TandaiAlpaOtomatisSdm.php`:
     - Menambahkan pass kedua per-yayasan setelah loop per-lembaga selesai untuk memproses karyawan pool (`lembaga_id IS NULL`, `yayasan_id = $yayasan->id`).
     - Menggunakan `resolveLiburPool()` dan menandai alpa menggunakan `AttendanceRecord` tanpa lembaga.
   - `app/Http/Controllers/Admin/AttendanceConfigurationController.php` & `AttendanceController.php`:
     - Query `$karyawanList` dropdown diperbarui menjadi pool-aware (`where('karyawan.lembaga_id', $lembagaId)->orWhere(fn ($q) => $q->whereNull('karyawan.lembaga_id')->where('karyawan.yayasan_id', $yayasanId))`).
     - Mengkualifikasi nama kolom dengan prefix tabel `karyawan.` untuk mencegah ambiguitas akibat join dengan tabel `persons`.
   - Pengujian: Menambahkan 2 test di `TandaiAlpaOtomatisSdmTest.php`, 1 test di `AttendanceConfigurationControllerTest.php`, dan 1 test di `AttendanceControllerTest.php`.

4. **Commit `58be527e` — Task 4: Kelompok D — Tangkap `PersonAlreadyExistsException` di `GuruController::store()`**
   - `app/Http/Controllers/Admin/GuruController.php`:
     - Membungkus pemanggilan `DB::transaction` di `store()` dengan blok `try / catch (PersonAlreadyExistsException $exception)` untuk mengembalikan error validasi ramah (`withErrors(['nik' => '...'])`) alih-alih HTTP 500 mentah saat terjadi race condition konkuren.
   - Pengujian: Menambahkan test penanganan error duplikasi NIK di `GuruCrudTest.php`.

5. **Commit `2c863e89` — Task 5: Kelompok E — Index Karyawan: Tambah Pencarian NIK**
   - `resources/views/admin/karyawan/index.blade.php`:
     - Menambahkan `'nik' => $k->person?->nik` pada array payload JSON `$spaItems`.
     - Memperbarui getter Alpine.js `filteredItems` dengan kondisi `|| (i.nik && i.nik.toLowerCase().includes(q))`.
   - Pengujian: Menambahkan test assert JSON payload NIK di `KaryawanCrudTest.php` dan verifikasi visual end-to-end langsung via Playwright browser subagent.

6. **Commit `5db68559` — Penyelarasan Fixture Test Pre-Existing**
   - `tests/Feature/KaryawanControllerTest.php`:
     - Menyelaraskan pembuatan fixture `$jenisKaryawan = JenisKaryawanMaster::factory()->create(['yayasan_id' => $yayasan->id]);` agar sesuai dengan validasi tenant scope baru pada update Karyawan.

---

## 2. Keputusan Penting yang Diambil

1. **`attendance_records.lembaga_id` Dijadikan Nullable Bersama `attendance_events`**:
   - Plan awal hanya menyebutkan `attendance_events.lembaga_id` diubah nullable. Namun saat eksekusi command Alpa otomatis, `AttendanceRecordAggregator::sync()` memetakan `$event->lembaga_id` ke record baru di `attendance_records`. Jika `attendance_records.lembaga_id` tetap `NOT NULL`, database melempar SQL Error 1048.
   - Keputusan: Migrasi `2026_09_07_132218_make_lembaga_id_nullable_on_attendance_events_table.php` diterapkan secara atomik pada KEDUA tabel (`attendance_events` dan `attendance_records`).
2. **Kualifikasi Tabel Eksplisit pada Query Dropdown Karyawan**:
   - Scope `orderByNama()` pada model `Karyawan` melakukan join ke tabel `persons`. Karena kedua tabel memiliki kolom `yayasan_id` dan `lembaga_id`, pemanggilan `where('yayasan_id', ...)` atau `whereNull('lembaga_id')` memicu MySQL Error 1052 (*Column 'yayasan_id' in where clause is ambiguous*).
   - Keputusan: Semua klausa filter tenant di `AttendanceConfigurationController.php` dan `AttendanceController.php` wajib ditulis dengan kualifikasi tabel lengkap (`karyawan.yayasan_id` dan `karyawan.lembaga_id`).
3. **Default Karyawan Pool Dianggap Hari Kerja**:
   - Sesuai konfirmasi bisnis user, karyawan pool tidak mengacu pada hari libur mingguan lembaga manapun dan model `Yayasan` sengaja tidak memiliki kolom libur mingguan.
   - Karyawan pool selalu dianggap hari kerja kecuali terdapat entri libur eksplisit pada `KalenderKerjaSdm` (`lembaga_id IS NULL`).
4. **Penyelarasan Fixture Test Existing `KaryawanControllerTest`**:
   - Sebelum perbaikan Task 1, `KaryawanController::update` menerima `jenis_karyawan_id` milik yayasan mana pun (celah IDOR). Tes lama `tests/Feature/KaryawanControllerTest.php` membuat `$jenisKaryawan` tanpa parameter `yayasan_id` (sehingga terbuat di yayasan acak baru).
   - Setelah IDOR ditutup, tes ini gagal secara valid dengan pesan validasi *"The selected jenis karyawan id is invalid."* Fixture tes diselaraskan agar berada di yayasan yang sama dengan karyawan.
5. **Defense-in-Depth pada GuruController Race Condition**:
   - Draf pengujian mengakui secara jujur bahwa test HTTP sekuensial sulit mereplikasi race condition konkuren murni. Penanganan `PersonAlreadyExistsException` tetap diimplementasikan sebagai lapisan pertahanan mendalam (*defense-in-depth*) melengkapi pre-check `validateProfil()`.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Hasil Full Test Suite**:
   - Total test yang dieksekusi: **2.977 tests** (8.077 assertions).
   - **2.973 test PASSED**.
   - **4 test FAILED** adalah kegagalan *pre-existing* yang sudah dikenal dan tidak terkait dengan modul SDM:
     - `Tests\Unit\M3DemoDataSeederTest > it seeds a spread of pendaftaran states across K-9 institutions...`
     - `Tests\Unit\M3DemoDataSeederTest > it is idempotent when the full DatabaseSeeder is run twice`
     - `Tests\Unit\PresensiSeederTest > it seeds student attendance records for the SD institution...`
     - `Tests\Unit\SesiPembelajaranSeederTest > it seeds learning sessions across all K-9 institutions`
   - **0 regresi baru** pada seluruh suite aplikasi.
2. **Item di Luar Scope (Dikonfirmasi User)**:
   - Kolom `hari_libur_mingguan_sdm` pada model/tabel `yayasan` sengaja tidak dibuat.
   - Perilaku migrasi `down()` yang lossy jika sudah ada baris `lembaga_id IS NULL` adalah perilaku yang diharapkan (*by design*).
3. **Status Git**:
   - Branch aktif: `rbac-v2`.
   - **TIDAK di-merge ke `main`** dan **TIDAK di-push** sesuai instruksi eksplisit kickoff.

---

## 4. Susulan (8 September 2026) — Perbaikan Wording & Kelengkapan Aturan Bisnis UI

Setelah bug lintas-yayasan di atas selesai, user meminta review ulang: *"apakah keduanya sudah sempurna termasuk wording dan ketentuannya?"* — bukan soal keamanan/scope lagi (sudah beres), tapi soal kejelasan teks & apakah aturan bisnis backend benar-benar dikomunikasikan ke user di UI. Audit lewat 2 subagent paralel (1 Karyawan, 1 Guru) menemukan ~24 temuan. Diperbaiki LANGSUNG (bukan lewat spec/plan, atas instruksi eksplisit user), commit `3e62cb79`:

**Karyawan** (`resources/views/admin/karyawan/{index,_form}.blade.php`, `tabs/profil.blade.php`, `app/Http/Controllers/Admin/KaryawanController.php`):
- Subtitle index sekarang menjelaskan konsep "Karyawan Pool" (sebelumnya dipakai tanpa definisi sama sekali).
- Istilah diseragamkan jadi **"Karyawan Pool"** di semua tempat (stat card, filter tab, fallback PHP) — sebelumnya "Pool Yayasan"/"Karyawan Pool"/"Lintas Lembaga" tercampur untuk 1 konsep yang sama.
- Kolom "Kapasitas Kasus" diberi tooltip (khusus Konselor BK/Psikolog, kenapa mayoritas baris tampil "-").
- Dialog konfirmasi ubah "Status Akun" sekarang eksplisit bilang itu juga menonaktifkan/aktifkan LOGIN karyawan — sebelumnya efek samping ini tidak diberitahu.
- **Form edit — field "Penempatan" (Pool/lembaga) yang SEBELUMNYA HILANG TOTAL di edit mode** (bukan cuma disabled, betul-betul tidak dirender) sekarang ditampilkan disabled+berisi value, mengikuti pola yang sudah benar di modul Lembaga sendiri. Ini temuan paling signifikan dari audit ini.
- Checkbox "Karyawan Pool" + select Yayasan diberi hint bahwa pilihan itu permanen setelah data disimpan.
- 2 pesan error NIK duplikat yang sebelumnya nyaris identik ("...untuk karyawan lain" vs "...untuk akun lain") diperjelas jadi benar-benar beda maknanya (duplikat Person dalam 1 yayasan vs duplikat username lintas sistem).

**Guru** (`resources/views/admin/guru/{_form,_daftar,edit}.blade.php`, `tabs/{profil,jabatan-tambahan,riwayat-pendidikan,sertifikasi}.blade.php`, `app/Http/Controllers/Admin/GuruController.php`, `app/Http/Controllers/Admin/Guru/JabatanTambahanController.php`):
- Empty-state daftar guru sekarang membedakan "belum ada data" vs "tidak ada hasil filter" (`request()->anyFilled(['search', 'jenis_ptk', 'status_aktif'])`) — sebelumnya SELALU bilang "tambahkan guru pertama" walau cuma filter yang kosong.
- Placeholder ditambah ke NIK/NIP/Email/No. HP/Golongan Pangkat — form ini sebelumnya 0 placeholder sama sekali di ~25 input, berbeda dari konvensi modul Lembaga yang konsisten pakai "Contoh: ...".
- Hint NIP: **tidak wajib unik** (kontras eksplisit dengan NIK yang wajib unik, sebelumnya kedua field tampil identik gaya tapi beda aturan tanpa penjelasan).
- Hint email: wajib unik **SELURUH SISTEM** (lintas lembaga/yayasan), bukan cuma di 1 lembaga.
- Hint "PTK = Pendidik dan Tenaga Kependidikan" ditambahkan (sebelumnya singkatan tidak pernah dijelaskan).
- Opsi status kepegawaian di `STATUS_KEPEGAWAIAN_OPTIONS` diberi kepanjangan: `GTY (Guru Tetap Yayasan)`, `PTY (Pegawai Tetap Yayasan)`, dst. — sebelumnya kode mentah tanpa penjelasan (VALUE tidak berubah, cuma LABEL dropdown).
- **3 tab relasional (Jabatan Tambahan, Riwayat Pendidikan, Sertifikasi) yang sebelumnya 0% menampilkan `<x-input-error>` ATAU `old()` value** sekarang punya keduanya — submit gagal sebelumnya kehilangan semua input yang sudah diisi tanpa ada pesan error yang terlihat sama sekali.
- `edit.blade.php`: `activeTab` sekarang dihitung server-side dari `$errors->hasAny([...])` per tab, otomatis kembali ke tab yang BENAR-BENAR gagal validasi — sebelumnya selalu redirect ke tab "Profil" (default hardcoded) walau yang gagal adalah form di tab lain, membuat error terlihat "hilang".
- Pesan Indonesia custom ditambahkan untuk `akhir_periode.after_or_equal` dan `email.unique` — sebelumnya pesan default Laravel (BAHASA INGGRIS, karena `APP_LOCALE=en` di `.env`) muncul di tengah UI berbahasa Indonesia.
- Dialog konfirmasi hapus jabatan tambahan diperbaiki jadi **"Hapus penugasan jabatan ini secara PERMANEN?"** — sebelumnya "...menghapus atau menonaktifkan..." padahal aksinya SELALU hard-delete (`detach()`), wording lama menyesatkan seolah bisa dipulihkan.
- Breadcrumb edit "Manajemen SDM (Guru)" diseragamkan jadi "Guru" (konsisten dengan index/create).

**Typo bersama kedua modul**: judul "Mode Pengemasan & Perubahan Profil" (harfiah "Packaging Mode") — jelas typo/salah terjemahan, muncul identik di `tabs/profil.blade.php` KEDUA modul (kemungkinan 1 sumber yang di-copy 2x) — diperbaiki jadi "Mode Edit & Perubahan Profil".

**Verifikasi**: 36 test terkait (`KaryawanCrudTest`, `KaryawanControllerTest`, `GuruCrudTest`, `GuruRelationalProfileTest`, `GuruControllerTest`, `GuruBkFieldsTest`) tetap hijau, Pint bersih. Full suite TIDAK dijalankan ulang untuk susulan ini (murni perubahan wording/view/pesan error, tidak menyentuh scope/query — cakupan test yang disentuh sudah representatif).

**Di luar scope susulan ini** (dicatat, belum dikerjakan): 2 field lain di modul Karyawan (Yayasan select hint sudah ditambahkan, tapi belum ada styling/urutan ulang form secara menyeluruh); breadcrumb create/edit Karyawan yang sedikit beda gaya ("Detail & Profil Karyawan" vs "Tambah Data Karyawan") belum diseragamkan lebih lanjut — dampaknya kecil, tidak membingungkan end-user, sengaja tidak disentuh supaya susulan ini tetap fokus ke temuan yang benar-benar signifikan.
