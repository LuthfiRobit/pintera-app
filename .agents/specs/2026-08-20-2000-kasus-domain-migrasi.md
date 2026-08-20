# Migrasi Modul Pendampingan/Kasus ke Domains\Kasus — Spec

## 1. Latar Belakang

Sub-task pertama dari master roadmap `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md` (§4, prioritas 1). Berbeda dari Sub-Task 05 (Akademik) yang domainnya sudah ada, ini domain **BARU sepenuhnya** — `app/Domains/Kasus/` belum pernah ada.

Cakupan nyata (dikoreksi dari perkiraan awal "4 controller/483 baris" yang salah — itu cuma menghitung yang tampil di sidebar):

| Controller | Baris | Route | Fungsi |
|---|---:|---|---|
| `Admin\KasusController` | 142 | `admin.kasus.*` | index, triase, assign-konselor, destroy, restore |
| `KasusController` (top-level) | 211 | `kasus.*` | index, create, store, show — untuk siswa/orang-tua/guru/konselor |
| `Admin\KasusAksesLogController` | 75 | `admin.kasus.log-akses` | log akses klinis |
| `Admin\KasusTerhapusController` | 55 | `admin.kasus.terhapus` | daftar kasus terhapus |
| `KasusConsentController` | 72 | `kasus.consent.approve` | approve consent orang tua |
| `KasusSesiController` | 96 | `kasus.sesi.*` | jadwalkan sesi, update status sesi |
| `KasusTugasController` | 106 | `kasus.tugas.*` | beri tugas batch, tandai selesai |
| `KasusTugasBatchPreviewController` | 47 | `kasus.tugas.preview` | preview batch tugas (AJAX) |
| `KasusTugasSubmissionController` | 129 | `kasus.tugas.submission.*` | submit bukti, review, download lampiran |
| `KasusEvaluasiController` | 109 | `kasus.evaluasi.store` | catat evaluasi + transisi status kasus |
| **Total** | **1042** | | |

6 model terkait, semua di `app/Models/`, 0 domain usage saat ini: `Kasus`, `KasusConsent`, `KasusSesi`, `KasusTugas`, `KasusTugasSubmission`, `KasusEvaluasi`.

2 service lama di `app/Services/`: `KonselorAllocationResolver`, `TugasBatchGenerator`.

Standar arsitektur wajib: `.agents/skills/laravel-feature-standard/SKILL.md` dan §3 (Prinsip Arsitektur Mengikat) `.agents/plans/2026-08-20-1800-master-refactor-domain-pattern.md`.

## 2. Audit Blast-Radius Model (hasil `grep` nyata, 2026-08-20)

| Model | File pemakai di luar ekosistem Kasus sendiri (test/factory/notification/mail/console command miliknya sendiri) | Keputusan |
|---|---|---|
| `Kasus` | 1 — `Admin\DashboardController` (widget hitungan) | **Pindah** ke `Domains\Kasus\Models\` |
| `KasusConsent` | 0 | **Pindah** |
| `KasusSesi` | 0 | **Pindah** |
| `KasusTugas` | 0 | **Pindah** |
| `KasusTugasSubmission` | 0 | **Pindah** |
| `KasusEvaluasi` | 0 | **Pindah** |

Semua 6 model layak dipindah — beda dari Akademik kemarin di mana model inti (`Lembaga`/`Kelas`/`Siswa`) harus tetap karena blast-radius ratusan file. Di sini cuma 1 titik sentuh luar (`Admin\DashboardController`), diupdate importnya seperti biasa.

**Notification & Mail class** (`KasusDiajukan`, `KasusEskalasi`, `KasusSelesai`, `KasusDikembalikan`, `ConsentDisetujui`, `KonselorDipilih`, `SesiDijadwalkan`, `SesiReminder`, `TugasBatchDibuat`, `TugasSelesai`, `SubmissionRevisi` — total 11 pasang Notification+Mail): **TETAP** di `app/Notifications/` dan `app/Mail/` (konvensi standar Laravel, keputusan eksplisit 2026-08-20 — bukan business-logic Action, infrastruktur generik).

## 3. Struktur Domain

```
app/Domains/Kasus/
├── Actions/
│   ├── Manajemen/       (triase, assign-konselor, destroy, restore, log-akses, terhapus)
│   ├── Pengajuan/       (create/store/show/index kasus)
│   ├── Consent/         (approve consent)
│   ├── Sesi/            (jadwalkan sesi, update status)
│   ├── Tugas/           (beri tugas batch, preview batch, tandai selesai)
│   ├── Submission/      (submit bukti, review, download)
│   └── Evaluasi/        (catat evaluasi + transisi status)
├── DataTransferObjects/
├── Models/
│   ├── Kasus.php
│   ├── KasusConsent.php
│   ├── KasusSesi.php
│   ├── KasusTugas.php
│   ├── KasusTugasSubmission.php
│   └── KasusEvaluasi.php
├── Policies/
│   └── KasusPolicy.php  (BARU)
└── Services/
    ├── KonselorAllocationResolver.php
    └── TugasBatchGenerator.php
