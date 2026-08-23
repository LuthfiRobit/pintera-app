# Spec: Serap Model Data Induk Berblast-Radius-Sempit ke Domain Pemiliknya

**Tanggal:** 23 Agustus 2026
**Branch:** `refactor-v1` (baru dibuat, sejajar `sdm-v1`, titik cabang = `sdm-v1` commit `dc54735`)
**Terkait:** `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` (roadmap induk), `.agents/skills/laravel-feature-standard/SKILL.md` (standar arsitektur mengikat)

## 1. Latar Belakang

Audit produk (`Peta Pengembangan Pintera`, 24 Agustus 2026) sempat mengklasifikasikan "Migrasi Data Induk ke `app/Domains/DataInduk`" sebagai satu blok besar berprioritas tinggi (prasyarat Feature-Gating). Investigasi lanjutan menemukan itu **bertentangan dengan keputusan arsitektur mengikat** di `master-refactor-domain-pattern.md` §3.2: model fondasi lintas-aplikasi (`Lembaga`, `Kelas`, `Siswa`, `TahunAjaran`, dst) sengaja **tidak pernah** dijadikan domain sendiri — blast radius-nya terlalu besar dan mereka bukan kapabilitas bisnis mandiri.

Tapi audit lanjutan (grep pemakaian nyata) menemukan **3 model di dalam grup "Data Induk" yang sebenarnya BUKAN fondasi lintas-domain** — blast radius-nya sempit dan seluruh pemakainya sudah berada dalam satu domain konseptual:

| Model | Jumlah file pemakai (grep nyata) | Domain pemilik konseptual |
|---|---:|---|
| `JenisKaryawanMaster` | 21 | SDM/Kepegawaian |
| `JabatanTambahanMaster` | 7 | SDM/Kepegawaian |
| `MataPelajaran` | 48 | Akademik |

Ketiganya memenuhi kriteria §3.1 (blast-radius kecil, SEMUA pemakai adalah 1 domain) — persis kriteria yang sudah dipakai untuk memindahkan `PolaJam`/`JamPelajaran` ke `Domains\Akademik` di Sub-Task 05 (36 file consumer, preseden langsung untuk spec ini).

## 2. Tujuan

Memindahkan `JenisKaryawanMaster` + `JabatanTambahanMaster` ke `app/Domains/Sdm/Models/`, dan `MataPelajaran` ke `app/Domains/Akademik/Models/` — beserta controller (Action/DTO) dan view masing-masing — mengikuti pola yang sudah terbukti jalan di Sub-Task 05, TANPA mengubah perilaku aplikasi sama sekali.

**Ini BUKAN prasyarat Feature-Gating** (sudah diklarifikasi terpisah) — murni kerapian arsitektur, prioritas Rendah di roadmap produk.

## 3. Keputusan Desain (hasil brainstorming dengan user)

### 3.1 Cakupan: 1 sub-task gabungan, paket penuh
Ketiga model dikerjakan dalam 1 spec/plan (pola teknisnya identik), tapi tiap model dapat task terpisah supaya bisa direview independen. Cakupan tiap model: **model + controller (Action/DTO) + view** dipindah sekaligus dalam sub-task ini (bukan model-saja-dulu) — mengikuti preseden Task 4+5 PolaJam yang juga menyelesaikan kedua bagian ini.

### 3.2 Namespace controller: pindah ke scope resmi
Controller dipindah dari `App\Http\Controllers\Admin\{Feature}Controller` ke:
- `App\Http\Controllers\Lembaga\Sdm\JenisKaryawanMasterController`
- `App\Http\Controllers\Lembaga\Sdm\JabatanTambahanMasterController`
- `App\Http\Controllers\Lembaga\Akademik\MataPelajaranController`

