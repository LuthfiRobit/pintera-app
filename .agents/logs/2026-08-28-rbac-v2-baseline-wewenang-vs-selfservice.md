# Handoff Log — RBAC v2 — Fix `kasus.view` Baseline vs Assignment

- **Spec**: [.agents/specs/2026-08-28-rbac-v2-baseline-wewenang-vs-selfservice.md](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-28-rbac-v2-baseline-wewenang-vs-selfservice.md)
- **Plan**: [.agents/plans/2026-08-28-rbac-v2-baseline-wewenang-vs-selfservice.md](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-28-rbac-v2-baseline-wewenang-vs-selfservice.md)

---

## Apa Yang Dikerjakan

1. **Task 1: Hapus `kasus.view` dari Baseline Karyawan** (`86fd42ef`)
   - Mengeluarkan permission `kasus.view` dari role `pegawai_lembaga` dan `pegawai_yayasan` pada [RoleSeeder.php](file:///d:/laragon/www/pintera-app/database/seeders/RoleSeeder.php).
   - Menambahkan 5 test allowlist baseline di [RoleSeederTest.php](file:///d:/laragon/www/pintera-app/tests/Unit/RoleSeederTest.php) untuk menjamin set permission baseline role `guru`, `pegawai_lembaga`, `pegawai_yayasan`, `siswa`, dan `orang_tua` tetap aman dan tidak bergeser secara tidak sengaja di masa depan.

2. **Task 2: Implementasi `KasusPolicy::viewAny()`** (`56068dab`)
   - Menambahkan method baru `viewAny(User $user): bool` di [KasusPolicy.php](file:///d:/laragon/www/pintera-app/app/Domains/Kasus/Policies/KasusPolicy.php) yang mengizinkan akses halaman index jika user memiliki capability `kasus.view` (via role lain seperti Guru BK) **ATAU** minimal terikat pada satu kasus aktif/historis sebagai konselor (`konselor_karyawan_id` menunjuk ke profil karyawan user).
   - Menambahkan 4 unit tests untuk mencakup seluruh skenario (capability `kasus.view`, karyawan pool konselor terikat kasus, karyawan biasa tanpa riwayat/satpam, dan status historis selesai) di [KasusPolicyTest.php](file:///d:/laragon/www/pintera-app/tests/Unit/Domains/Kasus/Policies/KasusPolicyTest.php).

3. **Task 3: Refaktor Gate Controller** (`afecb195`)
   - Mengubah otorisasi di `index()` pada [KasusController.php](file:///d:/laragon/www/pintera-app/app/Http/Controllers/KasusController.php) menggunakan `viewAny` (`$this->authorize('viewAny', Kasus::class)`).
   - Menghapus 8 baris gate `$this->authorize('kasus.view');` yang redundan di 6 controller:
     - [KasusController.php](file:///d:/laragon/www/pintera-app/app/Http/Controllers/KasusController.php) (`show`)
     - [KasusSesiController.php](file:///d:/laragon/www/pintera-app/app/Http/Controllers/KasusSesiController.php) (`store`, `updateStatus`)
     - [KasusTugasController.php](file:///d:/laragon/www/pintera-app/app/Http/Controllers/KasusTugasController.php) (`store`, `markSelesai`)
     - [KasusTugasBatchPreviewController.php](file:///d:/laragon/www/pintera-app/app/Http/Controllers/KasusTugasBatchPreviewController.php) (`preview`)
     - [KasusTugasSubmissionController.php](file:///d:/laragon/www/pintera-app/app/Http/Controllers/KasusTugasSubmissionController.php) (`store`, `review`, `download`)
     - [KasusEvaluasiController.php](file:///d:/laragon/www/pintera-app/app/Http/Controllers/KasusEvaluasiController.php) (`store`)
   - Otorisasi sebenarnya pada 8 endpoint tersebut tetap aman menggunakan `KasusPolicy::view()`, `downloadLampiran()`, `kelolaSesiTugas()`, atau pengecekan internal lainnya.

4. **Task 4: Pengujian Fitur Reproduksi Bug & Regresi** (`03c40c51`)
   - Menambahkan helper `buatKaryawanPoolViaRoleSeederAsli()` and 5 test fitur di [KasusKonselorAksesTest.php](file:///d:/laragon/www/pintera-app/tests/Feature/KasusKonselorAksesTest.php) untuk memverifikasi behavior riil.
   - Tes mencakup: akses 8 endpoint untuk pool konselor, blokir lintas-lembaga yayasan, blokir satpam/karyawan biasa (403), akses assignment historis selesai, dan bypass capability eksplisit.

5. **Task 5: Full Regression Check**
   - Menjalankan seluruh suite pengujian (2419 passed, 4 skipped, 0 failed).
   - Memastikan tidak ada penyimpangan style code menggunakan `vendor/bin/pint --dirty --format agent`.

---

## Keputusan Penting yang Diambil

- **Invariant `withoutGlobalScope(TenantScope::class)`**:
  Sesuai dengan keputusan desain spec §2.2(c), bypass `TenantScope` dipertahankan dalam `viewAny()` saat memetakan `karyawan()` dan mencari kasus `konselor_karyawan_id`. Hal ini penting agar karyawan pool yayasan (yang tidak memiliki `lembaga_id`) tetap valid mengakses menu Kasus Pendampingan untuk kasus lintas lembaga dalam yayasan tersebut.
- **OR Independen**:
  Pengecekan capability (`kasus.view`) dan fakta penugasan (`konselor_karyawan_id`) diposisikan sebagai OR yang berdiri sendiri, sehingga role administratif non-karyawan (seperti Operator Akademik atau Guru BK) tetap mendapatkan akses instan tanpa harus ditunjuk sebagai konselor terlebih dahulu.
- **Request Validation Key Mapping**:
  Pada pengujian Task 4, key payload diselaraskan menggunakan `tanggal_mulai` and `tanggal_selesai` (bukan `mulai_pada` and `batas_selesai_pada`) agar lolos validasi request `PreviewKasusTugasBatchRequest` / `StoreKasusTugasBatchRequest`.

---

## Hal yang Masih Perlu Direview Manusia / Claude

- **Verifikasi Commit**:
  Terdapat 4 commit lokal baru di branch `rbac-v2` yang belum di-push ke remote.
- **Pengecekan Manual**:
  Akun satpam (atau karyawan lain yang baru dibuat dan tidak ditugaskan sebagai konselor) sekarang dijamin mendapatkan respon **403 Forbidden** jika mencoba membuka halaman menu Kasus Pendampingan (`/admin/kasus`), sementara menu tersebut tetap terbuka dengan aman bagi Guru BK/Konselor terikat.
