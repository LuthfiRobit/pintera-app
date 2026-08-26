# Peta Pengembangan Pintera — Memori Lokal

> **Cermin dari Artifact**: https://claude.ai/code/artifact/ee114dde-1058-4bff-a43a-be904f90d667
> Baca file ini dulu sebelum fetch artifact via network — hanya fetch ulang kalau ada perubahan besar yang belum tercermin di sini (dan update file ini + artifact bersamaan setelahnya).
> Terakhir disinkronkan: 2026-08-26.

Audit menyeluruh platform SaaS Pintera — apa yang sudah ada, perlu diperbaiki/direfaktor, dan yang belum ada sama sekali. Disusun dari pembacaan kode & spec/plan langsung, bukan asumsi.

**Ringkasan angka**: 31 fitur baru belum ada · 4 ada-perlu-perbaikan · 3 ada-perlu-refactor · 7 sudah lengkap.

**Legenda status**: `Ada` / `Parsial` / `Belum Ada`. **Jenis pekerjaan**: Fitur Baru / Perbaikan / Refactor Arsitektur / Refactor UI. **Prioritas**: Tinggi / Sedang / Rendah.

---

## 🔴 Temuan Bug Prioritas Tinggi (26 Agustus 2026)

**Akun staf lembaga via form Pengguna generik jadi "profil belum tertaut"** — ✅ **Bagian dashboard SUDAH DIPERBAIKI 26 Agustus 2026.**
`Admin → Pengguna → Tambah` membiarkan admin mencentang role fungsional lembaga (Kepala Sekolah, Admin Administrasi, Bendahara Lembaga, Operator Akademik, Admin SDM, Admin Sarpras, dll) TANPA pernah membuat baris `Guru` atau `Karyawan` yang menyertainya (`UserController::store()` nol pemanggilan `Guru::create`/`Karyawan::create`). Ini masih **bug produk nyata** yang belum tertutup — form Pengguna generik masih bisa membuat akun cacat kalau admin tidak lewat jalur resmi (`Admin → Guru`/`Admin → Karyawan`).

**Yang sudah diperbaiki**: `DashboardController` (branch `pegawai_lembaga`/`pegawai_yayasan`, `DashboardController.php:249`) sekarang resolve profil pegawai lewat `Guru` ATAU `Karyawan` — sama seperti pola `resolvePegawai()` di `EmployeeQrCodeController`/`PengajuanIzinCutiController`. Akun sarpras.sd@demo.test (profilnya di `Guru`, jenis_ptk=tenaga_administrasi) sekarang tampil benar, bukan lagi "belum tertaut". Ikut ketemu & diperbaiki 2 bug turunan: `PenugasanShift` query hardcode morph type ke `Karyawan::class` (sekarang dinamis `$karyawan::class`), dan `Karyawan::class` dipakai tanpa `use` import (diam-diam resolve ke namespace controller yang salah, bikin filter shift selalu kosong tanpa error).

**Keterbatasan yang masih ada**: `DashboardStatsService::statistikSisaKuotaCuti()` masih Karyawan-only (skema `KuotaCutiConfig` sudah siapkan kolom `jenis_ptk` untuk jalur Guru, tapi service-nya belum diimplementasikan) — staf ber-profil Guru akan selalu tampil "Sisa Kuota Cuti: Belum dikonfigurasi", bukan crash, tapi juga bukan data nyata. Effort Kecil kalau mau dilengkapi.

**Yang BELUM diperbaiki**: root cause di `UserController::store()` sendiri — form Pengguna generik masih bisa dipakai untuk membuat akun ber-role fungsional lembaga tanpa profil kepegawaian apa pun. Rekomendasi tetap BUKAN auto-create profil (butuh NIK asli + jenis_karyawan_id yang tidak tersedia di form ini, berisiko fabrikasi data), tapi guardrail/validasi di form tsb — belum dikerjakan.

Lokasi: `app/Http/Controllers/Admin/DashboardController.php:248-295` (sudah fix), `app/Http/Controllers/Admin/UserController.php::store()` (belum fix).

