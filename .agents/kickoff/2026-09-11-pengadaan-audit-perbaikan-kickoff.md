# Kickoff: Perbaikan Audit Menyeluruh Modul Pengadaan & LPJ Sarpras

**Base commit**: `7a2572c0` (`docs(pengadaan): plan perbaikan audit menyeluruh...`)
**Branch**: `rbac-v2` (TETAP di sini, JANGAN pindah branch, JANGAN buat worktree)

---

## 1. Konteks

Audit menyeluruh (backend/workflow/scope + frontend/UI-UX/wording) modul Pengadaan & LPJ Sarpras (2 audit paralel) menemukan **2 celah keamanan Critical (IDOR)**, **3 bug UI Critical**, **1 High**, **4 Medium**, **4 Low** — total 14 temuan konkret. Ini bukan lanjutan dari 3 perbaikan `Workflow` engine sebelumnya di sesi ini — modul Pengadaan sendiri BELUM PERNAH diaudit sedalam Rapor/SDM sampai sesi ini, jadi temuannya lebih banyak dan lebih beragam (bukan cuma pola berulang dari domain lain).

**2 temuan paling serius (dampak bisnis nyata, bukan cuma bug kosmetik)**:
1. User lembaga A bisa mengisi Laporan Pertanggungjawaban (LPJ) dana untuk proposal lembaga B — integritas laporan keuangan sekolah bisa dipalsukan pihak yang tidak berkepentingan.
2. Akun admin yayasan baru yang dibuat lewat alur onboarding NORMAL (platform admin buatkan akun) berpotensi `yayasan_id` NULL, dan sistem fail-open ke yayasan PERTAMA di database — akun begini bisa menyetujui proposal, mencairkan dana, dan verifikasi LPJ milik yayasan yang sama sekali bukan tanggung jawabnya.

## 2. Dokumen WAJIB dibaca sebelum mulai, urutan ini

1. `.agents/specs/2026-09-11-pengadaan-audit-perbaikan.md` — spec lengkap, 4 putaran self-review, kode current-vs-fix KONKRET untuk semua 14 temuan (§2.1-§2.14), plus 3 item yang SENGAJA dikeluarkan dari scope (§3) dengan alasan eksplisit.
2. `.agents/plans/2026-09-11-pengadaan-audit-perbaikan.md` — 12 task TDD, kode lengkap per task (setiap task MANDIRI, tidak perlu baca task lain), 4 putaran self-review.

## 3. Keputusan Kritis — JANGAN DIUBAH tanpa lapor balik ke user

- **Task 1 WAJIB selesai & commit SEBELUM Task 7 dimulai** — Task 1 menambah 1 baris `abort_unless` di AWAL method `LpjPengadaanController::store()`, Task 7 menulis ulang SISA badan method itu. Kalau urutan terbalik, implementer Task 7 akan bingung karena kode "saat ini" yang dikutip di task brief tidak akan cocok, dan berisiko baris keamanan dari Task 1 hilang kalau method ditulis ulang tanpa hati-hati.
- **Task 3 WAJIB selesai & commit SEBELUM Task 11 dimulai** — keduanya menyentuh file yang sama (`proposal/index.blade.php`, `inbox/index.blade.php`) di baris yang berbeda (Task 3 di pembungkus tabel, Task 11 di dropdown/input di atasnya) — TIDAK overlap baris tapi urutan commit tetap harus dijaga.
- **Task 2 (fail-closed `TenantContext::activeYayasanId()`) adalah perubahan perilaku yang DISENGAJA** — bisa mengunci akses akun existing yang datanya bermasalah (`yayasan_id` null padahal seharusnya yayasan-scope). Ini BUKAN alasan untuk membatalkan/melunakkan fix. Implementer WAJIB menjalankan query dampak (`User::whereNull('yayasan_id')->whereHas('roles', fn($q) => $q->where('scope_level', 'yayasan'))->count()`) dan MELAPORKAN angkanya — bukan blocker implementasi, tapi wajib diketahui user.
- **Task 4 (tambah tone ke `<x-badge>`) HARUS murni ADDITIVE** — JANGAN ubah/hapus 6 tone existing (`brass/green/red/amber/blue/slate`), dipakai domain lain (Rapor, Workflow, SDM) di luar Pengadaan. Kalau menemukan diri berpikir "sekalian rapikan tone lain", JANGAN — itu scope creep berisiko regresi visual di luar audit ini.
- **Task 7 — validasi file LPJ jadi kondisional, TAPI submit LPJ PERTAMA KALI (belum ada `$proposal->lpj`) TETAP wajib upload semua foto.** HANYA resubmit setelah `RevisionRequired` yang jadi lebih longgar. Test `test_lpj_requires_scan_nota_and_foto_fisik` (SUDAH ADA sebelum plan ini, di `LpjValidationTest.php`) HARUS TETAP LOLOS setelah Task 7 — kalau test itu jadi gagal, itu tanda validasi kondisional yang ditulis SALAH ARAH (terlalu longgar), STOP dan laporkan, jangan modifikasi test lama supaya "lolos".
- **Task 10 JANGAN menambah method `destroy()` baru** — solusi HARUS `->except(['destroy'])` di route resource, bukan membangun kapabilitas hapus proposal yang memang tidak pernah didesain ada.
- **3 item SENGAJA TIDAK masuk scope, JANGAN dikerjakan**: validasi nominal `RecordDisbursementAction` (butuh keputusan produk terpisah), preview thumbnail LPJ sebelum submit (nice-to-have), refactor `lpj/create.blade.php` inline `<script>` ke `resources/js/*.js` terdaftar (utang teknis terpisah, jangan digabung ke Task 7 yang sudah menyentuh file sama).
- **JANGAN sentuh `app/Domains/Workflow/*`** sama sekali — semua 14 perbaikan murni domain Pengadaan + 1 file shared (`TenantContext.php`) + 1 komponen shared (`badge.blade.php`).

