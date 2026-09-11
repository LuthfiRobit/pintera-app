# Handoff Log: Audit & Penyempurnaan UI/UX Modul Assignment Kurikulum

- **Tanggal**: 11 September 2026
- **Spec Referensi**: `.agents/specs/2026-09-11-kurikulum-assignment-audit-perbaikan.md`
- **Plan Referensi**: `.agents/plans/2026-09-11-kurikulum-assignment-audit-perbaikan.md`
- **Branch**: `rbac-v2` (ahead of origin/rbac-v2 by 95 commits, unpushed/unmerged sesuai aturan proyek)

---

## 1. Apa yang Dikerjakan

Modul **Assignment Kurikulum** (`resources/views/admin/kurikulum-assignment/*`) bertindak sebagai rule engine yang menentukan kurikulum dan fase default untuk kelas baru melalui hirarki fallback 3 tingkat (Tingkat Spesifik ➔ Lembaga Catch-all ➔ Platform Default). Seluruh domain logic dipertahankan utuh karena sudah solid. Perubahan berfokus pada perbaikan kritis presentasional, keselamatan operasional, kepatuhan multi-tenancy, dan penyempurnaan UI/UX:

1. **Anti-Typo Tingkat (Task 1)**:
   - Mengganti input teks bebas pada field `tingkat` dengan interactive pill selector berbasis `BentukPendidikan::validTingkatValues()` secara dinamis.
   - Menyediakan tombol beralih cepat antara *Semua Tingkat (Default Jenjang)* dan *Tingkat Tertentu*.

2. **Keselamatan Operasional Resync (Task 2)**:
   - Menghubungkan proses sinkronisasi massal dengan modal konfirmasi `confirmDialog()` standar Pintera sebelum mengeksekusi POST ke server.
   - Melacak baris kelas yang dicentang secara reaktif via Alpine `x-model="terpilih"`.

3. **Modernisasi Index & Filter AJAX (Task 3)**:
   - Standarisasi kontainer halaman ke `mx-auto max-w-6xl space-y-4`.
   - Menambahkan 3 KPI metrics cards ringkas: *Total Aturan*, *Aturan Lembaga (Khusus)*, dan *Standar Platform (Cadangan)*.
   - Ekstraksi tabel ke partial `_daftar.blade.php` dan integrasi `dataTableFilter` untuk filter AJAX real-time (Tahun Ajaran & Bentuk Pendidikan) tanpa reload penuh.
   - Menjaga tenant scoping: filter AJAX dieksekusi ketat *setelah* tenant scoping.

4. **Form Edit Guard & Callout Dampak (Task 4)**:
   - Metadata immutable (`Berlaku Untuk` dan `Tahun Ajaran`) dipindahkan ke kartu ringkasan read-only berbadge semantik pada mode edit.
   - Menambahkan callout peringatan berwarna biru bahwa perubahan aturan kurikulum hanya berlaku untuk kelas baru, sedangkan kelas lama harus diselaraskan melalui menu Sinkronisasi.
   - Menambahkan breadcrumb navigasi standar Pintera di create & edit.

5. **Visual Diff & Floating Bar Resync (Task 5)**:
   - Menambahkan field baca-saja `faseLamaNama` pada `ResyncKurikulumFaseKelasAction::hitungDiff()`.
   - Menampilkan visual diff chip transisi kurikulum dan fase (`lama → baru`).
   - Floating bulk action bar otomatis muncul di bagian bawah saat ada kelas yang dicentang.
   - Empty state instruksional awal dan zero-drift state sukses yang ramah saat semua kelas sudah selaras.

6. **Standardisasi Istilah Bahasa Indonesia (Task 6)**:
   - Mengganti seluruh sisa istilah teknis lama (*Assignment* ➔ *Aturan Kurikulum*, *Platform Default* ➔ *Standar Platform*, *Cek Drift* ➔ *Pindai Keselarasan*, *Sinkronkan yang Dicentang* ➔ *Terapkan Sinkronisasi*).
   - Dikunci dengan test regresi negatif.

