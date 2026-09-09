# Handoff Log: Audit & Perbaikan Scope Lembaga Menu TP (Komponen Penilaian)

**Tanggal:** 2026-09-09  
**Branch:** `rbac-v2` (Clean working tree, commit ahead of `origin/rbac-v2` by 9 commits, belum di-merge dan belum di-push sesuai instruksi)  
**Dokumen Acuan:**
- Spec: [.agents/specs/2026-09-09-tp-scope-lembaga-perbaikan.md](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-09-tp-scope-lembaga-perbaikan.md)
- Plan: [.agents/plans/2026-09-09-tp-scope-lembaga-perbaikan.md](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-09-tp-scope-lembaga-perbaikan.md)

---

## 1. Apa yang Dikerjakan

Penyelesaian menyeluruh terhadap seluruh 6 task pada rencana implementasi audit ulang scope lembaga menu TP (Tujuan Pembelajaran / Komponen Penilaian) sisi Admin, dipicu oleh pertanyaan user *"tambah dan edit belum terkunci switch lembaga?"*.

Audit menemukan 7 item (Item A–G) terkait kebocoran/ambiguitas scope lembaga saat aktor yayasan berada di mode agregat ("Semua Lembaga"). Seluruh perbaikan telah diselesaikan secara berurutan, teruji dengan TDD, lolos linting Pint, dan diverifikasi visual di browser:

### Item A (Task 1) — 🔴 Guard "Tambah TP" Wajib Switch ke 1 Lembaga Aktif
- **Latar Belakang:**  
  Sebelumnya, tombol "+ Tambah TP Baru" dan "+ Tambah TP Pertama" dapat diakses oleh aktor yayasan dalam mode agregat ("Semua Lembaga"). Query `MataPelajaran::orderBy('nama')->get()` di `create()` memuat seluruh mata pelajaran dari seluruh lembaga yayasan tanpa pembeda lembaga, dan default tahun ajaran memilih acak baris pertama di database.
- **Implementasi:**  
  1. Pada `KomponenPenilaianController::create()` dan `KomponenPenilaianController::store()`, menambahkan guard:
     ```php
     if ($request->user()->widestScopeLevel() === 'yayasan') {
         abort_if($this->resolveActiveLembagaId($request->user()) === null, 422, 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah TP.');
     }
     ```
  2. Pada `_daftar.blade.php`, membungkus tombol "+ Tambah TP Baru" dan "+ Tambah TP Pertama" dengan `@if (! ($isYayasan ?? false) || ($activeLembaga ?? null))`. Pada blok `@empty`, bila dalam mode agregat, ditambahkan teks penjelas: *"Pilih 1 lembaga lewat pengalih di topbar untuk mulai menambah TP."*.
- **Commit:** `0ba10ff6`

### Item B (Task 2) — 🟡 `store()` Gagal dengan Pesan Validasi (Bukan 404 Kosong)
- **Latar Belakang:**  
  Bila payload `store()` mengandung `subjek_id` dan `semester_id` yang tidak cocok lembaganya (atau salah satu null/tidak ditemukan), controller sebelumnya memicu `abort_if(..., 404)`. Hal ini menyebabkan input form user (kode, deskripsi, bobot, KKTP) hilang tanpa penjelasan.
- **Implementasi:**  
  Mengganti abort 404 di `KomponenPenilaianController::store()` dengan `back()->withInput()->withErrors(...)`:
  - Jika subjek atau semester null: `'Subjek Penilaian atau Semester yang dipilih tidak valid.'`
  - Jika mapel dan semester beda lembaga: `'Mata Pelajaran dan Semester yang dipilih berasal dari lembaga yang berbeda.'`
- **Commit:** `85644c55`

### Item C (Task 3) — 🟡 Default Tahun Ajaran di `index()` Tidak Lagi Ambigu Saat Mode Agregat
- **Latar Belakang:**  
  Pada kunjungan pertama ke halaman index tanpa parameter URL, `tahunAjaranId` otomatis mengambil `TahunAjaran::where('status_aktif', true)->value('id')`. Untuk yayasan di mode agregat (badge "Semua Lembaga"), sistem secara diam-diam menyempitkan daftar TP ke 1 lembaga pemilik tahun ajaran aktif tersebut, sehingga TP lembaga lain tidak tampak.
- **Implementasi:**  
  Pada `KomponenPenilaianController::index()`, menambahkan kondisi `$isYayasanAggregate`:
  ```php
  $isYayasanAggregate = $request->user()->widestScopeLevel() === 'yayasan' && $this->resolveActiveLembagaId($request->user()) === null;

  $tahunAjaranId = $request->query('tahun_ajaran_id');
  if ($tahunAjaranId === null && ! $request->query->has('tahun_ajaran_id') && ! $isYayasanAggregate) {
      $tahunAjaranId = TahunAjaran::where('status_aktif', true)->value('id');
  }
  ```
  Saat mode agregat, `$tahunAjaranId` tetap `null` sehingga daftar TP memuat data seluruh lembaga di bawah yayasan.
- **Commit:** `afbf02e8`

### Item D (Task 4) — 🟡 Label Lembaga di Baris Daftar & Kartu Kalkulator Bobot
- **Latar Belakang:**  
  Saat melihat daftar TP lintas-lembaga di mode agregat, pengguna tidak tahu baris TP atau kartu live calculator bobot 100% milik lembaga mana.
