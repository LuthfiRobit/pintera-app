# Handoff Log: Rombak Total Manual Book Akademik

- **Tanggal**: 2026-09-06
- **Branch**: `akademik-v2`
- **Spec**: `.agents/specs/2026-09-05-rombak-manual-book-akademik.md`
- **Plan**: `.agents/plans/2026-09-05-rombak-manual-book-akademik.md`
- **Base Commit**: `9bf1cae2` (`feat(seeder): tambah LembagaPaudDemoSeeder untuk demo PAUD akademik`)
- **Artifact URL Target**: `https://claude.ai/code/artifact/92e6b639-d846-48ad-9e42-2a270abc5e03`
- **Output Artifact Lokal**: `scripts/manual-book-artifact/dist/manual-book-akademik.html` (5.49 MB)

---

## 1. Apa yang Dikerjakan

Telah diselesaikan perombakan total 100% pada dokumentasi panduan pengguna modul Akademik (`docs/manual-book/akademik/`) yang sebelumnya tidak terbarukan sejak akhir Juli 2026. Seluruh 14 task dalam rencana implementasi telah dieksekusi secara tuntas:

1. **Seeder Demo PAUD (`LembagaPaudDemoSeeder.php`)**:
   - Dibuat dan didaftarkan pada `database/seeders/DatabaseSeeder.php` (commit `9bf1cae2`).
   - Menyediakan data unit TK (`TK Permata`), Tahun Ajaran & Semester aktif, akun Guru PAUD (`guru.tk@demo.test`), rombel/kelas Kelompok A dengan pola jam & wali kelas, data siswa, serta komponen Elemen CP.
   - Mengisi nilai observasi naratif untuk 2 siswa dan **sengaja membiarkan 1 siswa bernilai kosong** sebagai pembuktian fungsional banner & tabel peringatan kelengkapan nilai rapor.
2. **Audit Gaya & Pembersihan File Usang**:
   - Menghapus 8 file markdown lama beserta seluruh tangkapan layar usang di `docs/manual-book/akademik/images/`.
3. **Penyusunan Ulang Bab Topik (Format 5-Bagian Standar)**:
   - Setiap bab disusun dengan format baku: (1) Judul, (2) Untuk Siapa, (3) Prasyarat, (4) Langkah-langkah bergambar, (5) Kesalahan Umum (gejala → penyebab → solusi).
   - Seluruh langkah diuji dan diverifikasi secara langsung melalui browser interaktif sebelum teks dan tangkapan layarnya diproduksi (*Definition of Done*):
     - **Bab 0 (`00-setup-lembaga.md`)**: Setup unit sekolah, edit profil sekolah, registrasi akun pegawai lewat `Admin → Guru`.
     - **Bab 1 (`01-data-master.md`)**: Pengelolaan Tahun Ajaran, Semester, Master Mapel, Kelas/Rombel, Siswa (termasuk fitur *Generate Akun Portal Siswa*), dan Kalender Lembaga.
     - **Bab 2 (`02-penjadwalan.md`)**: Pola jam, jam pelajaran per hari, penautan pola jam ke rombel, dan penyusunan roster mingguan.
     - **Bab 3 (`03-presensi-jurnal.md`)**: Jurnal KBM harian guru, presensi siswa per jam tatap muka, dan penggunaan field baru **Keterangan** untuk alasan Izin/Sakit.
     - **Bab 4 (`04-asesmen-nilai.md`)**: Komponen penilaian TP, asesmen sumatif lingkup materi/akhir semester, serta **sub-bagian khusus Sisi Guru PAUD** (penilaian berbasis Elemen CP dan narasi capaian perkembangan).
     - **Bab 5 (`05-rekap-rapor.md`)**: Rekap nilai kelas, catatan wali kelas dengan **banner peringatan kelengkapan nilai**, verifikasi Waka Kurikulum dengan **tabel detail kelengkapan nilai**, persetujuan akhir Kepala Sekolah, dan cetak PDF rapor dengan penandatangan struktural yang valid.
     - **Bab 6 (`06-kenaikan-kelas.md`)**: Pemetaan status siswa (Naik/Tinggal/Lulus) dan pembagian kelas baru di akhir tahun ajaran (lengkap dengan peringatan sifat *irreversible*).
     - **Lampiran (`lampiran-lintas-lembaga.md`)**: Pengaturan kalender akademik nasional dan hari libur bersama tingkat yayasan.
4. **Penambahan Bab Baru (Portal Layanan Mandiri)**:
   - **Bab 7 (`07-ruang-orang-tua.md`)**: Portal mandiri orang tua untuk memantau Nilai Anak & Unduh Rapor PDF resmi, Jadwal Pelajaran Anak, dan Riwayat Izin/Sakit lengkap dengan keterangan guru.
   - **Bab 8 (`08-ruang-siswa.md`)**: Portal mandiri peserta didik untuk melihat Capaian Nilai & Unduh Rapor Mandiri, Jadwal Pelajaran (mode matriks grid dan daftar), serta Statistik Presensi Kehadiran Siswa.