7. **Audit Lanjutan & Penyempurnaan Tambahan (Audit Review)**:
   - **Auto-Select Kurikulum di Edit**: Memperbaiki closure `$val` pada `_form.blade.php` untuk menangani backed enum `KurikulumFramework`. Sebelumnya nilai enum tidak ter-unwrap ke string, menyebabkan pembandingan strict `===` bernilai `false` sehingga opsi kurikulum lama tidak ter-select otomatis.
   - **Ambiguitas Scope di Halaman Resync**: Mengintegrasikan `ResolveLembagaScopeTrait` pada `ResyncKurikulumFaseController`. Saat aktor yayasan/lembaga sudah memilih lembaga aktif di switcher (`session('active_lembaga_id')`), form resync langsung mengunci lembaga tersebut sebagai context read-only ber-icon `apartment` dan hidden input, menghilangkan opsi lembaga lain agar tidak membingungkan. Opsi dropdown hanya muncul jika dalam mode agregat (*Semua Lembaga*), dan untuk yayasan diisolasi ketat hanya menampilkan unit milik yayasannya sendiri.
   - **Styling Checkbox Pintera**: Checkbox "pilih semua" dan checkbox baris di `resync.blade.php` diberikan kelas standar Pintera: `h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 transition cursor-pointer`.
   - **Penyatuan Style Select Option**: Mengganti seluruh tag `<select>` mentah di `index.blade.php`, `_form.blade.php`, dan `resync.blade.php` dengan komponen standar `<x-select>` sehingga seragam di seluruh modul.

---

## 2. Riwayat Komit Git

Seluruh pekerjaan telah terkomit rapi di branch `rbac-v2`:

- `816ea13c`: `fix(kurikulum-assignment): auto-select kurikulum di edit, hilangkan ambiguitas scope resync, percantik checkbox & satukan x-select`
- `92767dfc`: `docs(kurikulum-assignment): perbarui checklist kemajuan plan`
- `e36d0c26`: `test(kurikulum-assignment): kunci standardisasi wording dengan test regresi negatif`
- `ec3fc748`: `feat(kurikulum-assignment): index dapat filter AJAX, KPI ringkas, badge hierarki fallback`
- `71dec764`: `feat(kurikulum-assignment): resync tampilkan nama fase lama, diff chip, floating bulk bar, empty/zero-drift state`
- `0f74fcb5`: `feat(kurikulum-assignment): tambah confirmDialog sebelum sinkronisasi massal`
- `14d9670f`: `feat(kurikulum-assignment): metadata card + callout dampak di edit, breadcrumb create/edit`
- `f57b95b4`: `feat(kurikulum-assignment): ganti input tingkat jadi pill selector anti-typo`
- `1cf7b4b8`: `docs(kurikulum-assignment): tulis kickoff untuk eksekusi plan`

---

## 3. Keputusan Penting yang Diambil

1. **Enum Value Unwrapping di Blade Form**:
   Model `KurikulumAssignment` menggunakan model casts `'kurikulum' => KurikulumFramework::class`. Di partial form Blade `_form.blade.php`, `$assignment->$field` menghasilkan instance Enum object. Closure `$val` disesuaikan untuk mengecek `instanceof \BackedEnum ? $current->value : $current`. Keputusan ini menjaga kompatibilitas tipe data baik saat create (string dari `old()`), saat edit (object Enum dari model), maupun saat validasi error.

2. **Perilaku Bentuk Pendidikan pada Platform Actor di Mode Edit**:
   Sesuai logika backend di `KurikulumAssignmentController@update` baris 187-190, aktor platform diperbolehkan mengubah `bentuk_pendidikan` bahkan di mode edit. Oleh karena itu, field Bentuk Pendidikan tidak dikunci dalam metadata card read-only, melainkan tetap menggunakan `<x-select>` untuk aktor platform, dan dikunci sebagai teks read-only hanya untuk aktor non-platform.

