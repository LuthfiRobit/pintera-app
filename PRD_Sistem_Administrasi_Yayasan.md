# PRD — Sistem Administrasi Sekolah Yayasan (Multi-Lembaga)
**Versi:** 0.1 (Draft Pilot)
**Tanggal:** 12 Juli 2026
**Status:** Discussion Draft — untuk validasi sebelum breakdown sprint

---

## 1. Ringkasan Eksekutif

Sistem administrasi terpadu untuk sebuah **yayasan** yang menaungi **beberapa lembaga pendidikan** (TK/SD/SMP/SMA, dst), mencakup seluruh siklus hidup siswa dari **SPMB (Sistem Penerimaan Murid Baru)** sampai **kelulusan**, termasuk administrasi akademik, keuangan, dan operasional (Sarpra, HRD, BK).

**Pilot pertama (fase MVP)** difokuskan pada dua modul yang saling terkait erat:
1. **SPMB/PPDB** — pendaftaran murid baru
2. **Keuangan** — tagihan pendaftaran & daftar ulang (termasuk skema cicilan)

Modul Akademik, E-Sarpra, E-HRD, dan E-BK akan menyusul di fase berikutnya, namun arsitektur data (terutama tabel user, tenant/lembaga, dan siswa) dirancang agar modul-modul itu bisa "nyambung" tanpa migrasi besar di kemudian hari.

**Stack teknis:**
- Backend: Laravel + MySQL
- Frontend: Tailwind CSS + JavaScript (kemungkinan Alpine.js/Livewire untuk interaktivitas ringan — perlu diputuskan)
- Desain UI: Google Stitch
- Metode pengembangan: AI-assisted ("vibe coding") dengan Claude terintegrasi di Google Antigravity IDE

---

## 2. Tujuan & Non-Tujuan

### Tujuan Pilot
- Satu sistem yang bisa dipakai **seluruh lembaga di bawah yayasan secara bersamaan**, dengan kebijakan SPMB dan struktur tagihan yang **berbeda-beda per lembaga**.
- Alur SPMB → generate tagihan pendaftaran (wajib lunas) → verifikasi/lunas → murid diterima → generate tagihan daftar ulang (bisa dicicil dengan batas maksimum cicilan yang dikonfigurasi) → murid resmi terdaftar sebagai siswa aktif.
- **Jalur PPDB dinamis**: jalur (reguler/prestasi/afirmasi/dll) dan formulir kebutuhannya bisa berbeda-beda per jalur, namun untuk **data wajib siswa mengikuti standar field Dapodik** (agar kompatibel untuk pelaporan/ekspor ke Dapodik nanti). Setiap jalur/gelombang juga bisa punya **tes/seleksi sendiri** (jenis tes, jadwal, kriteria kelulusan) yang berbeda atau bahkan tidak ada sama sekali.
- Pembayaran mendukung **2 jalur**: Virtual Account BRI (otomatis) dan **transfer manual** (perlu verifikasi bukti bayar oleh admin keuangan).
- **Nominal tagihan dinamis (bisa gratis)**: baik tagihan pendaftaran maupun daftar ulang tidak selalu berbayar — sebuah `jalur_ppdb` atau `jenis_tagihan` tertentu bisa disetel **Rp 0 (gratis)**, misalnya untuk jalur afirmasi/beasiswa. Jika total tagihan = 0, sistem langsung menandai status `lunas` tanpa perlu proses pembayaran/verifikasi.
- Dashboard admin keuangan & yayasan untuk memonitor status pendaftaran dan penerimaan dana lintas lembaga.

### Non-Tujuan (di luar scope pilot ini)
- Modul Akademik (kurikulum, jurnal mengajar, nilai/rapor) — fase berikutnya.
- E-Sarpra, E-HRD, E-BK — ditunda.
- Payment gateway selain BRI VA (bisa ditambah di fase berikutnya sebagai adapter baru).
- Aplikasi mobile native (asumsi: web responsive dulu).

---

## 3. Aktor & Peran Pengguna

