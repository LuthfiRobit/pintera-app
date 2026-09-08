# Handoff Log: Validasi Scope Backend & Kejujuran Wording — Menu Kelas

> **Tanggal**: 8 September 2026  
> **Branch**: `rbac-v2`  
> **Spec**: `.agents/specs/2026-09-08-kelas-scope-wording-audit.md`  
> **Plan**: `.agents/plans/2026-09-08-kelas-scope-wording-audit.md`  
> **Kickoff**: `.agents/kickoff/2026-09-08-kelas-scope-wording-audit-kickoff.md`  
> **Base commit sebelum kickoff**: `2215aa0f`  
> **Commit range**: `2215aa0f..59dca3f1` (11 commit)  
> **Status**: Selesai & Terverifikasi (Pest: 54 test kelas lulus, 142 assertions; Pint passed; Browser automation verified)

---

## 1. Apa yang Dikerjakan

Menuntaskan audit backend dan frontend pada menu **Kelas** untuk menutup 2 gap backend nyata (guard `create()` dan scoping eksplisit dropdown target lembaga), 3 gap frontend (badge scope, kolom Lembaga di tabel agregat, label filter Tahun Ajaran), serta 3 perbaikan susulan berdasarkan review visual user (integrasi searchable TomSelect wali kelas, uncrop dropdown, dan perbaikan scrolling sidebar layout).

### Rincian Commit:

1. **Commit `f9ba84fe` — Kickoff Documentation**
   - Mendokumentasikan basis panduan implementasi di `.agents/kickoff/2026-09-08-kelas-scope-wording-audit-kickoff.md`.

2. **Commit `30a631bb` — Pendaftaran SVG Inline Icon Global**
   - `resources/views/components/icon.blade.php`:
     - Mendaftarkan icon `group_work`, `person_apron`, serta alias `badge` untuk mencegah placeholder tanda tanya `(?)` pada antarmuka.

3. **Commit `f1d4f4cf` — Task 1: Guard `create()` + Scoping Eksplisit Dropdown ke Lembaga Aktif**
   - `app/Http/Controllers/Admin/KelasController.php`:
     - Menambahkan guard pada `create(Request $request)`: jika aktor yayasan belum memilih lembaga aktif (`$lembagaId === null`), redirect kembali ke `admin.kelas.index` dengan error session `lembaga_id`.
     - Scoping eksplisit dropdown `tahunAjaranList`, `guruList`, dan `polaJamList` di method `create()` menggunakan `withoutGlobalScope(TenantScope::class)->where('lembaga_id', $lembagaId)`.
     - Menambahkan helper privat `scopeHeaderData(Request $request): array` yang menghitung `$isYayasan` dan `$activeLembaga`.
   - `tests/Feature/Admin/KelasCrudTest.php`:
     - Menambahkan test guard `create()` tanpa lembaga aktif (redirect + error).
     - Menambahkan test buka form `create()` dengan lembaga aktif (`assertOk()`).
     - Menambahkan test opsi Tahun Ajaran hanya milik lembaga aktif.

4. **Commit `82050dc2` — Task 2: Scoping Eksplisit Dropdown `edit()` ke Lembaga Pemilik Kelas**
   - `app/Http/Controllers/Admin/KelasController.php`:
     - Mengubah signature method `edit(Request $request, Kelas $kelas): View`.
     - Scoping eksplisit dropdown `tahunAjaranList`, `guruList`, dan `polaJamList` di method `edit()` menggunakan `withoutGlobalScope(TenantScope::class)->where('lembaga_id', $kelas->lembaga_id)`.
     - Meneruskan `...$this->scopeHeaderData($request)` ke view edit.
   - `tests/Feature/Admin/KelasCrudTest.php`:
     - Menambahkan test verifikasi bahwa di mode "Semua Lembaga", dropdown `edit()` hanya menampilkan opsi milik lembaga pemilik kelas (`$kelas->lembaga_id`).

5. **Commit `81ad26af` — Task 3: Wiring `scopeHeaderData()` & Eager-Load Relasi `lembaga` di `index()`**
   - `app/Http/Controllers/Admin/KelasController.php`:
     - Meng-eager-load relasi `lembaga` pada query index: `Kelas::with(['tahunAjaran', 'waliKelas', 'lembaga'])`.
     - Meng-eager-load relasi `lembaga` pada filter dropdown: `TahunAjaran::with('lembaga')`.
     - Meneruskan `...$this->scopeHeaderData($request)` ke KEDUA cabang `index()` (halaman penuh dan partial AJAX `_daftar`).
   - `tests/Feature/Admin/KelasCrudTest.php`:
     - Menambahkan test verifikasi bahwa view index penuh dan AJAX partial keduanya menerima `$isYayasan` dan `$activeLembaga`.

