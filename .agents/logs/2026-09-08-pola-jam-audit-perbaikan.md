# Handoff Log: Audit & Perbaikan Menu Pola Jam & Jam Pelajaran

**Tanggal:** 2026-09-08  
**Branch:** `akademik-v2` (Clean, commit ahead of origin/rbac-v2)  
**Dokumen Acuan:**
- Spec: [.agents/specs/2026-09-08-pola-jam-audit-perbaikan.md](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-08-pola-jam-audit-perbaikan.md)
- Plan: [.agents/plans/2026-09-08-pola-jam-audit-perbaikan.md](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-08-pola-jam-audit-perbaikan.md)

---

## 1. Apa yang Dikerjakan

Penyelesaian tuntas 7-stage TDD implementation plan untuk 1 bug keamanan kritis (Item A) dan 5 perbaikan UX/kejelasan (Item B-F) pada menu Pola Jam & Jam Pelajaran:

### Item A (Task 1) — 🔴 Fix IDOR Cross-Tenant di `JamPelajaranController::destroy()`
- **Latar Belakang & Bukti Empiris Sebelum Fix:**  
  Model `JamPelajaran` tidak mengimplementasikan `BelongsToTenant` / `lembaga_id` secara langsung (scoping tenant bergantung pada parent-nya, `PolaJam`). Pada `edit()` dan `update()`, pengecekan tenant dilakukan via `PolaJam::find($jamPelajaran->pola_jam_id)`. Namun pada `destroy()`, pengecekan ini tidak ada. Akibatnya, aktor lembaga A dengan permission `jam-pelajaran.delete` dapat menghapus slot jam pelajaran milik lembaga B hanya dengan mengirim request DELETE ke `/admin/jam-pelajaran/{id_milik_lembaga_b}`. Pada pengujian empiris sebelum fix, aksi ini menghasilkan HTTP 302 dan slot berhasil terhapus.
- **Implementasi & Bukti Empiris Setelah Fix:**  
  Menambahkan guard tenant eksplisit:
  ```php
  if (! PolaJam::find($jamPelajaran->pola_jam_id)) {
      abort(404);
  }
  ```
  Karena `PolaJam` dilindungi `TenantScope`, pemanggilan `PolaJam::find()` dengan ID milik lembaga lain akan menghasilkan `null`, sehingga `abort(404)` dieksekusi sebelum action dipanggil.
  Uji otomatis via test `rejects deleting another lembaga's jam pelajaran with 404` memastikan respons HTTP 404 dan record tetap utuh di database (`expect(JamPelajaran::find($jamLain->id))->not->toBeNull()`).
- **Komitmen:** Diselesaikan dan di-commit pertama kali secara terpisah (`674c819a`) sesuai mandat keamanan.

### Item B (Task 2) — `scopeHeaderData()` & Badge Scope di Header Index
- Menambahkan import `use App\Models\Lembaga;` dan method `scopeHeaderData(Request $request): array` pada `PolaJamController`. Mengikuti pola standar controller lain (`MataPelajaranController`, `KelasController`, `TahunAjaranController`).
- Menambahkan badge scope responsif di samping judul menu pada `resources/views/portals/lembaga/akademik/pola-jam/index.blade.php`:
  - Mode Agregat (`Semua Lembaga`): Badge warna violet/purple lembut (`bg-purple-50 text-purple-700 border-purple-200`).
  - Mode Narrow (Lembaga Aktif): Badge warna emerald/brand (`bg-brand-50 text-brand-700 border-brand-200`) dengan nama lembaga aktif.
  - Aktor Lembaga-scope: Tidak menampilkan badge (karena scope sudah tunggal dan jelas).

### Item C (Task 3) — Pill Nama Lembaga per-Kartu Hanya Muncul di Mode Agregat
- Membungkus pill nama lembaga pada masing-masing kartu pola jam dengan kondisi:
  ```blade
  @if (($isYayasan ?? false) && ! ($activeLembaga ?? null) && $pola->lembaga)
      <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">{{ $pola->lembaga->nama }}</span>
  @endif
  ```
- Mencegah redundansi visual saat aktor yayasan sudah memilih lembaga tertentu (nama lembaga sudah ditegaskan di badge header).

### Item D (Task 4) — Hint & State Nonaktif pada Tombol "+ Tambah Pola Jam" Saat Mode Agregat
- Pada mode agregat yayasan (`!$activeLembaga`), pembuatan pola jam tidak dapat diproses backend karena tidak ada lembaga target yang ditentukan.
- Tombol "+ Tambah Pola Jam" kini ditampilkan nonaktif (`disabled`, `opacity-50 cursor-not-allowed`) dilengkapi tooltip penjelasan `title="Pilih lembaga aktif lewat pengalih lembaga terlebih dahulu"`.
- Tombol kembali aktif normal saat lembaga telah dipilih via pengalih lembaga navbar atau bagi aktor lembaga-scope.

### Item E (Task 5) — Hapus Halaman Mati `create()` dan `edit()`
- Verifikasi awal memastikan 100% alur kerja Pola Jam di aplikasi menggunakan modal dialog Alpine di halaman `index.blade.php`, dan tidak ada link navigasi ke halaman `create`/`edit`.
- Menghapus route GET `pola-jam/create` dan `pola-jam/{polaJam}/edit` dari `routes/admin/akademik-master.php`.
- Menghapus method `create()` dan `edit()` dari `app/Http/Controllers/Admin/PolaJamController.php`.
- Menghapus file view mati: `create.blade.php` dan `edit.blade.php`.
- **Catatan Permission:** Permission `pola-jam.create` dan `pola-jam.edit` tetap dipertahankan utuh di seeder karena digunakan oleh `@can()` pada pemicu modal dan otorisasi `store()`, `update()`, dan `duplicate()`.

