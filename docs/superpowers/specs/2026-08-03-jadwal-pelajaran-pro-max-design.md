# Design Specification: Jadwal Pelajaran Pro-Max (Opsi C: Paket Lengkap)

**Tanggal:** 2026-08-03  
**Status:** Disetujui (Approved for Planning)  
**Modul:** Akademik — Jadwal Pelajaran (`App\Http\Controllers\Admin\JadwalPelajaranController`)

---

## 1. Latar Belakang & Tujuan
Modul **Jadwal Pelajaran** saat ini memproses penjenjangkan jam belajar, mata pelajaran, dan pengampu berbasis kelas dan semester. Walaupun logika backend dan pengujian (44 tes) telah berdiri matang, alur interaksi pengguna (UX) masih bersifat konvensional:
1. Penambahan dan penyuntingan sesi masih beralih dari satu halaman ke halaman lain (*full page redirects* ke `create.blade.php` dan `edit.blade.php`), membuat operator harus menunggu pemadatan ulang laman saat menyalin banyak data.
2. Tidak adanya presentasi **Matriks Roster Mingguan** (jadwal bergaya kalender horisontal mingguan per kelas) yang sangat dibutuhkan di sekolah untuk melisankan pemadaman jam kosong maupun pengecekan jadwal per sesi.
3. Belum tersedianya fitur salin cepat (**1-Click Duplikasi Jadwal**) untuk menyebarluaskan susunan jadwal dari semester lalu ke semester berjalan, ataupun antar kelas sejawat dengan Pola Jam setara.

Tujuan rancangan **Pro-Max Refactor** ini adalah mengubah alur integrasi UI/UX modul Jadwal Pelajaran agar berkinerja eksekutif, menggunakan model **SPA Modal Partials** berlisensi Alpine.js, integrasi pemetaan **Matriks Mingguan Interaktif**, serta sokongan backend **Duplikasi Jadwal Bebas Bentrok**.

---

## 2. Arsitektur Solusi & Alur Kerja

```
[Filter Card (Tahun Ajaran | Semester | Kelas)]
                     |
         +-----------+-----------+
         |                       |
   [Tambah Sesi (Modal)]   [Salin Jadwal (Modal)] ---> POST /admin/jadwal-pelajaran/duplicate
                     |                                         |
                     v                                 (DB::transaction)
          [View Mode Switcher]                         - Cek kesesuaian Pola Jam
         /                    \                        - Cek bentrok pengampu (Double-Booking)
 [Daftar Harian (List)]    [Matriks Roster (Table Grid)]
                                      |
                              (Klik Sel Jadwal)
                                      |
                         [Edit Jadwal Modal (SPA)]
```

### 2.1. 1-Click Duplikasi Jadwal Antar Kelas / Semester
- **Endpoint:** `POST /admin/jadwal-pelajaran/duplicate`
- **Payload Request:**
  - `source_kelas_id`: ID kelas sumber yang akan disalin
  - `source_semester_id`: ID semester sumber
  - `target_kelas_id`: ID kelas tujuan
  - `target_semester_id`: ID semester tujuan
- **Prosedur Validasi & Eksekusi (Transaction & Resilience):**
  1. Validasi hak akses lembaga (`jadwal-pelajaran.kelola` dan pengecekan tenant scope pada kelas & semester sumber maupun tujuan).
  2. Memeriksa keberadaan *Pola Jam* pada `target_kelas_id`. Apabila kelas tujuan belum dikaitkan dengan pola jam apapun, tolak dengan pesan error ramah.
  3. Menganalisis daftar slot dari `source_kelas_id`. Untuk setiap jadwal sesi, cari kesesuaian slot pada pola jam di kelas tujuan berdasarkan atribut `(hari, urutan)`.
  4. Pengecekan Bentrok Waktu (Double Booking & Collision Protection):
     - **Bentrok Ruang/Kelas:** Apakah kelas tujuan di semester tujuan sudah memiliki jadwal pada jam tersebut?
     - **Bentrok Guru:** Apakah guru bersangkutan sudah mengajar di kelas lain pada hari dan rentang waktu tersebut pada semester tujuan?
  5. Apabila terjadi bentrok pada sel tertentu, lewati (*skip*) sesi tersebut dan catatkan kalkulusnya.
  6. Mengirim respon balasan sukses beserta kalkulasi (misal: *"Berhasil menyalin 24 sesi jadwal. 2 sesi dilewati karena guru bentrok pada waktu tersebut."*).

