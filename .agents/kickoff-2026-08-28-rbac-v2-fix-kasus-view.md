# Kickoff Prompt — Fix RBAC v2: `kasus.view` Bocor ke Baseline Karyawan

Salin seluruh isi di bawah ini sebagai prompt awal untuk agent lain (sesi/agent yang berbeda dari yang menulis plan ini).

---

Kamu akan mengeksekusi sebuah implementation plan yang SUDAH DITULIS LENGKAP untuk **FIX RBAC** — permission `kasus.view` bocor ke baseline `pegawai_lembaga`/`pegawai_yayasan`, menyebabkan SEMUA karyawan (termasuk satpam/cleaning service/sopir yang tidak pernah ditugaskan menangani kasus siswa) otomatis punya akses ke menu Kasus Pendampingan. Kamu tidak perlu brainstorming ulang, tidak perlu menulis spec baru — semua keputusan desain sudah final dan disetujui lewat proses review berlapis.

## Yang harus kamu baca dulu

1. `.agents/specs/2026-08-28-rbac-v2-baseline-wewenang-vs-selfservice.md` — spec lengkap. §1 (terutama §1.3-1.5) WAJIB dibaca — menjelaskan kenapa fix ini TIDAK SESEDERHANA "hapus kasus.view lalu selesai" (draft awal spec ini justru DIBATALKAN karena hampir merusak fitur "Konselor Pool Karyawan" yang sudah ada).
2. `.agents/plans/2026-08-28-rbac-v2-baseline-wewenang-vs-selfservice.md` — plan implementasi (5 task, kode lengkap, TDD step-by-step, route name sudah diverifikasi).

## Konteks penting — kenapa fix ini ada

`AkunKaryawanGenerator::buat()` meng-assign role `pegawai_lembaga`/`pegawai_yayasan` ke SETIAP Karyawan baru tanpa percabangan jenis. Role itu memberi `kasus.view` ke semua karyawan lewat baseline `RoleSeeder.php:170-172` — termasuk yang tidak pernah ditugaskan konselor. `ListKasusUntukUserAction` (TIDAK diubah plan ini) untungnya sudah scope data dengan benar (karyawan hanya melihat kasus yang jadi tanggung jawabnya), jadi ini BUKAN kebocoran data lintas-tenant — murni menu/gate yang salah tempat: karyawan yang seharusnya tidak punya akses sama sekali tetap bisa buka `kasus.index` (list kosong tapi halaman tetap terbuka, membingungkan secara UX dan rapuh secara arsitektur).

## Peringatan PALING KRITIS — jangan sentuh `withoutGlobalScope(TenantScope::class)` di `viewAny()`

Task 2 menambahkan method baru `KasusPolicy::viewAny()` yang memakai `withoutGlobalScope(TenantScope::class)` dua kali. Ini **BUKAN bug, BUKAN celah keamanan** — ini invariant yang sudah dianalisis mendalam dan disetujui eksplisit oleh user (lihat spec §2.2(c) paragraf "Invariant withoutGlobalScope"). Kalau kamu (atau reviewer) melihat pola ini dan berpikir "ini kelihatan seperti tenant-isolation hole, saya perbaiki" — **JANGAN**. Baca dulu argumen lengkapnya di spec. Kalau tetap ragu, STOP dan tanya user, JANGAN diam-diam menambah filter `lembaga_id` manual atau menghapus bypass ini.

## Peringatan KEDUA — 8 endpoint yang gate-nya dihapus TETAP AMAN karena Policy lain yang sebenarnya

Task 3 menghapus `$this->authorize('kasus.view')` dari 8 endpoint. Di SETIAP endpoint itu, ada baris otorisasi LAIN persis setelahnya (`kelolaSesiTugas`, `KasusPolicy::view()`, `downloadLampiran()`, atau inline check) yang **TIDAK BOLEH ikut dihapus** — itu satu-satunya sumber otorisasi sesungguhnya, terbukti lewat trace lengkap di spec §1.4. Plan sudah menulis persis baris mana yang dihapus dan mana yang dipertahankan — SALIN PERSIS, jangan menyamaratakan "hapus semua authorize di method ini".

