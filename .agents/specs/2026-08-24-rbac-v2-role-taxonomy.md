# Spec: RBAC v2 — Role Taxonomy & Migration Baseline

- **Branch**: `rbac-v2`
- **Baseline commit**: `3f88c30`
- **Tanggal**: 24 Agustus 2026
- **Konteks**: kelanjutan diskusi role/permission yang sengaja ditunda sampai migrasi domain Keuangan selesai (lihat memori `project_role_permission_org_redesign`). Bukan proyek terpisah dari inisiatif "RBAC v2 Permission Consistency" sebelumnya di branch ini — ini babak lanjutan yang lebih besar: bukan sekadar merapikan permission existing, tapi merombak taxonomy role itu sendiri.

---

## 1. Context & Problem

Sistem role saat ini (`RoleSeeder.php`, `RolePermissionAssignmentSeeder.php`) tumbuh organik mengikuti kebutuhan fitur satu-per-satu, bukan hasil rancangan taxonomy sadar. Audit kritis (24 Agustus 2026) menemukan 8 masalah nyata:

1. `yayasan_super_admin` jadi "tong sampah" — menggabung Ketua Yayasan + Sekretaris + IT admin.
2. `admin_akademik` paling bermasalah — menggabung 4-5 jabatan nyata (Kepala TU, Wakasek Kurikulum, operator rapor, dll) jadi 1 role teknis.
3. Tidak ada role Wakil Kepala Sekolah per bidang — semua approval (RPP, kenaikan kelas, izin cuti SDM, keputusan SPMB) ditumpuk ke `kepala_sekolah`.
4. Tidak ada pembeda Wali Kelas vs Guru Mapel vs Guru BK — ketiganya digabung jadi 1 role `guru` generik.
5. `karyawan_pool`/`karyawan_lembaga` itu kategori DATA (`lembaga_id` null vs terisi), bukan jabatan — Satpam/Pustakawan/OB semua masuk kategori ini padahal kebutuhan permission-nya beda-beda.
6. `admin_administrasi` (SPMB) mestinya lebih pas sebagai role musiman/ad-hoc, bukan role permanen.
7. `bendahara_yayasan` scope-nya baru sebatas Sarpras/Pengadaan, padahal nanti relevan meluas ke Payroll dan Buku Kas BOS.
8. Penamaan tidak konsisten: `admin_keuangan` (lembaga-scope) vs `bendahara_yayasan` (yayasan-scope) sebenarnya jabatan sejenis di tingkat berbeda, tapi konvensi `admin_*` vs `*_yayasan` menyembunyikan kemiripan itu.

## 2. Design Principles

1. **`scope_level` = batas wilayah data. Role = fungsi/kewenangan.** Keduanya dimensi berbeda, jangan dipaksa jadi satu konsep.
2. **Role tidak merepresentasikan status kepegawaian.** Role merepresentasikan kombinasi capability dan authorization scope. Status/atribut organisasi (afiliasi lembaga, jenis kepegawaian) tetap berasal dari data domain (`Karyawan`, `User.lembaga_id`, dst), bukan dari nama role.
   - **Pengecualian eksplisit**: `pegawai_lembaga`/`pegawai_yayasan` adalah *scope-carrier roles* — lihat §5.4. Ini pengecualian sadar karena mekanisme authorization legacy (`widestScopeLevel()`) membutuhkan `scope_level` pada role, bukan penyimpangan dari prinsip di atas.
3. **Satu user boleh punya banyak role** (Spatie multi-role sudah didukung) — kombinasi role, bukan role monolitik per user.
4. **Jabatan baru menjadi role ketika menghasilkan kewenangan aplikasi yang berbeda** — bukan otomatis karena ada nama jabatan di struktur organisasi. Expand-on-demand, bukan modeling organisasi lengkap di depan.
5. **Permission bukan satu-satunya security boundary.** Permission = gate route/action. Record-level ownership/Policy = siapa boleh lihat/ubah record spesifik. Tenant/scope constraint = boundary data. Ketiganya lapisan terpisah, RBAC v2 hanya menata lapisan pertama.

## 3. Role Taxonomy

