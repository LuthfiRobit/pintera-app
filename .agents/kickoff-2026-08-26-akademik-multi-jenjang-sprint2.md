# Kickoff Prompt — Fondasi Akademik Multi-Jenjang, Sprint 2 (Assessment Type)

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP dan sudah 2x direview mendalam. Kamu tidak perlu brainstorming, tidak perlu menulis spec baru, tidak perlu bertanya "bagaimana pendekatannya" — semua keputusan desain sudah final dan didokumentasikan.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-26-akademik-multi-jenjang-sprint2.md` — spec lengkap (§3 hasil audit consumer `NilaiSiswa` — WAJIB dibaca sebelum Task 5, karena Task 5 memperbaiki 3 titik yang ditemukan audit ini).
2. `.agents/plans/2026-08-26-akademik-multi-jenjang-sprint2.md` — plan implementasi (6 task, kode lengkap per task, TDD step-by-step).

## Konteks singkat

- Repo Laravel 12.63.0 multi-tenant (`pintera-app`). Branch kerja: `akademik-v2` (SUDAH ADA — jangan buat branch baru, jangan buat worktree).
- **Prasyarat: Sprint 1 (Subjek Penilaian Polymorphic) SUDAH SELESAI & TERVERIFIKASI** — `subjek_type`/`subjek_id` sudah live, kolom lama `mata_pelajaran_id`/`elemen_cp` sudah di-drop, full suite hijau (2146 passed, 0 failed, 4 skipped terstruktur). Baseline commit plan ini: `ee1494a1`.
- **Kenapa sub-project ini ada**: Sprint 1 menghilangkan asumsi "penilaian pasti punya mata pelajaran". Sprint 2 menghilangkan asumsi kedua yang masih tersisa: "penilaian pasti berupa angka 0-100". PAUD butuh menilai secara naratif atau lewat skala capaian (BB/MB/BSH/BSB) — bukan cuma angka.
- **Cakupan Sprint 2 SAJA** — jangan mengerjakan rapor PDF format PAUD, progress bar UI, widget "nilai terbaru" dashboard, atau Report Engine (semua itu Sprint 5, sudah eksplisit jadi Bucket B di audit §3 spec — DITANDAI, bukan diperbaiki sekarang). Kalau tergoda "sekalian aja", STOP — scope creep sudah 2x ditolak eksplisit di review.

## Urutan eksekusi

**Task 1 → 2 → 3 → 4 → 5 → 6 murni LINEAR**, sama seperti Sprint 1 — setiap task butuh hasil task sebelumnya.

**PERHATIAN KHUSUS Task 4 Step 1-2**: ada task RED WAJIB yang harus dijalankan dan diverifikasi GAGAL/BERHASIL sesuai ekspektasi SEBELUM lanjut ke Step 3 (implementasi rule final). Ini bukan urutan TDD kosmetik — plan ini SENGAJA belum 100% yakin pola wildcard Laravel (`nilai.{siswaId}.{komponenId}.field` dgn komponen_id sbg literal key, bukan `*`) akan bekerja sesuai harapan untuk struktur nested 2-level dengan level kedua diketahui di server. **Kalau Step 2 gagal dengan pola yang ditulis di Step 3, STOP dan laporkan ke user — jangan improvisasi pendekatan lain tanpa melaporkan dulu**, walau plan sudah menyebut fallback-nya (`Validator::make()` manual dgn `after()` callback).

**Kalau kamu punya akses ke skill `superpowers`:**
Gunakan `superpowers:subagent-driven-development` — fresh subagent per task, task review, ledger progress di `.superpowers/akademik-multi-jenjang-sprint2/progress.md`. Dispatch berurutan (bukan paralel).

**Kalau tidak punya skill itu:**
Eksekusi manual task-by-task (Task 1 → 6), pola sama persis dengan kickoff Sprint 1:
1. Baca task, kerjakan tiap step persis seperti ditulis.
2. WAJIB baca isi file existing dulu sebelum menimpa/mengedit.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. Satu commit per task.
6. **JANGAN jalankan full test suite sampai Task 6 Step 3.**
7. Task 6 Step 3 (full suite) idealnya minta konfirmasi user dulu kalau kamu sesi interaktif; kalau kamu subagent yang di-dispatch otomatis untuk menyelesaikan seluruh plan, boleh langsung jalankan sbg tahap akhir.

## Peringatan eksplisit dari plan — titik yang HARUS diverifikasi, bukan diasumsikan

1. **Task 1 Step 7** — cek `database/factories/NilaiSiswaFactory.php` punya field `predikat` di `definition()` atau tidak; kalau belum, tambahkan (nullable) sebelum test Task 1 Step 6 bisa jalan.
2. **Task 3 Step 2** — nama route `guru.komponen-penilaian.create` adalah ASUMSI (sama seperti dicatat di kickoff Sprint 1) — verifikasi nama sebenarnya.
3. **Task 4 Step 1-3** — lihat "PERHATIAN KHUSUS" di atas. Ini yang paling kritis di seluruh Sprint 2.
4. **Task 5 Step 6** — signature `DashboardStatsService::statistikProgressRaporKelas()` (parameter & return shape) diasumsikan dari Sprint 1 — verifikasi langsung dari kode sebelum finalisasi test.
5. **Task 6 Step 4** — grep verifikasi akhir bisa saja menemukan "tempat keempat" yang tidak masuk audit §3 spec. **Kalau ketemu, STOP dan laporkan ke user — jangan langsung diperbaiki sendiri.** Audit di spec sudah dianggap final oleh 2 putaran review; kalau ternyata ada yang terlewat, itu perlu keputusan user (masuk Sprint 2 juga, atau ditambahkan ke Bucket B/C yang sudah ada).

## Pelajaran penting dari Sprint 1 (berlaku juga di sini)

1. **Migration tidak boleh bergantung pada Eloquent model** — Sprint 2 tidak ada migration data (cuma `ADD COLUMN` dgn default), jadi risiko ini kecil, tapi tetap jaga pola `DB::table()` kalau ternyata ada kebutuhan data-migration tak terduga.
2. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
3. **Kalau kamu menemukan file yang isinya BEDA dari yang dikutip plan (baseline sudah berubah) — STOP dan laporkan ke user**, jangan menebak-nebak menyesuaikan sendiri.
4. **JANGAN jalankan lebih dari satu proses `php artisan test`/`migrate:fresh` bersamaan** — pernah menyebabkan MySQL deadlock palsu yang terlihat seperti bug kode padahal cuma race antar proses test.
5. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti)** — di Sprint 1, klaim awal "118 passed, 4 skipped" ternyata cuma scoped run yang disalahartikan sbg full suite, dan baru ketahuan setelah reviewer menjalankan ulang `php artisan test` penuh secara independen. **Jalankan `php artisan test` TANPA filter apa pun untuk klaim "full suite hijau" — bukan `--filter=Akademik` atau kombinasi manual.**
6. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
7. **Jangan menambah fitur di luar 6 task ini** — termasuk godaan untuk "sekalian" memperbaiki Bucket B (progress bar, widget nilai terbaru) karena "sudah kelihatan sekalian mudah". Itu sengaja ditunda ke Sprint 5, dan mengerjakannya sekarang justru melanggar keputusan review yang sudah 2x ditegaskan.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Laporkan ke user apa yang kamu temukan vs apa yang plan asumsikan.

## Definisi selesai

- Task 1: kolom `assessment_type` (default `numeric`) + enum `AssessmentType`/`PredikatPaud` + cast model, 2 test PASS.
- Task 2: DTO/Action/Request mendukung `assessment_type` nullable dgn default domain-layer (elemen_cp→narrative, mata_pelajaran→numeric), override dihormati, invalid ditolak di request boundary, 4 test PASS.
- Task 3: field "Tipe Penilaian" tampil di form komponen-penilaian Lembaga & Guru dgn auto-preselect (bukan dikunci), terkunci `disabled` di edit kalau komponen sudah dipakai, 1 test PASS.
- Task 4: validasi nested 2 siswa × 2 komponen tipe berbeda TERBUKTI benar (bukan diasumsikan), `assessment_type` yang dipaksa masuk payload nilai terbukti diabaikan, invariant Action terbukti lewat 3 test `->with()` dataset (numeric/narrative/predicate dgn payload kotor), UI input nilai kondisional per tipe, minimal 5 test PASS.
- Task 5: 3 consumer Bucket C (`RaporCalculationService`, `CapaianKompetensiGenerator`, `DashboardStatsService`) terbukti menghasilkan angka BENAR dgn campuran tipe komponen dalam satu kelas/semester, 3 test PASS.
- Task 6: seluruh test Akademik lama tetap hijau tanpa perubahan assertion (regresi numeric-only terjaga), `migrate:fresh --seed` sukses, **full test suite (`php artisan test` tanpa filter) 0 failed**, grep verifikasi akhir tidak menemukan consumer ke-4 yang terlewat (atau kalau ada, sudah dilaporkan ke user bukan diperbaiki sepihak), handoff log dengan bukti verifikasi yang bisa ditelusuri (commit hash per task, angka test pasti dari full suite yang BENAR-BENAR dijalankan tanpa filter, catatan penyelesaian tiap "Catatan implementer"/peringatan di atas).