**Peta tabel PTK vs Karyawan** (referensi arsitektur):
| Jenis staf | Tabel profil benar | Cara buat |
|---|---|---|
| Guru, Kepsek, Admin Akademik/Administrasi/Sarpras, Guru BK (fungsi akademik/manajerial) | `Guru` (kolom `jenis_ptk`: guru_kelas/guru_mapel/kepala_sekolah/tenaga_administrasi/guru_bk) | `Admin → Guru` |
| Satpam, cleaning service, sopir, psikolog, konselor pool (staf umum) | `Karyawan` (`jenis_karyawan_id` → `JenisKaryawanMaster`, fleksibel) | `Admin → Karyawan` |

---

## 🟡 Technical Debt — `ElemenCp` (Sprint 1 Akademik) kemungkinan tumpang tindih dengan `MataPelajaran.tipe=aspek_perkembangan` (ditemukan 26 Agustus 2026)

Saat mengerjakan Sprint 1 fondasi akademik multi-jenjang (subjek polymorphic `MataPelajaran`|`ElemenCp`), ternyata desain ASLI modul Akademik (`docs/superpowers/specs/2026-07-24-presensi-asesmen-design.md`, ditulis 1 bulan sebelum Sprint 1) **sudah** merancang solusi untuk PAUD-tanpa-mata-pelajaran: `MataPelajaran.tipe = 'aspek_perkembangan'` (per-lembaga, dibuat lewat halaman Mata Pelajaran biasa yang sudah ada — `app/Http/Controllers/Lembaga/Akademik/MataPelajaranController.php`), dengan kutipan eksplisit *"struktur relasi ke Jadwal, Sesi Pembelajaran, dan Asesmen tetap satu jalur untuk keduanya"* — artinya cukup satu FK `MataPelajaran` biasa untuk semua jenjang, tidak perlu model terpisah.

**Implikasi**: solusi Sprint 1 (bikin model `ElemenCp` baru + polymorphic `subjek_type`/`subjek_id`) mungkin lebih rumit dari yang seharusnya — desain minimal yang konsisten dengan arsitektur asli adalah cukup jadikan `mata_pelajaran_id` nullable-tapi-tetap-FK-biasa ke `MataPelajaran`, buang kolom `elemen_cp` enum yang redundan, TANPA perlu morph sama sekali.

**Bonus temuan**: `ElemenCp` Sprint 1 cuma punya **3 elemen** (nilai_agama_moral, jati_diri, literasi_steam), padahal desain asli menyebut **"6 aspek STPPA"** (Standar Tingkat Pencapaian Perkembangan Anak, standar resmi PAUD Indonesia) — jadi selain kemungkinan arsitektur berlebih, datanya sendiri juga tidak lengkap dibanding standar resminya.

**Konteks kurikulum lain yang sudah diantisipasi tim sebelumnya** (dari desain yang sama, §10 Out of Scope):
- **K13/KTSP**: *"`komponen_penilaian` sudah dinamai generik supaya siap menampung KD, tapi alur/agregasi nilai KD belum dirancang detail"* — multi-kurikulum (bukan cuma Kurikulum Merdeka) sudah diantisipasi, belum dibangun.
- **Kemenag (madrasah)**: enum `KelompokMataPelajaran` sudah ada `AgamaKemenag` ("KMA 450") dan `ProjekP5Ppra` (PPRA = versi Kemenag dari P5) — variasi kurikulum antar naungan sudah nyata di kode, belum ada alur kerja penuh.
- **P5**: *"struktur penilaian per Dimensi P5, lintas mapel/kolaboratif, berbeda dari asesmen mapel reguler"* — konsisten dengan keputusan sebelumnya bahwa P5 butuh domain terpisah (bukan sekadar subjek baru di bawah KomponenPenilaian).

**Keputusan (26 Agustus 2026)**: TIDAK dibongkar sekarang — `ElemenCp` tetap dipakai apa adanya (sudah jalan, sudah full-test-covered). Dicatat sbg technical debt untuk direview nanti (kemungkinan konsolidasi `ElemenCp` → `MataPelajaran.tipe=aspek_perkembangan`, atau sebaliknya memperkaya `ElemenCp` jadi 6 aspek + dukungan tambahan). Sprint 3 (Curriculum Phase) tetap lanjut di atas fondasi Sprint 1 yang ada, dengan catatan desainnya WAJIB config-driven (bukan hardcoded) mengingat variasi kurikulum yang sekarang terkonfirmasi nyata dari riset ini.

---

