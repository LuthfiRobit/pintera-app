# Handoff Log: Badge Scope & Kejujuran Wording — Menu Tahun Ajaran

> **Tanggal**: 8 September 2026  
> **Branch**: `rbac-v2`  
> **Spec**: `.agents/specs/2026-09-08-tahun-ajaran-scope-wording-audit.md`  
> **Plan**: `.agents/plans/2026-09-08-tahun-ajaran-scope-wording-audit.md`  
> **Kickoff**: `.agents/kickoff/2026-09-08-tahun-ajaran-scope-wording-audit-kickoff.md`  
> **Base commit sebelum kickoff**: `34b2d410`  
> **Commit range**: `ec729561..c4051786` (5 commit)  
> **Status**: Selesai & Terverifikasi (Suite: 35 test lulus, 90 assertions, Pint passed)

---

## 1. Apa yang Dikerjakan

Menuntaskan audit frontend pada menu **Tahun Ajaran** untuk memastikan informasi yang disajikan konkret, jujur, dan selaras dengan perilaku backend `TenantScope` dan `TahunAjaran::activate()` tanpa mengubah lapisan backend sama sekali.

### Rincian Commit:

1. **Commit `ec729561` — Task 1: Controller — `scopeHeaderData()` + Eager Loading Relasi `lembaga`**
   - `app/Http/Controllers/Admin/TahunAjaranController.php`:
     - Menambahkan private method `scopeHeaderData(Request $request): array` yang menghitung `$isYayasan` dan `$activeLembaga` menggunakan `resolveActiveLembagaId($request->user())` dari `ResolveLembagaScopeTrait`.
     - Meng-eager-load relasi `lembaga` pada query index: `TahunAjaran::with(['semester', 'lembaga'])->get()`.
   - `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`:
     - Menambahkan 3 test pengujian data scope header (`isYayasan=true` & `activeLembaga=null` pada mode agregat, `activeLembaga` terisi saat switch lembaga, dan `isYayasan=false` untuk aktor lembaga-scope).

2. **Commit `587951f2` — Task 2: View Index — Badge Scope Header, Label Lembaga per Kartu, Wording Dialog**
   - `resources/views/admin/tahun-ajaran/index.blade.php`:
     - Menambahkan badge scope di header halaman: badge ungu `Semua Lembaga` untuk agregat yayasan, atau badge brand nama lembaga aktif saat di-switch.
     - Menambahkan label nama lembaga (`apartment` icon + `$ta->lembaga->nama`) pada kartu TA khusus saat berada di mode agregat yayasan ("Semua Lembaga").
     - Memperjelas teks konfirmasi aktivasi TA: `"Aktifkan {{ $ta->nama }}? Tahun Ajaran lain di lembaga {{ $ta->lembaga->nama ?? 'ini' }} akan dinonaktifkan (tidak memengaruhi lembaga lain)."`.
   - `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`:
     - Menambahkan test badge header, test label lembaga per-kartu saat 2 lembaga berbagi nama TA yang sama, dan test penyebutan nama lembaga pada dialog aktivasi.

3. **Commit `3e110251` — Task 3: View Modal — Konteks Lembaga Tujuan + Fix Ikon `calendar_month`**
   - `resources/views/admin/tahun-ajaran/_modal-tahun-ajaran.blade.php`:
     - Mengganti ikon `date_range` (yang belum terdaftar di komponen `<x-icon>` sehingga jatuh ke placeholder tanda tanya) menjadi `calendar_month`.
     - Menambahkan konteks lembaga tujuan di header modal: teks info nama lembaga jika aktif, atau pesan peringatan bagi yayasan yang belum memilih lembaga aktif.
   - `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`:
     - Menambahkan 4 test untuk konteks lembaga modal dan verifikasi render ikon `calendar_month`.

4. **Commit `cf3b1e31` — Task 4: Hapus Dead Code Halaman `admin.tahun-ajaran.create`**
   - Verifikasi grep memastikan tidak ada rujukan ke route `admin.tahun-ajaran.create` di seluruh codebase.
   - `routes/admin/akademik-master.php`: Menghapus baris route `admin.tahun-ajaran.create`.
   - `app/Http/Controllers/Admin/TahunAjaranController.php`: Menghapus method `create(): View`.
   - `resources/views/admin/tahun-ajaran/create.blade.php`: Menghapus file view mati (`git rm`).
   - *Catatan:* Permission `tahun-ajaran.create` tetap dipertahankan karena dipakai otorisasi `store()`, `update()`, dan `@can` Blade.
   - `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`:
     - Menambahkan test assert `RouteNotFoundException` saat route `admin.tahun-ajaran.create` dipanggil.