| Aktor | Deskripsi | Cakupan Akses |
|---|---|---|
| **Calon Murid / Wali Calon Murid** | Pendaftar SPMB, publik/belum jadi user internal | Portal pendaftaran, upload dokumen, cek status, bayar tagihan pendaftaran |
| **Murid** | Siswa aktif setelah lulus daftar ulang | Portal siswa (fase akademik menyusul) |
| **Wali Murid** | Orang tua/wali siswa aktif | Lihat tagihan anak, riwayat pembayaran, cicilan |
| **Guru** | Tenaga pendidik, punya *jabatan tugas tambahan* struktural/fungsional | Sesuai jabatan tambahan (lihat 3.1) |
| **Kepala Sekolah** | Pimpinan tiap lembaga | Approval, monitoring lembaganya, laporan |
| **Admin — Administrasi** | Operator data induk, SPMB | Kelola calon murid, verifikasi dokumen, gelombang & jalur |
| **Admin — Keuangan** | Operator tagihan & pembayaran | Setting jenis tagihan, skema cicilan, verifikasi transfer manual, rekonsiliasi VA |
| **Admin — E-Sarpra** | *(fase depan)* | - |
| **Admin — E-HRD** | *(fase depan)* | - |
| **Admin — E-BK** | *(fase depan)* | - |
| **Yayasan (Super Admin)** | Pengawas lintas lembaga | Lihat semua lembaga, konfigurasi tenant, laporan konsolidasi |

### 3.1 Jabatan Tugas Tambahan Guru (referensi regulasi)

Berdasarkan **Permendikdasmen No. 11 Tahun 2025** tentang Pemenuhan Beban Kerja Guru dan juknisnya **Kepmendikdasmen 221/P/2025**, jabatan tugas tambahan guru mencakup dua kelompok besar yang perlu direpresentasikan sebagai *role tambahan* (bukan role utama) di sistem:

**Struktural (nilai ekuivalensi lebih tinggi, umumnya melekat SK pimpinan):**
- Wakil Kepala Sekolah (kurikulum/kesiswaan/sarpras/humas)
- Kepala Perpustakaan, Kepala Laboratorium
- Kepala Program Keahlian (SMK)
- Koordinator BK

**Fungsional/Operasional:**
- Wali Kelas
- **Guru Wali** (peran baru — pendampingan individual siswa dari masuk s.d. lulus, berbeda dari Wali Kelas)
- Pembina OSIS, Pembina Ekstrakurikuler
- Koordinator Pengembangan Kompetensi
- Koordinator Pembelajaran Berbasis Projek (P5)
- Koordinator/anggota Tim Pencegahan dan Penanganan Kekerasan (TPPK)
- Guru Pendidikan Khusus (GPK) / Pembimbing Khusus (inklusi)

> **Implikasi desain:** tabel `guru` perlu relasi many-to-many ke tabel `jabatan_tambahan` (dengan kolom `mulai_periode`, `akhir_periode`, `no_sk`), karena satu guru bisa punya lebih dari satu jabatan tambahan, dan ini akan dipakai lagi di modul HRD/Akademik nanti (perhitungan jam kerja, SK penugasan).

---

## 4. Arsitektur Sistem

### 4.1 Multi-Tenant: Single Database + `tenant_id`
Sesuai keputusan: **satu database**, dengan kolom `lembaga_id` (tenant identifier) di setiap tabel yang datanya spesifik per lembaga.

**Prinsip implementasi di Laravel:**
- Gunakan **global scope** (`BelongsToTenant` trait + Eloquent Global Scope) supaya setiap query otomatis ter-filter `lembaga_id` sesuai konteks user yang login — mengurangi risiko data bocor antar lembaga karena lupa `where()`.
- User **Yayasan (Super Admin)** bisa bypass scope ini untuk lihat lintas lembaga.
- Middleware `ResolveTenant` menentukan `lembaga_id` aktif dari: (a) subdomain per lembaga, atau (b) session/pilihan lembaga setelah login — **perlu diputuskan** (lihat bagian 10, pertanyaan terbuka).
- Tabel referensi bersama (misalnya `jenis_tagihan_master`, `role`, `permission`) tidak perlu `lembaga_id` — hanya tabel transaksional/konfigurasi per lembaga yang perlu.