### 3.1 Organizational-scope roles
`pegawai_lembaga`, `pegawai_yayasan` — baseline scope-carrier untuk SEMUA akun pegawai, lihat §5.4. Bukan jabatan, murni bucket scope + baseline capability.

### 3.2 Functional roles
Merepresentasikan fungsi/kewenangan operasional nyata: `kepala_sekolah`, `wakasek_kurikulum`, `wakasek_kesiswaan`, `guru`, `wali_kelas`, `guru_bk`, `bendahara_lembaga`, `bendahara_yayasan`, `admin_sdm`, `admin_sarpras`, `operator_akademik`. Sebagian merepresentasikan jabatan organisasi nyata (`kepala_sekolah`), sebagian murni fungsional tanpa jabatan 1:1 (`admin_sarpras` — lihat §7 soal kenapa ini tetap sah).

### 3.3 Principal / self-service roles
`orang_tua`, `siswa` — bukan employee baseline, tidak butuh `pegawai_*`.

## 4. Baseline 18 Roles

Ini role yang **dibutuhkan berdasarkan kondisi produk saat ini** (fitur yang sudah ada + roadmap yang sudah pasti dikerjakan) — bukan pemetaan lengkap seluruh jabatan yang mungkin ada di organisasi sekolah. Role di luar daftar ini masuk *candidate roles* (§13), dibuat hanya ketika ada capability nyata yang membutuhkannya.

**Catatan progresi hitungan** (supaya tidak membingungkan bila dibandingkan dengan draft diskusi sebelumnya): baseline sempat disebut "16 role inti", lalu "17" setelah `pegawai` ditambahkan sebagai baseline wajib, lalu jadi **18** setelah `pegawai` dipecah jadi `pegawai_lembaga`+`pegawai_yayasan` karena temuan `widestScopeLevel()` (§5.4) — 1 role scope-carrier tunggal tidak bisa membawa 2 `scope_level` berbeda sekaligus.

```text
PLATFORM (1)
└── platform_super_admin

YAYASAN (3)
├── yayasan_super_admin
├── bendahara_yayasan
└── pengurus_yayasan          ← lihat §13, status masih kandidat tervalidasi-sebagian

ORGANIZATIONAL SCOPE / PEGAWAI (2)
├── pegawai_lembaga
└── pegawai_yayasan

FUNCTIONAL — LEMBAGA (10)
├── kepala_sekolah
├── wakasek_kurikulum
├── wakasek_kesiswaan
├── operator_akademik
├── admin_sdm
├── bendahara_lembaga
├── guru
├── wali_kelas
├── guru_bk
└── admin_sarpras

PRINCIPAL (2)
├── orang_tua
└── siswa
```

**Catatan koreksi jumlah**: draft diskusi sempat menyebut "17 role" termasuk `pengurus_yayasan` dalam hitungan — tapi §13 menegaskan `pengurus_yayasan` statusnya masih kandidat tervalidasi-sebagian (belum ada permission pembeda jelas dari `yayasan_super_admin`), bukan baseline murni. Spec ini tetap MENDAFTARKAN dia di baseline (karena kebutuhan organisasi yayasan sudah cukup jelas untuk didaftarkan sebagai role), tapi implementasi izin/permission-nya menunggu validasi eksplisit sebelum dipakai produksi — lihat §13 untuk kondisi keluarnya.

## 5. Scope Model

Tetap 3 `scope_level` yang sudah ada, TIDAK ditambah level baru:

### 5.1 `yayasan`
Boundary: seluruh lembaga di bawah 1 yayasan. Dipegang: `platform_super_admin` (lintas yayasan — TIDAK termasuk dalam match `widestScopeLevel()` biasa, role ini di luar cakupan yayasan sama sekali, hanya dicatat di sini untuk konteks hierarki), `yayasan_super_admin`, `bendahara_yayasan`, `pengurus_yayasan`, `pegawai_yayasan`.

### 5.2 `lembaga`
Boundary: 1 sekolah/lembaga spesifik. Dipegang: `kepala_sekolah`, `wakasek_*`, `operator_akademik`, `admin_sdm`, `bendahara_lembaga`, `guru`, `wali_kelas`, `guru_bk`, `pegawai_lembaga`.

