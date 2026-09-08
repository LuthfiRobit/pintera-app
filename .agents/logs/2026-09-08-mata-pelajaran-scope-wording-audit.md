# Handoff Log: Validasi Scope Backend & Kejujuran Wording — Menu Mata Pelajaran

> **Tanggal**: 8 September 2026  
> **Branch**: `rbac-v2`  
> **Spec**: `.agents/specs/2026-09-08-mata-pelajaran-scope-wording-audit.md`  
> **Plan**: `.agents/plans/2026-09-08-mata-pelajaran-scope-wording-audit.md`  
> **Kickoff**: `.agents/kickoff/2026-09-08-mata-pelajaran-scope-wording-audit-kickoff.md`  
> **Base commit sebelum kickoff**: `8fbc081b`  
> **Commit range**: `8fbc081b..507c61be` (7 commit)  
> **Status**: Selesai & Terverifikasi (Pest: 31 test domain mata pelajaran lulus, 81 assertions; MataPelajaranCrudTest: 23 test, 67 assertions; Pint passed)

---

## 1. Apa yang Dikerjakan

Menuntaskan audit backend dan frontend pada menu **Mata Pelajaran** untuk menutup 1 celah keamanan IDOR kritis pada `store()`, 1 guard `create()`, 1 koreksi logic `$isPaud` lintas-scope, serta 2 item frontend (badge scope di 3 view dan kolom Lembaga kondisional pada tabel agregat yayasan).

### Rincian Commit:

1. **Commit `15cd3277` — Kickoff Documentation**
   - Mendokumentasikan basis kickoff di `.agents/kickoff/2026-09-08-mata-pelajaran-scope-wording-audit-kickoff.md`.

2. **Commit `ba622337` — Task 1: Tutup Celah IDOR `store()` + Guard `create()` Tanpa Lembaga Aktif**
   - `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`:
     - Mengadopsi `use ResolveLembagaScopeTrait;` pada class controller (sebelumnya merupakan satu-satunya controller akademik yang belum menggunakan trait ini).
     - Memperbaiki `store()`: mengganti pembacaan mentah `session('active_lembaga_id')` dengan `$this->resolveActiveLembagaId($request->user())`. Ini memvalidasi ulang kepemilikan yayasan dan mencegah tersimpannya data ke lembaga di luar yayasan aktor saat session stale.
     - Menambahkan guard pada `create(Request $request)`: me-redirect kembali ke index mata pelajaran dengan error session `lembaga_id` jika aktor yayasan belum memilih lembaga aktif.
     - Menambahkan helper privat `scopeHeaderData(Request $request): array` untuk menghitung `$isYayasan` dan `$activeLembaga`.
   - `tests/Feature/Admin/MataPelajaranCrudTest.php`:
     - Menambahkan test verifikasi penolakan penyimpanan saat session `active_lembaga_id` stale (milik yayasan lain) dan memastikan row tidak tersimpan.
     - Menambahkan test guard `create()` tanpa lembaga aktif (redirect + error).
     - Menambahkan test form `create()` dapat terbuka saat lembaga aktif sudah di-switch.

3. **Commit `4fe56b7c` — Task 2: Fix `$isPaud` dari Lembaga Aktif + Wiring `scopeHeaderData()` di `index()`**
   - `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`:
     - Memperbaiki perhitungan `$isPaud`: beralih dari `auth()->user()->lembaga?->bentuk_pendidikan` (yang selalu null bagi akun yayasan) ke `Lembaga::find($lembagaAktifId)?->bentuk_pendidikan` berbasis `resolveActiveLembagaId()`.
     - Meng-eager-load relasi `lembaga`: `MataPelajaran::with('lembaga')->orderBy(...)`.
     - Meneruskan `...$this->scopeHeaderData($request)` ke kedua cabang `index()` (halaman penuh dan partial AJAX `_daftar`).
   - `tests/Feature/Admin/MataPelajaranCrudTest.php`:
     - Menambahkan test banner "Catatan untuk PAUD" muncul bagi aktor yayasan saat switch ke lembaga PAUD (TK/KB/TPA/SPS).
     - Menambahkan test banner "Catatan untuk PAUD" tidak muncul saat mode agregat "Semua Lembaga".
     - Menambahkan test verifikasi data scope header diteruskan ke halaman index penuh dan AJAX partial.

4. **Commit `ad5d90d5` — Task 3: Wiring `scopeHeaderData()` ke `edit()`**
   - `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`:
     - Menambahkan parameter `Request $request` pada signature `edit(Request $request, MataPelajaran $mataPelajaran): View`.
     - Meneruskan `...$this->scopeHeaderData($request)` ke view edit.
   - `tests/Feature/Admin/MataPelajaranCrudTest.php`:
     - Menambahkan test view edit menerima `$isYayasan` dan `$activeLembaga`.