5. **Commit `c4051786` — Susulan Feedback User: Daftarkan Ikon Hilang & Standardisasi `confirmDialog`**
   - `resources/views/components/icon.blade.php`:
     - Mendaftarkan SVG inline untuk ikon yang belum terdaftar: `edit_square`, `play_circle`, `data_table`, `view_timeline`, `calendar_today`, serta `remove`, `download`, dan `rotate_right` (mencegah placeholder tanda tanya pada global modal).
   - `resources/views/admin/tahun-ajaran/index.blade.php`:
     - Mengganti dialog native browser `onsubmit="return confirm(...)"` pada form aktivasi Tahun Ajaran menjadi modal dialog standar Pintera: `x-data @submit.prevent="confirmDialog(...)"`.
     - Menerapkan konfirmasi standar `confirmDialog(...)` yang sama pada aktivasi Semester Ganjil dan Semester Genap.
   - `tests/Feature/Admin/TahunAjaranSemesterPanelTest.php`:
     - Memperbarui test konfirmasi aktivasi agar memverifikasi pemanggilan `confirmDialog`.
     - Menambahkan test yang memastikan tidak ada lagi placeholder tanda tanya (`M9.5 9a2.5 2.5 0 0 1 4.6-1.4`) pada halaman yang dirender.

---

## 2. Keputusan Penting yang Diambil

1. **Wajib Memakai `resolveActiveLembagaId()` BUKAN `resolveLembagaId()`**:
   - `resolveLembagaId()` pada `ResolveLembagaScopeTrait` melempar `abort(422)` bila aktor yayasan belum memilih lembaga aktif. Untuk halaman agregat "Semua Lembaga", kita membutuhkan `resolveActiveLembagaId()` yang mengembalikan `null` secara aman sehingga badge scope dapat tampil tanpa error HTTP 422.
2. **Permission `tahun-ajaran.create` Tidak Dihapus dari Seeder**:
   - Penghapusan hanya dilakukan pada route GET, controller method `create()`, dan file Blade `create.blade.php`. Permission `tahun-ajaran.create` di seeder tetap dipertahankan karena digunakan otorisasi `store()`, `update()`, dan proteksi tombol di UI via `@can`.
3. **Pendaftaran Ikon Langsung di Komponen `<x-icon>` Global**:
   - Alih-alih hanya merename pemanggilan ikon di satu view `index.blade.php`, kami mendaftarkan ikon-ikon Material Symbols (`play_circle`, `edit_square`, `data_table`, `view_timeline`, dll.) ke dalam `resources/views/components/icon.blade.php`. Hal ini menyembuhkan bug placeholder tanda tanya `(?)` secara permanen untuk view mana pun yang menggunakannya.
4. **Standardisasi Konfirmasi Menggunakan Modal `confirmDialog`**:
   - Menghilangkan `window.confirm()` browser native dan beralih penuh ke Alpine store `confirmDialog()` yang memicu komponen `<x-confirm-dialog />` bawaan layout aplikasi.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Branch `rbac-v2` Tetap Tidak Di-merge ke `main`**:
   - Sesuai arahan, branch `rbac-v2` belum di-merge ke `main` karena membawa akumulasi perubahan dari sesi-sesi sebelumnya yang memerlukan keputusan rilis tersendiri oleh user.
2. **Scoping `Semester::activate()` di Luar Scope**:
   - Saat audit dicatat bahwa method `Semester::activate()` hanya memeriksa `where('lembaga_id', $this->lembaga_id)` dan tidak menyertakan `tahun_ajaran_id`. Kondisi ini saat ini aman karena invariant "1 TA aktif per lembaga" dijaga oleh `TahunAjaran::activate()`, dan UI hanya membuka tombol aktivasi semester untuk TA yang aktif. Jika kelak invariant ini berubah, perbaikan scoping backend semester dapat diaudit terpisah.
3. **Status Git Terkini**:
   - Branch: `rbac-v2`
   - Status: Bersih (`git status` clean), ahead 12 commit terhadap `origin/rbac-v2`.
