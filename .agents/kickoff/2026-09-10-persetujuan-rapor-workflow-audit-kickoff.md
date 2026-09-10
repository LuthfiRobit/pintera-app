# Kickoff: Audit & Perbaikan Persetujuan Rapor + Workflow Engine

**Base commit**: `9f47c7df` (`fix(rapor): pemisah kode mapel di legenda PDF pakai karakter bullet langsung, bukan entity HTML`)
**Branch**: `rbac-v2` (TETAP di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit halaman Persetujuan Rapor (`admin.rapor.persetujuan.*`, `PersetujuanController`) dimulai dari controller+view (7 temuan wording/scope/UX), lalu DIPERDALAM ke backend Actions atas permintaan eksplisit user ("audit semua dong dari backend"). Penelusuran itu menemukan **bug fungsional serius di shared Workflow engine** (`ApproverResolverService`), BUKAN cuma di Rapor — engine ini dipakai bersama oleh 3 domain (Akademik/Rapor, Pengadaan, SDM).

**Bug intinya**: `ApproverResolverService::checkRoleApprover()` mempercayai `session('active_lembaga_id')` mentah-mentah untuk aktor berscope yayasan, tanpa validasi kepemilikan. Untuk aktor yayasan dalam **mode agregat** (belum pilih lembaga aktif), `$effectiveLembagaId` SELALU `null` → approval SELALU gagal, bahkan untuk pengajuan yang sah miliknya. Ini BUKAN skenario pinggiran — sistem RBAC v2 proyek ini memang mendesain kombinasi role realistis seperti `pegawai_yayasan` (scope_level yayasan) + `kepala_sekolah`/`wakasek_kurikulum`/`admin_sdm` (scope_level lembaga) pada 1 user yang sama (lihat komentar `User::functionalRoles()`). `yayasan_super_admin` TIDAK kena (ada bypass eksplisit terpisah).

**Blast radius**: 5 dari 6 approval step berbasis role+scope-lembaga di `WorkflowDefinitionSeeder` (Rapor: Waka Kurikulum verify + Kepsek approve; Pengadaan: Kepsek verify; SDM: Kepsek verify + Admin SDM approve). Rapor SENDIRI punya duplikat bug yang sama persis di 2 Action-nya (`VerifyPengajuanRaporAction`, `ApprovePengajuanRaporAction`) — kode redundan yang harus dibersihkan SETELAH root cause diperbaiki.

**Keputusan scope (dikonfirmasi user)**: perbaiki root cause (Item W) + bersihkan duplikat Rapor (Item X) + 6 perbaikan page-level Rapor (Item A-F). **JANGAN sentuh kode Pengadaan/SDM** — sudah dikonfirmasi via grep tertarget bahwa keduanya TIDAK punya duplikat bug lokal seperti Rapor, murni mengandalkan `ApproverResolverService`, jadi otomatis ikut benar begitu Item W selesai. Audit penuh UI/wording Pengadaan & SDM DITUNDA sebagai audit terpisah di kemudian hari, TIDAK termasuk scope sesi ini.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-10-persetujuan-rapor-workflow-audit.md` — spec lengkap 8 item (W, X, A-F), kode current-vs-fix konkret untuk tiap item, 4 putaran self-review sudah dilakukan di dalam spec itu sendiri (baca terutama §3 "item TIDAK masuk scope" dan §4 "Urutan Pengerjaan").
2. `.agents/plans/2026-09-10-persetujuan-rapor-workflow-audit.md` — 9 task TDD, kode lengkap tiap step, sudah self-review (placeholder scan bersih, Task 3 sempat direvisi karena draft awal punya narasi "koreksi" yang membingungkan — versi final sudah bersih, ikuti apa adanya).

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Urutan Task 1 (Item W) → Task 2 (Item X) WAJIB berurutan, TIDAK BOLEH dibalik atau diparalelkan.** Task 2 menghapus lapis proteksi yang — walau buggy — saat ini satu-satunya guard yang lolos test existing di Rapor. Menghapusnya sebelum Task 1 selesai+teruji membuat Rapor kehilangan proteksi lembaga sama sekali untuk sementara.
- **`ApproverResolverService` (Task 1) TIDAK BOLEH import/reuse `App\Domains\Akademik\Support\ResolveLembagaScopeTrait`.** Workflow adalah engine generik dipakai 3 domain (Akademik/Pengadaan/SDM) dan tidak boleh bergantung pada namespace domain manapun — ini pelanggaran layering arsitektur, bukan sekadar gaya penulisan. Plan sudah memberi kode lengkap method privat baru `resolveEffectiveLembagaId()` yang MANDIRI di dalam `ApproverResolverService` sendiri — pakai persis itu, jangan didesain ulang.
- **Perbaikan Item W TIDAK melonggarkan blokir mode-agregat itu sendiri.** Aktor yayasan MASIH WAJIB pilih lembaga aktif sebelum approve step `scope_level: 'lembaga'` — ini benar secara bisnis (approval per-lembaga butuh konteks lembaga eksplisit). Yang diperbaiki HANYA validasi kepemilikan yayasan atas `session('active_lembaga_id')` (session stale/lintas-yayasan kini benar-benar divalidasi jadi `null`, bukan dipercaya mentah), BUKAN requirement "harus pilih lembaga aktif"-nya.
- **Task 3 (Item A) SENGAJA tidak punya test baru yang red/green** — plan sudah menjelaskan alasannya (kode lama sudah "kebetulan benar" untuk kasus stale-session karena `->when()` skip filter saat `null`; ini refactor dead-code, bukan bug-fix dengan hasil salah yang bisa dites merah). JANGAN memaksa menulis test gagal buatan untuk task ini — cukup jalankan regression test existing.
- **Task 4 (Item B) badge markup dan class CSS HARUS disalin PERSIS** dari `resources/views/admin/karyawan/index.blade.php` (baris 19-24) — sama persis string class-nya, sama ikon (`apartment`), sama struktur kondisional. JANGAN improvisasi styling baru.
- **Task 7 (Item E) WAJIB pakai `Rule::requiredIf(fn () => $this->input('action') === 'REJECT')`** — bukan validasi manual/custom rule lain.
- **Semua file PHP yang diubah WAJIB lolos `vendor/bin/pint --dirty --format agent`** sebelum task dianggap selesai (sudah jadi step eksplisit di tiap task plan yang menyentuh PHP).

## 4. Fakta Operasional

- **File test yang disentuh berulang lintas-task**: `tests/Feature/Rapor/RaporPersetujuanControllerTest.php` disentuh di Task 4, 5, 7, 8. `tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php` disentuh di Task 3, 6. `tests/Feature/Workflow/ApproverResolverServiceTest.php` (file BARU) dibuat di Task 1. Kerjakan task BERURUTAN (1→9) untuk hindari conflict antar-edit di file test yang sama.
- **Helper test existing yang WAJIB dipakai ulang, JANGAN bikin baru yang fungsinya sama**: `siapkanAktorPersetujuan()` (di `RaporPersetujuanControllerTest.php`) untuk setup aktor lembaga-scope Waka/Kepsek + pengajuan siap-approve. Plan menambah 2 helper BARU di Task 1 (`buatStepLembagaKepsekDanRequest()`, `buatAktorPegawaiYayasanKepsek()`) — nama-nama ini SUDAH diverifikasi tidak bentrok dengan fungsi manapun yang ada di codebase, pakai persis nama itu.
- **Pola simulasi session di test**: `session(['active_lembaga_id' => $id]);` dipanggil LANGSUNG (tanpa lewat HTTP request) — pola ini SUDAH dipakai luas di test lain proyek ini (`JadwalPelajaranTenantGuardTest.php` dkk), TERBUKTI berfungsi tanpa perlu `$this->withSession()`.
- **Field kunci untuk skenario "kombinasi role yayasan+lembaga"**: `User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id])` lalu `$user->assignRole([$roleFungsionalLembaga, $rolePegawaiYayasan])` — `pegawai_yayasan` adalah role scope-carrier (`scope_level: 'yayasan'`) yang SUDAH ada di `RoleSeeder`, jangan buat role baru.
- **MySQL deadlock risk**: cek proses PHP lain sebelum full suite di Task 9 kalau ada kekhawatiran proses lain masih jalan.

## 5. Instruksi Stop-and-Report

- **Kalau nomor baris di plan/spec berbeda dari kode aktual di lapangan** — STOP sejenak, baca versi terkini, sesuaikan berdasarkan ISI kode (cari via nama method/nama test, BUKAN nomor baris semata), catat perbedaannya di laporan task.
- **Kalau Task 1 (fix `ApproverResolverService`) atau Task 2 (cleanup Rapor) menunjukkan test IDOR/tenant-scope existing GAGAL setelah perubahan** — STOP TOTAL, laporkan detail ke user SEBELUM melanjutkan ke task berikutnya. Ini topik otorisasi lintas-tenant (riwayat proyek: bug cross-tenant/IDOR sudah terjadi berulang kali) — perbaikan HARUS memperketat/mempertahankan proteksi, TIDAK BOLEH melonggarkannya, bahkan secara tidak sengaja.
- **Kalau saat mengerjakan Task 1 menemukan `ApproverType::DirectRelation` atau `ApproverType::SpecificUser` (di `checkDirectRelationApprover()`/`canUserApprove()`) TERNYATA punya bug serupa** (raw session, tanpa validasi) — STOP, laporkan ke user dulu. Spec ini SENGAJA hanya membahas `checkRoleApprover()` (yang terbukti buggy dan dipakai di 5 step nyata) — perluasan scope ke method lain di file yang sama HARUS dikonfirmasi dulu, bukan diputuskan sendiri.
- **Kalau Task 2 (hapus duplikat Rapor) ternyata menemukan behavior test existing berubah** (bukan cuma pass/fail, tapi pesan error yang berubah, dsb) — STOP, laporkan detail persisnya (pesan lama vs baru) sebelum melanjutkan, karena pesan error `ApproverResolverService`/`ProcessApprovalAction` ("Anda tidak memiliki hak akses untuk memproses langkah persetujuan ini.") BEDA teksnya dari pesan lama Rapor yang dihapus ("Anda tidak berwenang memverifikasi/menyetujui pengajuan rapor lembaga lain.") — kalau ada test yang menegaskan teks pesan LAMA secara spesifik, itu perlu dilaporkan, bukan diam-diam disesuaikan.

## 6. Catatan Serah Terima

- Spec ditulis dan direview 4 kali dalam sesi yang sama (termasuk 1 putaran khusus soal keputusan arsitektur layering Workflow-vs-Akademik) — versi final sudah akurat terhadap kode aktual per saat spec ditulis, TAPI TETAP ikuti instruksi Stop-and-Report kalau menemukan sesuatu yang tidak cocok.
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (Task 9 eksplisit hanya regression + Pint + minta user jalankan full suite) — kalau user memang menghendaki handoff log terpisah di sesi ini, itu permintaan tambahan terpisah setelah plan selesai.
- Audit Pengadaan dan SDM (UI/wording, bukan bug ini) SENGAJA TIDAK masuk plan ini — sudah keputusan eksplisit user untuk ditunda sebagai audit terpisah nanti. JANGAN "sekalian" mengauditnya saat mengerjakan task manapun di sini.
- Setelah semua task selesai, JANGAN merge branch ke branch manapun — keputusan terpisah milik user.

## 7. Mulai dari mana

Mulai dari **Task 1** (fix root cause `ApproverResolverService` — paling kritis, membuka jalan untuk task lainnya) di `.agents/plans/2026-09-10-persetujuan-rapor-workflow-audit.md`, kerjakan BERURUTAN Task 1 → 9 (Task 2 punya dependensi keras ke Task 1; task lain menyentuh file test yang sama berulang kali — urutan berurutan WAJIB untuk hindari conflict, bukan sekadar disarankan).
