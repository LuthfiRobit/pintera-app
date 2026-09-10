# Spec: Guard Status Terminal di ProcessApprovalAction (Lintas Rapor/Pengadaan/SDM)

- **Tanggal**: 2026-09-10
- **Cabang Git**: `rbac-v2`
- **File utama**: `app/Domains/Workflow/Actions/ProcessApprovalAction.php`
- **Domain terdampak**: Rapor (Akademik), Pengadaan, SDM (Izin/Cuti) — via engine bersama `App\Domains\Workflow`

## 1. Latar Belakang

Saat audit alur Izin/Cuti SDM (setelah audit Rapor selesai), ditemukan bahwa `ProcessApprovalAction::execute()` — engine approval bersama yang dipakai 3 domain — **tidak pernah menolak memproses ulang `ApprovalRequest` yang statusnya sudah final** (`Approved`, `Rejected`, `Cancelled`, `RevisionRequired`). Ini bukan bug spesifik satu domain; fungsi ini dipakai identik oleh 4 Action konsumen: `VerifyPengajuanRaporAction`, `ApprovePengajuanRaporAction` (Rapor), `ProcessProposalApprovalAction` (Pengadaan), `ProsesApprovalIzinCutiAction` (SDM).

**Dibuktikan lewat test diagnostik** (dijalankan lalu dihapus, bukan bagian permanen codebase): memanggil keputusan approval final DUA KALI pada request yang sama menghasilkan:
- **SDM**: `AttendanceEvent` ganda (pegawai/guru tercatat izin/sakit/cuti 2x di tanggal yang sama) + `ApprovalLog` ganda.
- **Pengadaan**: `ApprovalLog` ganda (status proposal tidak rusak — kebetulan idempoten karena logic re-sync status-nya cuma re-set ke nilai yang sama).
- **Rapor**: TIDAK terdampak — `PersetujuanController::decision()` sudah punya guard status di level controller (`abort_unless($pengajuanRapor->status === $this->statusUntukAktor($request), 404, ...)`) yang memblokir percobaan kedua SEBELUM sempat memanggil Action sama sekali.

**Pemicu nyata di dunia nyata**: double-klik tombol "Setujui"/"Tolak" (koneksi lambat), tombol back browser lalu submit ulang, atau race condition asli (2 admin berbeda approve nyaris bersamaan). Ini BUKAN skenario eksotis — form HTML biasa tanpa proteksi submit-ganda rentan terhadap ini.

**Kenapa Rapor "kebetulan aman"**: guard yang jadi pelindung Rapor ada di level CONTROLLER, bukan di level Action/engine — jadi proteksi itu cuma berlaku untuk jalur HTTP Rapor, tidak otomatis menular ke Pengadaan/SDM yang tidak punya guard controller setara.

## 2. Akar Masalah (Kode Saat Ini)

`app/Domains/Workflow/Actions/ProcessApprovalAction.php`:
```php
public function execute(ApprovalRequest $request, User $user, ApprovalAction $action, ?string $notes = null): bool
{
    $currentStep = $request->currentStep;

    if (! $currentStep) {
        throw ValidationException::withMessages([
            'approval' => 'Permintaan persetujuan ini sudah selesai atau tidak memiliki langkah aktif.',
        ]);
    }

    if (! $this->resolverService->canUserApprove($currentStep, $user, $request)) {
        throw ValidationException::withMessages([
            'approval' => 'Anda tidak memiliki hak akses untuk memproses langkah persetujuan ini.',
        ]);
    }

    return DB::transaction(function () use ($request, $currentStep, $user, $action, $notes) {
        // ...ApprovalLog::create(), transisi status...
    });
}
```

**Kenapa pesan error yang ada ("sudah selesai atau tidak memiliki langkah aktif") tidak menyelamatkan**: pesan itu cuma terpicu kalau `$request->currentStep` bernilai `null` — TAPI `current_step_id` **tidak pernah di-null-kan** setelah approval final tercapai (lihat blok `if ($currentStep->is_final_step) { $request->status = ApprovalStatus::Approved; $request->save(); return true; }` — `current_step_id` dibiarkan menunjuk ke step final yang sama). Jadi `$request->currentStep` TETAP resolve ke step yang sama walau status sudah `Approved` — pesan error itu secara desain seharusnya menangkap kasus ini, tapi implementasinya tidak pernah benar-benar menutup celahnya.

