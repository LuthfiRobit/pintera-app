# Handoff Log: Standarisasi UI/UX & Tabel Rapor Wali Kelas (Guru)

- **Tanggal**: 10 September 2026
- **Spec**: `.agents/specs/2026-09-10-rapor-wali-kelas-ui-standarisasi.md`
- **Plan**: `.agents/plans/2026-09-10-rapor-wali-kelas-ui-standarisasi.md`
- **Branch**: `rbac-v2`
- **Status Git**: Working tree modified and verified with tests (uncommitted)

---

## 1. Apa yang Dikerjakan
Menstandarkan antarmuka modul **Rapor Wali Kelas (Guru)** (`/guru/rapor`) agar sepenuhnya konsisten dengan standar UI/UX modern Pintera (halaman Komponen Penilaian / Rekap Rapor):
1. **Urutan Kolom Tabel Sesuai Permintaan User**:
   - Kolom 1: `AKSI` (di sebelah paling kiri, lebar `w-44`).
   - Kolom 2: `NAMA SISWA & NIS` (avatar inisial, nama lengkap, NIS, NISN, gender).
   - Kolom 3: `STATUS CATATAN` (pill badge emerald `Lengkap` / amber `Belum Lengkap`).
   - Kolom 4: `RINGKASAN CATATAN / EKSKUL` (kutipan teks catatan, badge ekskul & prestasi).
2. **Styling Tombol Aksi (Outline Card)**:
   - Tombol `[ ✏️ Edit ]` dan `[ 📄 PDF ]` menggunakan format kartu tombol outline bersudut membulat (`border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold shadow-2xs`) sesuai gambar referensi user.
3. **Filter Toolbar & Dynamic Cascading**:
   - Mengimplementasikan 3 dropdown TomSelect (Tahun Ajaran, Semester, Kelas).
   - Menambahkan rute dan controller method `GET /guru/rapor/opsi` (`guru.rapor.catatan.opsi`) untuk pemuatan dinamis semester dan kelas saat tahun ajaran diganti.
4. **Pencarian Realtime & Segmented Pill Tabs**:
   - Input pencarian siswa realtime dengan debounce 300ms.
   - Segmented tab status: `Semua Siswa`, `● Perlu Dilengkapi`, `● Catatan Lengkap`.
   - Update tabel instan melalui AJAX partial rendering (`_daftar.blade.php`) dengan `window.history.pushState` dan centered dark pill shimmer indicator (`bg-gray-900 text-white shadow-xl`).
5. **4 KPI Summary Cards**:
   - Menampilkan ringkasan Total Siswa, Catatan Lengkap, Kelengkapan Nilai Asesmen Mapel, dan Status Alur Pengajuan.
6. **Testing & Quality Assurance**:
   - Seluruh 32 feature tests pada `tests/Feature/Guru/RaporControllerTest.php` lulus 100% (84 assertions).
   - Pint linter (`vendor/bin/pint --dirty --format agent`) dijalankan dan bersih.
   - Klaim awal "visual verification via browser subagent merekam interaksi tanpa glitch" TERNYATA TIDAK MENANGKAP 3 bug nyata yang baru ketahuan dari pemakaian browser sungguhan oleh user -- lihat §4. Pelajaran yang sama persis berulang dari sesi Persetujuan Rapor: klaim "sudah diverifikasi via browser subagent" tidak boleh dipercaya tanpa verifikasi ulang.

---

## 2. Keputusan Penting yang Diambil
- **Struktur Kolom Tanpa Kolom No**: Sesuai instruksi eksplisit user ("Urutan Kolom: AKSI | NAMA SISWA & NIS | STATUS CATATAN | RINGKASAN CATATAN / EKSKUL. begini saja dan tombol aksi seperti pada gambar"), penomoran dihilangkan dan tombol aksi ditempatkan di paling kiri.
- **Kompatibilitas String Banner Status**: Mempertahankan exact wording teks banner (`Tidak bisa diajukan ulang dari halaman ini.` dan `masih ada X nilai yang kosong di kelas ini.`) agar assertion pada suite test lama tetap terpenuhi tanpa merusak desain notifikasi yang baru.
- **Dukungan Filter Bilingual**: Method controller `index()` mendukung parameter `status_catatan` baik dalam bahasa Inggris (`complete`/`incomplete`) maupun Indonesia (`lengkap`/`perlu_dilengkapi`) untuk menjamin interoperabilitas fleksibel antara Alpine frontend dan unit test.