6. **Commit `4a8c47dc` — Task 4: Badge Scope Yayasan/Lembaga di Index, Create, dan Edit**
   - `resources/views/admin/kelas/index.blade.php`:
     - Menambahkan badge scope di header: badge ungu `Semua Lembaga` untuk agregat yayasan, atau badge brand nama lembaga saat aktif di-switch.
   - `resources/views/admin/kelas/create.blade.php`:
     - Menambahkan badge brand dengan `$activeLembaga->nama` (selalu nama lembaga aktif, tidak pernah varian ungu karena guard A.1).
   - `resources/views/admin/kelas/edit.blade.php`:
     - Menambahkan badge brand dengan nama lembaga pemilik kelas (`$kelas->lembaga->nama`).
   - `tests/Feature/Admin/KelasCrudTest.php`:
     - Menambahkan 3 test badge untuk halaman index, create, dan edit.

7. **Commit `09839d25` — Task 5: Kolom Lembaga di Tabel & Label Lembaga di Filter Index**
   - `resources/views/admin/kelas/_daftar.blade.php`:
     - Menambahkan kolom header `<th>Lembaga</th>` dan sel `<td>{{ $kelas->lembaga->nama ?? '-' }}</td>` khusus saat mode "Semua Lembaga" (`$isYayasan && !$activeLembaga`).
     - Menghitung atribut `colspan` empty-state secara dinamis (`5` saat mode "Semua Lembaga", `4` saat mode lembaga spesifik).
   - `resources/views/admin/kelas/index.blade.php`:
     - Menambahkan suffix `— {{ $ta->lembaga->nama }}` pada opsi filter dropdown Tahun Ajaran saat berada dalam mode "Semua Lembaga".
   - `tests/Feature/Admin/KelasCrudTest.php`:
     - Menambahkan test kemunculan kolom Lembaga di mode agregat dan penyembunyiannya di mode switch.
     - Menambahkan test suffix nama lembaga pada opsi filter Tahun Ajaran.

8. **Commit `e990cd0b` — Pint Formatting**
   - Menyelaraskan formatting `tests/Feature/Admin/KelasCrudTest.php` sesuai standar Laravel Pint proyek.

9. **Commit `d8bcbbd7` — Feedback User: Searchable TomSelect Wali Kelas**
   - `resources/views/admin/kelas/_form.blade.php`:
     - Mengganti elemen native `<select>` wali kelas menjadi searchable select menggunakan helper standar proyek: `window.tomSelectPegawai($refs.waliKelasSelect, ...)`.
     - Mendukung pencarian real-time berdasarkan nama, NIP, NUPTK, dan jabatan PTK.
   - `tests/Feature/Admin/KelasCrudTest.php`:
     - Menambahkan test verifikasi inisialisasi `tomSelectPegawai` pada form create dan pre-selection wali kelas pada form edit.

10. **Commit `f67fe8a0` — Feedback User: Uncrop Dropdown TomSelect**
    - `resources/views/admin/kelas/_form.blade.php`:
      - Menghapus class `overflow-hidden` pada container kartu utama form agar dropdown popup tidak terpotong tepi bawah kartu.
      - Menambahkan `rounded-t-2xl` pada header form kartu dan `relative z-20` pada kontainer field wali kelas.
    - `resources/views/admin/kelas/create.blade.php` & `edit.blade.php`:
      - Menambahkan padding bawah `pb-16` untuk kenyamanan ruang scroll.

