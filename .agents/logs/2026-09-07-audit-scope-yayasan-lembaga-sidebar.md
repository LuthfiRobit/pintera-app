# Audit: Status Dukungan "Semua Lembaga" & "Switch Lembaga" per Menu Sidebar

> **Tanggal**: 7 September 2026
> **Konteks**: Scan ulang menyeluruh setelah plan `.agents/logs/2026-09-07-scope-yayasan-lembaga-menu-fix.md` selesai (7 task, 15 commit). Mencakup SEMUA item sidebar (`resources/views/layouts/sidebar.blade.php`) + nav button di luar sidebar (topbar, dashboard). Tujuan: checklist definitif untuk kerja perbaikan per-menu berikutnya — jangan perbaiki dari asumsi, cek tabel ini dulu.

## Definisi 2 Kolom

- **Semua Lembaga** — saat aktor scope yayasan berada di mode "Semua Lembaga" (`session('active_lembaga_id')` kosong), apakah data yang tampil BENAR (agregat semua lembaga milik yayasan), bukan kosong/salah/nol diam-diam?
- **Switch Lembaga** — saat aktor sudah memilih 1 lembaga spesifik via switcher topbar, apakah data menyempit dengan benar ke lembaga itu saja (perilaku sama seperti staff lembaga itu sendiri)?

Simbol: ✅ didukung & terverifikasi | ⚠️ didukung tapi ada catatan minor | 🔴 **bug nyata, belum didukung** | 🟡 **butuh keputusan produk** (bukan bug teknis biasa) | ➖ konsep tidak berlaku (yayasan-wide, wajib-1-lembaga by design, atau personal/identitas)

---

## Akses & Peran

| Menu | Semua Lembaga | Switch Lembaga | Catatan |
|---|---|---|---|
| Pengguna | ✅ | ✅ | `UserController` — pola scope paling matang di codebase |
| Peran | ➖ | ➖ | Yayasan-wide by design (role = definisi sistem) |

## Data Induk

| Menu | Semua Lembaga | Switch Lembaga | Catatan |
|---|---|---|---|
| Lembaga | ➖ | ➖ | Yayasan-wide by design |
| Pengaturan Yayasan | ➖ | ➖ | Yayasan-wide by design (1 baris data) |
| Tahun Ajaran | ✅ | ✅ | Otomatis via `TenantScope` |
| Kelas | ✅ | ✅ | Otomatis via `TenantScope` |
| Mata Pelajaran | ✅ | ✅ | Otomatis via `TenantScope` |
| Guru | ✅ | ✅ | Otomatis via `TenantScope` |
| Karyawan | ✅ | ✅ | Filter manual, sudah didesain benar (pola "pool" lembaga_id/yayasan_id) |
| Jenis Karyawan Master | 🟡 | ➖ | `JenisKaryawanMasterController` — tabel `jenis_karyawan_master` TIDAK punya kolom `lembaga_id`/`yayasan_id` sama sekali (dikonfirmasi dari `mysql-schema.sql`). Datanya shared lintas **SEMUA yayasan di sistem**, bukan cuma lintas lembaga 1 yayasan. Butuh keputusan produk: disengaja (katalog nasional) atau harus dibatasi per-yayasan? |
| Jabatan Tambahan Master | 🟡 | ➖ | Sama persis kondisi di atas (`JabatanTambahanMasterController`, tabel `jabatan_tambahan_master` tanpa kolom tenant) |
| Siswa | ✅ | ✅ | Otomatis via `TenantScope` |
| Orang Tua | ✅ | ✅ | Diperbaiki Task 1 plan kemarin (dulu bocor lintas-yayasan di `index()`) |
| Template WhatsApp | 🟡 | ➖ | Sama kondisi seperti Jenis Karyawan/Jabatan Tambahan (`WhatsAppTemplateController`, tabel `whatsapp_template` tanpa kolom tenant) |

## Sarana & Prasarana