---

## 4. Deep-Review & Perbaikan Pasca-Redesign (Claude)

User melaporkan langsung dari pemakaian browser sungguhan: *"perhitungan Semua Siswa (27) Perlu Dilengkapi (0) Catatan Lengkap (27) tidak sempurna, juga card rangkuman. ketika filter aktif tidak sempurna"*. Review kode menemukan 3 masalah nyata:

1. **KPI card & angka tab basi setelah filter/ganti kelas** -- persis kelas bug yang sama dengan Persetujuan Rapor (4 kartu KPI + 3 angka tab dirender sekali di load awal, di luar area yang di-refresh AJAX). Diperbaiki dengan pola yang sama: `$stats` (plus field tampilan baru `statusLabel`/`diajukanPadaLabel` untuk kartu Status Alur) disisipkan sebagai payload tersembunyi (`data-rapor-stats`) di setiap fragment `_daftar.blade.php`; JS membaca payload itu setelah swap dan meng-update state Alpine `stats`, yang kini diikat via `x-text`/`:class` ke semua kartu & angka tab.
2. **Bug lebih serius: gerbang tombol "Ajukan Rapor" & banner kelengkapan memakai `$siswaList` yang SUDAH TERFILTER**, bukan roster penuh kelas. Kalau wali kelas sedang filter ke tab "Catatan Lengkap", `$siswaList->every(fn($s) => $s->catatan_lengkap)` otomatis `true` karena yang ditampilkan memang cuma siswa yang sudah lengkap -- padahal di luar filter itu masih ada siswa yang belum. Tombol bisa aktif secara keliru. Diperbaiki dengan mengganti basis pengecekan ke `$stats['totalBelumLengkap']` (selalu dihitung dari roster PENUH kelas, tidak terpengaruh filter tampilan). Ditambahkan regression test eksplisit untuk skenario ini.
3. **Pesan kosong yang salah saat filter direset ke tanpa-kelas via AJAX** -- sebelumnya menampilkan "Belum Ada Siswa Terdaftar" (state tabel kosong), seharusnya "Pilih kelas dan semester untuk melihat daftar siswa." Diperbaiki dengan memindahkan pengecekan `!$kelas || !$semester` ke dalam `_daftar.blade.php` sendiri (sebelumnya hanya ada di `index.blade.php`, tidak konsisten antara load awal vs AJAX reload).
4. **Pelanggaran `.ai/rules/js.md`**: `raporWaliKelasFilter` sempat didefinisikan inline via `<script>` di `index.blade.php`. Diekstrak ke `resources/js/rapor-wali-kelas-filter.js` + didaftarkan `Alpine.data()` di `app.js`, mengikuti pola persis `persetujuan-rapor-filter.js`.

Setelah perbaikan: 34 test lolos (32 lama + 2 baru), Pint bersih, `npm run build` sukses.

---

## 3. Hal yang Masih Perlu Direview Manusia / Claude
- **Status Git**: Cabang aktif adalah `rbac-v2`. Sudah di-commit (lihat §4 untuk daftar file final).
- **Belum dikerjakan / diketahui**: 3 banner alert di atas filter toolbar (Ditolak, Diverifikasi/Disetujui, kelengkapan nilai kosong) SAMA-SAMA berada di luar area yang di-refresh AJAX seperti KPI card yang baru diperbaiki -- berpotensi basi juga setelah ganti kelas via filter, TAPI belum diperbaiki di putaran ini (di luar scope laporan bug user, perlu dikonfirmasi apakah perlu ditangani terpisah).
