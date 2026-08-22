# Kehadiran SDM — Sub-project 4 (Terakhir): Izin/Cuti Berjenjang — Spec

## 1. Latar Belakang

Sub-project 1-3 (termasuk item tertunda Shift Bergilir) SUDAH SELESAI di branch `sdm-v1`, full test suite hijau (2007/2007). Modul saat ini bisa mencatat kehadiran, tahu jam kerja/toleransi/shift per pegawai, tapi status Izin/Sakit HANYA bisa di-set LANGSUNG oleh admin lewat input manual (`RecordManualAttendanceAction`, Sub-project 1) — TIDAK BERUBAH, jalur itu tetap ada untuk kasus admin mencatat atas nama pegawai yang tidak bisa akses sistem.

Sub-project 4 menambahkan **jalur baru**: pegawai (Guru/Karyawan) **mengajukan sendiri** izin/sakit/cuti, melalui **approval berjenjang**, baru statusnya tercatat resmi di `AttendanceRecord`/`AttendanceEvent`. Ini sub-project TERAKHIR dari 4 sub-project resmi modul Kehadiran SDM.

**Reuse total** domain generik `App\Domains\Workflow` (`WorkflowDefinition`, `WorkflowStep`, `ApprovalRequest`, `ApprovalLog`, `ApproverResolverService`, `InitializeApprovalRequestAction`, `ProcessApprovalAction`) yang sudah dipakai Rapor Akademik & Pengadaan Sarpras — dikonfirmasi lewat pembacaan kode nyata kedua domain itu sebelum brainstorming, BUKAN asumsi dari nama class.

## 2. Keputusan Desain (hasil brainstorming — logika alur DULU, baru teknis)

### 2.1 Alur Bisnis

| Tahap | Keputusan |
|---|---|
| Siapa mengajukan | Guru DAN Karyawan, keduanya, untuk DIRI SENDIRI saja |
| Ajukan atas nama orang lain | TIDAK ADA jalur baru — tetap pakai admin-manual existing (`RecordManualAttendanceAction`, Sub-project 1), TIDAK BERUBAH |
| Jenis pengajuan | 1 alur SERAGAM untuk Izin/Sakit/Cuti — beda jenisnya cuma kolom `kategori` pada pengajuan, BUKAN workflow terpisah. Boleh diajukan retroaktif (H-1/hari-H untuk sakit dadakan) — approval tetap jalan, keputusannya menyusul |
| Approver berjenjang | Role-based SAJA (2 lapis: **Kepala Sekolah** → **Admin SDM**, keduanya `scope_level: lembaga`) — TIDAK PERNAH menyentuh `ApproverResolverService.php` (file shared dipakai Rapor & Pengadaan), karena `ApproverType::Role` sudah generik (nama role dari data, bukan hardcode kode) |
| Struktur lapis per kategori pegawai | SERAGAM 1 `WorkflowDefinition` untuk SEMUA pegawai (guru maupun karyawan, kategori apapun) — kalau nanti perlu beda per kategori, tinggal tambah `WorkflowDefinition` baru, arsitektur sudah mendukung tanpa perubahan struktural |
| Konsekuensi ke kehadiran saat Approved | OTOMATIS: sistem generate `AttendanceEvent` (method `System`) untuk SETIAP tanggal dalam rentang, begitu status jadi `Approved` penuh |
| Konsekuensi ke kehadiran saat Rejected | TIDAK ADA aksi otomatis — auto-alpa job (`TandaiAlpaOtomatisSdm`) yang menandai Alpa di siklus H-1 berikutnya seperti biasa (pengajuan sudah tidak lagi Pending, jadi tidak di-skip lagi) |
| Interaksi dengan auto-alpa | `TandaiAlpaOtomatisSdm` SKIP pegawai yang punya `PengajuanIzinCuti` berstatus Pending/InReview yang rentang tanggalnya mencakup H-1 — mencegah Alpa keliru sebelum keputusan approval turun |
| Pembatalan | HANYA boleh SELAMA status masih Pending/InReview. Setelah Approved (event sudah dibuat, immutable) atau Rejected, pengajuan final — TIDAK bisa dibatalkan lagi (kalau perlu koreksi setelah Approved, itu jalur admin-manual existing) |
| Kuota/saldo cuti | DI LUAR CAKUPAN — item roadmap terpisah kalau nanti dibutuhkan |

### 2.2 Perluasan Enum (Murni Aditif)