## 3. Perbaikan

Tambahkan pengecekan status di AWAL `ProcessApprovalAction::execute()`, sebelum pengecekan `currentStep` maupun `canUserApprove()`:

```php
public function execute(ApprovalRequest $request, User $user, ApprovalAction $action, ?string $notes = null): bool
{
    if (! in_array($request->status, [ApprovalStatus::Pending, ApprovalStatus::InReview], true)) {
        throw ValidationException::withMessages([
            'approval' => 'Permintaan persetujuan ini sudah selesai diproses ('.$request->status->label().'), tidak dapat diproses ulang.',
        ]);
    }

    $currentStep = $request->currentStep;

    if (! $currentStep) {
        throw ValidationException::withMessages([
            'approval' => 'Permintaan persetujuan ini tidak memiliki langkah aktif.',
        ]);
    }

    if (! $this->resolverService->canUserApprove($currentStep, $user, $request)) {
        throw ValidationException::withMessages([
            'approval' => 'Anda tidak memiliki hak akses untuk memproses langkah persetujuan ini.',
        ]);
    }

    return DB::transaction(function () use ($request, $currentStep, $user, $action, $notes) {
        // ...tidak berubah...
    });
}
```

**Status yang DIBLOKIR dari diproses ulang**: `Approved`, `Rejected`, `Cancelled`, `RevisionRequired` (4 status final).
**Status yang TETAP BOLEH diproses**: `Pending`, `InReview` (2 status aktif — workflow masih berjalan).

## 4. Verifikasi Keamanan Perubahan (Sudah Dilakukan Sebelum Spec Ini Ditulis)

**Pertanyaan kunci yang dijawab**: apakah perbaikan ini akan mematahkan alur bisnis sah manapun, terutama alur "ajukan ulang setelah ditolak/revisi"?

Ditelusuri **semua pemanggil `ProcessApprovalAction::execute()`** di seluruh codebase (grep menyeluruh, hasil: persis 4 lokasi, semuanya Action "proses keputusan approver" — tidak ada satu pun jalur resubmission yang memanggilnya):
- `VerifyPengajuanRaporAction::execute()`
- `ApprovePengajuanRaporAction::execute()`
- `ProcessProposalApprovalAction::execute()`
- `ProsesApprovalIzinCutiAction::execute()`

Ditelusuri **bagaimana alur "ajukan ulang" bekerja** untuk Rapor (`SubmitPengajuanRaporAction`) dan Pengadaan (`SubmitPengajuanAction`) — keduanya **me-reset status `ApprovalRequest` kembali ke `Pending` secara LANGSUNG** (`$existingApprovalRequest->status = ApprovalStatus::Pending; $existingApprovalRequest->save();`), **TIDAK melewati `ProcessApprovalAction` sama sekali**. Artinya perbaikan ini tidak akan pernah "dilihat" oleh jalur resubmission — keduanya beroperasi di jalur kode yang sepenuhnya terpisah.

**Kasus khusus Pengadaan — aksi "Revisi" aktif dipakai** (beda dari Rapor & SDM yang cuma punya Setujui/Tolak): `ApprovalAction::RequestRevision` adalah opsi aksi yang benar-benar tersedia di form keputusan Pengadaan (`ProcessApprovalRequest` FormRequest mengizinkan seluruh enum `ApprovalAction`, termasuk `RequestRevision`). Dikonfirmasi: mengirim SEBUAH pengajuan ke status "Perlu Revisi" (transisi masuk) tetap bisa dilakukan kapan saja selama status pengajuan masih `Pending`/`InReview` (SAMA SEKALI tidak disentuh perbaikan ini) — yang diblokir HANYA memproses ULANG pengajuan yang statusnya SUDAH `RevisionRequired`, sampai pengaju submit ulang (yang me-reset ke `Pending` lewat jalur terpisah di atas).