## 1. Platform / Admin SaaS
*Dikelola tim penyedia, di atas semua Yayasan. Level paling kosong dari audit — identitas (`platform_super_admin`, `scope_level='platform'`) sudah ada sejak RBAC v2, tapi PRODUKnya (onboarding, feature-gating, billing, dashboard agregat) sebagian besar belum.*

### Manajemen Tenant
- **Onboarding Yayasan+Lembaga baru (UI operator)** — Belum Ada. Role identitas sudah ada, tapi tidak ada route/controller onboarding, masih manual lewat seeder/DB. *Prioritas: tergantung model bisnis (self-service vs white-glove).*
- **Feature-gating per Yayasan** — Belum Ada (grep `feature_flag`/`module_access`/`paket` nol hasil). Blocker: migrasi domain SPMB (Keuangan sudah selesai). *Prioritas Tinggi.*
- **Cloning template data master lintas tenant** — Parsial (`SpmbKonfigurasiDuplikasi` cuma antar Tahun Ajaran pada lembaga sama, bukan lintas tenant). *Prioritas Rendah.*
- **SSO / manajemen user & role lintas yayasan** — Parsial (Halaman Pengguna 25 Agustus 2026 sudah bisa filter lintas yayasan untuk `platform_super_admin`, model lain masih terisolasi). *Prioritas Rendah, niche.*

### Billing & Dashboard Provider
- **Billing/langganan Yayasan → Provider** — Belum Ada (modul Keuangan arahnya berlawanan: Yayasan→OrangTua). Blocker: feature-gating. *Prioritas Tinggi kalau ini sumber revenue utama.*
- **Dashboard Super Admin lintas SEMUA yayasan** — ✅ **Ada** (25 Agustus 2026). Cabang `widestScopeLevel() === 'platform'` di `DashboardController` mengagregasi seluruh yayasan (total lembaga/guru/pengguna, ringkasan per-yayasan, tren pertumbuhan). Lokasi: `Admin\DashboardController::index()`, `admin/dashboard/platform.blade.php`.
- **Backup terjadwal** — Belum Ada (`routes/console.php` cuma schedule bisnis). **Prioritas Tinggi — "Tidak Disarankan Diskip"**, risiko keamanan data pelanggan, effort Kecil. Quick win kandidat utama.
- **Audit-log viewer terpusat** — Parsial (Spatie Activitylog sudah dipakai luas, tidak ada UI viewer). Effort Kecil–Sedang. Quick win kandidat kedua.

## 2. Level Yayasan
*Fondasi (dashboard rekap, CRUD lembaga) solid. Yang belum: kebijakan terpusat & laporan gabungan lintas domain.*

- **Dashboard yayasan agregat** — ✅ Ada.
- **CRUD Lembaga** — ✅ Ada.
- **Kebijakan terpusat (SPP/SOP/PPDB) cascade** — Belum Ada. Prioritas Rendah–Sedang.
- **Laporan konsolidasi lintas-domain** — Parsial (baru Sarpras via `RekapAsetGlobalController`). Prioritas Sedang.
- **Mutasi siswa antar lembaga** — Belum Ada. Prioritas Rendah–Sedang.
- **Guru mengajar lintas lembaga (many-to-many)** — Belum Ada, `Guru.lembaga_id` masih belongsTo tunggal. Refactor Data Model besar, Prioritas Rendah.

## 3. Level Lembaga / Sekolah
*Level paling matang fungsional (Akademik, Kasus/BK, Kehadiran SDM, Keuangan, Sarpras/Pengadaan semua selesai). Gap: fitur belum ada + utang arsitektur/UI.*

### Kepegawaian & Keuangan Internal
- **Payroll/penggajian pegawai** — Belum Ada (grep "gaji"/"payroll" nol hasil). Kehadiran SDM sudah jadi input siap pakai. **Prioritas Tinggi**, effort Besar. Satu-satunya item tersisa di "Fase 3" roadmap (fitur besar independen).
- **Buku Kas & BOS** — Belum Ada. Wajib untuk sekolah negeri/BOS. Prioritas Sedang.
- **Payment gateway selain BRI** — Belum Ada (`PaymentGatewayInterface` sudah siap sbg abstraksi). Prioritas Sedang.