5. **Penyusunan Indeks Alur Kerja per Role (`README.md`)**:
   - Menjawab langsung pertanyaan operasional klien: *"Untuk peran saya, apa saja yang harus diisi dan bagaimana urutannya?"*.
   - Menyediakan daftar periksa bernomor urut bagi: Admin Yayasan, Operator Akademik/Admin Lembaga, Guru Mata Pelajaran, Guru PAUD/TK, Wali Kelas, Wakasek Kurikulum, dan Kepala Sekolah.
   - Menyediakan panduan akses fitur bagi role *self-service*: Orang Tua dan Siswa.
6. **Regenerasi Tangkapan Layar & Single-Page Artifact**:
   - Mengambil 36 gambar screenshot aplikasi nyata beresolusi tajam via skrip otomatis Playwright `scripts/manual-book-screenshots.mjs`.
   - Memperbarui skrip `scripts/manual-book-artifact/build.mjs` dan `template.html` untuk memuat 11 dokumen lengkap, tautan silang, styling horizontal separator, dan sinkronisasi kredensial akun uji coba.
   - Menghasilkan file output `scripts/manual-book-artifact/dist/manual-book-akademik.html` (5.49 MB) dengan seluruh aset visual tertanam langsung (*base64 inlined*).

---

## 2. Keputusan Penting yang Diambil

1. **Struktur Penomoran Bab**:
   - Bab 0 s.d. 6 dipertahankan urutannya agar tidak merusak konvensi hierarkis yang sudah dipahami.
   - Bab portal baru diletakkan di akhir (`07-ruang-orang-tua.md` dan `08-ruang-siswa.md`) karena kedua portal bersifat *read-only* konsumen data hilir yang tidak menjadi prasyarat bagi alur administratif guru/admin.
2. **Penyesuaian Skrip Otomatisasi Playwright**:
   - Port server lokal disesuaikan ke `http://localhost:8000`.
   - Rute presensi guru diarahkan ke rute aktif `/guru/jurnal-kbm` (bukan URL lama `/guru/sesi`).
   - Query parameter rekap rapor disesuaikan dengan data tahun ajaran aktif (`tahun_ajaran_id=2&kelas_id=13&semester_id=3`).
   - Parameter form kenaikan kelas disesuaikan ke pasangan tahun ajaran demo (`tahun_ajaran_id=1&tahun_ajaran_tujuan_id=2`).
3. **Pengecualian Screenshot Sisi Guru PAUD (Bab 4)**:
   - Sesuai §6 pada dokumen spec, fitur asesmen PAUD diverifikasi langsung di browser menggunakan akun `guru.tk@demo.test`. Penjelasan ditulis detail dengan membandingkan komponen Elemen CP vs Mapel tanpa screenshot terpisah demi efisiensi dan ukuran dokumen.
4. **Peningkatan Parser HTML Artifact (`build.mjs`)**:
   - Dukungan parsing link markdown dengan hash fragment (`file.md#anchor`) dipetakan ke section id (`#bab-N`, `#peta-role`, `#lampiran`).
   - Penambahan format pemisah garis horizontal (`---`) dan pemformatan teks miring (`*teks*`).
   - Daftar akun uji coba pada kartu sidebar `template.html` disinkronkan dengan akun demo yang aktif (`superadmin@demo.test`, `kurikulum.sd@demo.test`, `kepsek.sd@demo.test`, `guru.sd1@demo.test`, `guru.tk@demo.test`, `ortu.sd@demo.test`, `siswa.sd@demo.test`).

---

## 3. Hal yang Perlu Direview Manusia / Claude

1. **Publikasi ke URL Artifact Claude**:
   - File artifact HTML mandiri telah berhasil dibangun di `scripts/manual-book-artifact/dist/manual-book-akademik.html`.
   - Untuk mempublikasikan pembaruan ke URL artifact yang sama, silakan gunakan tool publikasi Artifact dengan parameter `url: "https://claude.ai/code/artifact/92e6b639-d846-48ad-9e42-2a270abc5e03"`.
2. **Status Git**:
   - Seluruh file di dalam `docs/manual-book/akademik/` dan `scripts/manual-book-artifact/dist/` berada di bawah aturan `.gitignore` (sesuai spesifikasi proyek, dokumentasi manual book tidak masuk ke dalam git tracking).
   - Perubahan kode seeder telah ter-commit pada `9bf1cae2`.
   - Perubahan file pendukung yang siap di-commit: `.agents/plans/2026-09-05-rombak-manual-book-akademik.md`, `.agents/logs/2026-09-05-rombak-manual-book-akademik.md`, `PETA_PENGEMBANGAN.md`, `scripts/manual-book-screenshots.mjs`, `scripts/manual-book-artifact/build.mjs`, dan `scripts/manual-book-artifact/template.html`.