| Enum | Case baru | Dampak |
|---|---|---|
| `App\Domains\Sdm\Enums\AttendanceStatus` | `Cuti = 'cuti'` | `match()` di `label()`/`badgeTone()` TIDAK punya `default` — PHP akan lempar `UnhandledMatchError` runtime kalau salah satu lupa di-update, jadi TIDAK MUNGKIN lolos diam-diam. WAJIB update kedua method. |
| `App\Domains\Workflow\Enums\ApprovalStatus` (SHARED — dipakai Rapor & Pengadaan) | `Cancelled = 'cancelled'` | Murni aditif — Rapor & Pengadaan tidak pernah memakai case ini, TIDAK ADA perubahan perilaku untuk mereka. `label()`/`badgeTone()` di enum itu juga TIDAK punya `default`, WAJIB di-update juga (proteksi yang sama). |
| `App\Domains\Workflow\Enums\ApprovalAction` (SHARED — dipakai Rapor & Pengadaan) | `Cancel = 'CANCEL'` | Murni aditif, dipakai `BatalkanPengajuanIzinCutiAction` (§4) untuk mencatat `ApprovalLog`. Kolom DB `approval_logs.action` cuma `string` biasa, tidak ada constraint yang perlu diubah. `label()` WAJIB di-update juga (proteksi sama). |

## 3. Struktur Data

### 3.1 Tabel baru `pengajuan_izin_cuti`

Domain `App\Domains\Sdm\Models\PengajuanIzinCuti`, DENGAN `BelongsToTenant`, `lembaga_id` WAJIB terisi (pola sama `PenugasanShift` Sub-project 3b — selalu milik pegawai spesifik, tidak ada konsep nasional).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `lembaga_id` | FK `lembaga` | disalin dari `$pegawai->lembaga_id` saat dibuat |
| `pegawai_type` | string | morph type (`Guru`/`Karyawan`) |
| `pegawai_id` | bigint | morph id |
| `kategori` | string | enum BARU `App\Domains\Sdm\Enums\KategoriPengajuanIzin` (`Izin`, `Sakit`, `Cuti`) |
| `tanggal_mulai` | date | |
| `tanggal_selesai` | date | WAJIB terisi (BEDA dari `PenugasanShift.tanggal_selesai` yang nullable — pengajuan izin/cuti SELALU py rentang pasti, tidak ada konsep "tanpa batas") — kalau 1 hari saja, `tanggal_mulai` = `tanggal_selesai` |
| `alasan` | text | |
| `timestamps` | | |

### 3.2 Enum `KategoriPengajuanIzin`

`Izin = 'izin'`, `Sakit = 'sakit'`, `Cuti = 'cuti'`. Method `toAttendanceStatus(): AttendanceStatus` — `Izin` → `AttendanceStatus::Izin`, `Sakit` → `AttendanceStatus::Sakit`, `Cuti` → `AttendanceStatus::Cuti` (case baru §2.2) — dipakai saat generate `AttendanceEvent` pasca-Approved (§4).

### 3.3 Relasi ke `ApprovalRequest` (Workflow domain, TIDAK ADA tabel baru untuk approval-nya sendiri)

`PengajuanIzinCuti::approvalRequest(): MorphOne` → `ApprovalRequest::class` via kolom `approvable_type`/`approvable_id` yang SUDAH ADA — pola SAMA PERSIS `PengajuanPengadaan::approvalRequest()` (Pengadaan Sarpras, dikonfirmasi lewat kode nyata).

Requester `ApprovalRequest` = pegawai itu sendiri (`Guru`/`Karyawan` model, BUKAN `User`) — konsisten `ApproverResolverService::checkRoleApprover()` yang sudah baca `$request->requester?->lembaga_id` secara generik (jalan untuk model apa pun yang punya `lembaga_id`, termasuk `Guru`/`Karyawan`).

## 4. Alur Teknis (Action Baru, Pola Reuse SAMA PERSIS Pengadaan Sarpras)

**`AjukanIzinCutiAction`** (mem-bungkus `InitializeApprovalRequestAction`, pola sama `SubmitPengajuanAction`):
1. Validasi `tanggal_mulai <= tanggal_selesai`.
2. `PengajuanIzinCuti::create([...])`.
3. `InitializeApprovalRequestAction::execute(workflowCode: 'IZIN_CUTI_SDM', approvable: $pengajuan, requester: $pegawai)`.

**`ProsesApprovalIzinCutiAction`** (mem-bungkus `ProcessApprovalAction`, pola sama `ProcessProposalApprovalAction`):
1. Panggil `ProcessApprovalAction::execute($approvalRequest, $user, $action, $notes)`.
2. `$approvalRequest->refresh()`.
3. Kalau `$approvalRequest->status === ApprovalStatus::Approved` → untuk SETIAP tanggal dari `tanggal_mulai` s.d. `tanggal_selesai` (inklusif): buat `AttendanceEvent` (`method: AttendanceMethod::System`, `arah: 'masuk'`, `status: $pengajuan->kategori->toAttendanceStatus()`, `waktu: tanggal 23:59` — pola SAMA `TandaiAlpaOtomatisSdm`, `dicatat_oleh_user_id: null`, `catatan: 'Disetujui via pengajuan izin/cuti #'.$pengajuan->id`), lalu `AttendanceRecordAggregator::sync($pegawai, $tanggal)` per tanggal.

