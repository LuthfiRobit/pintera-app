# Handoff Log: 5 Perbaikan UI/UX Lanjutan Modul Jadwal Pelajaran

**Tanggal:** 2026-09-12  
**Branch:** `rbac-v2`  
**Spec:** [2026-09-12-jadwal-pelajaran-iterasi-ui.md](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-12-jadwal-pelajaran-iterasi-ui.md)  
**Plan:** [2026-09-12-jadwal-pelajaran-iterasi-ui.md](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-12-jadwal-pelajaran-iterasi-ui.md)  

---

## 1. Apa yang Dikerjakan

Semua 5 perbaikan UI/UX lanjutan yang diminta oleh pengguna telah diimplementasikan, diuji melalui TDD/Pest, diverifikasi linting Pint, di-build ulang dengan Vite, dan divalidasi visualnya melalui subagent browser:

1. **Poin 1: Tata Letak Tombol Reset Filter & Menghilangkan Ruang Kosong**:
   - Menghapus baris pemisah kosong `border-b border-gray-100 pb-2` di [index.blade.php](file:///d:/laragon/www/pintera-app/resources/views/portals/lembaga/akademik/jadwal-pelajaran/index.blade.php).
   - Memindahkan tombol `Reset Filter` langsung ke jajaran tombol aksi kanan pada header card filter. Tombol hanya muncul ketika filter terisi (`tahunAjaranId || kelasId || semesterId`) dengan tampilan tombol rounded-xl putih ber-border halus, tanpa menyisakan celah kosong apapun baik saat filter belum dipilih maupun sudah dipilih.

2. **Poin 2: Eliminasi KPI Cards**:
   - Menghapus blok kartu KPI (Mata Pelajaran Aktif & Guru Pengampu Terlibat) dari [_daftar.blade.php](file:///d:/laragon/www/pintera-app/resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php).
   - Memperbarui test feature di [JadwalPelajaranCrudTest.php](file:///d:/laragon/www/pintera-app/tests/Feature/Admin/JadwalPelajaranCrudTest.php) agar meng-assert ketiadaan KPI card dan memastikan tombol Reset Filter tampil di header.

3. **Poin 3: Perlebar Modal Form Create & Edit**:
   - Memperlebar modal di [_modal-form.blade.php](file:///d:/laragon/www/pintera-app/resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-form.blade.php) dari `sm:max-w-xl` menjadi `sm:max-w-2xl`, memberikan ruang yang proporsional bagi form 3-kolom (Mata Pelajaran, Guru Pengampu, Ruangan Sarpras) dan multi-select slot jam.

4. **Poin 4: TomSelect Universal di Seluruh Select**:
   - **Modal Form**: Select `ruangan_id` kini diinisialisasi dengan `initModalRuanganSelect($el)` di [jadwal-pelajaran-filter.js](file:///d:/laragon/www/pintera-app/resources/js/jadwal-pelajaran-filter.js). State tersinkronisasi saat membuka form create (dibersihkan) maupun edit (di-set nilainya).
   - **Modal Duplikasi**: Select `source_semester_id` dan `source_kelas_id` di [_modal-duplicate.blade.php](file:///d:/laragon/www/pintera-app/resources/views/portals/lembaga/akademik/jadwal-pelajaran/_modal-duplicate.blade.php) diubah menjadi TomSelect (`initDuplicateSemesterSelect` dan `initDuplicateKelasSelect`). Ketika Tahun Ajaran Sumber dipilih, opsi dimasukkan via API TomSelect (`addOption()` dan `refreshOptions(false)`).
   - **Fallback Create & Edit**: Menambahkan method `initRuanganSelect(el)` di [jadwal-pelajaran-create.js](file:///d:/laragon/www/pintera-app/resources/js/jadwal-pelajaran-create.js), dan memasang `x-ref="ruanganSelect" x-init="initRuanganSelect($refs.ruanganSelect)"` di [create.blade.php](file:///d:/laragon/www/pintera-app/resources/views/portals/lembaga/akademik/jadwal-pelajaran/create.blade.php) dan [edit.blade.php](file:///d:/laragon/www/pintera-app/resources/views/portals/lembaga/akademik/jadwal-pelajaran/edit.blade.php).

5. **Poin 5: Tampilan Daftar Mengadopsi Pola Jam (Daftar Harian)**:
   - Merombak total bagian `viewMode === 'list'` di [_daftar.blade.php](file:///d:/laragon/www/pintera-app/resources/views/portals/lembaga/akademik/jadwal-pelajaran/_daftar.blade.php):
     - Menambahkan **Tab Filter Hari Horizontal** di baris atas dengan badge counter sesi: `Senin (6)`, `Selasa (6)`, dst.
     - Mengelompokkan konten sesi belajar per hari (`hariAktif`), dengan pesan fallback rapi dan miring jika suatu hari belum memiliki sesi.
     - Format baris sesi identik dengan Pola Jam: badge urutan jam mono (`bg-gray-100 font-mono`), pill rentang waktu (`H:i` &rarr; `H:i`) mono ber-ring, durasi menit `(X mnt)`, badge label slot jam, nama Mapel tebal, nama Guru dengan icon person, badge Ruangan dengan icon meeting_room.
     - Tombol aksi `Edit` dan `Hapus` menggunakan styling tombol badge ber-border (`rounded-lg border border-gray-200 ...` dan `border-error-200 bg-error-50/30 ...`) serta dilengkapi `<x-tooltip>`.
   - Mengoperasikan `collect($hariAktif)->first()?->value ?? 'senin'` untuk menjamin kompatibilitas array enum `Hari`.

---

## 2. Keputusan Penting yang Diambil

1. **Integrasi Tombol Reset Filter ke Header**:
   - Menempatkan tombol `Reset Filter` di samping tombol `Salin dari Kelas Lain` di dalam header card filter menghilangkan kebutuhan satu baris horizontal penuh ber-border, sehingga kartu filter tampil jauh lebih padat dan rapi.
2. **Koleksi Aman Array vs Collection untuk `$hariAktif`**:
   - Di controller, `$hariAktif` dikirimkan sebagai array murni (`Hari::aktifDari(...)`). Untuk inisialisasi state Alpine `hariAktif: '{{ collect($hariAktif)->first()?->value ?? 'senin' }}'`, pembungkus `collect(...)` digunakan agar Blade tidak memanggil method `first()` pada tipe data array.
3. **Pencopotan `x-model` pada TomSelect Modal Duplikasi**:
   - `x-model` dicopot dari select `source_semester_id` dan `source_kelas_id` dan digantikan oleh callback `onChange` pada instance TomSelect. Ini mencegah desinkronisasi atau looping event antara Alpine dan TomSelect DOM instance.

---

## 3. Verifikasi & Pengujian

1. **Test Pest**:
   - Menjalankan `php artisan test tests/Feature/Admin/JadwalPelajaranCrudTest.php --compact`.
   - **Hasil: 77 tests, 223 assertions — SEMUA PASS 100%**.
2. **Linting Pint**:
   - `vendor/bin/pint --dirty --format agent`: **PASSED**.
3. **Kompilasi Frontend (Vite)**:
   - `npm.cmd run build`: **SUKSES** (30.43s), `app-CIPcDtxI.css` & `app-CpomEeZj.js` ter-bundle tanpa error.
4. **Verifikasi Visual Browser**:
   - Browser subagent merekam dan menangkap screenshot:
     - `tampilan_daftar_jadwal_1789167193363.png`: Tampilan daftar dengan tab filter hari horizontal dan baris sesi bergaya Pola Jam, tombol reset filter di header tanpa spasi kosong, KPI cards telah hilang.
     - `modal_create_jadwal_1789167205228.png`: Modal tambah slot terbuka lebar (`max-w-2xl`) dengan dropdown Ruangan Sarpras berbasis TomSelect.
     - `modal_duplikasi_jadwal_1789167227970.png`: Modal salin jadwal dengan dropdown Tahun Ajaran, Semester, dan Kelas Sumber yang seragam menggunakan TomSelect.

---

## 4. Hal yang Masih Perlu Direview Manusia / Claude

1. **Status Git**:
   - Branch: `rbac-v2`
   - File yang dimodifikasi: 10 file (9 view/JS/test + handoff/spec/plan).
   - Perubahan belum di-commit dan belum di-push (siap di-commit).