**Catatan:** ini menyimpang dari preseden PolaJam (yang sengaja MEMPERTAHANKAN `Admin\PolaJamController`, tidak dipindah — §3.3 bilang "nol urgensi"). Keputusan sadar user: konsistensi pola jangka panjang lebih diutamakan di sini. **Item susulan dicatat eksplisit** (§8) untuk memindahkan controller SDM lain (`AttendanceConfigurationController`, `AttendanceController`, `AttendanceQrScanController`, `ApprovalIzinCutiController`, `PengajuanIzinCutiController`, `EmployeeQrCodeController`, dst — semua yang dibangun sepanjang modul Kehadiran SDM) ke `Lembaga\Sdm\` di kesempatan refactor terpisah, supaya tidak ada 2 controller SDM dengan namespace berbeda tanpa keterangan.

### 3.3 View: ikuti konvensi resmi §3.3
Pindah ke `resources/views/portals/lembaga/sdm/jenis-karyawan-master/`, `resources/views/portals/lembaga/sdm/jabatan-tambahan-master/`, `resources/views/portals/lembaga/akademik/mata-pelajaran/`. Ini adalah **penggunaan pertama** `portals/lembaga/sdm/` — dicatat eksplisit sebagai preseden baru untuk domain SDM (§8), karena seluruh view Kehadiran SDM yang sudah ada masih di `resources/views/admin/kehadiran-sdm/`/`resources/views/sdm/` (inkonsistensi yang sudah ada, TIDAK diperbaiki retroaktif di sub-task ini — di luar cakupan).

### 3.4 Route name: TIDAK berubah
Sesuai bahaya nyata §3.3 (sed blanket pernah merusak 5 file Blade saat migrasi 9 view Akademik) — nama route (`admin.jenis-karyawan-master.*`, dst) tetap identik, hanya `view(...)`/`@include(...)`/`assertViewIs(...)` yang diubah ke path baru. Verifikasi wajib: `grep -rn "route('portals\." resources/views/portals` harus kosong sebelum lanjut.

### 3.5 Zero-behavior-change
Default mengikat. Kalau ditemukan celah/inkonsistensi nyata di kode lama saat investigasi (mirip `salinJadwal` di Sub-Task 05), TIDAK diperbaiki diam-diam — dilaporkan sebagai keputusan terpisah ke user.

### 3.6 `newFactory()` — per-model, bukan blanket
- `JenisKaryawanMaster` — pakai `HasFactory`, **WAJIB** `newFactory()` ditambahkan setelah pindah namespace.
- `MataPelajaran` — pakai `HasFactory`, **WAJIB** `newFactory()`.
- `JabatanTambahanMaster` — **TIDAK** pakai `HasFactory` sama sekali saat ini. Zero-behavior-change berarti **TIDAK ditambahkan** juga — bukan celah untuk "sekalian dibenerin".

### 3.7 Gotcha referensi implisit same-namespace — WAJIB diverifikasi manual, bukan cuma grep `use`
Ditemukan lewat investigasi manual (grep `X::class` di seluruh `app/Models/`, BUKAN cuma grep baris `use`) — 3 relasi PALING PENTING (parent-child utama tiap model) referensinya implisit (tanpa `use` statement, resolusi same-namespace PHP otomatis) dan TIDAK muncul di grep `use App\Models\X;` biasa:

| File | Baris | Referensi implisit |
|---|---:|---|
| `app/Models/Karyawan.php` | 59 | `JenisKaryawanMaster::class` (relasi `belongsTo`) |
| `app/Models/Guru.php` | 76 | `JabatanTambahanMaster::class` (relasi `belongsToMany`) |
| `app/Models/JadwalPelajaran.php` | 51 | `MataPelajaran::class` (relasi `belongsTo`) |

Ketiganya WAJIB diperbaiki jadi FQCN inline (bukan `use` statement tambahan di file yang tetap di `app/Models/`) — persis pola `Kelas::polaJam()` yang sudah dipakai Sub-Task 05.

## 4. Daftar File Konsumen (grep nyata, 23 Agustus 2026, terhadap commit `dc54735`)

**`JenisKaryawanMaster`** (21 file, hasil `grep -rln "use App\\Models\\JenisKaryawanMaster;" --include="*.php" app database tests` + 1 gotcha implisit `Karyawan.php`):
```
app/Http/Controllers/Admin/AttendanceConfigurationController.php
tests/Feature/Sdm/KuotaCutiConfigTest.php
app/Domains/Sdm/Models/KuotaCutiConfig.php
tests/Unit/Services/AttendancePolicyResolverTest.php
tests/Feature/Sdm/AttendancePolicyModelTest.php
tests/Feature/Admin/AttendancePolicyControllerTest.php
app/Domains/Sdm/Models/AttendancePolicy.php
tests/Unit/Services/KonselorAllocationResolverTest.php
tests/Feature/KasusEvaluasiTest.php
tests/Feature/Admin/KaryawanCrudTest.php
tests/Feature/Admin/JenisKaryawanMasterCrudTest.php
database/seeders/OrangTuaKaryawanSeeder.php
app/Http/Controllers/Admin/KaryawanController.php
tests/Feature/KaryawanDashboardTest.php
tests/Unit/JenisKaryawanMasterSeederTest.php
database/seeders/JenisKaryawanMasterSeeder.php
database/factories/JenisKaryawanMasterFactory.php
app/Http/Controllers/Admin/JenisKaryawanMasterController.php
tests/Unit/Services/AkunKaryawanGeneratorTest.php
database/factories/KaryawanFactory.php
tests/Feature/KaryawanSchemaTest.php
```
+ gotcha implisit: `app/Models/Karyawan.php` (baris 59, TANPA `use` statement)

**`JabatanTambahanMaster`** (7 file + 1 gotcha implisit):
```
database/seeders/GuruJabatanTambahanSeeder.php
app/Http/Controllers/Admin/GuruController.php
tests/Feature/Admin/JabatanTambahanMasterCrudTest.php
app/Http/Controllers/Admin/JabatanTambahanMasterController.php
tests/Feature/Admin/GuruRelationalProfileTest.php
database/seeders/JabatanTambahanMasterSeeder.php
tests/Unit/JabatanTambahanTest.php
```
+ gotcha implisit: `app/Models/Guru.php` (baris 76, TANPA `use` statement)

**`MataPelajaran`** (48 file + 1 gotcha implisit):
```
tests/Feature/Guru/JurnalKbmTenantScopeTest.php
tests/Unit/Services/SesiPembelajaranGeneratorTest.php
tests/Unit/Services/RaporCalculationServiceTest.php
tests/Unit/Models/NilaiSiswaTest.php
tests/Unit/Models/JadwalPelajaranTest.php
tests/Unit/Models/AsesmenTest.php
tests/Unit/KomponenPenilaianSeederTest.php
tests/Unit/Domains/Sarpras/SarprasModelsTest.php
tests/Unit/Domains/Sarpras/GedungRuanganActionTest.php
tests/Feature/Guru/KomponenPenilaianControllerTest.php
tests/Feature/Guru/JurnalKbmControllerTest.php
tests/Feature/Guru/AsesmenControllerTest.php
tests/Feature/AkademikTenantScopeTest.php
tests/Feature/Akademik/RaporPdfDataBuilderTest.php
tests/Feature/Akademik/RppWorkflowTest.php
tests/Feature/Akademik/RaporApprovalActionsTest.php
tests/Feature/Akademik/JurnalKbmAdaptiveTest.php
tests/Feature/Akademik/JadwalSarprasCollisionTest.php
tests/Feature/Akademik/CapaianKompetensiGeneratorTest.php
tests/Feature/Akademik/GenerateNarasiPerkembanganActionTest.php
tests/Feature/Admin/RaporControllerTest.php
tests/Feature/Admin/KomponenPenilaianCrudTest.php
tests/Feature/Admin/KenaikanKelasControllerTest.php
tests/Feature/Admin/JadwalPelajaranCrudTest.php
database/seeders/SesiPembelajaranSeeder.php
database/seeders/NilaiSiswaSeeder.php
database/seeders/KomponenPenilaianSeeder.php
database/seeders/JadwalPelajaranSeeder.php
database/seeders/AsesmenSeeder.php
database/factories/KomponenPenilaianFactory.php
database/factories/JadwalPelajaranFactory.php
database/factories/AsesmenFactory.php
app/Http/Controllers/Guru/KomponenPenilaianController.php
app/Http/Controllers/Guru/AsesmenController.php
app/Http/Controllers/Admin/RppController.php
app/Http/Controllers/Admin/KomponenPenilaianController.php
app/Http/Controllers/Admin/JadwalPelajaranController.php
app/Domains/Akademik/Services/CapaianKompetensiGenerator.php
app/Domains/Akademik/Models/Rpp.php
app/Domains/Akademik/Models/SesiPembelajaran.php
app/Domains/Akademik/Models/KomponenPenilaian.php
app/Domains/Akademik/Models/Asesmen.php
app/Http/Controllers/Admin/MataPelajaranController.php
tests/Feature/Admin/MataPelajaranCrudTest.php
database/seeders/MataPelajaranSeeder.php
tests/Unit/MataPelajaranSeederTest.php
tests/Unit/Models/MataPelajaranTest.php
database/factories/MataPelajaranFactory.php
```
+ gotcha implisit: `app/Models/JadwalPelajaran.php` (baris 51, TANPA `use` statement)

**Peringatan:** daftar di atas adalah hasil grep NYATA pada commit `dc54735`. Kalau plan dieksekusi jauh setelah tanggal ini dan ada file baru yang mengimpor model-model ini, JANGAN asumsikan daftar ini masih 100% lengkap — plan (bukan spec ini) akan menyebutkan cara verifikasi ulang sebelum eksekusi tiap task.

## 5. Isi Model Saat Ini (baseline, untuk verifikasi sebelum edit)

```php
// app/Models/JenisKaryawanMaster.php
class JenisKaryawanMaster extends Model
{
    use HasFactory;
    protected $table = 'jenis_karyawan_master';
    protected $fillable = ['nama', 'is_konselor'];
    protected function casts(): array { return ['is_konselor' => 'boolean']; }
    public function karyawan(): HasMany { return $this->hasMany(Karyawan::class, 'jenis_karyawan_id'); }
}

