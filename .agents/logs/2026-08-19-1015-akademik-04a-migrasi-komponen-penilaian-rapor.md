# 📋 Handoff Log: Sub-Task 04a — Migrasi Komponen Penilaian, Asesmen, Nilai Siswa & Rapor ke Domain Akademik

- **Spec:** [`.agents/specs/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md)
- **Plan:** [`.agents/plans/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md)
- **Mode eksekusi:** Subagent-Driven Development, inline di sesi yang sama (bukan hand-off ke agent lain), langsung di branch `akademik-v2`, tanpa worktree.
- **Base commit sebelum Task 1:** `b3bb583`
- **Commit akhir:** `c418ce0`
- **Status:** 🟢 SELESAI — full suite 1781 passed / 0 failed (5510 assertions), diverifikasi setelah user menyetujui.

## Ringkasan

Migrasi struktural murni `KomponenPenilaian`, `Asesmen`, `NilaiSiswa`, `JenisAsesmen` dari `app/Models`/`app/Enums` ke `app/Domains/Akademik/`, menghilangkan duplikasi logic bisnis antara controller Admin dan Guru dengan Action bersama, dan ekstraksi `RaporCalculationService` — semuanya tanpa mengubah perilaku HTTP yang teramati. Ini fondasi untuk Sub-Task 04b (Adaptive E-Rapor Engine) yang belum dibrainstorm.

## Per-Task Detail

### Task 1: Pindahkan Model & Enum ke Domain Akademik
- Commit: `b3bb583..4f1b373`
- Model haiku (implementer, mekanis), Sonnet (reviewer).
- Memindahkan `KomponenPenilaian`, `Asesmen`, `NilaiSiswa`, `JenisAsesmen` ke `app/Domains/Akademik/`, update `use` statement di 21 file konsumen (4 controller, 3 factory, 3 seeder, 11 test).
- Review: **Approved**, 0 issue. 79/79 test scoped lulus (203 assertions).

### Task 2: Action & FormRequest Komponen Penilaian
- Commit: `4f1b373..37c9982`, fix `37c9982..d9b6e48`
- Model Sonnet (implementer & reviewer).
- Menambahkan `KomponenPenilaianData`/`UpdateKomponenPenilaianData` DTO, `Create/Update/DeleteKomponenPenilaianAction`, 4 FormRequest, merefaktor `Admin\KomponenPenilaianController` dan `Guru\KomponenPenilaianController` jadi thin — menghapus duplikasi logic validasi bobot ≤100% dan guard "sudah dipakai" yang sebelumnya ada persis sama di kedua controller.
- **Temuan review pertama**: implementer (mengikuti contoh kode di plan secara verbatim) kehilangan guard 404 pre-existing di `Admin::update()` (`$mataPelajaranSaatIni` check) — ini **bug penulisan plan saya sendiri**, bukan kesalahan implementer. Diperbaiki langsung (tidak perlu ditanyakan ke user — bug jelas, bukan keputusan desain), re-review konfirmasi guard sudah kembali di posisi asli, 31/31 test lulus.
- Review akhir: **Approved**.

### Task 3: Action & FormRequest Asesmen & Nilai Siswa
- Commit: `d9b6e48..e602285`
- Model Sonnet (implementer & reviewer).
- Menambahkan `AsesmenData`/`NilaiSiswaBatchData` DTO, `CreateAsesmenAction`/`SimpanNilaiSiswaAction` (keduanya dibungkus `DB::transaction()`, diverifikasi), 2 FormRequest, merefaktor `Guru\AsesmenController::store()`/`updateNilai()` jadi thin.
- Semua guard di controller asli dipertahankan (`abort_unless($mengajarKombinasiIni,...)` tetap di `store()`, `authorizeMilikGuru()` tetap eksplisit di `updateNilai()`).
- Review: **Approved**, 0 Critical/Important. 8/8 test lulus, 0 perubahan assertion.
- **1 Minor dicatat untuk final review**: urutan validasi vs guard ownership berubah (FormRequest lifecycle menjalankan validasi sebelum body controller) — skenario tidak-authorized-dan-payload-invalid sekarang 422 bukan 403, tidak ada test yang menguji kombinasi ini. Bukan pelanggaran spec (pola FormRequest memang diminta plan), tapi dicatat sebagai gap coverage.

