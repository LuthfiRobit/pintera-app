# Handoff Log: Audit & Perbaikan Menu RPP (Workflow, Wording, Backend)

**Tanggal:** 2026-09-09  
**Branch:** `akademik-v2` (Clean, commit ahead of `origin/rbac-v2` by 70 commits, belum dimerge ke manapun sesuai instruksi)  
**Dokumen Acuan:**
- Spec: [.agents/specs/2026-09-09-rpp-audit-workflow-perbaikan.md](file:///d:/laragon/www/pintera-app/.agents/specs/2026-09-09-rpp-audit-workflow-perbaikan.md)
- Plan: [.agents/plans/2026-09-09-rpp-audit-workflow-perbaikan.md](file:///d:/laragon/www/pintera-app/.agents/plans/2026-09-09-rpp-audit-workflow-perbaikan.md)

---

## 1. Apa yang Dikerjakan

Penyelesaian tuntas seluruh 10 task pada rencana implementasi audit mendalam menu RPP (Perangkat Ajar / Modul Ajar), meliputi perbaikan kebocoran privasi data guru (Item A), perbaikan validasi tenant context stale session (Item B), penyempurnaan wording & UX workflow kurikulum (Item C, D, E, F, G), serta mitigasi gap backend integritas multi-tenant (Item H, I):

### Item A (Task 1) — 🔴 Tutup Kebocoran Data RPP Guru Lain di Tab "Saya"
- **Latar Belakang & Bukti Empiris Sebelum Fix:**  
  Tab default "Perangkat Ajar Saya" menampilkan dokumen RPP milik SEMUA guru di lembaga kepada pengguna yang tidak memiliki relasi profil guru (`$user->guru === null`, seperti role `operator_akademik`, `kepala_sekolah`, dan `wakasek_kurikulum`). Hal ini disebabkan `ListRppAction` hanya menambahkan klausa `where('guru_id', $guru->id)` bila `$guru` ada, tanpa menangani cabang `else`. Bahkan untuk `operator_akademik` (yang memiliki `rpp.kelola`), tombol aksi Edit, Hapus, dan Ajukan sempat tampil di antarmuka (meski diblokir 403 saat eksekusi).
- **Implementasi:**  
  1. Pada `app/Domains/Akademik/Actions/Rpp/ListRppAction.php`, menambahkan cabang `else { $baseQuery->whereRaw('1 = 0'); }` ketika `$tab === 'saya'` dan user tidak memiliki profil Guru.
  2. Pada `resources/views/portals/lembaga/akademik/rpp/_daftar.blade.php`, menambahkan defense-in-depth pada tombol aksi: `auth()->user()->guru?->id === $rpp->guru_id`.
- **Commit:** `3ade59bd`

### Item B (Task 2) — Hapus Ketergantungan `TenantContext` & Validasi Lembaga Aktif
- **Latar Belakang:**  
  `ListRppAction` sebelumnya menggunakan `TenantContext::activeLembagaId()` yang membaca `session('active_lembaga_id')` secara mentah tanpa memvalidasi apakah lembaga tersebut benar-benar berada di bawah yayasan aktor yang bersangkutan. Jika session berisi ID lembaga dari yayasan lain (stale session), query menghasilkan data kosong salah di Inbox Verifikasi.
- **Implementasi:**  
  1. Menghapus dependency `TenantContext` dari `ListRppAction`. Method `execute()` kini menerima parameter eksplisit `?int $targetLembagaId`.
  2. Di `RppController::index()`, resolusi lembaga aktif kini menggunakan `resolveActiveLembagaId($user)` dari `ResolveLembagaScopeTrait`, menjamin validasi keanggotaan yayasan dan auto-fallback ke mode agregat bila session tidak valid.
- **Commit:** `abc75562`

### Item D (Task 3) — Empty-State Sadar Filter & Sinkronisasi Payload AJAX
- **Latar Belakang:**  
  Sebelumnya, jika pengguna menerapkan filter pencarian/kelas/mapel di Inbox Verifikasi dan tidak ada hasil yang cocok, sistem menampilkan pesan keliru: *"Semua pengajuan RPP telah selesai ditinjau."*, padahal dokumen yang belum ditinjau sebenarnya ada tetapi tersembunyi karena filter. Selain itu, cabang AJAX `_daftar.blade.php` tidak menerima variabel-variabel filter.
- **Implementasi:**  
  1. Pada `RppController::index()`, cabang AJAX diperkaya dengan `compact('rppList', 'tab', 'perPage', 'search', 'tahunAjaranId', 'semesterId', 'kelasId', 'mapelId', 'status', 'kurikulum', 'tahunAjaranAktif')`.
  2. Pada `_daftar.blade.php`, menambahkan logika kalkulasi `$adaFilterAktif` dan memperbarui pesan empty-state:
     - Tab `saya` tanpa profil Guru: *"Akun Anda tidak terhubung dengan profil Guru, sehingga tidak ada dokumen RPP pribadi di sini."*
     - Tab `verifikasi` dengan filter aktif: *"Tidak ada dokumen yang cocok dengan filter di Inbox Verifikasi."*
     - Tab `verifikasi` tanpa filter: *"Tidak ada perangkat ajar yang sedang menunggu review verifikasi kurikulum."* / *"Semua pengajuan RPP telah selesai ditinjau."*
- **Commit:** `6cb43d37`

### Item G (Task 4) — Badge Scope Yayasan / Lembaga di Header Index
- **Implementasi:**  
  1. Menambahkan method `scopeHeaderData(Request $request): array` di `RppController` menggunakan `ResolveLembagaScopeTrait` dan `Lembaga::withoutGlobalScopes()->find($lembagaId)`.
  2. Mengirim data scope ke cabang AJAX (`array_merge`) dan halaman penuh (`...$this->scopeHeaderData($request)`).
  3. Menambahkan badge visual di samping judul halaman pada `index.blade.php`:
     - Mode Agregat: Badge ungu (`border-purple-200 bg-purple-50 text-purple-700`) bertuliskan *"Semua Lembaga"*.
     - Mode Lembaga Aktif: Badge brand (`border-brand-200 bg-brand-50 text-brand-700`) bertuliskan nama lembaga aktif.
     - Aktor Lembaga-scope: Tidak menampilkan badge.
- **Commit:** `4cfff92f`

### Item C (Task 5) — Perbaikan Wording "Waka Kurikulum"
- **Latar Belakang:**  
  Otorisasi verifikasi RPP (`rpp.verify`) dimiliki oleh 3 role (`kepala_sekolah`, `wakasek_kurikulum`, dan verifikator yayasan), bukan hanya satu jabatan. Wording dialog konfirmasi pengajuan yang menyebut *"diverifikasi oleh Waka Kurikulum"* membingungkan bila lembaga tidak memiliki struktur Waka Kurikulum atau bila verifikasi dilakukan oleh Kepala Sekolah.
- **Implementasi:**  
  Mengubah teks konfirmasi pada `_daftar.blade.php` menjadi: *"Apakah Anda yakin ingin mengajukan berkas ini untuk diverifikasi oleh pihak kurikulum?"*.
- **Commit:** `b5abfb55`

### Item E (Task 6) — Label Default Inbox pada Dropdown Status Tab Verifikasi
- **Latar Belakang:**  
  Tab verifikasi secara default hanya menampilkan dokumen berstatus *"Menunggu Verifikasi"* (`Diajukan`), namun dropdown filter Status menampilkan opsi default *"Semua Status"*. Hal ini menimbulkan ilusi bahwa pengguna sedang melihat seluruh status padahal sebenarnya sedang berada di inbox default.
- **Implementasi:**  
  1. Di `index.blade.php`, menambahkan label penjelas: `Status <span class="font-normal text-gray-400">(Inbox default: Menunggu Verifikasi)</span>` saat berada di tab verifikasi.
  2. Mengubah teks opsi default menjadi: *"— Semua Status (Keluar dari Inbox Default) —"*.
- **Commit:** `91e2293e`

### Item F (Task 7) — Keterangan Cakupan KPI
- **Implementasi:**  
  Menambahkan keterangan visual tipis di atas kartu KPI pada `index.blade.php`:
  `<p class="text-[11px] text-gray-400 -mb-1">Ringkasan {{ $tab === 'saya' ? 'dokumen Anda' : 'seluruh dokumen di lembaga ini' }} (tidak berubah mengikuti filter pencarian/Tahun Ajaran/Semester/Kelas/Mapel/Kurikulum di bawah).</p>`.
- **Commit:** `1951e086`

### Item H (Task 8) — Validasi Multi-Tenant `kelas_id` di `UpdateRppRequest`
- **Latar Belakang:**  
  `StoreRppRequest` sudah memeriksa kesesuaian lembaga `kelas_id`, namun `UpdateRppRequest` sebelumnya hanya memeriksa kesesuaian tahun ajaran dengan semester tanpa memeriksa apakah `kelas_id` berasal dari lembaga yang sama dengan dokumen RPP.
- **Implementasi:**  
  Di `app/Http/Requests/Akademik/UpdateRppRequest.php`, menambahkan pengecekan menggunakan `Kelas::withoutGlobalScopes()->find($kelasId)`:
  ```php
  if ($kelas->lembaga_id !== $rpp->lembaga_id) {
      $validator->errors()->add('kelas_id', 'Kelas yang dipilih bukan berasal dari lembaga yang sama dengan dokumen RPP ini.');
  }
  ```
- **Commit:** `34e1b10d`

### Item I (Task 9) — Refactor Urutan Hapus File di `UpdateRppAction`
- **Latar Belakang:**  
  Sebelumnya, berkas lama langsung dihapus dari disk penyimpanan sebelum transaksi database dieksekusi. Jika proses update database gagal (misal rollback/deadlock/exception), berkas lama sudah terlanjur hilang permanen sementara data record di database tetap menunjuk ke path tersebut.
- **Implementasi:**  
  Penghapusan berkas lama (`Storage::disk('public')->delete($oldFilePath)`) dipindahkan ke dalam transaksi database SETELAH `$rpp->update()` berhasil dieksekusi.
- **Commit:** `2e820adf`

### Item J (Addendum, pasca-Task 10) — Tahun Ajaran Tidak Terlihat di Dropdown Semester Modal Tambah/Edit RPP
- **Latar Belakang:**  
  Ditemukan oleh user setelah Task 1-10 selesai & direview. Dropdown "Semester" pada modal Tambah/Edit RPP (`_modal-form.blade.php`) hanya menampilkan nama semester (*"Ganjil"*/*"Genap"*) tanpa keterangan Tahun Ajaran, sehingga user tidak punya konfirmasi visual sedang membuat/mengubah RPP untuk tahun pelajaran yang mana.
- **Implementasi:**  
  1. Di `RppController::index()`, `$semesterQuery` ditambahkan eager-load `->with('tahunAjaran')`.
  2. Di `_modal-form.blade.php`, dropdown Semester dikelompokkan pakai `<optgroup label="{{ $namaTahunAjaran }}">` per Tahun Ajaran (mengikuti pola yang sudah ada di modul Piket Guru, `piket-guru/create.blade.php`), dan label field diubah dari "Semester" jadi "Tahun Ajaran & Semester".
  3. Test baru ditambahkan di `RppWorkflowTest.php` yang mengecek keberadaan `<optgroup label="...">` dengan nama Tahun Ajaran pada render halaman index.
- **Commit:** `01008e73`

### Task 10 — Regresi Penuh, Pint, & Verifikasi Browser
- Seluruh 51 test domain RPP lulus tanpa kegagalan (143 assertions).
- Laravel Pint formatting lulus (`{"tool":"pint","result":"passed"}`).
- Verifikasi browser berhasil dilakukan menggunakan browser subagent (screenshot dan rekaman tersimpan).
- Checklist pada implementation plan diperbarui: commit `91b9b4c2`.

---

## 2. Keputusan Penting yang Diambil

1. **Pemilihan `Kelas::withoutGlobalScopes()->find()` di `UpdateRppRequest` (Task 8):**  
   Ketika menguji input `kelas_id` milik lembaga lain, pemanggilan `Kelas::find($kelasId)` biasa akan mengembalikan `null` karena model `Kelas` menggunakan trait `BelongsToTenant` (`TenantScope`). Tanpa `withoutGlobalScopes()`, model akan dianggap tidak ditemukan sehingga alur validasi custom terlewati dan controller melempar 404 (`ModelNotFoundException`) alih-alih menampilkan validation error form. Penggunaan `withoutGlobalScopes()` menyelesaikan masalah ini secara elegan dan aman.
2. **Perbaikan Latent Bug Pencarian Guru di `ListRppAction`:**  
   Saat menjalankan pengujian pencarian dengan kata kunci teks pada Task 3, ditemukan SQL error: `Column not found: 1054 Unknown column 'nama' in 'where clause'`. Kolom `nama` pada model `Guru` adalah accessor ke `Person` (`nama_lengkap`), bukan kolom fisik database. Klausa `->orWhereHas('guru', fn ($g) => $g->where('nama', 'like', "%{$search}%"))` diperbaiki menjadi `->orWhereHas('guru', fn ($g) => $g->search($search))` (menggunakan `scopeSearch` bawaan model `Guru`).
3. **Penyesuaian Fixture Test di `RppKurikulumReportingTest`:**  
   Pada test existing `RppKurikulumReportingTest`, fixture sebelumnya membuat user kurikulum dan guru secara terpisah tanpa menghubungkan `user_id`. Setelah fix Task 1 (yang mewajibkan `$user->guru` untuk melihat tab `saya`), fixture tersebut diperbaiki dengan menyematkan `'user_id' => $userKurikulum->id` pada instans Guru agar test mencerminkan aktor kurikulum yang juga bertindak sebagai guru secara sah.
4. **Isolasi Scope Sesuai Arahan:**  
   Ketergantungan `TenantContext` di 13 file lain (Sarpras/Pengadaan) tidak disentuh, fokus penuh diselesaikan pada modul RPP sesuai arahan user.

---

## 3. Daftar Commit Hash

| Task | Commit Hash | Ringkasan Commit |
|------|-------------|------------------|
| Task 1 | `3ade59bd` | `fix(rpp): tutup kebocoran RPP guru lain di tab Saya utk aktor tanpa profil Guru + cek kepemilikan eksplisit di tombol aksi` |
| Task 2 | `abc75562` | `fix(rpp): ganti TenantContext dengan resolveActiveLembagaId() tervalidasi di ListRppAction` |
| Task 3 | `6cb43d37` | `fix(rpp): empty-state Inbox Verifikasi sadar filter (bukan klaim salah 'semua sudah ditinjau')` |
| Task 4 | `4cfff92f` | `feat(rpp): badge scope isYayasan/activeLembaga di header index` |
| Task 5 | `b5abfb55` | `fix(rpp): ganti wording 'Waka Kurikulum' jadi 'pihak kurikulum' (3 role bisa verifikasi, bukan cuma 1)` |
| Task 6 | `91e2293e` | `feat(rpp): label default Inbox pada dropdown Status tab verifikasi` |
| Task 7 | `1951e086` | `feat(rpp): keterangan cakupan KPI (tidak ikut filter kontrol)` |
| Task 8 | `34e1b10d` | `fix(rpp): tambah cek lembaga kelas_id di UpdateRppRequest, samakan dengan StoreRppRequest` |
| Task 9 | `2e820adf` | `fix(rpp): pindahkan hapus file lama ke SETELAH commit transaksi (hindari state tidak konsisten kalau update gagal)` |
| Task 10 | `91b9b4c2` | `docs(rpp): tandai semua task selesai pada implementation plan` |
| Item J (Addendum) | `01008e73` | `fix(rpp): tampilkan Tahun Ajaran di dropdown Semester pada modal Tambah/Edit RPP` |

---

## 4. Hasil Pengujian & Verifikasi

### Automated Test Suite
Perintah:
```bash
php artisan test --compact --filter="RppWorkflowTest|RppControllerIdorTest|RppKurikulumReportingTest|StoreRppRequestKelasSemesterTest|RppVerifyTest"
```
Hasil:
- **51 tests passed** (143 assertions, 0 failed) — pada saat Task 10 selesai.
- **52 tests passed** (145 assertions, 0 failed) — setelah Item J (Addendum) ditambahkan.
- Domain RPP secara menyeluruh lulus verifikasi fungsional, otorisasi IDOR, dan validasi tenancy.

### Linter & Formatter
Perintah:
```bash
vendor/bin/pint --dirty --format agent
```
Hasil:
- `{"tool":"pint","result":"passed"}`

### Verifikasi Manual via Browser
Sesi visual browser mengonfirmasi:
1. **Aktor Yayasan tanpa profil Guru:**
   - Tab "Perangkat Ajar Saya": Menampilkan pesan ramah yang jelas: *"Akun Anda tidak terhubung dengan profil Guru, sehingga tidak ada dokumen RPP pribadi di sini."*, tidak ada RPP milik guru lain yang bocor.
   - Header: Menampilkan badge scope lembaga aktif (*"SDIT Pintera"* warna emerald/brand, atau *"Semua Lembaga"* warna ungu pada mode agregat).
   - Keterangan KPI: Menampilkan *"Ringkasan dokumen Anda (tidak berubah mengikuti filter...)"*.
2. **Tab Inbox Verifikasi Kurikulum:**
   - Dropdown Status: Menampilkan label *"Status (Inbox default: Menunggu Verifikasi)"* dengan opsi default *"— Semua Status (Keluar dari Inbox Default) —"*.
   - Keterangan KPI: Berubah menjadi *"Ringkasan seluruh dokumen di lembaga ini (tidak berubah mengikuti filter...)"*.
   - Dialog pengajuan: Menggunakan wording *"pihak kurikulum"*.

---

## 5. Hal yang Masih Perlu Direview / Kondisi Saat Ini

1. **Status Git:**
   - Berada di branch `akademik-v2`.
   - **TIDAK** dimerge ke branch manapun (sesuai instruksi: *"Setelah Task 10 selesai, JANGAN merge branch akademik-v2 ke branch manapun — keputusan terpisah milik user"*).
   - Working tree bersih (`git status` clean, folder `storage/debugbar/` diabaikan).
2. **Backlog Terpisah (Di Luar Scope):**
   - Penggunaan `TenantContext` pada 13 file modul Sarpras dan Pengadaan masih menggunakan mekanisme lama dan siap dimasukkan ke rencana audit terpisah saat modul bersangkutan ditinjau.
   - Modernisasi modal formulir RPP ke format AJAX/SPA murni (saat ini tetap menggunakan alur POST + full reload sesuai batasan spec).