**`BatalkanPengajuanIzinCutiAction`**:
1. Validasi `$approvalRequest->status` masih `Pending`/`InReview` — kalau tidak, tolak (`ValidationException`, pesan jelas "Pengajuan yang sudah [Disetujui/Ditolak] tidak dapat dibatalkan").
2. Validasi requester = aktor yang membatalkan (pegawai cuma bisa batalkan pengajuannya sendiri).
3. `$approvalRequest->update(['status' => ApprovalStatus::Cancelled])` + catat `ApprovalLog::create(['approval_request_id' => ..., 'workflow_step_id' => $approvalRequest->current_step_id, 'user_id' => $request->user()->id, 'action' => ApprovalAction::Cancel, 'notes' => $catatan])`.

Kolom `approval_logs.action` di DB HANYA `string` biasa (BUKAN DB enum constraint — dikonfirmasi lewat migrasi `2026_08_16_130000_create_universal_workflow_tables.php`), jadi menambah case BARU `Cancel = 'CANCEL'` ke `App\Domains\Workflow\Enums\ApprovalAction` (SHARED, dipakai Rapor & Pengadaan) AMAN dan murni aditif — TIDAK ADA migrasi/constraint yang perlu diubah. `ApprovalAction::label()` (kalau ada `match()` tanpa `default`) WAJIB ditambah case ini juga, sama proteksi PHP seperti §2.2.

## 5. Integrasi ke `TandaiAlpaOtomatisSdm` (Sub-project 2/3/3b)

Tambah 1 lapis filter TAMBAHAN ke `$pegawaiList` yang sudah ada (SETELAH filter `ShiftAwareAttendanceResolver::resolveLibur()` yang sudah ada, TIDAK menggantikannya): pegawai DIKECUALIKAN dari penandaan Alpa kalau punya `PengajuanIzinCuti` yang `tanggal_mulai <= $tanggal <= tanggal_selesai` DAN `approvalRequest->status` in `[Pending, InReview]`.

`ShiftAwareAttendanceResolver.php` dan `AttendancePolicyResolver.php` TIDAK disentuh sama sekali oleh perubahan ini — filter baru ini query terpisah, ditambahkan di `TandaiAlpaOtomatisSdm::handle()` sendiri (file INI boleh diubah, sudah pernah diubah 2x sebelumnya untuk alasan serupa).

## 6. RBAC

Permission baru (format `[domain].[action]` konsisten):

| Permission | Untuk |
|---|---|
| `kehadiran-sdm.izin.ajukan` | Guru/Karyawan mengajukan izin/cuti untuk diri sendiri |
| `kehadiran-sdm.izin.approve` | Memproses approval (dipegang role approver via `WorkflowStep`, TAPI permission ini tetap perlu ada sebagai gerbang tambahan sebelum masuk ke `ProcessApprovalAction` — konsisten pola existing Pengadaan/Rapor yang juga py permission terpisah dari resolusi approver) |
| `kehadiran-sdm.izin.lihat-sendiri` | Guru/Karyawan lihat riwayat pengajuannya sendiri |

`guru`, `karyawan_pool`, `karyawan_lembaga` role dapat `kehadiran-sdm.izin.ajukan` + `kehadiran-sdm.izin.lihat-sendiri`. `kepala_sekolah` dan `admin_sdm` dapat `kehadiran-sdm.izin.approve` (role approver ditentukan `WorkflowStep.approver_value`, permission ini gerbang tambahan di controller).

TIDAK ADA hardcode nama role di kode manapun — `ApproverResolverService::canUserApprove()` yang sudah ada (generik, tidak diubah) yang menentukan SIAPA boleh approve LANGKAH mana, permission `kehadiran-sdm.izin.approve` cuma gerbang "boleh mengakses halaman approval sama sekali".

## 7. Seed Workflow

Tambah blok BARU ke `database/seeders/WorkflowDefinitionSeeder.php` yang SUDAH ADA (pola SAMA PERSIS 2 workflow lain di situ, TIDAK bikin mekanisme konfigurasi baru):

```
code: 'IZIN_CUTI_SDM'
Step 1: step_name 'Verifikasi Kepala Sekolah', approver_type Role, approver_value 'kepala_sekolah', scope_level 'lembaga', is_final_step false
Step 2: step_name 'Persetujuan Admin SDM', approver_type Role, approver_value 'admin_sdm', scope_level 'lembaga', is_final_step true
```

## 8. UI