**Kesimpulan bisnis**: alur "Approver kirim revisi → Pengaju perbaiki & submit ulang → Approver review lagi dari awal" berjalan identik seperti sekarang. Yang berubah HANYA: approver (siapa pun) tidak bisa lagi memproses (Setujui/Tolak/Revisi) dua kali pada pengajuan yang SAMA yang statusnya sudah final.

## 5. Dampak UX (Tambahan, Bukan Sekadar Perbaikan Data)

Saat ini, percobaan kedua pada request yang sudah final **gagal secara diam-diam dari sisi bisnis** (tidak ada error yang terlihat user, padahal data ganda sudah tercipta di background). Setelah perbaikan, percobaan kedua akan menampilkan pesan error eksplisit dan JELAS ("permintaan ini sudah selesai diproses (Disetujui), tidak dapat diproses ulang") — user langsung tahu kenapa aksinya tidak berhasil, alih-alih tidak sadar ada apa-apa.

## 6. Pengujian

Untuk SETIAP dari 3 domain konsumen, tambahkan/pastikan ada test yang membuktikan:
1. Memanggil Action proses-keputusan (Verify/Approve Rapor, ProcessProposalApproval Pengadaan, ProsesApprovalIzinCuti SDM) **kedua kalinya** pada request yang statusnya SUDAH final (mis. sudah `Approved` di step final) → melempar `ValidationException`, TIDAK membuat `ApprovalLog` tambahan, TIDAK membuat `AttendanceEvent` tambahan (khusus SDM).
2. Regresi: alur normal SATU KALI proses (approve/reject/revisi) di status `Pending`/`InReview` tetap berhasil seperti biasa — tidak boleh ada regresi pada test yang sudah ada.
3. Regresi: alur resubmission (Rapor & Pengadaan) setelah Ditolak/Revisi tetap berfungsi — pengajuan bisa disubmit ulang dan status kembali `Pending`, lalu diproses ulang dari step pertama seperti biasa.
4. Test unit langsung di level `ProcessApprovalAction`/`tests/Feature/Workflow/` (kalau ada, atau ditambahkan) untuk skenario status non-aktif secara generik (tidak terikat 1 domain spesifik) — melengkapi pola yang sudah ada dari perbaikan fail-closed sebelumnya di sesi ini (`ApproverResolverServiceTest.php`).

Test diagnostik yang dipakai untuk MEMBUKTIKAN bug ini (dibuat sementara, sudah dihapus sebelum spec ini ditulis) TIDAK menjadi bagian permanen dari test suite — tapi pola skenarionya (approve final dua kali, cek jumlah `ApprovalLog`/`AttendanceEvent` sebelum-sesudah) dijadikan acuan untuk menulis test permanen yang proper di tahap plan.

## 7. Item yang SENGAJA TIDAK Masuk Scope

- **Tidak menambah guard status di level controller** untuk Pengadaan/SDM (meniru pola `abort_unless(...)` Rapor) — perbaikan di root (`ProcessApprovalAction`) sudah cukup menutup celah untuk ketiganya sekaligus, konsisten dengan filosofi "perbaiki akar sekali, jangan tambal per-domain" yang dipakai sepanjang sesi ini untuk 2 perbaikan Workflow engine sebelumnya (session-validation fix, fail-closed null-target fix).
- **Tidak mengubah desain `current_step_id` supaya di-null-kan setelah approval final** — walau itu JUGA akan menutup celah (karena `if (! $currentStep)` di awal method akan langsung menangkapnya), perbaikan berbasis status eksplisit lebih jelas maksudnya (pesan error bisa menyebutkan status pasti) dan tidak berisiko memengaruhi kode lain yang mungkin bergantung pada `current_step_id` tetap terisi setelah selesai (mis. tampilan "step terakhir" di UI riwayat/audit trail — `$ar?->currentStep?->step_name` dipakai di beberapa view untuk menampilkan step MANA yang terakhir memutuskan, termasuk saat status sudah final).
- **Tidak menambah rate-limiting/debounce di level frontend** (mis. disable tombol submit setelah diklik) — itu perbaikan UX tambahan yang independen, bisa jadi item terpisah kalau diperlukan, tapi perbaikan backend ini sudah cukup untuk mencegah kerusakan DATA terlepas dari perilaku frontend.

