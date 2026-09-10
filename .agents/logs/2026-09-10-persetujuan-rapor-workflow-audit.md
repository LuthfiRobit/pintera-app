# Handoff Log: Audit & Redesain Halaman Persetujuan Rapor dan Workflow Engine

**Tanggal:** 2026-09-10  
**Branch Git:** `rbac-v2`  
**Referensi Terkait:**
- Spec: [`.agents/specs/2026-09-10-persetujuan-rapor-workflow-audit.md`](file:///d:/laragon\www\pintera-app\.agents\specs\2026-09-10-persetujuan-rapor-workflow-audit.md)
- Plan: [`.agents/plans/2026-09-10-persetujuan-rapor-workflow-audit.md`](file:///d:/laragon\www\pintera-app\.agents\plans\2026-09-10-persetujuan-rapor-workflow-audit.md)
- Walkthrough: [`walkthrough.md`](file:///C:/Users/luthf/.gemini/antigravity-ide/brain/a81e15c1-f1a0-4286-8b5c-249a834fe334/walkthrough.md)

---

## 1. Apa yang Dikerjakan

1. **Perbaikan Fondasi Engine Workflow (`ApproverResolverService`)**:
   - Memperbaiki validasi kepemilikan lembaga pada `session('active_lembaga_id')` untuk aktor dengan scope `yayasan`.
   - Menghapus duplikasi guard pemeriksaan lembaga di Action spesifik Rapor (`VerifyPengajuanRaporAction`, `ApprovePengajuanRaporAction`).

2. **Ekstraksi Komponen Standar (`<x-scope-badge>`)**:
   - Mengekstrak duplikasi markup badge scope ke `resources/views/components/scope-badge.blade.php` per aturan `.ai/rules/components.md`.
   - Mengadopsi `<x-scope-badge>` pada `resources/views/admin/karyawan/index.blade.php` dan `resources/views/portals/lembaga/rapor/persetujuan/index.blade.php`.

3. **Penyempurnaan Halaman Index Sesuai Feedback User & Standar Halaman TP**:
   - **Filter Dropdown TomSelect (Tahun Ajaran & Semester)**: Menggantikan native select dengan komponen TomSelect (`window.TomSelect`), lengkap dengan placeholder, rounded container, chevron terintegrasi, dan pembaruan semester cascading via endpoint baru `admin.rapor.persetujuan.opsi`.
   - **Segmented Pill Filter Tabs (Identik Halaman TP)**: Mengadopsi tab pill tersegmentasi (`bg-gray-100 p-1 text-xs font-semibold`) dengan tombol aktif berlatar putih (`bg-white text-gray-900 shadow-sm`) dan indikator dot warna status (amber dot untuk *Menunggu Keputusan Saya* dan emerald dot untuk *Riwayat Keputusan*).
   - **Shimmer / Loading Overlay Modern**: Mengadopsi loading state gelap terpusat (`bg-gray-900 text-white shadow-xl` + `bg-white/70 backdrop-blur-xs`) seperti pada halaman TP.
   - **Navigasi Tab Bebas Reload (Pure AJAX Tabs)**: Navigasi antara *"Menunggu Keputusan Saya"* dan *"Riwayat Keputusan"* serta input pencarian realtime kini memperbarui kontainer tabel secara instan tanpa reload halaman dan menjaga sinkronisasi URL via `window.history.pushState`.
   - **Standarisasi Kolom Tabel (Aksi di Kiri)**: Memindahkan kolom **Aksi** ke urutan paling kiri (first column) sesuai kaidah standar tabel aplikasi Pintera (`AKSI` | `KELAS & WALI KELAS` | `TAHUN AJARAN & SEMESTER` | `STATUS ALUR` | `DIAJUKAN PADA`).

4. **Penyelarasan UI/UX Tabel Halaman Review dengan Rekap Rapor**:
   - **Ringkasan Statistik Kelas**: Mengadopsi 4 kartu ringkasan (Wali Kelas, Total Peserta Didik, Rata-Rata Kelas dengan tooltip, Skor Tertinggi).
   - **Matriks Nilai Asesmen Rekap Rapor**:
     - Legenda status: Tuntas (≥ 75) dan Perlu Bimbingan (< 75).
     - Kolom kiri *sticky*: **No** dan **Nama Peserta Didik & NIS** (kolom **Aksi/Rapor** yang semula ada di sini DIHAPUS pada putaran review, lihat §5 poin 6 -- duplikat 100% dengan aksi cetak yang sudah ada di kartu Catatan Wali Kelas).
     - Kolom tengah: Capaian asesmen tiap mata pelajaran dengan pill badge warna status.
     - Kolom kanan *sticky*: **Rata-Rata Umum**.
     - Kontainer tabel *scrollable* dengan sticky thead dan sticky identification columns.
   - **Suite Keputusan Interaktif**: Opsi Setujui Rapor Kelas vs Tolak, Minta Revisi Wali Kelas dengan validasi field catatan dinamis.

## 5. Deep-Review & Perbaikan Pasca-Redesign (Claude)

Setelah redesain di atas, dilakukan review kode baris-per-baris terhadap seluruh diff (bukan sekadar percaya klaim "sudah diverifikasi via browser subagent" di §3 lama -- klaim itu ternyata tidak menangkap bug nyata, lihat poin 7). Temuan & perbaikan:

1. **Root cause di Workflow engine** (`ApproverResolverService::checkRoleApprover()`): `session('active_lembaga_id')` dipercaya mentah untuk aktor scope yayasan mode agregat, menyebabkan approval SELALU gagal untuk kombinasi role realistis (mis. `pegawai_yayasan` + `kepala_sekolah`) walau pengajuan itu sah miliknya. Diperbaiki dengan resolusi yang divalidasi kepemilikan yayasan (private method `resolveEffectiveLembagaId()`, sengaja MANDIRI di domain Workflow, tidak reuse trait Akademik -- lihat spec Item W). Root cause ini otomatis membenarkan Pengadaan & SDM juga (dikonfirmasi via grep + 114 test Pengadaan/SDM lolos), tanpa perlu ubah kode di 2 domain itu.
2. **Guard defense-in-depth di 2 Action Rapor**: sempat dihapus dengan asumsi "duplikat murni Item W" (Task 2 plan), TAPI deep-review menemukan test pre-existing `RaporApprovalTenantScopeTest.php` yang mendokumentasikan guard itu sebagai lapis proteksi TERPISAH terhadap skenario fail-open lain (relasi `approvable`/`requester` ter-scope null oleh TenantScope). Dikembalikan dengan resolusi tervalidasi (`ResolveLembagaScopeTrait::resolveActiveLembagaId()`), bukan raw session seperti versi lama.
3. **KPI stat card & angka tab basi setelah filter/ganti tab AJAX** -- 4 kartu KPI dan 2 angka tab dirender sekali di load awal, di luar area yang di-refresh AJAX. Diperbaiki: server menyisipkan `$stats` terbaru sebagai payload tersembunyi (`data-rapor-stats`) di setiap fragment `_daftar.blade.php`; JS membaca payload itu setelah swap dan meng-update state Alpine `stats`, yang kini diikat via `x-text` ke semua kartu/angka.
4. **Placeholder pencarian overpromise**: teks bilang "kelas, wali kelas, atau pengaju" tapi backend cuma cari `kelas.nama`. Diperbaiki: `$filterClosure` kini juga mencari `kelas.waliKelas.person.nama_lengkap` dan `diajukanOleh.name`.
5. **Fokus keyboard hilang** di 2 kartu pilihan Setujui/Tolak (`<input type="radio" class="sr-only">` di dalam `<label>`, tanpa indikator visual saat di-Tab). Ditambahkan `focus-within:ring-2` per warna (emerald/rose).
6. **Kolom "Aksi/Rapor" di tabel matriks dihapus** -- atas masukan user langsung ("terlalu ramai") -- karena 100% duplikat dengan tombol "Buka di Platform"/"PDF" yang sudah ada di kartu Catatan Wali Kelas persis di bawahnya. Sticky column dari 3 (Aksi, No, Nama) jadi 2 (No, Nama), mengikuti pola persis Rekap Rapor.
7. **Bug "Buka di Platform" ditemukan lewat screenshot browser sungguhan dari user** (bukan dari review kode atau test otomatis) -- modal render sebagai ikon gambar rusak, bukan PDF. Akar masalah: `$store.imagePreview.buka(url, judul)` di kartu Catatan Wali Kelas cuma kirim 2 argumen, parameter `isPdf` tidak diisi eksplisit, dan auto-deteksi modal gagal karena URL cetak tidak berakhiran `.pdf`/`inline=1`. Diperbaiki dengan mengirim `isPdf=true` eksplisit + suffix `?inline=1`, mengikuti pola `bukaCetakRapor` di `rapor-filter.js`. **Catatan penting**: ini bukti nyata bahwa review kode + test otomatis TIDAK menggantikan verifikasi browser sungguhan untuk elemen interaktif JS -- klaim "sudah diverifikasi via browser subagent" di §3 lama tidak menangkap bug ini.
8. **Pelanggaran `.ai/rules/js.md`**: `persetujuanRaporFilter` sempat didefinisikan inline via `<script>` di `index.blade.php`. Diekstrak ke `resources/js/persetujuan-rapor-filter.js` + didaftarkan `Alpine.data()` di `app.js`, mengikuti pola persis `rapor-filter.js`. `npm run build` dikonfirmasi sukses.

**Audit tambahan (bukan bagian redesain, tapi ditriger dari pertanyaan "bagian rapor lain gimana?")**: sisi Wali Kelas/Guru (`Guru\RaporController`) dan sisi Siswa/Orang Tua (`NilaiRaporSiswaController`) dibaca penuh -- keduanya SUDAH aman (ownership check eksplisit per method, `$siswa` selalu diturunkan dari user login bukan dari parameter URL, test IDOR sudah ada & lolos). Satu temuan kecil: `catatanWaliKelasForm` di `catatan/edit.blade.php` PUNYA pelanggaran `.ai/rules/js.md` yang sama (`<script>` inline) -- **BELUM diperbaiki**, jadi target kerja berikutnya.

---

## 2. Keputusan Penting yang Diambil

1. **AJAX Filtering & History State**:
   Filter dan tab pada index menggunakan Alpine.js store lokal yang memanggil endpoint controller secara parsial. Controller mengembalikan view snippet `_daftar.blade.php` ketika menerima header `X-Requested-With: XMLHttpRequest`.
2. **Dedicated Opsi Endpoint untuk Persetujuan Rapor**:
   Menambahkan `route('admin.rapor.persetujuan.opsi')` di `PersetujuanController::opsi` untuk melayani opsi semester dinamis berbasis `tahun_ajaran_id` khusus aktor yang memiliki otorisasi `rapor.verify` atau `rapor.approve`.
3. **Aksi Rapor di Kiri Tabel Matriks -- KEPUTUSAN INI DIBALIK saat review**:
   Rencana awal meletakkan tombol *"Buka di Platform"* dan *"PDF"* di kolom paling kiri tabel matriks siswa. Setelah user menilai ini "terlalu ramai" (3 kolom sticky sekaligus: Aksi, No, Nama), kolom Aksi dihapus total -- fungsinya 100% tersedia lewat kartu Catatan Wali Kelas di bawah tabel, jadi tidak ada fungsi yang hilang, hanya dipindah ke satu tempat.
4. **Pengujian Komprehensif**:
   Menambahkan test cases pada `tests/Feature/Rapor/RaporPersetujuanControllerTest.php` untuk memvalidasi filtering `tahun_ajaran_id` & `semester_id`, partial view rendering via AJAX, dan endpoint `rapor.persetujuan.opsi`; ditambah lagi saat review untuk regresi kombinasi role yayasan (`tests/Feature/Workflow/ApproverResolverServiceTest.php`, baru) dan defense-in-depth tenant-scope (`RaporApprovalTenantScopeTest.php`, pre-existing, sempat gagal lalu diperbaiki).

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude

- **Branch Git**: Perubahan berada di branch `rbac-v2`, 10 commit di depan `origin/rbac-v2`, belum di-push/merge.
- **Pengujian**: seluruh test yang menyentuh Persetujuan Rapor/Workflow (38 test) lolos; full suite proyek (3123 test) juga dijalankan sekali di putaran audit ulang, 3 gagal semuanya dikonfirmasi pre-existing & tidak terkait (`SubjekTenantValidationTest`, `M3DemoDataSeederTest` x2).
- **Format Kode**: Lulus verifikasi Pint (`vendor/bin/pint --dirty --format agent`) dan `npm run build` (untuk modul JS yang diekstrak).
- **Verifikasi Visual Browser**: klaim "sudah diverifikasi via browser subagent" pada draf awal TIDAK menangkap bug nyata (lihat §5 poin 7, tombol "Buka di Platform" baru ketahuan rusak dari screenshot browser sungguhan milik user). Pelajaran: review kode + test otomatis tidak cukup untuk elemen interaktif JS/Alpine -- perlu klik langsung di browser.
- **Belum dikerjakan**: pelanggaran `.ai/rules/js.md` di `catatan/edit.blade.php` (`catatanWaliKelasForm` masih inline `<script>`) -- target kerja sesi berikutnya, fokus halaman Rapor Wali Kelas.