| Menu | Semua Lembaga | Switch Lembaga | Catatan |
|---|---|---|---|
| Gedung & Bangunan | ✅ | ✅ | Diperbaiki Task 6 (kartu ringkasan) |
| Ruangan & Fasilitas | ✅ | ✅ | Diperbaiki Task 6 (reuse closure `is_shared` sendiri) |
| Kategori Aset | ✅ | ✅ | Diperbaiki Task 6 |
| Aset & Inventaris | ✅ | ✅ | Sudah benar dari awal (`AsetBarangController`) |
| Riwayat Mutasi | ✅ | ✅ | Sudah benar dari awal (`MutasiAsetController`) |
| Usulan Pengadaan | ✅ | ✅ | List + kartu ringkasan diperbaiki Task 6. Buat usulan baru tetap wajib pilih 1 lembaga (by design, bukan bug) |
| Approval Pengadaan | ➖ | ➖ | Selalu yayasan-scoped by design, tidak tergantung switcher |
| Pencairan Kas Pengadaan | ➖ | ➖ | Sama, yayasan-scoped by design |
| Audit LPJ Belanja | ➖ | ➖ | Sama, yayasan-scoped by design |
| Rekap Aset Yayasan | ➖ | ➖ | Khusus yayasan (hidden dari lembaga-scope), memang selalu agregat |

## Keuangan

| Menu | Semua Lembaga | Switch Lembaga | Catatan |
|---|---|---|---|
| Virtual Account | 🔴 (sebagian) | ✅ | Halaman utama `index()` diperbaiki Task 4 & BENAR. TAPI 3 method lain di `VirtualAccountController` masih bug: **`calonGenerate()`** (daftar calon siswa utk VA baru, `Siswa::where('lembaga_id', $lembagaId)` tanpa null-guard → kosong total di mode agregat), **`generate()`** (generate VA massal, sama persis, 0 siswa diproses), **`export()`** lewat `App\Exports\VirtualAccountExport` (`whereHas('wallet.siswa', fn($q)=>$q->where('lembaga_id', $this->lembagaId))` tanpa guard → file Excel kosong). |
| Jenis Tagihan | ✅ | ✅ | Otomatis via `TenantScope` |
| Verifikasi Transfer Manual | ✅ | ✅ | `index()` diperbaiki Task 4. `approve()`/`reject()` per-baris memang wajib 1 lembaga (endpoint single-resource, itu benar/by design) |

## Kehadiran SDM

| Menu | Semua Lembaga | Switch Lembaga | Catatan |
|---|---|---|---|
| Daftar Kehadiran | ✅ | ✅ | Otomatis via `TenantScope` |
| Scan QR | ➖ (sengaja disembunyikan+guard 422) | ✅ | By design (Task 3) — aksi fisik on-site, tidak masuk akal diagregat |
| Persetujuan Izin/Cuti | ✅ | ✅ | Otomatis via `TenantScope` |
| Konfigurasi | ✅ | ✅ | Diperbaiki Task 5 (5 query gabungan nasional+semua lembaga). `titikAbsen`/`guruList`/`karyawanList`/`penugasanShiftList` tetap wajib 1 lembaga (form tambah baru, by design) |

## Pendampingan

| Menu | Semua Lembaga | Switch Lembaga | Catatan |
|---|---|---|---|
| Triase Kasus | ✅ | ✅ | Otomatis via `TenantScope` |
| Log Akses Klinis | ✅ | ✅ | Diperbaiki Task 1 (dulu bocor lintas-yayasan) |
| Kasus Terhapus | ✅ | ✅ | Diperbaiki Task 1 |

## Akademik

| Menu | Semua Lembaga | Switch Lembaga | Catatan |
|---|---|---|---|
| Pengaturan Akademik | ➖ (wajib pilih 1) | ✅ | By design — pengaturan per-lembaga, tidak ada konsep agregat |
| Kurikulum Assignment | ✅ | ✅ | Sudah benar (aggregate + global assignment handling) |
| Pola Jam | ✅ | ✅ | Otomatis via `TenantScope` |
| Jadwal Pelajaran | ⚠️ | ✅ | Dropdown guru/mapel di `index()`/`create()` bisa kurang terfilter di 1 edge case (user platform-level, belum ada kelas/tahun ajaran dipilih) — risiko RENDAH karena `store()`/`update()` (baris 211-239, 356-375) punya validasi eksplisit yang menolak (422) kombinasi guru-lembaga yang salah. Diterima as-is. |
| Perangkat Ajar (RPP) | ✅ | ✅ | Sudah benar (`TenantContext`) |
| Komponen Penilaian (TP) | ✅ | ✅ | Otomatis via `TenantScope` |
| Rekap Rapor | ✅ | ✅ | Diperbaiki Task 7 (label lembaga di dropdown tahun ajaran saat mode agregat) |
| Persetujuan Rapor | ✅ | ✅ | Sudah benar |
| Kenaikan Kelas | ✅ | ✅ | Otomatis via `TenantScope` |
| Jadwal Piket Guru | ➖ (wajib pilih 1) | ✅ | By design — jadwal piket per-lembaga, tidak ada konsep agregat |

