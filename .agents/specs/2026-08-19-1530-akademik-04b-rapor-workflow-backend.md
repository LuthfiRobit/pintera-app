# 📋 Spec: Sub-Task 04b — Adaptive E-Rapor Engine: Backend & Approval Workflow

- **Document ID / Slug:** `2026-08-19-1530-akademik-04b-rapor-workflow-backend`
- **Master Plan File:** [`.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md) — bagian dari FASE 4 (Adaptive E-Rapor Engine), dipecah per-lapisan: 04b (sub-task ini, backend + workflow), 04c (UI 4 role, menyusul), 04d (4 template PDF berjenjang, menyusul).
- **Target Domain:** `App\Domains\Akademik\` (baru) + `App\Domains\Workflow\` (reuse, existing dari modul Pengadaan Sarpras)
- **Tanggal & Waktu:** 19 Agustus 2026, 15:30 WIB
- **Status:** 🟡 SPEC DRAFT — menunggu review user sebelum lanjut ke Plan

---

## 1. Latar Belakang

FASE 4 (Adaptive E-Rapor Engine) mencakup skema DB baru, generator narasi capaian kompetensi otomatis, alur persetujuan berjenjang 4-tahap (Guru Mapel → Wali Kelas → Waka Kurikulum → Kepala Sekolah), dan 4 template PDF resmi berjenjang. Ini terlalu besar untuk satu plan — dipecah per-lapisan: sub-task ini (04b) membangun **fondasi backend murni** (skema, service, Action approval) tanpa route/controller/view sama sekali, diuji lewat Feature test yang memanggil Action langsung (pola sama seperti `PresensiAggregationServiceTest`/`RaporCalculationServiceTest` dari Sub-Task 03a/04a). UI (04c) dan PDF (04d) dibangun di atas fondasi ini setelah 04b selesai & direview.

Sub-Task 04a (selesai) sudah menyediakan `KomponenPenilaian` (berperan sebagai Tujuan Pembelajaran/TP — field `kode`/`deskripsi`/`kktp` persis itu), `Asesmen`, `NilaiSiswa`, dan `RaporCalculationService::hitungRekapKelas()` di `app/Domains/Akademik/` — semuanya langsung dipakai ulang di sini.

**Reuse Universal Workflow Engine.** Proyek ini sudah punya generic approval-workflow engine (`app/Domains/Workflow/`) yang dipakai modul Pengadaan Sarpras (`WorkflowDefinition` → `WorkflowStep` per-role → `ApprovalRequest` polymorphic → `ApprovalLog` audit trail). Investigasi sebelum brainstorming ini menemukan: engine mendukung approval forward multi-step berbasis role dengan baik, TAPI **tidak native** untuk (a) tolak-kembali-ke-draft — modul Pengadaan sendiri menangani ini secara manual di `SubmitPengajuanAction`-nya (reset `ApprovalRequest` yang sama ke `firstStep()` + status `Pending`), dan (b) efek "finalize" (mis. kunci nilai) — juga ditangani manual di luar engine oleh konsumennya. Sub-task ini mereplikasi pola manual yang sama persis untuk Rapor. Engine **tidak** menerapkan `BelongsToTenant` pada `ApprovalRequest`/`ApprovalLog` (isolasi hanya transitif lewat entitas `approvable`) — karena ini data akademik siswa (lebih sensitif dari pengadaan barang), sub-task ini WAJIB memverifikasi lewat test bahwa `ApprovalRequest` untuk Rapor hanya pernah diakses lewat relasi `PengajuanRapor::approvalRequest()` yang sudah tenant-scoped (`BelongsToTenant` di `PengajuanRapor`), tidak pernah lewat lookup langsung by ID ke tabel engine.

## 2. Scope

### In Scope

#### 2.1. Skema Database (3 perubahan)

**Migrasi baru 1 — `create_pengajuan_rapor_table.php`** (persis section 6.2 master spec, ditambah `lembaga_id` yang memang sudah ada di spec asli):
```php
Schema::create('pengajuan_rapor', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
    $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
    $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
    $table->string('status')->default('draft'); // Draft, Diajukan, Diverifikasi, Disetujui, Ditolak
    $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('diajukan_pada')->nullable();
    $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('diverifikasi_pada')->nullable();
    $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('disetujui_pada')->nullable();
    $table->text('catatan_revisi')->nullable();
    $table->date('tanggal_rapor')->nullable();
    $table->timestamps();

    $table->unique(['kelas_id', 'semester_id']);
    $table->index(['lembaga_id', 'semester_id', 'status'], 'idx_pengajuan_rapor_status');
});
```

**Migrasi baru 2 — `create_catatan_wali_kelas_table.php`** (persis section 6.3 master spec + 3 kolom pertumbuhan PAUD tambahan):
```php
Schema::create('catatan_wali_kelas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
    $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
    $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
    $table->text('catatan_sikap')->nullable();
    $table->text('catatan_perkembangan')->nullable(); // Khusus PAUD
    $table->decimal('tinggi_badan_cm', 5, 1)->nullable();   // Khusus PAUD
    $table->decimal('berat_badan_kg', 5, 1)->nullable();    // Khusus PAUD
    $table->decimal('lingkar_kepala_cm', 5, 1)->nullable(); // Khusus PAUD
    $table->json('ekstrakurikuler')->nullable();
    $table->json('prestasi')->nullable();
    $table->json('pkl_info')->nullable(); // Khusus SMK
    $table->string('keterangan_kenaikan', 50)->nullable();
    $table->timestamps();

    $table->unique(['siswa_id', 'semester_id']);
});
```

**Migrasi baru 3 — `add_kktp_minimal_to_komponen_penilaian_table.php`**:
```php
Schema::table('komponen_penilaian', function (Blueprint $table) {
    $table->unsignedTinyInteger('kktp_minimal')->nullable()->after('kktp');
});
```
`kktp_minimal` (0-100) adalah ambang numerik terpisah dari `bobot` (tetap untuk pembagi rata-rata tertimbang di `RaporCalculationService`) dan `kktp` (tetap teks deskripsi kualitatif, mis. "Minimal 75% benar") — dua kolom lama TIDAK diubah maknanya.

#### 2.2. Model & Enum Baru

- `app/Domains/Akademik/Models/PengajuanRapor.php` — `use HasFactory, BelongsToTenant`. Cast `status` ke `StatusPengajuanRapor`. Relasi: `kelas()`, `semester()`, `diajukanOleh()`/`diverifikasiOleh()`/`disetujuiOleh()` (`belongsTo(User::class)`), **`approvalRequest(): MorphOne`** — `$this->morphOne(\App\Domains\Workflow\Models\ApprovalRequest::class, 'approvable')`.
- `app/Domains/Akademik/Models/CatatanWaliKelas.php` — `use HasFactory, BelongsToTenant`. Relasi: `siswa()`, `semester()`. Cast `ekstrakurikuler`/`prestasi`/`pkl_info` ke `array`.
- `app/Domains/Akademik/Enums/StatusPengajuanRapor.php` — `enum StatusPengajuanRapor: string { case Draft = 'draft'; case Diajukan = 'diajukan'; case Diverifikasi = 'diverifikasi'; case Disetujui = 'disetujui'; case Ditolak = 'ditolak'; }` + method `label()`.
- Update `app/Domains/Akademik/Models/KomponenPenilaian.php` (dari 04a): tambah `kktp_minimal` ke `$fillable`.

#### 2.3. Perluasan Backward-Compatible ke Action/DTO 04a (Task 2)

- `App\Domains\Akademik\DataTransferObjects\KomponenPenilaianData` dan `UpdateKomponenPenilaianData` — tambah property `?int $kktpMinimal`, dengan `fromArray()` membaca `$data['kktp_minimal'] ?? null`.
- `CreateKomponenPenilaianAction`/`UpdateKomponenPenilaianAction` — sertakan `kktp_minimal` di array yang di-`create()`/di-assign ke model.
- `StoreKomponenPenilaianRequest`/`UpdateKomponenPenilaianRequest`/`StoreKomponenPenilaianSendiriRequest`/`UpdateKomponenPenilaianSendiriRequest` (Admin + Guru) — tambah rule `'kktp_minimal' => ['nullable', 'integer', 'min:0', 'max:100']`.
- **Regression wajib**: seluruh test existing `KomponenPenilaianCrudTest`/`KomponenPenilaianControllerTest` (dari 04a) harus tetap hijau tanpa perubahan assertion — field baru harus benar-benar opsional/backward-compatible.

#### 2.4. `CapaianKompetensiGenerator` (Service, Stateless)

`app/Domains/Akademik/Services/CapaianKompetensiGenerator.php`:
```php
public function generateNarasi(Siswa $siswa, MataPelajaran $mapel, Semester $semester): array
```
Ambil semua `KomponenPenilaian` (TP) milik `$mapel`+`$semester`. Untuk tiap TP, hitung rata-rata `NilaiSiswa.nilai_angka` milik `$siswa` lintas semua `Asesmen` TP itu (`whereNotNull('nilai_angka')`, TP tanpa nilai sama sekali diabaikan). Ambang kelulusan per-TP = `kktp_minimal` TP itu, fallback ke konstanta default `75` kalau `kktp_minimal` NULL.

- TP dengan skor **tertinggi**: kalau skornya ≥ ambang → tambahkan kalimat `"Menunjukkan penguasaan sangat baik dalam {deskripsi TP}."`.
- TP dengan skor **terendah**: kalau skornya < ambang → tambahkan kalimat `"Perlu bimbingan dan pendampingan dalam {deskripsi TP}."`.
- Kedua kondisi independen (bacaan literal spec) — bisa menghasilkan 0, 1, atau 2 kalimat tergantung apakah masing-masing syarat terpenuhi. Kalau tidak ada TP dengan nilai sama sekali, kembalikan array kosong.
- Return: `array{tertinggi: ?string, terendah: ?string}` (kalimat lengkap atau null per slot) — bukan array TP mentah, supaya konsumen (04c UI, 04d PDF) tinggal pakai teksnya langsung. Kelenturan menyunting narasi sebelum diajukan adalah keputusan UI+storage yang diambil di 04c, BUKAN bagian sub-task ini — generator ini murni fungsi komputasi tanpa penyimpanan.
- **Konstanta default ambang** (`75`) didefinisikan sebagai `private const DEFAULT_AMBANG_KKTP = 75;` di dalam service ini — bukan config app-wide, YAGNI sampai ada kebutuhan nyata untuk diubah per-lembaga.

Update `database/seeders/KomponenPenilaianSeeder.php` — isi `kktp_minimal` untuk seluruh TP yang di-seed (nilai realistis, mis. 75).

#### 2.5. Reuse Universal Workflow Engine — Definisi Baru

Update `database/seeders/WorkflowDefinitionSeeder.php`, tambahkan definisi baru (persis pola `PENGADAAN_SARPRAS` yang sudah ada, jangan hapus/ubah definisi itu):
```php
$rapor = WorkflowDefinition::updateOrCreate(
    ['code' => 'RAPOR_SEMESTER'],
    [
        'nama_workflow' => 'Persetujuan Rapor Semester',
        'deskripsi' => 'Alur verifikasi Waka Kurikulum dan persetujuan akhir Kepala Sekolah untuk pengajuan rapor per kelas per semester.',
        'is_active' => true,
    ]
);