### 4.2 High-Level Entities (lintas modul)

```
Yayasan (1) ──< Lembaga (N)
Lembaga (1) ──< TahunAjaran (N)
Lembaga (1) ──< User (N, via lembaga_id, kecuali Super Admin)
User (1) ──< Role (N)          // Spatie Permission direkomendasikan
Guru (1) ──< JabatanTambahan (N)
```

---

## 5. Modul 1: SPMB / PPDB

### 5.1 Konsep Utama
Karena kebijakan **bervariasi per lembaga** (multi gelombang & multi jalur), semua entitas SPMB harus dikonfigurasi per `lembaga_id` + `tahun_ajaran_id`.

### 5.2 Entities Inti

| Tabel | Keterangan |
|---|---|
| `tahun_ajaran` | Per lembaga, periode aktif SPMB |
| `gelombang_ppdb` | Gelombang 1, 2, dst — punya `tanggal_buka`, `tanggal_tutup`, `kuota` |
| `jalur_ppdb` | Reguler, Prestasi, Afirmasi, dll — dinamis per lembaga, bisa punya syarat dokumen & tes berbeda |
| `formulir_field` | Definisi field formulir dinamis per `jalur_ppdb`. **Field wajib inti (data siswa) mengacu ke standar Dapodik** (NISN, NIK, nama sesuai akta, tempat/tanggal lahir, nama orang tua, dll) dan tidak bisa dihapus/diubah admin lembaga — hanya field tambahan (di luar standar Dapodik) yang bebas dikonfigurasi per jalur |
| `calon_murid` | Data pendaftar (belum jadi `siswa`), kolom intinya mengikuti struktur field wajib Dapodik |
| `pendaftaran` | Relasi `calon_murid` × `gelombang_ppdb` × `jalur_ppdb`, status: `draft → menunggu_verifikasi → diterima/ditolak → daftar_ulang → aktif` |
| `dokumen_pendaftaran` | Upload akta, KK, rapor, dll, dengan status verifikasi per dokumen — daftar dokumen wajib bisa berbeda per `jalur_ppdb` |
| `seleksi_ppdb` | Konfigurasi tes/seleksi per `jalur_ppdb` × `gelombang_ppdb`: jenis tes, jadwal, bobot/kriteria kelulusan — opsional (jalur tertentu bisa tanpa tes) |
| `hasil_seleksi` | Nilai/hasil tes per `pendaftaran`, dipakai sebagai dasar keputusan diterima/ditolak |

### 5.3 Alur Utama
1. Calon wali murid mendaftar via portal publik → pilih lembaga, jalur, gelombang.
2. Isi formulir + upload dokumen → status `menunggu_verifikasi`.
3. Sistem generate **tagihan pendaftaran** (wajib lunas, lihat Modul 2).
4. Admin verifikasi dokumen & (jika ada tes/seleksi) input hasil seleksi.
5. Kepala Sekolah / Admin menetapkan **diterima/ditolak**.
6. Jika diterima → sistem generate **tagihan daftar ulang** (bisa dicicil).
7. Setelah tagihan daftar ulang lunas/cicilan pertama terbayar (**perlu diputuskan syarat minimalnya**) → `calon_murid` dikonversi jadi `siswa` aktif.

---

## 6. Modul 2: Keuangan