// app/Models/JabatanTambahanMaster.php
class JabatanTambahanMaster extends Model
{
    protected $table = 'jabatan_tambahan_master';
    protected $fillable = ['nama', 'kelompok'];
    public function guru(): BelongsToMany
    {
        return $this->belongsToMany(Guru::class, 'guru_jabatan_tambahan')
            ->withPivot(['mulai_periode', 'akhir_periode', 'no_sk'])
            ->withTimestamps()
            ->using(GuruJabatanTambahan::class);
    }
}

// app/Models/MataPelajaran.php
class MataPelajaran extends Model
{
    use HasFactory, BelongsToTenant;
    protected $table = 'mata_pelajaran';
    protected $fillable = ['lembaga_id', 'kode', 'nama', 'no_urut', 'tipe', 'kelompok', 'status'];
    protected function casts(): array
    {
        return ['tipe' => TipeMataPelajaran::class, 'kelompok' => KelompokMataPelajaran::class, 'status' => StatusMataPelajaran::class];
    }
    public function lembaga(): BelongsTo { return $this->belongsTo(Lembaga::class); }
}
```

## 6. Testing

- Tiap task pindah-model: test scoped ke file consumer yang terdaftar di §4 untuk model itu (bukan full suite).
- Tiap task refactor-controller: test scoped ke controller/view/route terkait model itu.
- Task terakhir sub-task ini: test scoped LUAS gabungan ketiganya (seluruh file di §4 sekaligus) — bukti tidak ada "Class not found" atau `RouteNotFoundException` di seluruh ekosistem SDM+Akademik yang bersinggungan.
- Full suite HANYA dijalankan sekali kalau user beri izin eksplisit di akhir — tidak diasumsikan.
- Verifikasi wajib sebelum setiap task pindah-model dianggap selesai: `grep -rln "use App\\Models\\<Model>;" --include="*.php" app database tests` harus KOSONG, DAN grep manual `<Model>::class` di seluruh `app/Models/` untuk pastikan tidak ada gotcha implisit baru yang terlewat.

## 7. Yang TIDAK Berubah (hard constraint)

- Model pindahan HANYA berisi `$fillable`, `casts()`, relationship — TIDAK ADA business logic baru ditambahkan.
- Kolom database, migration, nama tabel — TIDAK disentuh sama sekali.
- Route NAME — identik persis, tidak ada rename.
- Perilaku validasi/response controller — identik kata-per-kata dengan sebelum migrasi (kecuali memang berubah bentuk internal jadi Action/DTO, tapi HASIL akhirnya ke user harus sama).
- `JabatanTambahanMaster` TIDAK diberi `HasFactory` (lihat §3.6).

## 8. Catatan Susulan (dicatat, bukan dikerjakan di sub-task ini)

- Controller SDM lain yang sudah ada (`AttendanceConfigurationController`, `AttendanceController`, `AttendanceQrScanController`, `Admin\ApprovalIzinCutiController`, `PengajuanIzinCutiController`, `EmployeeQrCodeController`) masih di namespace `Admin\`/`Admin\Admin\` dan view-nya masih di `admin/kehadiran-sdm/`/`sdm/` — TIDAK dipindah ke `Lembaga\Sdm\`/`portals/lembaga/sdm/` di sub-task ini. Kalau nanti mau dikerjakan demi konsistensi pattern, itu sub-task terpisah.
- Setelah sub-task ini selesai, `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` §7 (Catatan Lintas Sub-Task) WAJIB ditambah 1 catatan baru: `portals/lembaga/sdm/` pertama kali dipakai di sub-task ini, sementara modul Kehadiran SDM lain masih pola lama — supaya sesi berikutnya tidak bingung kenapa ada 2 lokasi berbeda untuk domain yang sama.

## 9. Di Luar Cakupan

- Migrasi model Data Induk lain yang blast radius-nya LEBAR (`Lembaga`, `Kelas`, `Siswa`, `TahunAjaran`, `Semester`, `WhatsAppTemplate`) — tetap di `app/Models/` selamanya, sesuai §3.2 (lihat Peta Pengembangan Pintera untuk detail).
- Migrasi domain SPMB/Keuangan (prasyarat Feature-Gating, item terpisah dengan prioritas lebih tinggi).
- Perbaikan controller SDM lain ke `Lembaga\Sdm\` (lihat §8, dicatat sebagai susulan).