- **Implementasi:**  
  1. Pada `KomponenPenilaianController::index()`, menambahkan eager-loading `'lembaga'` pada query `$komponenList`.
  2. Meneruskan `...$this->scopeHeaderData($request)` ke cabang respon AJAX `_daftar.blade.php`.
  3. Pada `_daftar.blade.php`:
     - Kartu Live Calculator: menampilkan nama lembaga di samping nama semester bila `($isYayasan && ! $activeLembaga)`.
     - Setiap baris TP: menambahkan badge `<x-badge tone="slate">{{ $komponen->lembaga->nama ?? '-' }}</x-badge>` bila mode agregat.
- **Commit:** `25958951`

### Item E & F (Task 5) — 🟡🟢 Bersihkan Query Mati di `edit()` + Badge Lembaga di Header Edit
- **Latar Belakang:**  
  Method `edit()` sebelumnya memuat `mataPelajaranList`, `elemenCpList`, `semesterList`, dan `bentukPendidikan` dari database, padahal form Edit sudah mengunci permanen Subjek & Semester sejak perbaikan sebelumnya (menampilkan teks read-only). Selain itu, saat mengedit TP dari mode agregat, tidak ada informasi lembaga pemilik TP tersebut di header.
- **Implementasi:**  
  1. Mengubah signature method menjadi `public function edit(Request $request, KomponenPenilaian $komponenPenilaian): View`.
  2. Menghapus 4 query yang tidak terpakai dan menambahkan `...$this->scopeHeaderData($request)` serta eager-load `lembaga`.
  3. Pada `edit.blade.php`, menambahkan badge lembaga di header samping judul:
     ```blade
     @if (($isYayasan ?? false) && ! ($activeLembaga ?? null))
         <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
             <x-icon name="apartment" class="h-3.5 w-3.5" />
             {{ $komponenPenilaian->lembaga->nama ?? '-' }}
         </span>
     @endif
     ```
- **Commit:** `6aa40389`

### Task 6 — Regresi Penuh, Pint, & Verifikasi Browser
- Menjalankan seluruh test feature Komponen Penilaian: 69 test lulus (Admin 60 test, Guru 9 test).
- Menjalankan `vendor/bin/pint --dirty --format agent`: `{"tool":"pint","result":"passed"}`.
- Memperbarui status checklist pada plan file.
- **Commit:** `dfd43c79`

---

## 2. Keputusan Penting yang Diambil

1. **Guard Total pada Tambah TP (Item A):**  
   Meniru pola preseden yang sudah matang di `AttendanceQrScanController` (Scan QR Kehadiran SDM). Alih-alih merombak form `create` menjadi rumit dengan label per-lembaga pada 3 dropdown dan refetch AJAX dinamis, kita memilih guard total (wajib switch ke 1 lembaga). Begitu guard ini lolos, `session('active_lembaga_id')` tervalidasi terisi dan `TenantScope` otomatis membatasi seluruh model (`MataPelajaran`, `TahunAjaran`, `Semester`) ke 1 lembaga aktif tanpa perlu filter manual `where('lembaga_id', ...)`.
2. **Perbedaan Desain Task 1 vs Task 3:**  
   Task 1 (`create`/`store`) di-guard total (blokir akses) karena pembuatan record baru wajib terikat pada satu lembaga definitif. Sebaliknya, Task 3 (`index`) tetap diizinkan diakses dalam mode agregat karena fungsi melihat/monitoring data lintas lembaga adalah fitur sah bagi yayasan; hanya default tahun ajarannya yang dinetralkan agar tidak diam-diam memfilter ke 1 lembaga acak.
3. **Edit Tidak Di-guard Sama Seperti Create:**  
   TP yang diedit sudah pasti terikat 1 lembaga spesifik lewat route-model-binding. Karena Subjek & Semester sudah dikunci permanen, tidak ada celah untuk salah asosiasi lembaga lewat form Edit. Oleh karena itu, Edit hanya diberikan badge konteks lembaga (Item F) dan pembersihan query mati (Item E).
4. **Item G Dibiarkan Sesuai Spec:**  
   Filter Mata Pelajaran pada `index()` mode agregat tetap dibiarkan flat sesuai keputusan arsitektur di spec (tidak dirombak menjadi optgroup).
5. **Penyesuaian Asersi Test Lama pada Task 2:**  
   Test eksisting `it('rejects creating a komponen penilaian mixing a mata pelajaran and semester from different lembaga')` sebelumnya mengasumsikan kode status `404`. Karena Item B secara sadar mengubah perilaku ini menjadi redirect kembali dengan error validasi (`assertRedirect()->assertSessionHasErrors('subjek_id')`), asersi test lama tersebut disesuaikan agar selaras dengan kontrak baru.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Git State Saat Ini:**  
   - Branch: `rbac-v2`
   - Working tree: Clean (hanya file untracked log internal `storage/debugbar/`).
   - Ahead by: 9 commit dari `origin/rbac-v2`.
   - Status Merge/Push: Sesuai batasan operasional, branch **TIDAK di-merge** dan **BELUM di-push** ke remote repository. Keputusan merge/push diserahkan kepada user.
2. **Jalur Guru:**  
   - Controller `App\Http\Controllers\Guru\KomponenPenilaianController` dan view-nya sama sekali tidak tersentuh. Seluruh 9 test di `tests/Feature/Guru/KomponenPenilaianControllerTest.php` telah dijalankan dan lulus 100%.
3. **Verifikasi Browser Langsung:**  
   - Telah diverifikasi langsung pada aplikasi berjalan:
     - Akses langsung ke URL `http://127.0.0.1:8000/admin/komponen-penilaian/create` dalam mode agregat berhasil mengembalikan HTTP 422 dengan pesan panduan pengalihan lembaga yang tepat.
     - Halaman index `http://127.0.0.1:8000/admin/komponen-penilaian` dalam mode agregat menampilkan badge lembaga pada baris TP dan kartu kalkulator live bobot.