### Task 4: Ekstrak RaporCalculationService
- Commit: `e602285..5fd74f8`
- Model Sonnet (implementer & reviewer).
- Mengekstrak `RaporController::hitungRekap()` (private method) jadi `RaporCalculationService::hitungRekapKelas(Kelas $kelas, Semester $semester): array` — logic identik byte-for-byte, hanya dipindah. TDD: test ditulis dulu (3 skenario: weighted average, null score, kelas tanpa asesmen), RED lalu GREEN, baru controller direfaktor.
- Review: **Approved**, 0 Critical/Important. 18/18 test lulus (3 unit baru + 15 feature existing, 0 perubahan assertion).
- **1 Minor**: bukti RED-before-GREEN hanya klaim di report, tidak bisa dibuktikan independen dari diff satu-commit (bukan defect kode).

### Task 5: Audit Tenant-Scoping Level Controller
- Commit: `5fd74f8..60db352`, fix `60db352..c418ce0`
- Model Sonnet (implementer & reviewer).
- Audit menyeluruh terhadap 4 controller + 5 Action yang dibuat Task 1-4. **Tidak ditemukan celah cross-tenant nyata** — dikonfirmasi independen oleh reviewer lewat trace kode langsung (`TenantScope.php`, `RaporController.php`, `RaporCalculationService.php`), bukan sekadar percaya laporan implementer.
- **Temuan review pertama**: reasoning implementer soal *kenapa* aman itu salah (implementer bilang aman karena `Asesmen`'s double-where, padahal proteksi sebenarnya di level HTTP adalah gate list-membership `tahun_ajaran_id` di `RaporController::index()` yang men-drop semester mismatch jadi null SEBELUM `hitungRekapKelas()` sempat dipanggil) — dan test yang ditambahkan jadi vacuous untuk klaim yang dimaksud. Diperbaiki dengan menambah unit test langsung terhadap `RaporCalculationService::hitungRekapKelas()` yang benar-benar memakai pasangan kelas/semester lintas-lembaga (menguji invariant `Asesmen` double-where yang sesungguhnya), plus koreksi teks reasoning di report.
- Review akhir setelah fix: **Approved**. 70/70 regression bundle Task 2-5 + 4/4 `RaporCalculationServiceTest` lulus.
- **TEMUAN PENTING UNTUK FINAL REVIEW (di luar scope 04a, TIDAK diperbaiki)**: `app/Models/Scopes/TenantScope.php` **tidak punya filter level-yayasan sama sekali**. Aktor berscope yayasan dengan `session('active_lembaga_id')` kosong mendapat query **tanpa filter apa pun** — bocor lintas SEMUA lembaga di sistem, bukan cuma lintas lembaga dalam satu yayasan. Contoh nyata: `RaporController::index()`'s `TahunAjaran::where('status_aktif', true)`. Dikonfirmasi pola arsitektur yang dipakai bersama oleh ~15 controller lain di codebase — bukan bug baru dari sub-task ini, tapi gap pre-existing yang lebih luas dari scope 04a manapun.

## Verifikasi Akhir (Task 6)

Setelah user menyetujui, dijalankan `php artisan test` full suite:

```
Tests:    1781 passed (5510 assertions)
Duration: 441.02s
```

0 failed. Tidak ada regresi.

## Item Terbuka untuk Sesi Berikutnya

1. **Sub-Task 04b (Adaptive E-Rapor Engine)** belum dibrainstorm sama sekali — narasi TP otomatis, `PengajuanRapor`/`CatatanWaliKelas`, approval workflow, 4 template PDF berjenjang.
2. **Gap arsitektur `TenantScope.php` tanpa filter yayasan** (lihat Task 5 di atas) — perlu diputuskan apakah jadi sub-task perbaikan tersendiri atau ditangani sebagai bagian dari pekerjaan lain. Ini BUKAN diperkenalkan oleh 04a, tapi ditemukan dan didokumentasikan olehnya.
3. Branch `akademik-v2` masih belum pernah di-push ke remote (belum diminta).