```

7 sub-area Actions dipisah per boundary otorisasi/state yang berbeda (Manajemen = admin only, Pengajuan = siswa/ortu/guru, Sesi/Tugas/Submission = konselor-only mutations, Evaluasi = konselor ATAU admin tergantung status kasus), mengikuti pola Akademik (`Jadwal/`, `Penilaian/`, dst).

## 4. `KasusPolicy` — Konsolidasi Otorisasi

**Masalah yang ditemukan (audit kode 2026-08-20):** logic `assertKonselorPemegangKasus` (cek apakah user adalah konselor guru/karyawan pemegang kasus ini) diduplikasi 4 kali:
1. Trait `App\Http\Controllers\Concerns\AssertsKonselorPemegangKasus` — dipakai `KasusTugasController`, `KasusTugasBatchPreviewController`.
2. Private method identik di `KasusSesiController::assertKonselorPemegangKasus()`.
3. Private method identik di `KasusTugasSubmissionController::assertKonselorPemegangKasus()`.
4. Inline identik di `KasusEvaluasiController::store()` (baris 31-33, tanpa method terpisah).

Kombinasi otorisasi resource-specific lain yang berulang dengan variasi (`isSubmitter`/`isKontakUtama`/`isKonselor`/`isSiswaTerkait`/`isTriaseAdmin`) di `KasusController::show()` dan `KasusTugasSubmissionController::download()`.

**Solusi:** `App\Domains\Kasus\Policies\KasusPolicy` dengan method:
- `isKonselor(User $user, Kasus $kasus): bool` — private/protected helper internal, dipakai method Policy lain. Menggantikan SEMUA 4 titik duplikasi di atas.
- `view(User $user, Kasus $kasus): bool` — menggantikan kombinasi `isSubmitter || isKontakUtama || isTriaseAdmin || isKonselor || isSiswaTerkait` di `KasusController::show()`.
- `downloadLampiran(User $user, Kasus $kasus, KasusTugasSubmission $submission): bool` — menggantikan kombinasi mirip (tapi tidak identik — beda variabel `isSubmitter`) di `KasusTugasSubmissionController::download()`.
- `kelolaSesiTugas(User $user, Kasus $kasus): bool` — alias `isKonselor` untuk konteks Sesi/Tugas/Submission-review, dipakai menggantikan trait & 2 method privat yang terduplikasi.

**Registrasi:** karena model `Kasus` pindah ke `Domains\Kasus\Models\Kasus`, auto-discovery Policy Laravel MUNGKIN otomatis menebak `Domains\Kasus\Policies\KasusPolicy` (menebak dari namespace model, ganti segmen `Models` jadi `Policies`). Plan implementasi WAJIB memverifikasi ini dengan test nyata (bukan asumsi) — kalau auto-discovery gagal, daftarkan eksplisit di `AuthServiceProvider::$policies` sebagai fallback.

**Zero-behavior-change:** hasil akhir izin/tolak untuk SETIAP kombinasi role yang sudah ada harus identik persis. Ini murni konsolidasi lokasi kode, bukan perubahan aturan otorisasi. Test WAJIB membuktikan tiap kombinasi lama (isSubmitter, isKontakUtama, isKonselor, isSiswaTerkait, isTriaseAdmin, dan kombinasi silang) tetap menghasilkan keputusan yang sama.

## 5. Route::bind Bypass (kendala keamanan wajib dipertahankan)

`routes/kasus.php` punya `Route::bind('kasus', ...)` yang SENGAJA bypass `TenantScope`:

```php
// Orang tua accounts have no lembaga_id of their own, so implicit route-model binding's
// default TenantScope-applied lookup would 404 on {kasus} before the controller's own
// isSubmitter/isKontakUtama/kasus.triase authorization logic ever runs. Bind explicitly,
// bypassing the tenant scope; real authorization stays inside each controller action.
Route::bind('kasus', function ($value) {
    return \App\Models\Kasus::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
        ->withTrashed()
        ->findOrFail($value);
});
```

**WAJIB dipertahankan tanpa perubahan** (cuma update namespace `App\Models\Kasus` → `App\Domains\Kasus\Models\Kasus` di baris binding). Action dan `KasusPolicy` menerima objek `Kasus $kasus` yang SUDAH di-resolve lewat binding ini — TIDAK PERNAH query ulang model di dalam Action/Policy dengan asumsi TenantScope normal berlaku, karena itu akan mematahkan akses orang tua persis seperti yang dijelaskan di komentar.

Pola serupa (query eksplisit `withoutGlobalScope(TenantScope::class)`) muncul puluhan kali di 10 controller ini untuk relasi `siswa()`, `konselorGuru()`, `konselorKaryawan()`, `karyawan()`, `user()` — SEMUA harus dipertahankan persis di Action hasil migrasi, dengan komentar penjelasan yang sama dipindah bersama kodenya (bukan dihapus sebagai "housekeeping").

## 6. Service — Pindah ke Domain

- `app/Services/KonselorAllocationResolver.php` → `app/Domains/Kasus/Services/KonselorAllocationResolver.php` — dipakai `Admin\KasusController::triase()` dan `::assignKonselor()`.
- `app/Services/TugasBatchGenerator.php` → `app/Domains/Kasus/Services/TugasBatchGenerator.php` — dipakai `KasusTugasController::store()` dan `KasusTugasBatchPreviewController::preview()`.

Sama seperti `KalenderAkademikResolver` di Sub-Task 05 — pindah lokasi + namespace, TIDAK ada perubahan logic.

## 7. View — WAJIB Ikut Pindah (bukan ditunda)

Sesuai §3.3 master roadmap, view ikut pindah bersamaan dengan task migrasi controllernya, bukan ditunda seperti kasus controller-namespace.

**Temuan penting:** halaman `resources/views/kasus/*` diakses LINTAS-ROLE (siswa, orang tua, guru, karyawan-konselor, admin-triase-viewer) lewat SATU controller/view yang sama, di URL `/kasus/...` yang TIDAK diprefix scope apapun (keputusan FASE 5.1). Konvensi `portals/[scope]/[domain]/` yang ada mengasumsikan satu domain dimiliki satu scope — TIDAK cocok di sini. Keputusan (2026-08-20): view lintas-role ini masuk `portals/kasus/` **TANPA segmen scope** (mencerminkan langsung fakta routing yang sudah tidak berscope, dan konsisten dengan taksonomi scope resmi SKILL.md §1 yang tidak punya kategori "bersama/shared").

| File saat ini | View name saat ini | Target | View name baru |
|---|---|---|---|
| `resources/views/kasus/index.blade.php` | `kasus.index` | `portals/kasus/index.blade.php` | `portals.kasus.index` |
| `resources/views/kasus/create.blade.php` | `kasus.create` | `portals/kasus/create.blade.php` | `portals.kasus.create` |
| `resources/views/kasus/show.blade.php` | `kasus.show` | `portals/kasus/show.blade.php` | `portals.kasus.show` |
| `resources/views/kasus/partials/_tab-info.blade.php` | (partial, `@include`) | `portals/kasus/partials/_tab-info.blade.php` | — |
| `resources/views/kasus/partials/_tab-sesi.blade.php` | (partial, `@include`) | `portals/kasus/partials/_tab-sesi.blade.php` | — |
| `resources/views/kasus/partials/_tab-tugas.blade.php` | (partial, `@include`) | `portals/kasus/partials/_tab-tugas.blade.php` | — |
| `resources/views/kasus/partials/_tab-evaluasi.blade.php` | (partial, `@include`) | `portals/kasus/partials/_tab-evaluasi.blade.php` | — |
| `resources/views/admin/kasus/index.blade.php` | `admin.kasus.index` | `portals/lembaga/kasus/index.blade.php` | `portals.lembaga.kasus.index` |
| `resources/views/admin/kasus/triase.blade.php` | `admin.kasus.triase` | `portals/lembaga/kasus/triase.blade.php` | `portals.lembaga.kasus.triase` |
| `resources/views/admin/kasus/akses-log.blade.php` | `admin.kasus.akses-log` | `portals/lembaga/kasus/akses-log.blade.php` | `portals.lembaga.kasus.akses-log` |
| `resources/views/admin/kasus/terhapus.blade.php` | `admin.kasus.terhapus` | `portals/lembaga/kasus/terhapus.blade.php` | `portals.lembaga.kasus.terhapus` |

**Peringatan wajib** (insiden nyata Sub-Task 05): `resources/views/kasus/show.blade.php` dan 4 partial tab-nya KEMUNGKINAN BESAR punya `route('kasus.xxx')` calls (nama ROUTE, tidak berubah) berdampingan dengan `@include('kasus.partials._tab-xxx')` (nama VIEW, berubah). Cari-ganti WAJIB dibatasi hanya pada `view(`/`@include(`/`assertViewIs(`/`->name()`, verifikasi dengan `grep -rn "route('portals\." resources/views/portals` (harus kosong) sebelum lanjut — JANGAN blanket-replace satu file penuh.

## 8. Prinsip Arsitektur (ringkasan penerapan)

- **Controller thin**: tiap method jadi validasi → DTO → Action/Policy → response. Otorisasi resource-specific pindah ke `KasusPolicy` (`$this->authorize('view', $kasus)`), otorisasi permission generik tetap `$this->authorize('kasus.xxx')` seperti sekarang.
- **Action**: 1 use-case per class, method `execute()`, DTO sebagai input (bukan `Request`), `DB::transaction()` untuk mutasi multi-tabel (SUDAH dipakai konsisten di kode asli — `assignKonselor`, `destroy`, `restore`, `store` sesi/tugas, `evaluasi` semua sudah `DB::transaction`, dipertahankan).
- **Model pindahan**: cuma `$fillable`/`casts()`/relationship/scope. Tidak ada business logic di model.
- **Controller namespace TIDAK dipindah** (`Admin\KasusController`, `KasusController` top-level, dst tetap di lokasi sekarang) — sesuai §3.3 master roadmap, tidak retroaktif.
- **Zero-behavior-change** untuk SEMUA bagian KECUALI konsolidasi `KasusPolicy` (yang sendiri harus menghasilkan keputusan otorisasi identik, bukan celah baru) — tidak ada perubahan alur bisnis/state-machine/pesan yang disengaja di sub-task ini (beda dengan Sub-Task 05 yang punya 1 perubahan perilaku sengaja di `salinJadwal`).

## 9. Testing

- Test lama (semua test `Kasus*` yang ada — daftar lengkap ada di hasil `grep` §2, puluhan file) HARUS tetap hijau tanpa modifikasi assertion, KECUALI update import path (`App\Models\Kasus` → `App\Domains\Kasus\Models\Kasus`, dst) dan update nama view (`assertViewIs`) sesuai §7.
- Test baru WAJIB untuk `KasusPolicy`: setiap method (`view`, `downloadLampiran`, `kelolaSesiTugas`) diuji untuk SEMUA kombinasi role yang tadinya diperiksa inline (siswa terkait, orang tua kontak utama, orang tua bukan kontak utama, guru submitter, guru bukan submitter, konselor guru, konselor karyawan, admin triase 1-lembaga, admin triase yayasan, user tanpa relasi apapun).
- Full suite HANYA di task terakhir, minta izin eksplisit user dulu.

## 10. Di Luar Cakupan

- Namespace controller (`Admin\KasusController` dkk tetap, tidak dipindah ke `Lembaga\Kasus\` dst).
- Notification & Mail class (tetap di lokasi standar Laravel, keputusan eksplisit §2).
- Perubahan state machine kasus (`Diajukan → MenungguConsent → Ditugaskan → Berjalan → Eskalasi → Selesai`) — dipertahankan persis, tidak ada penyederhanaan/perluasan.
- Dashboard widget di `Admin\DashboardController` — cuma update import, tidak direfactor lebih jauh.

## 11. Asumsi

- Baseline kode: commit `5d93c60` (HEAD `rbac-v2` saat spec ditulis). Verifikasi ulang isi file kalau ada commit baru sebelum plan dieksekusi.
- Auto-discovery Policy Laravel untuk model bernamespace domain (§4) diverifikasi lewat test nyata di plan — bukan diasumsikan bekerja.