### 6.1 Prinsip Desain Tagihan
Ini bagian paling kritis karena harus mengakomodasi:
- **Tagihan pendaftaran**: harus **lunas**, tidak boleh dicicil.
- **Tagihan daftar ulang & lainnya**: **boleh dicicil**, dengan **batas maksimum jumlah cicilan** yang bisa diatur per jenis tagihan (dan berpotensi berbeda per lembaga).
- **Nominal dinamis, termasuk gratis**: baik tagihan pendaftaran maupun daftar ulang **tidak selalu berbayar** — nominal per `jenis_tagihan` dikonfigurasi bebas oleh Admin Keuangan, termasuk diisi **Rp 0** (misalnya untuk jalur afirmasi/beasiswa). Jika total `tagihan` (gabungan semua item) = 0, sistem otomatis set status `lunas` tanpa melalui alur pembayaran/verifikasi.
- Satu tagihan bisa berisi **beberapa item/jenis biaya sekaligus** (uang pangkal, seragam, buku, dll) — jadi struktur harus mendukung *invoice dengan multiple line items*, bukan satu tagihan = satu jenis biaya. Item dengan nominal 0 tetap tercatat (untuk transparansi/laporan), hanya tidak masuk ke proses pembayaran.

### 6.2 Entities Inti

| Tabel | Keterangan |
|---|---|
| `jenis_tagihan` | Master jenis biaya (pendaftaran, daftar ulang, uang pangkal, SPP, dll), per lembaga. Punya flag `bisa_dicicil` (boolean) dan `maks_cicilan` (int, nullable jika tidak dicicil) |
| `tagihan` (invoice header) | Terhubung ke `calon_murid`/`siswa`, punya `total_tagihan`, `status` (`belum_bayar/dicicil/lunas`), `jatuh_tempo` |
| `tagihan_item` | Line item dari `tagihan` → referensi `jenis_tagihan`, `jumlah` |
| `skema_cicilan` | Jika `tagihan` dicicil: jumlah cicilan disepakati, nominal per termin, jatuh tempo per termin |
| `cicilan` | Detail per termin cicilan: `ke-berapa`, `nominal`, `jatuh_tempo`, `status` |
| `pembayaran` | Transaksi bayar — bisa terhubung ke `tagihan` langsung (lunas) atau ke `cicilan` (per termin). Punya `metode` (`va_bri` / `manual_transfer`), `status` |
| `bukti_transfer` | Untuk metode manual: file upload + `diverifikasi_oleh`, `diverifikasi_at` |
| `va_bri_reference` | Nomor VA yang digenerate per tagihan/cicilan untuk rekonsiliasi otomatis |

### 6.3 Alur Pembayaran

**Jalur A — Virtual Account BRI:**
1. Sistem generate nomor VA unik per tagihan (atau per termin cicilan) via API BRI.
2. Wali murid transfer ke VA tsb.
3. Callback/webhook dari BRI (atau proses cek berkala jika BRI tidak sediakan webhook — **perlu dikonfirmasi ke pihak bank**) → `pembayaran.status = lunas` otomatis.

**Jalur B — Transfer Manual:**
1. Wali murid transfer ke rekening yayasan/lembaga → upload bukti transfer.
2. Status `pembayaran = menunggu_verifikasi`.
3. Admin Keuangan cek mutasi rekening → verifikasi manual → `pembayaran.status = lunas` / `ditolak` (dengan catatan alasan jika ditolak).

### 6.4 Aturan Bisnis Penting
- Sistem harus **mencegah** tagihan pendaftaran diberi opsi cicilan (validasi di level `jenis_tagihan.bisa_dicicil = false` untuk kategori pendaftaran).
- Setiap perubahan status pembayaran/tagihan harus tercatat di **audit log** (siapa, kapan, nilai sebelum/sesudah) — krusial untuk data keuangan.
- Perlu **job/scheduler** untuk reminder jatuh tempo cicilan (kanal notifikasi: email/WA — **perlu diputuskan**, lihat bagian 10).

---

## 7. Matriks Hak Akses (ringkas, level modul)

| Fitur | Calon Wali Murid | Wali Murid | Admin Administrasi | Admin Keuangan | Kepala Sekolah | Yayasan |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| Daftar SPMB | ✅ | – | – | – | – | – |
| Verifikasi dokumen SPMB | – | – | ✅ | – | 👁 | 👁 |
| Keputusan diterima/ditolak | – | – | 👁 usul | – | ✅ | 👁 |
| Setting jenis tagihan & skema cicilan | – | – | – | ✅ | – | 👁 |
| Bayar tagihan | ✅ (punya sendiri) | ✅ (anaknya) | – | – | – | – |
| Verifikasi transfer manual | – | – | – | ✅ | – | 👁 |
| Laporan keuangan lembaga | – | – | – | ✅ | 👁 | 👁 |
| Laporan konsolidasi yayasan | – | – | – | – | – | ✅ |
| Konfigurasi tenant/lembaga baru | – | – | – | – | – | ✅ |