WorkflowStep::updateOrCreate(
    ['workflow_definition_id' => $rapor->id, 'step_number' => 1],
    ['step_name' => 'Verifikasi Waka Kurikulum', 'approver_type' => ApproverType::Role, 'approver_value' => 'admin_akademik', 'scope_level' => 'lembaga', 'is_final_step' => false]
);

WorkflowStep::updateOrCreate(
    ['workflow_definition_id' => $rapor->id, 'step_number' => 2],
    ['step_name' => 'Persetujuan Akhir Kepala Sekolah', 'approver_type' => ApproverType::Role, 'approver_value' => 'kepala_sekolah', 'scope_level' => 'lembaga', 'is_final_step' => true]
);
```
Kedua step `ApproverType::Role` (BUKAN `DirectRelation` — hook `wali_kelas` yang sudah ada di `ApproverResolverService::checkDirectRelationApprover()` dirancang untuk skenario requester=Siswa dengan `kelasAktif()`, tidak cocok untuk Rapor di mana requester adalah User/Guru dan approvable adalah `PengajuanRapor`; tidak dipakai di sini).

#### 2.6. DTO Baru

`app/Domains/Akademik/DataTransferObjects/CatatanWaliKelasData.php` — `final readonly class` dengan property: `siswaId`, `semesterId`, `catatanSikap` (`?string`), `catatanPerkembangan` (`?string`), `tinggiBadanCm`/`beratBadanKg`/`lingkarKepalaCm` (`?float`), `ekstrakurikuler`/`prestasi`/`pklInfo` (`array`, default `[]`), `keteranganKenaikan` (`?string`) — `fromArray()` sesuai konvensi DTO existing (`JurnalPresensiData`, `KomponenPenilaianData`).

#### 2.7. 4 Action Baru (`app/Domains/Akademik/Actions/Rapor/`)

1. **`SimpanCatatanWaliKelasAction::execute(CatatanWaliKelasData $data): CatatanWaliKelas`** — `updateOrCreate(['siswa_id' => ..., 'semester_id' => ...], [...])`. **Tidak ada penguncian di sini** — berbeda dari `NilaiSiswa` (lihat 2.7), `CatatanWaliKelas` TIDAK dikunci sub-task ini meskipun `PengajuanRapor`-nya sudah `Disetujui`. Master spec hanya menyebutkan "nilai terkunci permanen", tidak menyebut catatan wali kelas — penguncian catatan (kalau memang diperlukan) adalah keputusan terpisah untuk sub-task berikutnya, bukan diasumsikan di sini.

2. **`SubmitPengajuanRaporAction::execute(Kelas $kelas, Semester $semester, User $user): PengajuanRapor`**:
   - Validasi: setiap `Siswa` di `$kelas` (`$kelas->siswa()->get()`, tanpa filter status — lihat Asumsi) punya `CatatanWaliKelas` untuk `$semester` — kalau ada yang belum, `throw ValidationException::withMessages(['catatan_wali_kelas' => "Siswa berikut belum memiliki catatan wali kelas: {daftar nama}."])`.
   - `DB::transaction`: `PengajuanRapor::updateOrCreate(['kelas_id' => $kelas->id, 'semester_id' => $semester->id], ['status' => StatusPengajuanRapor::Diajukan, 'diajukan_oleh' => $user->id, 'diajukan_pada' => now()])`.
   - Kalau `$pengajuanRapor->approvalRequest` sudah ada (resubmit dari `Ditolak`) → reset `ApprovalRequest` yang sama: `current_step_id = $approvalRequest->workflowDefinition->firstStep()->id`, `status = ApprovalStatus::Pending`, `last_notes = null` — pola identik `Pengadaan\SubmitPengajuanAction`.
   - Kalau belum ada → `InitializeApprovalRequestAction::execute('RAPOR_SEMESTER', $pengajuanRapor, $user)`.

3. **`VerifyPengajuanRaporAction::execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan): PengajuanRapor`** (Waka Kurikulum) — panggil `ProcessApprovalAction::execute($pengajuanRapor->approvalRequest, $user, $action, $catatan)`, lalu sinkron: `Approve` → `status = Diverifikasi`; `Reject` → `status = Ditolak, catatan_revisi = $catatan`.

4. **`ApprovePengajuanRaporAction::execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan): PengajuanRapor`** (Kepala Sekolah) — panggil `ProcessApprovalAction`, lalu sinkron: `Approve` → `status = Disetujui, disetujui_oleh = $user->id, disetujui_pada = now()`; `Reject` → `status = Ditolak, catatan_revisi = $catatan`.

Ketiga Action approval (2-4) memvalidasi `$pengajuanRapor->approvalRequest !== null` sebelum memanggil engine (`ValidationException` kalau null — belum pernah diajukan).

#### 2.8. Penegakan Kunci Nilai (Modifikasi 04a)

`App\Domains\Akademik\Actions\Penilaian\SimpanNilaiSiswaAction::execute()` (dari 04a Task 3) — tambahkan guard di awal method, sebelum `DB::transaction`:
```php
$terkunci = PengajuanRapor::where('kelas_id', $asesmen->kelas_id)
    ->where('semester_id', $asesmen->semester_id)
    ->where('status', StatusPengajuanRapor::Disetujui)
    ->exists();

