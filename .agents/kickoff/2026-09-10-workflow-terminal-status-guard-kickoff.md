# Kickoff: Guard Status Terminal di ProcessApprovalAction (Lintas Rapor/Pengadaan/SDM)

**Base commit**: `2ad4ba50` (`docs(workflow): spec guard status terminal di ProcessApprovalAction`)
**Branch**: `rbac-v2` (TETAP di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Saat audit alur Izin/Cuti SDM (setelah audit Rapor tuntas), ditemukan `ProcessApprovalAction::execute()` — engine approval bersama dipakai 3 domain (Rapor, Pengadaan, SDM) — **tidak pernah menolak memproses ulang `ApprovalRequest` yang statusnya sudah final** (Approved/Rejected/Cancelled/RevisionRequired).

**Dibuktikan lewat test diagnostik sementara** (sudah dihapus, bukan bagian permanen codebase — dibuat khusus untuk membuktikan bug sebelum menulis spec, konsisten dengan pola "verifikasi empiris" yang dipakai berulang kali sesi ini): memanggil approve KEDUA KALI pada pengajuan yang sudah final menghasilkan `ApprovalLog` ganda di SEMUA 3 domain, PLUS `AttendanceRecord` ganda khusus di SDM (pegawai tercatat izin/sakit/cuti 2x di tanggal yang sama). Rapor TIDAK terdampak karena py guard status terpisah di level controller (`PersetujuanController::decision()`).

**Pemicu nyata**: double-klik tombol Setujui/Tolak, tombol back browser lalu submit ulang, atau race condition asli (2 admin approve nyaris bersamaan). Bukan skenario eksotis.

Ini adalah perbaikan Workflow engine bersama yang KETIGA di sesi ini (setelah: 1. perbaikan validasi session lembaga aktif, 2. perbaikan fail-closed untuk target lembaga yang tidak bisa ditentukan). Pola yang sama terus berulang: satu bug di engine bersama, 3 domain terdampak, 1 perbaikan di root menutup semuanya.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-10-workflow-terminal-status-guard.md` — spec lengkap, 4 putaran self-review (termasuk 3 putaran verifikasi EMPIRIS terhadap enum `ApprovalAction`, ketergantungan UI pada `current_step_id`, dan sanity check test existing — BUKAN cuma baca ulang teks).
2. `.agents/plans/2026-09-10-workflow-terminal-status-guard.md` — 5 task TDD, kode lengkap tiap step, sudah self-review (menemukan & memperbaiki 1 bug nyata di draft plan-nya sendiri sebelum kickoff ini ditulis — lihat §3 poin pertama).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Perbaikan HANYA di 1 method** (`ProcessApprovalAction::execute()`) — JANGAN tambah guard controller per-domain di Rapor/Pengadaan/SDM. Spec §7 eksplisit menolak pendekatan itu (root cause sudah cukup untuk ketiganya, konsisten dengan filosofi "perbaiki akar sekali" yang dipakai 2x sebelumnya di sesi ini).
- **JANGAN ubah desain `current_step_id` supaya di-null-kan setelah approval final** — spec §7/§10 SUDAH MEMBUKTIKAN (via grep langsung ke `resources/views`) bahwa `admin/kehadiran-sdm/izin-cuti/show.blade.php:84` merender `$ar?->currentStep?->step_name` TANPA syarat status untuk menampilkan "Langkah Tahap Saat Ini" — kalau `current_step_id` di-null-kan, teks ini rusak jadi "—" untuk SEMUA pengajuan yang sudah selesai. Kalau menemukan diri berpikir "lebih simpel null-kan saja", STOP — itu sudah dipertimbangkan dan ditolak dengan bukti konkret.
- **Task 1's test helper WAJIB pakai `PengajuanRapor` sebagai `approvable`, BUKAN `Lembaga`** — plan-nya SUDAH diperbaiki dari draft awal yang salah (`Lembaga` model tidak punya kolom `lembaga_id`, kalau dipakai sebagai approvable, `$request->approvable?->lembaga_id` selalu `null` lewat magic getter Eloquent, memicu fail-closed dari perbaikan sesi sebelumnya dan membuat SEMUA panggilan gagal di `canUserApprove()` -- bukan cuma yang kedua kali yang mau diuji). Plan final di file sudah benar, JANGAN diubah kembali ke pola `Lembaga`.
- **Status yang DIBLOKIR dari diproses ulang**: HANYA `Approved`, `Rejected`, `Cancelled`, `RevisionRequired` (4 status final). Status yang TETAP BOLEH diproses: `Pending`, `InReview` (2 status aktif). JANGAN sampai arahnya terbalik.
- **`ApprovalAction::Cancel` TIDAK PERNAH lewat `ProcessApprovalAction`** (jalur terpisah di `BatalkanPengajuanIzinCutiAction`, dikonfirmasi tidak ada `if ($action === ApprovalAction::Cancel)` di method yang diubah) — tidak perlu test khusus untuk Cancel lewat method ini.
- **Task 2 (Rapor), Task 3 (Pengadaan), Task 4 (SDM) SALING INDEPENDEN** — bisa dikerjakan urutan bebas SETELAH Task 1 selesai (Task 1 adalah prasyarat keras untuk semuanya, karena tanpa fix di Task 1, test baru di Task 2-4 akan gagal). Task 5 (full suite) WAJIB terakhir.

## 4. Fakta Operasional

- **Model kunci untuk AttendanceRecord (SDM)**: nama CLASS model adalah `App\Domains\Sdm\Models\AttendanceRecord` — BUKAN `AttendanceEvent`. `attendanceEvents()` HANYA nama method relasi (`$pegawai->attendanceEvents()`), tapi model yang di-query harus `AttendanceRecord::where(...)`. Ini sudah dikonfirmasi lewat pembacaan langsung `tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php` yang sudah ada.
- **Helper test existing yang WAJIB dipakai ulang, JANGAN bikin baru yang fungsinya sama**:
  - `siapkanAktorPersetujuan()` di `RaporPersetujuanControllerTest.php` (Task 2)
  - `seedIzinCutiWorkflowForTest()` di `ProsesApprovalIzinCutiActionTest.php` (Task 4)
  - Pola construksi Yayasan/Lembaga/Gedung/KategoriAset/Ruangan di `PengajuanApprovalActionTest.php` (Task 3, class `test_submit_partial_approval_and_disbursement_lifecycle` yang sudah ada di file yang sama jadi rujukan pola)
- **Import yang perlu ditambah manual**: Task 3 perlu menambah `use App\Domains\Workflow\Models\ApprovalLog;` dan `use Illuminate\Validation\ValidationException;` ke `PengajuanApprovalActionTest.php` (dikonfirmasi belum ada di file itu). Task 2 dan Task 4 TIDAK perlu import baru (semua sudah ada di file masing-masing).
- **MySQL deadlock risk**: cek proses PHP lain sebelum full suite di Task 5 kalau ada kekhawatiran proses lain masih jalan.

## 5. Instruksi Stop-and-Report

- **Kalau Task 1's test baru ("processes a Pending request normally") GAGAL bahkan SEBELUM fix diterapkan** — itu tanda ada masalah lain di setup test (kemungkinan besar terkait resolusi lembaga/role), bukan soal fix status terminal. STOP, jangan lanjut ke fix sebelum baseline test ini hijau (ini test regresi baseline, harus lolos DUA-duanya sebelum DAN sesudah fix diterapkan — beda dari 4 test "rejects reprocessing..." yang MEMANG harus merah sebelum fix).
- **Kalau full suite (Task 5) menunjukkan kegagalan APA PUN di luar 3 yang sudah dikonfirmasi pre-existing** (`M3DemoDataSeederTest` x2, `SubjekTenantValidationTest`) — STOP TOTAL, laporkan detail ke user SEBELUM melanjutkan. Ini menyentuh engine approval bersama dipakai 3 domain — riwayat proyek ini sudah 3x menemukan bug lintas-tenant/lintas-domain di area ini sesi yang sama, jangan anggap enteng kegagalan tak terduga sekecil apa pun.
- **Kalau `ProposalEditAndResubmitTest.php` (Pengadaan) atau `SubmitPengajuanRaporActionTest.php` (Rapor) GAGAL setelah fix Task 1** — ini SANGAT PENTING, itu berarti asumsi utama spec (resubmission tidak lewat `ProcessApprovalAction`) TERNYATA SALAH untuk kasus tertentu yang belum ditemukan. STOP, laporkan detail persis skenario yang gagal — JANGAN modifikasi fix untuk "meloloskan" test resubmission tanpa berkonsultasi dulu, karena itu bisa berarti perbaikan fail-closed-nya perlu dipertimbangkan ulang bentuknya.
- **Kalau menemukan pemanggil `ProcessApprovalAction::execute()` KELIMA** (di luar 4 yang sudah diidentifikasi: Verify/ApprovePengajuanRaporAction, ProcessProposalApprovalAction, ProsesApprovalIzinCutiAction) — STOP, laporkan ke user. Spec sudah grep menyeluruh dan yakin cuma 4, tapi kalau kondisi kode berubah sejak spec ditulis, itu perlu dikonfirmasi ulang bukan diasumsikan aman.

## 6. Catatan Serah Terima

- Spec ditulis dan direview 4 kali dalam sesi yang sama (1 putaran standar + 3 putaran verifikasi empiris) — versi final sudah akurat terhadap kode aktual per saat spec ditulis, TAPI TETAP ikuti instruksi Stop-and-Report kalau menemukan sesuatu yang tidak cocok.
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (Task 5 eksplisit hanya verifikasi + minta laporan full-suite ke user) — kalau user memang menghendaki handoff log terpisah di sesi ini, itu permintaan tambahan setelah plan selesai.
- Setelah semua task selesai, JANGAN merge/push branch — keputusan terpisah milik user.

## 7. Mulai dari mana

Mulai dari **Task 1** (perbaikan inti + test generik level engine) di `.agents/plans/2026-09-10-workflow-terminal-status-guard.md` — WAJIB selesai dan lolos test dulu sebelum Task 2/3/4 (yang bisa urutan bebas satu sama lain), diakhiri Task 5 (full suite).