### Item F (Task 6) — `confirmDialog()` untuk Tombol Duplikat
- Menggantikan direct form submit pada tombol "Duplikat" dengan konfirmasi modal Alpine `confirmDialog()`.
- Menyertakan informasi detail bahwa aksi ini akan menduplikasi seluruh slot jam pelajaran, serta memberi peringatan eksplisit bahwa tautan kelas TIDAK ikut disalin (kelas harus ditautkan ulang secara manual melalui "Kelola Tautan").

---

## 2. Keputusan Penting yang Diambil

1. **Prioritas & Isolasi Task 1:** Task 1 (IDOR) diselesaikan dan di-commit terlebih dahulu (`674c819a`) sebelum modifikasi UX dimulai.
2. **Scoping Model `PolaJam` Tidak Diubah:** Model `PolaJam` sudah aman dengan `BelongsToTenant` / `TenantScope`. Celah keamanan hanya terjadi pada model child (`JamPelajaran`) yang tidak memiliki tenant global scope secara mandiri.
3. **Import `Lembaga`:** Menambahkan `use App\Models\Lembaga;` di `PolaJamController` untuk mencegah fatal error saat pemanggilan `Lembaga::withoutGlobalScopes()->find()`.
4. **Strategi Assertion Test Task 3:** Menguji ketiadaan pill lembaga di narrow mode dengan memeriksa tag HTML spesifik pill (`<span class="... text-gray-600">...</span>`), bukan `assertDontSee(nama_lembaga)` polos, karena nama lembaga yang sama tetap muncul secara sah di top navbar switcher dan header badge.

---

## 3. Daftar Commit Hash

| Task | Commit Hash | Pesan Commit |
|------|-------------|--------------|
| Task 1 | `674c819a` | `fix(jam-pelajaran): tutup IDOR lintas-lembaga di destroy(), samakan guard dengan edit()/update()` |
| Task 2 | `f6af9cf2` | `feat(pola-jam): tambah scopeHeaderData() + badge scope di header index` |
| Task 3 | `b971c306` | `fix(pola-jam): pill nama lembaga per-kartu hanya tampil saat mode agregat` |
| Task 4 | `3ba2c574` | `feat(pola-jam): nonaktifkan tombol Tambah Pola Jam saat mode agregat, beri hint` |
| Task 5 | `fc6be181` | `chore(pola-jam): hapus halaman mati create/edit (alur nyata 100% lewat modal di index)` |
| Task 6 | `26b722a2` | `feat(pola-jam): confirmDialog() untuk tombol Duplikat + penjelasan eksplisit apa yang disalin` |

---

## 4. Hasil Verifikasi & Regresi

1. **Unit & Feature Test Suite:**
   - `JamPelajaranCrudTest`: **6 passed** (17 assertions)
   - `PolaJamCrudTest`: **35 passed** (100 assertions)
   - Full domain test (`PolaJamCrudTest|JamPelajaranCrudTest|KelasPolaJamTest|DeletePolaJamActionTest|DuplicatePolaJamActionTest|PolaJamSeederTest`): **48 passed** (126 assertions, 0 failures)
   - Permission Seeder regression (`RolePermissionSeederTest`): **8 passed** (171 assertions, 0 failures)

2. **Laravel Pint Code Formatter:**
   - Status: `{"tool":"pint","result":"passed"}` (100% clean formatting)

3. **Verifikasi Manual via Browser Subagent:**
   - **Mode Agregat ("Semua Lembaga"):**
     - Badge header menampilkan "Semua Lembaga" (soft purple/violet).
     - Tombol "+ Tambah Pola Jam" nonaktif (dimmed `opacity-50`) dan menampilkan tooltip arahan.
     - Setiap kartu pola jam memuat pill nama lembaga pemiliknya.
     - Tangkapan layar tersimpan: `pola_jam_aggregate_mode`.
   - **Mode Lembaga Aktif ("SDIT PINTERA"):**
     - Pengalihan via navbar switcher berhasil.
     - Badge header berganti menjadi nama lembaga aktif ("SDIT PINTERA") bergaya brand emerald.
     - Pill nama lembaga per-kartu hilang.
     - Tombol "+ Tambah Pola Jam" aktif kembali; klik membuka modal "Tambah Pola Jam Baru".
     - Tangkapan layar tersimpan: `pola_jam_active_lembaga_modal`.
   - **Tombol Duplikat:**
     - Klik tombol Duplikat menampilkan dialog modal konfirmasi `confirmDialog()` berjudul "Duplikasi Pola Jam?".
     - Teks pesan menjelaskan salinan slot dan menegaskan "Tautan kelas TIDAK ikut disalin".
     - Tangkapan layar tersimpan: `pola_jam_duplicate_confirm_dialog`.
   - **Route Mati:**
     - Navigasi langsung ke `/admin/pola-jam/create` memunculkan 405 MethodNotAllowed (route GET create sudah tidak ada di sistem).
     - Tangkapan layar tersimpan: `pola_jam_dead_route_404`.

---

## 5. Hal yang Perlu Direview Manusia / Claude

- **Git Branch State:** Seluruh pekerjaan berada di branch `akademik-v2`. Sesuai ketentuan instruksi dan aturan proyek, branch ini **TIDAK di-merge** ke `main` / `master` / branch lain. Keputusan merge diserahkan kepada user.
- **Backlog Terpisah:** Bug platform-scope pada TenantScope sengaja tidak disentuh sesuai batasan spec global.
- Semua acceptance criteria telah terpenuhi 100% tanpa catatan terbuka.