**Self-service (Guru/Karyawan)**: halaman baru di bawah `routes/sdm.php` yang sudah ada (Sub-project 1, sudah punya `sdm.qr-saya`) — daftar riwayat pengajuan sendiri + form ajukan baru (kategori, tanggal mulai/selesai, alasan) + tombol batalkan (muncul kalau status masih Pending/InReview).

**Approval (Kepala Sekolah/Admin SDM)**: halaman baru daftar pengajuan yang menunggu approval di langkah aktor itu (query `ApprovalRequest` yang `current_step`-nya bisa di-approve aktor ini, via `ApproverResolverService::canUserApprove()` yang sudah ada) + aksi Setujui/Tolak dengan catatan opsional.

Detail routing/controller/view PERSIS ditentukan di tahap plan implementasi, mengikuti pola yang sudah established (controller thin, Action yang berat, `$this->authorize()` di setiap endpoint).

## 9. Yang TIDAK Berubah / Di Luar Cakupan

- `RecordManualAttendanceAction` (jalur admin-manual Izin/Sakit/Alpa langsung) — TIDAK BERUBAH.
- `ApproverResolverService.php`, `ShiftAwareAttendanceResolver.php`, `AttendancePolicyResolver.php`, `KalenderKerjaSdmResolver.php` — TIDAK disentuh sama sekali.
- Kuota/saldo cuti tahunan — di luar cakupan.
- Pengajuan atas nama pegawai lain oleh admin — tidak dibangun, tetap pakai jalur admin-manual existing.
- Notifikasi (email/WhatsApp) ke approver saat ada pengajuan baru — TIDAK disebutkan dalam brainstorming, di luar cakupan default kecuali diminta eksplisit saat plan (konsisten pola "jangan tambah fitur di luar yang diminta").

## 10. Testing

- Test `AjukanIzinCutiAction`: berhasil membuat `PengajuanIzinCuti` + `ApprovalRequest` berstatus Pending, step aktif = step 1 (Kepala Sekolah); tanggal_mulai > tanggal_selesai ditolak validasi.
- Test `ProsesApprovalIzinCutiAction`: approve step 1 → status jadi InReview, pindah ke step 2, BELUM ada AttendanceEvent; approve step 2 (final) → status Approved, AttendanceEvent dibuat untuk SETIAP tanggal dalam rentang dengan status sesuai kategori; reject di step manapun → status Rejected, TIDAK ADA AttendanceEvent dibuat.
- Test RBAC approval: `ApproverResolverService::canUserApprove()` (TIDAK diubah, dipakai apa adanya) — aktor tanpa role `kepala_sekolah` tidak bisa approve step 1; aktor `kepala_sekolah` dari lembaga LAIN tidak bisa approve (scope_level lembaga, sudah ditangani logic existing).
- Test `BatalkanPengajuanIzinCutiAction`: berhasil selama Pending/InReview; ditolak kalau sudah Approved/Rejected; ditolak kalau bukan requester aslinya.
- Test integrasi `TandaiAlpaOtomatisSdm`: pegawai dengan pengajuan Pending mencakup H-1 → TIDAK ditandai Alpa; pengajuan sudah Rejected → TETAP ditandai Alpa seperti biasa; pengajuan Approved (event sudah ada) → tidak ditandai Alpa karena `AttendanceRecord` sudah ada (perilaku existing tidak berubah).
- Test enum: `AttendanceStatus::Cuti` dan `ApprovalStatus::Cancelled` — pastikan `label()`/`badgeTone()` KEDUA enum tidak melempar `UnhandledMatchError` untuk case baru (test eksplisit memanggil kedua method untuk SEMUA case, bukan cuma yang baru).
- Test regresi: SEMUA test existing Rapor Akademik & Pengadaan Sarpras yang menyentuh `ApprovalStatus`/`ApproverResolverService` tetap hijau tanpa perubahan (jalankan test suite Rapor + Pengadaan penuh, bukan cuma SDM, karena ini SATU-SATUNYA sub-project yang menyentuh file benar-benar shared lintas domain).
- Full suite HANYA di task terakhir plan implementasi, minta izin user dulu.

## 11. Asumsi

- Baseline: commit `f71cad8` di branch `sdm-v1` (handoff log item Shift Bergilir) saat spec ini ditulis. Plan implementasi WAJIB verifikasi ulang isi `app/Domains/Workflow/` (SEMUA file), `database/seeders/WorkflowDefinitionSeeder.php`, `app/Console/Commands/TandaiAlpaOtomatisSdm.php`, `app/Domains/Sdm/Enums/AttendanceStatus.php` kalau ada commit baru masuk sebelum eksekusi.
- Test regresi Rapor Akademik & Pengadaan Sarpras (§10) WAJIB dijalankan sungguhan, bukan diasumsikan aman hanya karena perubahan "aditif" secara teori.