**Catatan khusus `admin_sarpras`**: scope_level-nya `yayasan` (sesuai `RoleSeeder.php` baris 29 saat ini), BUKAN `lembaga` — dipertahankan apa adanya, TIDAK diubah oleh RBAC v2 ini (lihat §6.3 unchanged).

### 5.3 `diri_sendiri`
Boundary: data milik sendiri. Dipegang: `orang_tua`, `siswa`.

### 5.4 `pegawai_*` scope-carrier invariant (FORMAL)

> **`pegawai_lembaga` dan `pegawai_yayasan` adalah scope-carrier roles. Keduanya memiliki functional permission baseline yang SAMA; perbedaannya HANYA pada `scope_level`.**

Alasan teknis (bukan preferensi taxonomy): `App\Models\User::widestScopeLevel()` (baris 97-106) menurunkan scope efektif user 100% dari `$this->roles->pluck('scope_level')` — TIDAK dari `User.lembaga_id`. Kalau baseline pegawai jadi 1 role tunggal dengan 1 `scope_level`, pegawai pool (lembaga_id null, kerja lintas-lembaga) kehilangan mekanisme satu-satunya yang memberi mereka akses `yayasan`-scope.

**RBAC v2 secara eksplisit TIDAK mengubah `widestScopeLevel()`** — itu kontrak authorization yang dipakai banyak role lain, mengubahnya di luar scope sub-project ini (lihat §14 Non-Goals).

Invariant assignment (sudah dijaga hari ini di `Admin\KaryawanController::store()`, TIDAK berubah):

```php
$isPool = $request->boolean('is_pool');
if ($isPool && ! $request->user()->hasRole('yayasan_super_admin')) {
    abort(403, 'Hanya yayasan super admin yang bisa membuat karyawan pool.');
}
```

```text
is_pool = true  → lembaga_id = null        → assignRole('pegawai_yayasan')
is_pool = false → lembaga_id = <lembaga>   → assignRole('pegawai_lembaga')
```

`is_pool` adalah checkbox eksplisit di form, digated ke `yayasan_super_admin` — BUKAN hasil form kosong/tidak lengkap. `lembaga_id = null` TIDAK PERNAH berarti "data belum diisi", selalu berarti "pegawai ini memang secara organisatoris pool yayasan". Invariant ini sudah terjaga di kode existing, RBAC v2 hanya me-rename role yang di-assign, TIDAK mengubah gate/validasi ini.

## 6. Existing Role → New Role Migration

### 6.1 Automatic (permission bundle identik, migrasi mekanis aman)

| Role lama | Role baru | Catatan |
|---|---|---|
| `karyawan_lembaga` | `pegawai_lembaga` | Permission baseline sama persis |
| `karyawan_pool` | `pegawai_yayasan` | Scope tetap `yayasan` |
| `guru` | `guru` + `pegawai_lembaga` | Tidak berubah nama, HANYA ditambah baseline `pegawai_lembaga` |
| `kepala_sekolah` | `kepala_sekolah` + `pegawai_lembaga` | Idem |
| `admin_sarpras` | `admin_sarpras` + `pegawai_yayasan` | Idem (scope tetap yayasan, tidak diubah) |
| `orang_tua` | `orang_tua` | No change — principal, bukan employee |
| `siswa` | `siswa` | No change — principal, bukan employee |
| `yayasan_super_admin` | `yayasan_super_admin` | No change — JANGAN disentuh |

### 6.2 Review-required (JANGAN mapping otomatis presisi — audit assignment aktual dulu)

| Role lama | Kandidat role baru | Kenapa perlu review |
|---|---|---|
| `admin_akademik` | `operator_akademik` + kombinasi `wakasek_kurikulum`/`wali_kelas`/`guru_bk` sesuai kondisi user aktual | Role paling broad — user yang pegang bisa jadi sebenarnya Kepala TU, Wakasek Kurikulum, operator rapor, atau kombinasi. Audit `user_has_roles` existing untuk `admin_akademik` dulu, baru tentukan role baru per user, BUKAN mapping 1:1 mekanis. |
| `admin_keuangan` | `bendahara_lembaga` + `pegawai_lembaga` | Kemungkinan besar 1:1, tapi tetap validasi tidak ada user yang sebenarnya cuma operator input (bukan bendahara penuh) sebelum migrasi permanen. |
| `admin_sdm` | `admin_sdm` (rename opsional `operator_sdm`, BELUM diputuskan) | Nama akhir belum final — putuskan saat implementasi, bukan blocker spec ini. |