3. **Eliminasi Ambiguitas Multi-Tenancy di Resync**:
   Saat yayasan memilih lembaga spesifik di topbar switcher, pengguna mengharapkan halaman resync langsung fokus pada lembaga tersebut. Menampilkan dropdown semua lembaga menimbulkan risiko salah pilih dan membingungkan operator. Solusinya: jika lembaga aktif terdeteksi, dropdown lembaga ditiadakan dan diganti info badge lembaga aktif, sementara daftar tahun ajaran langsung disaring untuk lembaga tersebut. Pada level controller, `authorizeScope` memastikan request apply juga terproteksi dari tampering `lembaga_id`.

4. **Komponen `<x-select>`**:
   Seluruh input select dimigrasikan dari HTML mentah ke `<x-select>`, memastikan gaya visual, border, focus ring, ukuran padding, dan error state konsisten dengan design system Pintera di modul lain.

---

## 4. Hasil Pengujian & Verifikasi

1. **Test Suite Modul (100% Passed)**:
   - `tests/Feature/Akademik/KurikulumAssignmentControllerTest.php`: 50 passed
   - `tests/Feature/Akademik/ResyncKurikulumFaseControllerTest.php`: 12 passed
   - `tests/Feature/Akademik/ResyncKurikulumFaseKelasTest.php`: 6 passed
   - `tests/Feature/Admin/KurikulumAssignmentDestroyGuardTest.php`: 3 passed
   - `tests/Unit/Models/KurikulumAssignmentTest.php`: 4 passed
   - `tests/Unit/Services/KurikulumAssignmentResolverTest.php`: 8 passed
   - **Total Scoped Tests**: 83 passed, 0 failures.

2. **Format Code**:
   - `vendor/bin/pint --dirty --format agent` dijalankan dan bersih.

3. **Frontend Build**:
   - `npm.cmd run build` (Vite) sukses tanpa error/warning.

4. **Visual Browser Subagent**:
   - Index Page: `index_page_filters_1789140122698.png` (filter select TomSelect rapi dan seragam).
   - Edit Page: `edit_kurikulum_open_1789140688174.png` (kurikulum otomatis ter-select dan dropdown TomSelect terbuka dengan styling Outfit font, border-radius 10px, shadow, dan hover highlight).
   - Create Page: `create_kurikulum_options_open_1789140798020.png` (Tahun Ajaran & Kurikulum TomSelect aktif dan seragam).
   - Resync Page: `resync_tahun_ajaran_open_1789140656866.png` (Lembaga & Tahun Ajaran TomSelect dengan floating dropdown menu).

---

## 5. Rollout Standarisasi Select Option (TomSelect Pintera)

Seluruh elemen `<select>` di modul Kurikulum Assignment kini telah diupgrade ke TomSelect standar Pintera:
- **`resync.blade.php`**: `lembaga_id` (pada mode agregat yayasan/platform) dan `tahun_ajaran_id` diinisialisasi menggunakan `window.TomSelect` dengan opsi `allowEmptyOption: true` dan container `h-[42px]`. Menggantikan popup select native browser dengan floating box `.ts-dropdown` bertema Outfit font dan border-radius 10px.
- **`_form.blade.php`**: `lembaga_id`, `tahun_ajaran_id`, `bentuk_pendidikan`, dan `kurikulum` diinisialisasi melalui helper Alpine `initTomSelect()`.
  - `kurikulum`: Dropdown rapi dengan pilihan kurikulum ter-styling, mempertahankan nilai default saat create maupun nilai tersimpan saat edit.
  - `bentuk_pendidikan`: Sinkron dua arah dengan reactive state Alpine, mereset pilihan pill tingkat saat bentuk pendidikan diubah.

---

## 6. Hal yang Perlu Direview Manusia / Claude Selanjutnya

1. **Mode Agregat Resync**:
   Saat yayasan berada dalam mode *Semua Lembaga* (tanpa active lembaga terpilih di sesi), form resync menampilkan dropdown yang hanya berisi daftar lembaga di bawah yayasan tersebut. Begitu lembaga dipilih, form melakukan submit GET untuk memuat tahun ajaran lembaga yang bersangkutan.
2. **Kondisi Branch Git**:
   Branch saat ini adalah `rbac-v2`. Sesuai instruksi, perubahan **TIDAK di-merge dan TIDAK di-push** ke remote repository, menunggu instruksi lebih lanjut dari pengguna.
