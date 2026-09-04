# Kickoff: Perbaikan Audit Akademik Putaran 3

**Base commit**: `bf194a66` (branch `akademik-v2`)
**Spec**: `.agents/specs/2026-09-04-perbaikan-audit-akademik-putaran-3.md`
**Plan**: `.agents/plans/2026-09-04-perbaikan-audit-akademik-putaran-3.md`

## Konteks

Ini paket perbaikan ke-3 (putaran 3) dari audit berkelanjutan modul Akademik pada branch `akademik-v2`. 8 task independen (kecuali Task 4 yang WAJIB setelah Task 2 karena sama-sama edit `RppController::store()`), masing-masing menutup 1 temuan dari spec. Yang PALING MENDESAK adalah Task 1: bug berdampak finansial — siswa yang baru dinonaktifkan (lulus/pindah/keluar) bisa memicu tagihan baru yang tidak seharusnya.

## Dokumen Wajib Dibaca

1. `.agents/specs/2026-09-04-perbaikan-audit-akademik-putaran-3.md` — spec lengkap §1-§4 (keputusan final, desain tiap fix, non-goals, test plan).
2. `.agents/plans/2026-09-04-perbaikan-audit-akademik-putaran-3.md` — plan 8 task ini, kode lengkap tiap step.

## Keputusan Kritis (JANGAN diubah tanpa eskalasi ke user)

1. **Root fix billing (Task 1) HANYA di `JenisTagihanSasaranMatcher.php`.** JANGAN sentuh `TagihanBillingGenerator.php` sama sekali — file itu sedang digarap paralel di branch `keuangan-v2`. JANGAN tambah guard status siswa di `GenerateTagihanForUpdatedClass.php` juga — root cause-nya ada di matcher, bukan di listener.
2. **Task 6 (`resolveActiveLembagaId`) CUMA untuk 3 controller**: `GuruController`, `KalenderAkademikController`, `PengaturanAkademikController`. `JalurPpdbController` dan `GelombangPpdbController` (juga punya pola sama, disebut di audit) **TIDAK disentuh sama sekali** di paket ini — sudah eksplisit di-skip oleh user.
3. Method baru `resolveActiveLembagaId(User $actor): ?int` ditambahkan ke trait **YANG SUDAH ADA**: `app/Domains/Akademik/Support/ResolveLembagaScopeTrait.php` (dari paket audit sebelumnya). JANGAN buat file/trait baru. Method ini TERPISAH dari `resolveLembagaId(User, ?int)` yang sudah ada di trait itu (beda use-case: create vs read).
4. Di `GuruController`, method `resolveLembagaId(Request $request): ?int` yang sudah ada **TETAP NAMANYA**, isinya saja diganti jadi memanggil `$this->resolveActiveLembagaId($request->user())`. Jangan rename — PHP mengizinkan method class meng-override method trait bernama sama tanpa error, jadi tidak ada konflik nyata; rename hanya akan mengubah pemanggil lain yang tidak perlu.
5. Di `ProsesKenaikanKelasAction` (Task 7), urutan array update **WAJIB** `kelas_terakhir_id` sebelum `kelas_id => null` — MySQL mengevaluasi `SET` kiri-ke-kanan, kalau dibalik nilai lama akan hilang.
6. Di `UpdateKomponenPenilaianAction` (Task 3), baris `->where('id', '!=', $komponen->id)` pada query `sum('bobot')` **SUDAH BENAR** — jangan dihapus atau diubah, itu bukan bagian dari bug yang diperbaiki di sini.
7. Tidak pindah branch. Tetap di `akademik-v2`.

## Fakta Operasional

- Jalankan `ps aux | grep artisan | grep -v grep` sebelum menjalankan test suite apa pun — jangan sampai ada 2 proses `php artisan test` berjalan bersamaan.
- Test SPMB yang kadang flaky (`tests/Feature/Spmb/WizardShellComponentsTest.php`) — kalau gagal, jalankan ulang sendirian untuk konfirmasi flaky (Faker nama lembaga mengandung apostrof), BUKAN regresi dari paket ini.
- `vendor/bin/pint --dirty --format agent` wajib dijalankan setelah setiap task yang mengubah PHP, SEBELUM commit.
- Ikuti pola test existing di tiap file (baca 1-2 test lain di file yang sama sebelum menambah test baru) untuk setup actor/factory/role yang konsisten dengan konvensi project.
- Format commit: judul singkat bahasa Indonesia teknis (`fix(domain): ...`), body kalau perlu, akhiri dengan baris `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`.

## Kalau Menemukan Ambiguitas

STOP dan laporkan ke user, jangan menebak-nebak, terutama untuk:
- Nama kolom/relasi yang disebut di plan sebagai "baca dulu struktur aktual" (mis. `Guru::nama_lengkap` vs lewat relasi `person`, nama route `admin.pengaturan-akademik.*`, nama field wajib form `guru.store`/`kalender-akademik.store`) — kalau struktur aktual berbeda signifikan dari asumsi plan, laporkan sebelum melanjutkan, jangan dipaksakan.
- Kalau ada test existing yang GAGAL akibat Task 1 (matcher menyempit ke siswa aktif saja) — itu artinya test lama mengasumsikan bug lama sebagai benar. Perbaiki assertion test itu sesuai perilaku baru, JANGAN melemahkan fix untuk membuat test lama lolos begitu saja tanpa memastikan itu memang assumsi yang salah.
- Kalau menemukan pola/method lain yang mirip dengan yang sedang diperbaiki tapi TIDAK disebut di plan (misal ada titik pemakaian `session('active_lembaga_id')` ke-4 di salah satu dari 3 controller yang belum tercatat) — laporkan, jangan otomatis diperbaiki juga tanpa konfirmasi scope.

## Catatan Serah-Terima (di luar scope paket ini, JANGAN dikerjakan)

- **#7-PPDB**: `JalurPpdbController` dan `GelombangPpdbController` punya pola session-staleness yang sama seperti Task 6, tapi eksplisit di-skip oleh user untuk putaran ini — dicatat sebagai utang teknis untuk paket berikutnya.
- **#9 Activitylog**: `ProsesKenaikanKelasAction` pakai mass-update yang tidak memicu Activitylog — ditunda, cuma relevan kalau ada kebutuhan tracking histori kenaikan kelas granular di masa depan (mis. kalau Opsi B tracking "siswa tinggal kelas" dari paket sebelumnya diperluas).

## Mulai dari Mana

Gunakan `superpowers:subagent-driven-development` untuk eksekusi. Urutan disarankan: Task 1 → Task 2 → Task 4 (setelah Task 2, WAJIB berurutan) → Task 3 → Task 5 → Task 6 → Task 7 → Task 8 (full suite final). Setiap task: implementer subagent → task reviewer subagent → fix loop kalau ada temuan Critical/Important → lanjut task berikutnya. Setelah Task 8 (full suite hijau), lakukan final whole-branch review sebelum melapor selesai ke user.