### 6.3 Unchanged (di luar scope RBAC v2 ini)

| Role | Alasan |
|---|---|
| `admin_administrasi` | SPMB — dibekukan, lihat §9. |
| `bendahara_yayasan` | Scope/permission Sarpras-Pengadaan-nya tidak disentuh; perluasan ke Payroll/BOS terjadi saat modul itu dibangun, bukan sekarang. |
| `admin_sarpras` (permission-nya) | Functional role sudah tepat, hanya ditambah baseline `pegawai_yayasan` (§6.1), permission Sarpras/Pengadaan itu sendiri tidak diubah. |

## 7. Multi-role Composition Rules

Kombinasi role adalah pola NORMAL, bukan pengecualian. Contoh sah:

```text
Pak Budi     : pegawai_lembaga + guru + wali_kelas
Bu Ani       : pegawai_lembaga + guru + guru_bk
Pak C        : pegawai_lembaga + guru + wali_kelas + wakasek_kurikulum
Satpam       : pegawai_lembaga  (baseline saja, tanpa functional role)
Bendahara    : pegawai_lembaga + bendahara_lembaga
Bendahara Y  : pegawai_yayasan + bendahara_yayasan
```

**Invariant**: setiap akun pegawai (dibuat lewat `AkunKaryawanGenerator`) WAJIB memiliki tepat satu dari `pegawai_lembaga`/`pegawai_yayasan`. Role fungsional spesialis adalah TAMBAHAN, tidak pernah menggantikan baseline ini. `orang_tua` dan `siswa` TIDAK memakai `pegawai_*` — mereka principal, bukan employee.

**JANGAN membuat role bernama jabatan spesifik** (`satpam`, `ob`, `pustakawan`) hanya karena ada record kepegawaian dengan jenis itu — `pegawai_lembaga`/`pegawai_yayasan` saja sudah cukup sampai ada capability nyata yang membutuhkan role terpisah (lihat §13).

## 8. Wali Kelas: Role Capability vs Kelas Relation

Prinsip kunci, berlaku sebagai preseden untuk role relasional lain di masa depan:

> **Role `wali_kelas` HANYA menentukan capability ("boleh menggunakan fitur wali kelas"). Assignment wali kelas tetap merupakan relasi domain terhadap `Kelas` (`Kelas.wali_kelas_guru_id`), BUKAN scope_level.**

```text
Spatie Role  wali_kelas          → "boleh menggunakan fitur wali kelas" (capability gate)
Domain data  Kelas.wali_kelas_guru_id → "kelas MANA yang boleh dia kelola" (ownership scope)
```

Authorization pattern yang benar:

```php
$user->hasRole('wali_kelas')
    && $kelas->wali_kelas_guru_id === $user->guru->id
```

BUKAN:

```php
$user->hasRole('wali_kelas')   // lalu diasumsikan boleh lihat SEMUA kelas — SALAH
```

`scope_level` (`lembaga`) membatasi wali_kelas ke sekolahnya sendiri; relasi `wali_kelas_guru_id` yang membatasi ke kelas spesifik. Dua lapisan berbeda, jangan dicampur.

## 9. SPMB Freeze

**RBAC v2 tidak melakukan redesign permission/domain SPMB.** Ini keputusan eksplisit, bukan kelalaian:

- `admin_administrasi` TETAP hidup sebagai role legacy khusus SPMB, permission `spmb.*` yang dia pegang saat ini TIDAK dipindahkan ke `operator_akademik` atau role baru manapun.
- Taxonomy RBAC v2 BOLEH mendokumentasikan bahwa fungsi SPMB berasal dari role lama ini, tapi TIDAK memperkenalkan role baru khusus SPMB sekarang.
- JANGAN merombak permission SPMB "demi taxonomy terlihat sempurna" — itu justru kerja dua kali begitu redesign SPMB (yang sudah diputuskan ditunda) benar-benar mulai.
- Ketika redesign SPMB dimulai, role dan permission operator SPMB dievaluasi ulang BERSAMA modul barunya, sebagai bagian dari spec SPMB sendiri — bukan bagian dari RBAC v2 ini.