5. **Commit `f89486d8` — Task 4: Badge Scope Yayasan/Lembaga di Index, Create, dan Edit**
   - `resources/views/portals/lembaga/akademik/mata-pelajaran/index.blade.php`:
     - Menambahkan badge scope di header: badge ungu `Semua Lembaga` untuk agregat yayasan atau badge brand nama lembaga aktif saat di-switch.
   - `resources/views/portals/lembaga/akademik/mata-pelajaran/create.blade.php`:
     - Menambahkan badge brand dengan `$activeLembaga->nama` (selalu nama lembaga aktif, tanpa varian ungu karena guard A.2).
   - `resources/views/portals/lembaga/akademik/mata-pelajaran/edit.blade.php`:
     - Menambahkan badge brand dengan `$mataPelajaran->lembaga->nama` (lembaga pemilik record).
   - `tests/Feature/Admin/MataPelajaranCrudTest.php`:
     - Menambahkan 3 test badge untuk halaman index, create, dan edit.

6. **Commit `e6d7a3f0` — Task 5: Kolom Lembaga di Tabel saat Mode "Semua Lembaga"**
   - `resources/views/portals/lembaga/akademik/mata-pelajaran/_daftar.blade.php`:
     - Menambahkan kolom header `<th>Lembaga</th>` dan sel `<td>{{ $mapel->lembaga->nama ?? '-' }}</td>` khusus saat mode agregat yayasan (`$isYayasan && !$activeLembaga`).
     - Mengubah `colSpan` empty-state tabel menjadi dinamis (`8` saat kolom lembaga aktif, `7` saat mode lembaga spesifik).
   - `tests/Feature/Admin/MataPelajaranCrudTest.php`:
     - Menambahkan test kemunculan header dan sel kolom Lembaga saat kode mapel duplikat di 2 lembaga berbeda.
     - Menambahkan test kolom Lembaga disembunyikan saat switch ke lembaga aktif.

7. **Commit `507c61be` — Task 6: Update Plan Checklist Selesai Seluruh Task**
   - `.agents/plans/2026-09-08-mata-pelajaran-scope-wording-audit.md`:
     - Memperbarui seluruh checkbox langkah implementasi Task 1 s/d 6 menjadi `[x]`.

---

## 2. Keputusan Penting yang Diambil

1. **Penutupan Celah Keamanan IDOR pada `store()`**:
   - `CreateMataPelajaranAction` mempercayai `lembaga_id` yang dikirim controller tanpa guard lintas-lembaga kedua (berbeda dari `CreateKelasAction` yang memiliki pengecekan 404). Oleh karena itu, pengamanan di controller melalui `resolveActiveLembagaId()` yang memvalidasi `Lembaga::where('id', $id)->where('yayasan_id', $yayasanId)->exists()` adalah pertahanan mutlak untuk mencegah kebocoran data multi-tenant.
2. **Kalkulasi `$isPaud` Mandiri dari `scopeHeaderData()`**:
   - Helper `scopeHeaderData()` secara desain hanya mengembalikan `$activeLembaga` untuk aktor berscope yayasan (menghasilkan `null` untuk aktor lembaga-scope karena badge tidak dibutuhkan oleh mereka).
   - Agar banner PAUD bekerja benar bagi **kedua jenis aktor** (baik staf lembaga PAUD maupun admin yayasan yang switch ke lembaga PAUD), `$isPaud` dihitung langsung dari `resolveActiveLembagaId()` + `Lembaga::find()`, tidak bergantung pada isi `activeLembaga` milik `scopeHeaderData()`.
3. **Penyelarasan Assertion Test Badge Create**:
   - Pada pengujian halaman `create`, string `"Semua Lembaga"` selalu hadir di HTML karena dirender oleh switcher global di navbar/layout. Pengujian badge create diselaraskan mengikuti pola `KelasCrudTest.php`: memverifikasi `assertSee($lembaga->nama)` dan `assertDontSee('border-purple-200')` (karena badge ungu adalah varian eksklusif agregat).
4. **Form Mata Pelajaran Tidak Memerlukan Scoping Dropdown Relasi**:
   - Form mata pelajaran (`_form.blade.php`) hanya berisi input teks (`kode`, `nama`, `no_urut`) dan enum (`tipe`, `kelompok`, `status`), tanpa foreign key dropdown dinamis (seperti Guru/Tahun Ajaran/Pola Jam pada Kelas). Oleh karena itu, tidak ada task scoping dropdown seperti pada audit Kelas.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Git State**:
   - Branch: `rbac-v2` (ahead 37 commit dari `origin/rbac-v2`).
   - Perubahan belum di-merge ke `main` dan belum di-push ke remote repository (menunggu keputusan rilis pengguna).
2. **Wording Empty-State Filter Tabel (Backlog Non-Mendesak)**:
   - Saat ini pesan tabel kosong selalu bertuliskan `"Belum ada mata pelajaran yang didaftarkan."`, baik ketika memang belum ada data sama sekali maupun saat pencarian/filter tidak menemukan kecocokan. Ini murni UX wording umum dan di luar cakupan scope audit yayasan/lembaga.
3. **Integritas `UpdateMataPelajaranAction`**:
   - Method `update()` dan Action pendukungnya tidak disentuh karena kolom `lembaga_id` bersifat immutable dan route-model-binding telah diproteksi oleh `TenantScope`.