11. **Commit `59dca3f1` — Feedback User: Sidebar Desktop Fixed Layout & Window Scroll Isolation**
    - `resources/views/layouts/sidebar.blade.php`:
      - Mengubah posisi desktop `<aside>` dari `lg:sticky lg:top-0` menjadi `lg:fixed lg:inset-y-0 lg:left-0 lg:z-40 lg:h-screen`, sehingga posisi sidebar terkunci penuh di viewport dan tidak ikut terdorong/terseret saat user men-scroll halaman ke bawah.
      - Menambahkan elemen invisible spacer `<div class="hidden shrink-0 transition-all duration-300 ease-out lg:block" :class="{ 'w-0': sidebarCollapsed, 'w-72': !sidebarCollapsed }" aria-hidden="true"></div>` tepat di samping aside untuk mempertahankan slot lebar `w-72` di dalam container `lg:flex`.
      - Mengisolasi scroll menu aktif sidebar: mengganti `activeItem.scrollIntoView({ block: 'center' })` (yang secara agresif men-scroll seluruh document window saat page load) menjadi `$el.scrollTop = Math.max(0, activeItem.offsetTop - ($el.clientHeight / 2));` pada container `<nav>`.
    - `resources/css/app.css`:
      - Menambahkan aturan `.ts-dropdown { z-index: 50; }` agar menu opsi TomSelect melayang di atas card maupun elemen sekitar.

---

## 2. Keputusan Penting yang Diambil

1. **Pemisahan Sumber Lembaga: `create()` vs `edit()`**:
   - Pada `create()`, target lembaga diambil dari sesi aktor yang sedang aktif melalui `resolveActiveLembagaId($request->user())`. Jika belum switch lembaga, form tidak dibuka sama sekali (redirect back dengan error).
   - Pada `edit()`, target lembaga diambil dari `$kelas->lembaga_id` (record pemilik). Hal ini memastikan aktor yayasan dalam mode "Semua Lembaga" dapat mengedit kelas di lembaga manapun dan tetap memperoleh opsi dropdown (Tahun Ajaran, Wali Kelas, Pola Jam) yang tepat milik lembaga tersebut, tanpa tergantung status switcher session.
2. **`CreateKelasAction` & `UpdateKelasAction` Dipertahankan Utuh**:
   - Sesuai prinsip arsitektur, kedua Action ini sudah aman (menghasilkan 404 jika ada ID relasi lintas-lembaga). Perubahan difokuskan murni pada lapisan Controller & Form untuk mencegah input tidak valid sedini mungkin.
3. **Penyertaan `scopeHeaderData()` pada Cabang AJAX Partial**:
   - Karena tabel `_daftar.blade.php` di-load ulang secara asynchronous melalui AJAX setiap kali filter atau pagination berubah, data scope `$isYayasan` dan `$activeLembaga` wajib disertakan pada kedua cabang `index()`.
4. **Colspan Dinamis Empty-State**:
   - `colspan` baris kosong dihitung dinamis: bernilai `5` saat kolom Lembaga aktif (mode agregat yayasan), dan `4` saat kolom Lembaga disembunyikan (mode switch atau lembaga-scope), menjaga presisi visual tabel.
5. **Transisi Sidebar Desktop ke `fixed` dengan Flex Spacer**:
   - CSS `sticky` memiliki keterbatasan inheren di mana batas bawah flex parent container akan menyeret elemen sticky jika konten utama selesai di-scroll. Mengubahnya menjadi `fixed` ditambah flex spacer memberikan stabilitas total pada sidebar saat halaman konten utama di-scroll, dengan animasi collapse/expand yang tetap halus.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

1. **Git State & Push**:
   - Branch: `rbac-v2` (ahead 26 commit dari `origin/rbac-v2`).
   - Sesuai instruksi dan panduan proyek, perubahan di branch `rbac-v2` **tidak di-merge ke `main`** dan **tidak di-push secara otomatis** tanpa persetujuan langsung dari user.
2. **Validasi FormRequest ID Relasi**:
   - Rule validasi `tahun_ajaran_id`, `wali_kelas_guru_id`, dan `pola_jam_id` pada `StoreKelasRequest` / `UpdateKelasRequest` saat ini bertipe `integer`. Integritas lintas lembaga diamankan secara preventif oleh controller scoping dan action guard. Jika diperlukan di masa depan, dapat ditambahkan rule `Rule::exists(...)` dengan batasan `lembaga_id`.
3. **Data Legacy Hipotetis**:
   - Jika terdapat data kelas lama di database yang dibuat sebelum adanya guard lintas-lembaga dan memiliki `wali_kelas_guru_id` dari lembaga yang berbeda, opsi tersebut tidak akan muncul terseleksi di dropdown `edit()` karena scoping ketat. Hal ini adalah perilaku yang diinginkan (mencegah persistensi data tidak valid).