### Kesiswaan
- **e-Ijazah/SKL** — Belum Ada. Prioritas Sedang.
- **Pelanggaran siswa (disiplin)** — Belum Ada, bisa reuse `App\Domains\Workflow`. Prioritas Sedang.
- **Prestasi siswa terstruktur** — Parsial (baru kolom JSON). Prioritas Rendah.
- **Ekstrakurikuler (pendaftaran+presensi+jadwal)** — Parsial (cuma CRUD master). Prioritas Sedang.
- **Data Alumni** — Parsial (`StatusSiswa::Lulus` ada, belum ada modul). Prioritas Rendah.

### Fasilitas & Komunikasi
- **Perpustakaan** — Belum Ada sama sekali. Prioritas Sedang.
- **Broadcast WA massal ke wali murid** — Belum Ada, tapi gateway Fonnte sudah real & jalan — tinggal UI di atasnya. **Effort kecil, dampak tinggi.**

### Utang Arsitektur & UI (bukan fitur baru)
- 🔴 **Bug "profil belum tertaut"** — lihat bagian atas file ini.
- **Serap Data Induk blast-radius sempit** (`JenisKaryawanMaster`, `JabatanTambahanMaster`, `MataPelajaran`) — ✅ Selesai 23 Agustus 2026 di `refactor-v1`.
  - Sisanya TETAP di `app/Models/` selamanya (keputusan arsitektur §3.2): `Lembaga` (~382 pemakai), `Kelas`, `Siswa`, `TahunAjaran`, `Semester`, `WhatsAppTemplate` — dipakai lintas domain, blast radius terlalu besar untuk dipindah.
- **Migrasi domain Keuangan** ke `app/Domains/Keuangan` — ✅ Selesai 24 Agustus 2026 (SP1-5 semua, full test suite hijau tiap sub-project).
- **Migrasi domain SPMB** ke `app/Domains/Spmb` — Belum Ada, **SENGAJA DITUNDA** (keputusan 24 Agustus 2026 — user mau rombak ulang modul SPMB, migrasi sekarang jadi kerja terbuang). Menu sidebar SPMB disembunyikan (bukan dihapus). Satu-satunya blocker tersisa untuk Feature-Gating.
- **Halaman UI lama**: Pembayaran, Tagihan, SPMB-Pendaftaran, Tahun Ajaran (create) — masih pattern `<x-panel>`/`text-brass`/`bg-paper` lama. *(Dashboard per-role SUDAH direstyle 25 Agustus 2026 — dikeluarkan dari daftar ini.)* Prioritas Rendah, kosmetik murni.
- **Rotasi otomatis shift (SDM)** — Belum Ada. Prioritas Rendah.
- **Penyempurnaan Kuota Cuti** (per-jenis, Izin/Sakit, prorata) — Parsial, sengaja di luar cakupan awal. Prioritas Rendah.

## 4. Level Orang Tua / Wali
*Dasar (dashboard anak, tagihan online, notifikasi transaksional) berjalan. Belum: komunikasi dua arah & transparansi presensi harian.*

- **Dashboard anak (tagihan & ringkasan)** — ✅ Ada.
- **Rapor digital dengan tanda tangan digital** — Belum Ada (PDF masih kolom kosong utk ttd manual). Prioritas Rendah–Sedang.
- **Chat dua arah dgn wali kelas/guru BK** — Belum Ada, butuh infra real-time baru. Pertimbangkan MVP (komentar per-kasus) dulu. Prioritas Rendah–Sedang.
- **Notifikasi presensi & penjemputan (tap-in/tap-out)** — Belum Ada, tapi data presensi siswa SUDAH ADA — cuma perlu hook notifikasi baru. **Fitur transparansi paling dicari ortu, effort relatif kecil.** Prioritas Sedang–Tinggi.

## 5. Level Siswa
*Gap paling mendesak di seluruh audit SUDAH TERTUTUP 25 Agustus 2026.*

- **Portal siswa: dashboard nilai/presensi/jadwal** — ✅ **Ada** (25 Agustus 2026). Kelas & profil, tagihan belum lunas, rekap presensi bulan berjalan, 5 nilai terbaru, jadwal hari ini — semua read-only dari data existing.
- **Pengajuan tugas & submit file** — Belum Ada, Portal Siswa sudah jadi rumahnya. Prioritas Sedang.
- **Pengajuan izin siswa mandiri** — Belum Ada, bisa reuse `App\Domains\Workflow` (pola sama izin/cuti SDM). Prioritas Sedang.
- **Kartu pelajar digital (QR)** — Belum Ada, pola QR sudah terbukti di `GenerateEmployeeQrTokenAction`. Prioritas Rendah.
- **E-learning** — Belum Ada, nice-to-have jangka panjang. Prioritas Rendah–Sedang.