*(✅ = akses penuh, 👁 = lihat/monitor saja)*

---

## 8. Kebutuhan Non-Fungsional

- **Keamanan data keuangan:** enkripsi kolom sensitif (nomor rekening, dokumen), rate-limiting endpoint pembayaran, log setiap akses data pembayaran.
- **Auditability:** semua tabel transaksi keuangan pakai *soft delete* + audit trail (paket `spatie/laravel-activitylog` direkomendasikan).
- **Skalabilitas tenant:** desain harus tahan jika jumlah lembaga bertambah (bukan hardcode nama lembaga di kode).
- **Idempotency:** webhook BRI dan proses generate tagihan harus idempotent (hindari duplikasi saat retry).
- **Konsistensi transaksi:** gunakan DB transaction Laravel (`DB::transaction`) untuk operasi yang menyentuh >1 tabel (misal: buat tagihan + tagihan_item + skema_cicilan sekaligus).

---

## 9. Roadmap Fase Berikutnya

| Fase | Modul | Catatan |
|---|---|---|
| **Fase 1 (Pilot ini)** | SPMB + Keuangan | Semua lembaga ikut bersamaan |
| **Fase 2** | Akademik (kurikulum, jurnal, nilai, e-rapor) | Bergantung pada tabel `siswa`, `guru`, `jabatan_tambahan` dari fase 1 |
| **Fase 3** | E-HRD | Terhubung ke `jabatan_tambahan` untuk perhitungan beban kerja/SK |
| **Fase 3** | E-Sarpra | Independen, bisa paralel dengan HRD |
| **Fase 3** | E-BK | Terhubung ke data siswa & Guru Wali/Koordinator BK |

---

## 10. Asumsi & Pertanyaan Terbuka â Keputusan (divalidasi 12 Juli 2026)

1. **Resolusi tenant â KEPUTUSAN: single domain + slug path untuk portal publik, session untuk internal.**
   Portal publik SPMB pakai path/slug per lembaga (`yayasan.id/sekolah-a/spmb`) agar tetap ada identitas per lembaga di URL tanpa perlu setup wildcard DNS/SSL. Staff/admin internal login di satu domain, `lembaga_id` aktif disimpan di session; staff biasa terkunci ke `lembaga_id` yang di-assign ke akunnya, Super Admin Yayasan bebas switch lembaga. Bisa dimigrasi ke subdomain penuh nanti tanpa mengubah struktur data (`lembaga_id` tetap sama).

2. **Syarat "siswa aktif" â KEPUTUSAN: cicilan pertama daftar ulang terbayar.**
   Begitu termin pertama cicilan daftar ulang lunas, `calon_murid` langsung dikonversi jadi `siswa` aktif. Sisa cicilan tetap jadi tanggungan siswa aktif tsb â tidak menahan proses akademik hanya karena tagihan belum lunas total.

3. **Rekonsiliasi VA BRI â KEPUTUSAN: desain adapter yang mendukung webhook DAN polling.**
   Belum ada akses/dokumentasi resmi API BRI saat draft ini ditulis. Engine pembayaran VA didesain dengan interface adapter yang mendukung kedua mode (callback webhook atau polling scheduler berkala) supaya begitu jenis integrasi BRI dikonfirmasi, tinggal pasang salah satu implementasi tanpa mengubah struktur data `pembayaran`/`va_bri_reference`. Selaras dengan prinsip idempotency di bagian 8.

4. **Kanal notifikasi â KEPUTUSAN: email dulu untuk pilot.**
   Reminder jatuh tempo cicilan & update status pendaftaran cukup lewat email (Laravel Mail/SMTP) di fase pilot. WhatsApp (Fonnte/Wablas/resmi WA Business API) ditunda ke fase berikutnya sebagai channel tambahan â butuh biaya provider & proses approval yang tidak perlu jadi blocker pilot.

