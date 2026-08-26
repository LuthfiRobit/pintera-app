# Peta Pengembangan Pintera — Memori Lokal

> **Cermin dari Artifact**: https://claude.ai/code/artifact/ee114dde-1058-4bff-a43a-be904f90d667
> Baca file ini dulu sebelum fetch artifact via network — hanya fetch ulang kalau ada perubahan besar yang belum tercermin di sini (dan update file ini + artifact bersamaan setelahnya).
> Terakhir disinkronkan: 2026-08-26.

Audit menyeluruh platform SaaS Pintera — apa yang sudah ada, perlu diperbaiki/direfaktor, dan yang belum ada sama sekali. Disusun dari pembacaan kode & spec/plan langsung, bukan asumsi.

**Ringkasan angka**: 31 fitur baru belum ada · 4 ada-perlu-perbaikan · 3 ada-perlu-refactor · 7 sudah lengkap.

**Legenda status**: `Ada` / `Parsial` / `Belum Ada`. **Jenis pekerjaan**: Fitur Baru / Perbaikan / Refactor Arsitektur / Refactor UI. **Prioritas**: Tinggi / Sedang / Rendah.

---

## 🔴 Temuan Bug Prioritas Tinggi (26 Agustus 2026)

**Akun staf lembaga via form Pengguna generik jadi "profil belum tertaut"**
`Admin → Pengguna → Tambah` membiarkan admin mencentang role fungsional lembaga (Kepala Sekolah, Admin Administrasi, Bendahara Lembaga, Operator Akademik, Admin SDM, Admin Sarpras, dll) TANPA pernah membuat baris `Guru` atau `Karyawan` yang menyertainya (`UserController::store()` nol pemanggilan `Guru::create`/`Karyawan::create`). Akibatnya begitu login, dashboard staf (`DashboardController.php:248-295`, cuma cek `$user->karyawan()`) menampilkan "Profil karyawan Anda belum tertaut" dan fitur self-service SDM (QR presensi, ajukan izin/cuti) 404.

Ini **bug real produksi**, bukan cuma artefak seeder — jalur resmi yang benar (`Admin → Guru` untuk PTK: guru/kepsek/tenaga administrasi/guru BK; `Admin → Karyawan` untuk staf umum non-PTK spt satpam/cleaning service, pakai `AkunKaryawanGenerator`) TIDAK dipaksakan; form Pengguna generik jadi jalur pintas yang membuat akun cacat.

**Fix yang direkomendasikan** (BUKAN auto-create profil — butuh NIK asli + jenis_karyawan_id yang tidak tersedia di form Pengguna, berisiko fabrikasi data & duplikasi profil paralel dgn Guru): ubah `DashboardController` (dan pola serupa lain) supaya resolusi pegawai cek `Guru` ATAU `Karyawan` — sama seperti pola `resolvePegawai()` di `EmployeeQrCodeController`/`PengajuanIzinCutiController` yang sudah benar.

Lokasi: `app/Http/Controllers/Admin/DashboardController.php:248-295`, `app/Http/Controllers/Admin/UserController.php::store()`.

**Peta tabel PTK vs Karyawan** (referensi arsitektur):
| Jenis staf | Tabel profil benar | Cara buat |
|---|---|---|
| Guru, Kepsek, Admin Akademik/Administrasi/Sarpras, Guru BK (fungsi akademik/manajerial) | `Guru` (kolom `jenis_ptk`: guru_kelas/guru_mapel/kepala_sekolah/tenaga_administrasi/guru_bk) | `Admin → Guru` |
| Satpam, cleaning service, sopir, psikolog, konselor pool (staf umum) | `Karyawan` (`jenis_karyawan_id` → `JenisKaryawanMaster`, fleksibel) | `Admin → Karyawan` |

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