## Peringatan KETIGA — nama route sudah diverifikasi, JANGAN improvisasi kalau ada yang beda

Task 4 pakai nama route `kasus.tugas.preview` (bukan `batch-preview`) dan `kasus.tugas.selesai` dengan HTTP method **PATCH** (bukan POST) — sudah dicek langsung dari `routes/kasus.php` sebelum plan ditulis. Kalau di codebase kamu ternyata beda (misalnya ada perubahan route setelah plan ini ditulis), STOP dan laporkan ke user, jangan menebak nama/method baru.

## Proyek ini pakai Laravel Boost — WAJIB dipakai selama eksekusi

- Kalau ragu struktur tabel (`kasus`, `karyawan`, `jenis_karyawan_master`) — pakai `database-schema`, jangan buka migration manual.
- Kalau ragu nama route — pakai `php artisan route:list --path=kasus` (bukan `--compact`, flag itu tidak ada di project ini), bukan menebak dari nama controller.
- **JANGAN buat script verifikasi terpisah/tinker** — test yang ditulis plan sudah cukup.

## Project Rules gate — WAJIB dibaca sebelum edit apa pun

Buka `.ai/rules/index.md`, lalu baca rule file yang glob-nya mencakup path yang disentuh plan ini:

- `.ai/rules/controllers.md` (Task 3: `KasusController`, `KasusSesiController`, `KasusTugasController`, `KasusTugasBatchPreviewController`, `KasusTugasSubmissionController`, `KasusEvaluasiController`)
- `.ai/rules/domains.md` dan cek juga `grep -rin 'policy\|kasus' .ai/rules` (Task 2: `KasusPolicy` ada di `app/Domains/Kasus/Policies/`)
- `.ai/rules/seeders.md` (Task 1: `RoleSeeder`)
- `.ai/rules/tests.md`

Kalau ada isi rule yang tampak bertentangan dengan instruksi plan, STOP dan laporkan konfliknya ke user — jangan diam-diam pilih salah satu.

## Konteks singkat — PENTING, baca sebelum eksekusi

- Repo Laravel 12 multi-tenant (`pintera-app`), Pest v4, MySQL. Branch kerja: `rbac-v2` (SUDAH ADA — jangan buat branch baru/worktree).
- **Task 1**: `RoleSeeder.php` baseline `guru`, `siswa`, `orang_tua` **TIDAK DIUBAH** — hanya baris 170-172 (`pegawai_lembaga`/`pegawai_yayasan`) yang dihapus `kasus.view`-nya. Verifikasi dulu baris itu persis sesuai plan Step 1 sebelum edit — kalau sudah berbeda dari yang ditulis plan, STOP.
- **Task 2**: `KasusPolicy::isKonselor()`, `view()`, `downloadLampiran()`, `kelolaSesiTugas()` **TIDAK DIUBAH SAMA SEKALI** — hanya menambah method baru `viewAny()` + satu baris `use App\Models\Scopes\TenantScope;` baru.
- **Task 3**: `Admin\KasusController` **TIDAK DISENTUH SAMA SEKALI** di task manapun — tetap `$this->authorize('kasus.view')` biasa, ini area triase administratif terpisah.
- **Task 4**: helper `buatKaryawanPoolViaRoleSeederAsli()` memanggil `RoleSeeder::run()` SUNGGUHAN (bukan manual grant permission) — ini yang membedakannya dari helper lama `buatKaryawanKonselorAkses()` di file yang sama, yang tetap dipertahankan apa adanya untuk test lama.
- **Task 5 (terakhir) WAJIB full test suite** (`php artisan test --compact` TANPA filter) — fix ini menyentuh gate otorisasi yang bisa berdampak ke test lain di luar domain Kasus.

## Urutan eksekusi

**Task 1 → 2 → 3 → 4 → 5 murni LINEAR.** Task 3 bergantung Task 2 (`viewAny()` harus sudah ada sebelum dipanggil di `index()`). Task 4 bergantung Task 1+2+3 (test butuh baseline baru + policy baru + gate yang sudah dihapus). Task 5 adalah checkpoint penutup.

**Kalau kamu punya akses ke skill `superpowers`:**
Plan ini menengah (5 task, ~9 file tersentuh, 1 method baru + 1 gate diganti + 8 gate redundan dihapus) — `subagent-driven-development` direkomendasikan karena tiap task independen diverifikasi test sebelum lanjut.