## Kehadiran Saya / Ruang Orang Tua / Ruang Siswa / Ruang Guru

➖ **Konsep tidak berlaku** — keempat grup ini berbasis identitas pribadi (guru/siswa/orang tua/pegawai sendiri), bukan konsep lembaga-vs-yayasan.

## Ringkasan (Dashboard)

➖ **Sudah didukung by design** — 2 view terpisah (`admin/dashboard/yayasan.blade.php` mode agregat, `admin/dashboard/lembaga.blade.php` mode 1 lembaga), otomatis switch sesuai konteks. Dashboard yayasan tidak punya link admin.* apapun (bersih). Dashboard lembaga punya 1 link (kartu "Pembayaran Menunggu Verifikasi" → `admin.pembayaran.index`), aman krn cuma render saat 1 lembaga aktif.

## Nav Button di Luar Sidebar

| Lokasi | Item | Status | Catatan |
|---|---|---|---|
| `resources/views/layouts/topbar.blade.php` | Bel notifikasi "Tagihan Perlu Ditinjau" (`admin.tagihan.perlu-ditinjau`) | 🔴 | Baris 14-23: `$effectiveLembagaId = $isYayasan ? $activeLembagaId : ...`. Saat mode "Semua Lembaga", `$effectiveLembagaId` null → `$perluDitinjauCount` dipaksa **0**, bel hilang total. Ini bug kelas SAMA dengan Kategori D (harusnya agregat, bukan disembunyikan) — meninjau tagihan bermasalah bukan aksi fisik on-site, yayasan wajar mau tahu lintas semua lembaga tanpa harus pilih 1 dulu. |
| `resources/views/admin/dashboard/lembaga.blade.php` | Kartu "Pembayaran Menunggu Verifikasi" → `admin.pembayaran.index` | ➖ | Aman, cuma render di mode 1 lembaga aktif |
| `admin/jalur-ppdb/index.blade.php`, `admin/gelombang-ppdb/index.blade.php` | Link inline "Tahun Ajaran" (empty-state) | ➖ | Rumpun modul PPDB (sengaja dibekukan/dikecualikan total dari audit) |

---

## Rekap: 4 Hal Nyata yang Belum Diperbaiki

Urutan disarankan untuk perintah "per menu" berikutnya:

1. **`VirtualAccountController::calonGenerate()`** — bug agregat, pola fix identik `index()` (bungkus `where('lembaga_id', $lembagaId)` dengan `when($lembagaId !== null, ...)`)
2. **`VirtualAccountController::generate()`** — sama
3. **`App\Exports\VirtualAccountExport`** (dipakai `VirtualAccountController::export()`) — sama, tapi di file class terpisah (constructor `__construct(private readonly ?int $lembagaId)`)
4. **`resources/views/layouts/topbar.blade.php`** baris 14-23 — bel notifikasi "Tagihan Perlu Ditinjau" perlu diagregat, bukan dipaksa 0 saat `$effectiveLembagaId` null. Perlu desain query baru (bukan cuma bungkus `when()`, karena saat ini logic-nya `? query : 0` bukan `where(...)` biasa) — cek dulu `Lembaga::where('yayasan_id', ...)` pattern yang sudah dipakai Task 1 (Kategori A) sebagai referensi.

Item 🟡 (Jenis Karyawan Master, Jabatan Tambahan Master, Template WhatsApp) **BUTUH KEPUTUSAN PRODUK DULU**, bukan tindakan teknis langsung — jangan dikerjakan sebelum ada keputusan eksplisit.