## Fitur Pembeda SaaS
*Satu hal sudah beres nyata (WhatsApp Gateway). Sisanya investasi diferensiasi jangka menengah-panjang.*

- **WhatsApp Gateway** — ✅ Ada & nyata (Fonnte API sungguhan).
- **Multi-kurikulum** (K13/Kurmer/Cambridge/Pesantren) — Belum Ada. Besar, Prioritas Rendah kecuali target pasar spesifik.
- **Integrasi Dapodik/EMIS/Dukcapil/BPJS** — Parsial (cuma field `kode_dapodik`, bukan integrasi API). Prioritas Rendah–Sedang.
- **AI Asisten** — Belum Ada, butuh kematangan data dulu. Prioritas Rendah untuk saat ini.
- **White-label** — Belum Ada, butuh Manajemen Tenant dulu. Prioritas Rendah–Sedang.
- **Offline Mode (PWA)** — Belum Ada. Prioritas Rendah.
- **Public API** — Belum Ada, sebaiknya setelah Feature-Gating. Prioritas Rendah.

---

## Urutan Pengerjaan (10 Fase, berdasarkan dependency nyata)

**Fase 0 — Fondasi sudah beres** (tidak perlu tindakan): RBAC v2, Kehadiran SDM, Presensi Akademik siswa, Migrasi domain Keuangan.

**Fase 1 — Migrasi arsitektur (prasyarat Feature-Gating)**: hanya tersisa **Migrasi domain SPMB** — SENGAJA DITUNDA sampai rombakan SPMB dimulai.

**Fase 2 — Fondasi Platform SaaS** (bisa diskip total kalau model bisnis tetap 1-instalasi-custom-per-yayasan): Feature-Gating, Manajemen Tenant, Billing SaaS. *(Dashboard Super Admin lintas yayasan sudah dikeluarkan dari fase ini — sudah selesai.)*

**Fase 3 — Fitur besar independen**: **Payroll/Penggajian** (satu-satunya tersisa — Portal Siswa sudah selesai & dikeluarkan dari fase ini).

**Fase 4 — Turunan Portal Siswa**: Pengajuan izin siswa mandiri, Pengajuan tugas & submit file, Kartu pelajar digital (bisa diskip), E-learning (bisa diskip).

**Fase 5 — Quick wins** (effort kecil, dampak langsung): **Backup terjadwal** (tidak disarankan diskip), Notifikasi presensi & penjemputan, Broadcast WA massal, Audit-log viewer (bisa diskip).

**Fase 6 — Kesiswaan & Fasilitas Lembaga** (independen semua): Pelanggaran siswa, Ekstrakurikuler, e-Ijazah/SKL, Buku Kas & BOS (bisa diskip kalau non-BOS), Payment gateway tambahan (bisa diskip), Perpustakaan (bisa diskip), Prestasi siswa (bisa diskip), Data Alumni (bisa diskip).

**Fase 7 — Level Yayasan**: Laporan konsolidasi lintas-domain, Kebijakan terpusat cascade (bisa diskip), Mutasi siswa antar lembaga (bisa diskip), Guru lintas lembaga (bisa diskip).

**Fase 8 — Penyempurnaan Ortu & SDM Lanjutan**: Rapor tanda tangan digital, Chat dua arah (bisa diskip — mulai versi MVP), Rotasi shift otomatis (bisa diskip, sudah diputuskan ditunda), Penyempurnaan Kuota Cuti (bisa diskip).

**Fase 9 — Refactor UI kosmetik** (aman dicicil kapan saja): Halaman lama Pembayaran/Tagihan/SPMB-Pendaftaran/Tahun Ajaran create.

**Fase 10 — Diferensiator SaaS jangka panjang** (paling akhir secara sengaja): Multi-kurikulum, Integrasi Dapodik/EMIS/dst, AI Asisten, White-label, Offline Mode, Public API.

---

## Cara update memori ini
Kalau ada perubahan besar pada roadmap (fitur baru selesai, prioritas berubah, bug baru ditemukan): update artifact via `Artifact` tool (publish ke URL yang sama), lalu salin ringkasan perubahannya ke file ini juga supaya tetap sinkron.