if ($terkunci) {
    throw ValidationException::withMessages([
        'nilai' => 'Nilai untuk kelas dan semester ini sudah dikunci karena rapor sudah disetujui.',
    ]);
}
```
**Regression wajib**: seluruh test existing `AsesmenControllerTest` (dari 04a Task 3) yang menyimpan nilai tanpa `PengajuanRapor` sama sekali harus tetap lulus (guard tidak memblokir kasus normal tanpa pengajuan rapor).

#### 2.9. Permission Baru

Tambah ke `database/seeders/PermissionSeeder.php`: `rapor.input-wali`, `rapor.ajukan`, `rapor.verify`, `rapor.approve`. Assignment di `RoleSeeder.php`:
- `guru` → `rapor.input-wali`, `rapor.ajukan` (ownership dicek via `wali_kelas_guru_id`, BUKAN role terpisah — pola sama seperti `presensi.isi`/`asesmen.kelola`).
- `admin_akademik` → `rapor.verify` (existing role ini sudah berperan sebagai "Waka Kurikulum" di sistem — lihat `rpp.verify` yang sudah dipegangnya).
- `kepala_sekolah` → `rapor.approve` (dipisah dari `rapor.verify`, sengaja TIDAK diberikan ke `admin_akademik` maupun sebaliknya — integritas dua-orang, beda dari pola RPP yang membolehkan kedua role melakukan `rpp.verify`).

#### 2.10. Test (Feature, headless — memanggil Action langsung, tanpa route/controller)

- `tests/Feature/Akademik/CapaianKompetensiGeneratorTest.php` (atau `Unit/Services/` — ikuti konvensi test unit murni tanpa DB seperti lainnya kalau memungkinkan, tapi ini butuh DB jadi Feature dengan `RefreshDatabase`): skenario TP tertinggi ≥ ambang, TP terendah < ambang, kedua kondisi sekaligus, tidak ada TP dengan nilai sama sekali, `kktp_minimal` NULL pakai fallback 75.
- `tests/Feature/Akademik/RaporApprovalWorkflowTest.php`: alur penuh submit→verify→approve (happy path), submit ditolak karena `CatatanWaliKelas` belum lengkap, verify tolak → resubmit → verify approve → approve, kunci nilai setelah `Disetujui` (coba `SimpanNilaiSiswaAction` setelahnya, harus `ValidationException`), **cross-tenant**: `ApprovalRequest` milik `PengajuanRapor` lembaga lain tidak bisa di-resolve/diproses oleh Waka/Kepsek lembaga sendiri (verifikasi lewat `PengajuanRapor::approvalRequest()` yang tenant-scoped, bukan lookup `ApprovalRequest::find()` langsung).

### Out of Scope (sengaja ditunda)

- Route/controller/view apa pun (Sub-Task 04c).
- Penyimpanan/penyuntingan narasi `CapaianKompetensiGenerator` yang di-generate (keputusan storage untuk fitur "edit sebelum diajukan" — 04c).
- 4 template PDF DomPDF (Sub-Task 04d).
- Perubahan pada `WorkflowDefinition`/`WorkflowStep`/`ApprovalRequest`/`ApprovalLog` milik engine (`app/Domains/Workflow/`) itu sendiri — sub-task ini murni KONSUMEN baru dari engine yang sudah ada, tidak mengubah engine.
- Perubahan definisi `PENGADAAN_SARPRAS` yang sudah ada.
- Auto-fill `tanggal_rapor` atau logic pencetakan — murni field data, diisi manual nanti di 04c/04d.

## 3. Asumsi

- **Koreksi setelah pengecekan kode nyata**: `RaporCalculationService`/`CreateAsesmenAction` (04a) TIDAK memfilter siswa berdasarkan `status` sama sekali — keduanya pakai `$kelas->siswa()` / `Siswa::where('kelas_id', ...)` polos, mengikutsertakan siswa berstatus lulus/pindah/keluar juga. Sub-task ini mengikuti presenden yang sama persis (`$kelas->siswa()->get()`, tanpa filter status) untuk validasi kelengkapan `CatatanWaliKelas` di `SubmitPengajuanRaporAction` — bukan `where('status', 'aktif')` seperti draf awal desain saya sebelumnya (asumsi itu keliru, sudah diverifikasi ulang terhadap kode sebenarnya).
- Semester yang dipakai untuk validasi/kalkulasi adalah semester yang secara eksplisit di-passing ke Action (bukan auto-detect "semester aktif") — konsisten dengan `RaporCalculationService::hitungRekapKelas(Kelas $kelas, Semester $semester)` yang sudah ada.
- `Kelas.wali_kelas_guru_id` tetap sumber kebenaran tunggal untuk "siapa Wali Kelas kelas ini" (sudah dipakai `RekapKehadiranController` dari 03a) — permission `rapor.input-wali`/`rapor.ajukan` di-guard di level pemanggil Action (controller di 04c nanti) via ownership check ini, BUKAN di dalam Action itu sendiri (Action tetap murni domain logic, tidak tahu soal HTTP/auth — pola sama seperti `SimpanNilaiSiswaAction` dari 04a yang juga tidak melakukan authorization sendiri).
- Tidak ada migrasi data untuk `PengajuanRapor`/`CatatanWaliKelas` lama — tabel baru, tidak ada data existing untuk dipindahkan.

## 4. Kriteria Penerimaan (Acceptance Criteria)

- [ ] 3 migrasi baru berjalan bersih (`pengajuan_rapor`, `catatan_wali_kelas`, `ALTER komponen_penilaian ADD kktp_minimal`).
- [ ] `PengajuanRapor` dan `CatatanWaliKelas` pakai `BelongsToTenant`, punya `lembaga_id` terisi otomatis atau eksplisit dari Action.
- [ ] `PengajuanRapor::approvalRequest()` adalah `MorphOne` yang benar ke `ApprovalRequest` — dibuktikan test bisa membuat, mengambil, dan memproses `ApprovalRequest` lewat relasi ini.
- [ ] `CapaianKompetensiGenerator::generateNarasi()` menghasilkan kalimat yang benar untuk kelima skenario di 2.10, termasuk fallback ambang 75 saat `kktp_minimal` NULL.
- [ ] `WorkflowDefinitionSeeder` menambah `RAPOR_SEMESTER` (2 step) TANPA mengubah/menghapus `PENGADAAN_SARPRAS` yang sudah ada — dibuktikan test existing `WorkflowEngineTest`/`Pengadaan*Test` tetap hijau.
- [ ] `SubmitPengajuanRaporAction` menolak submit kalau ada siswa di kelas tanpa `CatatanWaliKelas` (tanpa filter status, lihat Asumsi), dan mereset `ApprovalRequest` yang sama (bukan bikin baru) saat resubmit dari `Ditolak`.
- [ ] `VerifyPengajuanRaporAction`/`ApprovePengajuanRaporAction` mensinkronkan `PengajuanRapor.status` dengan benar untuk Approve maupun Reject di masing-masing tahap.
- [ ] `SimpanNilaiSiswaAction` (04a) menolak simpan nilai untuk kelas+semester yang `PengajuanRapor`-nya `Disetujui`, TIDAK menolak untuk kelas+semester lain (regression 04a tetap hijau).
- [ ] 4 field DTO/Action/FormRequest `KomponenPenilaian` (04a) menerima `kktp_minimal` opsional tanpa merusak test existing.
- [ ] Test cross-tenant: `ApprovalRequest` milik `PengajuanRapor` lembaga lain tidak bisa diproses lewat `VerifyPengajuanRaporAction`/`ApprovePengajuanRaporAction` oleh aktor lembaga berbeda (dibuktikan lewat `ApproverResolverService::checkRoleApprover()`'s existing `scope_level === 'lembaga'` guard — sub-task ini menulis test yang membuktikan guard itu benar-benar berlaku untuk kasus Rapor, bukan hanya untuk Pengadaan).
- [ ] Permission baru (`rapor.input-wali`, `rapor.ajukan`, `rapor.verify`, `rapor.approve`) terdaftar dan ter-assign sesuai role di 2.9.
- [ ] `php artisan test` full suite tetap 0 gagal (baseline dicek ulang di awal eksekusi plan, bukan angka dari sesi sebelumnya).

## 5. Rencana Pengujian

```bash
php artisan test --filter=CapaianKompetensi
php artisan test --filter=RaporApproval
php artisan test --filter=KomponenPenilaian
php artisan test --filter=Asesmen
php artisan test --filter=Workflow
php artisan test --filter=Pengadaan
php artisan test
```

Tidak ada verifikasi manual browser untuk sub-task ini (headless by design) — verifikasi manual menyusul di 04c setelah UI ada.