## 4. Fakta Operasional

- **Test file Pengadaan pakai PHPUnit class-based** (`namespace Tests\Feature\Pengadaan; class XxxTest extends TestCase`), BUKAN Pest function-style — dikonfirmasi dari 5 file existing sebelum plan ditulis. Tetap jalankan lewat `vendor/bin/pest` (Pest bisa menjalankan test PHPUnit class-based juga di proyek ini), method test pakai `public function test_xxx(): void`, bukan `it('...', function () {...})`.
- **Beberapa task (5, 6, 8, 9) mengasumsikan pola `setUp()` test file target TANPA membaca isi lengkapnya saat plan ditulis** — task brief SUDAH mengingatkan "baca dulu file sebelum menambah method, sesuaikan nama variabel". Ini BUKAN kelalaian, itu instruksi eksplisit di dalam task — implementer WAJIB benar-benar membaca file test target dulu, JANGAN asumsikan nama variabel dari task brief adalah kebenaran mutlak.
- **`CrossTenantIsolationTest.php`, `LpjValidationTest.php` sudah dibaca penuh saat plan ditulis** — Task 1, 2, 7 memakai properti/pola yang SUDAH terverifikasi akurat (`$this->proposalA2`, `$this->lembagaB1`, `$this->proposal`, `$this->item`, dst).
- **Route names yang relevan** (semua sudah terdaftar, tidak perlu route baru KECUALI tidak ada — Task 1-11 semuanya reuse route existing): `admin.pengadaan.proposal.{index,create,store,show,edit,update,submit}`, `admin.pengadaan.lpj.{create,store,staging-inventory,convert-inventory}`, `admin.pengadaan.inbox.{index,review,decision}`, `admin.pengadaan.disbursement.{index,store}`, `admin.pengadaan.audit-lpj.{index,show,verify}`.

## 5. Instruksi Stop-and-Report

- **Kalau Task 1/2/5/6/7/8's test baru ("harus GAGAL sebelum fix") ternyata sudah PASS** — tanda kode sudah berbeda dari yang didokumentasikan spec/plan. STOP, laporkan detail, jangan asumsikan "berarti sudah aman".
- **Kalau full suite (Task 12 Step 3) menunjukkan kegagalan APA PUN di luar 3 yang sudah dikonfirmasi pre-existing** (`M3DemoDataSeederTest` x2, `SubjekTenantValidationTest`) — STOP TOTAL, laporkan detail SEBELUM melanjutkan.
- **Kalau Task 9 menemukan nama kolom `status_item`/`catatan_reviewer` TIDAK ADA atau BEDA** di `PengajuanPengadaanItem` (walau sudah diverifikasi benar saat spec/plan ditulis) — STOP, laporkan, jangan lanjut dengan asumsi nama yang salah.
- **Kalau Task 2 Step 6 (query dampak) menunjukkan angka > 0** — WAJIB dilaporkan eksplisit ke user di akhir (bukan disembunyikan/diabaikan), sebagai catatan tindak lanjut manual yang perlu dilakukan platform admin.
- **Kalau `test_lpj_requires_scan_nota_and_foto_fisik` (test LAMA, sudah ada sebelum Task 7) jadi GAGAL setelah Task 7** — STOP, ini berarti validasi kondisional yang ditulis salah arah (submit pertama kali jadi ikut longgar, padahal seharusnya tidak). JANGAN modifikasi test lama supaya lolos — itu tanda bug di implementasi, bukan test yang perlu diperbarui.

## 6. Catatan Serah Terima

- Spec ditulis dan direview 4 kali dalam sesi yang sama. Plan ditulis dan direview 4 kali (sesuai permintaan user eksplisit "3-4 kali untuk spec dan plan").
- Plan ini TIDAK meminta update handoff log baru sebagai bagian dari task-nya (Task 12 eksplisit hanya verifikasi + laporan) — kalau user memang menghendaki handoff log terpisah setelah eksekusi, itu permintaan tambahan mengikuti pola 2 perbaikan sebelumnya di sesi ini.
- Setelah semua task selesai, JANGAN merge/push branch — keputusan terpisah milik user.

## 7. Mulai dari mana

Task 1 (LPJ IDOR, paling kritis & independen) dan Task 2 (fail-closed yayasan, paling kritis) bisa dikerjakan urutan bebas satu sama lain, TAPI Task 1 WAJIB selesai sebelum Task 7 dimulai. Task 4, 5, 6, 10 adalah quick-win trivial, bisa diselipkan kapan saja di antaranya. Task 7 adalah yang PALING BESAR (4 sub-bagian saling terkait) — sisihkan waktu lebih untuk task ini. Task 11 (polish) dan Task 12 (penutup) di akhir, sesuai urutan di plan.

Disarankan mulai dari **Task 1** di `.agents/plans/2026-09-11-pengadaan-audit-perbaikan.md`.