5. **Approval berjenjang â KEPUTUSAN: massal per gelombang, dengan opsi override individual.**
   Kepala Sekolah bisa approve/reject banyak calon murid sekaligus dalam satu gelombang (misal berdasarkan ranking hasil seleksi), namun tetap bisa membuka detail satu calon murid untuk approve/reject manual untuk kasus khusus/pengecualian.

6. **Dokumen wajib per jalur â KEPUTUSAN: dikonfigurasi bebas oleh Admin.**
   Daftar dokumen wajib per `jalur_ppdb` dibuat sebagai tabel konfigurasi (jenis dokumen, wajib/opsional, per jalur), mengikuti pola yang sama seperti `formulir_field` dan `seleksi_ppdb`. Konsisten dengan prinsip "jalur PPDB dinamis" di bagian 2 â menghindari migrasi/refactor begitu ada lembaga yang butuh dokumen berbeda per jalur.

---

## 11. Pembagian Modul Kerja untuk Development (untuk sprint dengan Claude + Antigravity)

Supaya bisa dikerjakan bertahap dengan AI-assisted coding, disarankan urutan berikut (tiap modul = satu unit kerja/branch yang bisa selesai dan diuji sendiri sebelum lanjut):

| # | Modul Kerja | Isi |
|---|---|---|
| M0 | **Fondasi** | Setup Laravel, autentikasi, RBAC (role & permission), struktur multi-tenant (`lembaga`, global scope), master data (tahun ajaran) |
| M1 | **SPMB — Konfigurasi** | CRUD gelombang, jalur, formulir per lembaga (panel admin) |
| M2 | **SPMB — Portal Publik** | Form pendaftaran calon murid, upload dokumen, cek status (tanpa login penuh / pakai token akses) |
| M3 | **SPMB — Verifikasi & Keputusan** | Panel admin verifikasi dokumen, panel Kepala Sekolah untuk keputusan diterima/ditolak |
| M4 | **Keuangan — Master** | CRUD jenis tagihan, skema cicilan (aturan bisa/tidak bisa cicil, maks cicilan) |
| M5 | **Keuangan — Invoicing Engine** | Generate tagihan otomatis dari event SPMB (daftar → tagihan pendaftaran; diterima → tagihan daftar ulang), termasuk pemecahan ke `cicilan` |
| M6 | **Keuangan — Payment: VA BRI** | Integrasi API BRI, generate VA, handle callback/polling |
| M7 | **Keuangan — Payment: Manual Transfer** | Upload bukti bayar, panel verifikasi admin |
| M8 | **Dashboard & Laporan** | Dashboard Admin Keuangan (per lembaga), Kepala Sekolah, dan Yayasan (konsolidasi lintas lembaga) |
| M9 | **Notifikasi** | Reminder jatuh tempo, notifikasi status pendaftaran (channel sesuai keputusan poin 10.4) |

Urutan ini sengaja diletakkan agar **M0–M3 (SPMB) bisa demo duluan** tanpa menunggu Keuangan selesai total, lalu **M4–M7 (Keuangan)** menyusul dan terhubung ke data SPMB yang sudah ada.

---

## 12. Metrik Keberhasilan Pilot

- Semua lembaga yayasan berhasil menjalankan SPMB penuh (buka gelombang → tutup → siswa aktif) di sistem tanpa proses manual paralel.
- % tagihan yang terverifikasi/lunas otomatis (VA) vs manual, sebagai baseline untuk evaluasi adopsi metode pembayaran.
- Waktu rata-rata verifikasi dokumen & transfer manual berkurang dibanding proses lama (jika ada baseline).
- Tidak ada insiden kebocoran data antar lembaga (validasi arsitektur tenant_id).

---

*Dokumen ini adalah draft diskusi — bagian 10 (pertanyaan terbuka) perlu dijawab sebelum breakdown ke task/ticket level untuk sprint pertama.*