## 10. Existing Code Consumers (Audit Wajib Sebelum Implementasi)

Blast radius hardcoded role-name checks di luar `RoleSeeder.php`/`RolePermissionAssignmentSeeder.php` (grep `app resources/views routes`, 24 Agustus 2026 — WAJIB grep ulang saat implementasi untuk konfirmasi masih akurat):

**`karyawan_pool`/`karyawan_lembaga`** (9 file, HARUS diupdate ke `pegawai_yayasan`/`pegawai_lembaga`):
- `app/Domains/Kasus/Actions/Pengajuan/ListKasusUntukUserAction.php`
- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Services/AkunKaryawanGenerator.php` — **consumer paling kritis**, ini yang melakukan `assignRole()` saat akun pegawai baru dibuat (lihat §5.4 untuk logic-nya, HANYA nama role yang berubah, logic `is_pool` TIDAK disentuh)
- `tests/Feature/Admin/KaryawanCrudTest.php`
- `tests/Feature/DashboardKasusTest.php`
- `tests/Feature/KaryawanDashboardTest.php`
- `tests/Feature/KasusEvaluasiTest.php`
- `tests/Feature/KasusKonselorAksesTest.php`
- `tests/Feature/KasusTugasReviewTest.php`
- `tests/Feature/Sdm/AttendanceRbacSeedTest.php`
- `tests/Feature/Sdm/IzinCutiWorkflowSeedTest.php`
- `tests/Unit/Services/AkunKaryawanGeneratorTest.php`

**`admin_akademik`/`admin_keuangan`** (2 file, HARUS diupdate sesuai hasil review §6.2):
- `app/Domains/Kasus/Actions/Consent/ApproveConsentAction.php`
- `app/Domains/Kasus/Actions/Evaluasi/CatatEvaluasiAction.php`

**`app/Models/User.php::widestScopeLevel()`** (baris 97-106) — DIBACA ulang, TIDAK DIUBAH, cuma perlu dipastikan `pegawai_yayasan`/`pegawai_lembaga` punya `scope_level` yang benar di `RoleSeeder.php`.

**`app/Http/Controllers/Admin/KaryawanController.php::store()`** — logic `is_pool`+gate `yayasan_super_admin` TIDAK diubah, hanya baris `assignRole()` di `AkunKaryawanGenerator` yang dipanggilnya yang berubah nama.

## 11. Migration & Backward Compatibility

- **Data migrasi `user_has_roles` existing WAJIB eksplisit**, mengikuti tabel §6 — TIDAK ada migrasi otomatis 100% untuk `admin_akademik` (kategori review-required).
- Untuk role di §6.1 (automatic): migration script Laravel bisa langsung rename/tambah role assignment di `role_has_permissions`/`model_has_roles` tanpa intervensi manual.
- Untuk role di §6.2 (review-required): migration script WAJIB menghasilkan laporan/daftar user + role lama mereka untuk direview manual SEBELUM assignment baru diterapkan — bukan auto-assign.
- `RoleSeeder.php` dan `RolePermissionAssignmentSeeder.php` diupdate untuk mendefinisikan 17 role baseline (§4) dengan permission masing-masing sesuai hasil pemetaan §6.
- Role lama yang digantikan (`karyawan_pool`, `karyawan_lembaga`) TIDAK dihapus dari database sampai migration + `AkunKaryawanGenerator` refactor + seluruh test di §10 lolos — urutan ini penting untuk zero-downtime migration.

## 12. Test Requirements

Minimal WAJIB ada di implementasi:

- **Invariant `pegawai_*` assignment**: pegawai dengan `lembaga_id` terisi → `pegawai_lembaga`; pegawai pool (`is_pool=true`, hanya `yayasan_super_admin` yang bisa) → `pegawai_yayasan`; user NON-`yayasan_super_admin` yang mencoba `is_pool=true` → 403 (regression test, guard existing HARUS tetap lolos setelah rename).
- **`widestScopeLevel()` regression**: user dengan HANYA `pegawai_lembaga` → `widestScopeLevel()` = `lembaga`; user dengan HANYA `pegawai_yayasan` → `yayasan`.
- **Multi-role composition**: user dengan `pegawai_lembaga` + `guru` + `wali_kelas` → semua permission gabungan aktif, TIDAK saling menghapus.
- **Wali Kelas capability-vs-relation**: user dengan role `wali_kelas` TAPI `Kelas.wali_kelas_guru_id` bukan miliknya → ditolak akses ke kelas itu (walau role-nya ada).
- Test existing yang menyentuh `karyawan_pool`/`karyawan_lembaga` (§10 daftar 9 file) WAJIB diupdate dan tetap lolos.
- Test existing yang menyentuh `admin_akademik`/`admin_keuangan` (§10 daftar 2 file) WAJIB diupdate sesuai hasil review §6.2 dan tetap lolos.

## 13. Candidate Roles — Not Implemented Yet

Role berikut TIDAK masuk baseline 17, TIDAK dihapus dari kemungkinan desain — dibuat HANYA ketika ada permission/capability nyata yang membutuhkannya:

| Role kandidat | Kondisi munculnya |
|---|---|
| `pengurus_yayasan` | Butuh permission yang benar-benar berbeda dari `yayasan_super_admin` dulu — sampai saat ini belum jelas apa bedanya. **Perlu divalidasi eksplisit sebelum jadi role produksi**, walau didaftarkan di §4 karena kebutuhan organisasinya sudah cukup jelas. |
| `kepala_tu` | Kalau permission TU mulai berbeda dari `operator_akademik`. |
| `staff_tu` | Idem. |
| `wakasek_sarpras` | Kalau workflow Sarpras perlu membedakan kewenangan Wakasek dari `admin_sarpras`. |
| `wakasek_humas` | Kalau ada modul komunikasi/relasi eksternal yang butuh permission berbeda. |
| `operator_kesiswaan` | Kalau administrasi kesiswaan (di luar guru_bk/wali_kelas) butuh role terpisah. |
| `operator_keuangan` | Kalau ada segregation-of-duty nyata antara "bendahara" (approval) vs "operator" (input) di lembaga. |
| `bendahara_bos` | Kalau modul Buku Kas & BOS punya segregation-of-duty nyata dari `bendahara_lembaga`. |
| `pustakawan` | Kalau modul Perpustakaan dibangun dan butuh capability khusus. |
| `platform_admin`/`platform_support`/`platform_finance`/`platform_auditor` | Kalau SaaS multi-tenant benar-benar diaktifkan — untuk kondisi sekarang cukup `platform_super_admin` saja. |
| Role SPMB baru manapun | Dibekukan total, lihat §9. |

## 14. Explicit Non-Goals

RBAC v2 ini **TIDAK**:

1. Mengubah mekanisme `scope_level`/`widestScopeLevel()` itu sendiri (§5.4) — kontrak authorization existing dipertahankan.
2. Memindahkan `Lembaga`/`Kelas`/`Siswa`/`TahunAjaran`/`Semester` ke domain baru — di luar cakupan RBAC, itu keputusan arsitektur terpisah (§3.2 `master-refactor-domain-pattern.md`).
3. Mendesain ulang `TenantScope` atau mekanisme tenant-isolation lainnya.
4. Mengubah permission/role SPMB (§9) — dibekukan total sampai redesign SPMB dimulai.
5. Membuat SEMUA jabatan organisasi nyata jadi Spatie role — hanya yang menghasilkan kewenangan aplikasi berbeda (§2 prinsip 4, §13 daftar kandidat).
6. Menghapus kebutuhan Policy/record-level authorization — permission RBAC ini tetap 1 lapisan dari 3 (§2 prinsip 5), bukan pengganti ownership check per-record.
7. Menyentuh permission Sarpras/Pengadaan existing (§6.3) — hanya menambah baseline `pegawai_*` di atasnya.