### 2.2. Transformasi SPA Modal (Tambah & Edit Tanpa Redirect)
- **Komponen Views:**
  - Tetap mempertahankan `create.blade.php` dan `edit.blade.php` bagi kompatibilitas fallback eksternal jika diperlukan, namun antarmuka utama (`index.blade.php`) kini mempekerjakan *SPA Modal partals*:
    - `_modal-create.blade.php`: Modal penambahan jadwal cepat untuk slot kosong.
    - `_modal-edit.blade.php`: Modal pengubahan mapel/guru pada jadwal terdaftar.
    - `_modal-duplicate.blade.php`: Modal pemilihan parameter sumber untuk fitur Salin Jadwal.
- **Integrasi Alpine.js & State Controller:**
  - Memperluas objek state pada `jadwalPelajaranFilter({ ... })` (atau di dalam container modal penampung di `index.blade.php`) agar menangani pembukaan modal `showModalCreate`, `showModalEdit`, dan `showModalDuplicate`.
  - Pada penggabungan AJAX respon dari `_daftar.blade.php`, aksi klik tombol *Edit* akan menembakan event Alpine atau pemanggilan fungsi turunan tanpa perlu pergerakan URL halaman!

### 2.3. Matriks Roster Mingguan (Interactive Weekly Timetable Matrix)
- Di bagian atas kontainer daftar jadwal pada `_daftar.blade.php`, ditambahkan *toggle button group* (seperti pada Pola Jam):
  - **Mode 1: Daftar Harian** (Tampilan *list card* harian berurutan yang saat ini terpasang).
  - **Mode 2: Matriks Mingguan** (Tabel horisontal Roster mingguan baru).
- **Spesifikasi Kolom Tabel Roster:**
  - **Header Horizontal (Sumbu X):** Kolom pertama adalah `"Jam Ke- / Waktu"`, diikuti kolom untuk setiap Hari Aktif lembaga (misal: Senin, Selasa, Rabu, dst.).
  - **Baris (Sumbu Y):** Diindeks berdasarkan seluruh `urutan` sesi yang diatur dalam *Pola Jam* kelas terpilih. Kolom pertama memperlihatkan label `Ke-[Urutan]` serta rentang jam (mis. `07:15 - 08:00`, diformat rapi tanpa detik menggunakan Carbon).
  - **Isi Sel:** 
    - Apabila terdapat jadwal pada sesi tersebut: Tampilkan *chip* visual berisi **Nama Mata Pelajaran** berfont bold di baris atas dan **Nama Guru Pengampu** bertanda ikon di baris bawah. Klik sel tersebut beraksi gila membuka **Modal Edit**.
    - Apabila slot jam pada pola jam bertanda Non-Pelajaran (misal Istirahat): Tampilkan strip abu-abu bergilir dengan label keterangan (*Istirahat / Upacara*).
    - Apabila slot berstatus Pelajaran namun belum diatur mapelnya (*Kosong*): Tampilkan *box dashed* bertuliskan *"Kosong (Klik untuk Tambah)"*. Klik memicu **Modal Create** ter-prefilled dengan hari dan urutan bersangkutan!

---

## 3. Strategi Pengujian (Test-Driven Development / TDD)

Pengujian akan dijalankan secara terotentikasi dan bebas regresi terhadap berkas test suite eksisting (`tests/Feature/Admin/JadwalPelajaranCrudTest.php`):
1. **Verifikasi Baseline:** 44 tes eksisting harus tetap **Lulus 100%** tanpa modifikasi deskriptor spesifikasi.
2. **Penambahan Test Case Baru (TDD Green Assertions):**
   - `it duplicates jadwal pelajaran from source to target kelas in transaction`
   - `it skips conflicting teacher slots during duplication and returns diagnostic info message`
   - `it refuses duplication if target kelas has no pattern assigned`
   - `it renders weekly timetable roster matrix table correctly on ajax list fetch`

---

## 4. Self-Review & Analisis Ambiguitas (Spec Quality Assurance)
- **Konsistensi Data AJAX vs Page Load:** Modul Jadwal Pelajaran memvalidasi muatan melalui request AJAX saat operator memilih dropdown Tahun Ajaran -> Semester -> Kelas. Maka dari itu, penyebaran state moda pembuka modal (seperti `openEditJadwal(...)`) wajib dirancang kohesi dan aman baik pada DOM pemuat awal maupun setelah render parsial `_daftar.blade.php` diperbarui.
- **Isolasi Lembaga:** Fitur salin jadwal dikontrol ketat di dalam fungsi `duplicate()` menggunakan klausal pemilahan tenant (`whereHas('kelas', fn($q) => $q->where('lembaga_id', ...))`).