## 8. Self-Review (Putaran 1)

**Cakupan vs temuan**: semua 3 domain (Rapor/Pengadaan/SDM) dan mekanisme fail-safe yang sudah ada (Rapor controller guard) sudah dibahas eksplisit di §1-§4. Tidak ada domain konsumen `ProcessApprovalAction` yang terlewat (diverifikasi via grep, persis 4 pemanggil).

**Placeholder scan**: kode di §3 adalah kode final siap tempel, tidak ada "TODO"/placeholder. Pesan error final menyebutkan `$request->status->label()` supaya informatif (bukan generik).

**Ambiguitas**: status mana yang "final" vs "aktif" sudah didaftar eksplisit di §3 (4 final, 2 aktif) — tidak ada status `ApprovalStatus` yang terlewat dicek (total 6 di enum, 4+2=6, cocok).

**Konsistensi arsitektur**: keputusan "tidak menambah guard controller per-domain" dan "tidak mengubah desain current_step_id" masing-masing didokumentasikan alasannya di §7, konsisten dengan keputusan arsitektur sebelumnya di sesi ini (perbaikan root cause, bukan tambal per-domain).

## 9. Self-Review (Putaran 2) — Verifikasi Empiris `ApprovalAction` Enum

Dibaca langsung `app/Domains/Workflow/Enums/ApprovalAction.php` untuk memastikan §3 dan §6 tidak salah sebut nama aksi. Terkonfirmasi 4 case: `Approve`, `Reject`, `RequestRevision`, `Cancel` — SEMUA sudah disebut benar di spec ini. Catatan tambahan: `Cancel` TIDAK PERNAH diproses lewat `ProcessApprovalAction` sama sekali (dikonfirmasi tidak ada `if ($action === ApprovalAction::Cancel)` di method itu) — pembatalan (`BatalkanPengajuanIzinCutiAction`) punya jalur sendiri yang langsung memanipulasi `ApprovalLog`/status, jadi otomatis TIDAK terdampak perbaikan ini sama sekali (tidak perlu dibahas lebih lanjut sebagai kasus terpisah).

## 10. Self-Review (Putaran 3) — Verifikasi Empiris Keputusan Arsitektur §7

Klaim di §7 ("`current_step_id` dipakai UI untuk menampilkan step terakhir yang memutuskan, termasuk saat status sudah final") diverifikasi lewat grep langsung ke seluruh `resources/views`. Ditemukan 4 titik pemakaian aktif, termasuk `admin/kehadiran-sdm/izin-cuti/show.blade.php:84` ("Langkah Tahap Saat Ini") yang tetap merender `$ar?->currentStep?->step_name` TANPA syarat status — kalau `current_step_id` di-null-kan setelah approval final (opsi alternatif yang ditolak di §7), teks ini akan berubah jadi "—" untuk SEMUA pengajuan yang sudah selesai, menghilangkan informasi berguna (step mana yang terakhir memutuskan). Ini mengonfirmasi keputusan memakai pengecekan `$request->status` eksplisit (bukan mengandalkan `current_step_id` menjadi null) adalah pilihan yang benar, bukan asumsi tanpa bukti.

## 11. Self-Review (Putaran 4) — Sanity Check Test Existing

Digrep seluruh `tests/` untuk memastikan tidak ada test yang justru mengharapkan "memproses ulang request yang sudah final harus BERHASIL" sebagai fitur yang disengaja (yang berarti perbaikan ini akan menghapus fitur, bukan memperbaiki bug). Ditemukan 8 file test yang menyentuh `ProcessApprovalAction` (langsung atau tidak langsung) — sudah dibaca semua di sesi audit sebelumnya, tidak ada satu pun yang mengasumsikan re-processability sebagai perilaku yang diinginkan. Semua skenario "panggil approve/verify dua kali" yang ada (mis. `RaporApprovalLockTest`) justru MENGHARAPKAN penolakan (`toThrow(ValidationException::class)`), konsisten dengan arah perbaikan ini.