**Kalau tidak punya skill itu sama sekali:**
Eksekusi manual:
1. Baca task, kerjakan tiap step persis seperti ditulis — kode di plan sudah lengkap.
2. **WAJIB baca file existing SEBELUM edit** (`RoleSeeder.php` baris 159-172, `KasusPolicy.php` penuh, 6 file controller yang disebut Task 3) dan bandingkan dengan kutipan "baris X saat ini" di plan — kalau baseline beda, STOP dan laporkan ke user.
3. Jalankan `php -l <file>` setelah tiap edit PHP, SEBELUM commit.
4. Jalankan test scoped SEBELUM commit — kalau gagal, JANGAN commit, diagnosis dulu.
5. 5 commit total (1 per task), pesan commit sudah ditulis di tiap Step terakhir.
6. **Full suite HANYA di Task 5** — jangan jalankan di task lain.

## Pelajaran penting dari sprint-sprint RBAC/Akademik sebelumnya (berlaku juga di sini)

1. **Jangan tandai step/task selesai kalau belum benar-benar diverifikasi lewat command nyata.**
2. **JANGAN jalankan lebih dari satu proses `php artisan test` bersamaan.**
3. **Klaim hasil test di handoff log HARUS bisa ditelusuri (command + angka pasti).**
4. **Kalau kamu memutuskan menyimpang dari pendekatan yang ditulis plan — STOP dan laporkan ke user, JANGAN diam-diam menulis solusi lain lalu bilang "sudah sesuai plan" di handoff log.**
5. **Ini fix otorisasi — jangan pernah melonggarkan atau menghapus guard `kelolaSesiTugas`/`KasusPolicy::view()`/`downloadLampiran()` yang DIPERTAHANKAN hanya demi meloloskan test yang gagal.** Kalau test gagal, cek dulu apakah fixture-nya butuh setup tambahan (role/permission/relasi data), BUKAN kode guard yang diubah.
6. **Pola `withoutGlobalScope(TenantScope::class)` di `viewAny()` sudah lolos review keamanan mendalam — jangan "diperbaiki" ulang tanpa izin user.** Ini pelajaran spesifik dari sesi brainstorming yang menulis spec ini: reviewer pertama sempat menganggapnya risiko P0, lalu ditarik setelah bukti kode lengkap ditunjukkan.

## Kalau menemukan sesuatu yang tidak sesuai plan

STOP, jangan menebak. Cek dulu via Boost (`database-schema`/`database-query`/`route:list`) kalau itu pertanyaan teknis yang bisa dijawab tool, baru laporkan ke user kalau tetap ambigu.

## Definisi selesai

- Task 1: baseline `pegawai_lembaga`/`pegawai_yayasan` tidak lagi punya `kasus.view`; baseline `guru`/`siswa`/`orang_tua` identik seperti sebelumnya; 5 test allowlist guardrail PASS.
- Task 2: `KasusPolicy::viewAny()` ada, method existing (`isKonselor`/`view`/`downloadLampiran`/`kelolaSesiTugas`) tidak berubah; 4 test unit PASS.
- Task 3: `kasus.index` pakai `authorize('viewAny', Kasus::class)`; 8 gate `kasus.view` redundan terhapus dari 6 controller; `Admin\KasusController` tidak tersentuh; test existing Kasus tetap hijau.
- Task 4: karyawan pool via `RoleSeeder` asli bisa akses 8 endpoint setelah ditugaskan konselor (bukti bug lama sudah tertutup TANPA merusak fitur pool); karyawan tanpa riwayat konselor 403 (bukti bug satpam sudah tertutup); pool lintas-lembaga, historis, dan capability-eksplisit semua PASS sesuai spec.
- Task 5: **Full test suite (`php artisan test --compact` tanpa filter) 0 failed**, angka pasti dicatat, laporan final ke user berisi angka pasti + commit hash (5 commit) + konfirmasi: satpam/karyawan tanpa assignment sekarang 403, karyawan pool konselor tetap bisa bekerja, baseline lain & Admin\KasusController tidak berubah.
